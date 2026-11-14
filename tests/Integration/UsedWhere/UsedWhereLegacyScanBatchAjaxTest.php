<?php
/**
 * The legacy Used Where batch endpoint (AJAX `tsmlt_used_where_scan_batch`)
 * stays callable with its old request and response shape, but no longer
 * scans posts outside the cron scan's lifecycle: offset 0 starts a scan the
 * way Re-scan does (start lock, storage-engine check, scan ID, coverage);
 * any other offset only reports progress. It never wipes or writes usage
 * records itself and never overwrites the scan status.
 *
 * Outgoing HTTP is blocked; every post and attachment is a generated
 * fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Helpers\Fns;
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
	 * Offset 0 starts a cron-driven scan, as Re-scan does: a queued status
	 * with a scan ID and coverage record, its first batch scheduled, the
	 * start lock released, and nothing scanned in the request itself.
	 */
	public function test_offset_zero_starts_a_cron_scan() {
		$result = $this->call( [ 'offset' => 0 ] )['response'];

		$this->assertTrue( $result['success'] );
		$this->assertSame( [ 'processed', 'total', 'complete' ], array_keys( $result['data'] ), 'The legacy response shape.' );
		$this->assertSame( 0, $result['data']['processed'] );
		$this->assertFalse( $result['data']['complete'] );
		$status = get_option( UsageScanStatus::OPTION );
		$this->assertSame( 'queued', $status['state'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9-]{36}$/', $status['scan_id'] );
		$this->assertSame( UsedWhereScanner::COVERAGE_VERSION, $status['coverage_version'] );
		$this->assertSame( 1, self::ticks() );
		$this->assertFalse( get_option( ScanStartLock::OPTION ) );
		$this->assertSame( '', get_post_meta( $this->image, UsedWhereScanner::META_KEY, true ), 'The start wiped the old records; no batch ran in the request.' );
	}

	/**
	 * Another offset during an active scan (here in its cleanup) reports the
	 * scan's progress and changes nothing: no record scanned, wiped or
	 * written, and the status (scan ID, state, cleanup stage) untouched.
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
	 * Offset 0 on a database that cannot run scans safely starts nothing and
	 * says why.
	 */
	public function test_offset_zero_on_unsupported_database_starts_nothing() {
		add_filter( 'query', [ $this, 'report_myisam_postmeta' ] );

		$result = $this->call( [ 'offset' => 0 ] )['response'];

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'InnoDB', $result['data']['message'] );
		$this->assertFalse( get_option( UsageScanStatus::OPTION ) );
		$this->assertSame( $this->records, get_post_meta( $this->image, UsedWhereScanner::META_KEY, true ) );
		$this->assertSame( 0, self::ticks() );
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
