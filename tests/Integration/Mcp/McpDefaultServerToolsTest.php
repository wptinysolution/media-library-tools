<?php
/**
 * The Media Library Tools tools and MCP App resources are always listed
 * directly on the MCP Adapter's default server
 * (`mcp_adapter_default_server_config`), next to the dedicated server.
 *
 * No setting or filter turns this on or off; only an MCP Adapter older than
 * 0.5.0 skips it. No ability becomes `meta.mcp.public`, so the default
 * server's execute-ability gateway still reaches only search and details.
 * The plugin's tools and resources are hidden from list results for users
 * without manage_options, and every call still runs each ability's checks.
 *
 * Pro is simulated by a stand-in that adds its names through Free's
 * extension points only while "licensed", as ProAbilitiesInit does; the real
 * Pro plugin is never loaded. The end-to-end tests use the real default
 * server and the dedicated endpoint, and are skipped unless MCP_ADAPTER_DIR
 * is set (see tests/README.md).
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Helpers\Fns;

/**
 * McpDefaultServerToolsTest
 */
class McpDefaultServerToolsTest extends WP_UnitTestCase {

	/**
	 * Default server route.
	 *
	 * @var string
	 */
	const DEFAULT_ROUTE = '/mcp/mcp-adapter-default-server';

	/**
	 * Dedicated server route.
	 *
	 * @var string
	 */
	const DEDICATED_ROUTE = '/media-library-tools/mcp';

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
	 * Stand-in Pro MCP App view.
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
	 * Real Pro tool names, which never appear without Pro.
	 *
	 * @var string[]
	 */
	const PRO_TOOL_NAMES = [ 'tsmlt-export-media-csv-bulk', 'tsmlt-get-media-csv-export', 'tsmlt-continue-media-csv-export', 'tsmlt-read-media-csv-export-file', 'tsmlt-get-exif-caption', 'tsmlt-update-exif-fields', 'tsmlt-preview-media-csv-import', 'tsmlt-apply-media-csv-import' ];

	/**
	 * Whether the stand-in Pro licence is active.
	 *
	 * @var bool
	 */
	private $licensed = false;

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
	 * Register the stand-ins and the stand-in Pro extension.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}

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

		// Mirrors ProAbilitiesInit: names are added only while licensed.
		$this->licensed = false;
		add_filter( McpServerRegistration::TOOLS_FILTER, [ $this, 'add_pro_tools' ] );
		add_filter( McpServerRegistration::RESOURCES_FILTER, [ $this, 'add_pro_resources' ] );
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
		remove_all_filters( McpServerRegistration::TOOLS_FILTER );
		remove_all_filters( McpServerRegistration::RESOURCES_FILTER );
		delete_option( 'tsmlt_settings' );
		parent::tear_down();
	}

	/**
	 * Stand-in Pro tools filter.
	 *
	 * @param mixed $names Names so far.
	 *
	 * @return mixed
	 */
	public function add_pro_tools( $names ) {
		if ( is_array( $names ) && $this->licensed ) {
			$names[] = self::PRO_TOOL;
		}
		return $names;
	}

	/**
	 * Stand-in Pro resources filter.
	 *
	 * @param mixed $names Names so far.
	 *
	 * @return mixed
	 */
	public function add_pro_resources( $names ) {
		if ( is_array( $names ) && $this->licensed ) {
			$names[] = self::VIEW;
		}
		return $names;
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
	 * The opt-in is gone: no setting, constant or filter remains, and a
	 * value saved by the earlier opt-in does not turn the tools off.
	 */
	public function test_no_opt_in_remains() {
		$this->require_adapter();

		$this->assertFalse( defined( McpServerRegistration::class . '::DEFAULT_SERVER_SETTING' ) );
		$this->assertFalse( defined( McpServerRegistration::class . '::DEFAULT_SERVER_FILTER' ) );
		$this->assertFalse( method_exists( McpServerRegistration::class, 'is_default_server_enabled' ) );
		$this->assertArrayNotHasKey( 'mcp_default_server_enabled', Fns::get_options() );

		update_option( 'tsmlt_settings', [ 'mcp_default_server_enabled' => 0 ] );
		add_filter( 'tsmlt_mcp_default_server_enabled', '__return_false' );
		$tools = McpServerRegistration::instance()->add_to_default_server( $this->default_config() )['tools'];
		remove_filter( 'tsmlt_mcp_default_server_enabled', '__return_false' );

		$this->assertContains( 'tsmlt/export-media-csv', $tools );
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
	 * Adapter versions before 0.5.0 (or without a version) are refused.
	 */
	public function test_adapter_version_gate() {
		$this->assertFalse( McpServerRegistration::adapter_supports_default_server( '0' ) );
		$this->assertFalse( McpServerRegistration::adapter_supports_default_server( '0.4.9' ) );
		$this->assertTrue( McpServerRegistration::adapter_supports_default_server( '0.5.0' ) );
		$this->assertTrue( McpServerRegistration::adapter_supports_default_server( '0.7.1' ) );
	}

	/**
	 * Every Free tool, including tsmlt/export-media-csv, is added; the gateway
	 * tools and other resources are kept first; nothing is listed twice;
	 * without Pro no Pro tool or view is added.
	 */
	public function test_free_tools_always_merged() {
		$this->require_adapter();

		$config            = $this->default_config();
		$config['tools'][] = 'tsmlt/search-media';

		$result = McpServerRegistration::instance()->add_to_default_server( $config );
		$tools  = $result['tools'];

		$this->assertSame( array_slice( $config['tools'], 0, 3 ), array_slice( $tools, 0, 3 ), 'The gateway tools are kept first.' );
		$this->assertSame( array_values( array_unique( $tools ) ), $tools, 'No tool is listed twice.' );
		foreach ( AbilitiesInit::instance()->get_ability_names() as $name ) {
			$this->assertContains( $name, $tools );
		}
		$this->assertContains( 'tsmlt/export-media-csv', $tools );
		$this->assertNotContains( self::PRO_TOOL, $tools );
		$this->assertNotContains( 'tsmlt/export-media-csv-bulk', $tools );
		$this->assertSame( [ 'other-plugin/public-resource' ], $result['resources'] );
		$this->assertSame( $config['prompts'], $result['prompts'] );
	}

	/**
	 * Pro's tools and view are added only while Pro adds them (licensed);
	 * names outside the plugin's category are refused.
	 */
	public function test_pro_tools_only_while_licensed() {
		$this->require_adapter();
		$server = McpServerRegistration::instance();

		$unlicensed = $server->add_to_default_server( $this->default_config() );
		$this->assertNotContains( self::PRO_TOOL, $unlicensed['tools'] );
		$this->assertNotContains( self::VIEW, $unlicensed['resources'] );

		$this->licensed = true;
		$licensed       = $server->add_to_default_server( $this->default_config() );
		$this->assertContains( self::PRO_TOOL, $licensed['tools'] );
		$this->assertSame( [ 'other-plugin/public-resource', self::VIEW ], $licensed['resources'] );
		$this->assertSame( array_values( array_unique( $licensed['tools'] ) ), $licensed['tools'] );

		add_filter(
			McpServerRegistration::TOOLS_FILTER,
			static function ( $names ) {
				$names[] = self::FOREIGN_ABILITY;
				return $names;
			}
		);
		$this->setExpectedIncorrectUsage( McpServerRegistration::TOOLS_FILTER );
		$this->assertNotContains( self::FOREIGN_ABILITY, $server->add_to_default_server( $this->default_config() )['tools'] );
	}

	/**
	 * A non-array configuration is passed through.
	 */
	public function test_non_array_config_is_passed_through() {
		$this->assertSame( 'broken', McpServerRegistration::instance()->add_to_default_server( 'broken' ) );
	}

	/**
	 * No plugin ability is MCP-public except search and details, so the
	 * execute-ability gateway reaches nothing else.
	 */
	public function test_mcp_public_flags_are_unchanged() {
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
		$visible   = [ [ 'name' => 'mcp-adapter-discover-abilities' ], [ 'name' => 'other-plugin-test-tool' ], 'unreadable' ];

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( $tools, $server->hide_tools_from_unauthorized( $tools ) );
		$this->assertSame( $resources, $server->hide_resources_from_unauthorized( $resources ) );

		foreach ( [ 'editor', 'author', 'subscriber' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertSame( $visible, $server->hide_tools_from_unauthorized( $tools ), $role );
			$this->assertSame( [ [ 'uri' => 'ui://other/view.html' ] ], $server->hide_resources_from_unauthorized( $resources ), $role );
		}

		wp_set_current_user( 0 );
		$this->assertSame( $visible, $server->hide_tools_from_unauthorized( $tools ) );
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
	 * Send one JSON-RPC message.
	 *
	 * @param string $route  Server route.
	 * @param string $method JSON-RPC method.
	 * @param array  $params Params.
	 *
	 * @return array Decoded response body.
	 */
	private function send( string $route, string $method, array $params = [] ): array {
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
		$data = rest_get_server()->dispatch( $request )->get_data();
		return is_array( $data ) ? $data : (array) json_decode( (string) wp_json_encode( $data ), true );
	}

	/**
	 * Log in as a new user with a role and open a session on a route.
	 *
	 * @param string $role  Role.
	 * @param string $route Server route.
	 *
	 * @return int User ID.
	 */
	private function connect_as( string $role, string $route ): int {
		$user_id = self::factory()->user->create( [ 'role' => $role ] );
		wp_set_current_user( $user_id );
		unset( $this->sessions[ $route ] );

		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'Accept', 'application/json, text/event-stream' );
		$request->set_body(
			wp_json_encode(
				[
					'jsonrpc' => '2.0',
					'id'      => ++$this->request_id,
					'method'  => 'initialize',
					'params'  => [
						'protocolVersion' => self::PROTOCOL_VERSION,
						'capabilities'    => (object) [],
						'clientInfo'      => [
							'name'    => 'tsmlt-tests',
							'version' => '1.0.0',
						],
					],
				]
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), $route );
		$headers                  = $response->get_headers();
		$this->sessions[ $route ] = $headers['Mcp-Session-Id'] ?? '';
		$this->send( $route, 'notifications/initialized' );
		return $user_id;
	}

	/**
	 * Tools from tools/list on a route.
	 *
	 * @param string $route Server route.
	 *
	 * @return array<string, array> Tools by name.
	 */
	private function list_tools( string $route ): array {
		return array_column( $this->send( $route, 'tools/list' )['result']['tools'], null, 'name' );
	}

	/**
	 * Call a tool.
	 *
	 * @param string $route     Server route.
	 * @param string $tool      Tool name.
	 * @param array  $arguments Arguments.
	 *
	 * @return array JSON-RPC message.
	 */
	private function call( string $route, string $tool, array $arguments ): array {
		return $this->send(
			$route,
			'tools/call',
			[
				'name'      => $tool,
				'arguments' => (object) $arguments,
			]
		);
	}

	/**
	 * Whether a tools/call message is a refusal.
	 *
	 * @param array $message JSON-RPC message.
	 *
	 * @return bool
	 */
	private function is_refused( array $message ): bool {
		return isset( $message['error'] ) || true === ( $message['result']['isError'] ?? false );
	}

	/**
	 * End to end on the real default server: administrators get every Free
	 * tool directly, next to the gateway tools, with their annotations; no
	 * Pro tool without Pro; tsmlt-export-media-csv runs.
	 */
	public function test_default_server_lists_and_runs_free_tools() {
		$this->require_adapter();
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
				'post_title'     => 'Default server probe',
				'file'           => '2026/11/default-server-probe.jpg',
			]
		);
		$this->connect_as( 'administrator', self::DEFAULT_ROUTE );

		$tools = $this->list_tools( self::DEFAULT_ROUTE );
		$this->assertArrayHasKey( 'mcp-adapter-execute-ability', $tools );
		foreach ( AbilitiesInit::instance()->get_ability_names() as $ability ) {
			$this->assertArrayHasKey( str_replace( '/', '-', $ability ), $tools, $ability );
		}
		$this->assertArrayHasKey( 'tsmlt-export-media-csv', $tools );
		foreach ( self::PRO_TOOL_NAMES as $pro ) {
			$this->assertArrayNotHasKey( $pro, $tools, 'No Pro tool without Pro.' );
		}
		$this->assertTrue( $tools['tsmlt-search-media']['annotations']['readOnlyHint'] );
		$this->assertTrue( $tools['tsmlt-update-media-metadata']['annotations']['destructiveHint'] );
		$this->assertTrue( $tools['tsmlt-trash-media']['annotations']['destructiveHint'] );

		$result = $this->call( self::DEFAULT_ROUTE, 'tsmlt-export-media-csv', [ 'ids' => [ $id ] ] );
		$this->assertFalse( $this->is_refused( $result ), wp_json_encode( $result ) );
		$this->assertStringContainsString( 'Default server probe', (string) wp_json_encode( $result['result'] ) );
	}

	/**
	 * End to end: the dedicated endpoint still works and offers the same
	 * Media Library Tools tools as the default server.
	 */
	public function test_dedicated_endpoint_matches_default_server() {
		$this->require_adapter();

		$this->connect_as( 'administrator', self::DEFAULT_ROUTE );
		$default = preg_grep( '/^tsmlt-/', array_keys( $this->list_tools( self::DEFAULT_ROUTE ) ) );

		$this->connect_as( 'administrator', self::DEDICATED_ROUTE );
		$dedicated = $this->list_tools( self::DEDICATED_ROUTE );
		$this->assertArrayNotHasKey( 'mcp-adapter-execute-ability', $dedicated );

		$dedicated = array_keys( $dedicated );
		sort( $default );
		sort( $dedicated );
		$this->assertSame( $dedicated, array_values( $default ) );

		$result = $this->call( self::DEDICATED_ROUTE, 'tsmlt-search-media', [ 'per_page' => 1 ] );
		$this->assertFalse( $this->is_refused( $result ), wp_json_encode( $result ) );
	}

	/**
	 * End to end: users without manage_options can connect to the default
	 * server (the adapter's rule) but see none of the plugin's tools, and
	 * every call — read or write — is refused without changing anything.
	 */
	public function test_default_server_enforces_permissions() {
		$this->require_adapter();
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
				'post_title'     => 'Original title',
				'file'           => '2026/11/permission-probe.jpg',
			]
		);

		foreach ( [ 'editor', 'author', 'subscriber' ] as $role ) {
			$this->connect_as( $role, self::DEFAULT_ROUTE );

			$names = array_keys( $this->list_tools( self::DEFAULT_ROUTE ) );
			$this->assertContains( 'mcp-adapter-discover-abilities', $names, $role );
			$this->assertSame( [], array_values( preg_grep( '/^tsmlt-/', $names ) ), $role );

			$this->assertTrue( $this->is_refused( $this->call( self::DEFAULT_ROUTE, 'tsmlt-export-media-csv', [ 'ids' => [ $id ] ] ) ), $role );
			$this->assertTrue(
				$this->is_refused(
					$this->call(
						self::DEFAULT_ROUTE,
						'tsmlt-update-media-metadata',
						[
							'id'    => $id,
							'title' => 'Changed by ' . $role,
						]
					)
				),
				$role
			);
			$this->assertTrue( $this->is_refused( $this->call( self::DEFAULT_ROUTE, 'tsmlt-trash-media', [ 'token' => 'x' ] ) ), $role );
		}

		$this->assertSame( 'Original title', get_post( $id )->post_title );
		$this->assertSame( 'inherit', get_post( $id )->post_status, 'Not trashed.' );
	}

	/**
	 * End to end: the execute-ability gateway still refuses the non-public
	 * abilities for administrators.
	 */
	public function test_gateway_still_refuses_non_public_abilities() {
		$this->require_adapter();
		$this->connect_as( 'administrator', self::DEFAULT_ROUTE );

		foreach ( [ 'tsmlt/update-media-metadata', 'tsmlt/export-media-csv', 'tsmlt/trash-media' ] as $ability ) {
			$result = $this->call(
				self::DEFAULT_ROUTE,
				'mcp-adapter-execute-ability',
				[
					'ability_name' => $ability,
					'parameters'   => (object) [],
				]
			);
			$this->assertTrue( $this->is_refused( $result ), $ability );
		}
	}
}
