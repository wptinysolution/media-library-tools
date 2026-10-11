<?php
/**
 * End-to-end tests through the official MCP Adapter plugin.
 *
 * Sends real MCP JSON-RPC messages to the adapter's default server route and
 * checks that `tsmlt/search-media` can be discovered and executed. Skipped
 * unless MCP_ADAPTER_DIR points at an unpacked copy of the mcp-adapter plugin
 * (see tests/README.md).
 *
 * @package TinySolutions\mlt
 */

/**
 * McpAdapterTest
 */
class McpAdapterTest extends WP_UnitTestCase {

	/**
	 * Default server route.
	 *
	 * @var string
	 */
	const ROUTE = '/mcp/mcp-adapter-default-server';

	/**
	 * MCP protocol revision the tests negotiate.
	 *
	 * @var string
	 */
	const PROTOCOL_VERSION = '2025-11-25';

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
	 * Skip when the adapter is not loaded.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}
		$this->session_id = null;
	}

	/**
	 * Send one JSON-RPC message.
	 *
	 * @param string $method JSON-RPC method.
	 * @param array  $params Params.
	 *
	 * @return WP_REST_Response
	 */
	private function send( string $method, array $params = [] ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
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
	 * Run the initialize handshake and keep the session ID.
	 *
	 * @return array Decoded initialize result.
	 */
	private function initialize(): array {
		$response = $this->send(
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
		$data = $this->decode( $response );
		$this->assertArrayHasKey( 'result', $data, wp_json_encode( $data ) );

		$headers = $response->get_headers();
		if ( isset( $headers['Mcp-Session-Id'] ) ) {
			$this->session_id = $headers['Mcp-Session-Id'];
		}

		$this->send( 'notifications/initialized' );
		return $data['result'];
	}

	/**
	 * Decode a response body to an array.
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
	 * Call an MCP tool and return the JSON-RPC result.
	 *
	 * @param string $tool      Tool name.
	 * @param array  $arguments Tool arguments.
	 *
	 * @return array
	 */
	private function call_tool( string $tool, array $arguments ): array {
		$data = $this->decode(
			$this->send(
				'tools/call',
				[
					'name'      => $tool,
					'arguments' => (object) $arguments,
				]
			)
		);
		$this->assertArrayHasKey( 'result', $data, wp_json_encode( $data ) );
		return $data['result'];
	}

	/**
	 * Initialize a session as a new user with the given role.
	 *
	 * @param string $role Role name.
	 */
	private function connect_as( string $role ) {
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
		$this->session_id = null;
		$this->initialize();
	}

	/**
	 * Execute an ability through the adapter's execute-ability tool.
	 *
	 * @param array $parameters Ability parameters.
	 *
	 * @return array Structured content from the tool result.
	 */
	private function execute_search( array $parameters ): array {
		$result = $this->call_tool(
			'mcp-adapter-execute-ability',
			[
				'ability_name' => 'tsmlt/search-media',
				'parameters'   => (object) $parameters,
			]
		);
		$this->assertArrayHasKey( 'structuredContent', $result, wp_json_encode( $result ) );
		return $result;
	}

	/**
	 * The ability is listed by discover-abilities.
	 */
	public function test_discoverable() {
		$this->connect_as( 'administrator' );

		$result = $this->call_tool( 'mcp-adapter-discover-abilities', [] );
		$names  = wp_list_pluck( $result['structuredContent']['abilities'], 'name' );

		$this->assertContains( 'tsmlt/search-media', $names );
		$this->assertContains( 'tsmlt/get-media-details', $names );
		$this->assertContains( 'tsmlt/update-media-metadata', $names );
		$this->assertFalse( $result['isError'] );
	}

	/**
	 * get-ability-info returns the strict input schema and MCP metadata.
	 */
	public function test_ability_info() {
		$this->connect_as( 'administrator' );

		$info = $this->call_tool( 'mcp-adapter-get-ability-info', [ 'ability_name' => 'tsmlt/search-media' ] )['structuredContent'];

		$this->assertSame( 'tsmlt/search-media', $info['name'] );
		$this->assertFalse( $info['input_schema']['additionalProperties'] );
		$this->assertSame( 50, $info['input_schema']['properties']['per_page']['maximum'] );
		$this->assertFalse( $info['meta']['show_in_rest'] );
		$this->assertTrue( $info['meta']['mcp']['public'] );
		// Data for an LLM, so it must not be HTML-escaped.
		$this->assertStringNotContainsString( '&quot;', $info['description'] );
	}

	/**
	 * An administrator can execute the ability and gets allowlisted output.
	 */
	public function test_execute_as_administrator() {
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Harbour sunset',
				'file'           => '2026/10/harbour.jpg',
			]
		);
		self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Office desk',
			]
		);
		update_post_meta( $id, 'secret_custom_field', 'SECRET-META-MARKER' );
		$this->connect_as( 'administrator' );

		$result = $this->execute_search( [ 'search' => 'harbour' ] );
		$json   = wp_json_encode( $result );

		$this->assertFalse( $result['isError'] );
		$this->assertTrue( $result['structuredContent']['success'] );
		$data = $result['structuredContent']['data'];
		$this->assertSame( 1, $data['total'] );
		$this->assertSame( $id, $data['items'][0]['id'] );
		$this->assertSame( [ 'id', 'title', 'alt_text', 'caption', 'mime_type', 'url', 'date' ], array_keys( $data['items'][0] ) );
		$this->assertStringNotContainsString( 'SECRET-META-MARKER', $json );
		$this->assertStringNotContainsString( wp_json_encode( wp_upload_dir()['basedir'] ), $json );
	}

	/**
	 * A logged-in user without manage_options is refused.
	 */
	public function test_execute_refused_without_manage_options() {
		self::factory()->attachment->create( [ 'post_mime_type' => 'image/jpeg' ] );
		$this->connect_as( 'editor' );

		$data = $this->decode(
			$this->send(
				'tools/call',
				[
					'name'      => 'mcp-adapter-execute-ability',
					'arguments' => [
						'ability_name' => 'tsmlt/search-media',
						'parameters'   => (object) [],
					],
				]
			)
		);

		$this->assertTrue( $data['result']['isError'], wp_json_encode( $data ) );
		$this->assertSame( 'Permission denied', $data['result']['content'][0]['text'] );
		$this->assertStringNotContainsString( '"items"', wp_json_encode( $data ) );
	}

	/**
	 * Unknown parameters are refused through MCP too.
	 */
	public function test_execute_rejects_unknown_parameters() {
		$this->connect_as( 'administrator' );

		$data = $this->decode(
			$this->send(
				'tools/call',
				[
					'name'      => 'mcp-adapter-execute-ability',
					'arguments' => [
						'ability_name' => 'tsmlt/search-media',
						'parameters'   => [ 'post_status' => 'trash' ],
					],
				]
			)
		);

		$this->assertTrue( $data['result']['isError'], wp_json_encode( $data ) );
		$this->assertStringContainsString( 'post_status is not a valid property', $data['result']['content'][0]['text'] );
		$this->assertStringNotContainsString( '"items"', wp_json_encode( $data ) );
	}

	/**
	 * get-media-details runs through the adapter and returns allowlisted output.
	 */
	public function test_get_media_details_as_administrator() {
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Harbour sunset',
				'file'           => '2026/10/harbour.jpg',
			]
		);
		wp_update_attachment_metadata(
			$id,
			[
				'width'      => 640,
				'height'     => 480,
				'file'       => '2026/10/harbour.jpg',
				'image_meta' => [ 'latitude' => 'GPS-MARKER' ],
			]
		);
		$this->connect_as( 'administrator' );

		$result = $this->call_tool(
			'mcp-adapter-execute-ability',
			[
				'ability_name' => 'tsmlt/get-media-details',
				'parameters'   => [ 'id' => $id ],
			]
		);

		$this->assertFalse( $result['isError'], wp_json_encode( $result ) );
		$data = $result['structuredContent']['data'];
		$this->assertSame( $id, $data['id'] );
		$this->assertSame( 'harbour.jpg', $data['filename'] );
		$this->assertSame( 640, $data['width'] );
		$this->assertNull( $data['parent'] );
		$this->assertStringNotContainsString( 'GPS-MARKER', wp_json_encode( $result ) );
	}

	/**
	 * A missing attachment comes back as a tool error, not a transport error.
	 */
	public function test_get_media_details_not_found() {
		$this->connect_as( 'administrator' );

		$result = $this->call_tool(
			'mcp-adapter-execute-ability',
			[
				'ability_name' => 'tsmlt/get-media-details',
				'parameters'   => [ 'id' => 999999 ],
			]
		);

		$this->assertTrue( $result['isError'] );
		$this->assertStringContainsString( 'No attachment exists with that ID.', $result['content'][0]['text'] );
	}

	/**
	 * get-media-details is refused for users without manage_options.
	 */
	public function test_get_media_details_refused_without_manage_options() {
		$id = self::factory()->attachment->create( [ 'post_mime_type' => 'image/jpeg' ] );
		$this->connect_as( 'editor' );

		$result = $this->call_tool(
			'mcp-adapter-execute-ability',
			[
				'ability_name' => 'tsmlt/get-media-details',
				'parameters'   => [ 'id' => $id ],
			]
		);

		$this->assertTrue( $result['isError'] );
		$this->assertSame( 'Permission denied', $result['content'][0]['text'] );
	}

	/**
	 * update-media-metadata writes through the adapter for an administrator.
	 */
	public function test_update_media_metadata_as_administrator() {
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Before',
			]
		);
		$this->connect_as( 'administrator' );

		$result = $this->call_tool(
			'mcp-adapter-execute-ability',
			[
				'ability_name' => 'tsmlt/update-media-metadata',
				'parameters'   => [
					'id'       => $id,
					'title'    => 'After',
					'alt_text' => 'New alt',
				],
			]
		);

		$this->assertFalse( $result['isError'], wp_json_encode( $result ) );
		$data = $result['structuredContent']['data'];
		$this->assertSame( [ 'title', 'alt_text' ], $data['updated_fields'] );
		$this->assertSame( 'After', $data['media']['title'] );
		clean_post_cache( $id );
		$this->assertSame( 'After', get_post( $id )->post_title );
		$this->assertSame( 'New alt', get_post_meta( $id, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * update-media-metadata is refused for users without manage_options and writes nothing.
	 */
	public function test_update_media_metadata_refused_without_manage_options() {
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Before',
			]
		);
		$this->connect_as( 'editor' );

		$result = $this->call_tool(
			'mcp-adapter-execute-ability',
			[
				'ability_name' => 'tsmlt/update-media-metadata',
				'parameters'   => [
					'id'    => $id,
					'title' => 'Hacked',
				],
			]
		);

		$this->assertTrue( $result['isError'] );
		$this->assertSame( 'Permission denied', $result['content'][0]['text'] );
		clean_post_cache( $id );
		$this->assertSame( 'Before', get_post( $id )->post_title );
	}

	/**
	 * Unknown parameters such as filename are refused by the adapter path too.
	 */
	public function test_update_media_metadata_rejects_filename() {
		$id = self::factory()->attachment->create( [ 'post_mime_type' => 'image/jpeg' ] );
		$this->connect_as( 'administrator' );

		$result = $this->call_tool(
			'mcp-adapter-execute-ability',
			[
				'ability_name' => 'tsmlt/update-media-metadata',
				'parameters'   => [
					'id'       => $id,
					'filename' => 'evil.php',
				],
			]
		);

		$this->assertTrue( $result['isError'] );
		$this->assertStringContainsString( 'filename is not a valid property', $result['content'][0]['text'] );
	}

	/**
	 * Unauthenticated requests are refused by the adapter transport.
	 */
	public function test_unauthenticated_is_refused() {
		wp_set_current_user( 0 );

		$response = $this->send(
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

		$this->assertSame( 401, $response->get_status() );
	}
}
