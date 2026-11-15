<?php
/**
 * Per-attachment claim for the MCP trash ability.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Trash;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;

/**
 * Lets only one MCP trash or restore request at a time re-check and change
 * a given attachment, so two requests cannot both pass the checks and both
 * report the change as theirs.
 *
 * A claim is an options row per attachment holding a lease (the time until
 * which it is held) and a random value, as ScanStartLock does: taken with a
 * plain INSERT (the unique option_name key accepts only one), renewed by
 * its owner with an UPDATE that must still find its own value, taken over
 * after its lease ran out with an UPDATE that must still find the old
 * value, and released with a DELETE that must find the caller's own value.
 * The lease is LEASE seconds, renewed right before the native call, so a
 * long trash or restore keeps its claim; a claim left by a request that
 * died is taken over once its lease has run out. add_option() is not used:
 * it checks first and then runs INSERT ... ON DUPLICATE KEY UPDATE, so two
 * requests could both succeed. A database error never counts as a claim.
 */
class TrashClaim {

	/**
	 * Prefix of the claim option, followed by the attachment ID.
	 *
	 * @var string
	 */
	const PREFIX = 'tsmlt_trash_claim_';

	/**
	 * Seconds a claim is held from when it was taken or last renewed. Only
	 * after this can another request take it over (crash recovery).
	 *
	 * @var int
	 */
	const LEASE = HOUR_IN_SECONDS;

	/**
	 * Autoload value of a claim row.
	 *
	 * @var string
	 */
	const AUTOLOAD = 'off';

	/**
	 * Claim an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string|null|false This request's claim value; null when another
	 *                           request holds it; false on a database error.
	 */
	public static function acquire( int $attachment_id ) {
		$value = self::value();
		if ( self::insert( $attachment_id, $value ) ) {
			return $value;
		}
		$current = self::read( $attachment_id );
		if ( false === $current ) {
			return false;
		}
		if ( null === $current ) {
			// Released after the INSERT failed, or the INSERT failed for
			// another reason: try once more.
			return self::insert( $attachment_id, $value ) ? $value : false;
		}
		if ( self::held_until( $current ) >= time() ) {
			return null;
		}
		return self::replace( $attachment_id, $current, $value ) ? $value : null;
	}

	/**
	 * Renew this request's claim for another LEASE seconds, only while it is
	 * still this request's.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $value         This request's claim value.
	 *
	 * @return string|null The renewed value; null when the claim is no
	 *                     longer this request's or could not be renewed.
	 */
	public static function renew( int $attachment_id, string $value ): ?string {
		$renewed = self::value();
		return self::replace( $attachment_id, $value, $renewed ) ? $renewed : null;
	}

	/**
	 * Until when a stored claim is held (Unix time); 0 for a value that
	 * cannot be read, which counts as expired.
	 *
	 * @param string $value Stored claim value.
	 *
	 * @return int
	 */
	public static function held_until( string $value ): int {
		$until = strtok( $value, ':' );
		return false !== $until && ctype_digit( $until ) ? (int) $until : 0;
	}

	/**
	 * A new claim value: the lease end and a random part.
	 *
	 * @return string
	 */
	private static function value(): string {
		return ( time() + self::LEASE ) . ':' . bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Release a claim, only if it is still this request's. A claim that
	 * cannot be deleted can be taken over once its lease has run out.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $value         This request's claim value.
	 *
	 * @return void
	 */
	public static function release( int $attachment_id, string $value ): void {
		self::claim_query(
			static function () use ( $attachment_id, $value ) {
				return Fns::DB()->delete( 'options' )
					->where( 'option_name', '=', self::option( $attachment_id ) )
					->andWhere( 'option_value', '=', $value )
					->execute();
			}
		);
		self::forget_cached( $attachment_id );
	}

	/**
	 * Option name of an attachment's claim.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string
	 */
	public static function option( int $attachment_id ): string {
		return self::PREFIX . $attachment_id;
	}

	/**
	 * Insert the claim row.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $value         Claim value.
	 *
	 * @return bool Whether exactly one row was inserted.
	 */
	private static function insert( int $attachment_id, string $value ): bool {
		$inserted = self::claim_query(
			static function () use ( $attachment_id, $value ) {
				return Fns::DB()->insert(
					'options',
					[
						[
							'option_name'  => self::option( $attachment_id ),
							'option_value' => $value,
							'autoload'     => self::AUTOLOAD,
						],
					]
				)->execute();
			}
		);
		self::forget_cached( $attachment_id );
		return 1 === $inserted;
	}

	/**
	 * Replace a claim value (an expired claim, or the owner's own on
	 * renewal), only if it still holds the value read.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $old           Value read.
	 * @param string $value         New value.
	 *
	 * @return bool Whether this request replaced it.
	 */
	private static function replace( int $attachment_id, string $old, string $value ): bool {
		$updated = self::claim_query(
			static function () use ( $attachment_id, $old, $value ) {
				global $wpdb;
				// The query builder's update does not return the row count.
				Fns::DB()->update( 'options', [ 'option_value' => $value ] )
					->where( 'option_name', '=', self::option( $attachment_id ) )
					->andWhere( 'option_value', '=', $old )
					->execute();
				return '' === (string) $wpdb->last_error ? (int) $wpdb->rows_affected : false;
			}
		);
		self::forget_cached( $attachment_id );
		return 1 === $updated;
	}

	/**
	 * The claim value stored in the database.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string|null|false The value; null when there is no claim;
	 *                           false on a database error.
	 */
	private static function read( int $attachment_id ) {
		global $wpdb;
		$rows = self::claim_query(
			static function () use ( $attachment_id ) {
				return Fns::DB()->select( 'option_value' )
					->from( 'options' )
					->where( 'option_name', '=', self::option( $attachment_id ) )
					->get();
			}
		);
		if ( '' !== (string) $wpdb->last_error || ! is_array( $rows ) ) {
			return false;
		}
		return isset( $rows[0]['option_value'] ) ? (string) $rows[0]['option_value'] : null;
	}

	/**
	 * Run a claim query with database errors hidden (a lost race is an
	 * expected duplicate-key error); an exception counts as a failure.
	 *
	 * @param callable $query Query.
	 *
	 * @return mixed The query's result, or false.
	 */
	private static function claim_query( callable $query ) {
		global $wpdb;
		$suppressed = $wpdb->suppress_errors( true );
		try {
			$result = $query();
		} catch ( \Throwable $e ) {
			$wpdb->last_error = $e->getMessage();
			$result           = false;
		}
		$wpdb->suppress_errors( $suppressed );
		return $result;
	}

	/**
	 * Drop cached copies of a claim option, so get_option() matches the
	 * database after the direct queries above.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	private static function forget_cached( int $attachment_id ): void {
		$option = self::option( $attachment_id );
		wp_cache_delete( $option, 'options' );
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ $option ] ) ) {
			unset( $notoptions[ $option ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}
	}
}
