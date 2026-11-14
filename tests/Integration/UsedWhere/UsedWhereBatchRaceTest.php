<?php
/**
 * A Used Where scan batch that is already running when the scan is
 * cancelled, cleared or restarted changes nothing afterwards: no status
 * write, no next batch, no usage records for a scan that is no longer the
 * current one. Normal batches still save records, report progress, schedule
 * the next batch and complete.
 *
 * The cancel, clear or restart is made from the hook that fires when the
 * batch marks the scan as running, before it processes its posts — the
 * point where another request's action interleaves with it. Outgoing HTTP
 * is blocked; every post and attachment is a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereBatchRaceTest
 */
class UsedWhereBatchRaceTest extends WP_UnitTestCase {

	/**
	 * Status option.
	 *
	 * @var string
	 */
	const STATUS = 'tsmlt_used_where_scan_status';

	/**
	 * What to do while the batch runs: 'cancel', 'restart', 'clear' or
	 * 'restart_then_fail'.
	 *
	 * @var string
	 */
	private $action = '';

	/**
	 * Whether the action ran.
	 *
	 * @var bool
	 */
	private $acted = false;

	/**
	 * Status written by the restart, before the old batch finished.
	 *
	 * @var array
	 */
	private $restarted = [];

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
					'file'           => 'race/image-' . $i . '.jpg',
					'post_mime_type' => 'image/jpeg',
				]
			);
			set_post_thumbnail( $post, $image );
		}
		$this->action    = '';
		$this->acted     = false;
		$this->restarted = [];
	}

	/**
	 * Remove hooks, filters and scheduled batches.
	 */
	public function tear_down() {
		remove_action( 'update_option_' . self::STATUS, [ $this, 'act_during_batch' ] );
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
	 * Cancel, clear or restart when the batch marks the scan as running.
	 *
	 * @param mixed $old Previous value.
	 * @param mixed $new New value.
	 */
	public function act_during_batch( $old, $new ) {
		if ( $this->acted || 'running' !== ( $new['state'] ?? '' ) ) {
			return;
		}
		$this->acted = true;
		$scanner     = UsedWhereScanner::instance();
		if ( 'cancel' === $this->action ) {
			$scanner->cancel_scheduled_scan();
		} elseif ( 'clear' === $this->action ) {
			$scanner->clear_scan();
		} else {
			$scanner->start_scheduled_scan();
			$this->restarted = get_option( self::STATUS );
			if ( 'restart_then_fail' === $this->action ) {
				add_filter( 'tsmlt_used_where_scannable_post_types', [ $this, 'fail_batch' ] );
			}
		}
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
	 * Run a batch while doing an action from the hook.
	 *
	 * @param string $action Action.
	 * @param int    $offset Batch offset.
	 */
	private function run_batch_with( string $action, int $offset = 0 ) {
		$this->action = $action;
		add_action( 'update_option_' . self::STATUS, [ $this, 'act_during_batch' ], 10, 2 );
		UsedWhereScanner::instance()->run_tick_batch( $offset );
		$this->assertTrue( $this->acted, 'Precondition: the action ran during the batch.' );
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
	 * Attachments with usage records.
	 *
	 * @return int
	 */
	private static function records(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", UsedWhereScanner::META_KEY ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
	}

	/**
	 * Cancel during a batch: the scan stays cancelled, no next batch, and the
	 * batch saves no usage records.
	 */
	public function test_cancel_during_batch_stays_cancelled() {
		$start = self::start();

		$this->run_batch_with( 'cancel' );

		$status = get_option( self::STATUS );
		$this->assertSame( 'cancelled', $status['state'] );
		$this->assertSame( $start['scan_id'], $status['scan_id'] );
		$this->assertSame( 0, (int) $status['processed'], 'Progress not overwritten.' );
		$this->assertSame( [], self::ticks() );
		$this->assertSame( 0, self::records() );
	}

	/**
	 * Re-scan during a batch: the new scan keeps its own status (ID,
	 * progress, coverage) and only its first batch is scheduled — the old
	 * batch neither reports progress nor schedules its continuation.
	 */
	public function test_restart_during_batch_leaves_the_new_scan_alone() {
		$old = self::start();

		$this->run_batch_with( 'restart' );

		$status = get_option( self::STATUS );
		$this->assertNotSame( $old['scan_id'], $status['scan_id'] );
		$this->assertSame( $this->restarted, $status, 'The new scan\'s status is exactly as its start wrote it.' );
		$this->assertSame( 'queued', $status['state'] );
		$this->assertSame( 0, (int) $status['processed'] );
		$this->assertSame( UsedWhereScanner::COVERAGE_VERSION, $status['coverage_version'] );
		$this->assertSame( [ 0 ], self::ticks(), 'Only the new scan\'s first batch.' );
		$this->assertSame( 0, self::records(), 'The old batch saved nothing into the new scan.' );
	}

	/**
	 * Clear Results during a batch: no status and no usage records come back.
	 */
	public function test_clear_during_batch_stays_cleared() {
		self::start();

		$this->run_batch_with( 'clear' );

		$this->assertFalse( get_option( self::STATUS ) );
		$this->assertSame( [], self::ticks() );
		$this->assertSame( 0, self::records() );
	}

	/**
	 * A batch of an old scan that fails after a Re-scan does not mark the
	 * new scan as failed.
	 */
	public function test_old_batch_failure_does_not_mark_the_new_scan() {
		self::start();

		$this->run_batch_with( 'restart_then_fail' );

		$status = get_option( self::STATUS );
		$this->assertSame( 'queued', $status['state'] );
		$this->assertSame( '', $status['last_error'] );
		$this->assertSame( $this->restarted['scan_id'], $status['scan_id'] );
	}

	/**
	 * A normal batch saves records, reports progress, keeps the scan ID and
	 * coverage, and schedules the next batch.
	 */
	public function test_normal_batch_schedules_the_next_one() {
		$start = self::start();

		UsedWhereScanner::instance()->run_tick_batch( 0 );

		$status = get_option( self::STATUS );
		$this->assertSame( 'running', $status['state'] );
		$this->assertSame( 10, (int) $status['processed'] );
		$this->assertSame( $start['scan_id'], $status['scan_id'] );
		$this->assertSame( $start['coverage_version'], $status['coverage_version'] );
		$this->assertSame( $start['scanned_post_types'], $status['scanned_post_types'] );
		$this->assertSame( [ 10 ], self::ticks() );
		$this->assertGreaterThan( 0, self::records() );
	}

	/**
	 * A scan run to the end completes with every post processed and every
	 * featured image recorded.
	 */
	public function test_normal_scan_completes() {
		$start = self::start();

		$offset = 0;
		for ( $i = 0; $i < 10; $i++ ) {
			wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
			UsedWhereScanner::instance()->run_tick_batch( $offset );
			$status = get_option( self::STATUS );
			if ( 'complete' === $status['state'] ) {
				break;
			}
			$offset = (int) $status['next_offset'];
		}

		$this->assertSame( 'complete', $status['state'] );
		$this->assertTrue( $status['complete'] );
		$this->assertSame( (int) $status['total'], (int) $status['processed'] );
		$this->assertSame( $start['scan_id'], $status['scan_id'] );
		$this->assertSame( 25, self::records() );
	}
}
