<?php
/**
 * MCP exposure of the trash tools: the dedicated Media Library Tools
 * endpoint lists them with their annotations and runs the whole
 * preview, trash, list and restore flow through the adapter (including its
 * output checks); the MCP Adapter's default gateway neither lists,
 * describes nor runs any of them.
 *
 * Every attachment is a generated fixture. Outgoing HTTP is blocked.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Modules\Trash\MediaTrash;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

require_once __DIR__ . '/DefaultGatewayClientTrait.php';

/**
 * McpTrashToolsExposureTest
 */
class McpTrashToolsExposureTest extends WP_UnitTestCase {

	use DefaultGatewayClientTrait;

	/**
	 * Dedicated MCP route.
	 *
	 * @var string
	 */
	const SCOPED_ROUTE = '/media-library-tools/mcp';

	/**
	 * The trash abilities.
	 *
	 * @var string[]
	 */
	const ABILITIES = [ 'tsmlt/list-trashed-media', 'tsmlt/preview-trash-media', 'tsmlt/trash-media', 'tsmlt/restore-media' ];

	/**
	 * What clients are told about permanent deletion, word for word.
	 *
	 * @var string
	 */
	const GUIDANCE = 'Permanent deletion is not available through MCP. Before permanently deleting media, inspect the entire website for dependencies, create and verify a backup, then log in to WordPress Admin and carefully perform the deletion manually.';

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
	 * Administrator; a verified usage scan; the adapter loaded.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/trash-media' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
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
	 * Remove filters.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
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
	 * A generated attachment.
	 *
	 * @return int
	 */
	private function attachment(): int {
		return (int) self::factory()->attachment->create(
			[
				'file'           => 'mcp-trash/image.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_date'      => '2020-01-01 00:00:00',
			]
		);
	}

	/**
	 * A post's stored status, read fresh.
	 *
	 * @param int $id Post ID.
	 *
	 * @return string
	 */
	private static function status( int $id ): string {
		clean_post_cache( $id );
		return (string) get_post( $id )->post_status;
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
	 *
	 * @return array The initialize response.
	 */
	private function connect(): array {
		$initialize = $this->send(
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
		return $initialize;
	}

	/**
	 * Call a tool on the dedicated server.
	 *
	 * @param string $tool      Tool name.
	 * @param array  $arguments Arguments.
	 *
	 * @return array The JSON-RPC result.
	 */
	private function tool( string $tool, array $arguments ): array {
		return $this->send(
			self::SCOPED_ROUTE,
			'tools/call',
			[
				'name'      => $tool,
				'arguments' => (object) $arguments,
			]
		)['result'];
	}

	/**
	 * The dedicated endpoint lists the four tools with their annotations.
	 */
	public function test_dedicated_endpoint_lists_the_tools() {
		$this->connect();

		$tools = array_column( $this->send( self::SCOPED_ROUTE, 'tools/list' )['result']['tools'], null, 'name' );

		$expected = [
			'tsmlt-list-trashed-media'  => [ true, false ],
			'tsmlt-preview-trash-media' => [ true, false ],
			'tsmlt-trash-media'         => [ false, true ],
			'tsmlt-restore-media'       => [ false, false ],
		];
		foreach ( $expected as $name => $hints ) {
			$this->assertArrayHasKey( $name, $tools );
			$this->assertSame( $hints, [ $tools[ $name ]['annotations']['readOnlyHint'], $tools[ $name ]['annotations']['destructiveHint'] ], $name );
			$this->assertFalse( $tools[ $name ]['inputSchema']['additionalProperties'], $name );
		}
	}

	/**
	 * Clients are told, word for word, that permanent deletion is not
	 * available: in the server's instructions and in every trash tool's
	 * description. No tool can delete permanently or empty the trash, and
	 * no trash tool takes a delete-related input.
	 */
	public function test_permanent_deletion_is_not_offered() {
		$initialize = $this->connect();
		$this->assertStringContainsString( self::GUIDANCE, $initialize['result']['instructions'] );
		$this->assertSame( self::GUIDANCE, MediaTrash::permanent_delete_guidance() );

		$tools = array_column( $this->send( self::SCOPED_ROUTE, 'tools/list' )['result']['tools'], null, 'name' );
		foreach ( self::ABILITIES as $name ) {
			$this->assertStringContainsString( self::GUIDANCE, $tools[ str_replace( '/', '-', $name ) ]['description'], $name );
			$this->assertStringContainsString( self::GUIDANCE, wp_get_ability( $name )->get_description(), $name );
		}
		foreach ( array_keys( $tools ) as $name ) {
			$this->assertDoesNotMatchRegularExpression( '/delete|empty|purge/', $name, 'No permanent-delete or empty-trash tool.' );
		}
		$inputs = [
			'tsmlt-list-trashed-media'  => [ 'page', 'per_page' ],
			'tsmlt-preview-trash-media' => [ 'ids' ],
			'tsmlt-trash-media'         => [ 'ids', 'confirmation_token' ],
			'tsmlt-restore-media'       => [ 'ids' ],
		];
		foreach ( $inputs as $name => $properties ) {
			$this->assertSame( $properties, array_keys( $tools[ $name ]['inputSchema']['properties'] ), $name );
		}
	}

	/**
	 * Preview, trash, list and restore run end to end through the
	 * dedicated endpoint, with outputs that pass the adapter's checks.
	 */
	public function test_flow_through_the_dedicated_endpoint() {
		$id = $this->attachment();
		$this->connect();

		$preview = $this->tool( 'tsmlt-preview-trash-media', [ 'ids' => [ $id ] ] );
		$this->assertFalse( $preview['isError'], wp_json_encode( $preview ) );
		$token = $preview['structuredContent']['confirmation_token'];

		$trash = $this->tool(
			'tsmlt-trash-media',
			[
				'ids'                => [ $id ],
				'confirmation_token' => $token,
			]
		);
		$this->assertFalse( $trash['isError'], wp_json_encode( $trash ) );
		$this->assertSame( 'trashed', $trash['structuredContent']['items'][0]['result'] );
		$this->assertSame( 'trash', self::status( $id ) );

		$list = $this->tool( 'tsmlt-list-trashed-media', [] );
		$this->assertFalse( $list['isError'], wp_json_encode( $list ) );
		$this->assertSame( [ $id ], array_column( $list['structuredContent']['items'], 'id' ) );

		$restore = $this->tool( 'tsmlt-restore-media', [ 'ids' => [ $id ] ] );
		$this->assertFalse( $restore['isError'], wp_json_encode( $restore ) );
		$this->assertSame( 'restored', $restore['structuredContent']['items'][0]['result'] );
		$this->assertSame( 'inherit', self::status( $id ) );
	}

	/**
	 * The default gateway neither lists, describes nor runs any trash tool;
	 * a valid token sent through it is not used and nothing is trashed.
	 */
	public function test_not_on_the_default_gateway() {
		$id = $this->attachment();
		$this->gw_connect_as( 'administrator' );
		// Issued to the gateway's user, so only the gateway could refuse it.
		$token = wp_get_ability( 'tsmlt/preview-trash-media' )->execute( [ 'ids' => [ $id ] ] )['confirmation_token'];

		$listed = array_column( $this->gw_call( 'mcp-adapter-discover-abilities', [] )['structuredContent']['abilities'], 'name' );
		foreach ( self::ABILITIES as $name ) {
			$this->assertNotContains( $name, $listed, $name );
			$this->assertTrue( $this->gw_call( 'mcp-adapter-get-ability-info', [ 'ability_name' => $name ] )['isError'], $name );
		}
		$run = $this->gw_call(
			'mcp-adapter-execute-ability',
			self::gw_execute_args(
				'tsmlt/trash-media',
				[
					'ids'                => [ $id ],
					'confirmation_token' => $token,
				]
			)
		);
		$restore = $this->gw_call( 'mcp-adapter-execute-ability', self::gw_execute_args( 'tsmlt/restore-media', [ 'ids' => [ $id ] ] ) );

		$this->assertTrue( $run['isError'] );
		$this->assertTrue( $restore['isError'] );
		$this->assertSame( 'inherit', self::status( $id ) );
		$this->assertNotFalse( get_option( MediaTrash::TOKEN_OPTION_PREFIX . hash( 'sha256', $token ) ), 'The token was not used.' );
	}
}
