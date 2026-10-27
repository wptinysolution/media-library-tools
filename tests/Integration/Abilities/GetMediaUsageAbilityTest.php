<?php
/**
 * Integration tests for tsmlt/get-media-usage: where attachments are used,
 * read from the Used Where records without scanning or writing anything.
 *
 * Usage records are written in the scanner's own format (post meta
 * `_tsmlt_image_usages`), and once by the scanner itself. The MCP tests run
 * when the MCP Adapter is loaded (MCP_ADAPTER_DIR is set). Every post and
 * attachment is a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\GetMediaUsage;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * GetMediaUsageAbilityTest
 */
class GetMediaUsageAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 */
	const NAME = 'tsmlt/get-media-usage';

	/**
	 * MCP tool name.
	 */
	const TOOL = 'tsmlt-get-media-usage';

	/**
	 * Dedicated server route.
	 */
	const SCOPED_ROUTE = '/media-library-tools/mcp';

	/**
	 * Default gateway route.
	 */
	const DEFAULT_ROUTE = '/mcp/mcp-adapter-default-server';

	/**
	 * Keys of one item.
	 */
	const ITEM_KEYS = [ 'id', 'used', 'total_usage', 'hidden', 'truncated', 'usages' ];

	/**
	 * Keys of one usage.
	 */
	const USAGE_KEYS = [ 'post_id', 'post_type', 'post_status', 'title', 'usage_type', 'sitewide' ];

	/**
	 * Post IDs whose read_post is denied by the test filter.
	 *
	 * @var int[]
	 */
	private $denied = [];

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
	 * Administrator; no scan status; read_post denial filter.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( GetMediaUsage::SCAN_STATUS_OPTION );
		$this->denied   = [];
		$this->sessions = [];
		add_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10, 4 );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
	}

	/**
	 * Remove filters.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10 );
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		parent::tear_down();
	}

	/**
	 * Deny read_post for the posts in $this->denied.
	 *
	 * @param string[] $caps    Required capabilities.
	 * @param string   $cap     Requested capability.
	 * @param int      $user_id User ID.
	 * @param array    $args    Arguments.
	 *
	 * @return string[]
	 */
	public function deny_read( $caps, $cap, $user_id, $args ) {
		if ( 'read_post' === $cap && isset( $args[0] ) && in_array( (int) $args[0], $this->denied, true ) ) {
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
	 * Run the ability through the Abilities API (schema and permission checks).
	 *
	 * @param array $input Input.
	 *
	 * @return mixed
	 */
	private function call_ability( array $input ) {
		$ability = wp_get_ability( self::NAME );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		return $ability->execute( $input );
	}

	/**
	 * Call the execute callback directly (no schema or permission callback).
	 *
	 * @param mixed $input Input.
	 *
	 * @return mixed
	 */
	private static function direct( $input ) {
		return GetMediaUsage::instance()->execute( $input );
	}

	/**
	 * An image attachment.
	 *
	 * @param string $title Title.
	 * @param array  $args  Extra post fields.
	 *
	 * @return int
	 */
	private function attachment( string $title = 'Usage fixture', array $args = [] ): int {
		return (int) self::factory()->attachment->create(
			array_merge(
				[
					'post_title'     => $title,
					'post_mime_type' => 'image/jpeg',
				],
				$args
			)
		);
	}

	/**
	 * A post.
	 *
	 * @param string $title  Title.
	 * @param string $status Status.
	 * @param string $type   Post type.
	 *
	 * @return int
	 */
	private function post( string $title, string $status = 'publish', string $type = 'post' ): int {
		return (int) self::factory()->post->create(
			[
				'post_title'  => $title,
				'post_status' => $status,
				'post_type'   => $type,
			]
		);
	}

	/**
	 * Store usage records in the scanner's format.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $records       Records: [ post_id, usage_type, cached title, post type ].
	 */
	private static function record( int $attachment_id, array $records ) {
		$rows = [];
		foreach ( $records as $record ) {
			$rows[] = [
				'post_id'    => $record[0],
				'post_title' => $record[2] ?? 'Cached title',
				'post_type'  => $record[3] ?? ( 0 === $record[0] ? 'site_settings' : 'post' ),
				'usage_type' => $record[1],
			];
		}
		update_post_meta( $attachment_id, UsedWhereScanner::META_KEY, $rows );
	}

	/**
	 * Store a scan status.
	 *
	 * @param array $status Status row.
	 */
	private static function scan( array $status ) {
		update_option( GetMediaUsage::SCAN_STATUS_OPTION, $status, false );
	}

	/**
	 * The single item of a successful one-ID result.
	 *
	 * @param mixed $result Result.
	 *
	 * @return array
	 */
	private function only_item( $result ): array {
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		$this->assertCount( 1, $result['items'] );
		return $result['items'][0];
	}

	/**
	 * Assert an error code.
	 *
	 * @param string $code   Expected code.
	 * @param mixed  $result Result.
	 */
	private function assert_error( string $code, $result ) {
		$this->assertWPError( $result );
		$this->assertSame( $code, $result->get_error_code(), $result->get_error_message() );
	}

	// ---------------------------------------------------------------------
	// Usages
	// ---------------------------------------------------------------------

	/**
	 * Featured, content, excerpt, page builder and site-wide usages are
	 * reported with live titles and statuses; site-wide ones as post 0.
	 */
	public function test_reports_recorded_usages() {
		$id       = $this->attachment();
		$featured = $this->post( 'Old featured title' );
		$draft    = $this->post( 'Draft & "quoted"', 'draft' );
		$page     = $this->post( 'Builder page', 'publish', 'page' );
		self::record(
			$id,
			[
				[ $featured, 'featured', 'Old featured title' ],
				[ $draft, 'content' ],
				[ $draft, 'excerpt' ],
				[ $page, 'elementor', 'Builder page', 'page' ],
				[ 0, 'site_logo', 'Site Settings' ],
				[ 0, 'widget', 'Site Settings' ],
			]
		);
		wp_update_post(
			[
				'ID'         => $featured,
				'post_title' => 'New featured title',
			]
		);

		$item = $this->only_item( $this->call_ability( [ 'ids' => [ $id ] ] ) );

		$this->assertSame( self::ITEM_KEYS, array_keys( $item ) );
		$this->assertSame( $id, $item['id'] );
		$this->assertTrue( $item['used'] );
		$this->assertSame( 6, $item['total_usage'] );
		$this->assertSame( 0, $item['hidden'] );
		$this->assertFalse( $item['truncated'] );
		$this->assertSame(
			[
				[ $featured, 'post', 'publish', 'New featured title', 'featured', false ],
				[ $draft, 'post', 'draft', 'Draft & "quoted"', 'content', false ],
				[ $draft, 'post', 'draft', 'Draft & "quoted"', 'excerpt', false ],
				[ $page, 'page', 'publish', 'Builder page', 'elementor', false ],
				[ 0, 'site_settings', null, null, 'site_logo', true ],
				[ 0, 'site_settings', null, null, 'widget', true ],
			],
			array_map( 'array_values', $item['usages'] )
		);
		foreach ( $item['usages'] as $usage ) {
			$this->assertSame( self::USAGE_KEYS, array_keys( $usage ) );
		}
	}

	/**
	 * Records written by the scanner itself (featured image and content) are
	 * read back.
	 */
	public function test_reads_records_written_by_the_scanner() {
		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$post       = self::factory()->post->create(
			[
				'post_title'   => 'Scanned post',
				'post_content' => wp_get_attachment_image( $attachment, 'full' ),
			]
		);
		set_post_thumbnail( $post, $attachment );
		UsedWhereScanner::instance()->process_batch( 0, 50, false );
		self::scan( [ 'state' => 'complete', 'processed' => 2, 'total' => 2 ] );

		$item = $this->only_item( $this->call_ability( [ 'ids' => [ $attachment ] ] ) );
		wp_delete_file( get_attached_file( $attachment ) );

		$this->assertTrue( $item['used'] );
		$types = [];
		foreach ( $item['usages'] as $usage ) {
			$this->assertSame( $post, $usage['post_id'] );
			$this->assertSame( 'Scanned post', $usage['title'] );
			$types[] = $usage['usage_type'];
		}
		$this->assertContains( 'featured', $types );
		$this->assertContains( 'content', $types );
	}

	/**
	 * A private post the user can read is listed; posts the user cannot
	 * read, and posts of a type no longer registered, are only counted.
	 */
	public function test_unreadable_posts_are_counted_as_hidden() {
		$id       = $this->attachment();
		$private  = $this->post( 'Private post', 'private' );
		$denied   = $this->post( 'Secret post' );
		$orphaned = $this->post( 'Old type post', 'publish', 'tsmlt_gone_type' );
		$this->assertFalse( post_type_exists( 'tsmlt_gone_type' ), 'Precondition: unregistered type.' );
		$this->denied = [ $denied ];
		self::record(
			$id,
			[
				[ $private, 'content' ],
				[ $denied, 'content', 'Secret post' ],
				[ $orphaned, 'meta', 'Old type post', 'tsmlt_gone_type' ],
			]
		);

		$item = $this->only_item( $this->call_ability( [ 'ids' => [ $id ] ] ) );

		$this->assertTrue( $item['used'] );
		$this->assertSame( 3, $item['total_usage'] );
		$this->assertSame( 2, $item['hidden'] );
		$this->assertCount( 1, $item['usages'] );
		$this->assertSame( [ $private, 'private', 'Private post' ], [ $item['usages'][0]['post_id'], $item['usages'][0]['post_status'], $item['usages'][0]['title'] ] );
		$json = (string) wp_json_encode( $item );
		$this->assertStringNotContainsString( 'Secret post', $json );
		$this->assertStringNotContainsString( 'Old type post', $json );
	}

	/**
	 * Records of posts that no longer exist are skipped; when nothing else
	 * remains, the item is not used (scan complete).
	 */
	public function test_deleted_posts_are_skipped() {
		$id      = $this->attachment();
		$kept    = $this->post( 'Kept post' );
		$deleted = $this->post( 'Deleted post' );
		self::record(
			$id,
			[
				[ $kept, 'content' ],
				[ $deleted, 'content', 'Deleted post' ],
				[ 987654321, 'featured', 'Never existed' ],
			]
		);
		wp_delete_post( $deleted, true );
		$other = $this->attachment();
		self::record( $other, [ [ 987654321, 'featured' ] ] );
		self::scan( [ 'state' => 'complete' ] );

		$result = $this->call_ability( [ 'ids' => [ $id, $other ] ] );

		$this->assertSame( 1, $result['items'][0]['total_usage'] );
		$this->assertSame( [ $kept ], wp_list_pluck( $result['items'][0]['usages'], 'post_id' ) );
		$this->assertSame( 0, $result['items'][0]['hidden'] );
		$this->assertFalse( $result['items'][1]['used'] );
		$this->assertSame( 0, $result['items'][1]['total_usage'] );
	}

	/**
	 * A usage type the scanner does not define is reported as "other";
	 * malformed records are ignored.
	 */
	public function test_unknown_usage_types_become_other() {
		$id   = $this->attachment();
		$post = $this->post( 'Typed post' );
		update_post_meta(
			$id,
			UsedWhereScanner::META_KEY,
			[
				[
					'post_id'    => $post,
					'post_type'  => 'post',
					'usage_type' => '<script>alert(1)</script>',
				],
				[
					'post_id'    => $post,
					'post_type'  => 'post',
					'usage_type' => [ 'array' ],
				],
				[
					'post_id'    => $post,
					'post_type'  => 'post',
					'usage_type' => 'permalink',
				],
				[
					'post_id'    => 0,
					'usage_type' => 'made_up_sitewide',
				],
				'not a record',
				[
					'post_id'    => 'abc',
					'usage_type' => 'content',
				],
			]
		);

		$item = $this->only_item( $this->call_ability( [ 'ids' => [ $id ] ] ) );

		$this->assertSame( [ 'other', 'other', 'permalink', 'other' ], wp_list_pluck( $item['usages'], 'usage_type' ) );
		$this->assertSame( [ false, false, false, true ], wp_list_pluck( $item['usages'], 'sitewide' ) );
		$this->assertSame( 4, $item['total_usage'] );
	}

	/**
	 * max_usages limits the list, not the counts; hidden usages do not count
	 * towards the limit.
	 */
	public function test_truncation() {
		$id    = $this->attachment();
		$posts = [];
		for ( $i = 1; $i <= 4; $i++ ) {
			$posts[] = $this->post( 'Post ' . $i );
		}
		$this->denied = [ $posts[3] ];
		self::record(
			$id,
			array_map(
				static function ( $post ) {
					return [ $post, 'content' ];
				},
				$posts
			)
		);

		$cut = $this->only_item( $this->call_ability( [ 'ids' => [ $id ], 'max_usages' => 2 ] ) );
		$this->assertTrue( $cut['truncated'] );
		$this->assertSame( array_slice( $posts, 0, 2 ), wp_list_pluck( $cut['usages'], 'post_id' ) );
		$this->assertSame( 4, $cut['total_usage'] );
		$this->assertSame( 1, $cut['hidden'] );

		$exact = $this->only_item( $this->call_ability( [ 'ids' => [ $id ], 'max_usages' => 3 ] ) );
		$this->assertFalse( $exact['truncated'] );
		$this->assertCount( 3, $exact['usages'] );

		$default = $this->only_item( $this->call_ability( [ 'ids' => [ $id ] ] ) );
		$this->assertFalse( $default['truncated'] );
	}

	/**
	 * Items come back in the order requested; nothing but the allowlisted
	 * keys is returned (no permalinks, cached titles or paths).
	 */
	public function test_order_and_minimal_output() {
		$a    = $this->attachment( 'A' );
		$b    = $this->attachment( 'B' );
		$post = $this->post( 'Linked post' );
		self::record( $b, [ [ $post, 'featured', 'Cached title' ] ] );

		$result = $this->call_ability( [ 'ids' => [ $b, $a ] ] );

		$this->assertSame( [ 'scan', 'items' ], array_keys( $result ) );
		$this->assertSame( [ $b, $a ], wp_list_pluck( $result['items'], 'id' ) );
		$json = (string) wp_json_encode( $result );
		foreach ( [ 'post_link', 'permalink', 'url', 'post_title', 'Cached title', home_url(), wp_upload_dir()['basedir'] ] as $absent ) {
			$this->assertStringNotContainsString( $absent, str_replace( '"usage_type":"permalink"', '', $json ), $absent );
		}
	}

	// ---------------------------------------------------------------------
	// Scan state
	// ---------------------------------------------------------------------

	/**
	 * Scan states and their effect on items without usage.
	 *
	 * @return array
	 */
	public function data_scan_states(): array {
		return [
			'no scan yet'               => [ null, 'idle', false, null ],
			'queued'                    => [ [ 'state' => 'queued' ], 'queued', false, null ],
			'running'                   => [ [ 'state' => 'running' ], 'running', false, null ],
			'cancelled'                 => [ [ 'state' => 'cancelled' ], 'cancelled', false, null ],
			'error'                     => [ [ 'state' => 'error' ], 'error', false, null ],
			'complete'                  => [ [ 'state' => 'complete' ], 'complete', true, false ],
			'row from before state'     => [ [ 'complete' => true ], 'complete', true, false ],
			'old incomplete row'        => [ [ 'complete' => false ], 'idle', false, null ],
			'unknown state'             => [ [ 'state' => 'exploded' ], 'idle', false, null ],
			'not an array'              => [ 'garbage', 'idle', false, null ],
		];
	}

	/**
	 * used is false only after a completed scan, null otherwise; an item
	 * with a recorded usage is used whatever the scan state.
	 *
	 * @dataProvider data_scan_states
	 *
	 * @param mixed     $status   Stored status (null: none).
	 * @param string    $state    Expected state.
	 * @param bool      $complete Expected complete.
	 * @param bool|null $unused   Expected used for an item without usage.
	 */
	public function test_scan_state_semantics( $status, string $state, bool $complete, $unused ) {
		if ( null !== $status ) {
			update_option( GetMediaUsage::SCAN_STATUS_OPTION, $status, false );
		}
		$none = $this->attachment( 'No usage' );
		$used = $this->attachment( 'Used' );
		self::record( $used, [ [ 0, 'site_icon' ] ] );

		$result = $this->call_ability( [ 'ids' => [ $none, $used ] ] );

		$this->assertSame( $state, $result['scan']['state'] );
		$this->assertSame( $complete, $result['scan']['complete'] );
		$this->assertSame( $unused, $result['items'][0]['used'] );
		$this->assertSame( 0, $result['items'][0]['total_usage'] );
		$this->assertSame( [], $result['items'][0]['usages'] );
		$this->assertTrue( $result['items'][1]['used'] );
	}

	/**
	 * Progress and time come from the stored status.
	 */
	public function test_scan_status_fields() {
		self::scan(
			[
				'state'     => 'running',
				'processed' => 40,
				'total'     => 120,
				'timestamp' => '2026-10-27 09:30:00',
				'last_error' => 'should not appear',
			]
		);

		$scan = $this->call_ability( [ 'ids' => [ $this->attachment() ] ] )['scan'];

		$this->assertSame(
			[
				'state'       => 'running',
				'complete'    => false,
				'processed'   => 40,
				'total'       => 120,
				'last_update' => '2026-10-27 09:30:00',
			],
			$scan
		);
	}

	// ---------------------------------------------------------------------
	// No scan, no writes
	// ---------------------------------------------------------------------

	/**
	 * A stalled scan is not re-scheduled (as get_scan_status() would), and
	 * no option, post meta or cron event is written.
	 */
	public function test_never_schedules_a_scan_or_writes() {
		$id   = $this->attachment();
		$post = $this->post( 'Post' );
		self::record( $id, [ [ $post, 'content' ] ] );
		$stalled = [
			'state'        => 'running',
			'processed'    => 5,
			'total'        => 50,
			'next_offset'  => 5,
			'last_tick_at' => '2000-01-01 00:00:00',
		];
		self::scan( $stalled );
		$usage_before = get_post_meta( $id, UsedWhereScanner::META_KEY, true );
		$cron_before  = _get_cron_array();

		$writes = [];
		$track  = static function () use ( &$writes ) {
			$writes[] = current_filter();
		};
		foreach ( [ 'update_option', 'add_option', 'delete_option', 'add_post_meta', 'update_post_meta', 'delete_post_meta', 'clean_post_cache', 'schedule_event' ] as $hook ) {
			add_action( $hook, $track );
		}
		$scheduled = static function ( $event ) use ( &$writes ) {
			$writes[] = 'schedule_event';
			return $event;
		};
		add_filter( 'schedule_event', $scheduled );

		$result = $this->call_ability( [ 'ids' => [ $id ] ] );

		foreach ( [ 'update_option', 'add_option', 'delete_option', 'add_post_meta', 'update_post_meta', 'delete_post_meta', 'clean_post_cache' ] as $hook ) {
			remove_action( $hook, $track );
		}
		remove_filter( 'schedule_event', $scheduled );

		$this->assertIsArray( $result );
		$this->assertSame( [], $writes );
		$this->assertFalse( wp_next_scheduled( UsedWhereScanner::SCAN_TICK_HOOK, [ 5 ] ) );
		$this->assertSame( $cron_before, _get_cron_array() );
		$this->assertSame( $stalled, get_option( GetMediaUsage::SCAN_STATUS_OPTION ) );
		$this->assertSame( $usage_before, get_post_meta( $id, UsedWhereScanner::META_KEY, true ) );
	}

	// ---------------------------------------------------------------------
	// Input validation
	// ---------------------------------------------------------------------

	/**
	 * Input the schema refuses.
	 *
	 * @return array
	 */
	public function data_invalid_input(): array {
		return [
			'no ids'             => [ [] ],
			'empty ids'          => [ [ 'ids' => [] ] ],
			'51 ids'             => [ [ 'ids' => range( 1, 51 ) ] ],
			'duplicate ids'      => [ [ 'ids' => [ 5, 5 ] ] ],
			'string id'          => [ [ 'ids' => [ 'abc' ] ] ],
			'float id'           => [ [ 'ids' => [ 1.5 ] ] ],
			'zero id'            => [ [ 'ids' => [ 0 ] ] ],
			'negative id'        => [ [ 'ids' => [ -3 ] ] ],
			'extra property'     => [ [ 'ids' => [ 5 ], 'include_drafts' => true ] ],
			'max_usages 0'       => [ [ 'ids' => [ 5 ], 'max_usages' => 0 ] ],
			'max_usages 101'     => [ [ 'ids' => [ 5 ], 'max_usages' => 101 ] ],
			'max_usages float'   => [ [ 'ids' => [ 5 ], 'max_usages' => 2.5 ] ],
		];
	}

	/**
	 * The Abilities API refuses invalid input before the callback runs.
	 *
	 * @dataProvider data_invalid_input
	 *
	 * @param array $input Input.
	 */
	public function test_schema_refuses_invalid_input( array $input ) {
		$this->assert_error( 'ability_invalid_input', $this->call_ability( $input ) );
	}

	/**
	 * A single number for ids passes WordPress's schema check (it accepts a
	 * scalar for an array), so the callback refuses it.
	 */
	public function test_single_number_for_ids_is_refused() {
		$this->assert_error( AbilityGuard::ERROR_INVALID_INPUT, $this->call_ability( [ 'ids' => $this->attachment() ] ) );
	}

	/**
	 * Invalid input reaching the callback directly is refused too.
	 *
	 * @return array
	 */
	public function data_invalid_direct_input(): array {
		return [
			'not an array'      => [ 'ids', AbilityGuard::ERROR_INVALID_INPUT ],
			'no ids'            => [ [], AbilityGuard::ERROR_INVALID_INPUT ],
			'empty ids'         => [ [ 'ids' => [] ], AbilityGuard::ERROR_INVALID_INPUT ],
			'51 ids'            => [ [ 'ids' => range( 1, 51 ) ], AbilityGuard::ERROR_INVALID_INPUT ],
			'keyed ids'         => [ [ 'ids' => [ 'a' => 5 ] ], AbilityGuard::ERROR_INVALID_INPUT ],
			'ids not a list'    => [ [ 'ids' => 5 ], AbilityGuard::ERROR_INVALID_INPUT ],
			'duplicate ids'     => [ [ 'ids' => [ 5, 5 ] ], AbilityGuard::ERROR_INVALID_INPUT ],
			'numeric string id' => [ [ 'ids' => [ '5' ] ], AbilityGuard::ERROR_INVALID_ATTACHMENT_ID ],
			'zero id'           => [ [ 'ids' => [ 0 ] ], AbilityGuard::ERROR_INVALID_ATTACHMENT_ID ],
			'extra property'    => [ [ 'ids' => [ 5 ], 'x' => 1 ], AbilityGuard::ERROR_INVALID_INPUT ],
			'string max'        => [ [ 'ids' => [ 5 ], 'max_usages' => '10' ], AbilityGuard::ERROR_INVALID_INPUT ],
			'max too high'      => [ [ 'ids' => [ 5 ], 'max_usages' => 500 ], AbilityGuard::ERROR_INVALID_INPUT ],
		];
	}

	/**
	 * Direct calls validate without the schema.
	 *
	 * @dataProvider data_invalid_direct_input
	 *
	 * @param mixed  $input Input.
	 * @param string $code  Expected code.
	 */
	public function test_callback_refuses_invalid_input( $input, string $code ) {
		$this->assert_error( $code, self::direct( $input ) );
	}

	// ---------------------------------------------------------------------
	// Permissions and attachments
	// ---------------------------------------------------------------------

	/**
	 * Users without manage_options, and logged-out requests, are refused by
	 * the permission callback and by the callback itself.
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
	 * Refused users.
	 *
	 * @dataProvider data_refused_users
	 *
	 * @param string $role Role ('' for logged out).
	 * @param string $code Expected code from the callback.
	 */
	public function test_requires_manage_options( string $role, string $code ) {
		$id = $this->attachment();
		self::record( $id, [ [ 0, 'site_logo' ] ] );
		wp_set_current_user( $role ? self::factory()->user->create( [ 'role' => $role ] ) : 0 );

		$this->assert_error( 'ability_invalid_permissions', $this->call_ability( [ 'ids' => [ $id ] ] ) );
		$this->assert_error( $code, self::direct( [ 'ids' => [ $id ] ] ) );
	}

	/**
	 * Any missing, non-attachment, trashed or unreadable ID fails the whole
	 * request with not found, naming that ID.
	 *
	 * @return array
	 */
	public function data_unavailable_attachments(): array {
		return [
			'missing'         => [ 'missing' ],
			'not attachment'  => [ 'post' ],
			'trashed'         => [ 'trashed' ],
			'not readable'    => [ 'denied' ],
		];
	}

	/**
	 * Whole-request refusal.
	 *
	 * @dataProvider data_unavailable_attachments
	 *
	 * @param string $kind Kind of unavailable ID.
	 */
	public function test_unavailable_attachment_fails_the_request( string $kind ) {
		$good = $this->attachment();
		switch ( $kind ) {
			case 'missing':
				$bad = 987654321;
				break;
			case 'post':
				$bad = $this->post( 'Not media' );
				break;
			case 'trashed':
				$bad = $this->attachment( 'Trashed' );
				wp_trash_post( $bad );
				break;
			default:
				$bad          = $this->attachment( 'Denied' );
				$this->denied = [ $bad ];
		}

		foreach ( [ $this->call_ability( [ 'ids' => [ $good, $bad ] ] ), self::direct( [ 'ids' => [ $good, $bad ] ] ) ] as $result ) {
			$this->assert_error( AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND, $result );
			$this->assertStringContainsString( (string) $bad, $result->get_error_message() );
			$this->assertSame( 404, $result->get_error_data()['status'] );
		}
	}

	// ---------------------------------------------------------------------
	// Registration and MCP
	// ---------------------------------------------------------------------

	/**
	 * Registered read-only, idempotent and not public on the default gateway.
	 */
	public function test_registration_meta() {
		$ability = wp_get_ability( self::NAME );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		$meta = $ability->get_meta();
		$this->assertTrue( $meta['annotations']['readonly'] );
		$this->assertFalse( $meta['annotations']['destructive'] );
		$this->assertTrue( $meta['annotations']['idempotent'] );
		$this->assertFalse( $meta['mcp']['public'] );
		$this->assertFalse( $ability->get_input_schema()['additionalProperties'] );
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
	 * Call a tool.
	 *
	 * @param string $route     Route.
	 * @param string $tool      Tool name.
	 * @param array  $arguments Arguments.
	 *
	 * @return array
	 */
	private function tool( string $route, string $tool, array $arguments ): array {
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
	 * Listed and callable on the dedicated server; the result passes the
	 * adapter's output-schema check.
	 */
	public function test_listed_and_callable_on_dedicated_server() {
		$this->connect( self::SCOPED_ROUTE );
		$id   = $this->attachment();
		$post = $this->post( 'MCP post' );
		self::record( $id, [ [ $post, 'featured' ], [ 0, 'nav_menu' ] ] );
		self::scan( [ 'state' => 'complete' ] );

		$tools = array_column( $this->send( self::SCOPED_ROUTE, 'tools/list' )['result']['tools'], null, 'name' );
		$this->assertArrayHasKey( self::TOOL, $tools );
		$this->assertTrue( $tools[ self::TOOL ]['annotations']['readOnlyHint'] );

		$result = $this->tool( self::SCOPED_ROUTE, self::TOOL, [ 'ids' => [ $id ] ] );
		$this->assertFalse( $result['isError'], wp_json_encode( $result ) );
		$item = $result['structuredContent']['items'][0];
		$this->assertTrue( $item['used'] );
		$this->assertSame( [ $post, 0 ], wp_list_pluck( $item['usages'], 'post_id' ) );

		$none = $this->tool( self::SCOPED_ROUTE, self::TOOL, [ 'ids' => [ $this->attachment( 'Unused' ) ] ] );
		$this->assertFalse( $none['isError'], wp_json_encode( $none ) );
		$this->assertFalse( $none['structuredContent']['items'][0]['used'] );

		$bad = $this->tool( self::SCOPED_ROUTE, self::TOOL, [ 'ids' => [ $id ], 'x' => 1 ] );
		$this->assertTrue( $bad['isError'] );
	}

	/**
	 * Not discoverable or runnable through the default gateway.
	 */
	public function test_not_on_default_gateway() {
		$this->connect( self::DEFAULT_ROUTE );
		$id = $this->attachment();

		$discovered = $this->tool( self::DEFAULT_ROUTE, 'mcp-adapter-discover-abilities', [] );
		$names      = wp_list_pluck( $discovered['structuredContent']['abilities'], 'name' );
		$this->assertContains( 'tsmlt/search-media', $names );
		$this->assertNotContains( self::NAME, $names );

		$run = $this->tool(
			self::DEFAULT_ROUTE,
			'mcp-adapter-execute-ability',
			[
				'ability_name' => self::NAME,
				'parameters'   => (object) [ 'ids' => [ $id ] ],
			]
		);
		$this->assertTrue( $run['isError'], wp_json_encode( $run ) );
	}
}
