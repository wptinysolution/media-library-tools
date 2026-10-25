<?php
/**
 * Used-where scanner debug log: off by default, so scans write no file to
 * the uploads directory; the fixed-name log left by earlier releases is
 * deleted on upgrade and when a scan starts; when a developer turns the log
 * on, its file name is not guessable and each message stays on one line.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Controllers\Installation;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereDebugLogTest
 */
class UsedWhereDebugLogTest extends WP_UnitTestCase {

	/**
	 * Files created by a test.
	 *
	 * @var string[]
	 */
	private $created = [];

	/**
	 * Administrator; no outgoing HTTP (a scan start may spawn cron).
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
	}

	/**
	 * Remove created files, and any log a regression wrote.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		foreach ( self::log_files() as $name ) {
			wp_delete_file( self::log_dir() . '/' . $name );
		}
		foreach ( array_reverse( $this->created ) as $path ) {
			if ( is_file( $path ) ) {
				wp_delete_file( $path );
			} elseif ( is_dir( $path ) ) {
				rmdir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test cleanup.
			}
		}
		$this->created = [];
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
	 * The debug log directory.
	 *
	 * @return string
	 */
	private static function log_dir(): string {
		return trailingslashit( wp_upload_dir()['basedir'] ) . UsedWhereScanner::DEBUG_LOG_SUBDIR;
	}

	/**
	 * Log files in the debug directory.
	 *
	 * @return string[]
	 */
	private static function log_files(): array {
		$files = glob( self::log_dir() . '/*.log' );
		return is_array( $files ) ? array_map( 'basename', $files ) : [];
	}

	/**
	 * Create a file (and its directory) in the debug log directory.
	 *
	 * @param string $name    File name.
	 * @param string $content Content.
	 *
	 * @return string Path.
	 */
	private function put( string $name, string $content ): string {
		$dir = self::log_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			$this->created[] = $dir;
		}
		$path = $dir . '/' . $name;
		file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->created[] = $path;
		return $path;
	}

	/**
	 * Call a private static scanner method.
	 *
	 * @param string $method Method.
	 * @param array  $args   Arguments.
	 *
	 * @return mixed
	 */
	private static function call_private( string $method, array $args = [] ) {
		$ref = new ReflectionMethod( UsedWhereScanner::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}

	/**
	 * The switch is off.
	 */
	public function test_debug_log_is_off_by_default() {
		$this->assertFalse( UsedWhereScanner::DEBUG_LOG );
	}

	/**
	 * A scan batch, including the site-wide and options sweep that logs,
	 * writes no log file.
	 */
	public function test_scan_writes_no_debug_log() {
		$this->assertSame( [], self::log_files(), 'Precondition: no log yet.' );
		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->created[] = get_attached_file( $attachment );
		$post            = self::factory()->post->create( [ 'post_content' => wp_get_attachment_image( $attachment, 'full' ) ] );
		set_post_thumbnail( $post, $attachment );
		update_option( 'site_logo', $attachment );

		UsedWhereScanner::instance()->process_batch( 0, 20, true );

		$this->assertSame( [], self::log_files() );
	}

	/**
	 * Upgrading deletes the old fixed-name log and nothing else.
	 */
	public function test_upgrade_deletes_legacy_log_only() {
		$legacy = $this->put( UsedWhereScanner::LEGACY_DEBUG_LOG_FILE, "[2026-05-20 10:00:00] option-scan START\n" );
		$keep   = $this->put( 'keep.txt', 'not a log' );
		update_option( 'tsmlt_plugin_version', '1.0.0' );

		Installation::maybe_create_tables();

		$this->assertFileDoesNotExist( $legacy );
		$this->assertFileExists( $keep );
		$this->assertSame( TSMLT_VERSION, get_option( 'tsmlt_plugin_version' ) );
	}

	/**
	 * Starting a usage scan also deletes the old log (for sites that do not
	 * go through a version upgrade).
	 */
	public function test_scan_start_deletes_legacy_log() {
		$legacy = $this->put( UsedWhereScanner::LEGACY_DEBUG_LOG_FILE, 'old' );

		UsedWhereScanner::instance()->start_scheduled_scan();
		UsedWhereScanner::instance()->cancel_scheduled_scan();

		$this->assertFileDoesNotExist( $legacy );
	}

	/**
	 * Without a legacy log, the cleanup does nothing and does not fail.
	 */
	public function test_cleanup_without_legacy_log_is_a_no_op() {
		$keep = $this->put( 'keep.txt', 'not a log' );

		UsedWhereScanner::delete_legacy_debug_log();

		$this->assertFileExists( $keep );
	}

	/**
	 * When turned on, the log file name comes from the site's secret keys:
	 * not the old fixed name, not guessable, and stable.
	 */
	public function test_log_file_name_is_not_guessable() {
		$name = self::call_private( 'debug_log_file_name' );

		$this->assertMatchesRegularExpression( '/^used-where-[a-f0-9]{32}\.log$/', $name );
		$this->assertNotSame( UsedWhereScanner::LEGACY_DEBUG_LOG_FILE, $name );
		$this->assertSame( 'used-where-' . wp_hash( 'tsmlt_used_where_debug_log' ) . '.log', $name );
		$this->assertSame( $name, self::call_private( 'debug_log_file_name' ) );
	}

	/**
	 * A message cannot add lines of its own, and is capped.
	 */
	public function test_log_line_is_one_capped_line() {
		$line = self::call_private( 'debug_log_line', [ "ids=[1,2]\n[2026-01-01 00:00:00] forged\r\x00end" ] );

		$this->assertSame( 1, substr_count( $line, PHP_EOL ) );
		$this->assertStringEndsWith( PHP_EOL, $line );
		$this->assertStringContainsString( 'ids=[1,2] [2026-01-01 00:00:00] forged end', $line );

		$long = self::call_private( 'debug_log_line', [ str_repeat( 'a', 5000 ) ] );
		$this->assertSame( 2000, substr_count( $long, 'a' ) );
	}
}
