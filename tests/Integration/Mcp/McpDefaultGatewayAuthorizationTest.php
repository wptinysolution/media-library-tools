<?php
/**
 * Authorization of the three Free abilities through the MCP Adapter's default
 * gateway (`mcp-adapter-execute-ability`), with real WordPress users,
 * capabilities and REST authentication — nothing under test is mocked.
 *
 * Layers, in order, in MCP Adapter 0.7.0:
 *
 * 1. Transport (HttpTransport::check_permission): the REST route requires a
 *    logged-in user with `read`. Logged out → 401; logged in without `read`
 *    → 403. No MCP handler runs.
 * 2. Gateway permission (ExecuteAbilityAbility::check_permission): `read`,
 *    MCP exposure, then the target ability's check_permissions(). The Free
 *    abilities require manage_options there, so a user without it gets
 *    `isError: true` with text "Permission denied", before any ability's
 *    execute() starts.
 * 3. Per-attachment checks inside the target's execute callback (read_post,
 *    edit_post): refused as an `isError` result with the ability's message.
 *
 * Skipped unless MCP_ADAPTER_DIR is set (see tests/README.md).
 *
 * @package TinySolutions\mlt
 */

require_once __DIR__ . '/DefaultGatewayClientTrait.php';

/**
 * McpDefaultGatewayAuthorizationTest
 */
class McpDefaultGatewayAuthorizationTest extends WP_UnitTestCase {

	use DefaultGatewayClientTrait;

	/**
	 * Fixture attachment ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Skip without the adapter; create the fixture.
	 */
	public function set_up() {
		parent::set_up();
		$this->gw_require_adapter();
		$this->id = $this->gw_make_attachment( 'Authorization probe' );
	}

	/**
	 * Remove the spies.
	 */
	public function tear_down() {
		$this->gw_remove_spies();
		parent::tear_down();
	}

	/**
	 * Valid parameters per ability; `{id}` is the fixture.
	 *
	 * @param string $name Ability name.
	 *
	 * @return array
	 */
	private function parameters( string $name ): array {
		$parameters = [
			'tsmlt/search-media'          => [ 'search' => 'Authorization probe' ],
			'tsmlt/get-media-details'     => [ 'id' => $this->id ],
			'tsmlt/update-media-metadata' => [
				'id'      => $this->id,
				'caption' => 'Changed by an unauthorized probe',
			],
		];
		return $parameters[ $name ];
	}

	/**
	 * @return array
	 */
	public function data_abilities(): array {
		return [
			'tsmlt/search-media'          => [ 'tsmlt/search-media' ],
			'tsmlt/get-media-details'     => [ 'tsmlt/get-media-details' ],
			'tsmlt/update-media-metadata' => [ 'tsmlt/update-media-metadata' ],
		];
	}

	/**
	 * An administrator runs each ability through the gateway.
	 *
	 * @dataProvider data_abilities
	 *
	 * @param string $name Ability name.
	 */
	public function test_administrator_is_authorized( string $name ) {
		$this->gw_connect_as( 'administrator' );
		$this->gw_install_spies();

		$result = $this->gw_call( 'mcp-adapter-execute-ability', self::gw_execute_args( $name, $this->parameters( $name ) ) );

		$this->assertFalse( $result['isError'], wp_json_encode( $result ) );
		$this->assertTrue( $result['structuredContent']['success'] );
		$this->assertSame( [ 'mcp-adapter/execute-ability', $name ], $this->spied_names( 'executed' ) );

		$data = $result['structuredContent']['data'];
		if ( 'tsmlt/search-media' === $name ) {
			$this->assertSame( [ $this->id ], array_column( $data['items'], 'id' ) );
		} elseif ( 'tsmlt/get-media-details' === $name ) {
			$this->assertSame( $this->id, $data['id'] );
		} else {
			$this->assertSame( [ 'caption' ], $data['updated_fields'] );
			$this->assertSame( 'Changed by an unauthorized probe', $this->gw_snapshot( $this->id )['post_excerpt'] );
		}
	}

	/**
	 * Every Free ability crossed with every role that has `read` but not
	 * manage_options.
	 *
	 * @return array
	 */
	public function data_abilities_and_roles_without_manage_options(): array {
		$cases = [];
		foreach ( array_keys( $this->data_abilities() ) as $name ) {
			foreach ( [ 'editor', 'author', 'contributor', 'subscriber' ] as $role ) {
				$cases[ $name . ' as ' . $role ] = [ $name, $role ];
			}
		}
		return $cases;
	}

	/**
	 * A logged-in user without manage_options can reach the gateway but is
	 * refused by the target ability's permission check: no ability starts
	 * executing, no data is returned, nothing changes.
	 *
	 * @dataProvider data_abilities_and_roles_without_manage_options
	 *
	 * @param string $name Ability name.
	 * @param string $role Role.
	 */
	public function test_users_without_manage_options_are_refused( string $name, string $role ) {
		$this->gw_connect_as( $role );
		$this->assertFalse( current_user_can( 'manage_options' ), 'Precondition: the role lacks manage_options.' );
		$this->assertTrue( current_user_can( 'read' ), 'Precondition: the role passes the gateway read check.' );
		$before = $this->gw_snapshot( $this->id );
		$this->gw_install_spies();

		$result = $this->gw_call( 'mcp-adapter-execute-ability', self::gw_execute_args( $name, $this->parameters( $name ) ) );

		$this->assertTrue( $result['isError'], wp_json_encode( $result ) );
		$this->assertSame( 'Permission denied', $result['content'][0]['text'] );
		$this->assertArrayNotHasKey( 'structuredContent', $result );
		$this->assertStringNotContainsString( 'Authorization probe', wp_json_encode( $result ) );

		$this->assertSame( [], $this->spied_names( 'invoked' ), 'No ability may start, not even the gateway.' );
		$this->assertSame( [], $this->spied_names( 'executed' ) );
		$this->assert_no_mutation_hooks( $this->id );
		$this->assertSame( $before, $this->gw_snapshot( $this->id ) );
	}

	/**
	 * A logged-in user with no role (no `read`) cannot open a session.
	 */
	public function test_user_without_read_cannot_open_a_session() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => '' ] ) );
		$this->assertFalse( current_user_can( 'read' ), 'Precondition: the user lacks read.' );

		$this->assertSame( 403, $this->gw_initialize()->get_status() );
	}

	/**
	 * A logged-out request is refused by the transport with 401: no ability
	 * is invoked and nothing changes, even with a session ID from an earlier
	 * administrator connection.
	 *
	 * @dataProvider data_abilities
	 *
	 * @param string $name Ability name.
	 */
	public function test_logged_out_request_is_refused( string $name ) {
		$this->gw_connect_as( 'administrator' );
		wp_set_current_user( 0 );
		$before = $this->gw_snapshot( $this->id );
		$this->gw_install_spies();

		$response = $this->gw_call_raw( 'mcp-adapter-execute-ability', self::gw_execute_args( $name, $this->parameters( $name ) ) );

		$this->assertSame( 401, $response->get_status() );
		$this->assertStringNotContainsString( 'Authorization probe', wp_json_encode( $this->gw_decode( $response ) ) );
		$this->assertSame( [], $this->spied_names( 'invoked' ) );
		$this->assert_no_mutation_hooks( $this->id );
		$this->assertSame( $before, $this->gw_snapshot( $this->id ) );
	}

	/**
	 * Per-attachment checks hold through the gateway. An administrator denied
	 * a meta capability on the fixture gets the ability's own refusal; the
	 * ability starts (these checks run inside its callback) but writes or
	 * reveals nothing.
	 *
	 * @dataProvider data_object_denials
	 *
	 * @param string $name     Ability name.
	 * @param string $cap      Denied meta capability.
	 * @param string $expected Expected error text.
	 */
	public function test_attachment_capability_is_enforced( string $name, string $cap, string $expected ) {
		$this->gw_connect_as( 'administrator' );
		$id   = $this->id;
		$deny = static function ( $caps, $requested, $user_id, $args ) use ( $cap, $id ) {
			if ( $cap === $requested && isset( $args[0] ) && (int) $args[0] === $id ) {
				return [ 'do_not_allow' ];
			}
			return $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 4 );
		$before = $this->gw_snapshot( $this->id );
		$this->gw_install_spies();

		$result = $this->gw_call( 'mcp-adapter-execute-ability', self::gw_execute_args( $name, $this->parameters( $name ) ) );
		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assertTrue( $result['isError'], wp_json_encode( $result ) );
		$this->assertSame( $expected, $result['content'][0]['text'] );
		$this->assertStringNotContainsString( 'Original caption', wp_json_encode( $result ) );
		$this->assertSame( [ 'mcp-adapter/execute-ability', $name ], $this->spied_names( 'executed' ) );
		$this->assert_no_mutation_hooks( $this->id );
		$this->assertSame( $before, $this->gw_snapshot( $this->id ) );
	}

	/**
	 * @return array
	 */
	public function data_object_denials(): array {
		return [
			'details without read_post' => [ 'tsmlt/get-media-details', 'read_post', 'No attachment exists with that ID.' ],
			'update without read_post'  => [ 'tsmlt/update-media-metadata', 'read_post', 'No attachment exists with that ID.' ],
			'update without edit_post'  => [ 'tsmlt/update-media-metadata', 'edit_post', 'You are not allowed to edit this attachment.' ],
		];
	}

	/**
	 * Search omits an attachment the administrator may not read, through the
	 * gateway too.
	 */
	public function test_search_omits_unreadable_attachment() {
		$this->gw_connect_as( 'administrator' );
		$id   = $this->id;
		$deny = static function ( $caps, $requested, $user_id, $args ) use ( $id ) {
			if ( 'read_post' === $requested && isset( $args[0] ) && (int) $args[0] === $id ) {
				return [ 'do_not_allow' ];
			}
			return $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 4 );

		$result = $this->gw_call( 'mcp-adapter-execute-ability', self::gw_execute_args( 'tsmlt/search-media', $this->parameters( 'tsmlt/search-media' ) ) );
		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assertFalse( $result['isError'], wp_json_encode( $result ) );
		$this->assertSame( [], $result['structuredContent']['data']['items'] );
		$this->assertStringNotContainsString( 'Original caption', wp_json_encode( $result ) );
	}
}
