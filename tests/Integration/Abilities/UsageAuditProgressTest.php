<?php
/**
 * Live progress and incremental used / unused / unknown results while the
 * Used Where scan runs, through the MCP tools only.
 *
 * The test drives a reference client that follows the documented polling
 * workflow (docs/12-mcp-integration.md, "Live progress"): it polls
 * tsmlt-get-usage-scan-status, checks images in batches with
 * tsmlt-get-media-usage, keeps results between polls, re-checks only what
 * can still change, and verifies the final summary against the scan's
 * `scan_id`. WP-Cron does not run in tests, so scan batches are run one at a
 * time between polls. Outgoing HTTP is blocked. Every post and attachment is
 * a generated fixture. Skipped when the MCP Adapter is not loaded.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\Media\StartUsageScan;
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsageAuditProgressTest
 */
class UsageAuditProgressTest extends WP_UnitTestCase {

	/**
	 * Dedicated server route.
	 */
	const ROUTE = '/media-library-tools/mcp';

	/**
	 * Terminal scan states.
	 */
	const TERMINAL = [ 'complete', 'cancelled', 'error' ];

	/**
	 * MCP session.
	 *
	 * @var string
	 */
	private $session = '';

	/**
	 * JSON-RPC request counter.
	 *
	 * @var int
	 */
	private $request_id = 0;

	/**
	 * Client state: scan run the results belong to.
	 *
	 * @var string|null
	 */
	private $scan_id = null;

	/**
	 * Client state: image ID => [ filename, class, usage_count, provisional ].
	 *
	 * @var array<int, array>
	 */
	private $images = [];

	/**
	 * Client state: IDs sent in each tsmlt-get-media-usage call.
	 *
	 * @var int[][]
	 */
	private $calls = [];

	/**
	 * Client state: index of the call in which each image was first used.
	 *
	 * @var array<int, int>
	 */
	private $used_at = [];

	/**
	 * Client state: whether the summary is final.
	 *
	 * @var bool
	 */
	private $final = false;

	/**
	 * Client state: last status.
	 *
	 * @var array
	 */
	private $status = [];

	/**
	 * Client setting: IDs per tsmlt-get-media-usage call.
	 *
	 * @var int
	 */
	private $batch = 2;

	/**
	 * Administrator, MCP session, no scan, no HTTP, fast scan.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( UsageScanStatus::OPTION );
		delete_option( StartUsageScan::START_LOCK_OPTION );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		delete_transient( UsedWhereScanner::SCAN_LOCK_KEY );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		$this->connect();
	}

	/**
	 * Remove filters and scheduled batches.
	 */
	public function tear_down() {
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

	// ---------------------------------------------------------------------
	// MCP transport
	// ---------------------------------------------------------------------

	/**
	 * Send one JSON-RPC message.
	 *
	 * @param string $method Method.
	 * @param array  $params Params.
	 *
	 * @return array
	 */
	private function send( string $method, array $params = [] ): array {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'Accept', 'application/json, text/event-stream' );
		if ( $this->session ) {
			$request->set_header( 'Mcp-Session-Id', $this->session );
			$request->set_header( 'MCP-Protocol-Version', '2025-11-25' );
		}
		$request->set_body(
			wp_json_encode(
				[
					'jsonrpc' => '2.0',
					'id'      => ++$this->request_id,
					'method'  => $method,
					'params'  => (object) $params,
				]
			)
		);
		$response = rest_get_server()->dispatch( $request );
		if ( 'initialize' === $method ) {
			$this->session = $response->get_headers()['Mcp-Session-Id'];
		}
		$data = $response->get_data();
		return is_array( $data ) ? $data : json_decode( wp_json_encode( $data ), true );
	}

	/**
	 * Open a session.
	 */
	private function connect() {
		$this->send(
			'initialize',
			[
				'protocolVersion' => '2025-11-25',
				'capabilities'    => (object) [],
				'clientInfo'      => [
					'name'    => 'tsmlt-tests',
					'version' => '1.0.0',
				],
			]
		);
		$this->send( 'notifications/initialized' );
	}

	/**
	 * Call a tool; its result must pass the adapter's output-schema check.
	 *
	 * @param string $tool      Tool.
	 * @param array  $arguments Arguments.
	 *
	 * @return array Structured content.
	 */
	private function tool( string $tool, array $arguments = [] ): array {
		$result = $this->send(
			'tools/call',
			[
				'name'      => $tool,
				'arguments' => (object) $arguments,
			]
		)['result'];
		$this->assertFalse( $result['isError'], $tool . ': ' . wp_json_encode( $result ) );
		return $result['structuredContent'];
	}

	// ---------------------------------------------------------------------
	// Reference client (the documented workflow)
	// ---------------------------------------------------------------------

	/**
	 * Every image ID with its file name, paging through image search results
	 * by ascending ID.
	 *
	 * @return array<int, string>
	 */
	private function enumerate_images(): array {
		$names = [];
		$page  = 1;
		do {
			$found = $this->tool(
				'tsmlt-search-media',
				[
					'mime_type' => 'image',
					'orderby'   => 'id',
					'order'     => 'ASC',
					'per_page'  => 2,
					'page'      => $page,
				]
			);
			foreach ( $found['items'] as $item ) {
				$names[ $item['id'] ] = wp_basename( (string) wp_parse_url( $item['url'], PHP_URL_PATH ) );
			}
			++$page;
		} while ( $page <= $found['total_pages'] );
		return $names;
	}

	/**
	 * Forget all results (a new scan run started).
	 */
	private function reset_results() {
		foreach ( $this->images as $id => $image ) {
			$this->images[ $id ] = array_merge(
				$image,
				[
					'class'       => 'unknown',
					'usage_count' => null,
					'provisional' => true,
				]
			);
		}
		$this->final = false;
	}

	/**
	 * Check images in batches and record their classification.
	 *
	 * @param int[] $ids Image IDs.
	 *
	 * @return bool Whether every response came from one completed scan.
	 */
	private function check( array $ids ): bool {
		$verified = true;
		foreach ( array_chunk( $ids, $this->batch ) as $chunk ) {
			$this->calls[] = $chunk;
			$usage         = $this->tool( 'tsmlt-get-media-usage', [ 'ids' => $chunk ] );
			$complete      = $usage['scan']['complete'];
			$verified      = $verified && $complete;
			foreach ( $usage['items'] as $item ) {
				if ( true === $item['used'] ) {
					$class = 'used';
					if ( ! isset( $this->used_at[ $item['id'] ] ) ) {
						$this->used_at[ $item['id'] ] = count( $this->calls ) - 1;
					}
				} elseif ( false === $item['used'] && $complete ) {
					$class = 'unused';
				} else {
					$class = 'unknown';
				}
				$this->images[ $item['id'] ]['class']       = $class;
				$this->images[ $item['id'] ]['usage_count'] = null === $item['used'] ? null : $item['total_usage'];
				$this->images[ $item['id'] ]['provisional'] = ! $complete;
			}
		}
		return $verified;
	}

	/**
	 * One poll: status, then the images that can still change.
	 *
	 * While the scan runs, only unknown images are re-checked (a recorded
	 * usage is not removed while the scan runs). When it completes, every
	 * image is checked once more and the summary is final only if the scan
	 * run is still the same and still complete afterwards.
	 *
	 * @return array Summary.
	 */
	private function poll(): array {
		$this->status = $this->tool( 'tsmlt-get-usage-scan-status' );

		if ( $this->status['scan_id'] !== $this->scan_id ) {
			$this->scan_id = $this->status['scan_id'];
			$this->reset_results();
		}

		foreach ( $this->enumerate_images() as $id => $filename ) {
			if ( ! isset( $this->images[ $id ] ) ) {
				$this->images[ $id ] = [
					'filename'    => $filename,
					'class'       => 'unknown',
					'usage_count' => null,
					'provisional' => true,
				];
				$this->final         = false;
			}
		}

		$stalled = $this->status['stalled'];
		if ( 'complete' === $this->status['state'] && ! $this->final ) {
			$verified    = $this->check( array_keys( $this->images ) );
			$after       = $this->tool( 'tsmlt-get-usage-scan-status' );
			$this->final = $verified && $after['complete'] && $after['scan_id'] === $this->scan_id;
			if ( ! $this->final ) {
				$this->reset_results();
			}
		} elseif ( in_array( $this->status['state'], [ 'queued', 'running' ], true ) && ! $stalled ) {
			$unknown = array_keys(
				array_filter(
					$this->images,
					static function ( $image ) {
						return 'unknown' === $image['class'];
					}
				)
			);
			$this->check( $unknown );
		}

		return $this->summary();
	}

	/**
	 * What a client shows.
	 *
	 * @return array
	 */
	private function summary(): array {
		$by_class = [
			'used'    => [],
			'unused'  => [],
			'unknown' => [],
		];
		foreach ( $this->images as $id => $image ) {
			$by_class[ $image['class'] ][] = $id;
		}
		return [
			'state'    => $this->status['stalled'] ? 'stalled' : $this->status['state'],
			'progress' => $this->status['processed'] . ' / ' . $this->status['total'],
			'percent'  => $this->status['percent'],
			'final'    => $this->final,
			'used'     => $by_class['used'],
			'unused'   => $by_class['unused'],
			'unknown'  => $by_class['unknown'],
		];
	}

	// ---------------------------------------------------------------------
	// Fixtures and scan control
	// ---------------------------------------------------------------------

	/**
	 * An image attachment.
	 *
	 * @param string $file File under uploads.
	 *
	 * @return int
	 */
	private function image( string $file ): int {
		return (int) self::factory()->attachment->create(
			[
				'file'           => $file,
				'post_title'     => basename( $file ),
				'post_mime_type' => 'image/jpeg',
			]
		);
	}

	/**
	 * Library: an image used as the featured image of the first post, one
	 * used in the content of the last of 25 posts (a later scan batch), and
	 * one used nowhere.
	 *
	 * @return array{early: int, late: int, unused: int}
	 */
	private function library(): array {
		$early  = $this->image( 'audit/early.jpg' );
		$late   = $this->image( 'audit/late.jpg' );
		$unused = $this->image( 'audit/unused.jpg' );

		$first = (int) self::factory()->post->create( [ 'post_title' => 'First' ] );
		set_post_thumbnail( $first, $early );
		self::factory()->post->create_many( 23 );
		self::factory()->post->create(
			[
				'post_title'   => 'Last',
				'post_content' => '<img src="' . esc_url( wp_get_attachment_url( $late ) ) . '" class="wp-image-' . $late . '" />',
			]
		);
		return compact( 'early', 'late', 'unused' );
	}

	/**
	 * Run the next scheduled scan batch, as WP-Cron would.
	 *
	 * @return bool Whether a batch ran.
	 */
	private static function run_one_batch(): bool {
		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			foreach ( $hooks[ UsedWhereScanner::SCAN_TICK_HOOK ] ?? [] as $event ) {
				$offset = (int) ( $event['args'][0] ?? 0 );
				wp_unschedule_event( $timestamp, UsedWhereScanner::SCAN_TICK_HOOK, [ $offset ] );
				UsedWhereScanner::instance()->run_tick_batch( $offset );
				return true;
			}
		}
		return false;
	}

	/**
	 * Poll after every batch until the scan ends; return each summary.
	 *
	 * @return array[]
	 */
	private function poll_until_terminal(): array {
		$summaries = [ $this->poll() ];
		for ( $i = 0; $i < 20 && ! in_array( $this->status['state'], self::TERMINAL, true ); $i++ ) {
			self::run_one_batch();
			$summaries[] = $this->poll();
		}
		return $summaries;
	}

	// ---------------------------------------------------------------------
	// Tests
	// ---------------------------------------------------------------------

	/**
	 * Results build up batch by batch, provisional until the scan completes;
	 * nothing is unused before then; progress only moves forward; the final
	 * summary is verified.
	 */
	public function test_incremental_results_until_final() {
		$lib = $this->library();
		$this->assertSame( 'started', $this->tool( 'tsmlt-start-usage-scan' )['result'] );

		$summaries = $this->poll_until_terminal();
		$last      = array_pop( $summaries );

		$this->assertGreaterThanOrEqual( 3, count( $summaries ), 'Several polls while the scan ran.' );
		$percent = -1;
		$seen    = [];
		foreach ( $summaries as $summary ) {
			$this->assertFalse( $summary['final'] );
			$this->assertSame( [], $summary['unused'], 'Nothing is unused before the scan completes.' );
			$this->assertGreaterThanOrEqual( $percent, $summary['percent'] );
			$this->assertLessThan( 100, $summary['percent'] );
			$percent = $summary['percent'];
			$seen[]  = [ in_array( $lib['early'], $summary['used'], true ), in_array( $lib['late'], $summary['used'], true ) ];
		}
		$this->assertContains( [ true, false ], $seen, 'At some poll the early image was used and the late one still unknown.' );

		$this->assertSame( 'complete', $last['state'] );
		$this->assertTrue( $last['final'] );
		$this->assertSame( 100, $last['percent'] );
		$this->assertSame( $this->status['total'] . ' / ' . $this->status['total'], $last['progress'] );
		$this->assertEqualsCanonicalizing( [ $lib['early'], $lib['late'] ], $last['used'] );
		$this->assertSame( [ $lib['unused'] ], $last['unused'] );
		$this->assertSame( [], $last['unknown'] );
		$this->assertSame( 'unused.jpg', $this->images[ $lib['unused'] ]['filename'] );
		$this->assertSame( 0, $this->images[ $lib['unused'] ]['usage_count'] );
		$this->assertSame( 1, $this->images[ $lib['early'] ]['usage_count'] );
		$this->assertFalse( $this->images[ $lib['early'] ]['provisional'] );
	}

	/**
	 * While the scan runs, an image is sent again only while it is unknown;
	 * every call stays within the batch size; the final pass checks every
	 * image exactly once; polling after the final summary sends nothing.
	 */
	public function test_no_needless_rechecks() {
		$lib = $this->library();
		$this->tool( 'tsmlt-start-usage-scan' );

		$this->poll_until_terminal();
		$calls = $this->calls;
		$this->poll();

		$this->assertSame( $calls, $this->calls, 'Nothing is checked again once final.' );
		foreach ( $calls as $chunk ) {
			$this->assertLessThanOrEqual( $this->batch, count( $chunk ) );
			$this->assertSame( array_values( array_unique( $chunk ) ), $chunk );
		}
		$ids        = array_merge( ...$calls );
		$final_pass = array_slice( $ids, -count( $this->images ) );
		$this->assertEqualsCanonicalizing( array_keys( $this->images ), $final_pass );
		$this->assertArrayHasKey( $lib['early'], $this->used_at );
		$running_calls = array_slice( $calls, 0, count( $calls ) - (int) ceil( count( $this->images ) / $this->batch ) );
		foreach ( array_slice( $running_calls, $this->used_at[ $lib['early'] ] + 1 ) as $chunk ) {
			$this->assertNotContains( $lib['early'], $chunk, 'A provisionally used image is not re-checked while the scan runs.' );
		}
	}

	/**
	 * A scan started during the audit (here from the admin page) changes
	 * scan_id: every result is reset to unknown, nothing stays unused, and
	 * the audit becomes final again only after the new scan completes.
	 */
	public function test_new_scan_during_audit_resets_results() {
		$lib = $this->library();
		$this->tool( 'tsmlt-start-usage-scan' );
		$this->poll_until_terminal();
		$this->assertTrue( $this->summary()['final'] );
		$first_id = $this->scan_id;

		UsedWhereScanner::instance()->start_scheduled_scan();
		$restarted = $this->poll();

		$this->assertNotSame( $first_id, $this->scan_id );
		$this->assertFalse( $restarted['final'] );
		$this->assertSame( [], $restarted['unused'] );
		$this->assertContains( $lib['unused'], $restarted['unknown'] );

		$summaries = $this->poll_until_terminal();
		$last      = array_pop( $summaries );
		$this->assertTrue( $last['final'] );
		$this->assertSame( [ $lib['unused'] ], $last['unused'] );
	}

	/**
	 * A scan restarted while one is still running is detected the same way.
	 */
	public function test_restart_while_running_is_detected() {
		$this->library();
		$this->tool( 'tsmlt-start-usage-scan' );
		$this->poll();
		self::run_one_batch();
		$this->poll();
		$this->assertNotEmpty( $this->summary()['used'] );
		$first_id = $this->scan_id;

		UsedWhereScanner::instance()->start_scheduled_scan();
		$summary = $this->poll();

		$this->assertNotSame( $first_id, $this->scan_id );
		$this->assertSame( 'queued', $summary['state'] );
		$this->assertSame( [], $summary['used'], 'Results of the old run were dropped.' );
		$this->assertSame( [], $summary['unused'] );
	}

	/**
	 * Failed, cancelled and stalled scans are reported as such; the audit
	 * is never final and nothing is unused.
	 *
	 * @return array
	 */
	public function data_unfinished(): array {
		return [
			'error'     => [ 'error' ],
			'cancelled' => [ 'cancelled' ],
			'stalled'   => [ 'stalled' ],
		];
	}

	/**
	 * Unfinished scans.
	 *
	 * @dataProvider data_unfinished
	 *
	 * @param string $end How the scan ends.
	 */
	public function test_unfinished_scan_is_never_final( string $end ) {
		$lib = $this->library();
		$this->tool( 'tsmlt-start-usage-scan' );
		$this->poll();
		self::run_one_batch();
		$row = get_option( UsageScanStatus::OPTION );
		if ( 'cancelled' === $end ) {
			UsedWhereScanner::instance()->cancel_scheduled_scan();
		} elseif ( 'error' === $end ) {
			update_option( UsageScanStatus::OPTION, array_merge( $row, [ 'state' => 'error', 'last_error' => 'Boom in /srv/secret' ] ), false );
		} else {
			wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
			update_option( UsageScanStatus::OPTION, array_merge( $row, [ 'last_tick_at' => '2000-01-01 00:00:00' ] ), false );
		}

		$summary = $this->poll();
		$again   = $this->poll();

		$this->assertSame( $end, $summary['state'] );
		$this->assertFalse( $summary['final'] );
		$this->assertFalse( $again['final'] );
		$this->assertSame( [], $summary['unused'] );
		$this->assertContains( $lib['unused'], $summary['unknown'] );
		$this->assertLessThan( 100, $summary['percent'] );
	}

	/**
	 * Polling only reads: the client's status, search and usage calls write
	 * no option or post meta, schedule nothing, and leave attachments as they
	 * are, while the scan is running.
	 */
	public function test_polling_writes_nothing() {
		$lib = $this->library();
		$this->tool( 'tsmlt-start-usage-scan' );
		self::run_one_batch();
		$row      = get_option( UsageScanStatus::OPTION );
		$cron     = _get_cron_array();
		$snapshot = [];
		foreach ( $lib as $id ) {
			$snapshot[ $id ] = [ get_post( $id, ARRAY_A ), get_post_meta( $id ) ];
		}
		$writes = [];
		$track  = static function () use ( &$writes ) {
			$writes[] = current_filter();
		};
		$hooks  = [ 'add_option', 'update_option', 'delete_option', 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ];
		foreach ( $hooks as $hook ) {
			add_action( $hook, $track );
		}

		$this->poll();
		$this->poll();

		foreach ( $hooks as $hook ) {
			remove_action( $hook, $track );
		}
		$this->assertSame( [], $writes );
		$this->assertSame( $row, get_option( UsageScanStatus::OPTION ) );
		$this->assertSame( $cron, _get_cron_array() );
		foreach ( $lib as $id ) {
			$this->assertSame( $snapshot[ $id ], [ get_post( $id, ARRAY_A ), get_post_meta( $id ) ] );
		}
	}
}
