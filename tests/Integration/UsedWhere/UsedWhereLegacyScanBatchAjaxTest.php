<?php
/**
 * The legacy Used Where batch endpoint (AJAX `tsmlt_used_where_scan_batch`)
 * keeps its pre-MCP contract: `offset` and `batch_size` in, `{ processed,
 * total, complete }` out, one batch run in the request, so a client that
 * sends the returned `processed` as the next offset until `complete` scans
 * the whole site. Each request runs the scan's next step through the scan
 * lifecycle (start lock, batch lock, offset claim, transactions or the
 * fallback save): offset 0 starts a new scan, the batch at an offset runs
 * only when the scan expects it, the completion cleanup runs once every
 * post is done, and a failed batch or a stopped scan is an error.
 *
 * Outgoing HTTP is blocked; every post and attachment is a generated
 * fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\UsedWhere\ScanBatchLock;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereLegacyScanBatchAjaxTest
 */
class UsedWhereLegacyScanBatchAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Image used as a post's featured image.
	 *
	 * @var int
	 */
	private $image = 0;

	/**
	 * Usage records written before the request.
	 *
	 * @var array
	 */
	private $records = [];

	/**
	 * Administrator; a post with a featured image; no scan, lock or batch.
	 */
	public function set_up() {
		parent::set_up();
		// _handleAjax() fires admin_init; keep core's update checks off the network.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( UsageScanStatus::OPTION );
		delete_option( ScanStartLock::OPTION );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );

		$post        = (int) self::factory()->post->create();
		$this->image = (int) self::factory()->attachment->create(
			[
				'file'           => 'legacy/image.jpg',
				'post_mime_type' => 'image/jpeg',
			]
		);
		set_post_thumbnail( $post, $this->image );
		$this->records = [
			[
				'post_id'    => 999,
				'post_title' => 'Earlier scan',
				'post_type'  => 'post',
				'usage_type' => 'content',
			],
		];
		update_post_meta( $this->image, UsedWhereScanner::META_KEY, $this->records );
	}

	/**
	 * Remove filters and scheduled batches.
	 */
	public function tear_down() {
		remove_filter( 'query', [ $this, 'report_myisam_postmeta' ] );
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
	 * Answer the storage-engine lookup with a MyISAM postmeta table.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function report_myisam_postmeta( $query ) {
		global $wpdb;
		if ( false === strpos( $query, 'information_schema.TABLES' ) ) {
			return $query;
		}
		return $wpdb->prepare( 'SELECT %s AS table_name, %s AS engine UNION ALL SELECT %s, %s UNION ALL SELECT %s, %s', $wpdb->options, 'InnoDB', $wpdb->postmeta, 'MyISAM', $wpdb->posts, 'InnoDB' );
	}

	/**
	 * Call the legacy endpoint.
	 *
	 * @param array $params      Request params.
	 * @param bool  $valid_nonce Whether to send a valid nonce.
	 *
	 * @return array Decoded response and raw body.
	 */
	private function call( array $params, bool $valid_nonce = true ): array {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['nonce']            = $valid_nonce ? wp_create_nonce( Fns::NONCE_ID ) : 'invalid';
		$_POST['params']           = wp_json_encode( $params );
		$this->_last_response      = '';
		try {
			$this->_handleAjax( 'tsmlt_used_where_scan_batch' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = $e->getMessage();
		}
		return [
			'response' => json_decode( $this->_last_response, true ),
			'raw'      => $this->_last_response,
		];
	}

	/**
	 * Scheduled scan batches.
	 *
	 * @return int
	 */
	private static function ticks(): int {
		$count = 0;
		foreach ( (array) _get_cron_array() as $hooks ) {
			$count += count( $hooks[ UsedWhereScanner::SCAN_TICK_HOOK ] ?? [] );
		}
		return $count;
	}

	/**
	 * A scan status in a given state.
	 *
	 * @param array $fields Fields.
	 *
	 * @return array The status written.
	 */
	private static function status( array $fields ): array {
		$status = array_merge(
			[
				'state'            => 'running',
				'processed'        => 1,
				'total'            => 1,
				'complete'         => false,
				'next_offset'      => 1,
				'scan_id'          => wp_generate_uuid4(),
				'started_at'       => '2026-01-01 00:00:00',
				'last_tick_at'     => current_time( 'mysql' ),
				'coverage_version' => UsedWhereScanner::COVERAGE_VERSION,
				'coverage_gaps'    => [],
				'cleanup_stage'    => 'reconcile',
				'cleanup_cursor'   => 0,
			],
			$fields
		);
		update_option( UsageScanStatus::OPTION, $status, false );
		return $status;
	}

	/**
	 * Offset 0 starts a new scan, as Re-scan does (scan ID, coverage, start
	 * lock released, the old records wiped), and runs it in the request: on
	 * this one-post site the batch and the completion cleanup both run, so
	 * the first response is already complete.
	 */
	public function test_offset_zero_runs_the_scan() {
		$result = $this->call( [ 'offset' => 0 ] )['response'];

		$this->assertTrue( $result['success'] );
		$this->assertSame( [ 'processed', 'total', 'complete' ], array_keys( $result['data'] ), 'The legacy response shape.' );
		$this->assertSame( (int) $result['data']['total'], $result['data']['processed'] );
		$this->assertTrue( $result['data']['complete'] );
		$status = get_option( UsageScanStatus::OPTION );
		$this->assertSame( 'complete', $status['state'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9-]{36}$/', $status['scan_id'] );
		$this->assertSame( UsedWhereScanner::COVERAGE_VERSION, $status['coverage_version'] );
		$this->assertFalse( get_option( ScanStartLock::OPTION ) );
		$this->assertNull( ScanBatchLock::instance()->read_lock() );
		$usages = get_post_meta( $this->image, UsedWhereScanner::META_KEY, true );
		$this->assertSame( [ 'featured' ], array_column( $usages, 'usage_type' ), 'The old record was wiped and the real usage found.' );
	}

	/**
	 * An offset the running scan does not expect (out of range here; the
	 * scan is in its cleanup at offset 1) runs nothing and reports the
	 * scan's real progress, so `processed` is the offset to send next; no
	 * record is scanned, wiped or written and the status is untouched. A
	 * huge batch_size is accepted (and limited).
	 */
	public function test_other_offset_reports_progress_and_changes_nothing() {
		$status = self::status( [] );

		$result = $this->call(
			[
				'offset'     => 20,
				'batch_size' => 100000,
			]
		)['response'];

		$this->assertTrue( $result['success'] );
		$this->assertSame(
			[
				'processed' => 1,
				'total'     => 1,
				'complete'  => false,
			],
			$result['data']
		);
		$this->assertSame( $status, get_option( UsageScanStatus::OPTION ) );
		$this->assertSame( $this->records, get_post_meta( $this->image, UsedWhereScanner::META_KEY, true ) );
		$this->assertSame( 0, self::ticks() );
	}

	/**
	 * A completed scan keeps its status (scan ID, coverage), so its results
	 * stay verified.
	 */
	public function test_completed_scan_is_not_overwritten() {
		$status = self::status(
			[
				'state'         => 'complete',
				'complete'      => true,
				'cleanup_stage' => 'done',
			]
		);

		$result = $this->call( [ 'offset' => 5 ] )['response'];

		$this->assertTrue( $result['data']['complete'] );
		$this->assertSame( $status, get_option( UsageScanStatus::OPTION ) );
		$this->assertTrue( UsageScanStatus::read()['coverage']['verified'] );
		$this->assertSame( $this->records, get_post_meta( $this->image, UsedWhereScanner::META_KEY, true ) );
	}

	/**
	 * Offset 0 while another request is starting a scan is refused, like
	 * Re-scan: nothing wiped, the status and the other request's lock kept.
	 */
	public function test_offset_zero_waits_for_the_start_lock() {
		$status = self::status( [] );
		$held   = ScanStartLock::instance()->acquire();

		$result = $this->call( [ 'offset' => 0 ] )['response'];

		$this->assertFalse( $result['success'] );
		$this->assertSame( $status, get_option( UsageScanStatus::OPTION ) );
		$this->assertSame( $this->records, get_post_meta( $this->image, UsedWhereScanner::META_KEY, true ) );
		ScanStartLock::instance()->release( $held );
	}

	/**
	 * Posts with featured images, as many as given.
	 *
	 * @param int $count Posts.
	 *
	 * @return int[] Their images.
	 */
	private function more_posts( int $count ): array {
		$images = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$post     = (int) self::factory()->post->create();
			$image    = (int) self::factory()->attachment->create(
				[
					'file'           => 'legacy/more-' . $i . '.jpg',
					'post_mime_type' => 'image/jpeg',
				]
			);
			$images[] = $image;
			set_post_thumbnail( $post, $image );
		}
		return $images;
	}

	/**
	 * Drive a scan the legacy way: offset 0, then `processed` until
	 * `complete`.
	 *
	 * @param int $batch_size Batch size.
	 *
	 * @return array[] Every response's data.
	 */
	private function drive( int $batch_size ): array {
		$responses = [];
		$offset    = 0;
		for ( $i = 0; $i < 30; $i++ ) {
			$result = $this->call(
				[
					'offset'     => $offset,
					'batch_size' => $batch_size,
				]
			)['response'];
			$this->assertTrue( $result['success'], wp_json_encode( $result ) );
			$responses[] = $result['data'];
			if ( $result['data']['complete'] ) {
				break;
			}
			$offset = $result['data']['processed'];
		}
		return $responses;
	}

	/**
	 * Every image has exactly one usage (its post's featured image).
	 *
	 * @param int[] $images Images.
	 */
	private function assert_one_usage_each( array $images ) {
		foreach ( array_merge( [ $this->image ], $images ) as $id ) {
			$usages = get_post_meta( $id, UsedWhereScanner::META_KEY, true );
			$this->assertSame( [ 'featured' ], array_column( (array) $usages, 'usage_type' ), "Image $id." );
		}
	}

	/**
	 * A client drives a whole scan request by request with batch_size 2:
	 * each response is the legacy shape, `processed` grows by at most 2 per
	 * request, and the last one is complete; every usage found once.
	 */
	public function test_client_loop_scans_the_site() {
		$images    = $this->more_posts( 6 );
		$responses = $this->drive( 2 );

		$last = end( $responses );
		$this->assertTrue( $last['complete'] );
		$this->assertSame( $last['total'], $last['processed'] );
		$this->assertGreaterThanOrEqual( 4, count( $responses ), 'Several requests, two posts each.' );
		$previous = 0;
		foreach ( $responses as $data ) {
			$this->assertSame( [ 'processed', 'total', 'complete' ], array_keys( $data ) );
			$this->assertLessThanOrEqual( $previous + 2, $data['processed'] );
			$previous = $data['processed'];
		}
		$this->assertSame( 'complete', get_option( UsageScanStatus::OPTION )['state'] );
		$this->assert_one_usage_each( $images );
		$this->assertNull( ScanBatchLock::instance()->read_lock() );
	}

	/**
	 * batch_size: 0 or missing uses its limits (at least 1; 20 by default),
	 * and huge values are limited to 100.
	 */
	public function test_batch_size_limits() {
		$this->more_posts( 3 );
		$first = $this->call(
			[
				'offset'     => 0,
				'batch_size' => 0,
			]
		)['response'];
		$this->assertSame( 1, $first['data']['processed'], 'batch_size 0 runs one post.' );
		$this->assertSame( 100, UsedWhereScanner::LEGACY_MAX_BATCH );
		$all = $this->call( [ 'offset' => 0 ] )['response'];
		$this->assertTrue( $all['data']['complete'], 'The default batch of 20 covers this site in one request.' );
	}

	/**
	 * An offset other than 0 with no running scan is an error that says to
	 * start with offset 0: never started, cancelled or failed.
	 */
	public function test_offset_without_a_running_scan_is_an_error() {
		foreach ( [ 'idle', 'cancelled', 'error' ] as $state ) {
			if ( 'idle' === $state ) {
				delete_option( UsageScanStatus::OPTION );
			} else {
				self::status( [ 'state' => $state ] );
			}
			$result = $this->call( [ 'offset' => 4 ] )['response'];
			$this->assertFalse( $result['success'], $state );
			$this->assertStringContainsString( 'offset 0', $result['data']['message'], $state );
			$this->assertSame( $this->records, get_post_meta( $this->image, UsedWhereScanner::META_KEY, true ), $state );
		}
	}

	/**
	 * Break the first usage-record INSERT.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function break_usage_insert( $query ) {
		global $wpdb;
		if ( 0 === stripos( ltrim( $query ), 'INSERT' ) && false !== strpos( $query, $wpdb->postmeta ) && false !== strpos( $query, UsedWhereScanner::META_KEY ) ) {
			remove_filter( 'query', [ $this, 'break_usage_insert' ] );
			return 'INSERT INTO tsmlt_no_such_table VALUES (1)';
		}
		return $query;
	}

	/**
	 * A batch that cannot be saved is an error, never a success: nothing is
	 * counted, and sending the same offset again saves it.
	 */
	public function test_failed_batch_is_an_error_and_can_be_retried() {
		global $wpdb;
		$images = $this->more_posts( 3 );
		$this->call(
			[
				'offset'     => 0,
				'batch_size' => 2,
			]
		);
		$offset = (int) get_option( UsageScanStatus::OPTION )['next_offset'];
		$this->assertSame( 2, $offset );

		add_filter( 'query', [ $this, 'break_usage_insert' ] );
		$suppressed = $wpdb->suppress_errors( true );
		$failed     = $this->call(
			[
				'offset'     => $offset,
				'batch_size' => 2,
			]
		)['response'];
		$wpdb->suppress_errors( $suppressed );

		$this->assertFalse( $failed['success'] );
		$this->assertStringContainsString( 'could not be saved', $failed['data']['message'] );
		$this->assertSame( $offset, (int) get_option( UsageScanStatus::OPTION )['next_offset'], 'Nothing counted.' );

		$retry = $this->call(
			[
				'offset'     => $offset,
				'batch_size' => 2,
			]
		)['response'];
		$this->assertTrue( $retry['success'] );
		$this->assertGreaterThan( $offset, $retry['data']['processed'] );
		$offset = $retry['data']['processed'];
		for ( $i = 0; $i < 5 && empty( $retry['data']['complete'] ); $i++ ) {
			$retry  = $this->call(
				[
					'offset'     => $offset,
					'batch_size' => 2,
				]
			)['response'];
			$offset = $retry['data']['processed'];
		}
		$this->assertTrue( $retry['data']['complete'] );
		$this->assert_one_usage_each( $images );
	}

	/**
	 * A cron batch that ran the expected offset first: the legacy request
	 * runs nothing for it and returns the real progress, and the client
	 * finishes the scan without duplicates.
	 */
	public function test_cron_and_legacy_share_the_scan() {
		$images = $this->more_posts( 5 );
		$this->call(
			[
				'offset'     => 0,
				'batch_size' => 2,
			]
		);
		UsedWhereScanner::instance()->run_tick_batch( 2 );
		$after_cron = (int) get_option( UsageScanStatus::OPTION )['next_offset'];
		$this->assertGreaterThan( 2, $after_cron, 'Precondition: cron ran the batch at 2.' );

		$stale = $this->call(
			[
				'offset'     => 2,
				'batch_size' => 2,
			]
		)['response'];
		$this->assertTrue( $stale['success'] );
		$this->assertGreaterThanOrEqual( $after_cron, $stale['data']['processed'], 'The real progress, nothing run again.' );

		$offset = $stale['data']['processed'];
		for ( $i = 0; $i < 10 && empty( $stale['data']['complete'] ); $i++ ) {
			$stale  = $this->call(
				[
					'offset'     => $offset,
					'batch_size' => 2,
				]
			)['response'];
			$offset = $stale['data']['processed'];
		}
		$this->assertTrue( $stale['data']['complete'] );
		$this->assert_one_usage_each( $images );
	}

	/**
	 * An MCP start while a legacy client drives a scan does not restart it,
	 * and the scan's MCP status follows the legacy progress to completion.
	 */
	public function test_mcp_start_during_a_legacy_scan() {
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/start-usage-scan' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		$this->more_posts( 3 );
		$this->call(
			[
				'offset'     => 0,
				'batch_size' => 1,
			]
		);
		$scan_id = get_option( UsageScanStatus::OPTION )['scan_id'];

		$mcp = wp_get_ability( 'tsmlt/start-usage-scan' )->execute();
		$this->assertSame( 'already_running', $mcp['result'] );
		$this->assertSame( $scan_id, get_option( UsageScanStatus::OPTION )['scan_id'], 'Not restarted.' );

		$offset = (int) get_option( UsageScanStatus::OPTION )['next_offset'];
		for ( $i = 0; $i < 10; $i++ ) {
			$data = $this->call(
				[
					'offset'     => $offset,
					'batch_size' => 1,
				]
			)['response']['data'];
			if ( $data['complete'] ) {
				break;
			}
			$offset = $data['processed'];
		}
		$this->assertTrue( UsageScanStatus::read()['complete'] );
		$this->assertSame( $scan_id, get_option( UsageScanStatus::OPTION )['scan_id'] );
	}

	/**
	 * On a non-InnoDB database (simulated MyISAM postmeta) the client loop
	 * works the same, through the fallback save.
	 */
	public function test_client_loop_on_myisam() {
		add_filter( 'query', [ $this, 'report_myisam_postmeta' ] );
		$this->assertFalse( UsedWhereScanner::transactions_supported() );
		$images    = $this->more_posts( 4 );
		$responses = $this->drive( 2 );
		$this->assertTrue( end( $responses )['complete'] );
		$this->assert_one_usage_each( $images );
	}

	/**
	 * While another worker holds the batch lock, the request waits for it
	 * (a few seconds at most), then reports the real progress, not a run.
	 */
	public function test_waits_for_the_batch_lock() {
		$this->more_posts( 2 );
		$this->call(
			[
				'offset'     => 0,
				'batch_size' => 1,
			]
		);
		$offset = (int) get_option( UsageScanStatus::OPTION )['next_offset'];
		$lock   = ScanBatchLock::instance()->acquire();
		$this->assertIsString( $lock );

		$started = microtime( true );
		$result  = $this->call(
			[
				'offset'     => $offset,
				'batch_size' => 1,
			]
		)['response'];
		ScanBatchLock::instance()->release( $lock );

		$this->assertGreaterThan( 4, microtime( true ) - $started, 'It waited.' );
		$this->assertTrue( $result['success'] );
		$this->assertSame( $offset, $result['data']['processed'], 'Nothing ran while the lock was held.' );
	}

	/**
	 * The nonce and capability checks still come first: refused requests
	 * change nothing.
	 */
	public function test_nonce_and_capability_checks() {
		$status = self::status( [] );

		$this->assertSame( '-1', $this->call( [ 'offset' => 0 ], false )['raw'] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$editor = $this->call( [ 'offset' => 0 ] )['response'];

		$this->assertFalse( $editor['success'] );
		$this->assertSame( 'Unauthorized.', $editor['data']['message'] );
		$this->assertSame( $status, get_option( UsageScanStatus::OPTION ) );
		$this->assertSame( $this->records, get_post_meta( $this->image, UsedWhereScanner::META_KEY, true ) );
	}
}
