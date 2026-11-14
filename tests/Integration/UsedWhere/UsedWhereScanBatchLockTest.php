<?php
/**
 * The Used Where scan batch lock is released only by its owner: not by
 * another batch, a batch whose lock was taken over, Cancel, Clear Results
 * or stall recovery. A lock left by a dead batch is taken over after
 * ScanBatchLock::SECONDS. A batch that finds the lock held tries its offset
 * again later.
 *
 * Outgoing HTTP is blocked; every post and attachment is a generated
 * fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\UsedWhere\ScanBatchLock;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereScanBatchLockTest
 */
class UsedWhereScanBatchLockTest extends WP_UnitTestCase {

	/**
	 * Status option.
	 *
	 * @var string
	 */
	const STATUS = 'tsmlt_used_where_scan_status';

	/**
	 * Lock value written by another batch while this one runs, or ''.
	 *
	 * @var string
	 */
	private $takeover = '';

	/**
	 * No lock, no scan, no scheduled batch, no HTTP.
	 */
	public function set_up() {
		parent::set_up();
		self::write_lock( null );
		delete_option( self::STATUS );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		$this->takeover = '';
	}

	/**
	 * Remove filters and scheduled batches.
	 */
	public function tear_down() {
		remove_action( 'update_option_' . self::STATUS, [ $this, 'take_over_lock' ] );
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		remove_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		parent::tear_down();
	}

	/**
	 * Refuse outgoing HTTP requests.
	 *
	 * @return WP_Error
	 */
	public function block_http() {
		return new WP_Error( 'blocked', 'No HTTP in tests.' );
	}

	/**
	 * Replace the lock with another batch's, once, while a batch runs.
	 */
	public function take_over_lock() {
		if ( '' === $this->takeover ) {
			$this->takeover = time() . ':' . str_repeat( 'b', 32 );
			self::write_lock( $this->takeover );
		}
	}

	/**
	 * Write (or delete, for null) the lock row directly.
	 *
	 * @param string|null $value Lock value.
	 */
	private static function write_lock( ?string $value ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Test fixture.
		$wpdb->delete( $wpdb->options, [ 'option_name' => ScanBatchLock::OPTION ] );
		if ( null !== $value ) {
			$wpdb->insert(
				$wpdb->options,
				[
					'option_name'  => ScanBatchLock::OPTION,
					'option_value' => $value,
					'autoload'     => 'off',
				]
			);
		}
		// phpcs:enable
		wp_cache_delete( ScanBatchLock::OPTION, 'options' );
	}

	/**
	 * The lock row in the database, or null.
	 *
	 * @return string|null
	 */
	private static function stored_lock(): ?string {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", ScanBatchLock::OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
		return null === $value ? null : (string) $value;
	}

	/**
	 * An active scan expecting a batch at an offset.
	 *
	 * @param int    $offset       Next offset.
	 * @param string $last_tick_at Last batch time.
	 */
	private static function active_scan( int $offset, string $last_tick_at = '' ) {
		update_option(
			self::STATUS,
			[
				'state'        => 'running',
				'processed'    => $offset,
				'total'        => 50,
				'next_offset'  => $offset,
				'scan_id'      => 'scan-a',
				'last_tick_at' => $last_tick_at,
				'last_error'   => '',
			],
			false
		);
	}

	/**
	 * One owner: the first acquire gets a time-and-token value, a second is
	 * refused, a wrong token releases nothing, the owner releases.
	 */
	public function test_only_the_owner_releases() {
		$lock = ScanBatchLock::instance();
		$mine = $lock->acquire();

		$this->assertMatchesRegularExpression( '/^\d+:[a-f0-9]{32}$/', $mine );
		$this->assertNull( $lock->acquire() );
		$lock->release( time() . ':' . str_repeat( 'f', 32 ) );
		$this->assertSame( $mine, self::stored_lock(), 'Another token releases nothing.' );
		$lock->release( $mine );
		$this->assertNull( self::stored_lock() );
	}

	/**
	 * A lock older than SECONDS is taken over; its old owner's release then
	 * leaves the new owner's lock alone.
	 */
	public function test_abandoned_lock_is_taken_over_and_kept() {
		$old = ( time() - ScanBatchLock::SECONDS - 5 ) . ':' . str_repeat( 'a', 32 );
		self::write_lock( $old );

		$new = ScanBatchLock::instance()->acquire();
		ScanBatchLock::instance()->release( $old );

		$this->assertIsString( $new );
		$this->assertNotSame( $old, $new );
		$this->assertSame( $new, self::stored_lock() );
	}

	/**
	 * Cancel, Clear Results and stall recovery leave a batch's lock alone.
	 */
	public function test_cancel_clear_and_stall_recovery_keep_the_lock() {
		$held = ScanBatchLock::instance()->acquire();
		$this->assertIsString( $held );

		self::active_scan( 10, '2000-01-01 00:00:00' );
		UsedWhereScanner::instance()->get_scan_status();
		$this->assertSame( $held, self::stored_lock(), 'Stall recovery keeps it.' );
		$this->assertNotFalse( wp_next_scheduled( UsedWhereScanner::SCAN_TICK_HOOK, [ 10 ] ), 'Stall recovery still re-arms the scan.' );

		UsedWhereScanner::instance()->cancel_scheduled_scan();
		$this->assertSame( $held, self::stored_lock(), 'Cancel keeps it.' );

		UsedWhereScanner::instance()->clear_scan();
		$this->assertSame( $held, self::stored_lock(), 'Clear Results keeps it.' );
	}

	/**
	 * A batch that finds the lock held leaves it alone, writes nothing, and
	 * schedules its offset again.
	 */
	public function test_batch_finding_the_lock_held_tries_again_later() {
		$held = ScanBatchLock::instance()->acquire();
		self::active_scan( 10 );
		$before = get_option( self::STATUS );

		UsedWhereScanner::instance()->run_tick_batch( 10 );

		$this->assertSame( $held, self::stored_lock() );
		$this->assertSame( $before, get_option( self::STATUS ) );
		$this->assertNotFalse( wp_next_scheduled( UsedWhereScanner::SCAN_TICK_HOOK, [ 10 ] ) );
	}

	/**
	 * A duplicate of an offset already done, finding the lock held, is not
	 * scheduled again.
	 */
	public function test_stale_duplicate_finding_the_lock_held_stops() {
		ScanBatchLock::instance()->acquire();
		self::active_scan( 20 );

		UsedWhereScanner::instance()->run_tick_batch( 10 );

		$this->assertFalse( wp_next_scheduled( UsedWhereScanner::SCAN_TICK_HOOK, [ 10 ] ) );
	}

	/**
	 * A batch whose lock was taken over while it ran leaves the new owner's
	 * lock in place when it ends.
	 */
	public function test_batch_releases_only_its_own_lock() {
		self::active_scan( 0 );
		add_action( 'update_option_' . self::STATUS, [ $this, 'take_over_lock' ] );

		UsedWhereScanner::instance()->run_tick_batch( 0 );

		$this->assertNotSame( '', $this->takeover, 'Precondition: the lock was taken over.' );
		$this->assertSame( $this->takeover, self::stored_lock() );
	}

	/**
	 * A batch and a cleanup tick that ran normally release their lock.
	 */
	public function test_batch_releases_its_lock() {
		self::active_scan( 0 );

		UsedWhereScanner::instance()->run_tick_batch( 0 );

		$this->assertNull( self::stored_lock() );
		$this->assertSame( 'running', get_option( self::STATUS )['state'], 'No post: the cleanup is next.' );

		UsedWhereScanner::instance()->run_tick_batch( 0 );

		$this->assertNull( self::stored_lock() );
		$this->assertSame( 'complete', get_option( self::STATUS )['state'] );
	}
}
