<?php
/**
 * Integration tests for tsmlt/preview-trash-unused-media and
 * tsmlt/trash-unused-media: only attachments the current, completed usage
 * scan found unused are moved to the trash, with wp_trash_post() only.
 *
 * Most tests store a completed scan status directly and write usage records
 * in the scanner's format; one runs a real scan. WP-Cron does not run in
 * tests. Outgoing HTTP is blocked. The MCP tests run when the MCP Adapter is
 * loaded (MCP_ADAPTER_DIR is set). Every file, post and attachment is a
 * generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\PreviewTrashUnusedMedia;
use TinySolutions\mlt\Abilities\Media\TrashUnusedMedia;
use TinySolutions\mlt\Abilities\TrashEligibility;
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * TrashUnusedMediaAbilityTest
 */
class TrashUnusedMediaAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability names.
	 */
	const TRASH   = 'tsmlt/trash-unused-media';
	const PREVIEW = 'tsmlt/preview-trash-unused-media';

	/**
	 * Routes.
	 */
	const SCOPED_ROUTE  = '/media-library-tools/mcp';
	const DEFAULT_ROUTE = '/mcp/mcp-adapter-default-server';

	/**
	 * Scan ID used for stored complete scans.
	 */
	const SCAN_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

	/**
	 * Post IDs and capabilities denied by the test filter: [ cap => ids ].
	 *
	 * @var array<string, int[]>
	 */
	private $denied = [];

	/**
	 * Files created by a test.
	 *
	 * @var string[]
	 */
	private $files = [];

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
	 * Administrator; no scan; no scheduled work; capability filter.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( UsageScanStatus::OPTION );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		wp_unschedule_hook( TrashEligibility::POST_RESCAN_HOOK );
		$this->denied   = [];
		$this->sessions = [];
		add_filter( 'map_meta_cap', [ $this, 'deny' ], 10, 4 );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
	}

	/**
	 * Remove filters, scheduled work and files.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny' ], 10 );
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		remove_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		wp_unschedule_hook( TrashEligibility::POST_RESCAN_HOOK );
		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
		parent::tear_down();
	}

	/**
	 * Deny capabilities for chosen posts.
	 *
	 * @param string[] $caps    Required capabilities.
	 * @param string   $cap     Requested capability.
	 * @param int      $user_id User ID.
	 * @param array    $args    Arguments.
	 *
	 * @return string[]
	 */
	public function deny( $caps, $cap, $user_id, $args ) {
		if ( isset( $this->denied[ $cap ], $args[0] ) && in_array( (int) $args[0], $this->denied[ $cap ], true ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
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
	 * @param string $name  Ability.
	 * @param mixed  $input Input.
	 *
	 * @return mixed
	 */
	private function call_ability( string $name, $input ) {
		$ability = wp_get_ability( $name );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		return $ability->execute( $input );
	}

	/**
	 * Call an execute callback directly.
	 *
	 * @param string $name  Ability.
	 * @param mixed  $input Input.
	 *
	 * @return mixed
	 */
	private static function direct( string $name, $input ) {
		return ( self::TRASH === $name ? TrashUnusedMedia::instance() : PreviewTrashUnusedMedia::instance() )->execute( $input );
	}

	/**
	 * An image attachment uploaded before the scan (backdated).
	 *
	 * @param string $title Title.
	 * @param array  $args  Extra fields.
	 *
	 * @return int
	 */
	private function attachment( string $title = 'Trash fixture', array $args = [] ): int {
		return (int) self::factory()->attachment->create(
			array_merge(
				[
					'post_title'     => $title,
					'post_mime_type' => 'image/jpeg',
					'post_date'      => '2020-01-01 00:00:00',
					'post_date_gmt'  => '2020-01-01 00:00:00',
				],
				$args
			)
		);
	}

	/**
	 * An attachment with a real file, uploaded before the scan.
	 *
	 * @return int
	 */
	private function attachment_with_file(): int {
		$id = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->files[] = get_attached_file( $id );
		wp_update_post(
			[
				'ID'            => $id,
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
			]
		);
		return $id;
	}

	/**
	 * Store a completed scan (started now, after the backdated uploads), with
	 * the per-post re-checks scheduled by fixture posts already run.
	 *
	 * @param array $overrides Fields to change.
	 */
	private static function complete_scan( array $overrides = [] ) {
		wp_unschedule_hook( TrashEligibility::POST_RESCAN_HOOK );
		update_option(
			UsageScanStatus::OPTION,
			array_merge(
				[
					'state'        => 'complete',
					'complete'     => true,
					'processed'    => 10,
					'total'        => 10,
					'scan_id'      => self::SCAN_ID,
					'started_at'   => current_time( 'mysql' ),
					'last_tick_at' => current_time( 'mysql' ),
					'timestamp'    => current_time( 'mysql' ),
				],
				$overrides
			),
			false
		);
	}

	/**
	 * Record a usage for an attachment.
	 *
	 * @param int $id Attachment ID.
	 */
	private static function mark_used( int $id ) {
		update_post_meta( $id, UsedWhereScanner::META_KEY, [ [ 'post_id' => 0, 'post_type' => 'site_settings', 'usage_type' => 'site_logo' ] ] );
	}

	/**
	 * Results keyed by ID: [ result-or-eligible, reason ].
	 *
	 * @param array  $output Output.
	 * @param string $field  'result' or 'eligible'.
	 *
	 * @return array
	 */
	private static function by_id( array $output, string $field ): array {
		$rows = [];
		foreach ( $output['results'] as $row ) {
			$rows[ $row['id'] ] = [ $row[ $field ], $row['reason'] ];
		}
		return $rows;
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
	 * Post status of an attachment, read from the database.
	 *
	 * @param int $id ID.
	 *
	 * @return string|null
	 */
	private static function status_of( int $id ): ?string {
		clean_post_cache( $id );
		$post = get_post( $id );
		return $post ? $post->post_status : null;
	}

	/**
	 * Mixed set of IDs: two unused, one used, one uploaded after the scan,
	 * one already trashed, one missing, one regular post, one private
	 * attachment.
	 *
	 * @return array<string, int>
	 */
	private function mixed_ids(): array {
		$ids            = [];
		$ids['unused1'] = $this->attachment( 'Unused one' );
		$ids['unused2'] = $this->attachment( 'Unused two' );
		$ids['used']    = $this->attachment( 'Used' );
		self::mark_used( $ids['used'] );
		$ids['trashed'] = $this->attachment( 'Trashed' );
		wp_trash_post( $ids['trashed'] );
		$ids['private'] = $this->attachment( 'Private', [ 'post_status' => 'private' ] );
		$ids['post']    = (int) self::factory()->post->create();
		$ids['missing'] = 987654321;
		self::complete_scan();
		$ids['late'] = (int) self::factory()->attachment->create(
			[
				'post_title'     => 'Uploaded after the scan',
				'post_mime_type' => 'image/jpeg',
				'post_date'      => '2099-01-01 00:00:00',
				'post_date_gmt'  => '2099-01-01 00:00:00',
			]
		);
		return $ids;
	}

	// ---------------------------------------------------------------------
	// Trash
	// ---------------------------------------------------------------------

	/**
	 * Unused attachments are trashed; every other ID is skipped with its
	 * reason; results follow the request order.
	 */
	public function test_trashes_only_confirmed_unused() {
		$ids = $this->mixed_ids();

		$result = $this->call_ability( self::TRASH, [ 'ids' => array_values( $ids ) ] );

		$this->assertSame( [ 'scan', 'trashed', 'results' ], array_keys( $result ) );
		$this->assertSame( self::SCAN_ID, $result['scan']['scan_id'] );
		$this->assertSame( [ $ids['unused1'], $ids['unused2'] ], $result['trashed'] );
		$this->assertSame( array_values( $ids ), wp_list_pluck( $result['results'], 'id' ) );
		$this->assertSame(
			[
				$ids['unused1'] => [ 'trashed', null ],
				$ids['unused2'] => [ 'trashed', null ],
				$ids['used']    => [ 'skipped', 'used' ],
				$ids['trashed'] => [ 'skipped', 'already_trashed' ],
				$ids['private'] => [ 'skipped', 'not_eligible' ],
				$ids['post']    => [ 'skipped', 'not_found' ],
				$ids['missing'] => [ 'skipped', 'not_found' ],
				$ids['late']    => [ 'skipped', 'not_covered' ],
			],
			self::by_id( $result, 'result' )
		);
		foreach ( $result['results'] as $row ) {
			$this->assertNotSame( '', $row['message'] );
		}
		$this->assertSame( 'trash', self::status_of( $ids['unused1'] ) );
		$this->assertSame( 'inherit', self::status_of( $ids['used'] ) );
		$this->assertSame( 'inherit', self::status_of( $ids['late'] ) );
		$this->assertSame( 'private', self::status_of( $ids['private'] ) );
		$this->assertSame( 'publish', self::status_of( $ids['post'] ) );
		$this->assertSame( 'inherit', get_post_meta( $ids['unused1'], '_wp_trash_meta_status', true ), 'Restorable.' );
	}

	/**
	 * The file and the attachment record stay: nothing is deleted, and the
	 * attachment can be restored.
	 */
	public function test_nothing_is_deleted_permanently() {
		$id   = $this->attachment_with_file();
		$file = get_attached_file( $id );
		$meta = wp_get_attachment_metadata( $id );
		self::complete_scan();
		$deleted = [];
		$watch   = static function ( $post_id ) use ( &$deleted ) {
			$deleted[] = $post_id;
		};
		foreach ( [ 'delete_post', 'deleted_post', 'delete_attachment', 'wp_delete_file' ] as $hook ) {
			add_action( $hook, $watch );
		}

		$result = $this->call_ability( self::TRASH, [ 'ids' => [ $id ] ] );

		foreach ( [ 'delete_post', 'deleted_post', 'delete_attachment', 'wp_delete_file' ] as $hook ) {
			remove_action( $hook, $watch );
		}
		$this->assertSame( [ $id ], $result['trashed'] );
		$this->assertSame( [], $deleted );
		$this->assertFileExists( $file );
		$this->assertSame( 'trash', self::status_of( $id ) );
		$this->assertSame( $meta, wp_get_attachment_metadata( $id ) );
		$this->assertNotFalse( wp_untrash_post( $id ) );
		$this->assertSame( 'inherit', self::status_of( $id ) );
	}

	/**
	 * Calling again with the same IDs changes nothing more.
	 */
	public function test_repeat_is_harmless() {
		$id = $this->attachment();
		self::complete_scan();
		$this->call_ability( self::TRASH, [ 'ids' => [ $id ] ] );

		$again = $this->call_ability( self::TRASH, [ 'ids' => [ $id ] ] );

		$this->assertSame( [], $again['trashed'] );
		$this->assertSame( [ $id => [ 'skipped', 'already_trashed' ] ], self::by_id( $again, 'result' ) );
	}

	/**
	 * The tool never starts a scan, schedules work, or changes the scan
	 * status; attachments it did not trash are unchanged.
	 */
	public function test_no_scan_or_background_work() {
		$unused = $this->attachment();
		$used   = $this->attachment();
		self::mark_used( $used );
		self::complete_scan();
		$status = get_option( UsageScanStatus::OPTION );
		$cron   = _get_cron_array();
		$before = [ get_post( $used, ARRAY_A ), get_post_meta( $used ) ];

		$this->call_ability( self::TRASH, [ 'ids' => [ $unused, $used ] ] );

		$this->assertSame( $cron, _get_cron_array() );
		$this->assertSame( $status, get_option( UsageScanStatus::OPTION ) );
		clean_post_cache( $used );
		$this->assertSame( $before, [ get_post( $used, ARRAY_A ), get_post_meta( $used ) ] );
	}

	/**
	 * Attachment-level permissions: no read access is reported as not
	 * found, no delete access as not allowed.
	 */
	public function test_attachment_permissions() {
		$unreadable  = $this->attachment();
		$undeletable = $this->attachment();
		self::complete_scan();
		$this->denied = [
			'read_post'   => [ $unreadable ],
			'delete_post' => [ $undeletable ],
		];

		$result = $this->call_ability( self::TRASH, [ 'ids' => [ $unreadable, $undeletable ] ] );

		$this->assertSame( [], $result['trashed'] );
		$this->assertSame(
			[
				$unreadable  => [ 'skipped', 'not_found' ],
				$undeletable => [ 'skipped', 'not_allowed' ],
			],
			self::by_id( $result, 'result' )
		);
		$this->assertSame( 'inherit', self::status_of( $unreadable ) );
		$this->assertSame( 'inherit', self::status_of( $undeletable ) );
	}

	/**
	 * A usage recorded for an attachment in posts the user cannot read, or
	 * a record whose post is gone, still counts as used.
	 */
	public function test_any_record_counts_as_used() {
		$hidden = $this->attachment();
		$stale  = $this->attachment();
		$secret = (int) self::factory()->post->create( [ 'post_status' => 'private' ] );
		$this->denied = [ 'read_post' => [ $secret ] ];
		update_post_meta( $hidden, UsedWhereScanner::META_KEY, [ [ 'post_id' => $secret, 'post_type' => 'post', 'usage_type' => 'content' ] ] );
		update_post_meta( $stale, UsedWhereScanner::META_KEY, [ [ 'post_id' => 987654321, 'post_type' => 'post', 'usage_type' => 'featured' ] ] );
		self::complete_scan();

		$result = $this->call_ability( self::TRASH, [ 'ids' => [ $hidden, $stale ] ] );

		$this->assertSame( [], $result['trashed'] );
		$this->assertSame( [ 'used', 'used' ], wp_list_pluck( $result['results'], 'reason' ) );
	}

	// ---------------------------------------------------------------------
	// Scan gates
	// ---------------------------------------------------------------------

	/**
	 * Scans that are not a current, completed one.
	 *
	 * @return array
	 */
	public function data_scan_gates(): array {
		$old = '2000-01-01 00:00:00';
		return [
			'never scanned'      => [ null, TrashEligibility::ERROR_SCAN_INCOMPLETE ],
			'queued'             => [ [ 'state' => 'queued', 'complete' => false, 'last_tick_at' => '' ], TrashEligibility::ERROR_SCAN_INCOMPLETE ],
			'running'            => [ [ 'state' => 'running', 'complete' => false ], TrashEligibility::ERROR_SCAN_INCOMPLETE ],
			'stalled'            => [ [ 'state' => 'running', 'complete' => false, 'last_tick_at' => $old ], TrashEligibility::ERROR_SCAN_INCOMPLETE ],
			'cancelled'          => [ [ 'state' => 'cancelled', 'complete' => false ], TrashEligibility::ERROR_SCAN_INCOMPLETE ],
			'error'              => [ [ 'state' => 'error', 'complete' => false, 'last_error' => 'Boom' ], TrashEligibility::ERROR_SCAN_INCOMPLETE ],
			'cleared'            => [ [ 'state' => 'idle' ], TrashEligibility::ERROR_SCAN_INCOMPLETE ],
			'old complete row'   => [ [ 'state' => null, 'complete' => true, 'scan_id' => null ], TrashEligibility::ERROR_SCAN_OUTDATED ],
			'complete, no id'    => [ [ 'scan_id' => '' ], TrashEligibility::ERROR_SCAN_OUTDATED ],
		];
	}

	/**
	 * Every gate refuses the whole request; nothing is trashed, by the tool
	 * or the preview.
	 *
	 * @dataProvider data_scan_gates
	 *
	 * @param array|null $status Stored status overrides (null: none).
	 * @param string     $code   Expected error code.
	 */
	public function test_scan_gates( $status, string $code ) {
		$id = $this->attachment();
		if ( null !== $status ) {
			self::complete_scan( $status );
			$row = array_filter( get_option( UsageScanStatus::OPTION ), static function ( $value ) {
				return null !== $value;
			} );
			update_option( UsageScanStatus::OPTION, $row, false );
		}

		$this->assert_error( $code, $this->call_ability( self::TRASH, [ 'ids' => [ $id ] ] ) );
		$this->assert_error( $code, $this->call_ability( self::PREVIEW, [ 'ids' => [ $id ] ] ) );
		$this->assertSame( 'inherit', self::status_of( $id ) );
	}

	/**
	 * A pending per-post usage re-check refuses the request.
	 */
	public function test_pending_rescan_refuses() {
		$id = $this->attachment();
		self::complete_scan();
		wp_schedule_single_event( time() + 10, TrashEligibility::POST_RESCAN_HOOK, [ 123 ] );

		$this->assert_error( TrashEligibility::ERROR_UPDATES_PENDING, $this->call_ability( self::TRASH, [ 'ids' => [ $id ] ] ) );
		$this->assertSame( 'inherit', self::status_of( $id ) );
	}

	/**
	 * The site's trash is on in this environment, so wp_trash_post() trashes.
	 * (With EMPTY_TRASH_DAYS at 0 the request is refused; the constant cannot
	 * be changed inside one test process.)
	 */
	public function test_trash_is_enabled_here() {
		$this->assertTrue( TrashEligibility::trash_enabled() );
	}

	/**
	 * A new scan that starts while the IDs are being checked refuses the
	 * whole request; nothing is trashed.
	 */
	public function test_scan_change_during_validation_refuses() {
		$a = $this->attachment();
		$b = $this->attachment();
		self::complete_scan();
		$fired = false;
		$start = static function ( $value, $object_id, $meta_key ) use ( &$fired, $b ) {
			if ( ! $fired && UsedWhereScanner::META_KEY === $meta_key && $b === (int) $object_id ) {
				$fired = true;
				UsedWhereScanner::instance()->start_scheduled_scan();
			}
			return $value;
		};
		add_filter( 'get_post_metadata', $start, 10, 3 );

		$result = $this->call_ability( self::TRASH, [ 'ids' => [ $a, $b ] ] );

		remove_filter( 'get_post_metadata', $start, 10 );
		$this->assertTrue( $fired );
		$this->assert_error( TrashEligibility::ERROR_SCAN_CHANGED, $result );
		$this->assertSame( 'inherit', self::status_of( $a ) );
		$this->assertSame( 'inherit', self::status_of( $b ) );
	}

	/**
	 * When the scan changes after some IDs were trashed, the rest are not
	 * moved (failed, scan_changed); what was moved is reported.
	 *
	 * @return array
	 */
	public function data_mid_run_changes(): array {
		return [
			'new scan starts'      => [ 'scan' ],
			'post re-check queued' => [ 'rescan' ],
		];
	}

	/**
	 * Mid-run changes.
	 *
	 * @dataProvider data_mid_run_changes
	 *
	 * @param string $change What happens after the first move.
	 */
	public function test_scan_change_during_trashing_stops( string $change ) {
		$a = $this->attachment();
		$b = $this->attachment();
		$c = $this->attachment();
		self::complete_scan();
		$once  = false;
		$after = static function () use ( &$once, $change ) {
			if ( $once ) {
				return;
			}
			$once = true;
			if ( 'scan' === $change ) {
				UsedWhereScanner::instance()->start_scheduled_scan();
			} else {
				wp_schedule_single_event( time() + 10, TrashEligibility::POST_RESCAN_HOOK, [ 5 ] );
			}
		};
		add_action( 'trashed_post', $after );

		$result = $this->call_ability( self::TRASH, [ 'ids' => [ $a, $b, $c ] ] );

		remove_action( 'trashed_post', $after );
		$this->assertSame( [ $a ], $result['trashed'] );
		$this->assertSame(
			[
				$a => [ 'trashed', null ],
				$b => [ 'failed', 'scan_changed' ],
				$c => [ 'failed', 'scan_changed' ],
			],
			self::by_id( $result, 'result' )
		);
		$this->assertSame( 'inherit', self::status_of( $b ) );
		$this->assertSame( 'inherit', self::status_of( $c ) );
	}

	/**
	 * An attachment that gets a usage recorded after the first check is
	 * skipped when it is reached.
	 */
	public function test_usage_recorded_after_first_check_is_skipped() {
		$a = $this->attachment();
		$b = $this->attachment();
		self::complete_scan();
		$after = static function ( $post_id ) use ( $a, $b ) {
			if ( $a === (int) $post_id ) {
				self::mark_used( $b );
			}
		};
		add_action( 'trashed_post', $after );

		$result = $this->call_ability( self::TRASH, [ 'ids' => [ $a, $b ] ] );

		remove_action( 'trashed_post', $after );
		$this->assertSame( [ $a => [ 'trashed', null ], $b => [ 'skipped', 'used' ] ], self::by_id( $result, 'result' ) );
		$this->assertSame( 'inherit', self::status_of( $b ) );
	}

	/**
	 * When WordPress does not move an attachment (a plugin blocks it, or
	 * claims success without moving), it is reported as failed.
	 */
	public function test_trash_failure_is_reported() {
		$blocked = $this->attachment();
		$faked   = $this->attachment();
		$ok      = $this->attachment();
		self::complete_scan();
		$filter = static function ( $check, $post ) use ( $blocked, $faked ) {
			if ( $blocked === (int) $post->ID ) {
				return false;
			}
			if ( $faked === (int) $post->ID ) {
				return $post;
			}
			return $check;
		};
		add_filter( 'pre_trash_post', $filter, 10, 2 );

		$result = $this->call_ability( self::TRASH, [ 'ids' => [ $blocked, $faked, $ok ] ] );

		remove_filter( 'pre_trash_post', $filter, 10 );
		$this->assertSame( [ $ok ], $result['trashed'] );
		$this->assertSame(
			[
				$blocked => [ 'failed', 'trash_failed' ],
				$faked   => [ 'failed', 'trash_failed' ],
				$ok      => [ 'trashed', null ],
			],
			self::by_id( $result, 'result' )
		);
	}

	// ---------------------------------------------------------------------
	// Preview
	// ---------------------------------------------------------------------

	/**
	 * The preview reports the same eligibility as the tool would act on, and
	 * changes nothing.
	 */
	public function test_preview_matches_and_changes_nothing() {
		$ids    = $this->mixed_ids();
		$status = get_option( UsageScanStatus::OPTION );
		$cron   = _get_cron_array();
		$writes = [];
		$track  = static function () use ( &$writes ) {
			$writes[] = current_filter();
		};
		$hooks  = [ 'add_option', 'update_option', 'delete_option', 'added_post_meta', 'updated_post_meta', 'deleted_post_meta', 'post_updated', 'wp_trash_post' ];
		foreach ( $hooks as $hook ) {
			add_action( $hook, $track );
		}

		$preview = $this->call_ability( self::PREVIEW, [ 'ids' => array_values( $ids ) ] );

		foreach ( $hooks as $hook ) {
			remove_action( $hook, $track );
		}
		$this->assertSame( [], $writes );
		$this->assertSame( $cron, _get_cron_array() );
		$this->assertSame( $status, get_option( UsageScanStatus::OPTION ) );
		$this->assertSame( [ 'scan', 'eligible', 'results' ], array_keys( $preview ) );
		$this->assertSame( [ $ids['unused1'], $ids['unused2'] ], $preview['eligible'] );
		foreach ( [ 'unused1', 'unused2', 'used', 'late' ] as $key ) {
			$this->assertSame( 'inherit', self::status_of( $ids[ $key ] ), $key . ' unchanged by the preview' );
		}

		$trash = $this->call_ability( self::TRASH, [ 'ids' => array_values( $ids ) ] );
		$this->assertSame( $preview['eligible'], $trash['trashed'] );
		$this->assertSame( wp_list_pluck( $preview['results'], 'reason' ), wp_list_pluck( $trash['results'], 'reason' ) );
	}

	// ---------------------------------------------------------------------
	// Input and permissions
	// ---------------------------------------------------------------------

	/**
	 * Input the schema refuses.
	 *
	 * @return array
	 */
	public function data_invalid_input(): array {
		return [
			'no ids'         => [ [] ],
			'empty ids'      => [ [ 'ids' => [] ] ],
			'51 ids'         => [ [ 'ids' => range( 1, 51 ) ] ],
			'duplicates'     => [ [ 'ids' => [ 5, 5 ] ] ],
			'string id'      => [ [ 'ids' => [ 'abc' ] ] ],
			'float id'       => [ [ 'ids' => [ 1.5 ] ] ],
			'zero'           => [ [ 'ids' => [ 0 ] ] ],
			'negative'       => [ [ 'ids' => [ -2 ] ] ],
			'extra property' => [ [ 'ids' => [ 5 ], 'force' => true ] ],
			'dry run flag'   => [ [ 'ids' => [ 5 ], 'dry_run' => false ] ],
		];
	}

	/**
	 * Schema refusals, for both abilities.
	 *
	 * @dataProvider data_invalid_input
	 *
	 * @param array $input Input.
	 */
	public function test_schema_refuses_invalid_input( array $input ) {
		self::complete_scan();
		foreach ( [ self::TRASH, self::PREVIEW ] as $name ) {
			$this->assert_error( 'ability_invalid_input', $this->call_ability( $name, $input ) );
		}
	}

	/**
	 * Callback refusals (reached without the schema).
	 *
	 * @return array
	 */
	public function data_invalid_direct_input(): array {
		return [
			'not an array'    => [ 'all', AbilityGuard::ERROR_INVALID_INPUT ],
			'no ids'          => [ [], AbilityGuard::ERROR_INVALID_INPUT ],
			'ids not a list'  => [ [ 'ids' => 5 ], AbilityGuard::ERROR_INVALID_INPUT ],
			'keyed ids'       => [ [ 'ids' => [ 'a' => 5 ] ], AbilityGuard::ERROR_INVALID_INPUT ],
			'51 ids'          => [ [ 'ids' => range( 1, 51 ) ], AbilityGuard::ERROR_INVALID_INPUT ],
			'duplicates'      => [ [ 'ids' => [ 7, 7 ] ], AbilityGuard::ERROR_INVALID_INPUT ],
			'numeric string'  => [ [ 'ids' => [ '7' ] ], AbilityGuard::ERROR_INVALID_ATTACHMENT_ID ],
			'zero'            => [ [ 'ids' => [ 0 ] ], AbilityGuard::ERROR_INVALID_ATTACHMENT_ID ],
			'extra property'  => [ [ 'ids' => [ 7 ], 'all' => true ], AbilityGuard::ERROR_INVALID_INPUT ],
		];
	}

	/**
	 * Direct-call validation.
	 *
	 * @dataProvider data_invalid_direct_input
	 *
	 * @param mixed  $input Input.
	 * @param string $code  Code.
	 */
	public function test_callback_refuses_invalid_input( $input, string $code ) {
		self::complete_scan();
		foreach ( [ self::TRASH, self::PREVIEW ] as $name ) {
			$this->assert_error( $code, self::direct( $name, $input ) );
		}
	}

	/**
	 * 50 IDs are accepted.
	 */
	public function test_fifty_ids_accepted() {
		self::complete_scan();
		$ids    = range( 900000001, 900000050 );
		$result = $this->call_ability( self::PREVIEW, [ 'ids' => $ids ] );
		$this->assertCount( 50, $result['results'] );
		$this->assertSame( [], $result['eligible'] );
	}

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
	 * manage_options is required by the permission callback and again by
	 * the callback; nothing is trashed.
	 *
	 * @dataProvider data_refused_users
	 *
	 * @param string $role Role ('' for logged out).
	 * @param string $code Code from the callback.
	 */
	public function test_requires_manage_options( string $role, string $code ) {
		$id = $this->attachment();
		self::complete_scan();
		wp_set_current_user( $role ? self::factory()->user->create( [ 'role' => $role ] ) : 0 );

		foreach ( [ self::TRASH, self::PREVIEW ] as $name ) {
			$this->assert_error( 'ability_invalid_permissions', $this->call_ability( $name, [ 'ids' => [ $id ] ] ) );
			$this->assert_error( $code, self::direct( $name, [ 'ids' => [ $id ] ] ) );
		}
		$this->assertSame( 'inherit', self::status_of( $id ) );
	}

	// ---------------------------------------------------------------------
	// Registration, MCP and the full workflow
	// ---------------------------------------------------------------------

	/**
	 * Registration meta.
	 */
	public function test_registration_meta() {
		$trash = wp_get_ability( self::TRASH )->get_meta();
		$this->assertFalse( $trash['annotations']['readonly'] );
		$this->assertTrue( $trash['annotations']['destructive'] );
		$this->assertFalse( $trash['mcp']['public'] );
		$preview = wp_get_ability( self::PREVIEW )->get_meta();
		$this->assertTrue( $preview['annotations']['readonly'] );
		$this->assertFalse( $preview['annotations']['destructive'] );
		$this->assertFalse( $preview['mcp']['public'] );
		foreach ( [ self::TRASH, self::PREVIEW ] as $name ) {
			$schema = wp_get_ability( $name )->get_input_schema();
			$this->assertFalse( $schema['additionalProperties'] );
			$this->assertSame( [ 'ids' ], array_keys( $schema['properties'] ) );
			$this->assertSame( 50, $schema['properties']['ids']['maxItems'] );
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
	 * Call a tool on a route.
	 *
	 * @param string $route     Route.
	 * @param string $tool      Tool.
	 * @param array  $arguments Arguments.
	 *
	 * @return array Result.
	 */
	private function tool( string $route, string $tool, array $arguments = [] ): array {
		return $this->send(
			$route,
			'tools/call',
			[
				'name'      => $tool,
				'arguments' => (object) $arguments,
			]
		)['result'];
	}

	/**
	 * Listed on the dedicated server with the right hints; output passes the
	 * adapter's schema check; refusals are tool errors.
	 */
	public function test_tools_on_dedicated_server() {
		$this->connect( self::SCOPED_ROUTE );
		$tools = array_column( $this->send( self::SCOPED_ROUTE, 'tools/list' )['result']['tools'], null, 'name' );
		$this->assertTrue( $tools['tsmlt-trash-unused-media']['annotations']['destructiveHint'] );
		$this->assertFalse( $tools['tsmlt-trash-unused-media']['annotations']['readOnlyHint'] );
		$this->assertTrue( $tools['tsmlt-preview-trash-unused-media']['annotations']['readOnlyHint'] );

		$refused = $this->tool( self::SCOPED_ROUTE, 'tsmlt-trash-unused-media', [ 'ids' => [ $this->attachment() ] ] );
		$this->assertTrue( $refused['isError'], 'No completed scan.' );

		$ids = $this->mixed_ids();
		$preview = $this->tool( self::SCOPED_ROUTE, 'tsmlt-preview-trash-unused-media', [ 'ids' => array_values( $ids ) ] );
		$this->assertFalse( $preview['isError'], wp_json_encode( $preview ) );
		$trash = $this->tool( self::SCOPED_ROUTE, 'tsmlt-trash-unused-media', [ 'ids' => $preview['structuredContent']['eligible'] ] );
		$this->assertFalse( $trash['isError'], wp_json_encode( $trash ) );
		$this->assertSame( [ $ids['unused1'], $ids['unused2'] ], $trash['structuredContent']['trashed'] );
	}

	/**
	 * Neither tool is discoverable or runnable through the default gateway.
	 */
	public function test_not_on_default_gateway() {
		$this->connect( self::DEFAULT_ROUTE );
		$id = $this->attachment();
		self::complete_scan();

		$names = wp_list_pluck( $this->tool( self::DEFAULT_ROUTE, 'mcp-adapter-discover-abilities' )['structuredContent']['abilities'], 'name' );
		$this->assertNotContains( self::TRASH, $names );
		$this->assertNotContains( self::PREVIEW, $names );
		foreach ( [ self::TRASH, self::PREVIEW ] as $name ) {
			$run = $this->tool(
				self::DEFAULT_ROUTE,
				'mcp-adapter-execute-ability',
				[
					'ability_name' => $name,
					'parameters'   => (object) [ 'ids' => [ $id ] ],
				]
			);
			$this->assertTrue( $run['isError'], $name );
		}
		$this->assertSame( 'inherit', self::status_of( $id ) );
	}

	/**
	 * The documented workflow with a real scan: start, wait for complete,
	 * audit, preview, trash the confirmed-unused IDs.
	 */
	public function test_full_workflow_with_a_real_scan() {
		$this->connect( self::SCOPED_ROUTE );
		$used   = (int) self::factory()->attachment->create( [ 'file' => 'wf/used.jpg', 'post_mime_type' => 'image/jpeg', 'post_date' => '2020-01-01 00:00:00', 'post_date_gmt' => '2020-01-01 00:00:00' ] );
		$unused = (int) self::factory()->attachment->create( [ 'file' => 'wf/unused.jpg', 'post_mime_type' => 'image/jpeg', 'post_date' => '2020-01-01 00:00:00', 'post_date_gmt' => '2020-01-01 00:00:00' ] );
		$post   = (int) self::factory()->post->create();
		set_post_thumbnail( $post, $used );
		wp_unschedule_hook( TrashEligibility::POST_RESCAN_HOOK );

		$this->assertSame( 'started', $this->tool( self::SCOPED_ROUTE, 'tsmlt-start-usage-scan' )['structuredContent']['result'] );
		$this->assertTrue( $this->tool( self::SCOPED_ROUTE, 'tsmlt-trash-unused-media', [ 'ids' => [ $unused ] ] )['isError'], 'Refused while queued.' );
		for ( $i = 0; $i < 50; $i++ ) {
			$ticks = [];
			foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
				foreach ( $hooks[ UsedWhereScanner::SCAN_TICK_HOOK ] ?? [] as $event ) {
					$ticks[] = [ $timestamp, (int) $event['args'][0] ];
				}
			}
			if ( ! $ticks ) {
				break;
			}
			wp_unschedule_event( $ticks[0][0], UsedWhereScanner::SCAN_TICK_HOOK, [ $ticks[0][1] ] );
			UsedWhereScanner::instance()->run_tick_batch( $ticks[0][1] );
		}
		$this->assertSame( 'complete', $this->tool( self::SCOPED_ROUTE, 'tsmlt-get-usage-scan-status' )['structuredContent']['state'] );

		$usage = array_column( $this->tool( self::SCOPED_ROUTE, 'tsmlt-get-media-usage', [ 'ids' => [ $used, $unused ] ] )['structuredContent']['items'], 'used', 'id' );
		$this->assertSame( [ $used => true, $unused => false ], $usage );

		$preview = $this->tool( self::SCOPED_ROUTE, 'tsmlt-preview-trash-unused-media', [ 'ids' => [ $used, $unused ] ] )['structuredContent'];
		$this->assertSame( [ $unused ], $preview['eligible'] );

		$trash = $this->tool( self::SCOPED_ROUTE, 'tsmlt-trash-unused-media', [ 'ids' => $preview['eligible'] ] )['structuredContent'];
		$this->assertSame( [ $unused ], $trash['trashed'] );
		$this->assertSame( 'trash', self::status_of( $unused ) );
		$this->assertSame( 'inherit', self::status_of( $used ) );
	}
}
