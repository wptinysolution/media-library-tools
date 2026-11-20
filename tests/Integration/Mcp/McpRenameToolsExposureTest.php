<?php
/**
 * MCP exposure of the rename tools: the dedicated Media Library Tools
 * endpoint lists them with their annotations and runs preview, single
 * rename, bulk rename, status and cancel through the adapter (including its
 * output checks); the MCP Adapter's default gateway neither lists,
 * describes nor runs any of them, and a token sent through it is not used.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\ConfirmationToken;
use TinySolutions\mlt\Modules\Rename\McpRename;
use TinySolutions\mlt\Modules\Rename\RenameJob;

require_once __DIR__ . '/DefaultGatewayClientTrait.php';

/**
 * McpRenameToolsExposureTest
 */
class McpRenameToolsExposureTest extends WP_UnitTestCase {

	use DefaultGatewayClientTrait;

	/**
	 * Dedicated MCP route.
	 *
	 * @var string
	 */
	const SCOPED_ROUTE = '/media-library-tools/mcp';

	/**
	 * The rename abilities.
	 *
	 * @var string[]
	 */
	const ABILITIES = [ 'tsmlt/preview-media-rename', 'tsmlt/rename-media', 'tsmlt/get-media-rename-status', 'tsmlt/cancel-media-rename' ];

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
	 * Attachments to delete.
	 *
	 * @var int[]
	 */
	private $ids = [];

	/**
	 * Administrator; the adapter loaded; no job.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/rename-media' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( RenameJob::STATE_OPTION );
	}

	/**
	 * Remove fixtures.
	 */
	public function tear_down() {
		delete_option( RenameJob::STATE_OPTION );
		wp_clear_scheduled_hook( RenameJob::TICK_HOOK );
		foreach ( $this->ids as $id ) {
			wp_delete_attachment( $id, true );
		}
		parent::tear_down();
	}

	/**
	 * An uploaded JPEG.
	 *
	 * @return int
	 */
	private function jpeg(): int {
		$id          = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->ids[] = $id;
		return $id;
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
	 * @return void
	 */
	private function connect(): void {
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
	 * Preview id => name pairs on the dedicated server.
	 *
	 * @param array<int, string> $names Names.
	 *
	 * @return array Structured content.
	 */
	private function preview( array $names ): array {
		$items = [];
		foreach ( $names as $id => $name ) {
			$items[] = [
				'id'       => $id,
				'filename' => $name,
			];
		}
		$preview = $this->tool( 'tsmlt-preview-media-rename', [ 'items' => $items ] );
		$this->assertFalse( $preview['isError'], wp_json_encode( $preview ) );
		return $preview['structuredContent'];
	}

	/**
	 * The dedicated endpoint lists the four tools with their annotations.
	 */
	public function test_dedicated_endpoint_lists_the_tools() {
		$this->connect();

		$tools = array_column( $this->send( self::SCOPED_ROUTE, 'tools/list' )['result']['tools'], null, 'name' );

		$expected = [
			'tsmlt-preview-media-rename'    => [ false, false ],
			'tsmlt-rename-media'            => [ false, true ],
			'tsmlt-get-media-rename-status' => [ true, false ],
			'tsmlt-cancel-media-rename'     => [ false, false ],
		];
		foreach ( $expected as $name => $hints ) {
			$this->assertArrayHasKey( $name, $tools );
			$this->assertSame( $hints, [ $tools[ $name ]['annotations']['readOnlyHint'], $tools[ $name ]['annotations']['destructiveHint'] ], $name );
			$this->assertFalse( $tools[ $name ]['inputSchema']['additionalProperties'], $name );
		}
		$this->assertSame( [ 'confirmation_token', 'confirm' ], array_keys( $tools['tsmlt-rename-media']['inputSchema']['properties'] ) );
	}

	/**
	 * Single and bulk renames, status and cancel run through the dedicated
	 * endpoint, with outputs that pass the adapter's checks.
	 */
	public function test_flow_through_the_dedicated_endpoint() {
		$single = $this->jpeg();
		$a      = $this->jpeg();
		$b      = $this->jpeg();
		$c      = $this->jpeg();
		$this->connect();

		$preview = $this->preview( [ $single => 'mcp-endpoint-single' ] );
		$renamed = $this->tool(
			'tsmlt-rename-media',
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$this->assertFalse( $renamed['isError'], wp_json_encode( $renamed ) );
		$this->assertSame( 'single', $renamed['structuredContent']['mode'] );
		$this->assertSame( 'mcp-endpoint-single.jpg', wp_basename( get_attached_file( $single ) ) );

		$preview = $this->preview(
			[
				$a => 'mcp-endpoint-a',
				$b => 'mcp-endpoint-b',
			]
		);
		$bulk    = $this->tool(
			'tsmlt-rename-media',
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$this->assertFalse( $bulk['isError'], wp_json_encode( $bulk ) );
		$this->assertSame( 'bulk', $bulk['structuredContent']['mode'] );
		$job_id = $bulk['structuredContent']['job_id'];

		RenameJob::instance()->run_batch();
		$status = $this->tool( 'tsmlt-get-media-rename-status', [] );
		$this->assertFalse( $status['isError'], wp_json_encode( $status ) );
		$this->assertSame( 'done', $status['structuredContent']['status'] );
		$this->assertSame( 2, $status['structuredContent']['renamed'] );

		$preview = $this->preview(
			[
				$a => 'mcp-endpoint-a2',
				$c => 'mcp-endpoint-c',
			]
		);
		$bulk    = $this->tool(
			'tsmlt-rename-media',
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$cancel  = $this->tool( 'tsmlt-cancel-media-rename', [ 'job_id' => $bulk['structuredContent']['job_id'] ] );
		$this->assertFalse( $cancel['isError'], wp_json_encode( $cancel ) );
		$this->assertSame( 'cancelled', $cancel['structuredContent']['status'] );
		$this->assertNotSame( $job_id, $bulk['structuredContent']['job_id'] );
	}

	/**
	 * The default gateway neither lists, describes nor runs any rename tool;
	 * a valid token sent through it is not used and nothing is renamed.
	 */
	public function test_not_on_the_default_gateway() {
		$id   = $this->jpeg();
		$file = get_attached_file( $id );
		$this->gw_connect_as( 'administrator' );
		// Issued to the gateway's user, so only the gateway could refuse it.
		$token = wp_get_ability( 'tsmlt/preview-media-rename' )->execute(
			[
				'items' => [
					[
						'id'       => $id,
						'filename' => 'mcp-gateway-new',
					],
				],
			]
		)['confirmation_token'];

		$listed = array_column( $this->gw_call( 'mcp-adapter-discover-abilities', [] )['structuredContent']['abilities'], 'name' );
		foreach ( self::ABILITIES as $name ) {
			$this->assertNotContains( $name, $listed, $name );
			$this->assertTrue( $this->gw_call( 'mcp-adapter-get-ability-info', [ 'ability_name' => $name ] )['isError'], $name );
		}
		$run    = $this->gw_call(
			'mcp-adapter-execute-ability',
			self::gw_execute_args(
				'tsmlt/rename-media',
				[
					'confirmation_token' => $token,
					'confirm'            => true,
				]
			)
		);
		$status = $this->gw_call( 'mcp-adapter-execute-ability', self::gw_execute_args( 'tsmlt/get-media-rename-status', [] ) );

		$this->assertTrue( $run['isError'] );
		$this->assertTrue( $status['isError'] );
		$this->assertSame( $file, get_attached_file( $id ) );
		$this->assertNotFalse( get_option( ConfirmationToken::option( McpRename::TOKEN_OPTION_PREFIX, $token ) ), 'The token was not used.' );
	}
}
