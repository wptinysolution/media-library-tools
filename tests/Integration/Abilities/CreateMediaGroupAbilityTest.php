<?php
/**
 * tsmlt/create-media-group: registration and schemas, creating top-level and
 * child groups, duplicate names, parent validation, name validation,
 * permissions, use with the other group abilities, and MCP exposure.
 *
 * Executed through WP_Ability::execute(), the path the MCP Adapter uses; the
 * MCP tests send real JSON-RPC messages in-process (skipped unless
 * MCP_ADAPTER_DIR is set). Every group is created in the test database.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\CreateMediaGroup;
use TinySolutions\mlt\Helpers\Fns;

/**
 * CreateMediaGroupAbilityTest
 */
class CreateMediaGroupAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 */
	const NAME = 'tsmlt/create-media-group';

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
		return $ability->execute( $input );
	}

	/**
	 * Create a group through the ability and assert it succeeded.
	 *
	 * @param array $input Input.
	 *
	 * @return array Result.
	 */
	private function create( array $input ): array {
		$result = $this->call_ability( self::NAME, $input );
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : '' );
		$this->assertTrue( rest_validate_value_from_schema( $result, wp_get_ability( self::NAME )->get_output_schema(), 'output' ) );
		return $result;
	}

	/**
	 * Number of media groups in the database.
	 *
	 * @return int
	 */
	private static function group_count(): int {
		return (int) wp_count_terms(
			[
				'taxonomy'   => Fns::CATEGORY,
				'hide_empty' => false,
			]
		);
	}

	/**
	 * Assert an ability error and that no group was created.
	 *
	 * @param mixed  $result Result.
	 * @param string $code   Expected error code.
	 * @param int    $before Group count before the call.
	 */
	private function assert_refused( $result, string $code, int $before ) {
		$this->assertWPError( $result );
		$this->assertSame( $code, $result->get_error_code() );
		$this->assertSame( $before, self::group_count(), 'No group was created.' );
	}

	// ---------------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------------

	/**
	 * Registered in the plugin category, dedicated server only, with closed
	 * schemas and no slug or description input.
	 */
	public function test_registration_and_schemas() {
		$ability = wp_get_ability( self::NAME );

		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( AbilitiesInit::CATEGORY, $ability->get_category() );
		$this->assertFalse( $ability->get_meta()['mcp']['public'] );
		$this->assertFalse( $ability->get_meta()['annotations']['readonly'] );

		$input = $ability->get_input_schema();
		$this->assertFalse( $input['additionalProperties'] );
		$this->assertSame( [ 'name' ], $input['required'] );
		$this->assertSame( [ 'name', 'parent' ], array_keys( $input['properties'] ) );
		$this->assertSame( 200, $input['properties']['name']['maxLength'] );
		$this->assertSame( 0, $input['properties']['parent']['minimum'] );

		$output = $ability->get_output_schema();
		$this->assertFalse( $output['additionalProperties'] );
		$this->assertSame( [ 'group', 'created' ], $output['required'] );
	}

	// ---------------------------------------------------------------------
	// Creating
	// ---------------------------------------------------------------------

	/**
	 * A top-level group, with a slug WordPress generated.
	 */
	public function test_creates_a_top_level_group() {
		$result = $this->create( [ 'name' => 'Product Photos' ] );

		$this->assertTrue( $result['created'] );
		$this->assertSame( 'Product Photos', $result['group']['name'] );
		$this->assertSame( 0, $result['group']['parent'] );
		$this->assertSame( 0, $result['group']['count'] );
		$term = get_term( $result['group']['id'], Fns::CATEGORY );
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( 'product-photos', $term->slug );
	}

	/**
	 * A child group under an existing group; names WordPress stores with
	 * entities are returned as plain text.
	 */
	public function test_creates_a_child_group() {
		$parent = $this->create( [ 'name' => 'Travel' ] )['group']['id'];

		$child = $this->create(
			[
				'name'   => 'Beach & Sea',
				'parent' => $parent,
			]
		);

		$this->assertSame( $parent, $child['group']['parent'] );
		$this->assertSame( 'Beach & Sea', $child['group']['name'] );
		$this->assertSame( $parent, (int) get_term( $child['group']['id'], Fns::CATEGORY )->parent );
	}

	/**
	 * A backslash in the name is kept: wp_insert_term() expects slashed
	 * input, so an unslashed name would lose it, fail the read-back and be
	 * deleted again.
	 */
	public function test_backslash_in_name_is_kept() {
		$before = self::group_count();

		$result = $this->create( [ 'name' => 'C:\\Photos\\2026' ] );

		$this->assertTrue( $result['created'] );
		$this->assertSame( 'C:\\Photos\\2026', $result['group']['name'] );
		$term = get_term( $result['group']['id'], Fns::CATEGORY );
		$this->assertInstanceOf( WP_Term::class, $term, 'The group was kept after verification.' );
		$this->assertSame( 'C:\\Photos\\2026', $term->name );
		$this->assertSame( $before + 1, self::group_count() );
	}

	/**
	 * A name already used under the same parent is refused, ignoring case,
	 * and the error names the existing group.
	 */
	public function test_duplicate_name_under_the_same_parent_is_refused() {
		$existing = $this->create( [ 'name' => 'Products' ] )['group']['id'];
		$before   = self::group_count();

		foreach ( [ 'Products', 'PRODUCTS', '  products  ' ] as $name ) {
			$result = $this->call_ability( self::NAME, [ 'name' => $name ] );
			$this->assert_refused( $result, CreateMediaGroup::ERROR_GROUP_EXISTS, $before );
			$this->assertSame( 409, $result->get_error_data()['status'] );
			$this->assertSame( $existing, $result->get_error_data()['group_id'] );
			$this->assertStringContainsString( (string) $existing, $result->get_error_message() );
		}
	}

	/**
	 * The same name under a different parent is allowed, as in WordPress.
	 */
	public function test_same_name_under_a_different_parent_is_allowed() {
		$shop   = $this->create( [ 'name' => 'Shop' ] )['group']['id'];
		$travel = $this->create( [ 'name' => 'Travel' ] )['group']['id'];

		$a = $this->create(
			[
				'name'   => 'Products',
				'parent' => $shop,
			]
		);
		$b = $this->create(
			[
				'name'   => 'Products',
				'parent' => $travel,
			]
		);
		$top = $this->create( [ 'name' => 'Products' ] );

		$this->assertNotSame( $a['group']['id'], $b['group']['id'] );
		$this->assertSame( [ $shop, $travel, 0 ], [ $a['group']['parent'], $b['group']['parent'], $top['group']['parent'] ] );
		$slugs = array_map(
			static function ( $result ) {
				return get_term( $result['group']['id'], Fns::CATEGORY )->slug;
			},
			[ $a, $b, $top ]
		);
		$this->assertCount( 3, array_unique( $slugs ), 'WordPress made the slugs unique.' );
	}

	// ---------------------------------------------------------------------
	// Validation
	// ---------------------------------------------------------------------

	/**
	 * Unknown parents and terms of other taxonomies are refused.
	 */
	public function test_invalid_parents_are_refused() {
		$tag      = (int) self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] );
		$category = (int) self::factory()->term->create( [ 'taxonomy' => 'category' ] );
		$before   = self::group_count();

		foreach ( [ 999999, $tag, $category ] as $parent ) {
			$result = $this->call_ability(
				self::NAME,
				[
					'name'   => 'Orphan',
					'parent' => $parent,
				]
			);
			$this->assert_refused( $result, AbilityGuard::ERROR_GROUP_NOT_FOUND, $before );
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
		$before = self::group_count();

		$this->assert_refused( $this->call_ability( self::NAME, [ 'name' => $name ] ), AbilityGuard::ERROR_INVALID_INPUT, $before );
	}

	/**
	 * Names that pass the schema but carry no usable text.
	 *
	 * @return array
	 */
	public function data_unusable_names(): array {
		return [
			'spaces only'  => [ '   ' ],
			'markup only'  => [ '<b></b>' ],
			'script only'  => [ '<script>alert(1)</script>' ],
			'too long when stored' => [ str_repeat( '&', 200 ) ],
		];
	}

	/**
	 * Malformed input is refused by the schema, and by the execute callback
	 * when it is reached directly.
	 *
	 * @dataProvider data_malformed_input
	 *
	 * @param array $input Input.
	 */
	public function test_malformed_input_is_refused( array $input ) {
		$before = self::group_count();

		$this->assert_refused( $this->call_ability( self::NAME, $input ), 'ability_invalid_input', $before );
		$this->assertWPError( CreateMediaGroup::instance()->execute( $input ) );
		$this->assertSame( $before, self::group_count() );
	}

	/**
	 * Malformed inputs.
	 *
	 * @return array
	 */
	public function data_malformed_input(): array {
		return [
			'missing name'     => [ [] ],
			'empty name'       => [ [ 'name' => '' ] ],
			'name too long'    => [ [ 'name' => str_repeat( 'g', 201 ) ] ],
			'name not text'    => [ [ 'name' => [ 'Group' ] ] ],
			'name a number'    => [ [ 'name' => 5 ] ],
			'negative parent'  => [
				[
					'name'   => 'Group',
					'parent' => -1,
				],
			],
			'parent a name'    => [
				[
					'name'   => 'Group',
					'parent' => 'Travel',
				],
			],
			'slug not allowed' => [
				[
					'name' => 'Group',
					'slug' => 'custom',
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
		wp_set_current_user( '' === $role ? 0 : self::factory()->user->create( [ 'role' => $role ] ) );
		$before = self::group_count();

		$this->assert_refused( $this->call_ability( self::NAME, [ 'name' => 'Blocked' ] ), 'ability_invalid_permissions', $before );
		$this->assert_refused( CreateMediaGroup::instance()->execute( [ 'name' => 'Blocked' ] ), $code, $before );
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
	 * An administrator without the taxonomy's edit_terms capability is
	 * refused.
	 */
	public function test_edit_terms_capability_is_required() {
		$cap   = get_taxonomy( Fns::CATEGORY )->cap->edit_terms;
		$block = static function ( $allcaps ) use ( $cap ) {
			$allcaps[ $cap ] = false;
			return $allcaps;
		};
		add_filter( 'user_has_cap', $block );
		$before = self::group_count();

		$result = $this->call_ability( self::NAME, [ 'name' => 'No edit_terms' ] );

		remove_filter( 'user_has_cap', $block );
		$this->assert_refused( $result, AbilityGuard::ERROR_FORBIDDEN, $before );
	}

	// ---------------------------------------------------------------------
	// With the other group abilities
	// ---------------------------------------------------------------------

	/**
	 * A created group is listed, can be assigned, and found through search.
	 */
	public function test_created_group_works_with_the_other_abilities() {
		$group = $this->create( [ 'name' => 'Fresh Group' ] )['group'];

		$listed = $this->call_ability( 'tsmlt/list-media-groups', [ 'id' => $group['id'] ] );
		$this->assertSame( [ $group ], $listed['groups'] );

		$attachment = (int) self::factory()->attachment->create( [ 'post_mime_type' => 'image/jpeg' ] );
		$set        = $this->call_ability(
			'tsmlt/set-media-groups',
			[
				'id'        => $attachment,
				'group_ids' => [ $group['id'] ],
				'mode'      => 'add',
			]
		);
		$this->assertTrue( $set['changed'] );

		$found = $this->call_ability( 'tsmlt/search-media', [ 'group_id' => $group['id'] ] );
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
	 * On the dedicated server the tool is listed and creates a group; a
	 * duplicate is a tool error naming the existing ID.
	 */
	public function test_available_on_the_dedicated_server() {
		$route = '/media-library-tools/mcp';
		$this->connect( $route );

		$this->assertContains( 'tsmlt-create-media-group', wp_list_pluck( $this->send( $route, 'tools/list' )['result']['tools'], 'name' ) );

		$created = $this->tool( $route, 'tsmlt-create-media-group', [ 'name' => 'Over MCP' ] );
		$this->assertFalse( $created['isError'], wp_json_encode( $created ) );
		$id = $created['structuredContent']['group']['id'];
		$this->assertSame( 'Over MCP', get_term( $id, Fns::CATEGORY )->name );

		$duplicate = $this->tool( $route, 'tsmlt-create-media-group', [ 'name' => 'over mcp' ] );
		$this->assertTrue( $duplicate['isError'], wp_json_encode( $duplicate ) );
		$this->assertStringContainsString( (string) $id, $duplicate['content'][0]['text'] );
	}

	/**
	 * Not offered on the MCP Adapter's default gateway, and cannot be run
	 * through it.
	 */
	public function test_not_available_on_the_default_gateway() {
		$route = '/mcp/mcp-adapter-default-server';
		$this->connect( $route );
		$before = self::group_count();

		$discovered = $this->tool( $route, 'mcp-adapter-discover-abilities', [] );
		$names      = wp_list_pluck( $discovered['structuredContent']['abilities'], 'name' );
		$this->assertContains( 'tsmlt/search-media', $names, 'Precondition: discovery works.' );
		$this->assertNotContains( self::NAME, $names );

		$run = $this->tool(
			$route,
			'mcp-adapter-execute-ability',
			[
				'ability_name' => self::NAME,
				'parameters'   => (object) [ 'name' => 'Through the gateway' ],
			]
		);
		$this->assertTrue( $run['isError'], wp_json_encode( $run ) );
		$this->assertSame( $before, self::group_count() );
	}
}
