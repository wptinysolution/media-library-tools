<?php
/**
 * Duplicate scan control over MCP: `tsmlt/start-duplicate-scan` starts the
 * existing duplicate scan (DuplicateScanner, WP-Cron batches), never
 * restarts a running one, resumes a stalled one, and shares the scanner's
 * start lock with the Duplicates page; `tsmlt/get-duplicate-scan-status`
 * only reads. After a scan completes, `tsmlt/find-duplicate-media` lists
 * what it found.
 *
 * Concurrency is reproduced by holding the start lock, as another request
 * would, in one process (not truly parallel). Every attachment and file is
 * a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Abilities\Media\GetDuplicateScanStatus;
use TinySolutions\mlt\Abilities\Media\StartDuplicateScan;
use TinySolutions\mlt\Modules\Duplicate\DuplicateScanner;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;

/**
 * DuplicateScanAbilitiesTest
 */
class DuplicateScanAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Start ability.
	 *
	 * @var string
	 */
	const START = 'tsmlt/start-duplicate-scan';

	/**
	 * Status ability.
	 *
	 * @var string
	 */
	const STATUS = 'tsmlt/get-duplicate-scan-status';

	/**
	 * Dedicated MCP route.
	 *
	 * @var string
	 */
	const SCOPED_ROUTE = '/media-library-tools/mcp';

	/**
	 * Default gateway route.
	 *
	 * @var string
	 */
	const DEFAULT_ROUTE = '/mcp/mcp-adapter-default-server';

	/**
	 * Statements other than reads sent while observing.
	 *
	 * @var string[]
	 */
	private $writes = [];

	/**
	 * MCP session per route.
	 *
	 * @var array<string, string>
	 */
	private $sessions = [];

	/**
	 * JSON-RPC request counter.
	 *
	 * @var int
	 */
	private $request_id = 0;

	/**
	 * Files written for the test.
	 *
	 * @var string[]
	 */
	private $files = [];

	/**
	 * Administrator; no scan state, results or start lock.
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( self::START ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}tsmlt_duplicate_file" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test fixture.
		delete_option( DuplicateScanner::STATE_OPTION );
		delete_option( DuplicateScanner::START_LOCK_OPTION );
		wp_clear_scheduled_hook( DuplicateScanner::TICK_HOOK );
		$this->writes   = [];
		$this->sessions = [];
		$this->files    = [];
	}

	/**
	 * Remove filters, files, state and lock.
	 */
	public function tear_down() {
		remove_filter( 'query', [ $this, 'record_query' ] );
		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test cleanup.
			}
		}
		delete_option( DuplicateScanner::STATE_OPTION );
		delete_option( DuplicateScanner::START_LOCK_OPTION );
		wp_clear_scheduled_hook( DuplicateScanner::TICK_HOOK );
		parent::tear_down();
	}

	/**
	 * Record every statement that is not a read.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function record_query( $query ) {
		if ( ! preg_match( '/^\s*(SELECT|SHOW)\b/i', $query ) ) {
			$this->writes[] = $query;
		}
		return $query;
	}

	/**
	 * An image attachment whose file exists with the given bytes.
	 *
	 * @param string $file  File, relative to uploads.
	 * @param string $bytes Content.
	 *
	 * @return int
	 */
	private function image( string $file, string $bytes ): int {
		$path = wp_get_upload_dir()['basedir'] . '/' . $file;
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->files[] = $path;
		return (int) self::factory()->attachment->create(
			[
				'file'           => $file,
				'post_mime_type' => 'image/jpeg',
			]
		);
	}

	/**
	 * Run an ability.
	 *
	 * @param string $name  Ability.
	 * @param mixed  $input Input.
	 *
	 * @return mixed
	 */
	private static function call( string $name, $input = null ) {
		return wp_get_ability( $name )->execute( $input );
	}

	/**
	 * Store a scan state.
	 *
	 * @param array $state State.
	 */
	private static function state( array $state ) {
		update_option( DuplicateScanner::STATE_OPTION, $state, false );
	}

	/**
	 * Only administrators may start or read the scan; editors, authors and
	 * visitors are refused by the permission check and by a direct call.
	 */
	public function test_requires_manage_options() {
		foreach ( [ 'editor', 'author', 'subscriber' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			foreach ( [ self::START, self::STATUS ] as $name ) {
				$this->assertFalse( wp_get_ability( $name )->check_permissions(), $role . ' ' . $name );
			}
			$this->assertWPError( StartDuplicateScan::instance()->execute() );
			$this->assertWPError( GetDuplicateScanStatus::instance()->execute() );
		}
		wp_set_current_user( 0 );
		$this->assertWPError( StartDuplicateScan::instance()->execute() );
		$this->assertWPError( GetDuplicateScanStatus::instance()->execute() );
		$this->assertFalse( get_option( DuplicateScanner::STATE_OPTION ), 'Nothing was started.' );
	}

	/**
	 * A new scan starts in the background: state running, first batch
	 * scheduled, the library total snapshot; no media changes.
	 */
	public function test_start_a_new_scan() {
		$this->image( 'dupscan/a.jpg', 'A' );
		$this->image( 'dupscan/b.jpg', 'B' );

		$result = self::call( self::START );

		$this->assertSame( 'started', $result['result'] );
		$this->assertSame( 'running', $result['scan']['state'] );
		$this->assertFalse( $result['scan']['complete'] );
		$this->assertFalse( $result['scan']['stalled'] );
		$this->assertSame( 0, $result['scan']['processed'] );
		$this->assertSame( 2, $result['scan']['total'] );
		$this->assertSame( 0, $result['scan']['percent'] );
		$this->assertNotSame( '', $result['scan']['started_at'] );
		$this->assertNotFalse( wp_next_scheduled( DuplicateScanner::TICK_HOOK ) );
		$this->assertTrue( rest_validate_value_from_schema( $result, wp_get_ability( self::START )->get_output_schema(), 'result' ) );
	}

	/**
	 * A running scan is not restarted: its progress, start time and next
	 * batch stay as they were.
	 */
	public function test_start_while_running() {
		$this->image( 'dupscan/c.jpg', 'C' );
		self::state(
			[
				'status'     => 'running',
				'offset'     => 40,
				'total'      => 100,
				'started_at' => time() - 60,
				'updated_at' => time() - 5,
			]
		);
		wp_schedule_single_event( time() + 30, DuplicateScanner::TICK_HOOK );
		$next   = wp_next_scheduled( DuplicateScanner::TICK_HOOK );
		$before = get_option( DuplicateScanner::STATE_OPTION );

		$result = self::call( self::START );

		$this->assertSame( 'already_running', $result['result'] );
		$this->assertSame( 40, $result['scan']['processed'] );
		$this->assertSame( 40, $result['scan']['percent'] );
		$this->assertSame( $before, get_option( DuplicateScanner::STATE_OPTION ) );
		$this->assertSame( $next, wp_next_scheduled( DuplicateScanner::TICK_HOOK ) );
	}

	/**
	 * Starts from MCP and from the Duplicates page take the same start lock:
	 * while one holds it, an MCP start reports already_running and changes
	 * nothing, and the page's Start returns the progress without restarting.
	 */
	public function test_concurrent_starts_share_the_lock() {
		$this->image( 'dupscan/d.jpg', 'D' );
		$lock = ScanStartLock::instance()->acquire( DuplicateScanner::START_LOCK_OPTION );
		$this->assertIsString( $lock );

		$mcp = self::call( self::START );
		$this->assertSame( 'already_running', $mcp['result'] );
		$this->assertFalse( get_option( DuplicateScanner::STATE_OPTION ), 'MCP started nothing while the lock was held.' );

		$admin = DuplicateScanner::instance()->start();
		$this->assertSame( 'idle', $admin['status'], 'The page\'s Start did not run either.' );
		$this->assertFalse( wp_next_scheduled( DuplicateScanner::TICK_HOOK ) );

		ScanStartLock::instance()->release( $lock, DuplicateScanner::START_LOCK_OPTION );
		$this->assertSame( 'running', DuplicateScanner::instance()->start()['status'], 'Free again: the page starts a scan.' );
		$this->assertSame( 'already_running', self::call( self::START )['result'], 'MCP then sees it running.' );
		$this->assertFalse( get_option( DuplicateScanner::START_LOCK_OPTION ), 'Every start released the lock.' );

		// The usage scan's own lock is separate.
		$usage = ScanStartLock::instance()->acquire();
		$this->assertIsString( $usage );
		ScanStartLock::instance()->release( $usage );
	}

	/**
	 * A scan with no progress for over STALL_SECONDS is reported stalled and
	 * resumed from its offset, with its next batch scheduled; a recent one
	 * is not stalled.
	 */
	public function test_stalled_scan_is_resumed() {
		$this->image( 'dupscan/e.jpg', 'E' );
		$started = time() - 3600;
		self::state(
			[
				'status'     => 'running',
				'offset'     => 10,
				'total'      => 50,
				'started_at' => $started,
				'updated_at' => time() - DuplicateScanner::STALL_SECONDS - 1,
			]
		);
		$this->assertTrue( self::call( self::STATUS )['stalled'] );

		$result = self::call( self::START );

		$this->assertSame( 'resumed', $result['result'] );
		$this->assertSame( 10, $result['scan']['processed'], 'Resumed from where it stopped.' );
		$this->assertSame( $started, (int) get_option( DuplicateScanner::STATE_OPTION )['started_at'] );
		$this->assertNotFalse( wp_next_scheduled( DuplicateScanner::TICK_HOOK ) );

		self::state( [ 'updated_at' => time() ] + get_option( DuplicateScanner::STATE_OPTION ) );
		$this->assertFalse( self::call( self::STATUS )['stalled'] );
	}

	/**
	 * The status only reads: no write statement, no schedule change, the
	 * state option and stale result rows untouched (it does not call
	 * get_scan_status(), which purges them).
	 */
	public function test_status_is_read_only() {
		global $wpdb;
		self::state(
			[
				'status'     => 'running',
				'offset'     => 3,
				'total'      => 4,
				'started_at' => time() - 30,
				'updated_at' => time() - 2,
			]
		);
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test fixture: a row of a deleted attachment.
			$wpdb->prefix . 'tsmlt_duplicate_file',
			[
				'attachment_id' => 999999,
				'file_hash'     => str_repeat( 'f', 32 ),
				'file_size'     => 10,
				'file_path'     => 'gone.jpg',
			]
		);
		$state = get_option( DuplicateScanner::STATE_OPTION );
		$cron  = _get_cron_array();
		add_filter( 'query', [ $this, 'record_query' ] );

		$status = self::call( self::STATUS );

		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertSame( [], $this->writes );
		$this->assertSame( $state, get_option( DuplicateScanner::STATE_OPTION ) );
		$this->assertSame( $cron, _get_cron_array() );
		$this->assertSame( '1', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tsmlt_duplicate_file WHERE attachment_id = 999999" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test read.
		$this->assertSame( 'running', $status['state'] );
		$this->assertSame( 75, $status['percent'] );
		$this->assertTrue( rest_validate_value_from_schema( $status, wp_get_ability( self::STATUS )->get_output_schema(), 'status' ) );
	}

	/**
	 * Every stored state is reported in the closed schema: idle before any
	 * scan, complete (done) at 100%, cancelled, and running capped at 99%.
	 */
	public function test_states() {
		$schema = wp_get_ability( self::STATUS )->get_output_schema();
		$idle   = self::call( self::STATUS );
		$this->assertSame( 'idle', $idle['state'] );
		$this->assertSame( '', $idle['started_at'] );
		$this->assertTrue( rest_validate_value_from_schema( $idle, $schema, 'status' ) );

		foreach (
			[
				[ 'done', 5, 5, 'complete', 100, true ],
				[ 'cancelled', 2, 5, 'cancelled', 40, false ],
				[ 'running', 5, 5, 'running', 99, false ],
				[ 'something-else', 0, 0, 'idle', 0, false ],
			] as $case
		) {
			self::state(
				[
					'status'     => $case[0],
					'offset'     => $case[1],
					'total'      => $case[2],
					'started_at' => time(),
					'updated_at' => time(),
				]
			);
			$status = self::call( self::STATUS );
			$this->assertSame( $case[3], $status['state'], $case[0] );
			$this->assertSame( $case[4], $status['percent'], $case[0] );
			$this->assertSame( $case[5], $status['complete'], $case[0] );
			$this->assertTrue( rest_validate_value_from_schema( $status, $schema, 'status' ) );
		}
	}

	/**
	 * Both abilities take no input: anything else is refused.
	 */
	public function test_closed_input() {
		foreach ( [ self::START, self::STATUS ] as $name ) {
			$this->assertWPError( self::call( $name, [ 'restart' => true ] ), $name );
			$this->assertFalse( wp_get_ability( $name )->get_input_schema()['additionalProperties'] );
		}
		$this->assertWPError( StartDuplicateScan::instance()->execute( [ 'restart' => true ] ) );
		$this->assertFalse( get_option( DuplicateScanner::STATE_OPTION ) );
	}

	/**
	 * Start, let the batches run, then the status is complete and
	 * tsmlt/find-duplicate-media lists the two identical files.
	 */
	public function test_scan_to_results() {
		$first  = $this->image( 'dupscan/same-1.jpg', str_repeat( 'same bytes ', 50 ) );
		$second = $this->image( 'dupscan/same-2.jpg', str_repeat( 'same bytes ', 50 ) );
		$this->image( 'dupscan/other.jpg', 'different' );

		$this->assertSame( 'started', self::call( self::START )['result'] );
		$runs = 0;
		while ( $runs < 20 && 'complete' !== self::call( self::STATUS )['state'] ) {
			DuplicateScanner::run_tick();
			++$runs;
		}
		$status = self::call( self::STATUS );
		$this->assertTrue( $status['complete'] );
		$this->assertSame( 100, $status['percent'] );
		$this->assertSame( 3, $status['processed'] );

		$found = self::call( 'tsmlt/find-duplicate-media' );
		$this->assertIsArray( $found, is_wp_error( $found ) ? $found->get_error_message() : '' );
		$this->assertCount( 1, $found['groups'] );
		$this->assertEqualsCanonicalizing( [ $first, $second ], wp_list_pluck( $found['groups'][0]['items'], 'id' ) );
		$this->assertTrue( $found['scan']['complete'] );

		$this->assertSame( 'started', self::call( self::START )['result'], 'After completion a new scan can start.' );
	}

	/**
	 * The existing duplicate scanner behaves as before for the Duplicates
	 * page: Start restarts even a running scan from the beginning.
	 */
	public function test_admin_start_unchanged() {
		$this->image( 'dupscan/f.jpg', 'F' );
		self::state(
			[
				'status'     => 'running',
				'offset'     => 1,
				'total'      => 1,
				'started_at' => time() - 100,
				'updated_at' => time() - 10,
			]
		);
		$progress = DuplicateScanner::instance()->start();
		$this->assertSame( 'running', $progress['status'] );
		$this->assertSame( 0, $progress['offset'] );
		$this->assertTrue( $progress['tick_scheduled'] );
	}

	/**
	 * Both are listed on the dedicated endpoint only (not on the default
	 * gateway), with closed input, and the status runs over MCP.
	 */
	public function test_dedicated_endpoint_only() {
		foreach ( [ self::START, self::STATUS ] as $name ) {
			$this->assertFalse( wp_get_ability( $name )->get_meta()['mcp']['public'] );
			$this->assertContains( $name, McpServerRegistration::instance()->get_tool_names() );
		}
		$this->assertTrue( wp_get_ability( self::STATUS )->get_meta()['annotations']['readonly'] );
		$this->assertFalse( wp_get_ability( self::START )->get_meta()['annotations']['readonly'] );
		$this->assertFalse( wp_get_ability( self::START )->get_meta()['annotations']['destructive'] );

		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run the transport part.' );
		}
		$this->connect( self::SCOPED_ROUTE );
		$tools = array_column( $this->send( self::SCOPED_ROUTE, 'tools/list' )['result']['tools'], null, 'name' );
		foreach ( [ 'tsmlt-start-duplicate-scan', 'tsmlt-get-duplicate-scan-status' ] as $tool ) {
			$this->assertArrayHasKey( $tool, $tools );
			$this->assertFalse( $tools[ $tool ]['inputSchema']['additionalProperties'] );
		}
		$call = $this->send(
			self::SCOPED_ROUTE,
			'tools/call',
			[
				'name'      => 'tsmlt-get-duplicate-scan-status',
				'arguments' => (object) [],
			]
		)['result'];
		$this->assertFalse( $call['isError'], wp_json_encode( $call ) );
		$this->assertSame( 'idle', $call['structuredContent']['state'] );

		$this->connect( self::DEFAULT_ROUTE );
		$info = $this->send(
			self::DEFAULT_ROUTE,
			'tools/call',
			[
				'name'      => 'mcp-adapter-get-ability-info',
				'arguments' => [ 'ability_name' => self::START ],
			]
		)['result'];
		$this->assertTrue( $info['isError'], 'Not offered on the default gateway.' );
		$this->assertFalse( get_option( DuplicateScanner::STATE_OPTION ), 'Nothing started over MCP here.' );
	}

	/**
	 * Send one JSON-RPC message to a route.
	 *
	 * @param string $route  Route.
	 * @param string $method Method.
	 * @param array  $params Params.
	 *
	 * @return array
	 */
	private function send( string $route, string $method, array $params = [] ): array {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'Accept', 'application/json, text/event-stream' );
		if ( isset( $this->sessions[ $route ] ) ) {
			$request->set_header( 'Mcp-Session-Id', $this->sessions[ $route ] );
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
			$this->sessions[ $route ] = $response->get_headers()['Mcp-Session-Id'];
		}
		$data = $response->get_data();
		return is_array( $data ) ? $data : json_decode( wp_json_encode( $data ), true );
	}

	/**
	 * Open a session on a route.
	 *
	 * @param string $route Route.
	 */
	private function connect( string $route ) {
		$this->send(
			$route,
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
		$this->send( $route, 'notifications/initialized' );
	}
}
