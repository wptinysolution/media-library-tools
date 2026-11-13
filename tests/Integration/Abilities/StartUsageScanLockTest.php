<?php
/**
 * The start lock of tsmlt/start-usage-scan: one owner at a time, abandoned
 * locks taken over by one request only, release only by the owner, and
 * database errors that never count as holding the lock.
 *
 * PHPUnit runs one request at a time, so competing requests cannot really
 * run in parallel here. Competition is reproduced at the critical database
 * statement instead: a `query` filter writes the competing request's lock
 * just before this request's INSERT or UPDATE reaches the database. Outgoing
 * HTTP is blocked.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\Media\StartUsageScan;
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * StartUsageScanLockTest
 */
class StartUsageScanLockTest extends WP_UnitTestCase {

	/**
	 * What the `query` filter does: '', 'compete_insert', 'compete_update',
	 * 'break_insert', 'break_select' or 'break_delete'.
	 *
	 * @var string
	 */
	private $mode = '';

	/**
	 * The competing request's lock value.
	 *
	 * @var string
	 */
	private $competitor = '';

	/**
	 * Whether the filter is running its own query.
	 *
	 * @var bool
	 */
	private $in_filter = false;

	/**
	 * Administrator; no scan, no lock, no scheduled batch, no HTTP.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/start-usage-scan' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( UsageScanStatus::OPTION );
		delete_option( StartUsageScan::START_LOCK_OPTION );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		delete_transient( UsedWhereScanner::SCAN_LOCK_KEY );
		$this->mode       = '';
		$this->competitor = time() . ':' . str_repeat( 'c', 32 );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		add_filter( 'query', [ $this, 'interfere' ] );
	}

	/**
	 * Remove filters and scheduled batches.
	 */
	public function tear_down() {
		remove_filter( 'query', [ $this, 'interfere' ] );
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
	 * Interfere with queries on the lock row, by $this->mode.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function interfere( $query ) {
		if ( $this->in_filter || '' === $this->mode || false === strpos( $query, StartUsageScan::START_LOCK_OPTION ) ) {
			return $query;
		}
		$verb = strtoupper( strtok( ltrim( $query ), ' ' ) );
		if ( ( 'compete_insert' === $this->mode && 'INSERT' === $verb ) || ( 'compete_update' === $this->mode && 'UPDATE' === $verb ) ) {
			// The competing request's statement reaches the database first.
			$this->in_filter = true;
			self::write_lock( $this->competitor );
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
	 * Write the lock row directly (another request, or an older plugin).
	 *
	 * @param string $value Lock value.
	 */
	private static function write_lock( string $value ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Test fixture.
		$wpdb->delete( $wpdb->options, [ 'option_name' => StartUsageScan::START_LOCK_OPTION ] );
		$wpdb->insert(
			$wpdb->options,
			[
				'option_name'  => StartUsageScan::START_LOCK_OPTION,
				'option_value' => $value,
				'autoload'     => 'off',
			]
		);
		// phpcs:enable
		wp_cache_delete( StartUsageScan::START_LOCK_OPTION, 'options' );
	}

	/**
	 * The lock row in the database, or null.
	 *
	 * @return string|null
	 */
	private static function stored_lock(): ?string {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", StartUsageScan::START_LOCK_OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
		return null === $value ? null : (string) $value;
	}

	/**
	 * Call a private lock method.
	 *
	 * @param string $method Method.
	 * @param array  $args   Arguments.
	 *
	 * @return mixed
	 */
	private static function lock( string $method, array $args = [] ) {
		$reflection = new ReflectionMethod( StartUsageScan::class, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}
		return $reflection->invokeArgs( StartUsageScan::instance(), $args );
	}

	/**
	 * Start a scan through the ability.
	 *
	 * @return array|WP_Error
	 */
	private static function start() {
		return wp_get_ability( 'tsmlt/start-usage-scan' )->execute();
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
	 * The first request gets the lock: a time and a random token, stored in
	 * the database; the second is refused while it is held.
	 */
	public function test_first_request_acquires_second_is_refused() {
		$first = self::lock( 'acquire_lock' );

		$this->assertMatchesRegularExpression( '/^\d+:[a-f0-9]{32}$/', $first );
		$this->assertSame( $first, self::stored_lock() );
		$this->assertNull( self::lock( 'acquire_lock' ) );
		$this->assertSame( $first, self::stored_lock(), 'The holder keeps the lock.' );
	}

	/**
	 * Competing for a free lock: the other request's row is inserted just
	 * before this request's INSERT, so this request is refused.
	 */
	public function test_competing_insert() {
		$this->mode = 'compete_insert';

		$this->assertNull( self::lock( 'acquire_lock' ) );
		$this->assertSame( '', $this->mode, 'Precondition: the competing lock was written first.' );
		$this->assertSame( $this->competitor, self::stored_lock() );
	}

	/**
	 * Competing for an abandoned lock: the other request takes it over just
	 * before this request's UPDATE, which then matches nothing.
	 */
	public function test_competing_takeover_of_an_abandoned_lock() {
		self::write_lock( ( time() - StartUsageScan::START_LOCK_SECONDS - 5 ) . ':' . str_repeat( 'a', 32 ) );
		$this->mode = 'compete_update';

		$this->assertNull( self::lock( 'acquire_lock' ) );
		$this->assertSame( '', $this->mode, 'Precondition: the competing takeover ran first.' );
		$this->assertSame( $this->competitor, self::stored_lock() );
	}

	/**
	 * Why add_option() was replaced: in the same race window it reports
	 * success to the second request, which would then also start a scan.
	 */
	public function test_add_option_is_not_insert_if_absent() {
		$this->mode = 'compete_insert';

		$this->assertTrue( add_option( StartUsageScan::START_LOCK_OPTION, (string) ( time() + 1 ), '', false ) );
	}

	/**
	 * A request releases only its own lock: once its lock expired and was
	 * taken over, releasing leaves the new owner's lock in place.
	 */
	public function test_release_only_own_lock() {
		$mine = self::lock( 'acquire_lock' );
		self::write_lock( $this->competitor );

		self::lock( 'release_lock', [ $mine ] );
		$this->assertSame( $this->competitor, self::stored_lock() );

		self::write_lock( $mine );
		self::lock( 'release_lock', [ $mine ] );
		$this->assertNull( self::stored_lock() );
		$this->assertFalse( get_option( StartUsageScan::START_LOCK_OPTION ), 'The option cache agrees.' );
	}

	/**
	 * Abandoned locks are taken over — also a bare timestamp written by an
	 * older version of the plugin — while a current one is respected.
	 */
	public function test_expired_lock_recovery() {
		foreach ( [ (string) ( time() - StartUsageScan::START_LOCK_SECONDS - 5 ), ( time() - 3600 ) . ':' . str_repeat( 'b', 32 ), '' ] as $stale ) {
			self::write_lock( $stale );
			$taken = self::lock( 'acquire_lock' );
			$this->assertMatchesRegularExpression( '/^\d+:[a-f0-9]{32}$/', (string) $taken, wp_json_encode( $stale ) );
			$this->assertSame( $taken, self::stored_lock() );
		}

		self::write_lock( (string) time() );
		$this->assertNull( self::lock( 'acquire_lock' ), 'A current bare timestamp is respected.' );
	}

	/**
	 * A stale lock does not block a start, and the start leaves no lock.
	 */
	public function test_start_clears_a_stale_lock() {
		self::write_lock( ( time() - StartUsageScan::START_LOCK_SECONDS - 5 ) . ':' . str_repeat( 'a', 32 ) );

		$this->assertSame( 'started', self::start()['result'] );
		$this->assertSame( 1, self::ticks() );
		$this->assertNull( self::stored_lock() );
	}

	/**
	 * A failed INSERT with no lock row is a database error: the start is
	 * refused with an error, nothing is scheduled and no lock is left.
	 */
	public function test_insert_failure_fails_closed() {
		$this->mode = 'break_insert';

		$this->assertFalse( self::lock( 'acquire_lock' ) );
		$result = self::start();

		$this->assertWPError( $result );
		$this->assertSame( StartUsageScan::ERROR_START_FAILED, $result->get_error_code() );
		$this->assertSame( 0, self::ticks() );
		$this->assertFalse( get_option( UsageScanStatus::OPTION ) );
		$this->assertNull( self::stored_lock() );
	}

	/**
	 * When the lock row cannot be read, the request does not assume it is
	 * free: the start is refused and the existing lock is untouched.
	 */
	public function test_lookup_failure_fails_closed() {
		self::write_lock( $this->competitor );
		$this->mode = 'break_select';

		$this->assertFalse( self::lock( 'acquire_lock' ) );
		$this->assertSame( StartUsageScan::ERROR_START_FAILED, self::start()->get_error_code() );
		$this->assertSame( 0, self::ticks() );
		$this->mode = '';
		$this->assertSame( $this->competitor, self::stored_lock() );
	}

	/**
	 * A lock that cannot be deleted stays until it expires: the start itself
	 * succeeds, other starts are refused meanwhile, and the lock is taken
	 * over once it is abandoned.
	 */
	public function test_delete_failure_keeps_lock_until_it_expires() {
		$this->mode = 'break_delete';

		$this->assertSame( 'started', self::start()['result'] );
		$this->mode = '';
		$left = self::stored_lock();
		$this->assertNotNull( $left, 'The undeletable lock is still there.' );
		$this->assertNull( self::lock( 'acquire_lock' ), 'Others are refused until it expires.' );

		self::write_lock( ( time() - StartUsageScan::START_LOCK_SECONDS - 5 ) . substr( (string) $left, (int) strpos( (string) $left, ':' ) ) );
		$this->assertNotNull( self::lock( 'acquire_lock' ), 'Taken over once abandoned.' );
	}

	/**
	 * Scan behaviour is unchanged: a start queues one scan and releases the
	 * lock; a repeated start reports the scan as already running, takes no
	 * lock and schedules nothing more; a released lock can be taken again.
	 */
	public function test_scan_behaviour_and_replay() {
		$first = self::start();

		$this->assertSame( 'started', $first['result'] );
		$this->assertSame( 'queued', $first['scan']['state'] );
		$this->assertNull( self::stored_lock() );

		$again = self::start();
		$this->assertSame( 'already_running', $again['result'] );
		$this->assertSame( $first['scan']['scan_id'], $again['scan']['scan_id'] );
		$this->assertSame( 1, self::ticks() );
		$this->assertNull( self::stored_lock() );

		$lock = self::lock( 'acquire_lock' );
		self::lock( 'release_lock', [ $lock ] );
		$this->assertNotNull( self::lock( 'acquire_lock' ), 'A released lock can be taken again.' );
	}
}
