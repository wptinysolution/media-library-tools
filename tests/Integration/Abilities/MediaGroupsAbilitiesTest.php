<?php
/**
 * Media groups over MCP: tsmlt/list-media-groups, the group_id filter of
 * tsmlt/search-media, and tsmlt/set-media-groups.
 *
 * Executed through WP_Ability::execute(), the path the MCP Adapter uses; the
 * MCP tests send real JSON-RPC messages in-process (skipped unless
 * MCP_ADAPTER_DIR is set). Groups and attachments are generated fixtures.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\SetMediaGroups;
use TinySolutions\mlt\Helpers\Fns;

/**
 * MediaGroupsAbilitiesTest
 */
class MediaGroupsAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Ability names.
	 */
	const LIST   = 'tsmlt/list-media-groups';
	const SEARCH = 'tsmlt/search-media';
	const SET    = 'tsmlt/set-media-groups';

	/**
	 * Dedicated MCP server route.
	 */
	const SCOPED_ROUTE = '/media-library-tools/mcp';

	/**
	 * Group IDs by key.
	 *
	 * @var array<string, int>
	 */
	private $groups = [];

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
	 * Administrator; groups: Travel (with child Beach), two "Products"
	 * groups under different parents, an "A & B" group, and Empty.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->sessions = [];

		$make = function ( string $name, int $parent = 0 ): int {
			return (int) self::factory()->term->create(
				[
					'taxonomy' => Fns::CATEGORY,
					'name'     => $name,
					'parent'   => $parent,
				]
			);
		};

		$this->groups['travel']    = $make( 'Travel' );
		$this->groups['beach']     = $make( 'Beach', $this->groups['travel'] );
		$this->groups['shop']      = $make( 'Shop' );
		$this->groups['products1'] = $make( 'Products', $this->groups['shop'] );
		$this->groups['products2'] = $make( 'Products', $this->groups['travel'] );
		$this->groups['ab']        = $make( 'A & B' );
		$this->groups['empty']     = $make( 'Empty' );
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * Execute an ability as the current user.
	 *
	 * @param string $name  Ability name.
	 * @param array  $input Input.
	 *
	 * @return mixed
	 */
	private function call_ability( string $name, array $input = [] ) {
		$ability = wp_get_ability( $name );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		return $ability->execute( $input );
	}

	/**
	 * Assert a result is an array that matches the ability's output schema.
	 *
	 * @param string $name   Ability name.
	 * @param mixed  $result Result.
	 */
	private function assert_valid_output( string $name, $result ) {
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : '' );
		$this->assertTrue( rest_validate_value_from_schema( $result, wp_get_ability( $name )->get_output_schema(), 'output' ) );
	}

	/**
	 * Create an image attachment.
	 *
	 * @param string $title Title.
	 * @param array  $args  Extra post arguments.
	 *
	 * @return int
	 */
	private function attachment( string $title = 'Fixture', array $args = [] ): int {
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
	 * An attachment's group IDs, sorted.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return int[]
	 */
	private static function group_ids( int $id ): array {
		clean_object_term_cache( $id, 'attachment' );
		$ids = array_map( 'intval', wp_get_object_terms( $id, Fns::CATEGORY, [ 'fields' => 'ids' ] ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * Group IDs by key, sorted.
	 *
	 * @param string ...$keys Keys.
	 *
	 * @return int[]
	 */
	private function ids( string ...$keys ): array {
		$ids = array_map(
			function ( $key ) {
				return $this->groups[ $key ];
			},
			$keys
		);
		sort( $ids );
		return $ids;
	}

	// ---------------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------------

	/**
	 * Both abilities are registered in the plugin category, dedicated server
	 * only, with closed input schemas and the right annotations.
	 */
	public function test_registration_and_schemas() {
		foreach ( [ self::LIST, self::SET ] as $name ) {
			$ability = wp_get_ability( $name );
			$this->assertInstanceOf( WP_Ability::class, $ability, $name );
			$this->assertSame( AbilitiesInit::CATEGORY, $ability->get_category() );
			$this->assertFalse( $ability->get_meta()['mcp']['public'], $name );
			$this->assertFalse( $ability->get_input_schema()['additionalProperties'] );
			$this->assertFalse( $ability->get_output_schema()['additionalProperties'] );
		}

		$list = wp_get_ability( self::LIST );
		$this->assertTrue( $list->get_meta()['annotations']['readonly'] );
		$this->assertSame( [ 'search', 'id', 'page', 'per_page' ], array_keys( $list->get_input_schema()['properties'] ) );

		$set = wp_get_ability( self::SET );
		$this->assertFalse( $set->get_meta()['annotations']['readonly'] );
		$this->assertSame( [ 'id', 'group_ids', 'mode' ], $set->get_input_schema()['required'] );
		$this->assertSame( [ 'add', 'remove', 'replace' ], $set->get_input_schema()['properties']['mode']['enum'] );

		$this->assertArrayHasKey( 'group_id', wp_get_ability( self::SEARCH )->get_input_schema()['properties'] );
		$this->assertTrue( wp_get_ability( self::SEARCH )->get_meta()['mcp']['public'], 'search-media stays public.' );
	}

	// ---------------------------------------------------------------------
	// tsmlt/list-media-groups
	// ---------------------------------------------------------------------

	/**
	 * Every group, by name then ID, with id, name, parent and count.
	 */
	public function test_lists_all_groups() {
		wp_set_object_terms( $this->attachment(), [ $this->groups['travel'] ], Fns::CATEGORY );

		$result = $this->call_ability( self::LIST );

		$this->assert_valid_output( self::LIST, $result );
		$this->assertSame( 7, $result['total'] );
		$this->assertSame( 1, $result['total_pages'] );
		$this->assertSame( [ 'A & B', 'Beach', 'Empty', 'Products', 'Products', 'Shop', 'Travel' ], wp_list_pluck( $result['groups'], 'name' ) );
		$beach = $result['groups'][1];
		$this->assertSame(
			[
				'id'     => $this->groups['beach'],
				'name'   => 'Beach',
				'parent' => $this->groups['travel'],
				'count'  => 0,
			],
			$beach
		);
		$this->assertSame( 1, $result['groups'][6]['count'] );
	}

	/**
	 * Search matches part of the name, case-insensitively, including names
	 * WordPress stores with entities.
	 */
	public function test_search_by_name() {
		$this->assertSame( [ 'Beach' ], wp_list_pluck( $this->call_ability( self::LIST, [ 'search' => 'eac' ] )['groups'], 'name' ) );
		$this->assertSame( [ 'Travel' ], wp_list_pluck( $this->call_ability( self::LIST, [ 'search' => 'TRAV' ] )['groups'], 'name' ) );
		$this->assertSame( [ 'A & B' ], wp_list_pluck( $this->call_ability( self::LIST, [ 'search' => 'A & B' ] )['groups'], 'name' ) );
		$this->assertSame( 0, $this->call_ability( self::LIST, [ 'search' => 'nothing-like-this' ] )['total'] );
	}

	/**
	 * Duplicate names are all returned, told apart by ID and parent.
	 */
	public function test_duplicate_names_are_all_returned() {
		$result = $this->call_ability( self::LIST, [ 'search' => 'Products' ] );

		$this->assertSame( 2, $result['total'] );
		$this->assertSame( $this->ids( 'products1', 'products2' ), wp_list_pluck( $result['groups'], 'id' ) );
		$this->assertEqualSets( [ $this->groups['shop'], $this->groups['travel'] ], wp_list_pluck( $result['groups'], 'parent' ) );
	}

	/**
	 * An exact ID returns that group; with a search, the group must match
	 * both; unknown IDs and other taxonomies' terms return nothing.
	 */
	public function test_filter_by_id_and_combination() {
		$this->assertSame( [ $this->groups['beach'] ], wp_list_pluck( $this->call_ability( self::LIST, [ 'id' => $this->groups['beach'] ] )['groups'], 'id' ) );

		$both = $this->call_ability(
			self::LIST,
			[
				'id'     => $this->groups['beach'],
				'search' => 'bea',
			]
		);
		$this->assertSame( [ $this->groups['beach'] ], wp_list_pluck( $both['groups'], 'id' ) );

		$mismatch = $this->call_ability(
			self::LIST,
			[
				'id'     => $this->groups['beach'],
				'search' => 'Travel',
			]
		);
		$this->assertSame( 0, $mismatch['total'] );

		$tag = (int) self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] );
		$this->assertSame( 0, $this->call_ability( self::LIST, [ 'id' => $tag ] )['total'] );
		$this->assertSame( 0, $this->call_ability( self::LIST, [ 'id' => 999999 ] )['total'] );
	}

	/**
	 * Pages are stable and bounded.
	 */
	public function test_pagination() {
		$first  = $this->call_ability(
			self::LIST,
			[
				'per_page' => 3,
				'page'     => 1,
			]
		);
		$last   = $this->call_ability(
			self::LIST,
			[
				'per_page' => 3,
				'page'     => 3,
			]
		);
		$beyond = $this->call_ability(
			self::LIST,
			[
				'per_page' => 3,
				'page'     => 4,
			]
		);

		$this->assertSame( 3, $first['total_pages'] );
		$this->assertSame( [ 'A & B', 'Beach', 'Empty' ], wp_list_pluck( $first['groups'], 'name' ) );
		$this->assertSame( [ 'Travel' ], wp_list_pluck( $last['groups'], 'name' ) );
		$this->assertSame( [], $beyond['groups'] );
		$this->assertSame( 7, $beyond['total'] );
	}

	/**
	 * Malformed input is refused by the schema.
	 *
	 * @dataProvider data_invalid_list_input
	 *
	 * @param array $input Input.
	 */
	public function test_invalid_list_input( array $input ) {
		$result = $this->call_ability( self::LIST, $input );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Invalid list inputs.
	 *
	 * @return array
	 */
	public function data_invalid_list_input(): array {
		return [
			'per_page 0'       => [ [ 'per_page' => 0 ] ],
			'per_page 101'     => [ [ 'per_page' => 101 ] ],
			'page 0'           => [ [ 'page' => 0 ] ],
			'id 0'             => [ [ 'id' => 0 ] ],
			'id as name'       => [ [ 'id' => 'Travel' ] ],
			'search too long'  => [ [ 'search' => str_repeat( 'x', 201 ) ] ],
			'unknown property' => [ [ 'taxonomy' => 'category' ] ],
		];
	}

	// ---------------------------------------------------------------------
	// tsmlt/search-media group_id
	// ---------------------------------------------------------------------

	/**
	 * group_id returns media assigned directly to the group, not to its
	 * subgroups, combined with the other filters and pagination; without it
	 * nothing changes.
	 */
	public function test_search_media_by_group() {
		$a     = $this->attachment( 'Groupfix one' );
		$b     = $this->attachment( 'Groupfix two' );
		$child = $this->attachment( 'Groupfix child' );
		$other = $this->attachment( 'Groupfix other' );
		$pdf   = $this->attachment( 'Groupfix pdf', [ 'post_mime_type' => 'application/pdf' ] );
		wp_set_object_terms( $a, [ $this->groups['travel'] ], Fns::CATEGORY );
		wp_set_object_terms( $b, [ $this->groups['travel'] ], Fns::CATEGORY );
		wp_set_object_terms( $pdf, [ $this->groups['travel'] ], Fns::CATEGORY );
		wp_set_object_terms( $child, [ $this->groups['beach'] ], Fns::CATEGORY );
		wp_set_object_terms( $other, [ $this->groups['shop'] ], Fns::CATEGORY );

		$all = $this->call_ability( self::SEARCH, [ 'search' => 'Groupfix' ] );
		$this->assert_valid_output( self::SEARCH, $all );
		$this->assertSame( 5, $all['total'], 'Without group_id the search is unchanged.' );

		$travel = $this->call_ability(
			self::SEARCH,
			[
				'search'   => 'Groupfix',
				'group_id' => $this->groups['travel'],
			]
		);
		$this->assert_valid_output( self::SEARCH, $travel );
		$this->assertEqualSets( [ $a, $b, $pdf ], wp_list_pluck( $travel['items'], 'id' ) );

		$images = $this->call_ability(
			self::SEARCH,
			[
				'group_id'  => $this->groups['travel'],
				'mime_type' => 'image',
				'per_page'  => 1,
				'page'      => 2,
				'orderby'   => 'id',
				'order'     => 'ASC',
			]
		);
		$this->assertSame( 2, $images['total'] );
		$this->assertSame( 2, $images['total_pages'] );
		$this->assertSame( [ max( $a, $b ) ], wp_list_pluck( $images['items'], 'id' ) );

		$empty = $this->call_ability( self::SEARCH, [ 'group_id' => $this->groups['empty'] ] );
		$this->assertSame( 0, $empty['total'] );
	}

	/**
	 * Unknown IDs and other taxonomies' terms are refused with a clear
	 * error; a name is never taken as an ID.
	 */
	public function test_search_media_rejects_invalid_groups() {
		$tag = (int) self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] );

		foreach ( [ 999999, $tag ] as $group_id ) {
			$result = $this->call_ability( self::SEARCH, [ 'group_id' => $group_id ] );
			$this->assertWPError( $result );
			$this->assertSame( AbilityGuard::ERROR_GROUP_NOT_FOUND, $result->get_error_code() );
			$this->assertStringContainsString( (string) $group_id, $result->get_error_message() );
		}

		$by_name = $this->call_ability( self::SEARCH, [ 'group_id' => 'Travel' ] );
		$this->assertWPError( $by_name );
		$this->assertSame( 'ability_invalid_input', $by_name->get_error_code() );
	}

	// ---------------------------------------------------------------------
	// tsmlt/set-media-groups
	// ---------------------------------------------------------------------

	/**
	 * Run set-media-groups.
	 *
	 * @param int    $id        Attachment ID.
	 * @param array  $group_ids Group IDs.
	 * @param string $mode      Mode.
	 *
	 * @return mixed
	 */
	private function set_groups( int $id, array $group_ids, string $mode ) {
		return $this->call_ability(
			self::SET,
			[
				'id'        => $id,
				'group_ids' => $group_ids,
				'mode'      => $mode,
			]
		);
	}

	/**
	 * Each mode, including empty lists and no-ops.
	 *
	 * @dataProvider data_modes
	 *
	 * @param string[] $start     Starting groups.
	 * @param string[] $requested Requested groups.
	 * @param string   $mode      Mode.
	 * @param string[] $expected  Expected groups.
	 */
	public function test_modes( array $start, array $requested, string $mode, array $expected ) {
		$id = $this->attachment();
		wp_set_object_terms( $id, $this->ids( ...$start ), Fns::CATEGORY );

		$result = $this->set_groups( $id, $this->ids( ...$requested ), $mode );

		$this->assert_valid_output( self::SET, $result );
		$this->assertSame( $id, $result['id'] );
		$this->assertSame( $mode, $result['mode'] );
		$this->assertEqualSets( $this->ids( ...$start ), wp_list_pluck( $result['groups_before'], 'id' ) );
		$this->assertEqualSets( $this->ids( ...$expected ), wp_list_pluck( $result['groups_after'], 'id' ) );
		$this->assertSame( $this->ids( ...$start ) !== $this->ids( ...$expected ), $result['changed'] );
		$this->assertSame( $this->ids( ...$expected ), self::group_ids( $id ), 'Stored relationships match.' );
	}

	/**
	 * Modes.
	 *
	 * @return array
	 */
	public function data_modes(): array {
		return [
			'add'                      => [ [ 'travel' ], [ 'shop' ], 'add', [ 'travel', 'shop' ] ],
			'add already assigned'     => [ [ 'travel' ], [ 'travel' ], 'add', [ 'travel' ] ],
			'add empty list'           => [ [ 'travel' ], [], 'add', [ 'travel' ] ],
			'remove'                   => [ [ 'travel', 'shop' ], [ 'travel' ], 'remove', [ 'shop' ] ],
			'remove not assigned'      => [ [ 'travel' ], [ 'shop' ], 'remove', [ 'travel' ] ],
			'remove empty list'        => [ [ 'travel', 'shop' ], [], 'remove', [ 'travel', 'shop' ] ],
			'replace'                  => [ [ 'travel', 'shop' ], [ 'beach' ], 'replace', [ 'beach' ] ],
			'replace with same'        => [ [ 'travel' ], [ 'travel' ], 'replace', [ 'travel' ] ],
			'replace empty clears all' => [ [ 'travel', 'shop' ], [], 'replace', [] ],
			'add one duplicate name'   => [ [], [ 'products2' ], 'add', [ 'products2' ] ],
		];
	}

	/**
	 * Only this taxonomy's relationships change: the attachment's fields,
	 * metadata, other terms and modified date are untouched.
	 */
	public function test_nothing_else_changes() {
		register_taxonomy_for_object_type( 'post_tag', 'attachment' );
		$id  = $this->attachment( 'Untouched' );
		$tag = (int) self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] );
		wp_set_object_terms( $id, [ $tag ], 'post_tag' );
		update_post_meta( $id, '_wp_attachment_image_alt', 'Alt stays' );
		$post_before = get_post( $id, ARRAY_A );
		$meta_before = get_post_meta( $id );

		$result = $this->set_groups( $id, [ $this->groups['shop'] ], 'replace' );

		$this->assertTrue( $result['changed'] );
		clean_post_cache( $id );
		$this->assertSame( $post_before, get_post( $id, ARRAY_A ) );
		$this->assertSame( $meta_before, get_post_meta( $id ) );
		$this->assertSame( [ $tag ], array_map( 'intval', wp_get_object_terms( $id, 'post_tag', [ 'fields' => 'ids' ] ) ) );
		unregister_taxonomy_for_object_type( 'post_tag', 'attachment' );
	}

	/**
	 * Unknown IDs or other taxonomies' terms anywhere in the list: refused,
	 * nothing applied — not even the valid IDs.
	 *
	 * @dataProvider data_invalid_group_lists
	 *
	 * @param string $kind Which invalid ID to add.
	 */
	public function test_invalid_group_ids_change_nothing( string $kind ) {
		$id      = $this->attachment();
		$invalid = 'tag' === $kind ? (int) self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] ) : 999999;
		wp_set_object_terms( $id, [ $this->groups['travel'] ], Fns::CATEGORY );

		foreach ( [ 'add', 'replace', 'remove' ] as $mode ) {
			$result = $this->set_groups( $id, [ $this->groups['shop'], $invalid ], $mode );
			$this->assertWPError( $result, $mode );
			$this->assertSame( AbilityGuard::ERROR_GROUP_NOT_FOUND, $result->get_error_code() );
			$this->assertSame( [ $this->groups['travel'] ], self::group_ids( $id ), $mode );
		}
	}

	/**
	 * Invalid ID kinds.
	 *
	 * @return array
	 */
	public function data_invalid_group_lists(): array {
		return [
			'unknown ID'          => [ 'unknown' ],
			'other taxonomy term' => [ 'tag' ],
		];
	}

	/**
	 * Malformed input is refused by the schema, and by the execute
	 * callback when it is reached directly.
	 *
	 * @dataProvider data_malformed_set_input
	 *
	 * @param array $input Input (attachment added).
	 */
	public function test_malformed_set_input( array $input ) {
		$id    = $this->attachment();
		$input = array_merge( [ 'id' => $id ], $input );

		$result = $this->call_ability( self::SET, $input );
		$direct = SetMediaGroups::instance()->execute( $input );

		// Core's schema check refuses most of these; a bare number passes it as a
		// one-item list (rest_is_array()) and the ability refuses it instead.
		$this->assertWPError( $result );
		$this->assertContains( $result->get_error_code(), [ 'ability_invalid_input', AbilityGuard::ERROR_INVALID_INPUT ] );
		$this->assertWPError( $direct );
		$this->assertSame( [], self::group_ids( $id ) );
	}

	/**
	 * Malformed inputs (without the ID).
	 *
	 * @return array
	 */
	public function data_malformed_set_input(): array {
		return [
			'missing mode'      => [ [ 'group_ids' => [ 1 ] ] ],
			'invalid mode'      => [
				[
					'group_ids' => [ 1 ],
					'mode'      => 'append',
				],
			],
			'missing group_ids' => [ [ 'mode' => 'add' ] ],
			'names not IDs'     => [
				[
					'group_ids' => [ 'Travel' ],
					'mode'      => 'add',
				],
			],
			'not a list'        => [
				[
					'group_ids' => 5,
					'mode'      => 'add',
				],
			],
			'zero ID'           => [
				[
					'group_ids' => [ 0 ],
					'mode'      => 'add',
				],
			],
		];
	}

	/**
	 * Missing attachments, other post types and trashed attachments are all
	 * "not found".
	 */
	public function test_missing_attachments_are_not_found() {
		$trashed = $this->attachment();
		wp_trash_post( $trashed );
		$post = (int) self::factory()->post->create();

		foreach ( [ 999999, $post, $trashed ] as $id ) {
			$result = $this->set_groups( $id, [ $this->groups['travel'] ], 'add' );
			$this->assertWPError( $result );
			$this->assertSame( AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND, $result->get_error_code(), (string) $id );
		}
		$this->assertSame( [], self::group_ids( $post ) );
	}

	/**
	 * Users without manage_options and logged-out requests are refused.
	 *
	 * @dataProvider data_refused_users
	 *
	 * @param string $role Role, or '' for logged out.
	 */
	public function test_users_without_permission_are_refused( string $role ) {
		$id = $this->attachment();
		wp_set_current_user( '' === $role ? 0 : self::factory()->user->create( [ 'role' => $role ] ) );

		foreach ( [ self::LIST => [], self::SET => [ 'id' => $id, 'group_ids' => [ $this->groups['travel'] ], 'mode' => 'add' ] ] as $name => $input ) {
			$result = $this->call_ability( $name, $input );
			$this->assertWPError( $result, $name );
			$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		}
		$this->assertSame( [], self::group_ids( $id ) );
	}

	/**
	 * Refused roles.
	 *
	 * @return array
	 */
	public function data_refused_users(): array {
		return [
			'editor'     => [ 'editor' ],
			'author'     => [ 'author' ],
			'logged out' => [ '' ],
		];
	}

	/**
	 * An administrator without edit_post on the attachment, or without the
	 * taxonomy's assign capability, is refused.
	 */
	public function test_edit_and_assign_capabilities_are_required() {
		$id   = $this->attachment();
		$deny = static function ( $caps, $cap, $user_id, $args ) use ( $id ) {
			return 'edit_post' === $cap && isset( $args[0] ) && (int) $args[0] === $id ? [ 'do_not_allow' ] : $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 4 );
		$no_edit = $this->set_groups( $id, [ $this->groups['travel'] ], 'add' );
		remove_filter( 'map_meta_cap', $deny, 10 );

		$assign  = get_taxonomy( Fns::CATEGORY )->cap->assign_terms;
		$no_term = static function ( $allcaps ) use ( $assign ) {
			$allcaps[ $assign ] = false;
			return $allcaps;
		};
		add_filter( 'user_has_cap', $no_term );
		$no_assign = $this->set_groups( $id, [ $this->groups['travel'] ], 'add' );
		remove_filter( 'user_has_cap', $no_term );

		$this->assertSame( AbilityGuard::ERROR_FORBIDDEN, $no_edit->get_error_code() );
		$this->assertSame( AbilityGuard::ERROR_FORBIDDEN, $no_assign->get_error_code() );
		$this->assertSame( [], self::group_ids( $id ) );
	}

	/**
	 * When the stored result does not match what was requested, the previous
	 * groups are put back and an error is returned.
	 */
	public function test_unverified_write_is_rolled_back() {
		$id = $this->attachment();
		wp_set_object_terms( $id, [ $this->groups['travel'] ], Fns::CATEGORY );
		// Another plugin adds a group during the write, once.
		$interfere = function ( $object_id ) use ( $id ) {
			static $done = false;
			if ( $object_id === $id && ! $done ) {
				$done = true;
				wp_set_object_terms( $id, [ $this->groups['empty'] ], Fns::CATEGORY, true );
			}
		};
		add_action( 'set_object_terms', $interfere );

		$result = $this->set_groups( $id, [ $this->groups['shop'] ], 'add' );

		remove_action( 'set_object_terms', $interfere );
		$this->assertWPError( $result );
		$this->assertSame( SetMediaGroups::ERROR_UPDATE_FAILED, $result->get_error_code() );
		$this->assertSame( [ $this->groups['travel'] ], self::group_ids( $id ) );
	}

	/**
	 * The media table's bulk edit still assigns groups in each mode through
	 * the shared Api::assign_groups().
	 */
	public function test_bulk_edit_groups_still_work() {
		$a = $this->attachment();
		$b = $this->attachment();
		wp_set_object_terms( $a, [ $this->groups['travel'] ], Fns::CATEGORY );

		$bulk = static function ( array $ids, array $groups, string $mode ) {
			return \TinySolutions\mlt\Controllers\Admin\Api::instance()->media_submit_bulk_action(
				[
					'ids'                  => $ids,
					'type'                 => 'bulkedit',
					'post_categories'      => $groups,
					'post_categories_mode' => $mode,
				]
			);
		};

		$bulk( [ $a, $b ], [ $this->groups['shop'] ], 'add' );
		$this->assertSame( $this->ids( 'travel', 'shop' ), self::group_ids( $a ) );
		$this->assertSame( $this->ids( 'shop' ), self::group_ids( $b ) );

		$bulk( [ $a ], [ $this->groups['travel'] ], 'remove' );
		$this->assertSame( $this->ids( 'shop' ), self::group_ids( $a ) );

		$bulk( [ $a, $b ], [ $this->groups['beach'] ], 'replace' );
		$this->assertSame( $this->ids( 'beach' ), self::group_ids( $a ) );
		$this->assertSame( $this->ids( 'beach' ), self::group_ids( $b ) );
	}

	// ---------------------------------------------------------------------
	// MCP
	// ---------------------------------------------------------------------

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
	 * @return array Result.
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
	 * Over MCP on the dedicated server: find a group by name, filter media by
	 * it, and set an attachment's groups.
	 */
	public function test_group_workflow_over_mcp() {
		$this->connect( self::SCOPED_ROUTE );
		$id = $this->attachment( 'Mcp group fixture' );

		$names = wp_list_pluck( $this->send( self::SCOPED_ROUTE, 'tools/list' )['result']['tools'], 'name' );
		$this->assertContains( 'tsmlt-list-media-groups', $names );
		$this->assertContains( 'tsmlt-set-media-groups', $names );

		$found = $this->tool( self::SCOPED_ROUTE, 'tsmlt-list-media-groups', [ 'search' => 'Beach' ] );
		$this->assertFalse( $found['isError'], wp_json_encode( $found ) );
		$group_id = $found['structuredContent']['groups'][0]['id'];
		$this->assertSame( $this->groups['beach'], $group_id );

		$set = $this->tool(
			self::SCOPED_ROUTE,
			'tsmlt-set-media-groups',
			[
				'id'        => $id,
				'group_ids' => [ $group_id ],
				'mode'      => 'add',
			]
		);
		$this->assertFalse( $set['isError'], wp_json_encode( $set ) );
		$this->assertTrue( $set['structuredContent']['changed'] );
		$this->assertSame( [ $group_id ], self::group_ids( $id ) );

		$search = $this->tool( self::SCOPED_ROUTE, 'tsmlt-search-media', [ 'group_id' => $group_id ] );
		$this->assertFalse( $search['isError'], wp_json_encode( $search ) );
		$this->assertSame( [ $id ], wp_list_pluck( $search['structuredContent']['items'], 'id' ) );

		$bad = $this->tool(
			self::SCOPED_ROUTE,
			'tsmlt-set-media-groups',
			[
				'id'        => $id,
				'group_ids' => [ 999999 ],
				'mode'      => 'replace',
			]
		);
		$this->assertTrue( $bad['isError'], wp_json_encode( $bad ) );
		$this->assertStringContainsString( '999999', $bad['content'][0]['text'] );
		$this->assertSame( [ $group_id ], self::group_ids( $id ) );
	}

	/**
	 * The group tools are not offered on the MCP Adapter's default gateway;
	 * search-media still is.
	 */
	public function test_group_tools_not_on_default_gateway() {
		$route = '/mcp/mcp-adapter-default-server';
		$this->connect( $route );

		$discovered = $this->tool( $route, 'mcp-adapter-discover-abilities', [] );
		$names      = wp_list_pluck( $discovered['structuredContent']['abilities'], 'name' );

		$this->assertContains( self::SEARCH, $names );
		$this->assertNotContains( self::LIST, $names );
		$this->assertNotContains( self::SET, $names );

		$id  = $this->attachment();
		$run = $this->tool(
			$route,
			'mcp-adapter-execute-ability',
			[
				'ability_name' => self::SET,
				'parameters'   => (object) [
					'id'        => $id,
					'group_ids' => [ $this->groups['travel'] ],
					'mode'      => 'add',
				],
			]
		);
		$this->assertTrue( $run['isError'], wp_json_encode( $run ) );
		$this->assertSame( [], self::group_ids( $id ), 'The default gateway cannot run the write ability.' );
	}
}
