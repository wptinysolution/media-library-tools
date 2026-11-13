<?php
/**
 * The Used Where page's Re-scan (AJAX `tsmlt_used_where_scan_start`) and the
 * tsmlt/start-usage-scan ability share one start lock (ScanStartLock): each
 * refuses while the other is starting a scan, only the owner releases the
 * lock, and database errors refuse the start before anything is wiped.
 *
 * PHPUnit runs one request at a time. Contention is reproduced by holding
 * the lock for one entry point, by calling the other entry point while the
 * first is inside its start (from the hook that fires when the scan status is
 * written), and by a `query` filter that writes a competing lock just before
 * this request's INSERT. Outgoing HTTP is blocked. The HTTP status of an
 * error (409 or 500) is not checked: under PHPUnit headers are already sent,
 * so wp_send_json_error() does not set it.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereScanStartLockAjaxTest
 */
class UsedWhereScanStartLockAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * What the `query` filter does: '', 'compete_insert', 'break_insert' or
	 * 'break_select'.
	 *
	 * @var string
	 */
	private $mode = '';

	/**
	 * Whether the filter is running its own query.
	 *
	 * @var bool
	 */
	private $in_filter = false;

	/**
	 * Observations made while the Re-scan was inside its start.
	 *
	 * @var array
	 */
	private $during_start = [];

	/**
	 * Administrator; no scan, no lock, no scheduled batch, no HTTP.
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
		delete_transient( UsedWhereScanner::SCAN_LOCK_KEY );
		$this->mode         = '';
		$this->during_start = [];
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		add_filter( 'query', [ $this, 'interfere' ] );
	}

	/**
	 * Remove filters, hooks and scheduled batches.
	 */
	public function tear_down() {
		remove_filter( 'query', [ $this, 'interfere' ] );
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		remove_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		remove_action( 'add_option_' . UsageScanStatus::OPTION, [ $this, 'call_mcp_during_start' ] );
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
	 * Interfere with queries on the lock row, by $this->mode.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function interfere( $query ) {
		if ( $this->in_filter || '' === $this->mode || false === strpos( $query, ScanStartLock::OPTION ) ) {
			return $query;
		}
		$verb = strtoupper( strtok( ltrim( $query ), ' ' ) );
		if ( 'compete_insert' === $this->mode && 'INSERT' === $verb ) {
			$this->in_filter = true;
			self::write_lock( time() . ':' . str_repeat( 'c', 32 ) );
			$this->in_filter = false;
			$this->mode      = '';
			return $query;
		}
		if ( 'break_' . strtolower( $verb ) === $this->mode ) {
			return 'SELECT * FROM tsmlt_no_such_table';
		}
		return $query;
	}

	/**
	 * While the Re-scan is inside its start (the scan status was just
	 * written), an MCP request tries to start a scan.
	 */
	public function call_mcp_during_start() {
		$this->during_start['lock_before'] = self::stored_lock();
		$this->during_start['mcp']         = wp_get_ability( 'tsmlt/start-usage-scan' )->execute();
		$this->during_start['lock_after']  = self::stored_lock();
	}

	/**
	 * Write the lock row directly (another request).
	 *
	 * @param string $value Lock value.
	 */
	private static function write_lock( string $value ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Test fixture.
		$wpdb->delete( $wpdb->options, [ 'option_name' => ScanStartLock::OPTION ] );
		$wpdb->insert(
			$wpdb->options,
			[
				'option_name'  => ScanStartLock::OPTION,
				'option_value' => $value,
				'autoload'     => 'off',
			]
		);
		// phpcs:enable
		wp_cache_delete( ScanStartLock::OPTION, 'options' );
	}

	/**
	 * The lock row in the database, or null.
	 *
	 * @return string|null
	 */
	private static function stored_lock(): ?string {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", ScanStartLock::OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
		return null === $value ? null : (string) $value;
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
	 * Press Re-scan as the current user.
	 *
	 * @param bool $valid_nonce Whether to send a valid nonce.
	 *
	 * @return array{response: mixed, raw: string}
	 */
	private function rescan( bool $valid_nonce = true ): array {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['nonce']            = $valid_nonce ? wp_create_nonce( Fns::NONCE_ID ) : 'invalid';
		$_POST['params']           = '{}';
		$this->_last_response      = '';
		try {
			$this->_handleAjax( 'tsmlt_used_where_scan_start' );
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
	 * The Re-scan takes the lock, starts the scan with the same response as
	 * before, and releases the lock.
	 */
	public function test_rescan_takes_and_releases_the_lock() {
		$result = $this->rescan()['response'];

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'queued', $result['data']['state'] );
		$this->assertSame( 'queued', UsageScanStatus::read()['state'] );
		$this->assertSame( 1, self::ticks() );
		$this->assertNull( self::stored_lock(), 'Released after the start.' );
	}

	/**
	 * While the MCP ability holds the lock, the Re-scan is refused and wipes
	 * nothing; it does not release the MCP request's lock.
	 */
	public function test_rescan_refused_while_mcp_holds_the_lock() {
		$mcp = ScanStartLock::instance()->acquire();
		$this->assertIsString( $mcp, 'Precondition: the MCP request holds the lock.' );

		$result = $this->rescan()['response'];

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Another scan is being started right now. Please wait a moment and try again.', $result['data']['message'] );
		$this->assertFalse( get_option( UsageScanStatus::OPTION ), 'No scan started.' );
		$this->assertSame( 0, self::ticks() );
		$this->assertSame( $mcp, self::stored_lock(), "The MCP request's lock is untouched." );
	}

	/**
	 * While the Re-scan is starting a scan, the MCP ability is refused
	 * ("already_running"), starts nothing and leaves the Re-scan's lock alone.
	 */
	public function test_mcp_refused_while_rescan_holds_the_lock() {
		add_action( 'add_option_' . UsageScanStatus::OPTION, [ $this, 'call_mcp_during_start' ] );

		$result = $this->rescan()['response'];

		$this->assertTrue( $result['success'] );
		$this->assertMatchesRegularExpression( '/^\d+:[a-f0-9]{32}$/', (string) $this->during_start['lock_before'], 'The Re-scan held the lock during its start.' );
		$this->assertSame( 'already_running', $this->during_start['mcp']['result'] );
		$this->assertSame( $this->during_start['lock_before'], $this->during_start['lock_after'], "The MCP request did not release the Re-scan's lock." );
		$this->assertSame( 1, self::ticks(), 'Only the Re-scan scheduled a batch.' );
		$this->assertNull( self::stored_lock() );
	}

	/**
	 * Two Re-scans at once: the other request's lock reaches the database
	 * just before this request's INSERT, so this request is refused.
	 */
	public function test_competing_rescans() {
		$this->mode = 'compete_insert';

		$result = $this->rescan()['response'];

		$this->assertSame( '', $this->mode, 'Precondition: the competing lock was written first.' );
		$this->assertFalse( $result['success'] );
		$this->assertFalse( get_option( UsageScanStatus::OPTION ) );
		$this->assertSame( time() . ':' . str_repeat( 'c', 32 ), self::stored_lock(), "The other request's lock is untouched." );
	}

	/**
	 * Database errors refuse the Re-scan before anything is wiped,
	 * and leave any existing lock as it was.
	 */
	public function test_database_errors_fail_closed() {
		$this->mode = 'break_insert';
		$insert     = $this->rescan()['response'];
		$this->assertFalse( $insert['success'] );
		$this->assertSame( 'The scan could not be started. Please try again.', $insert['data']['message'] );

		$held = time() . ':' . str_repeat( 'd', 32 );
		$this->mode = '';
		self::write_lock( $held );
		$this->mode = 'break_select';
		$select     = $this->rescan()['response'];
		$this->mode = '';

		$this->assertFalse( $select['success'] );
		$this->assertFalse( get_option( UsageScanStatus::OPTION ), 'No scan started.' );
		$this->assertSame( 0, self::ticks() );
		$this->assertSame( $held, self::stored_lock() );
	}

	/**
	 * The nonce and capability checks still come first: refused requests
	 * take no lock and start nothing.
	 */
	public function test_nonce_and_capability_checks() {
		$bad_nonce = $this->rescan( false );
		$this->assertSame( '-1', $bad_nonce['raw'] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$editor = $this->rescan()['response'];
		$this->assertFalse( $editor['success'] );
		$this->assertSame( 'Unauthorized.', $editor['data']['message'] );

		$this->assertNull( self::stored_lock() );
		$this->assertFalse( get_option( UsageScanStatus::OPTION ) );
		$this->assertSame( 0, self::ticks() );
	}

	/**
	 * The Re-scan still restarts a scan: a second press starts a new scan
	 * (new scan ID) and leaves no lock behind.
	 */
	public function test_rescan_restarts_a_scan() {
		$this->rescan();
		$first_id = UsageScanStatus::read()['scan_id'];
		$second   = $this->rescan()['response'];

		$this->assertTrue( $second['success'] );
		$this->assertSame( 'queued', $second['data']['state'] );
		$this->assertNotSame( '', $first_id );
		$this->assertNotSame( $first_id, UsageScanStatus::read()['scan_id'], 'A new scan was started.' );
		$this->assertNull( self::stored_lock() );
	}
}
