<?php
/**
 * Integration tests for tsmlt/find-duplicate-media: permissions, input,
 * duplicate scan states, grouping, ordering and pagination, what is left
 * out (repeated rows, deleted and trashed attachments), hidden and
 * truncated items, parents, usage consistent with tsmlt/get-media-usage, a
 * fixed number of queries per page, no side effects, and MCP exposure on
 * the dedicated endpoint only.
 *
 * Duplicate scan results are written directly to the duplicate table (as a
 * scan would); every attachment is a generated fixture. Outgoing HTTP is
 * blocked.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\FindDuplicateMedia;
use TinySolutions\mlt\Abilities\Media\GetMediaUsage;
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Modules\Duplicate\DuplicateScanner;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

require_once dirname( __DIR__ ) . '/Mcp/DefaultGatewayClientTrait.php';

/**
 * FindDuplicateMediaAbilityTest
 */
class FindDuplicateMediaAbilityTest extends WP_UnitTestCase {

	use DefaultGatewayClientTrait;

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/find-duplicate-media';

	/**
	 * Dedicated MCP route.
	 *
	 * @var string
	 */
	const SCOPED_ROUTE = '/media-library-tools/mcp';

	/**
	 * Post IDs denied read_post.
	 *
	 * @var int[]
	 */
	private $denied = [];

	/**
	 * Calls of observed hooks, by hook.
	 *
	 * @var array<string, int>
	 */
	private $calls = [];

	/**
	 * Statements other than reads sent while observing.
	 *
	 * @var string[]
	 */
	private $writes = [];

	/**
	 * Whether the batch read of postmeta fails.
	 *
	 * @var bool
	 */
	private $fail_meta = false;

	/**
	 * Whether the usage scan status was changed during a read.
	 *
	 * @var bool
	 */
	private $changed = false;

	/**
	 * MCP sessions by route.
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
	 * Administrator; no duplicate results, duplicate scan state or usage scan.
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( self::NAME ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}tsmlt_duplicate_file" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test fixture.
		delete_option( DuplicateScanner::STATE_OPTION );
		delete_option( UsageScanStatus::OPTION );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10, 4 );
		$this->denied  = [];
		$this->calls   = [];
		$this->writes  = [];
		$this->changed = false;
	}

	/**
	 * Remove filters.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		remove_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10 );
		remove_filter( 'query', [ $this, 'record_query' ] );
		remove_filter( 'query', [ $this, 'break_meta_read' ] );
		remove_filter( 'get_post_metadata', [ $this, 'change_scan_during_read' ], 10 );
		foreach ( self::observed_hooks() as $hook ) {
			remove_filter( $hook, [ $this, 'count_call' ] );
		}
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
	 * Deny read_post on the posts in $this->denied.
	 *
	 * @param string[] $caps    Capabilities.
	 * @param string   $cap     Capability checked.
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
	 * Count a call of an observed hook.
	 *
	 * @param mixed $value Filtered value, returned unchanged.
	 *
	 * @return mixed
	 */
	public function count_call( $value = null ) {
		$this->calls[ current_filter() ] = ( $this->calls[ current_filter() ] ?? 0 ) + 1;
		return $value;
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
	 * Make the batch read of post meta (update_meta_cache()) fail.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function break_meta_read( $query ) {
		global $wpdb;
		if ( $this->fail_meta && false !== strpos( $query, "FROM {$wpdb->postmeta} WHERE post_id IN" ) ) {
			return 'SELECT * FROM tsmlt_no_such_table';
		}
		return $query;
	}

	/**
	 * Start another usage scan while the usage records are read.
	 *
	 * @param mixed  $value    Value.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 *
	 * @return mixed
	 */
	public function change_scan_during_read( $value, $post_id, $meta_key ) {
		if ( ! $this->changed && UsedWhereScanner::META_KEY === $meta_key ) {
			$this->changed = true;
			$status        = get_option( UsageScanStatus::OPTION );
			update_option( UsageScanStatus::OPTION, array_merge( $status, [ 'scan_id' => wp_generate_uuid4() ] ), false );
		}
		return $value;
	}

	/**
	 * Hooks that would show a write, a schedule or a file read.
	 *
	 * @return string[]
	 */
	private static function observed_hooks(): array {
		return [ 'add_option', 'update_option', 'delete_option', 'add_post_meta', 'update_post_meta', 'delete_post_meta', 'setted_transient', 'pre_schedule_event', 'pre_unschedule_hook', 'pre_clear_scheduled_hook', 'get_attached_file', 'wp_insert_post_data' ];
	}

	/**
	 * A generated attachment.
	 *
	 * @param array $args Post arguments.
	 *
	 * @return int
	 */
	private function attachment( array $args = [] ): int {
		static $n = 0;
		++$n;
		return (int) self::factory()->attachment->create(
			array_merge(
				[
					'file'           => 'dup/image-' . $n . '.jpg',
					'post_mime_type' => 'image/jpeg',
					'post_date'      => '2020-01-01 00:00:00',
				],
				$args
			)
		);
	}

	/**
	 * Duplicate scan rows: one per attachment ID, all with this hash.
	 *
	 * @param string $hash Hash (any 32 characters).
	 * @param int[]  $ids  Attachment IDs (repeat one to repeat its row).
	 * @param int    $size File size.
	 */
	private static function rows( string $hash, array $ids, int $size = 1000 ) {
		global $wpdb;
		foreach ( $ids as $id ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test fixture.
				$wpdb->prefix . 'tsmlt_duplicate_file',
				[
					'attachment_id' => $id,
					'file_hash'     => str_pad( $hash, 32, '0' ),
					'file_size'     => $size,
					'file_path'     => '2026/11/secret-path-' . $id . '.jpg',
				]
			);
		}
	}

	/**
	 * A duplicate group of new attachments.
	 *
	 * @param string $hash  Hash.
	 * @param int    $count Attachments.
	 *
	 * @return int[]
	 */
	private function group( string $hash, int $count ): array {
		$ids = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$ids[] = $this->attachment();
		}
		self::rows( $hash, $ids );
		return $ids;
	}

	/**
	 * A verified, complete usage scan that covered every fixture.
	 */
	private static function verified_usage_scan() {
		update_option(
			UsageScanStatus::OPTION,
			[
				'state'            => 'complete',
				'complete'         => true,
				'processed'        => 1,
				'total'            => 1,
				'scan_id'          => wp_generate_uuid4(),
				'started_at'       => '2099-01-01 00:00:00',
				'coverage_version' => UsedWhereScanner::COVERAGE_VERSION,
				'coverage_gaps'    => [],
			],
			false
		);
	}

	/**
	 * Run the ability through the Abilities API.
	 *
	 * @param array|null $input Input.
	 *
	 * @return mixed
	 */
	private static function call( $input = null ) {
		return wp_get_ability( self::NAME )->execute( $input );
	}

	/**
	 * Attachment IDs listed per group.
	 *
	 * @param array $result Result.
	 *
	 * @return int[][]
	 */
	private static function listed( array $result ): array {
		return array_map(
			static function ( $group ) {
				return array_column( $group['items'], 'id' );
			},
			$result['groups']
		);
	}

	/**
	 * Only users with manage_options: refused through the permission
	 * callback and by a direct call, logged out or not.
	 */
	public function test_requires_manage_options() {
		$this->group( 'a', 2 );
		foreach ( [ 'editor', 'author' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertSame( 'ability_invalid_permissions', self::call()->get_error_code(), $role );
			$this->assertSame( AbilityGuard::ERROR_FORBIDDEN, FindDuplicateMedia::instance()->execute()->get_error_code(), $role );
		}
		wp_set_current_user( 0 );
		$this->assertSame( 'ability_invalid_permissions', self::call()->get_error_code() );
		$this->assertSame( AbilityGuard::ERROR_UNAUTHENTICATED, FindDuplicateMedia::instance()->execute()->get_error_code() );
	}

	/**
	 * Closed input: unknown keys and out-of-range values are refused,
	 * through the schema and by a direct call; defaults apply.
	 */
	public function test_input_is_closed_and_bounded() {
		foreach ( [ [ 'x' => 1 ], [ 'page' => 0 ], [ 'page' => 10001 ], [ 'page' => PHP_INT_MAX ], [ 'per_page' => 0 ], [ 'per_page' => 21 ] ] as $input ) {
			$this->assertSame( 'ability_invalid_input', self::call( $input )->get_error_code(), wp_json_encode( $input ) );
			$this->assertSame( AbilityGuard::ERROR_INVALID_INPUT, FindDuplicateMedia::instance()->execute( $input )->get_error_code(), wp_json_encode( $input ) );
		}
		// A numeric string passes the schema's integer check; execute() refuses it.
		$this->assertSame( AbilityGuard::ERROR_INVALID_INPUT, self::call( [ 'page' => '2' ] )->get_error_code() );
		$this->assertSame( AbilityGuard::ERROR_INVALID_INPUT, FindDuplicateMedia::instance()->execute( [ 'page' => '2' ] )->get_error_code() );
		$result = self::call();
		$this->assertSame( 1, $result['page'] );
		$this->assertSame( 10, $result['per_page'] );
		$this->assertSame( 20, self::call( [ 'per_page' => 20 ] )['per_page'] );
		$last = self::call(
			[
				'page'     => 10000,
				'per_page' => 20,
			]
		);
		$this->assertSame( [], $last['groups'], 'The highest page is accepted.' );
		$this->assertFalse( $last['has_more'] );
	}

	/**
	 * No duplicate scan yet: idle, nothing found.
	 */
	public function test_no_scan() {
		$result = self::call();

		$this->assertSame(
			[
				'state'      => 'idle',
				'processed'  => 0,
				'total'      => 0,
				'started_at' => '',
				'updated_at' => '',
				'complete'   => false,
			],
			$result['scan']
		);
		$this->assertSame( [], $result['groups'] );
		$this->assertFalse( $result['has_more'] );
	}

	/**
	 * The duplicate scan's state: complete only when done; running and
	 * cancelled results are listed as found so far.
	 */
	public function test_scan_states() {
		$this->group( 'a', 2 );
		foreach ( [ 'running' => false, 'cancelled' => false, 'done' => true, 'unexpected' => false ] as $state => $complete ) {
			update_option(
				DuplicateScanner::STATE_OPTION,
				[
					'status'     => $state,
					'offset'     => 7,
					'total'      => 9,
					'started_at' => 1767225600,
					'updated_at' => 1767225660,
				],
				false
			);

			$scan = self::call()['scan'];

			$this->assertSame( 'unexpected' === $state ? 'idle' : $state, $scan['state'] );
			$this->assertSame( $complete, $scan['complete'], $state );
			$this->assertSame( 7, $scan['processed'] );
			$this->assertSame( 9, $scan['total'] );
			$this->assertSame( '2026-01-01T00:00:00+00:00', $scan['started_at'] );
			$this->assertSame( '2026-01-01T00:01:00+00:00', $scan['updated_at'] );
			$this->assertCount( 1, self::call()['groups'], $state );
		}
	}

	/**
	 * Largest groups first, then by hash; attachments in ID order; the file
	 * size is the group's.
	 */
	public function test_groups_are_ordered() {
		$b = $this->group( 'b', 2 );
		$c = $this->group( 'c', 3 );
		$a = $this->group( 'a', 2 );

		$result = self::call();

		$this->assertSame( [ $c, $a, $b ], self::listed( $result ) );
		$this->assertSame( [ 3, 2, 2 ], array_column( $result['groups'], 'item_count' ) );
		$this->assertSame( 1000, $result['groups'][0]['file_size'] );
	}

	/**
	 * Pages do not overlap, are the same when asked again, and has_more is
	 * true until the last one.
	 */
	public function test_pagination() {
		foreach ( [ 'a', 'b', 'c', 'd', 'e' ] as $hash ) {
			$this->group( $hash, 2 );
		}

		$pages = [];
		foreach ( [ 1, 2, 3 ] as $page ) {
			$pages[ $page ] = self::call(
				[
					'page'     => $page,
					'per_page' => 2,
				]
			);
		}

		$this->assertSame( [ true, true, false ], array_column( $pages, 'has_more' ) );
		$this->assertSame( [ 2, 2, 1 ], array_map( 'count', array_column( $pages, 'groups' ) ) );
		$all = array_merge( ...array_map( [ self::class, 'listed' ], $pages ) );
		$this->assertCount( 5, array_unique( array_map( 'wp_json_encode', $all ) ), 'No group twice.' );
		$this->assertSame(
			$pages[1],
			self::call(
				[
					'page'     => 1,
					'per_page' => 2,
				]
			),
			'Stable.'
		);
		$this->assertSame(
			[],
			self::call(
				[
					'page'     => 9,
					'per_page' => 2,
				]
			)['groups']
		);
	}

	/**
	 * Repeated rows of one attachment are not a duplicate, and count once.
	 */
	public function test_repeated_rows_of_one_attachment() {
		$single = $this->attachment();
		self::rows( 'a', [ $single, $single, $single ] );
		$x = $this->attachment();
		$y = $this->attachment();
		self::rows( 'b', [ $x, $x, $y ] );

		$result = self::call();

		$this->assertSame( [ [ $x, $y ] ], self::listed( $result ) );
		$this->assertSame( 2, $result['groups'][0]['item_count'] );
	}

	/**
	 * Rows of deleted and trashed attachments are left out (a group that
	 * falls below two members disappears) and stay in the table.
	 */
	public function test_deleted_and_trashed_attachments_are_left_out() {
		global $wpdb;
		$kept    = $this->group( 'a', 2 );
		$deleted = $this->attachment();
		self::rows( 'a', [ $deleted ] );
		wp_delete_post( $deleted, true );
		$alone   = $this->attachment();
		$trashed = $this->attachment( [ 'post_status' => 'trash' ] );
		self::rows( 'b', [ $alone, $trashed ] );
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tsmlt_duplicate_file" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.

		$result = self::call();

		$this->assertSame( [ $kept ], self::listed( $result ) );
		$this->assertSame( 2, $result['groups'][0]['item_count'] );
		$this->assertSame( $before, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tsmlt_duplicate_file" ), 'Stale rows are not purged.' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
	}

	/**
	 * An attachment the user cannot read is counted in hidden and never
	 * listed.
	 */
	public function test_unreadable_attachments_are_hidden() {
		$ids          = $this->group( 'a', 3 );
		$this->denied = [ $ids[1] ];

		$result = self::call();

		$group = $result['groups'][0];
		$this->assertSame( [ $ids[0], $ids[2] ], array_column( $group['items'], 'id' ) );
		$this->assertSame( 1, $group['hidden'] );
		$this->assertSame( 3, $group['item_count'] );
		$this->assertStringNotContainsString( 'image-' . $ids[1], wp_json_encode( $result ) );
	}

	/**
	 * At most 20 attachments are listed per group.
	 */
	public function test_items_are_truncated_at_twenty() {
		$ids          = $this->group( 'a', 23 );
		$this->denied = [ $ids[0] ];

		$group = self::call()['groups'][0];

		$this->assertCount( 20, $group['items'] );
		$this->assertSame( array_slice( $ids, 1, 20 ), array_column( $group['items'], 'id' ) );
		$this->assertTrue( $group['truncated'] );
		$this->assertSame( 23, $group['item_count'] );
		$this->assertSame( 1, $group['hidden'] );
	}

	/**
	 * The parent is returned only when it exists and can be read.
	 */
	public function test_parent_needs_read_access() {
		$readable   = (int) self::factory()->post->create();
		$unreadable = (int) self::factory()->post->create();
		$a          = $this->attachment( [ 'post_parent' => $readable ] );
		$b          = $this->attachment( [ 'post_parent' => $unreadable ] );
		$c          = $this->attachment( [ 'post_parent' => 999999 ] );
		$d          = $this->attachment();
		self::rows( 'a', [ $a, $b, $c, $d ] );
		$this->denied = [ $unreadable ];

		$items = array_column( self::call()['groups'][0]['items'], 'parent_id', 'id' );

		$this->assertSame(
			[
				$a => $readable,
				$b => null,
				$c => null,
				$d => null,
			],
			$items
		);
	}

	/**
	 * Items are MediaPresenter summaries plus parent and usage only: no file
	 * path, hash or raw metadata.
	 */
	public function test_output_has_no_paths_hashes_or_metadata() {
		$ids = $this->group( 'abc123', 2 );
		update_post_meta( $ids[0], '_wp_attachment_metadata', [ 'file' => 'secret-meta-path.jpg' ] );

		$result = self::call();
		$json   = wp_json_encode( $result );

		$this->assertSame( [ 'id', 'title', 'alt_text', 'caption', 'mime_type', 'url', 'date', 'parent_id', 'usage' ], array_keys( $result['groups'][0]['items'][0] ) );
		$this->assertSame( [ 'file_size', 'item_count', 'hidden', 'truncated', 'items' ], array_keys( $result['groups'][0] ) );
		$this->assertStringNotContainsString( 'secret-path', $json );
		$this->assertStringNotContainsString( 'secret-meta-path', $json );
		$this->assertStringNotContainsString( 'abc123', $json );
		$this->assertStringNotContainsString( 'file_hash', $json );
	}

	/**
	 * Usage matches tsmlt/get-media-usage for every case: a usage recorded,
	 * none (covered by a verified scan), only records of deleted posts, an
	 * upload after the scan started; and with no or an unverified scan.
	 */
	public function test_usage_matches_get_media_usage() {
		$post    = (int) self::factory()->post->create();
		$gone    = (int) self::factory()->post->create();
		$used    = $this->attachment();
		$unused  = $this->attachment();
		$stale   = $this->attachment();
		$sitewide = $this->attachment();
		$late    = $this->attachment( [ 'post_date' => '2100-01-01 00:00:00' ] );
		$entry   = static function ( int $post_id, string $type ): array {
			return [
				'post_id'    => $post_id,
				'post_title' => 'Post',
				'post_type'  => 'post',
				'usage_type' => $type,
			];
		};
		update_post_meta( $used, UsedWhereScanner::META_KEY, [ $entry( $post, 'content' ) ] );
		update_post_meta( $stale, UsedWhereScanner::META_KEY, [ $entry( $gone, 'content' ) ] );
		update_post_meta( $sitewide, UsedWhereScanner::META_KEY, [ $entry( 0, 'site_logo' ) ] );
		wp_delete_post( $gone, true );
		$ids = [ $used, $unused, $stale, $sitewide, $late ];
		self::rows( 'a', $ids );

		foreach ( [ 'none', 'running', 'verified' ] as $scan ) {
			if ( 'running' === $scan ) {
				update_option(
					UsageScanStatus::OPTION,
					[
						'state'            => 'running',
						'scan_id'          => wp_generate_uuid4(),
						'started_at'       => '2099-01-01 00:00:00',
						'last_tick_at'     => current_time( 'mysql' ),
						'coverage_version' => UsedWhereScanner::COVERAGE_VERSION,
						'coverage_gaps'    => [],
					],
					false
				);
			} elseif ( 'verified' === $scan ) {
				self::verified_usage_scan();
			}

			$usage    = array_column( self::call()['groups'][0]['items'], 'usage', 'id' );
			$expected = [];
			foreach ( wp_get_ability( 'tsmlt/get-media-usage' )->execute( [ 'ids' => $ids ] )['items'] as $item ) {
				$expected[ $item['id'] ] = true === $item['used'] ? 'used' : ( false === $item['used'] ? 'unused' : 'unknown' );
			}
			ksort( $usage );
			ksort( $expected );
			$this->assertSame( $expected, $usage, $scan );
		}
		$this->assertSame( 'used', $usage[ $used ] );
		$this->assertSame( 'unused', $usage[ $unused ] );
		$this->assertSame( 'unused', $usage[ $stale ], 'A record of a deleted post is not a usage.' );
		$this->assertSame( 'used', $usage[ $sitewide ] );
		$this->assertSame( 'unknown', $usage[ $late ], 'Uploaded after the scan started.' );
	}

	/**
	 * A usage scan that changes while the records are read makes "no usage"
	 * unknown.
	 */
	public function test_usage_scan_changing_during_the_read_is_unknown() {
		$ids = $this->group( 'a', 2 );
		self::verified_usage_scan();
		$this->assertSame( [ 'unused', 'unused' ], array_column( self::call()['groups'][0]['items'], 'usage' ), 'Precondition.' );
		add_filter( 'get_post_metadata', [ $this, 'change_scan_during_read' ], 10, 3 );
		wp_cache_flush();

		$usage = array_column( self::call()['groups'][0]['items'], 'usage', 'id' );

		$this->assertTrue( $this->changed, 'Precondition: the scan changed during the read.' );
		$this->assertSame(
			[
				$ids[0] => 'unknown',
				$ids[1] => 'unknown',
			],
			$usage
		);
	}

	/**
	 * Nothing is written, scheduled or read from files: no write statement,
	 * option, meta, transient or cron change, and the duplicate table and
	 * scan state stay as they were.
	 */
	public function test_read_only() {
		global $wpdb;
		$this->group( 'a', 3 );
		$deleted = $this->attachment();
		self::rows( 'a', [ $deleted ] );
		wp_delete_post( $deleted, true );
		self::verified_usage_scan();
		update_option( DuplicateScanner::STATE_OPTION, [ 'status' => 'done' ], false );
		$table = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tsmlt_duplicate_file ORDER BY id", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
		$state = get_option( DuplicateScanner::STATE_OPTION );
		$cron  = _get_cron_array();
		foreach ( self::observed_hooks() as $hook ) {
			add_filter( $hook, [ $this, 'count_call' ] );
		}
		add_filter( 'query', [ $this, 'record_query' ] );

		self::call();
		self::call(
			[
				'page'     => 2,
				'per_page' => 1,
			]
		);

		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertSame( [], $this->writes );
		$this->assertSame( [], $this->calls );
		$this->assertSame( $table, $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tsmlt_duplicate_file ORDER BY id", ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
		$this->assertSame( $state, get_option( DuplicateScanner::STATE_OPTION ) );
		$this->assertSame( $cron, _get_cron_array() );
	}

	/**
	 * A failed read of the usage records fails the call (never "unused" for
	 * used media), and leaves no empty records cached behind.
	 */
	public function test_failed_usage_read_fails_closed() {
		global $wpdb;
		$post = (int) self::factory()->post->create();
		$ids  = $this->group( 'a', 2 );
		foreach ( $ids as $id ) {
			update_post_meta(
				$id,
				UsedWhereScanner::META_KEY,
				[
					[
						'post_id'    => $post,
						'post_title' => 'Post',
						'post_type'  => 'post',
						'usage_type' => 'content',
					],
				]
			);
		}
		self::verified_usage_scan();
		$this->assertSame( [ 'used', 'used' ], array_column( self::call()['groups'][0]['items'], 'usage' ), 'Precondition.' );
		add_filter( 'query', [ $this, 'break_meta_read' ] );
		wp_cache_flush();

		$this->fail_meta = true;
		$suppressed      = $wpdb->suppress_errors( true );
		$result          = self::call();
		$wpdb->suppress_errors( $suppressed );
		$this->fail_meta = false;

		$this->assertWPError( $result );
		$this->assertSame( FindDuplicateMedia::ERROR_READ_FAILED, $result->get_error_code() );
		$this->assertStringNotContainsString( 'unused', wp_json_encode( $result ) );
		$this->assertNotEmpty( get_post_meta( $ids[0], UsedWhereScanner::META_KEY, true ), 'No empty record left cached.' );
		$this->assertSame( [ 'used', 'used' ], array_column( self::call()['groups'][0]['items'], 'usage' ) );
	}

	/**
	 * Attached media: a page still costs the same number of queries for 2
	 * groups as for 10 (parents are read in one batch, not per member).
	 */
	public function test_parents_do_not_add_queries() {
		global $wpdb;
		for ( $i = 0; $i < 10; $i++ ) {
			$ids = [];
			for ( $j = 0; $j < 3; $j++ ) {
				$ids[] = $this->attachment( [ 'post_parent' => (int) self::factory()->post->create() ] );
			}
			self::rows( 'p' . $i, $ids );
		}
		self::verified_usage_scan();

		$queries = [];
		foreach ( [ 2, 10 ] as $per_page ) {
			wp_cache_flush();
			wp_set_current_user( get_current_user_id() );
			$start  = $wpdb->num_queries;
			$result = self::call( [ 'per_page' => $per_page ] );
			$this->assertCount( $per_page, $result['groups'] );
			$this->assertNotContains( null, array_column( $result['groups'][0]['items'], 'parent_id' ), 'Precondition: attached.' );
			$queries[ $per_page ] = $wpdb->num_queries - $start;
		}

		$this->assertSame( $queries[2], $queries[10], wp_json_encode( $queries ) );
	}

	/**
	 * A page costs the same number of queries for 2 groups as for 10.
	 */
	public function test_queries_do_not_grow_with_the_page() {
		global $wpdb;
		$post = (int) self::factory()->post->create();
		for ( $i = 0; $i < 10; $i++ ) {
			foreach ( $this->group( 'g' . $i, 3 ) as $id ) {
				update_post_meta(
					$id,
					UsedWhereScanner::META_KEY,
					[
						[
							'post_id'    => $post,
							'post_title' => 'Post',
							'post_type'  => 'post',
							'usage_type' => 'content',
						],
					]
				);
			}
		}
		self::verified_usage_scan();

		$queries = [];
		foreach ( [ 2, 10 ] as $per_page ) {
			wp_cache_flush();
			wp_set_current_user( get_current_user_id() );
			$start = $wpdb->num_queries;
			$this->assertCount( $per_page, self::call( [ 'per_page' => $per_page ] )['groups'] );
			$queries[ $per_page ] = $wpdb->num_queries - $start;
		}

		$this->assertSame( $queries[2], $queries[10], wp_json_encode( $queries ) );
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
	 * Open a session on the dedicated server.
	 */
	private function connect() {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}
		$this->send(
			self::SCOPED_ROUTE,
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
		$this->send( self::SCOPED_ROUTE, 'notifications/initialized' );
	}

	/**
	 * The dedicated endpoint lists the tool (read-only, closed input) and
	 * returns its result through the adapter's output check.
	 */
	public function test_dedicated_endpoint() {
		$ids = $this->group( 'a', 2 );
		$this->connect();

		$tools = array_column( $this->send( self::SCOPED_ROUTE, 'tools/list' )['result']['tools'], null, 'name' );
		$this->assertArrayHasKey( 'tsmlt-find-duplicate-media', $tools );
		$this->assertTrue( $tools['tsmlt-find-duplicate-media']['annotations']['readOnlyHint'] );
		$this->assertFalse( $tools['tsmlt-find-duplicate-media']['annotations']['destructiveHint'] );
		$this->assertFalse( $tools['tsmlt-find-duplicate-media']['inputSchema']['additionalProperties'] );

		$result = $this->send(
			self::SCOPED_ROUTE,
			'tools/call',
			[
				'name'      => 'tsmlt-find-duplicate-media',
				'arguments' => (object) [],
			]
		)['result'];
		$this->assertFalse( $result['isError'], wp_json_encode( $result ) );
		$this->assertSame( $ids, array_column( $result['structuredContent']['groups'][0]['items'], 'id' ) );
	}

	/**
	 * The default gateway neither lists, describes nor runs it.
	 */
	public function test_not_on_the_default_gateway() {
		$this->gw_require_adapter();
		$this->group( 'a', 2 );
		$this->gw_connect_as( 'administrator' );

		$listed = array_column( $this->gw_call( 'mcp-adapter-discover-abilities', [] )['structuredContent']['abilities'], 'name' );
		$info   = $this->gw_call( 'mcp-adapter-get-ability-info', [ 'ability_name' => self::NAME ] );
		$run    = $this->gw_call( 'mcp-adapter-execute-ability', self::gw_execute_args( self::NAME, [] ) );

		$this->assertNotContains( self::NAME, $listed );
		$this->assertTrue( $info['isError'] );
		$this->assertTrue( $run['isError'] );
		$this->assertStringNotContainsString( '"groups"', wp_json_encode( $run ) );
	}
}
