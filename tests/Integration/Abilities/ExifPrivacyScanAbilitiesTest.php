<?php
/**
 * EXIF privacy scan: the per-image results ExifScanner records, and
 * tsmlt/start-exif-privacy-scan, tsmlt/get-exif-privacy-scan-status and
 * tsmlt/list-exif-privacy-results (dedicated endpoint only).
 *
 * Scans run through the scanner's own WP-Cron tick handler on generated
 * fixtures (JPEGs with known EXIF built by TsmltExifJpegFixture, and
 * WordPress's test images); no real media is read.
 *
 * @package TinySolutions\mlt
 */

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';

use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Abilities\Media\ListExifPrivacyResults;
use TinySolutions\mlt\Abilities\Media\StartExifPrivacyScan;
use TinySolutions\mlt\Modules\ExifData\ExifScanner;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;

/**
 * ExifPrivacyScanAbilitiesTest
 */
class ExifPrivacyScanAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Start ability.
	 *
	 * @var string
	 */
	const START = 'tsmlt/start-exif-privacy-scan';

	/**
	 * Status ability.
	 *
	 * @var string
	 */
	const STATUS = 'tsmlt/get-exif-privacy-scan-status';

	/**
	 * List ability.
	 *
	 * @var string
	 */
	const LIST = 'tsmlt/list-exif-privacy-results';

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
	 * GPS latitude in the fixture (degrees, minutes, seconds).
	 *
	 * @var array
	 */
	const LATITUDE = [ [ 51, 1 ], [ 30, 1 ], [ 2617, 100 ] ];

	/**
	 * Files to remove.
	 *
	 * @var string[]
	 */
	private $temp_files = [];

	/**
	 * Files made unreadable.
	 *
	 * @var string[]
	 */
	private $locked_files = [];

	/**
	 * IDs for which read_post is denied.
	 *
	 * @var int[]
	 */
	private $denied = [];

	/**
	 * Statements other than reads sent while observing.
	 *
	 * @var string[]
	 */
	private $writes = [];

	/**
	 * Whether a new scan should start during the next privacy result read.
	 *
	 * @var bool
	 */
	private $change_scan = false;

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
	 * Administrator; no scan.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( self::LIST ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		if ( ! function_exists( 'exif_read_data' ) ) {
			$this->markTestSkipped( 'The PHP EXIF extension is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		ExifScanner::instance()->clear_scan();
		delete_option( ExifScanner::START_LOCK_OPTION );
		$this->denied      = [];
		$this->writes      = [];
		$this->change_scan = false;
		$this->sessions    = [];
		add_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10, 4 );
	}

	/**
	 * Remove fixtures and filters.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10 );
		remove_filter( 'query', [ $this, 'record_query' ] );
		remove_filter( 'get_post_metadata', [ $this, 'change_scan_during_read' ], 10 );
		foreach ( $this->locked_files as $file ) {
			chmod( $file, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		}
		foreach ( $this->temp_files as $file ) {
			if ( is_string( $file ) && file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
		ExifScanner::instance()->clear_scan();
		delete_option( ExifScanner::START_LOCK_OPTION );
		parent::tear_down();
	}

	/**
	 * Deny read_post for the IDs in $denied.
	 *
	 * @param string[] $caps    Primitive caps.
	 * @param string   $cap     Meta cap.
	 * @param int      $user_id User ID.
	 * @param array    $args    Arguments.
	 *
	 * @return string[]
	 */
	public function deny_read( $caps, $cap, $user_id, $args ) {
		if ( 'read_post' === $cap && in_array( (int) ( $args[0] ?? 0 ), $this->denied, true ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
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
	 * Start a new scan run the first time a privacy result is read.
	 *
	 * @param mixed  $value    Short-circuit value.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 *
	 * @return mixed
	 */
	public function change_scan_during_read( $value, $post_id, $meta_key ) {
		if ( $this->change_scan && ExifScanner::PRIVACY_META_KEY === $meta_key ) {
			$this->change_scan = false;
			ExifScanner::instance()->start();
		}
		return $value;
	}

	/**
	 * Upload bytes as an attachment.
	 *
	 * @param string $bytes File contents.
	 * @param string $name  File name.
	 *
	 * @return int
	 */
	private function upload( string $bytes, string $name ): int {
		$temp = trailingslashit( get_temp_dir() ) . wp_unique_filename( get_temp_dir(), $name );
		file_put_contents( $temp, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $temp;
		$id                 = self::factory()->attachment->create_upload_object( $temp );
		$this->temp_files[] = get_attached_file( $id );
		return (int) $id;
	}

	/**
	 * Upload a JPEG with the given EXIF.
	 *
	 * @param string $name File name.
	 * @param array  $ifd0 IFD0 entries.
	 * @param array  $exif EXIF entries.
	 * @param array  $gps  GPS entries.
	 *
	 * @return int
	 */
	private function jpeg( string $name, array $ifd0, array $exif = [], array $gps = [] ): int {
		return $this->upload( TsmltExifJpegFixture::build( DIR_TESTDATA . '/images/test-square-150.jpg', $ifd0, $exif, $gps ), $name );
	}

	/**
	 * Upload a WordPress test image.
	 *
	 * @param string $file File in the test data images folder.
	 *
	 * @return int
	 */
	private function test_image( string $file ): int {
		return $this->upload( (string) file_get_contents( DIR_TESTDATA . '/images/' . $file ), $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
	}

	/**
	 * Fixtures: GPS with camera; camera only; author, copyright and date;
	 * EXIF without sensitive data; no EXIF; PNG; WebP; missing; unreadable.
	 *
	 * @return array<string, int>
	 */
	private function library(): array {
		$ids    = [
			'gps'        => $this->jpeg(
				'gps.jpg',
				[ [ 0x010F, TsmltExifJpegFixture::ASCII, 'Apple' ] ],
				[],
				[
					[ 0x0001, TsmltExifJpegFixture::ASCII, 'N' ],
					[ 0x0002, TsmltExifJpegFixture::RATIONAL, self::LATITUDE ],
					[ 0x0003, TsmltExifJpegFixture::ASCII, 'W' ],
					[ 0x0004, TsmltExifJpegFixture::RATIONAL, [ [ 0, 1 ], [ 7, 1 ], [ 3965, 100 ] ] ],
				]
			),
			'camera'     => $this->jpeg( 'camera.jpg', [ [ 0x0110, TsmltExifJpegFixture::ASCII, 'Secret Model 9' ] ] ),
			'author'     => $this->jpeg(
				'author.jpg',
				[
					[ 0x013B, TsmltExifJpegFixture::ASCII, 'Jane Private' ],
					[ 0x8298, TsmltExifJpegFixture::ASCII, 'Private Copyright Holder' ],
				],
				[ [ 0x9003, TsmltExifJpegFixture::ASCII, '2019:05:06 07:08:09' ] ]
			),
			'harmless'   => $this->jpeg( 'harmless.jpg', [], [ [ 0x8827, TsmltExifJpegFixture::SHORT, 100 ] ] ),
			'plain'      => $this->test_image( 'test-square-150.jpg' ),
			'png'        => $this->test_image( 'test-image.png' ),
			'webp'       => $this->test_image( 'test-image.webp' ),
			'missing'    => (int) self::factory()->attachment->create(
				[
					'post_mime_type' => 'image/jpeg',
					'file'           => '2026/10/missing-privacy.jpg',
				]
			),
			'unreadable' => $this->jpeg( 'locked.jpg', [ [ 0x010F, TsmltExifJpegFixture::ASCII, 'Canon' ] ] ),
		];
		$locked = get_attached_file( $ids['unreadable'] );
		chmod( $locked, 0000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		$this->locked_files[] = $locked;
		if ( is_readable( $locked ) ) {
			$this->markTestSkipped( 'Files stay readable for this user (running as root?).' );
		}
		return $ids;
	}

	/**
	 * Run the scan to completion through its WP-Cron tick handler.
	 *
	 * @return void
	 */
	private static function scan(): void {
		ExifScanner::instance()->start();
		$ticks = 0;
		while ( $ticks < 50 && 'running' === ExifScanner::instance()->get_progress()['status'] ) {
			ExifScanner::run_tick();
			++$ticks;
		}
		wp_clear_scheduled_hook( ExifScanner::TICK_HOOK );
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
	 * Listed IDs of one listing.
	 *
	 * @param array $input Input.
	 *
	 * @return int[]
	 */
	private static function ids( array $input ): array {
		$result = self::call( self::LIST, $input + [ 'per_page' => 100 ] );
		return wp_list_pluck( $result['items'], 'id' );
	}

	/**
	 * Each image gets the result of its file; categories are presence
	 * only and the stored value holds no EXIF text.
	 */
	public function test_scan_records_privacy_results() {
		$ids = $this->library();
		self::scan();

		$expected = [
			'gps'        => [ 'sensitive', [ 'gps', 'camera' ] ],
			'camera'     => [ 'sensitive', [ 'camera' ] ],
			'author'     => [ 'sensitive', [ 'date_taken', 'author', 'copyright' ] ],
			'harmless'   => [ 'exif_no_sensitive', [] ],
			'plain'      => [ 'no_exif', [] ],
			'png'        => [ 'unsupported', [] ],
			'webp'       => [ 'unsupported', [] ],
			'missing'    => [ 'missing_file', [] ],
			'unreadable' => [ 'unreadable', [] ],
		];
		$run      = ExifScanner::instance()->get_progress()['run'];
		foreach ( $expected as $key => $result ) {
			$stored = get_post_meta( $ids[ $key ], ExifScanner::PRIVACY_META_KEY, true );
			$this->assertSame( ExifScanner::privacy_value( $run, $result[0], $result[1] ), $stored, $key );
			$this->assertMatchesRegularExpression( '/^[0-9a-f]{16}:[a-z_]+[|]([a-z_]+[|])*$/', $stored, $key );
		}
	}

	/**
	 * The EXIF Data page's counts are the same as before (with_exif: any
	 * array exif_read_data() returns for a JPEG, TIFF or WebP file that
	 * exists), and Clear removes the privacy results.
	 */
	public function test_admin_scan_behaviour_unchanged() {
		$ids = $this->library();
		self::scan();

		$with = 0;
		foreach ( $ids as $id ) {
			$file  = get_attached_file( $id );
			$exif  = in_array( get_post_mime_type( $id ), [ 'image/jpeg', 'image/jpg', 'image/tiff', 'image/webp' ], true ) && $file && file_exists( $file )
				? @exif_read_data( $file, null, true ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Files without EXIF warn.
				: false;
			$with += is_array( $exif ) && ! empty( $exif ) ? 1 : 0;
		}
		$status = ExifScanner::instance()->get_scan_status();
		$this->assertSame( count( $ids ), $status['processed'] );
		$this->assertSame( $with, $status['with_exif'] );
		$this->assertSame( count( $ids ) - $with, $status['without_exif'] );
		$this->assertSame( 'done', ExifScanner::instance()->get_progress()['status'] );
		$this->assertGreaterThan( 0, ExifScanner::instance()->get_progress()['started_at'], 'Kept through the batches.' );

		ExifScanner::instance()->clear_scan();
		$this->assertSame( '', get_post_meta( $ids['gps'], ExifScanner::PRIVACY_META_KEY, true ) );
	}

	/**
	 * Start starts a new run, reports a running one, resumes a stalled one,
	 * and does nothing while another start holds the lock.
	 */
	public function test_start_and_status() {
		$this->jpeg( 'one.jpg', [ [ 0x010F, TsmltExifJpegFixture::ASCII, 'Apple' ] ] );

		$idle = self::call( self::STATUS );
		$this->assertSame( 'idle', $idle['state'] );
		$this->assertFalse( $idle['has_privacy_results'] );

		$started = self::call( self::START );
		$this->assertSame( 'started', $started['result'] );
		$this->assertSame( 'running', $started['scan']['state'] );
		$this->assertTrue( $started['scan']['has_privacy_results'] );
		$this->assertNotFalse( wp_next_scheduled( ExifScanner::TICK_HOOK ) );
		$run = ExifScanner::instance()->get_progress()['run'];

		$again = self::call( self::START );
		$this->assertSame( 'already_running', $again['result'] );
		$this->assertSame( $run, ExifScanner::instance()->get_progress()['run'], 'Not restarted.' );

		$state               = get_option( ExifScanner::SCAN_STATUS_KEY );
		$state['updated_at'] = time() - ExifScanner::STALL_SECONDS - 10;
		update_option( ExifScanner::SCAN_STATUS_KEY, $state, false );
		wp_clear_scheduled_hook( ExifScanner::TICK_HOOK );
		$this->assertTrue( self::call( self::STATUS )['stalled'] );
		$resumed = self::call( self::START );
		$this->assertSame( 'resumed', $resumed['result'] );
		$this->assertSame( $run, ExifScanner::instance()->get_progress()['run'], 'Same run.' );
		$this->assertNotFalse( wp_next_scheduled( ExifScanner::TICK_HOOK ) );

		ExifScanner::run_tick();
		$done = self::call( self::STATUS );
		$this->assertSame( 'complete', $done['state'] );
		$this->assertSame( 100, $done['percent'] );

		$lock = ScanStartLock::instance()->acquire( ExifScanner::START_LOCK_OPTION );
		$this->assertIsString( $lock );
		$held = self::call( self::START );
		$this->assertSame( 'already_running', $held['result'], 'Another start holds the lock.' );
		$this->assertSame( $run, ExifScanner::instance()->get_progress()['run'] );
		$admin = ExifScanner::instance()->start();
		$this->assertSame( $run, $admin['run'], 'The admin Start waits for the same lock.' );
		ScanStartLock::instance()->release( $lock, ExifScanner::START_LOCK_OPTION );

		$this->assertSame( 'started', self::call( self::START )['result'], 'A completed scan is started again.' );
		$this->assertNotSame( $run, ExifScanner::instance()->get_progress()['run'] );

		$schema = wp_get_ability( self::START )->get_output_schema();
		$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $started ), true ), $schema, 'result' ) );
	}

	/**
	 * The status is read-only and neither tool takes input.
	 */
	public function test_status_is_read_only() {
		$this->jpeg( 'one.jpg', [] );
		self::call( self::START );
		$option = get_option( ExifScanner::SCAN_STATUS_KEY );
		$cron   = _get_cron_array();
		add_filter( 'query', [ $this, 'record_query' ] );
		self::call( self::STATUS );
		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertSame( [], $this->writes );
		$this->assertSame( $option, get_option( ExifScanner::SCAN_STATUS_KEY ) );
		$this->assertSame( $cron, _get_cron_array() );

		$this->assertWPError( self::call( self::STATUS, [ 'x' => 1 ] ) );
		$this->assertWPError( self::call( self::START, [ 'restart' => true ] ) );
	}

	/**
	 * Listing by result and category, with presence only: no coordinates,
	 * names, dates, camera models or paths.
	 */
	public function test_list_results() {
		$ids = $this->library();
		self::scan();

		$sensitive = self::call( self::LIST );
		$this->assertSame( [ $ids['gps'], $ids['camera'], $ids['author'] ], wp_list_pluck( $sensitive['items'], 'id' ) );
		$this->assertSame( 'sensitive', $sensitive['result'] );
		$this->assertSame( 3, $sensitive['total'] );
		$this->assertSame( [ 'gps', 'camera' ], $sensitive['items'][0]['categories'] );
		$this->assertSame( 'gps.jpg', $sensitive['items'][0]['filename'] );
		$this->assertSame( 'image/jpeg', $sensitive['items'][0]['mime_type'] );

		$this->assertSame( [ $ids['gps'] ], self::ids( [ 'category' => 'gps' ] ) );
		$gps = self::call( self::LIST, [ 'category' => 'gps' ] );
		$this->assertSame( 1, $gps['total'], 'Filtered in SQL.' );
		$this->assertSame( 0, $gps['excluded'] );
		$this->assertSame( [ $ids['author'] ], self::ids( [ 'category' => 'author' ] ) );
		$this->assertSame( [ $ids['gps'], $ids['camera'] ], self::ids( [ 'category' => 'camera' ] ) );
		$this->assertSame( [ $ids['harmless'] ], self::ids( [ 'result' => 'exif_no_sensitive' ] ) );
		$this->assertSame( [ $ids['plain'] ], self::ids( [ 'result' => 'no_exif' ] ) );
		$this->assertSame( [ $ids['png'], $ids['webp'] ], self::ids( [ 'result' => 'unsupported' ] ) );
		$this->assertSame( [ $ids['missing'] ], self::ids( [ 'result' => 'missing_file' ] ) );
		$this->assertSame( [ $ids['unreadable'] ], self::ids( [ 'result' => 'unreadable' ] ) );
		$this->assertSame( [], self::ids( [ 'result' => 'no_extension' ] ) );
		$this->assertSame( [], self::ids( [ 'result' => 'not_scanned' ] ) );

		$all = [];
		foreach ( ListExifPrivacyResults::results() as $result ) {
			$all[] = self::call(
				self::LIST,
				[
					'result'   => $result,
					'per_page' => 100,
				]
			);
		}
		$json = wp_json_encode( $all );
		foreach ( [ '51.50', '26.17', '0.127', '39.65', 'Apple', 'Secret Model', 'Jane Private', 'Private Copyright', '2019', 'latitude', 'longitude' ] as $value ) {
			$this->assertStringNotContainsString( $value, $json, $value );
		}
		$this->assertStringNotContainsString( wp_upload_dir()['basedir'], $json );
		$this->assertDoesNotMatchRegularExpression( '#[0-9]{4}\\\\/[0-9]{2}\\\\/#', $json, 'No upload folders.' );

		$schema = wp_get_ability( self::LIST )->get_output_schema();
		foreach ( $all as $result ) {
			$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), $schema, 'result' ) );
		}
	}

	/**
	 * Media the run did not reach (uploaded after it, or with a result of
	 * an earlier run) is not_scanned, never listed as without EXIF.
	 */
	public function test_unscanned_media_is_not_safe() {
		$ids = $this->library();
		self::scan();
		$late  = $this->test_image( 'test-square-150.jpg' );
		$stale = $this->test_image( 'test-square-150.jpg' );
		update_post_meta( $stale, ExifScanner::PRIVACY_META_KEY, ExifScanner::privacy_value( str_repeat( 'a', 16 ), 'no_exif' ) );

		$this->assertSame( [ $late, $stale ], self::ids( [ 'result' => 'not_scanned' ] ) );
		$this->assertSame( [ $ids['plain'] ], self::ids( [ 'result' => 'no_exif' ] ) );
	}

	/**
	 * Pages follow attachment IDs: no overlap, every item once, an empty
	 * page past the end.
	 */
	public function test_pagination() {
		$created = [];
		for ( $i = 0; $i < 5; $i++ ) {
			$created[] = $this->jpeg( 'page-' . $i . '.jpg', [ [ 0x013B, TsmltExifJpegFixture::ASCII, 'A' ] ] );
		}
		self::scan();
		$seen = [];
		for ( $page = 1; $page <= 3; $page++ ) {
			$result = self::call(
				self::LIST,
				[
					'page'     => $page,
					'per_page' => 2,
				]
			);
			$this->assertSame( 5, $result['total'] );
			$this->assertSame( 3, $result['total_pages'] );
			$seen = array_merge( $seen, wp_list_pluck( $result['items'], 'id' ) );
		}
		$this->assertSame( $created, $seen );
		$this->assertSame(
			[],
			self::call(
				self::LIST,
				[
					'page'     => 4,
					'per_page' => 2,
				]
			)['items']
		);
		$empty = self::call( self::LIST, [ 'result' => 'missing_file' ] );
		$this->assertSame( [], $empty['items'] );
		$this->assertSame( 0, $empty['total_pages'] );
	}

	/**
	 * Invalid input is refused.
	 */
	public function test_invalid_input() {
		self::scan();
		foreach (
			[
				'unknown result'      => [ 'result' => 'safe' ],
				'unknown category'    => [ 'category' => 'serial' ],
				'category on no_exif' => [
					'result'   => 'no_exif',
					'category' => 'gps',
				],
				'page 0'              => [ 'page' => 0 ],
				'per_page 101'        => [ 'per_page' => 101 ],
				'per_page string'     => [ 'per_page' => '10' ],
				'extra key'           => [ 'path' => '/etc' ],
				'regex in result'     => [ 'result' => 'sensitive.*' ],
			] as $label => $input
		) {
			$this->assertWPError( self::call( self::LIST, $input ), $label );
			$this->assertWPError( ListExifPrivacyResults::instance()->execute( $input ), $label );
		}
	}

	/**
	 * Without a completed run with privacy results (none, running,
	 * cancelled, or a scan from before privacy results), listing is
	 * refused with how to get one.
	 */
	public function test_requires_a_completed_scan() {
		$this->jpeg( 'any.jpg', [] );
		$cases = [
			'no scan'   => null,
			'running'   => [ 'status' => 'running' ],
			'cancelled' => [ 'status' => 'cancelled' ],
			'legacy'    => [
				'status'    => 'done',
				'processed' => 1,
				'total'     => 1,
			],
		];
		foreach ( $cases as $label => $state ) {
			ExifScanner::instance()->clear_scan();
			if ( 'running' === $label || 'cancelled' === $label ) {
				ExifScanner::instance()->start();
				$stored           = get_option( ExifScanner::SCAN_STATUS_KEY );
				$stored['status'] = $state['status'];
				update_option( ExifScanner::SCAN_STATUS_KEY, $stored, false );
			} elseif ( null !== $state ) {
				update_option( ExifScanner::SCAN_STATUS_KEY, $state, false );
			}
			$result = self::call( self::LIST );
			$this->assertWPError( $result, $label );
			$this->assertSame( ListExifPrivacyResults::ERROR_SCAN_REQUIRED, $result->get_error_code(), $label );
			$this->assertStringContainsString( 'tsmlt/start-exif-privacy-scan', $result->get_error_message() );
		}
	}

	/**
	 * A scan started while the page is read is refused.
	 */
	public function test_scan_changing_during_the_read_is_refused() {
		$this->jpeg( 'one.jpg', [ [ 0x013B, TsmltExifJpegFixture::ASCII, 'A' ] ] );
		self::scan();
		$this->change_scan = true;
		add_filter( 'get_post_metadata', [ $this, 'change_scan_during_read' ], 10, 3 );
		$result = self::call( self::LIST );
		$this->assertWPError( $result );
		$this->assertSame( ListExifPrivacyResults::ERROR_SCAN_CHANGED, $result->get_error_code() );
	}

	/**
	 * Listing writes nothing and reads no files; media the user cannot
	 * read is counted as hidden.
	 */
	public function test_list_is_read_only_and_hides_unreadable() {
		$ids = $this->library();
		self::scan();
		$option       = get_option( ExifScanner::SCAN_STATUS_KEY );
		$meta         = get_post_meta( $ids['gps'], ExifScanner::PRIVACY_META_KEY, true );
		$cron         = _get_cron_array();
		$this->denied = [ $ids['camera'] ];
		add_filter( 'query', [ $this, 'record_query' ] );
		$result = self::call( self::LIST );
		remove_filter( 'query', [ $this, 'record_query' ] );

		$this->assertSame( [], $this->writes );
		$this->assertSame( $option, get_option( ExifScanner::SCAN_STATUS_KEY ) );
		$this->assertSame( $meta, get_post_meta( $ids['gps'], ExifScanner::PRIVACY_META_KEY, true ) );
		$this->assertSame( $cron, _get_cron_array() );
		$this->assertSame( [ $ids['gps'], $ids['author'] ], wp_list_pluck( $result['items'], 'id' ) );
		$this->assertSame( 1, $result['hidden'] );
	}

	/**
	 * Only administrators may use the three tools.
	 */
	public function test_requires_manage_options() {
		self::scan();
		foreach ( [ 'editor', 'author', 'subscriber' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			foreach ( [ self::START, self::STATUS, self::LIST ] as $name ) {
				$this->assertFalse( wp_get_ability( $name )->check_permissions( [] ), $role . ' ' . $name );
			}
			$this->assertWPError( StartExifPrivacyScan::instance()->execute( [] ), $role );
			$this->assertWPError( ListExifPrivacyResults::instance()->execute( [] ), $role );
		}
		wp_set_current_user( 0 );
		$state = get_option( ExifScanner::SCAN_STATUS_KEY );
		$this->assertWPError( StartExifPrivacyScan::instance()->execute( [] ) );
		$this->assertWPError( ListExifPrivacyResults::instance()->execute( [] ) );
		$this->assertSame( $state, get_option( ExifScanner::SCAN_STATUS_KEY ), 'Nothing started.' );
	}

	/**
	 * Listed on the dedicated endpoint only; works over MCP there and is
	 * refused on the default gateway.
	 */
	public function test_dedicated_endpoint_only() {
		foreach ( [ self::START, self::STATUS, self::LIST ] as $name ) {
			$this->assertFalse( wp_get_ability( $name )->get_meta()['mcp']['public'], $name );
			$this->assertContains( $name, McpServerRegistration::instance()->get_tool_names() );
		}
		$this->assertTrue( wp_get_ability( self::LIST )->get_meta()['annotations']['readonly'] );
		$this->assertTrue( wp_get_ability( self::STATUS )->get_meta()['annotations']['readonly'] );
		$this->assertFalse( wp_get_ability( self::START )->get_meta()['annotations']['destructive'] );

		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run the transport part.' );
		}
		$id = $this->jpeg(
			'gps.jpg',
			[],
			[],
			[
				[ 0x0001, TsmltExifJpegFixture::ASCII, 'N' ],
				[ 0x0002, TsmltExifJpegFixture::RATIONAL, self::LATITUDE ],
			]
		);
		self::scan();
		$this->connect( self::SCOPED_ROUTE );
		$tools = array_column( $this->send( self::SCOPED_ROUTE, 'tools/list' )['result']['tools'], null, 'name' );
		foreach ( [ 'tsmlt-start-exif-privacy-scan', 'tsmlt-get-exif-privacy-scan-status', 'tsmlt-list-exif-privacy-results' ] as $tool ) {
			$this->assertArrayHasKey( $tool, $tools );
			$this->assertFalse( $tools[ $tool ]['inputSchema']['additionalProperties'], $tool );
		}
		$call = $this->send(
			self::SCOPED_ROUTE,
			'tools/call',
			[
				'name'      => 'tsmlt-list-exif-privacy-results',
				'arguments' => [ 'category' => 'gps' ],
			]
		)['result'];
		$this->assertFalse( $call['isError'], wp_json_encode( $call ) );
		$this->assertSame( [ $id ], array_column( $call['structuredContent']['items'], 'id' ) );
		$this->assertStringNotContainsString( '26.17', wp_json_encode( $call ) );

		$this->connect( self::DEFAULT_ROUTE );
		foreach ( [ self::START, self::STATUS, self::LIST ] as $name ) {
			$info = $this->send(
				self::DEFAULT_ROUTE,
				'tools/call',
				[
					'name'      => 'mcp-adapter-get-ability-info',
					'arguments' => [ 'ability_name' => $name ],
				]
			)['result'];
			$this->assertTrue( $info['isError'], $name . ' is not offered on the default gateway.' );
		}
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
