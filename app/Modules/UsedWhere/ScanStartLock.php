<?php
/**
 * The lock that lets only one request start a usage scan at a time.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\UsedWhere;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Start lock shared by every way of starting a usage scan: the
 * tsmlt/start-usage-scan ability and the Used Where page's Re-scan.
 *
 * The lock is an options row holding the time and a random token. It is
 * taken with a plain INSERT (the table's unique option_name key accepts only
 * one), an abandoned lock is taken over with an UPDATE that must still find
 * the old value, and it is released with a DELETE that must find the
 * caller's own value. A database error never counts as holding the lock.
 */
class ScanStartLock {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Option used as the start lock.
	 *
	 * @var string
	 */
	const OPTION = 'tsmlt_used_where_scan_start_lock';

	/**
	 * Seconds after which a start lock left behind by a failed request is
	 * ignored.
	 *
	 * @var int
	 */
	const SECONDS = 60;

	/**
	 * Autoload value of the start lock row.
	 *
	 * @var string
	 */
	const AUTOLOAD = 'off';

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Take the start lock.
	 *
	 * A plain INSERT takes a free lock: the options table's unique key on
	 * option_name lets exactly one request insert it. A lock older than
	 * START_LOCK_SECONDS is abandoned and is taken over with an UPDATE that
	 * must still find the value just read, so of several requests only one
	 * takes it. add_option() is not used: it checks first and then runs
	 * INSERT ... ON DUPLICATE KEY UPDATE, so two requests could both get the
	 * lock. Lock rows are read from the database, not the option cache.
	 *
	 * @return string|null|false This request's lock value; null when another
	 *                           request holds the lock; false on a database
	 *                           error.
	 */
	public function acquire() {
		$value = time() . ':' . bin2hex( random_bytes( 16 ) );
		if ( $this->insert_lock( $value ) ) {
			return $value;
		}

		$current = $this->read_lock();
		if ( false === $current ) {
			return false;
		}
		if ( null === $current ) {
			// Released after the INSERT failed, or the INSERT failed for
			// another reason: try once more.
			return $this->insert_lock( $value ) ? $value : false;
		}

		$taken = (int) strtok( $current, ':' );
		if ( $taken > 0 && ( time() - $taken ) <= self::SECONDS ) {
			return null;
		}
		return $this->replace_lock( $current, $value ) ? $value : null;
	}

	/**
	 * Release the start lock, only if it is still this request's. A lock
	 * taken over by another request after it expired is left alone; a lock
	 * that cannot be deleted expires after START_LOCK_SECONDS.
	 *
	 * @param string $value This request's lock value.
	 *
	 * @return void
	 */
	public function release( string $value ): void {
		self::lock_query(
			static function () use ( $value ) {
				return Fns::DB()->delete( 'options' )
					->where( 'option_name', '=', self::OPTION )
					->andWhere( 'option_value', '=', $value )
					->execute();
			}
		);
		self::forget_cached_lock();
	}

	/**
	 * Insert the lock row.
	 *
	 * @param string $value Lock value.
	 *
	 * @return bool Whether exactly one row was inserted.
	 */
	private function insert_lock( string $value ): bool {
		$inserted = self::lock_query(
			static function () use ( $value ) {
				return Fns::DB()->insert(
					'options',
					[
						[
							'option_name'  => self::OPTION,
							'option_value' => $value,
							'autoload'     => self::AUTOLOAD,
						],
					]
				)->execute();
			}
		);
		self::forget_cached_lock();
		return 1 === $inserted;
	}

	/**
	 * Replace an abandoned lock, only if it still holds the value read.
	 *
	 * @param string $old   Value read.
	 * @param string $value New value.
	 *
	 * @return bool Whether this request replaced it.
	 */
	private function replace_lock( string $old, string $value ): bool {
		$updated = self::lock_query(
			static function () use ( $old, $value ) {
				global $wpdb;
				// The query builder's update does not return the row count.
				Fns::DB()->update( 'options', [ 'option_value' => $value ] )
					->where( 'option_name', '=', self::OPTION )
					->andWhere( 'option_value', '=', $old )
					->execute();
				return '' === (string) $wpdb->last_error ? (int) $wpdb->rows_affected : false;
			}
		);
		self::forget_cached_lock();
		return 1 === $updated;
	}

	/**
	 * The lock value stored in the database.
	 *
	 * @return string|null|false The value; null when there is no lock; false
	 *                           on a database error.
	 */
	private function read_lock() {
		global $wpdb;
		$rows = self::lock_query(
			static function () {
				return Fns::DB()->select( 'option_value' )
					->from( 'options' )
					->where( 'option_name', '=', self::OPTION )
					->get();
			}
		);
		if ( '' !== (string) $wpdb->last_error || ! is_array( $rows ) ) {
			return false;
		}
		return isset( $rows[0]['option_value'] ) ? (string) $rows[0]['option_value'] : null;
	}

	/**
	 * Run a lock query with database errors hidden (a lost race is an
	 * expected duplicate-key error); an exception counts as a failure.
	 *
	 * @param callable $query Query.
	 *
	 * @return mixed The query's result, or false.
	 */
	private static function lock_query( callable $query ) {
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
	 * Drop the cached copy of the lock option, so get_option() matches the
	 * database after the direct queries above.
	 *
	 * @return void
	 */
	private static function forget_cached_lock(): void {
		wp_cache_delete( self::OPTION, 'options' );
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ self::OPTION ] ) ) {
			unset( $notoptions[ self::OPTION ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}
	}
}
