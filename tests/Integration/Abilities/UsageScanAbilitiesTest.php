<?php
/**
 * Integration tests for tsmlt/start-usage-scan and tsmlt/get-usage-scan-status,
 * their effect on tsmlt/get-media-usage, and a complete used/unused audit run
 * the way an MCP client runs it: start, poll, page through image search
 * results, check usage in batches.
 *
 * WP-Cron does not run in tests: scan batches are run by calling the
 * scanner's tick handler for each scheduled event. Outgoing HTTP (spawn_cron)
 * is blocked. The MCP tests run when the MCP Adapter is loaded
 * (MCP_ADAPTER_DIR is set). Every post and attachment is a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\StartUsageScan;
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsageScanAbilitiesTest
 */
class UsageScanAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Ability names.
	 */
	const START  = 'tsmlt/start-usage-scan';
	const STATUS = 'tsmlt/get-usage-scan-status';
	const USAGE  = 'tsmlt/get-media-usage';

	/**
	 * Routes.
	 */
	const SCOPED_ROUTE  = '/media-library-tools/mcp';
	const DEFAULT_ROUTE = '/mcp/mcp-adapter-default-server';

	/**
	 * Keys of the status.
	 */
	const STATUS_KEYS = [ 'scan_id', 'state', 'complete', 'processed', 'total', 'percent', 'last_update', 'started_at', 'last_tick_at', 'ever_started', 'stalled', 'has_error' ];

	/**
	 * MCP sessions per route.
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
	 * Administrator; no scan, no scheduled batch, no HTTP, fast scan.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( UsageScanStatus::OPTION );
		delete_option( StartUsageScan::START_LOCK_OPTION );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		delete_transient( UsedWhereScanner::SCAN_LOCK_KEY );
		$this->sessions = [];
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
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
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * Run an ability through the Abilities API.
	 *
	 * @param string $name  Ability name.
	 * @param mixed  $input Input.
	 *
	 * @return mixed
	 */
	private function call_ability( string $name, $input = null ) {
		$ability = wp_get_ability( $name );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		return $ability->execute( $input );
	}

	/**
	 * Call an execute callback directly.
	 *
	 * @param string $name  Ability name.
	 * @param mixed  $input Input.
	 *
	 * @return mixed
	 */
	private static function direct( string $name, $input = null ) {
		$class = self::START === $name ? StartUsageScan::class : TinySolutions\mlt\Abilities\Media\GetUsageScanStatus::class;
		return $class::instance()->execute( $input );
	}

	/**
	 * Scheduled scan batches: list of [ timestamp, offset ].
	 *
	 * @return array
	 */
	private static function scheduled_ticks(): array {
		$ticks = [];
		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			foreach ( $hooks[ UsedWhereScanner::SCAN_TICK_HOOK ] ?? [] as $event ) {
				$ticks[] = [ (int) $timestamp, (int) ( $event['args'][0] ?? 0 ) ];
			}
		}
		return $ticks;
	}

	/**
	 * Run scheduled scan batches, as WP-Cron would, until none is left.
	 *
	 * @param int  $limit       Most batches.
	 * @param bool $must_finish Fail when batches are left after $limit.
	 */
	private function run_scheduled_ticks( int $limit = 50, bool $must_finish = true ) {
		for ( $i = 0; $i < $limit; $i++ ) {
			$ticks = self::scheduled_ticks();
			if ( ! $ticks ) {
				return;
			}
			[ $timestamp, $offset ] = $ticks[0];
			wp_unschedule_event( $timestamp, UsedWhereScanner::SCAN_TICK_HOOK, [ $offset ] );
			UsedWhereScanner::instance()->run_tick_batch( $offset );
		}
		if ( $must_finish ) {
			$this->fail( 'The scan did not finish.' );
		}
	}

	/**
	 * Store a scan status row.
	 *
	 * @param array $status Status.
	 */
	private static function set_status( array $status ) {
		update_option( UsageScanStatus::OPTION, $status, false );
	}

	/**
	 * Assert an error code.
	 *
	 * @param string $code   Code.
	 * @param mixed  $result Result.
	 */
	private function assert_error( string $code, $result ) {
		$this->assertWPError( $result );
		$this->assertSame( $code, $result->get_error_code(), $result->get_error_message() );
	}

	/**
	 * An image attachment with a file name.
	 *
	 * @param string $file File under uploads.
	 * @param string $mime MIME type.
	 *
	 * @return int
	 */
	private function attachment( string $file, string $mime = 'image/jpeg' ): int {
		return (int) self::factory()->attachment->create(
			[
				'file'           => $file,
				'post_title'     => basename( $file ),
				'post_mime_type' => $mime,
			]
		);
	}

	/**
	 * Snapshot of attachment data that must never change (rows and meta
	 * other than usage tracking).
	 *
	 * @param int[] $ids Attachment IDs.
	 *
	 * @return array
	 */
	private static function attachment_snapshot( array $ids ): array {
		$snapshot = [];
		foreach ( $ids as $id ) {
			$meta = get_post_meta( $id );
			unset( $meta[ UsedWhereScanner::META_KEY ], $meta['_tsmlt_usage_tracked'], $meta['_tsmlt_permalink_fp'] );
			$post = get_post( $id, ARRAY_A );
			// The scanner sets the "attached post" as part of its normal run,
			// with wp_update_post(), which also updates the modified time.
			unset( $post['post_parent'], $post['ancestors'], $post['post_modified'], $post['post_modified_gmt'] );
			$snapshot[ $id ] = [ $post, $meta ];
		}
		return $snapshot;
	}

	// ---------------------------------------------------------------------
	// tsmlt/start-usage-scan
	// ---------------------------------------------------------------------

	/**
	 * With no scan, a scan is queued with one scheduled batch.
	 */
	public function test_start_queues_a_scan() {
		$result = $this->call_ability( self::START );

		$this->assertSame( [ 'result', 'scan' ], array_keys( $result ) );
		$this->assertSame( 'started', $result['result'] );
		$this->assertSame( self::STATUS_KEYS, array_keys( $result['scan'] ) );
		$this->assertSame( 'queued', $result['scan']['state'] );
		$this->assertTrue( $result['scan']['ever_started'] );
		$this->assertFalse( $result['scan']['stalled'] );
		$this->assertCount( 1, self::scheduled_ticks() );
		$this->assertSame( 0, self::scheduled_ticks()[0][1] );
		$this->assertFalse( get_option( StartUsageScan::START_LOCK_OPTION ), 'Start lock released.' );
	}

	/**
	 * A scan already queued or running is returned, not restarted: the
	 * status, the scheduled batches and the usage records stay as they are.
	 *
	 * @return array
	 */
	public function data_active_states(): array {
		return [
			'queued'  => [ 'queued' ],
			'running' => [ 'running' ],
		];
	}

	/**
	 * Already running.
	 *
	 * @dataProvider data_active_states
	 *
	 * @param string $state State.
	 */
	public function test_running_scan_is_not_restarted( string $state ) {
		$image = $this->attachment( 'run/a.jpg' );
		update_post_meta( $image, UsedWhereScanner::META_KEY, [ [ 'post_id' => 0, 'post_type' => 'site_settings', 'usage_type' => 'site_logo' ] ] );
		$status = [
			'state'        => $state,
			'processed'    => 3,
			'total'        => 9,
			'next_offset'  => 3,
			'started_at'   => current_time( 'mysql' ),
			'last_tick_at' => 'running' === $state ? current_time( 'mysql' ) : '',
			'timestamp'    => current_time( 'mysql' ),
		];
		self::set_status( $status );
		wp_schedule_single_event( time() + 20, UsedWhereScanner::SCAN_TICK_HOOK, [ 3 ] );
		$cron = _get_cron_array();

		$result = $this->call_ability( self::START );

		$this->assertSame( 'already_running', $result['result'] );
		$this->assertSame( $state, $result['scan']['state'] );
		$this->assertSame( 3, $result['scan']['processed'] );
		$this->assertSame( $status, get_option( UsageScanStatus::OPTION ) );
		$this->assertSame( $cron, _get_cron_array() );
		$this->assertNotEmpty( get_post_meta( $image, UsedWhereScanner::META_KEY, true ), 'Usage records kept.' );
	}

	/**
	 * Starting twice schedules one batch only; the second call reports the
	 * first scan.
	 */
	public function test_second_start_does_not_schedule_again() {
		$this->assertSame( 'started', $this->call_ability( self::START )['result'] );
		$status = get_option( UsageScanStatus::OPTION );

		$this->assertSame( 'already_running', $this->call_ability( self::START )['result'] );
		$this->assertSame( 'already_running', $this->call_ability( self::START )['result'] );

		$this->assertCount( 1, self::scheduled_ticks() );
		$this->assertSame( $status, get_option( UsageScanStatus::OPTION ) );
	}

	/**
	 * While another request holds the start lock, nothing starts; a lock
	 * left behind long ago is ignored.
	 */
	public function test_start_lock_prevents_concurrent_starts() {
		add_option( StartUsageScan::START_LOCK_OPTION, time(), '', false );

		$blocked = $this->call_ability( self::START );

		$this->assertSame( 'already_running', $blocked['result'] );
		$this->assertSame( 'idle', $blocked['scan']['state'] );
		$this->assertSame( [], self::scheduled_ticks() );
		$this->assertFalse( get_option( UsageScanStatus::OPTION ) );
		$this->assertNotFalse( get_option( StartUsageScan::START_LOCK_OPTION ), "The other request's lock is left alone." );

		update_option( StartUsageScan::START_LOCK_OPTION, time() - StartUsageScan::START_LOCK_SECONDS - 5 );

		$this->assertSame( 'started', $this->call_ability( self::START )['result'] );
		$this->assertCount( 1, self::scheduled_ticks() );
		$this->assertFalse( get_option( StartUsageScan::START_LOCK_OPTION ) );
	}

	/**
	 * After a scan that ended (complete, cancelled, error), a new one starts.
	 *
	 * @return array
	 */
	public function data_ended_states(): array {
		return [
			'complete'  => [ 'complete' ],
			'cancelled' => [ 'cancelled' ],
			'error'     => [ 'error' ],
		];
	}

	/**
	 * Ended scans.
	 *
	 * @dataProvider data_ended_states
	 *
	 * @param string $state State.
	 */
	public function test_new_scan_after_an_ended_one( string $state ) {
		self::set_status(
			[
				'state'      => $state,
				'complete'   => 'complete' === $state,
				'last_error' => 'error' === $state ? 'Boom' : '',
			]
		);

		$result = $this->call_ability( self::START );

		$this->assertSame( 'started', $result['result'] );
		$this->assertSame( 'queued', $result['scan']['state'] );
		$this->assertFalse( $result['scan']['has_error'] );
		$this->assertCount( 1, self::scheduled_ticks() );
	}

	/**
	 * A stalled scan is resumed from where it stopped, with one batch
	 * scheduled however often start is called.
	 *
	 * @return array
	 */
	public function data_stalled(): array {
		return [
			'running, batches stopped'  => [ 'running', '2000-01-01 00:00:00', 10 ],
			'queued, never got a batch' => [ 'queued', '', 0 ],
		];
	}

	/**
	 * Stalled scans.
	 *
	 * @dataProvider data_stalled
	 *
	 * @param string $state     State.
	 * @param string $last_tick Last batch time.
	 * @param int    $offset    Next offset.
	 */
	public function test_stalled_scan_is_resumed( string $state, string $last_tick, int $offset ) {
		self::set_status(
			[
				'state'        => $state,
				'processed'    => $offset,
				'total'        => 40,
				'next_offset'  => $offset,
				'started_at'   => '2000-01-01 00:00:00',
				'last_tick_at' => $last_tick,
			]
		);
		$this->assertTrue( UsageScanStatus::read()['stalled'], 'Precondition.' );

		$first  = $this->call_ability( self::START );
		$second = $this->call_ability( self::START );

		$this->assertSame( 'resumed', $first['result'] );
		$this->assertSame( 'resumed', $second['result'] );
		$this->assertSame( [ $offset ], array_column( self::scheduled_ticks(), 1 ) );
		$this->assertSame( $state, get_option( UsageScanStatus::OPTION )['state'], 'Not restarted.' );
		$this->assertSame( $offset, get_option( UsageScanStatus::OPTION )['next_offset'] );
	}

	/**
	 * Resuming a stalled scan whose batch is still pending (WP-Cron not
	 * running) does not schedule a second one.
	 */
	public function test_resume_keeps_a_pending_batch() {
		self::set_status(
			[
				'state'        => 'queued',
				'next_offset'  => 0,
				'started_at'   => '2000-01-01 00:00:00',
				'last_tick_at' => '',
			]
		);
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, UsedWhereScanner::SCAN_TICK_HOOK, [ 0 ] );

		$this->assertSame( 'resumed', $this->call_ability( self::START )['result'] );

		$this->assertCount( 1, self::scheduled_ticks() );
	}

	/**
	 * Input is refused: by the schema, and by the callback directly.
	 */
	public function test_start_refuses_input() {
		$this->assert_error( 'ability_invalid_input', $this->call_ability( self::START, [ 'force' => true ] ) );
		$this->assert_error( AbilityGuard::ERROR_INVALID_INPUT, self::direct( self::START, [ 'force' => true ] ) );
		$this->assert_error( AbilityGuard::ERROR_INVALID_INPUT, self::direct( self::START, 'go' ) );
		$this->assertSame( [], self::scheduled_ticks() );
		$this->assertSame( 'started', $this->call_ability( self::START, [] )['result'], 'An empty object is fine.' );
	}

	// ---------------------------------------------------------------------
	// Permissions
	// ---------------------------------------------------------------------

	/**
	 * Refused users.
	 *
	 * @return array
	 */
	public function data_refused_users(): array {
		return [
			'logged out' => [ '', AbilityGuard::ERROR_UNAUTHENTICATED ],
			'editor'     => [ 'editor', AbilityGuard::ERROR_FORBIDDEN ],
			'author'     => [ 'author', AbilityGuard::ERROR_FORBIDDEN ],
			'subscriber' => [ 'subscriber', AbilityGuard::ERROR_FORBIDDEN ],
		];
	}

	/**
	 * Both abilities need manage_options, checked by the permission callback
	 * and again by the callback; nothing is scheduled for a refused user.
	 *
	 * @dataProvider data_refused_users
	 *
	 * @param string $role Role ('' for logged out).
	 * @param string $code Code from the callback.
	 */
	public function test_require_manage_options( string $role, string $code ) {
		wp_set_current_user( $role ? self::factory()->user->create( [ 'role' => $role ] ) : 0 );

		foreach ( [ self::START, self::STATUS ] as $name ) {
			$this->assert_error( 'ability_invalid_permissions', $this->call_ability( $name ) );
			$this->assert_error( $code, self::direct( $name ) );
		}
		$this->assertSame( [], self::scheduled_ticks() );
		$this->assertFalse( get_option( UsageScanStatus::OPTION ) );
	}

	// ---------------------------------------------------------------------
	// tsmlt/get-usage-scan-status
	// ---------------------------------------------------------------------

	/**
	 * Stored status rows and the reported state.
	 *
	 * @return array
	 */
	public function data_status_rows(): array {
		return [
			'never started'         => [ null, 'idle', false, false ],
			'queued'                => [ [ 'state' => 'queued' ], 'queued', false, true ],
			'running'               => [ [ 'state' => 'running' ], 'running', false, true ],
			'complete'              => [ [ 'state' => 'complete' ], 'complete', true, true ],
			'cancelled'             => [ [ 'state' => 'cancelled' ], 'cancelled', false, true ],
			'error'                 => [ [ 'state' => 'error' ], 'error', false, true ],
			'row from before state' => [ [ 'complete' => true ], 'complete', true, true ],
			'unknown state'         => [ [ 'state' => 'weird' ], 'idle', false, true ],
		];
	}

	/**
	 * States are told apart; never started is idle with ever_started false.
	 *
	 * @dataProvider data_status_rows
	 *
	 * @param mixed  $row          Stored row (null: none).
	 * @param string $state        Expected state.
	 * @param bool   $complete     Expected complete.
	 * @param bool   $ever_started Expected ever_started.
	 */
	public function test_status_states( $row, string $state, bool $complete, bool $ever_started ) {
		if ( null !== $row ) {
			self::set_status( $row );
		}

		$status = $this->call_ability( self::STATUS );

		$this->assertSame( self::STATUS_KEYS, array_keys( $status ) );
		$this->assertSame( $state, $status['state'] );
		$this->assertSame( $complete, $status['complete'] );
		$this->assertSame( $ever_started, $status['ever_started'] );
		$this->assertSame( 'error' === $state, $status['has_error'] );
	}

	/**
	 * Progress and times are reported; an invalid time is reported as empty;
	 * the error text (which can hold server paths) is not returned.
	 */
	public function test_status_fields_and_no_error_text() {
		self::set_status(
			[
				'state'        => 'error',
				'processed'    => 12,
				'total'        => 30,
				'started_at'   => '2026-10-27 08:00:00',
				'last_tick_at' => '<b>bad</b>',
				'timestamp'    => '2026-10-27 08:05:00',
				'last_error'   => 'Failed in /var/www/html/wp-content/secret-path.php',
				'next_offset'  => 12,
			]
		);

		$status = $this->call_ability( self::STATUS );

		$this->assertSame(
			[
				'scan_id'      => '',
				'state'        => 'error',
				'complete'     => false,
				'processed'    => 12,
				'total'        => 30,
				'percent'      => 40,
				'last_update'  => '2026-10-27 08:05:00',
				'started_at'   => '2026-10-27 08:00:00',
				'last_tick_at' => '',
				'ever_started' => true,
				'stalled'      => false,
				'has_error'    => true,
			],
			$status
		);
		$this->assertStringNotContainsString( 'secret-path', (string) wp_json_encode( $status ) );
	}

	/**
	 * A stalled scan is reported as stalled and left alone: nothing is
	 * scheduled or written, unlike UsedWhereScanner::get_scan_status().
	 */
	public function test_status_never_reschedules_a_stalled_scan() {
		$row = [
			'state'        => 'running',
			'processed'    => 5,
			'total'        => 50,
			'next_offset'  => 5,
			'started_at'   => '2000-01-01 00:00:00',
			'last_tick_at' => '2000-01-01 00:01:00',
		];
		self::set_status( $row );
		set_transient( UsedWhereScanner::SCAN_LOCK_KEY, 123, 120 );
		$cron   = _get_cron_array();
		$writes = [];
		$track  = static function () use ( &$writes ) {
			$writes[] = current_filter();
		};
		$hooks  = [ 'add_option', 'update_option', 'delete_option', 'add_post_meta', 'update_post_meta', 'delete_post_meta' ];
		foreach ( $hooks as $hook ) {
			add_action( $hook, $track );
		}

		$status = $this->call_ability( self::STATUS );
		$usage  = $this->call_ability( self::USAGE, [ 'ids' => [ $this->attachment( 'stall/a.jpg' ) ] ] );

		foreach ( $hooks as $hook ) {
			remove_action( $hook, $track );
		}
		$this->assertTrue( $status['stalled'] );
		$this->assertSame( 'running', $status['state'] );
		$this->assertNull( $usage['items'][0]['used'] );
		$this->assertSame( [], array_diff( $writes, [ 'add_post_meta', 'update_post_meta' ] ), 'No option written.' );
		$this->assertSame( $cron, _get_cron_array() );
		$this->assertSame( $row, get_option( UsageScanStatus::OPTION ) );
		$this->assertSame( 123, get_transient( UsedWhereScanner::SCAN_LOCK_KEY ) );
	}

	/**
	 * Input is refused.
	 */
	public function test_status_refuses_input() {
		$this->assert_error( 'ability_invalid_input', $this->call_ability( self::STATUS, [ 'state' => 'complete' ] ) );
		$this->assert_error( AbilityGuard::ERROR_INVALID_INPUT, self::direct( self::STATUS, [ 'x' => 1 ] ) );
		$this->assertSame( 'idle', $this->call_ability( self::STATUS, [] )['state'] );
	}

	// ---------------------------------------------------------------------
	// Scan lifecycle and tsmlt/get-media-usage
	// ---------------------------------------------------------------------

	/**
	 * Fixtures: two used images (featured image, content) and one unused.
	 *
	 * @return array{featured: int, content: int, unused: int, post: int}
	 */
	private function library(): array {
		$featured = $this->attachment( 'lib/featured.jpg' );
		$content  = $this->attachment( 'lib/content.jpg' );
		$unused   = $this->attachment( 'lib/unused.jpg' );
		$post     = (int) self::factory()->post->create(
			[
				'post_title'   => 'Uses images',
				'post_content' => '<img src="' . esc_url( wp_get_attachment_url( $content ) ) . '" class="wp-image-' . $content . '" />',
			]
		);
		set_post_thumbnail( $post, $featured );
		return compact( 'featured', 'content', 'unused', 'post' );
	}

	/**
	 * Usage of some attachments.
	 *
	 * @param int[] $ids IDs.
	 *
	 * @return array<int, array> Items by ID.
	 */
	private function usage( array $ids ): array {
		$result = $this->call_ability( self::USAGE, [ 'ids' => $ids ] );
		$this->assertIsArray( $result );
		return array_column( $result['items'], null, 'id' );
	}

	/**
	 * Start, run the batches, and get-media-usage moves from unknown to
	 * used / not used; the status ability follows the same lifecycle; no
	 * attachment data changes.
	 */
	public function test_scan_lifecycle_and_media_usage() {
		global $wpdb;
		$lib = $this->library();
		$ids = [ $lib['featured'], $lib['content'], $lib['unused'] ];
		// Backdate, so an update during the scan cannot fall in the same second.
		foreach ( $ids as $id ) {
			$wpdb->update( $wpdb->posts, [ 'post_modified' => '2001-01-01 00:00:00', 'post_modified_gmt' => '2001-01-01 00:00:00' ], [ 'ID' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test fixture.
			clean_post_cache( $id );
		}
		$snapshot = self::attachment_snapshot( $ids );

		$this->assertSame( 'started', $this->call_ability( self::START )['result'] );
		$this->assertSame( 'queued', $this->call_ability( self::STATUS )['state'] );
		$queued = $this->usage( $ids );
		$this->assertNull( $queued[ $lib['unused'] ]['used'], 'Unknown before the scan finishes.' );

		$this->run_scheduled_ticks();

		$status = $this->call_ability( self::STATUS );
		$this->assertSame( 'complete', $status['state'] );
		$this->assertTrue( $status['complete'] );
		$this->assertSame( $status['total'], $status['processed'] );
		$done = $this->usage( $ids );
		$this->assertTrue( $done[ $lib['featured'] ]['used'] );
		$this->assertContains( 'featured', wp_list_pluck( $done[ $lib['featured'] ]['usages'], 'usage_type' ) );
		$this->assertTrue( $done[ $lib['content'] ]['used'] );
		$this->assertContains( 'content', wp_list_pluck( $done[ $lib['content'] ]['usages'], 'usage_type' ) );
		$this->assertFalse( $done[ $lib['unused'] ]['used'] );

		$this->assertSame( $snapshot, self::attachment_snapshot( $ids ), 'Attachment rows and meta unchanged.' );
	}

	/**
	 * A scan that ends in error or is cancelled leaves items without a
	 * recorded usage unknown, never unused.
	 *
	 * @return array
	 */
	public function data_failed_ends(): array {
		return [
			'error'     => [ 'error' ],
			'cancelled' => [ 'cancelled' ],
		];
	}

	/**
	 * Failed or cancelled scans.
	 *
	 * @dataProvider data_failed_ends
	 *
	 * @param string $end Final state.
	 */
	public function test_failed_scan_leaves_items_unknown( string $end ) {
		$lib = $this->library();
		$this->call_ability( self::START );
		if ( 'cancelled' === $end ) {
			UsedWhereScanner::instance()->cancel_scheduled_scan();
		} else {
			self::set_status( array_merge( get_option( UsageScanStatus::OPTION ), [ 'state' => 'error', 'last_error' => 'Boom' ] ) );
		}

		$status = $this->call_ability( self::STATUS );
		$items  = $this->usage( [ $lib['featured'], $lib['unused'] ] );

		$this->assertSame( $end, $status['state'] );
		$this->assertFalse( $status['complete'] );
		$this->assertNull( $items[ $lib['featured'] ]['used'], 'Records were cleared by the new scan; unknown, not unused.' );
		$this->assertNull( $items[ $lib['unused'] ]['used'] );
	}

	// ---------------------------------------------------------------------
	// Consistency while scans start and run
	// ---------------------------------------------------------------------

	/**
	 * A new scan is marked queued (with a new scan_id) before any usage
	 * record is deleted, so no reader sees the old "complete" next to
	 * records being cleared.
	 */
	public function test_status_is_queued_before_records_are_wiped() {
		$image = $this->attachment( 'wipe/a.jpg' );
		update_post_meta( $image, UsedWhereScanner::META_KEY, [ [ 'post_id' => 0, 'post_type' => 'site_settings', 'usage_type' => 'site_logo' ] ] );
		self::set_status( [ 'state' => 'complete', 'scan_id' => '11111111-1111-4111-8111-111111111111' ] );
		$seen  = [];
		$watch = static function ( $query ) use ( &$seen ) {
			if ( 0 === stripos( ltrim( $query ), 'DELETE' ) && false !== strpos( $query, UsedWhereScanner::META_KEY ) ) {
				$seen[] = UsageScanStatus::read();
			}
			return $query;
		};
		add_filter( 'query', $watch );

		UsedWhereScanner::instance()->start_scheduled_scan();

		remove_filter( 'query', $watch );
		$this->assertNotEmpty( $seen, 'The wipe ran.' );
		$this->assertSame( 'queued', $seen[0]['state'] );
		$this->assertNotSame( '11111111-1111-4111-8111-111111111111', $seen[0]['scan_id'] );
		$this->assertSame( $seen[0]['scan_id'], UsageScanStatus::read()['scan_id'], 'The same scan_id is kept after the start.' );
	}

	/**
	 * Records deleted with direct queries are also dropped from the object
	 * cache (a persistent cache would otherwise keep serving them).
	 */
	public function test_wipe_clears_cached_records() {
		$image = $this->attachment( 'cache/a.jpg' );
		$post  = (int) self::factory()->post->create();
		wp_update_post( [ 'ID' => $image, 'post_parent' => $post ] );
		update_post_meta( $image, UsedWhereScanner::META_KEY, [ [ 'post_id' => $post, 'post_type' => 'post', 'usage_type' => 'content' ] ] );
		$this->assertNotEmpty( get_post_meta( $image, UsedWhereScanner::META_KEY, true ), 'Precondition: cached.' );
		$this->assertSame( $post, (int) get_post( $image )->post_parent );

		UsedWhereScanner::instance()->start_scheduled_scan();

		$this->assertSame( '', get_post_meta( $image, UsedWhereScanner::META_KEY, true ) );
		$this->assertSame( 0, (int) get_post( $image )->post_parent );
	}

	/**
	 * A scan that starts while tsmlt/get-media-usage reads the records makes
	 * "not used" unknown in that response, and scan.complete false.
	 */
	public function test_media_usage_rechecks_status_after_reading() {
		$unused = $this->attachment( 'race/unused.jpg' );
		$other  = $this->attachment( 'race/other.jpg' );
		self::set_status( [ 'state' => 'complete', 'scan_id' => '22222222-2222-4222-8222-222222222222' ] );
		$fired = false;
		$start = static function ( $value, $object_id, $meta_key ) use ( &$fired, $other ) {
			if ( ! $fired && UsedWhereScanner::META_KEY === $meta_key && $other === (int) $object_id ) {
				$fired = true;
				UsedWhereScanner::instance()->start_scheduled_scan();
			}
			return $value;
		};
		add_filter( 'get_post_metadata', $start, 10, 3 );

		$result = $this->call_ability( self::USAGE, [ 'ids' => [ $unused, $other ] ] );

		remove_filter( 'get_post_metadata', $start, 10 );
		$this->assertTrue( $fired );
		$this->assertSame( 'queued', $result['scan']['state'] );
		$this->assertFalse( $result['scan']['complete'] );
		$this->assertSame( [ null, null ], wp_list_pluck( $result['items'], 'used' ) );
	}

	/**
	 * A scan that finishes during the request is not reported as verified
	 * either: the state is complete but scan.complete is false (ask again).
	 */
	public function test_media_usage_scan_finishing_mid_request_is_not_verified() {
		$image = $this->attachment( 'race/finish.jpg' );
		self::set_status( [ 'state' => 'running', 'scan_id' => '33333333-3333-4333-8333-333333333333' ] );
		$finish = static function ( $value, $object_id, $meta_key ) use ( $image ) {
			if ( UsedWhereScanner::META_KEY === $meta_key && $image === (int) $object_id ) {
				update_option( UsageScanStatus::OPTION, [ 'state' => 'complete', 'scan_id' => '33333333-3333-4333-8333-333333333333' ], false );
			}
			return $value;
		};
		add_filter( 'get_post_metadata', $finish, 10, 3 );

		$result = $this->call_ability( self::USAGE, [ 'ids' => [ $image ] ] );

		remove_filter( 'get_post_metadata', $finish, 10 );
		$this->assertSame( 'complete', $result['scan']['state'] );
		$this->assertFalse( $result['scan']['complete'] );
		$this->assertNull( $result['items'][0]['used'] );
		$this->assertFalse( $this->call_ability( self::USAGE, [ 'ids' => [ $image ] ] )['items'][0]['used'], 'Verified on the next call.' );
	}

	/**
	 * Every start gets a new scan_id, batches keep it, clearing removes it.
	 */
	public function test_scan_id_lifecycle() {
		$this->assertSame( '', $this->call_ability( self::STATUS )['scan_id'] );

		$first = $this->call_ability( self::START )['scan']['scan_id'];
		$this->assertMatchesRegularExpression( '/^[a-f0-9-]{36}$/', $first );
		$this->run_scheduled_ticks();
		$this->assertSame( $first, $this->call_ability( self::STATUS )['scan_id'], 'Batches keep it.' );

		$second = $this->call_ability( self::START )['scan']['scan_id'];
		$this->assertNotSame( $first, $second );

		UsedWhereScanner::instance()->clear_scan();
		$this->assertSame( '', $this->call_ability( self::STATUS )['scan_id'] );
	}

	/**
	 * percent is safe for every combination.
	 *
	 * @return array
	 */
	public function data_percent(): array {
		return [
			'total unknown'       => [ 'queued', 0, 0, 0 ],
			'half'                => [ 'running', 5, 10, 50 ],
			'rounds down'         => [ 'running', 2, 3, 66 ],
			'all processed'       => [ 'running', 10, 10, 99 ],
			'more than total'     => [ 'running', 15, 10, 99 ],
			'negative stored'     => [ 'running', -4, 10, 0 ],
			'complete'            => [ 'complete', 3, 10, 100 ],
			'complete, no total'  => [ 'complete', 0, 0, 100 ],
			'error part way'      => [ 'error', 4, 10, 40 ],
		];
	}

	/**
	 * Percent.
	 *
	 * @dataProvider data_percent
	 *
	 * @param string $state     State.
	 * @param int    $processed Processed.
	 * @param int    $total     Total.
	 * @param int    $percent   Expected percent.
	 */
	public function test_percent( string $state, int $processed, int $total, int $percent ) {
		self::set_status( compact( 'state', 'processed', 'total' ) );

		$this->assertSame( $percent, $this->call_ability( self::STATUS )['percent'] );
	}

	// ---------------------------------------------------------------------
	// Registration and MCP
	// ---------------------------------------------------------------------

	/**
	 * Registration meta.
	 */
	public function test_registration_meta() {
		$start = wp_get_ability( self::START );
		$this->assertFalse( $start->get_meta()['annotations']['readonly'] );
		$this->assertTrue( $start->get_meta()['annotations']['destructive'] );
		$this->assertFalse( $start->get_meta()['mcp']['public'] );
		$status = wp_get_ability( self::STATUS );
		$this->assertTrue( $status->get_meta()['annotations']['readonly'] );
		$this->assertFalse( $status->get_meta()['mcp']['public'] );
		foreach ( [ $start, $status ] as $ability ) {
			$this->assertFalse( $ability->get_input_schema()['additionalProperties'] );
			$this->assertSame( [], $ability->get_input_schema()['properties'] );
		}
	}

	/**
	 * Send one JSON-RPC message.
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
	 * Open a session.
	 *
	 * @param string $route Route.
	 */
	private function connect( string $route ) {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}
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

	/**
	 * Call a tool and return its structured content (asserting success).
	 *
	 * @param string $tool      Tool.
	 * @param array  $arguments Arguments.
	 *
	 * @return array
	 */
	private function tool( string $tool, array $arguments = [] ): array {
		$result = $this->send(
			self::SCOPED_ROUTE,
			'tools/call',
			[
				'name'      => $tool,
				'arguments' => (object) $arguments,
			]
		)['result'];
		$this->assertFalse( $result['isError'], $tool . ': ' . wp_json_encode( $result ) );
		return $result['structuredContent'];
	}

	/**
	 * Both tools are listed on the dedicated server with their hints and a
	 * closed input schema; their results pass the adapter's output check.
	 */
	public function test_tools_on_dedicated_server() {
		$this->connect( self::SCOPED_ROUTE );
		$tools = array_column( $this->send( self::SCOPED_ROUTE, 'tools/list' )['result']['tools'], null, 'name' );

		$this->assertArrayHasKey( 'tsmlt-start-usage-scan', $tools );
		$this->assertArrayHasKey( 'tsmlt-get-usage-scan-status', $tools );
		$this->assertTrue( $tools['tsmlt-get-usage-scan-status']['annotations']['readOnlyHint'] );
		$this->assertTrue( $tools['tsmlt-start-usage-scan']['annotations']['destructiveHint'] );
		$this->assertFalse( $tools['tsmlt-start-usage-scan']['inputSchema']['additionalProperties'] );

		$this->assertSame( 'idle', $this->tool( 'tsmlt-get-usage-scan-status' )['state'] );
		$this->assertSame( 'started', $this->tool( 'tsmlt-start-usage-scan' )['result'] );
		$this->assertSame( 'already_running', $this->tool( 'tsmlt-start-usage-scan' )['result'] );
		$this->assertSame( 'queued', $this->tool( 'tsmlt-get-usage-scan-status' )['state'] );

		$bad = $this->send(
			self::SCOPED_ROUTE,
			'tools/call',
			[
				'name'      => 'tsmlt-start-usage-scan',
				'arguments' => (object) [ 'restart' => true ],
			]
		)['result'];
		$this->assertTrue( $bad['isError'] );
	}

	/**
	 * Neither tool is discoverable or runnable through the default gateway,
	 * and nothing is started through it.
	 */
	public function test_not_on_default_gateway() {
		$this->connect( self::DEFAULT_ROUTE );
		$call = function ( string $tool, array $arguments ) {
			return $this->send(
				self::DEFAULT_ROUTE,
				'tools/call',
				[
					'name'      => $tool,
					'arguments' => (object) $arguments,
				]
			)['result'];
		};

		$names = wp_list_pluck( $call( 'mcp-adapter-discover-abilities', [] )['structuredContent']['abilities'], 'name' );
		$this->assertContains( 'tsmlt/search-media', $names );
		$this->assertNotContains( self::START, $names );
		$this->assertNotContains( self::STATUS, $names );

		foreach ( [ self::START, self::STATUS ] as $name ) {
			$run = $call(
				'mcp-adapter-execute-ability',
				[
					'ability_name' => $name,
					'parameters'   => (object) [],
				]
			);
			$this->assertTrue( $run['isError'], $name . ': ' . wp_json_encode( $run ) );
		}
		$this->assertSame( [], self::scheduled_ticks() );
		$this->assertFalse( get_option( UsageScanStatus::OPTION ) );
	}

	/**
	 * Classify audit results the way the documented workflow does.
	 *
	 * @param array $item Item from tsmlt-get-media-usage.
	 * @param array $scan Its scan block.
	 *
	 * @return string
	 */
	private static function classify( array $item, array $scan ): string {
		if ( true === $item['used'] ) {
			return 'used';
		}
		return false === $item['used'] && $scan['complete'] ? 'unused' : 'unknown';
	}

	/**
	 * Run the audit over MCP: page through image search results (small pages
	 * and batches to exercise paging), check usage in batches, classify.
	 *
	 * @param int $per_page Search page size.
	 * @param int $batch    Usage batch size.
	 *
	 * @return array<int, array{filename: string, class: string}>
	 */
	private function audit( int $per_page, int $batch ): array {
		$ids   = [];
		$names = [];
		$page  = 1;
		do {
			$found = $this->tool(
				'tsmlt-search-media',
				[
					'mime_type' => 'image',
					'orderby'   => 'id',
					'order'     => 'ASC',
					'per_page'  => $per_page,
					'page'      => $page,
				]
			);
			foreach ( $found['items'] as $item ) {
				$this->assertArrayNotHasKey( $item['id'], $names, 'No ID twice.' );
				$ids[]                = $item['id'];
				$names[ $item['id'] ] = wp_basename( (string) wp_parse_url( $item['url'], PHP_URL_PATH ) );
			}
			++$page;
		} while ( $page <= $found['total_pages'] );
		$this->assertCount( $found['total'], $ids );

		$report = [];
		foreach ( array_chunk( $ids, $batch ) as $chunk ) {
			$usage = $this->tool( 'tsmlt-get-media-usage', [ 'ids' => $chunk ] );
			foreach ( $usage['items'] as $item ) {
				$report[ $item['id'] ] = [
					'filename' => $names[ $item['id'] ],
					'class'    => self::classify( $item, $usage['scan'] ),
				];
			}
		}
		$this->assertSame( $ids, array_keys( $report ), 'Every image checked once, in order.' );
		return $report;
	}

	/**
	 * The complete workflow: start, poll until complete, enumerate images,
	 * check in batches: used, unused, and non-images left out.
	 */
	public function test_complete_audit_over_mcp() {
		$this->connect( self::SCOPED_ROUTE );
		$lib    = $this->library();
		$second = $this->attachment( 'lib/second-unused.png', 'image/png' );
		$pdf    = $this->attachment( 'lib/document.pdf', 'application/pdf' );

		$this->assertSame( 'started', $this->tool( 'tsmlt-start-usage-scan' )['result'] );
		$polls = 0;
		do {
			$this->run_scheduled_ticks( 1, false );
			++$polls;
			$status = $this->tool( 'tsmlt-get-usage-scan-status' );
		} while ( ! in_array( $status['state'], [ 'complete', 'cancelled', 'error' ], true ) && $polls < 50 );
		$this->assertSame( 'complete', $status['state'] );

		$report = $this->audit( 2, 2 );

		$this->assertSame(
			[
				$lib['featured'] => [ 'filename' => 'featured.jpg', 'class' => 'used' ],
				$lib['content']  => [ 'filename' => 'content.jpg', 'class' => 'used' ],
				$lib['unused']   => [ 'filename' => 'unused.jpg', 'class' => 'unused' ],
				$second          => [ 'filename' => 'second-unused.png', 'class' => 'unused' ],
			],
			$report
		);
		$this->assertArrayNotHasKey( $pdf, $report );
		$this->assertSame( 'complete', $this->tool( 'tsmlt-get-usage-scan-status' )['state'], 'Still complete after the audit.' );
	}

	/**
	 * When the scan did not complete, the same audit classifies nothing as
	 * unused.
	 */
	public function test_incomplete_audit_has_no_unused() {
		$this->connect( self::SCOPED_ROUTE );
		$this->library();
		$this->assertSame( 'started', $this->tool( 'tsmlt-start-usage-scan' )['result'] );
		$this->assertSame( 'queued', $this->tool( 'tsmlt-get-usage-scan-status' )['state'] );

		$report = $this->audit( 50, 50 );

		$this->assertCount( 3, $report );
		$this->assertNotContains( 'unused', array_column( $report, 'class' ) );
	}
}
