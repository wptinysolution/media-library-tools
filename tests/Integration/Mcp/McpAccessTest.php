<?php
/**
 * "Turn off MCP access" (McpAccess): on by default; when turned off every
 * Media Library Tools ability, the dedicated endpoint and the plugin's
 * entries on the default MCP server are refused or hidden, while other
 * abilities and servers are not touched; turning it back on restores
 * access for an existing session.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\McpAccess;
use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Controllers\Admin\McpDocsPage;
use TinySolutions\mlt\Modules\Mcp\McpConnection;

require_once __DIR__ . '/DefaultGatewayClientTrait.php';

/**
 * McpAccessTest
 */
class McpAccessTest extends WP_UnitTestCase {

	use DefaultGatewayClientTrait;

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
	 * Administrator; access on.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( McpAccess::OPTION );
		$this->sessions = [];
	}

	/**
	 * Access back on.
	 */
	public function tear_down() {
		delete_option( McpAccess::OPTION );
		parent::tear_down();
	}

	/**
	 * On by default; the switch stores and reads back.
	 */
	public function test_default_and_switch() {
		$this->assertTrue( McpAccess::is_enabled() );
		$this->assertTrue( AbilityGuard::check_base_capability() );

		$this->assertTrue( McpAccess::set_disabled( true ) );
		$this->assertFalse( McpAccess::is_enabled() );
		$this->assertSame( AbilityGuard::ERROR_MCP_DISABLED, AbilityGuard::check_base_capability()->get_error_code() );

		$this->assertTrue( McpAccess::set_disabled( false ) );
		$this->assertTrue( McpAccess::is_enabled() );
		$this->assertTrue( AbilityGuard::check_base_capability() );
	}

	/**
	 * Every Media Library Tools ability is refused while access is off, in
	 * its permission callback and when run, and works again after.
	 */
	public function test_abilities_refused_while_off() {
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/search-media' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		McpAccess::set_disabled( true );
		$names = McpServerRegistration::instance()->get_tool_names();
		$this->assertContains( 'tsmlt/search-media', $names );
		foreach ( $names as $name ) {
			$this->assertFalse( wp_get_ability( $name )->check_permissions( [] ), $name );
		}
		$this->assertSame( AbilityGuard::ERROR_MCP_DISABLED, \TinySolutions\mlt\Abilities\Media\SearchMedia::instance()->execute( [] )->get_error_code() );
		$this->assertFalse( McpServerRegistration::instance()->check_transport_permission() );

		McpAccess::set_disabled( false );
		$this->assertTrue( wp_get_ability( 'tsmlt/search-media' )->check_permissions( [] ) );
		$this->assertIsArray( wp_get_ability( 'tsmlt/search-media' )->execute( [] ) );
		$this->assertTrue( McpServerRegistration::instance()->check_transport_permission() );
	}

	/**
	 * The plugin's tools and resources are left off and hidden from the
	 * default server; other entries are kept.
	 */
	public function test_default_server_lists_only_others_while_off() {
		$registration = McpServerRegistration::instance();
		$config       = [
			'tools'     => [ 'core/other-ability' ],
			'resources' => [],
		];
		McpAccess::set_disabled( true );
		$this->assertSame( $config, $registration->add_to_default_server( $config ), 'Nothing added.' );

		$tools  = [ [ 'name' => 'other-plugin-tool' ] ];
		$listed = $registration->hide_tools_from_unauthorized(
			array_merge(
				$tools,
				array_map(
					static function ( $name ) {
						return [ 'name' => str_replace( '/', '-', $name ) ];
					},
					[ 'tsmlt/search-media', 'tsmlt/get-media-details' ]
				)
			)
		);
		$this->assertSame( $tools, $listed, 'Only the other plugin\'s tool stays listed.' );

		McpAccess::set_disabled( false );
		if ( McpServerRegistration::adapter_supports_default_server() ) {
			$this->assertContains( 'tsmlt/search-media', $registration->add_to_default_server( $config )['tools'] );
		}
	}

	/**
	 * Abilities of other plugins and WordPress are not affected.
	 */
	public function test_other_abilities_unaffected() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		McpAccess::set_disabled( true );
		$checked = 0;
		foreach ( wp_get_abilities() as $ability ) {
			if ( 0 === strpos( $ability->get_name(), 'tsmlt/' ) ) {
				continue;
			}
			$result = $ability->check_permissions( null );
			$this->assertFalse( is_wp_error( $result ) && AbilityGuard::ERROR_MCP_DISABLED === $result->get_error_code(), $ability->get_name() );
			++$checked;
		}
		$this->assertGreaterThanOrEqual( 0, $checked );
	}

	/**
	 * Both endpoints: an existing session loses the plugin's tools while
	 * access is off and gets them back after, without reconnecting.
	 */
	public function test_both_endpoints_with_existing_sessions() {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}
		$dedicated = '/media-library-tools/mcp';
		$default   = '/mcp/' . McpDocsPage::CLIENT_KEY_DEFAULT_SERVER;
		$this->connect( $dedicated );
		$this->connect( $default );
		$this->assertContains( 'tsmlt-search-media', $this->tool_names( $dedicated ) );
		$this->assertContains( 'tsmlt-search-media', $this->tool_names( $default ) );

		McpAccess::set_disabled( true );

		$this->assertSame( [], $this->tool_names( $dedicated ), 'The dedicated endpoint refuses.' );
		$this->assertNotContains( 'tsmlt-search-media', $this->tool_names( $default ), 'Gone from the default server.' );
		$call = $this->send(
			$default,
			'tools/call',
			[
				'name'      => 'tsmlt-search-media',
				'arguments' => (object) [],
			]
		);
		$this->assertTrue( isset( $call['error'] ) || ! empty( $call['result']['isError'] ), wp_json_encode( $call ) );

		McpAccess::set_disabled( false );

		$this->assertContains( 'tsmlt-search-media', $this->tool_names( $dedicated ), 'Back without reconnecting.' );
		$this->assertContains( 'tsmlt-search-media', $this->tool_names( $default ) );
	}

	/**
	 * The default server's gateway cannot run the plugin's abilities while
	 * access is off (the only ones it may run are search and details).
	 */
	public function test_gateway_refused_while_off() {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}
		$this->gw_connect_as( 'administrator' );
		$this->assertFalse( $this->gw_call( 'mcp-adapter-execute-ability', self::gw_execute_args( 'tsmlt/search-media', [] ) )['isError'], 'Precondition: allowed while on.' );
		McpAccess::set_disabled( true );
		$this->assertTrue( $this->gw_call( 'mcp-adapter-execute-ability', self::gw_execute_args( 'tsmlt/search-media', [] ) )['isError'] );
		$this->assertTrue( $this->gw_call( 'mcp-adapter-execute-ability', self::gw_execute_args( 'tsmlt/get-media-details', [ 'id' => 1 ] ) )['isError'] );
	}

	/**
	 * The MCP page's connection URL is the default server on this site.
	 */
	public function test_connection_url() {
		$settings = McpConnection::instance()->settings();
		$this->assertSame( rest_url( 'mcp/mcp-adapter-default-server' ), $settings['serverUrl'] );
		$this->assertStringStartsWith( home_url(), $settings['serverUrl'], 'Built from this site, not a fixed domain.' );
		$this->assertSame( McpDocsPage::instance()->get_client_server_key( McpDocsPage::CLIENT_KEY_DEFAULT_SERVER ), $settings['serverName'] );
		$this->assertTrue( $settings['enabled'] );
		McpAccess::set_disabled( true );
		$this->assertFalse( McpConnection::instance()->settings()['enabled'] );
	}

	/**
	 * Names from tools/list on a route ([] when refused).
	 *
	 * @param string $route Route.
	 *
	 * @return string[]
	 */
	private function tool_names( string $route ): array {
		$list = $this->send( $route, 'tools/list' );
		return isset( $list['result']['tools'] ) ? wp_list_pluck( $list['result']['tools'], 'name' ) : [];
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
			$this->sessions[ $route ] = $response->get_headers()['Mcp-Session-Id'] ?? '';
		}
		$data = $response->get_data();
		return is_array( $data ) ? $data : (array) json_decode( (string) wp_json_encode( $data ), true );
	}

	/**
	 * Open a session.
	 *
	 * @param string $route Route.
	 */
	private function connect( string $route ) {
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
}
