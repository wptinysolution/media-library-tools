<?php
/**
 * The opt-in that lists the Media Library Tools tools directly on the MCP
 * Adapter's default server (`mcp_adapter_default_server_config`).
 *
 * Off by default; on through the `mcp_default_server_enabled` setting or the
 * `tsmlt_mcp_default_server_enabled` filter. The dedicated server is unchanged
 * either way, no ability becomes `meta.mcp.public`, and the plugin's tools and
 * resources are hidden from list results for users without manage_options.
 *
 * The end-to-end tests build a second default server with its own route
 * (the real one already exists in this process) through the adapter's own
 * DefaultServerFactory. They are skipped unless MCP_ADAPTER_DIR is set (see
 * tests/README.md).
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Controllers\Admin\Api;
use TinySolutions\mlt\Helpers\Fns;

/**
 * McpDefaultServerOptInTest
 */
class McpDefaultServerOptInTest extends WP_UnitTestCase {

	/**
	 * Server ID of the test copy of the default server.
	 *
	 * @var string
	 */
	const TEST_SERVER_ID = 'tsmlt-test-default-server';

	/**
	 * Route of the test copy of the default server.
	 *
	 * @var string
	 */
	const TEST_ROUTE = '/tsmlt-test/default-server';

	/**
	 * MCP protocol revision the tests negotiate.
	 *
	 * @var string
	 */
	const PROTOCOL_VERSION = '2025-11-25';

	/**
	 * Stand-in Pro tool (registered in the plugin's category).
	 *
	 * @var string
	 */
	const PRO_TOOL = 'tsmlt/test-pro-tool';

	/**
	 * Stand-in MCP App view (as Pro would register).
	 *
	 * @var string
	 */
	const VIEW = 'tsmlt/test-default-view';

	/**
	 * URI of the stand-in view.
	 *
	 * @var string
	 */
	const VIEW_URI = 'ui://media-library-tools/test-default-view.html';

	/**
	 * Stand-in for another plugin's ability, outside the plugin's category.
	 *
	 * @var string
	 */
	const FOREIGN_ABILITY = 'other-plugin/test-tool';

	/**
	 * Whether the test copy of the default server was built in this process.
	 *
	 * @var bool
	 */
	private static $server_built = false;

	/**
	 * `rest_api_init` callbacks that register the test server's route.
	 *
	 * @var callable[]
	 */
	private static $route_callbacks = [];

	/**
	 * Session ID from the initialize handshake.
	 *
	 * @var string|null
	 */
	private $session_id = null;

	/**
	 * JSON-RPC request counter.
	 *
	 * @var int
	 */
	private $request_id = 0;

	/**
	 * Register the stand-ins and start with the option off.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		delete_option( 'tsmlt_settings' );

		$registry = WP_Abilities_Registry::get_instance();
		if ( ! $registry->is_registered( self::PRO_TOOL ) ) {
			$registry->register(
				self::PRO_TOOL,
				[
					'label'               => 'Test Pro tool',
					'description'         => 'Stand-in for a Pro ability.',
					'category'            => AbilitiesInit::CATEGORY,
					'execute_callback'    => '__return_empty_array',
					'permission_callback' => '__return_false',
					'meta'                => [
						'mcp' => [
							'public' => false,
							'type'   => 'tool',
						],
					],
				]
			);
		}
		if ( ! $registry->is_registered( self::VIEW ) ) {
			$registry->register(
				self::VIEW,
				[
					'label'               => 'Test view',
					'description'         => 'Stand-in for a Pro MCP App view.',
					'category'            => AbilitiesInit::CATEGORY,
					'execute_callback'    => '__return_empty_string',
					'permission_callback' => '__return_false',
					'meta'                => [
						'mcp' => [
							'public'   => false,
							'type'     => 'resource',
							'uri'      => self::VIEW_URI,
							'mimeType' => McpServerRegistration::APP_MIME_TYPE,
						],
					],
				]
			);
		}
	}

	/**
	 * Remove the stand-ins and the filters.
	 */
	public function tear_down() {
		$registry = WP_Abilities_Registry::get_instance();
		foreach ( [ self::PRO_TOOL, self::VIEW ] as $name ) {
			if ( $registry->is_registered( $name ) ) {
				$registry->unregister( $name );
			}
		}
		remove_all_filters( McpServerRegistration::DEFAULT_SERVER_FILTER );
		remove_all_filters( McpServerRegistration::TOOLS_FILTER );
		remove_all_filters( McpServerRegistration::RESOURCES_FILTER );
		delete_option( 'tsmlt_settings' );
		parent::tear_down();
	}

	/**
	 * Turn the setting on or off.
	 *
	 * @param bool $enabled Setting value.
	 */
	private function set_setting( bool $enabled ) {
		$settings = get_option( 'tsmlt_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];

		$settings[ McpServerRegistration::DEFAULT_SERVER_SETTING ] = $enabled ? 1 : 0;
		update_option( 'tsmlt_settings', $settings );
	}

	/**
	 * Skip unless the loaded adapter can carry the tools.
	 */
	private function require_adapter() {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}
		if ( ! McpServerRegistration::adapter_supports_default_server() ) {
			$this->markTestSkipped( 'The loaded MCP Adapter is older than ' . McpServerRegistration::DEFAULT_SERVER_MIN_ADAPTER_VERSION . '.' );
		}
	}

	/**
	 * A default server configuration as the adapter passes it.
	 *
	 * @return array
	 */
	private function default_config(): array {
		return [
			'server_id' => 'mcp-adapter-default-server',
			'tools'     => [ 'mcp-adapter/discover-abilities', 'mcp-adapter/get-ability-info', 'mcp-adapter/execute-ability' ],
			'resources' => [ 'other-plugin/public-resource' ],
			'prompts'   => [],
		];
	}

	/**
	 * The option is off on a new install, and the default server
	 * configuration is returned unchanged.
	 */
	public function test_disabled_by_default() {
		$server = McpServerRegistration::instance();

		$this->assertSame( 0, Fns::get_options()[ McpServerRegistration::DEFAULT_SERVER_SETTING ] );
		$this->assertFalse( $server->is_default_server_enabled() );
		$this->assertSame( $this->default_config(), $server->add_to_default_server( $this->default_config() ) );
	}

	/**
	 * The callback is hooked after plugins using the default priority.
	 */
	public function test_hooks_are_registered() {
		$server = McpServerRegistration::instance();

		$this->assertSame( McpServerRegistration::DEFAULT_SERVER_CONFIG_PRIORITY, has_filter( 'mcp_adapter_default_server_config', [ $server, 'add_to_default_server' ] ) );
		$this->assertSame( 10, has_filter( 'mcp_adapter_tools_list', [ $server, 'hide_tools_from_unauthorized' ] ) );
		$this->assertSame( 10, has_filter( 'mcp_adapter_resources_list', [ $server, 'hide_resources_from_unauthorized' ] ) );
	}

	/**
	 * The setting turns it on; the filter overrides the setting both ways and
	 * only a strict true enables it.
	 */
	public function test_setting_and_filter() {
		$server = McpServerRegistration::instance();

		$this->set_setting( true );
		$this->assertTrue( $server->is_default_server_enabled() );

		add_filter( McpServerRegistration::DEFAULT_SERVER_FILTER, '__return_false' );
		$this->assertFalse( $server->is_default_server_enabled() );
		remove_all_filters( McpServerRegistration::DEFAULT_SERVER_FILTER );

		$this->set_setting( false );
		$this->assertFalse( $server->is_default_server_enabled() );

		add_filter( McpServerRegistration::DEFAULT_SERVER_FILTER, '__return_true' );
		$this->assertTrue( $server->is_default_server_enabled() );
		remove_all_filters( McpServerRegistration::DEFAULT_SERVER_FILTER );

		foreach ( [ 1, '1', 'yes', [ true ] ] as $truthy ) {
			$filter = static function () use ( $truthy ) {
				return $truthy;
			};
			add_filter( McpServerRegistration::DEFAULT_SERVER_FILTER, $filter );
			$this->assertFalse( $server->is_default_server_enabled(), 'Only a strict true enables it.' );
			remove_filter( McpServerRegistration::DEFAULT_SERVER_FILTER, $filter );
		}
	}

	/**
	 * A malformed settings option leaves it off.
	 */
	public function test_malformed_setting_is_off() {
		update_option( 'tsmlt_settings', 'not-an-array' );
		$this->assertFalse( McpServerRegistration::instance()->is_default_server_enabled() );
	}

	/**
	 * Saving the settings stores the option as 1 or 0.
	 */
	public function test_settings_save() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		Api::instance()->update_option( [ McpServerRegistration::DEFAULT_SERVER_SETTING => 'on' ] );
		$this->assertSame( 1, get_option( 'tsmlt_settings' )[ McpServerRegistration::DEFAULT_SERVER_SETTING ] );
		$this->assertTrue( McpServerRegistration::instance()->is_default_server_enabled() );

		Api::instance()->update_option( [] );
		$this->assertSame( 0, get_option( 'tsmlt_settings' )[ McpServerRegistration::DEFAULT_SERVER_SETTING ] );
		$this->assertFalse( McpServerRegistration::instance()->is_default_server_enabled() );
	}

	/**
	 * Adapter versions before 0.5.0 (or without a version) are refused.
	 */
	public function test_adapter_version_gate() {
		$this->assertFalse( McpServerRegistration::adapter_supports_default_server( '0' ) );
		$this->assertFalse( McpServerRegistration::adapter_supports_default_server( '0.4.9' ) );
		$this->assertTrue( McpServerRegistration::adapter_supports_default_server( '0.5.0' ) );
		$this->assertTrue( McpServerRegistration::adapter_supports_default_server( '0.7.1' ) );
	}

	/**
	 * When on, the dedicated server's tools and resources are appended to
	 * the default server's lists; existing entries are kept, nothing is
	 * listed twice, and rejected names stay out.
	 */
	public function test_enabled_merges_the_dedicated_lists() {
		$this->require_adapter();
		$this->set_setting( true );
		add_filter(
			McpServerRegistration::TOOLS_FILTER,
			static function ( $names ) {
				$names[] = self::PRO_TOOL;
				$names[] = self::FOREIGN_ABILITY;
				return $names;
			}
		);
		add_filter(
			McpServerRegistration::RESOURCES_FILTER,
			static function ( $names ) {
				$names[] = self::VIEW;
				return $names;
			}
		);
		$this->setExpectedIncorrectUsage( McpServerRegistration::TOOLS_FILTER );

		$config = $this->default_config();
		$config['tools'][] = 'tsmlt/search-media';

		$result = McpServerRegistration::instance()->add_to_default_server( $config );
		$tools  = $result['tools'];

		$this->assertSame( array_slice( $config['tools'], 0, 3 ), array_slice( $tools, 0, 3 ), 'The gateway tools are kept first.' );
		$this->assertSame( array_values( array_unique( $tools ) ), $tools, 'No tool is listed twice.' );
		foreach ( AbilitiesInit::instance()->get_ability_names() as $name ) {
			$this->assertContains( $name, $tools );
		}
		$this->assertContains( self::PRO_TOOL, $tools );
		$this->assertNotContains( self::FOREIGN_ABILITY, $tools );
		$this->assertSame( [ 'other-plugin/public-resource', self::VIEW ], $result['resources'] );
		$this->assertSame( $config['prompts'], $result['prompts'] );
		$this->assertSame( $config['server_id'], $result['server_id'] );
	}

	/**
	 * A non-array configuration is passed through.
	 */
	public function test_non_array_config_is_passed_through() {
		$this->set_setting( true );
		$this->assertSame( 'broken', McpServerRegistration::instance()->add_to_default_server( 'broken' ) );
	}

	/**
	 * The option does not make any plugin ability MCP-public, so the
	 * execute-ability gateway still cannot reach them.
	 */
	public function test_mcp_public_flags_are_unchanged() {
		$this->set_setting( true );
		$public = [];
		foreach ( wp_get_abilities() as $ability ) {
			if ( AbilitiesInit::CATEGORY !== $ability->get_category() ) {
				continue;
			}
			$meta = $ability->get_meta();
			if ( ! empty( $meta['mcp']['public'] ) ) {
				$public[] = $ability->get_name();
			}
		}
		sort( $public );
		$this->assertSame( [ 'tsmlt/get-media-details', 'tsmlt/search-media' ], $public );
	}

	/**
	 * Users without manage_options do not see the plugin's tools or
	 * resources in list results, as arrays or protocol objects; other
	 * entries stay, in order. Administrators see everything.
	 */
	public function test_lists_hide_plugin_entries_from_unauthorized_users() {
		$server   = McpServerRegistration::instance();
		$name_obj = new class() {
			/**
			 * Tool name.
			 *
			 * @return string
			 */
			public function getName(): string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Mirrors the protocol record.
				return 'tsmlt-get-media-details';
			}
		};
		$tools     = [
			[ 'name' => 'mcp-adapter-discover-abilities' ],
			[ 'name' => 'tsmlt-search-media' ],
			$name_obj,
			[ 'name' => 'tsmlt-test-pro-tool' ],
			[ 'name' => 'other-plugin-test-tool' ],
			'unreadable',
		];
		$resources = [
			[ 'uri' => 'ui://other/view.html' ],
			[ 'uri' => self::VIEW_URI ],
		];

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( $tools, $server->hide_tools_from_unauthorized( $tools ) );
		$this->assertSame( $resources, $server->hide_resources_from_unauthorized( $resources ) );

		foreach ( [ 'editor', 'author', 'subscriber' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertSame(
				[ [ 'name' => 'mcp-adapter-discover-abilities' ], [ 'name' => 'other-plugin-test-tool' ], 'unreadable' ],
				$server->hide_tools_from_unauthorized( $tools ),
				$role
			);
			$this->assertSame( [ [ 'uri' => 'ui://other/view.html' ] ], $server->hide_resources_from_unauthorized( $resources ), $role );
		}

		wp_set_current_user( 0 );
		$this->assertSame( [ [ 'name' => 'mcp-adapter-discover-abilities' ], [ 'name' => 'other-plugin-test-tool' ], 'unreadable' ], $server->hide_tools_from_unauthorized( $tools ) );
		$this->assertSame( 'broken', $server->hide_tools_from_unauthorized( 'broken' ) );
	}

	/**
	 * A tool renamed through the adapter's `mcp_adapter_tool_name` filter is
	 * still hidden.
	 */
	public function test_renamed_tool_is_hidden() {
		$rename = static function ( $name ) {
			return 'tsmlt-search-media' === $name ? 'renamed-search' : $name;
		};
		add_filter( 'mcp_adapter_tool_name', $rename );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$result = McpServerRegistration::instance()->hide_tools_from_unauthorized( [ [ 'name' => 'renamed-search' ] ] );
		remove_filter( 'mcp_adapter_tool_name', $rename );

		$this->assertSame( [], $result );
	}

	/**
	 * Build the test copy of the default server, with the option on, through
	 * the adapter's DefaultServerFactory, and rebuild the REST routes.
	 */
	private function build_default_server() {
		$this->require_adapter();
		if ( ! self::$server_built ) {
			$this->create_default_server();
		}

		// The routes go on the current REST server, without rebuilding it:
		// a rebuild would drop routes whose hooks earlier tests restored away.
		if ( ! array_key_exists( self::TEST_ROUTE, rest_get_server()->get_routes() ) ) {
			global $wp_current_filter, $wp_actions;
			rest_get_server();
			// register_rest_route() only accepts routes once `rest_api_init`
			// has run; the test case resets the action counts between tests.
			$count                       = $wp_actions['rest_api_init'] ?? null;
			$wp_actions['rest_api_init'] = max( 1, (int) $count ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test only.
			$wp_current_filter[]         = 'rest_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test only.
			try {
				foreach ( self::$route_callbacks as $callback ) {
					call_user_func( $callback );
				}
			} finally {
				array_pop( $wp_current_filter );
				if ( null === $count ) {
					unset( $wp_actions['rest_api_init'] );
				} else {
					$wp_actions['rest_api_init'] = $count; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test only.
				}
			}
		}
		$this->assertArrayHasKey( self::TEST_ROUTE, rest_get_server()->get_routes() );
	}

	/**
	 * Create the test copy of the default server, with the option on, and
	 * keep the `rest_api_init` callbacks its transport added.
	 */
	private function create_default_server() {
		global $wp_filter;
		$before = $this->rest_api_init_callbacks();

		$this->set_setting( true );
		$rename = static function ( $config ) {
			$config['server_id']              = self::TEST_SERVER_ID;
			$config['server_route_namespace'] = 'tsmlt-test';
			$config['server_route']           = 'default-server';
			return $config;
		};
		add_filter( 'mcp_adapter_default_server_config', $rename, 5 );

		// create_server() only accepts servers during `mcp_adapter_init`,
		// which already ran in this process.
		global $wp_current_filter;
		$wp_current_filter[] = 'mcp_adapter_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test only.
		try {
			\WP\MCP\Servers\DefaultServerFactory::create();
		} finally {
			array_pop( $wp_current_filter );
			remove_filter( 'mcp_adapter_default_server_config', $rename, 5 );
		}

		self::$route_callbacks = array_diff_key( $this->rest_api_init_callbacks(), $before );
		self::$server_built    = true;
	}

	/**
	 * Callbacks hooked to `rest_api_init`, by hook ID.
	 *
	 * @return array<string, callable>
	 */
	private function rest_api_init_callbacks(): array {
		global $wp_filter;
		$callbacks = [];
		if ( isset( $wp_filter['rest_api_init'] ) ) {
			foreach ( $wp_filter['rest_api_init']->callbacks as $priority_callbacks ) {
				foreach ( $priority_callbacks as $id => $callback ) {
					$callbacks[ $id ] = $callback['function'];
				}
			}
		}
		return $callbacks;
	}

	/**
	 * Send one JSON-RPC message to the test server.
	 *
	 * @param string $method JSON-RPC method.
	 * @param array  $params Params.
	 *
	 * @return WP_REST_Response
	 */
	private function send( string $method, array $params = [] ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', self::TEST_ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'Accept', 'application/json, text/event-stream' );
		if ( null !== $this->session_id ) {
			$request->set_header( 'Mcp-Session-Id', $this->session_id );
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
	 * Log in as a new user with a role and open a session.
	 *
	 * @param string $role Role.
	 */
	private function connect_as( string $role ) {
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
		$this->session_id = null;
		$response         = $this->send(
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
		$this->assertSame( 200, $response->get_status() );
		$headers          = $response->get_headers();
		$this->session_id = $headers['Mcp-Session-Id'] ?? null;
		$this->send( 'notifications/initialized' );
	}

	/**
	 * Tool names from tools/list.
	 *
	 * @return string[]
	 */
	private function list_tool_names(): array {
		return wp_list_pluck( $this->decode( $this->send( 'tools/list' ) )['result']['tools'], 'name' );
	}

	/**
	 * Call a tool.
	 *
	 * @param string $tool      Tool name.
	 * @param array  $arguments Arguments.
	 *
	 * @return array
	 */
	private function call( string $tool, array $arguments = [] ): array {
		return $this->decode(
			$this->send(
				'tools/call',
				[
					'name'      => $tool,
					'arguments' => (object) $arguments,
				]
			)
		);
	}

	/**
	 * End to end: administrators get every Free tool directly, next to the
	 * gateway tools, with their annotations, and can call them.
	 */
	public function test_default_server_lists_and_runs_plugin_tools() {
		$this->build_default_server();
		$this->connect_as( 'administrator' );

		$tools = $this->decode( $this->send( 'tools/list' ) )['result']['tools'];
		$names = wp_list_pluck( $tools, 'name' );
		$this->assertContains( 'mcp-adapter-execute-ability', $names );
		foreach ( AbilitiesInit::instance()->get_ability_names() as $ability ) {
			$this->assertContains( str_replace( '/', '-', $ability ), $names );
		}
		$by_name = array_column( $tools, null, 'name' );
		$this->assertTrue( $by_name['tsmlt-search-media']['annotations']['readOnlyHint'] );
		$this->assertTrue( $by_name['tsmlt-update-media-metadata']['annotations']['destructiveHint'] );

		$result = $this->call( 'tsmlt-search-media', [ 'per_page' => 1 ] );
		$this->assertArrayHasKey( 'result', $result );
		$this->assertFalse( $result['result']['isError'] ?? false );
	}

	/**
	 * End to end: users without manage_options can connect (the default
	 * server admits any logged-in user) but see none of the plugin's tools,
	 * and calling one is refused.
	 */
	public function test_default_server_hides_and_refuses_for_editors() {
		$this->build_default_server();
		$this->connect_as( 'editor' );

		$names = $this->list_tool_names();
		$this->assertContains( 'mcp-adapter-discover-abilities', $names );
		$this->assertSame( [], preg_grep( '/^tsmlt-/', $names ) );

		$result = $this->call( 'tsmlt-update-media-metadata', [ 'id' => 1, 'title' => 'x' ] );
		$refused = isset( $result['error'] ) || true === ( $result['result']['isError'] ?? false );
		$this->assertTrue( $refused, 'An editor must not run a plugin tool on the default server.' );
	}

	/**
	 * End to end: the execute-ability gateway still cannot reach the
	 * non-public abilities, even with the option on.
	 */
	public function test_gateway_still_refuses_non_public_abilities() {
		$this->build_default_server();
		$this->connect_as( 'administrator' );

		$result = $this->call(
			'mcp-adapter-execute-ability',
			[
				'ability_name' => 'tsmlt/update-media-metadata',
				'parameters'   => [
					'id'    => 1,
					'title' => 'x',
				],
			]
		);
		$refused = isset( $result['error'] ) || true === ( $result['result']['isError'] ?? false );
		$this->assertTrue( $refused );
	}
}
