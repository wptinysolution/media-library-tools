<?php
/**
 * End-to-end tests for the dedicated Media Library Tools MCP server.
 *
 * Runs the official MCP Adapter in-process (skipped unless MCP_ADAPTER_DIR is
 * set; see tests/README.md). An extra public ability stands in for another
 * plugin's write ability, to prove the dedicated server neither lists nor
 * executes anything but this plugin's abilities, while the adapter's default
 * server keeps working.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\McpServerRegistration;

/**
 * McpScopedServerTest
 */
class McpScopedServerTest extends WP_UnitTestCase {

	/**
	 * Dedicated server route.
	 *
	 * @var string
	 */
	const SCOPED_ROUTE = '/media-library-tools/mcp';

	/**
	 * Adapter default server route.
	 *
	 * @var string
	 */
	const DEFAULT_ROUTE = '/mcp/mcp-adapter-default-server';

	/**
	 * MCP protocol revision the tests negotiate.
	 *
	 * @var string
	 */
	const PROTOCOL_VERSION = '2025-11-25';

	/**
	 * Stand-in for another plugin's public write ability.
	 *
	 * @var string
	 */
	const FOREIGN_ABILITY = 'other-plugin/delete-everything';

	/**
	 * Whether the foreign ability ran.
	 *
	 * @var bool
	 */
	public static $foreign_executed = false;

	/**
	 * Session ID per route.
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
	 * Skip without the adapter; register the foreign ability.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}
		$this->sessions         = [];
		self::$foreign_executed = false;

		if ( ! WP_Abilities_Registry::get_instance()->is_registered( self::FOREIGN_ABILITY ) ) {
			// The registry is already initialised, so register directly, as
			// wp_register_ability() only works during wp_abilities_api_init.
			WP_Abilities_Registry::get_instance()->register(
				self::FOREIGN_ABILITY,
				[
					'label'               => 'Delete everything',
					'description'         => 'Stand-in for another plugin\'s destructive ability.',
					'category'            => AbilitiesInit::CATEGORY,
					'input_schema'        => [
						'type'    => 'object',
						'default' => [],
					],
					'output_schema'       => [ 'type' => 'object' ],
					'execute_callback'    => static function () {
						self::$foreign_executed = true;
						return [ 'deleted' => true ];
					},
					'permission_callback' => '__return_true',
					'meta'                => [
						'annotations' => [
							'readonly'    => false,
							'destructive' => true,
						],
						'mcp'         => [ 'public' => true ],
					],
				]
			);
		}
	}

	/**
	 * Remove the foreign ability.
	 */
	public function tear_down() {
		if ( class_exists( 'WP_Abilities_Registry' ) && WP_Abilities_Registry::get_instance()->is_registered( self::FOREIGN_ABILITY ) ) {
			WP_Abilities_Registry::get_instance()->unregister( self::FOREIGN_ABILITY );
		}
		parent::tear_down();
	}

	/**
	 * Send one JSON-RPC message.
	 *
	 * @param string $route  Server route.
	 * @param string $method JSON-RPC method.
	 * @param array  $params Params.
	 *
	 * @return WP_REST_Response
	 */
	private function send( string $route, string $method, array $params = [] ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'Accept', 'application/json, text/event-stream' );
		if ( isset( $this->sessions[ $route ] ) ) {
			$request->set_header( 'Mcp-Session-Id', $this->sessions[ $route ] );
			$request->set_header( 'MCP-Protocol-Version', self::PROTOCOL_VERSION );
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
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Decode a response body.
	 *
	 * @param WP_REST_Response $response Response.
	 *
	 * @return array
	 */
	private function decode( WP_REST_Response $response ): array {
		$data = $response->get_data();
		return is_array( $data ) ? $data : json_decode( wp_json_encode( $data ), true );
	}

	/**
	 * Initialize a session on a route.
	 *
	 * @param string $route Server route.
	 *
	 * @return WP_REST_Response The initialize response.
	 */
	private function initialize( string $route ): WP_REST_Response {
		unset( $this->sessions[ $route ] );
		$response = $this->send(
			$route,
			'initialize',
			[
				'protocolVersion' => self::PROTOCOL_VERSION,
				'capabilities'    => (object) [],
				'clientInfo'      => [
					'name'    => 'tsmlt-tests',
					'version' => '1.0.0',
				],
			]
		);
		$headers = $response->get_headers();
		if ( 200 === $response->get_status() && isset( $headers['Mcp-Session-Id'] ) ) {
			$this->sessions[ $route ] = $headers['Mcp-Session-Id'];
			$this->send( $route, 'notifications/initialized' );
		}
		return $response;
	}

	/**
	 * Log in as a new user with a role and open a session on a route.
	 *
	 * @param string $role  Role.
	 * @param string $route Server route.
	 */
	private function connect_as( string $role, string $route = self::SCOPED_ROUTE ) {
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
		$this->sessions = [];
		$this->assertSame( 200, $this->initialize( $route )->get_status() );
	}

	/**
	 * Call a tool and return the decoded JSON-RPC message.
	 *
	 * @param string $route     Server route.
	 * @param string $tool      Tool name.
	 * @param array  $arguments Arguments.
	 *
	 * @return array
	 */
	private function call( string $route, string $tool, array $arguments = [] ): array {
		return $this->decode(
			$this->send(
				$route,
				'tools/call',
				[
					'name'      => $tool,
					'arguments' => (object) $arguments,
				]
			)
		);
	}

	/**
	 * The server is registered at the expected route.
	 */
	public function test_route_is_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( self::SCOPED_ROUTE, $routes );
		$this->assertArrayHasKey( self::DEFAULT_ROUTE, $routes, 'Default server must keep working.' );
		$this->assertSame( 'media-library-tools', McpServerRegistration::SERVER_ID );
	}

	/**
	 * tools/list exposes exactly the plugin's three abilities, with hints.
	 */
	public function test_lists_only_plugin_tools() {
		$this->connect_as( 'administrator' );

		$tools = $this->decode( $this->send( self::SCOPED_ROUTE, 'tools/list' ) )['result']['tools'];
		$names = wp_list_pluck( $tools, 'name' );
		sort( $names );

		$this->assertSame( [ 'tsmlt-get-media-details', 'tsmlt-search-media', 'tsmlt-update-media-metadata' ], $names );

		$by_name = array_column( $tools, null, 'name' );
		$this->assertTrue( $by_name['tsmlt-search-media']['annotations']['readOnlyHint'] );
		$this->assertTrue( $by_name['tsmlt-get-media-details']['annotations']['readOnlyHint'] );
		$this->assertFalse( $by_name['tsmlt-update-media-metadata']['annotations']['readOnlyHint'] );
		$this->assertTrue( $by_name['tsmlt-update-media-metadata']['annotations']['destructiveHint'] );
		$this->assertFalse( $by_name['tsmlt-update-media-metadata']['inputSchema']['additionalProperties'] );
	}

	/**
	 * The default server still discovers every public ability, including the
	 * foreign one — the exposure this server exists to avoid.
	 */
	public function test_default_server_unchanged() {
		$this->connect_as( 'administrator', self::DEFAULT_ROUTE );

		$tools = wp_list_pluck( $this->decode( $this->send( self::DEFAULT_ROUTE, 'tools/list' ) )['result']['tools'], 'name' );
		$this->assertContains( 'mcp-adapter-discover-abilities', $tools );

		$abilities = wp_list_pluck(
			$this->call( self::DEFAULT_ROUTE, 'mcp-adapter-discover-abilities' )['result']['structuredContent']['abilities'],
			'name'
		);
		$this->assertContains( self::FOREIGN_ABILITY, $abilities );
		$this->assertContains( 'tsmlt/search-media', $abilities );
		$this->assertFalse( self::$foreign_executed );
	}

	/**
	 * Unlisted tools cannot be called on the dedicated server, directly or
	 * through the adapter's gateway tools.
	 *
	 * @dataProvider data_unlisted_calls
	 *
	 * @param string $tool      Tool name.
	 * @param array  $arguments Arguments.
	 */
	public function test_unlisted_tools_cannot_be_called( string $tool, array $arguments ) {
		$this->connect_as( 'administrator' );

		$data = $this->call( self::SCOPED_ROUTE, $tool, $arguments );

		$this->assertArrayHasKey( 'error', $data, wp_json_encode( $data ) );
		$this->assertStringContainsStringIgnoringCase( 'not found', wp_json_encode( $data['error'] ) );
		$this->assertFalse( self::$foreign_executed, 'Foreign ability must never run.' );
	}

	/**
	 * @return array
	 */
	public function data_unlisted_calls(): array {
		return [
			'foreign ability as tool'     => [ 'other-plugin-delete-everything', [] ],
			'execute-ability gateway'     => [
				'mcp-adapter-execute-ability',
				[
					'ability_name' => self::FOREIGN_ABILITY,
					'parameters'   => (object) [],
				],
			],
			'discover-abilities gateway'  => [ 'mcp-adapter-discover-abilities', [] ],
			'get-ability-info gateway'    => [ 'mcp-adapter-get-ability-info', [ 'ability_name' => self::FOREIGN_ABILITY ] ],
			'ability name with slash'     => [ self::FOREIGN_ABILITY, [] ],
			'core ability'                => [ 'core-get-site-info', [] ],
		];
	}

	/**
	 * Read tools work for an administrator.
	 */
	public function test_read_tools_work() {
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Scoped harbour',
				'file'           => '2026/10/scoped.jpg',
			]
		);
		$this->connect_as( 'administrator' );

		$search = $this->call( self::SCOPED_ROUTE, 'tsmlt-search-media', [ 'search' => 'Scoped harbour' ] )['result'];
		$this->assertFalse( $search['isError'], wp_json_encode( $search ) );
		$this->assertSame( [ $id ], wp_list_pluck( $search['structuredContent']['items'], 'id' ) );

		$details = $this->call( self::SCOPED_ROUTE, 'tsmlt-get-media-details', [ 'id' => $id ] )['result'];
		$this->assertFalse( $details['isError'], wp_json_encode( $details ) );
		$this->assertSame( 'scoped.jpg', $details['structuredContent']['filename'] );
	}

	/**
	 * The update tool writes for an administrator.
	 */
	public function test_update_tool_works() {
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Before',
			]
		);
		$this->connect_as( 'administrator' );

		$result = $this->call(
			self::SCOPED_ROUTE,
			'tsmlt-update-media-metadata',
			[
				'id'      => $id,
				'caption' => 'Scoped caption',
			]
		)['result'];

		$this->assertFalse( $result['isError'], wp_json_encode( $result ) );
		$this->assertSame( [ 'caption' ], $result['structuredContent']['updated_fields'] );
		clean_post_cache( $id );
		$this->assertSame( 'Scoped caption', get_post( $id )->post_excerpt );
	}

	/**
	 * Ability input validation still applies on the dedicated server.
	 */
	public function test_input_validation_still_applies() {
		$id = self::factory()->attachment->create( [ 'post_mime_type' => 'image/jpeg' ] );
		$this->connect_as( 'administrator' );

		$data = $this->call(
			self::SCOPED_ROUTE,
			'tsmlt-update-media-metadata',
			[
				'id'       => $id,
				'filename' => 'evil.php',
			]
		);

		$this->assertTrue( isset( $data['error'] ) || ! empty( $data['result']['isError'] ), wp_json_encode( $data ) );
		$this->assertStringContainsString( 'filename', wp_json_encode( $data ) );
	}

	/**
	 * Logged-in users without manage_options cannot open a session at all.
	 *
	 * @dataProvider data_roles_without_manage_options
	 *
	 * @param string $role Role.
	 */
	public function test_transport_refuses_users_without_manage_options( string $role ) {
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );

		$response = $this->initialize( self::SCOPED_ROUTE );

		$this->assertSame( 403, $response->get_status() );
		$this->assertArrayNotHasKey( self::SCOPED_ROUTE, $this->sessions );
	}

	/**
	 * @return array
	 */
	public function data_roles_without_manage_options(): array {
		return [
			'editor'      => [ 'editor' ],
			'author'      => [ 'author' ],
			'subscriber'  => [ 'subscriber' ],
		];
	}

	/**
	 * Anonymous requests are refused.
	 */
	public function test_transport_refuses_anonymous() {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->initialize( self::SCOPED_ROUTE )->get_status() );
	}
}
