<?php
/**
 * tsmlt/update-media-group: renaming a media group without changing its ID,
 * slug, parent or media; sibling duplicates; validation; permissions; use
 * with the other group abilities; and MCP exposure.
 *
 * Executed through WP_Ability::execute(), the path the MCP Adapter uses; the
 * MCP tests send real JSON-RPC messages in-process (skipped unless
 * MCP_ADAPTER_DIR is set). Groups and attachments are generated fixtures.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\UpdateMediaGroup;
use TinySolutions\mlt\Helpers\Fns;

/**
 * UpdateMediaGroupAbilityTest
 */
class UpdateMediaGroupAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 */
	const NAME = 'tsmlt/update-media-group';

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
	 * Administrator.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->sessions = [];
	}

	/**
	 * Create a group.
	 *
	 * @param string $name   Name.
	 * @param int    $parent_id Parent group ID.
	 *
	 * @return int
	 */
	private function group( string $name, int $parent_id = 0 ): int {
		return (int) self::factory()->term->create(
			[
				'taxonomy' => Fns::CATEGORY,
				'name'     => $name,
				'parent'   => $parent_id,
			]
		);
	}

	/**
	 * Execute an ability as the current user.
	 *
	 * @param string $name  Ability name.
	 * @param array  $input Input.
	 *
	 * @return mixed
	 */
	private function call_ability( string $name, array $input ) {
		$ability = wp_get_ability( $name );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		$result = $ability->execute( $input );
		clean_term_cache( isset( $input['id'] ) && is_int( $input['id'] ) ? $input['id'] : [], Fns::CATEGORY );
		return $result;
	}

	/**
	 * Rename through the ability and assert it succeeded.
	 *
	 * @param int    $id   Group ID.
	 * @param string $name New name.
	 *
	 * @return array
	 */
	private function rename( int $id, string $name ): array {
		$result = $this->call_ability(
			self::NAME,
			[
				'id'   => $id,
				'name' => $name,
			]
		);
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : '' );
		$this->assertTrue( rest_validate_value_from_schema( $result, wp_get_ability( self::NAME )->get_output_schema(), 'output' ) );
		return $result;
	}

	/**
	 * Everything about a group that renaming must not change, plus its name.
	 *
	 * @param int $id Group ID.
	 *
	 * @return array
	 */
	private static function snapshot( int $id ): array {
		clean_term_cache( $id, Fns::CATEGORY );
		$term = get_term( $id, Fns::CATEGORY );
		return [
			'name'   => $term->name,
			'slug'   => $term->slug,
			'parent' => (int) $term->parent,
			'count'  => (int) $term->count,
			'tt_id'  => (int) $term->term_taxonomy_id,
		];
	}

	/**
	 * Assert an error and that the group is unchanged.
	 *
	 * @param mixed  $result Result.
	 * @param string $code   Expected code.
	 * @param int    $id     Group ID.
	 * @param array  $before Snapshot before.
	 */
	private function assert_refused( $result, string $code, int $id, array $before ) {
		$this->assertWPError( $result );
		$this->assertSame( $code, $result->get_error_code() );
		$this->assertSame( $before, self::snapshot( $id ), 'The group is unchanged.' );
	}

	// ---------------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------------

	/**
	 * Registered in the plugin category, dedicated server only, destructive,
	 * with closed schemas accepting only id and name.
	 */
	public function test_registration_and_schemas() {
		$ability = wp_get_ability( self::NAME );

		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( AbilitiesInit::CATEGORY, $ability->get_category() );
		$this->assertFalse( $ability->get_meta()['mcp']['public'] );
		$this->assertTrue( $ability->get_meta()['annotations']['destructive'] );

		$input = $ability->get_input_schema();
		$this->assertFalse( $input['additionalProperties'] );
		$this->assertSame( [ 'id', 'name' ], $input['required'] );
		$this->assertSame( [ 'id', 'name' ], array_keys( $input['properties'] ) );
		$this->assertSame( 200, $input['properties']['name']['maxLength'] );

		$output = $ability->get_output_schema();
		$this->assertFalse( $output['additionalProperties'] );
		$this->assertSame( [ 'group', 'previous_name', 'changed' ], $output['required'] );
	}

	// ---------------------------------------------------------------------
	// Renaming
	// ---------------------------------------------------------------------

	/**
	 * A rename changes only the name: the ID, slug, parent, count and media
	 * assignments stay the same.
	 */
	public function test_rename_keeps_id_slug_parent_and_media() {
		$parent = $this->group( 'Travel' );
		$id     = $this->group( 'Beach', $parent );
		$media  = [];
		for ( $i = 0; $i < 2; $i++ ) {
			$media[] = (int) self::factory()->attachment->create( [ 'post_mime_type' => 'image/jpeg' ] );
			wp_set_object_terms( end( $media ), [ $id ], Fns::CATEGORY );
		}
		$before = self::snapshot( $id );

		$result = $this->rename( $id, 'Beaches & Coast' );

		$this->assertTrue( $result['changed'] );
		$this->assertSame( 'Beach', $result['previous_name'] );
		$this->assertSame(
			[
				'id'     => $id,
				'name'   => 'Beaches & Coast',
				'parent' => $parent,
				'count'  => 2,
			],
			$result['group']
		);
		$after = self::snapshot( $id );
		$this->assertSame( array_merge( $before, [ 'name' => $after['name'] ] ), $after, 'Only the name changed.' );
		$this->assertSame( 'beach', $after['slug'], 'The slug (and URL) is kept.' );
		foreach ( $media as $attachment ) {
			$this->assertSame( [ $id ], array_map( 'intval', wp_get_object_terms( $attachment, Fns::CATEGORY, [ 'fields' => 'ids' ] ) ) );
		}
	}

	/**
	 * Changing only letter case is a rename of the group itself, not a
	 * clash with itself.
	 */
	public function test_case_only_rename() {
		$id = $this->group( 'travel' );

		$result = $this->rename( $id, 'Travel' );

		$this->assertTrue( $result['changed'] );
		$this->assertSame( 'Travel', get_term( $id, Fns::CATEGORY )->name );
	}

	/**
	 * A backslash in the new name is kept (wp_update_term() expects slashed
	 * input).
	 */
	public function test_backslash_in_name_is_kept() {
		$id = $this->group( 'Paths' );

		$result = $this->rename( $id, 'C:\\Photos\\2026' );

		$this->assertSame( 'C:\\Photos\\2026', $result['group']['name'] );
		$this->assertSame( 'C:\\Photos\\2026', get_term( $id, Fns::CATEGORY )->name );
	}

	/**
	 * Renaming to the current name changes nothing and writes nothing.
	 */
	public function test_same_name_is_unchanged() {
		$id     = $this->group( 'A & B' );
		$before = self::snapshot( $id );
		$writes = 0;
		$count  = static function () use ( &$writes ) {
			++$writes;
		};
		add_action( 'edit_terms', $count );

		$result = $this->rename( $id, '  A & B  ' );

		remove_action( 'edit_terms', $count );
		$this->assertFalse( $result['changed'] );
		$this->assertSame( 'A & B', $result['previous_name'] );
		$this->assertSame( 'A & B', $result['group']['name'] );
		$this->assertSame( 0, $writes, 'wp_update_term() was not called.' );
		$this->assertSame( $before, self::snapshot( $id ) );
	}

	/**
	 * A name a sibling already has (any case) is refused, naming the sibling.
	 */
	public function test_duplicate_sibling_name_is_refused() {
		$parent  = $this->group( 'Shop' );
		$sibling = $this->group( 'Products', $parent );
		$id      = $this->group( 'Catalogue', $parent );
		$before  = self::snapshot( $id );

		foreach ( [ 'Products', 'PRODUCTS', ' products ' ] as $name ) {
			$result = $this->call_ability(
				self::NAME,
				[
					'id'   => $id,
					'name' => $name,
				]
			);
			$this->assert_refused( $result, UpdateMediaGroup::ERROR_GROUP_EXISTS, $id, $before );
			$this->assertSame( 409, $result->get_error_data()['status'] );
			$this->assertSame( $sibling, $result->get_error_data()['group_id'] );
			$this->assertStringContainsString( (string) $sibling, $result->get_error_message() );
		}
	}

	/**
	 * The same name as a group under a different parent is allowed.
	 */
	public function test_same_name_under_a_different_parent_is_allowed() {
		$shop   = $this->group( 'Shop' );
		$travel = $this->group( 'Travel' );
		$this->group( 'Products', $shop );
		$id = $this->group( 'Souvenirs', $travel );

		$result = $this->rename( $id, 'Products' );

		$this->assertTrue( $result['changed'] );
		$this->assertSame( $travel, $result['group']['parent'] );
	}

	// ---------------------------------------------------------------------
	// Validation
	// ---------------------------------------------------------------------

	/**
	 * Unknown IDs and terms of other taxonomies are refused, and those terms
	 * are not changed.
	 */
	public function test_unknown_and_other_taxonomy_ids_are_refused() {
		$tag      = (int) self::factory()->term->create(
			[
				'taxonomy' => 'post_tag',
				'name'     => 'Tag Name',
			]
		);
		$category = (int) self::factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'Category Name',
			]
		);

		foreach ( [ 999999 => null, $tag => 'post_tag', $category => 'category' ] as $id => $taxonomy ) {
			$result = $this->call_ability(
				self::NAME,
				[
					'id'   => $id,
					'name' => 'Hijacked',
				]
			);
			$this->assertWPError( $result );
			$this->assertSame( AbilityGuard::ERROR_GROUP_NOT_FOUND, $result->get_error_code(), (string) $id );
			if ( $taxonomy ) {
				$this->assertNotSame( 'Hijacked', get_term( $id, $taxonomy )->name );
			}
		}
	}

	/**
	 * Names with no usable text are refused by the ability.
	 *
	 * @dataProvider data_unusable_names
	 *
	 * @param string $name Name.
	 */
	public function test_unusable_names_are_refused( string $name ) {
		$id     = $this->group( 'Original' );
		$before = self::snapshot( $id );

		$result = $this->call_ability(
			self::NAME,
			[
				'id'   => $id,
				'name' => $name,
			]
		);

		$this->assert_refused( $result, AbilityGuard::ERROR_INVALID_INPUT, $id, $before );
	}

	/**
	 * Names that pass the schema but carry no usable text.
	 *
	 * @return array
	 */
	public function data_unusable_names(): array {
		return [
			'spaces only'          => [ '   ' ],
			'markup only'          => [ '<b></b>' ],
			'script only'          => [ '<script>alert(1)</script>' ],
			'too long when stored' => [ str_repeat( '&', 200 ) ],
		];
	}

	/**
	 * Malformed input is refused by the schema, and by the execute callback
	 * when it is reached directly.
	 *
	 * @dataProvider data_malformed_input
	 *
	 * @param array $input Input without the ID (merged unless 'id' given).
	 */
	public function test_malformed_input_is_refused( array $input ) {
		$id     = $this->group( 'Original' );
		$before = self::snapshot( $id );
		$input  = array_key_exists( 'id', $input ) ? $input : array_merge( [ 'id' => $id ], $input );

		$this->assert_refused( $this->call_ability( self::NAME, $input ), 'ability_invalid_input', $id, $before );
		$this->assertWPError( UpdateMediaGroup::instance()->execute( $input ) );
		$this->assertSame( $before, self::snapshot( $id ) );
	}

	/**
	 * Malformed inputs.
	 *
	 * @return array
	 */
	public function data_malformed_input(): array {
		return [
			'missing name'     => [ [] ],
			'missing id'       => [
				[
					'id'   => null,
					'name' => 'X',
				],
			],
			'empty name'       => [ [ 'name' => '' ] ],
			'name too long'    => [ [ 'name' => str_repeat( 'g', 201 ) ] ],
			'name not text'    => [ [ 'name' => [ 'Group' ] ] ],
			'name a number'    => [ [ 'name' => 5 ] ],
			'id a string'      => [
				[
					'id'   => 'Original',
					'name' => 'X',
				],
			],
			'id zero'          => [
				[
					'id'   => 0,
					'name' => 'X',
				],
			],
			'slug not allowed' => [
				[
					'name' => 'X',
					'slug' => 'custom',
				],
			],
			'parent not allowed' => [
				[
					'name'   => 'X',
					'parent' => 0,
				],
			],
		];
	}

	// ---------------------------------------------------------------------
	// Permissions
	// ---------------------------------------------------------------------

	/**
	 * Editors, authors and logged-out requests are refused.
	 *
	 * @dataProvider data_refused_users
	 *
	 * @param string $role Role, or '' for logged out.
	 * @param string $code Error code from the execute callback.
	 */
	public function test_users_without_permission_are_refused( string $role, string $code ) {
		$id     = $this->group( 'Original' );
		$before = self::snapshot( $id );
		wp_set_current_user( '' === $role ? 0 : self::factory()->user->create( [ 'role' => $role ] ) );
		$input = [
			'id'   => $id,
			'name' => 'Blocked',
		];

		$this->assert_refused( $this->call_ability( self::NAME, $input ), 'ability_invalid_permissions', $id, $before );
		$this->assert_refused( UpdateMediaGroup::instance()->execute( $input ), $code, $id, $before );
	}

	/**
	 * Refused users.
	 *
	 * @return array
	 */
	public function data_refused_users(): array {
		return [
			'editor'     => [ 'editor', AbilityGuard::ERROR_FORBIDDEN ],
			'author'     => [ 'author', AbilityGuard::ERROR_FORBIDDEN ],
			'logged out' => [ '', AbilityGuard::ERROR_UNAUTHENTICATED ],
		];
	}

	/**
	 * An administrator without the taxonomy's edit_terms capability, or
	 * without edit_term for this group, is refused.
	 */
	public function test_taxonomy_and_object_capabilities_are_required() {
		$id     = $this->group( 'Original' );
		$before = self::snapshot( $id );
		$input  = [
			'id'   => $id,
			'name' => 'Blocked',
		];

		$cap      = get_taxonomy( Fns::CATEGORY )->cap->edit_terms;
		$no_terms = static function ( $allcaps ) use ( $cap ) {
			$allcaps[ $cap ] = false;
			return $allcaps;
		};
		add_filter( 'user_has_cap', $no_terms );
		$without_edit_terms = $this->call_ability( self::NAME, $input );
		remove_filter( 'user_has_cap', $no_terms );

		$deny = static function ( $caps, $requested, $user_id, $args ) use ( $id ) {
			return 'edit_term' === $requested && isset( $args[0] ) && (int) $args[0] === $id ? [ 'do_not_allow' ] : $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 4 );
		$without_edit_term = $this->call_ability( self::NAME, $input );
		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assert_refused( $without_edit_terms, AbilityGuard::ERROR_FORBIDDEN, $id, $before );
		$this->assert_refused( $without_edit_term, AbilityGuard::ERROR_FORBIDDEN, $id, $before );
	}

	/**
	 * When the stored result does not match the request, the previous name is
	 * put back and an error is returned; the group itself is kept.
	 */
	public function test_unverified_rename_restores_the_previous_name() {
		$id      = $this->group( 'Original' );
		$before  = self::snapshot( $id );
		$tamper  = static function ( $data ) {
			$data['name'] = 'Tampered';
			return $data;
		};
		$applied = false;
		$once    = static function ( $data ) use ( &$applied, $tamper ) {
			if ( $applied ) {
				return $data;
			}
			$applied = true;
			return $tamper( $data );
		};
		add_filter( 'wp_update_term_data', $once );

		$result = $this->call_ability(
			self::NAME,
			[
				'id'   => $id,
				'name' => 'Renamed',
			]
		);

		remove_filter( 'wp_update_term_data', $once );
		$this->assert_refused( $result, UpdateMediaGroup::ERROR_UPDATE_FAILED, $id, $before );
		$this->assertInstanceOf( WP_Term::class, get_term( $id, Fns::CATEGORY ), 'The group was not deleted.' );
	}

	// ---------------------------------------------------------------------
	// With the other group abilities
	// ---------------------------------------------------------------------

	/**
	 * After a rename, list-media-groups shows the new name and search-media
	 * still finds the same media by group ID.
	 */
	public function test_renamed_group_works_with_the_other_abilities() {
		$id         = $this->group( 'Before Rename' );
		$attachment = (int) self::factory()->attachment->create( [ 'post_mime_type' => 'image/jpeg' ] );
		wp_set_object_terms( $attachment, [ $id ], Fns::CATEGORY );

		$this->rename( $id, 'After Rename' );

		$listed = $this->call_ability( 'tsmlt/list-media-groups', [ 'search' => 'After Rename' ] );
		$this->assertSame( [ $id ], wp_list_pluck( $listed['groups'], 'id' ) );
		$this->assertSame( 0, $this->call_ability( 'tsmlt/list-media-groups', [ 'search' => 'Before Rename' ] )['total'] );

		$found = $this->call_ability( 'tsmlt/search-media', [ 'group_id' => $id ] );
		$this->assertSame( [ $attachment ], wp_list_pluck( $found['items'], 'id' ) );
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
	 * @param string $tool      Tool.
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
	 * On the dedicated server the tool is listed and renames; a duplicate is
	 * a tool error naming the sibling.
	 */
	public function test_available_on_the_dedicated_server() {
		$route = '/media-library-tools/mcp';
		$this->connect( $route );
		$sibling = $this->group( 'Taken' );
		$id      = $this->group( 'Over MCP' );

		$this->assertContains( 'tsmlt-update-media-group', wp_list_pluck( $this->send( $route, 'tools/list' )['result']['tools'], 'name' ) );

		$renamed = $this->tool(
			$route,
			'tsmlt-update-media-group',
			[
				'id'   => $id,
				'name' => 'Renamed Over MCP',
			]
		);
		$this->assertFalse( $renamed['isError'], wp_json_encode( $renamed ) );
		$this->assertSame( 'Over MCP', $renamed['structuredContent']['previous_name'] );
		$this->assertSame( 'Renamed Over MCP', get_term( $id, Fns::CATEGORY )->name );

		$duplicate = $this->tool(
			$route,
			'tsmlt-update-media-group',
			[
				'id'   => $id,
				'name' => 'taken',
			]
		);
		$this->assertTrue( $duplicate['isError'], wp_json_encode( $duplicate ) );
		$this->assertStringContainsString( (string) $sibling, $duplicate['content'][0]['text'] );
	}

	/**
	 * Not offered on the MCP Adapter's default gateway, and cannot be run
	 * through it.
	 */
	public function test_not_available_on_the_default_gateway() {
		$route = '/mcp/mcp-adapter-default-server';
		$this->connect( $route );
		$id     = $this->group( 'Gateway' );
		$before = self::snapshot( $id );

		$discovered = $this->tool( $route, 'mcp-adapter-discover-abilities', [] );
		$names      = wp_list_pluck( $discovered['structuredContent']['abilities'], 'name' );
		$this->assertContains( 'tsmlt/search-media', $names, 'Precondition: discovery works.' );
		$this->assertNotContains( self::NAME, $names );

		$run = $this->tool(
			$route,
			'mcp-adapter-execute-ability',
			[
				'ability_name' => self::NAME,
				'parameters'   => (object) [
					'id'   => $id,
					'name' => 'Through the gateway',
				],
			]
		);
		$this->assertTrue( $run['isError'], wp_json_encode( $run ) );
		$this->assertSame( $before, self::snapshot( $id ) );
	}
}
