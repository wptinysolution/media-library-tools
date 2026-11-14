<?php
/**
 * A Used Where scan batch writes its progress only if the scan is still the
 * one it started on, checked and written in one step: a Cancel, Clear
 * Results or Re-scan stored after the batch's last check but before its
 * status write is kept, and the batch schedules nothing. Database writes
 * that change no row, and database errors, also leave the status alone and
 * schedule nothing.
 *
 * PHPUnit runs one request at a time, so competing requests cannot really
 * run in parallel here. Competition is reproduced at the critical database
 * statement instead: a `query` filter runs the other request's action after
 * the batch's last status read, just before its status write — when the
 * batch's save transaction starts (its status write is the transaction's
 * first statement; the action must not run inside that transaction, which
 * another request's never is), or before the error write of a failing
 * batch — or it replaces that UPDATE. Real database-lock competition, with
 * a second connection, is in UsedWhereBatchTransactionTest. Outgoing HTTP is
 * blocked; every post and attachment is a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereFinalWriteRaceTest
 */
class UsedWhereFinalWriteRaceTest extends WP_UnitTestCase {

	/**
	 * Status option.
	 *
	 * @var string
	 */
	const STATUS = 'tsmlt_used_where_scan_status';

	/**
	 * What the `query` filter does: 'cancel', 'restart', 'clear' or
	 * 'acknowledge' run when the batch's save transaction starts, or before
	 * its second status UPDATE (the first one marks the scan as running)
	 * when it saves nothing; 'zero' and 'error' replace that UPDATE and every
	 * later status UPDATE.
	 *
	 * @var string
	 */
	private $mode = '';

	/**
	 * Status UPDATEs that reached the filter during the batch.
	 *
	 * @var int
	 */
	private $updates = 0;

	/**
	 * Whether the action ran.
	 *
	 * @var bool
	 */
	private $acted = false;

	/**
	 * Whether the filter is running its own queries.
	 *
	 * @var bool
	 */
	private $in_filter = false;

	/**
	 * Status written by the restart.
	 *
	 * @var array
	 */
	private $restarted = [];

	/**
	 * Statuses passed to the option's update action.
	 *
	 * @var array[]
	 */
	private $written = [];

	/**
	 * Administrator, 25 posts with a featured image each, no scan.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( self::STATUS );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		delete_transient( UsedWhereScanner::SCAN_LOCK_KEY );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		for ( $i = 0; $i < 25; $i++ ) {
			$post  = (int) self::factory()->post->create();
			$image = (int) self::factory()->attachment->create(
				[
					'file'           => 'final-write/image-' . $i . '.jpg',
					'post_mime_type' => 'image/jpeg',
				]
			);
			set_post_thumbnail( $post, $image );
		}
		$this->mode      = '';
		$this->updates   = 0;
		$this->acted     = false;
		$this->in_filter = false;
		$this->restarted = [];
		$this->written   = [];
	}

	/**
	 * Remove filters and scheduled batches.
	 */
	public function tear_down() {
		remove_filter( 'query', [ $this, 'interfere' ] );
		remove_action( 'update_option_' . self::STATUS, [ $this, 'record_write' ] );
		remove_filter( 'tsmlt_used_where_scannable_post_types', [ $this, 'fail_batch' ] );
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
	 * Make the batch fail.
	 *
	 * @throws RuntimeException Always.
	 */
	public function fail_batch() {
		throw new RuntimeException( 'Batch failed.' );
	}

	/**
	 * Record a status passed to the option's update action.
	 *
	 * @param mixed $old Previous value.
	 * @param mixed $new New value.
	 */
	public function record_write( $old, $new ) {
		$this->written[] = $new;
	}

	/**
	 * Interfere with the batch's status UPDATEs, by $this->mode.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function interfere( $query ) {
		global $wpdb;
		if ( $this->in_filter ) {
			return $query;
		}
		// The batch's save transaction (a savepoint in tests) starts.
		$begins = 0 === strpos( $query, 'SAVEPOINT tsmlt_used_where_batch' );
		if ( ! $begins ) {
			if ( false === strpos( $query, self::STATUS ) || 0 !== stripos( ltrim( $query ), 'UPDATE' ) ) {
				return $query;
			}
			++$this->updates;
			if ( $this->updates < 2 ) {
				return $query;
			}
			if ( 'zero' === $this->mode ) {
				return "UPDATE {$wpdb->options} SET option_value = option_value WHERE 1 = 0";
			}
			if ( 'error' === $this->mode ) {
				return 'UPDATE tsmlt_no_such_table SET a = 1';
			}
		}
		if ( $this->acted || in_array( $this->mode, [ '', 'zero', 'error' ], true ) ) {
			return $query;
		}

		// Another request's action reaches the database first.
		$this->acted     = true;
		$this->in_filter = true;
		$scanner         = UsedWhereScanner::instance();
		if ( 'cancel' === $this->mode ) {
			$scanner->cancel_scheduled_scan();
		} elseif ( 'clear' === $this->mode ) {
			$scanner->clear_scan();
		} elseif ( 'restart' === $this->mode ) {
			$scanner->start_scheduled_scan();
			$this->restarted = get_option( self::STATUS );
		} elseif ( 'acknowledge' === $this->mode ) {
			$scanner->acknowledge_scan_status();
		}
		$this->in_filter = false;
		return $query;
	}

	/**
	 * Start a scan and leave its first batch unscheduled, to run it by hand.
	 *
	 * @return array Status after the start.
	 */
	private static function start(): array {
		UsedWhereScanner::instance()->start_scheduled_scan();
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		return get_option( self::STATUS );
	}

	/**
	 * Run the first batch with the `query` filter in a mode.
	 *
	 * @param string $mode Mode.
	 */
	private function run_batch_with( string $mode ) {
		global $wpdb;
		$this->mode = $mode;
		add_filter( 'query', [ $this, 'interfere' ] );
		$suppressed = $wpdb->suppress_errors( true );
		UsedWhereScanner::instance()->run_tick_batch( 0 );
		$wpdb->suppress_errors( $suppressed );
		remove_filter( 'query', [ $this, 'interfere' ] );
	}

	/**
	 * The status in the database, not the option cache.
	 *
	 * @return mixed The status, or null when there is none.
	 */
	private static function stored_status() {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::STATUS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
		return null === $value ? null : maybe_unserialize( $value );
	}

	/**
	 * Offsets of scheduled batches, sorted.
	 *
	 * @return int[]
	 */
	private static function ticks(): array {
		$ticks = [];
		foreach ( (array) _get_cron_array() as $hooks ) {
			foreach ( $hooks[ UsedWhereScanner::SCAN_TICK_HOOK ] ?? [] as $event ) {
				$ticks[] = (int) ( $event['args'][0] ?? 0 );
			}
		}
		sort( $ticks );
		return $ticks;
	}

	/**
	 * Cancel stored after the batch's last check, just before its status
	 * write: the scan stays cancelled with its progress untouched, and no
	 * next batch is scheduled.
	 */
	public function test_cancel_before_the_status_write_is_kept() {
		$start = self::start();

		$this->run_batch_with( 'cancel' );

		$this->assertTrue( $this->acted, 'Precondition: the cancel ran before the status write.' );
		$status = self::stored_status();
		$this->assertSame( 'cancelled', $status['state'] );
		$this->assertSame( $start['scan_id'], $status['scan_id'] );
		$this->assertSame( 0, (int) $status['processed'], 'Progress not overwritten.' );
		$this->assertSame( $status, get_option( self::STATUS ), 'No stale cached status.' );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * Re-scan stored just before the old batch's status write: the new scan
	 * keeps exactly the status its start wrote, and only its own first batch
	 * is scheduled.
	 */
	public function test_restart_before_the_status_write_is_kept() {
		$old = self::start();

		$this->run_batch_with( 'restart' );

		$this->assertTrue( $this->acted, 'Precondition: the restart ran before the status write.' );
		$status = self::stored_status();
		$this->assertNotSame( $old['scan_id'], $status['scan_id'] );
		$this->assertSame( $this->restarted, $status, 'The new scan\'s status is exactly as its start wrote it.' );
		$this->assertSame( 'queued', $status['state'] );
		$this->assertSame( 0, (int) $status['processed'] );
		$this->assertSame( [ 0 ], self::ticks(), 'Only the new scan\'s first batch.' );
	}

	/**
	 * Clear Results stored just before the old batch's status write: no
	 * status is recreated and no next batch is scheduled.
	 */
	public function test_clear_before_the_status_write_is_kept() {
		self::start();

		$this->run_batch_with( 'clear' );

		$this->assertTrue( $this->acted, 'Precondition: the clear ran before the status write.' );
		$this->assertNull( self::stored_status() );
		$this->assertFalse( get_option( self::STATUS ) );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * Cancel stored just before a failing batch writes its error: the scan
	 * stays cancelled, not failed.
	 */
	public function test_cancel_before_the_error_write_is_kept() {
		self::start();
		add_filter( 'tsmlt_used_where_scannable_post_types', [ $this, 'fail_batch' ] );

		$this->run_batch_with( 'cancel' );

		$this->assertTrue( $this->acted, 'Precondition: the cancel ran before the error write.' );
		$status = self::stored_status();
		$this->assertSame( 'cancelled', $status['state'] );
		$this->assertSame( '', $status['last_error'] );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * A status write that changes no row (tried three times) leaves the
	 * status as the batch's start left it and schedules nothing.
	 */
	public function test_write_changing_no_row_schedules_nothing() {
		$start = self::start();

		$this->run_batch_with( 'zero' );

		$status = self::stored_status();
		$this->assertSame( 4, $this->updates, 'The running mark, then three attempts.' );
		$this->assertSame( 'running', $status['state'] );
		$this->assertSame( $start['scan_id'], $status['scan_id'] );
		$this->assertSame( 0, (int) $status['processed'] );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * A database error on the status write leaves the status as the batch's
	 * start left it and schedules nothing.
	 */
	public function test_database_error_fails_closed() {
		$start = self::start();

		$this->run_batch_with( 'error' );

		$status = self::stored_status();
		$this->assertSame( 2, $this->updates, 'No retry after an error.' );
		$this->assertSame( 'running', $status['state'] );
		$this->assertSame( $start['scan_id'], $status['scan_id'] );
		$this->assertSame( 0, (int) $status['processed'] );
		$this->assertSame( '', $status['last_error'] );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * Another field of the same scan written just before the batch's status
	 * write: the batch checks again, keeps that field, writes its progress
	 * and schedules the next batch.
	 */
	public function test_other_field_written_meanwhile_is_kept() {
		$start = self::start();

		$this->run_batch_with( 'acknowledge' );

		$this->assertTrue( $this->acted, 'Precondition: the other write ran before the status write.' );
		$status = self::stored_status();
		$this->assertTrue( $status['notified'], 'The other write is kept.' );
		$this->assertSame( 'running', $status['state'] );
		$this->assertSame( $start['scan_id'], $status['scan_id'] );
		$this->assertSame( 10, (int) $status['processed'] );
		$this->assertSame( [ 10 ], self::ticks() );
	}

	/**
	 * A normal batch, observed through the same filter, writes its progress
	 * once, fires the option's update action, and schedules the next batch.
	 */
	public function test_normal_batch_writes_and_schedules() {
		$start = self::start();
		add_action( 'update_option_' . self::STATUS, [ $this, 'record_write' ], 10, 2 );

		$this->run_batch_with( '' );

		$status = self::stored_status();
		$this->assertSame( 2, $this->updates, 'The running mark and the progress.' );
		$this->assertSame( [ 'running', 'running' ], array_column( $this->written, 'state' ) );
		$this->assertSame( $status, end( $this->written ) );
		$this->assertSame( $status, get_option( self::STATUS ), 'No stale cached status.' );
		$this->assertSame( 10, (int) $status['processed'] );
		$this->assertSame( $start['scan_id'], $status['scan_id'] );
		$this->assertSame( [ 10 ], self::ticks() );
	}
}
