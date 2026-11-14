<?php
/**
 * The lock that lets one Used Where scan batch run at a time.
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
 * Batch lock held while a scan batch runs.
 *
 * The lock is an options row holding the time and a random token, owned by
 * the batch that wrote it. It is taken with a plain INSERT (the table's
 * unique option_name key accepts only one), a lock older than SECONDS (left
 * by a batch that died) is taken over with an UPDATE that must still find
 * the old value, and it is released with a DELETE that must find the
 * owner's own value, so no other batch, Cancel, Clear Results or stall
 * recovery ever releases it. A database error never counts as holding the
 * lock.
 *
 * The lock only saves duplicate work: a batch's results are saved by
 * BatchTransaction, which lets only the batch that owns the scan's next
 * offset save anything.
 */
class ScanBatchLock {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Option used as the batch lock.
	 *
	 * @var string
	 */
	const OPTION = 'tsmlt_used_where_scan_batch_lock';

	/**
	 * Seconds after which a lock left behind by a batch that died is taken
	 * over.
	 *
	 * @var int
	 */
	const SECONDS = 90;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Take the batch lock.
	 *
	 * @return string|null|false This batch's lock value; null when another
	 *                           batch holds the lock; false on a database
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
			// Released after the INSERT failed: try once more.
			return $this->insert_lock( $value ) ? $value : false;
		}

		$taken = (int) strtok( $current, ':' );
		if ( $taken > 0 && ( time() - $taken ) < self::SECONDS ) {
			return null;
		}
		return $this->replace_lock( $current, $value ) ? $value : null;
	}

	/**
	 * Release the batch lock, only if it is still this batch's.
	 *
	 * @param string $value This batch's lock value.
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
	 * The lock value stored in the database.
	 *
	 * @return string|null|false The value; null when there is no lock; false
	 *                           on a database error.
	 */
	public function read_lock() {
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
							'autoload'     => 'off',
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
	 * @return bool Whether this batch replaced it.
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
