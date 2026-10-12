<?php
/**
 * Boundary tests through the MCP Adapter's default gateway server.
 *
 * The default server (/mcp/mcp-adapter-default-server) does not list the
 * plugin's tools directly. Clients reach them through its
 * `mcp-adapter-execute-ability` tool, with arguments
 * `{ ability_name, parameters }`. Path in MCP Adapter 0.7.0:
 *
 *   ToolsHandler::call_tool()
 *     -> McpTool::check_permission() -> ExecuteAbilityAbility::check_permission()
 *        (gateway `read` capability, mcp.public exposure, then the target
 *        ability's own check_permissions())
 *     -> McpTool::execute() -> WP_Ability::execute( 'mcp-adapter/execute-ability' )
 *     -> ExecuteAbilityAbility::execute() -> WP_Ability::execute( target )
 *
 * The target's WP_Error comes back as `{ success: false, error }`, which
 * ToolsHandler turns into `{ content: [ { type: text, text } ], isError: true }`.
 *
 * Core fires `wp_ability_invoked` when execute() starts and
 * `wp_before_execute_ability` only after input validation and permissions
 * pass, so together they show the gateway reached the target ability and the
 * target refused the input before its execute callback ran.
 *
 * Skipped unless MCP_ADAPTER_DIR is set (see tests/README.md).
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Helpers\Fns;

/**
 * McpDefaultGatewayBoundaryTest
 */
class McpDefaultGatewayBoundaryTest extends WP_UnitTestCase {

	/**
	 * Default server route.
	 *
	 * @var string
	 */
	const ROUTE = '/mcp/mcp-adapter-default-server';

	/**
	 * Gateway tool name on the default server.
	 *
	 * @var string
	 */
	const GATEWAY_TOOL = 'mcp-adapter-execute-ability';

	/**
	 * Ability behind the gateway tool.
	 *
	 * @var string
	 */
	const GATEWAY_ABILITY = 'mcp-adapter/execute-ability';

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
	 * Fixture attachment ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Group (tsmlt_category) term ID the fixture is not assigned to.
	 *
	 * @var int
	 */
	private $term_id;

	/**
	 * What ran while the gateway was called.
	 *
	 * @var array<string, array>
	 */
	private $spied = [];

	/**
	 * Spy callbacks, kept so they can be removed.
	 *
	 * @var array<int, array{0: string, 1: callable, 2: int}>
	 */
	private $spies = [];

	/**
	 * Skip without the adapter; create the fixture and open a session.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$parent_id = self::factory()->post->create( [ 'post_title' => 'Gateway parent' ] );
		$this->id  = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
				'post_title'     => 'Gateway probe',
				'post_excerpt'   => 'Original caption',
				'post_content'   => 'Original description',
				'post_name'      => 'gateway-probe',
				'post_parent'    => $parent_id,
				'file'           => '2026/10/gateway-probe.jpg',
			]
		);
		update_post_meta( $this->id, '_wp_attachment_image_alt', 'Original alt' );
		wp_update_attachment_metadata(
			$this->id,
			[
				'width'  => 800,
				'height' => 600,
				'file'   => '2026/10/gateway-probe.jpg',
			]
		);
		$this->term_id = (int) self::factory()->term->create(
			[
				'taxonomy' => Fns::CATEGORY,
				'name'     => 'Gateway group',
			]
		);

		$this->session_id = null;
		$this->initialize();

		// Spies go on after setup, so only the call under test is recorded.
		$this->spied = [];
		$this->spy( 'wp_ability_invoked', 'invoked', 1 );
		$this->spy( 'wp_before_execute_ability', 'executed', 1 );
		$this->spy( 'tsmlt_attachment_rename_to', 'rename', 1 );
		$this->spy( 'attachment_updated', 'post_update', 1 );
		$this->spy( 'set_object_terms', 'terms', 1 );
		$this->spy( 'added_post_meta', 'meta', 2 );
		$this->spy( 'updated_post_meta', 'meta', 2 );
		$this->spy( 'deleted_post_meta', 'meta', 2 );
	}

	/**
	 * Remove the spies.
	 */
	public function tear_down() {
		foreach ( $this->spies as $spy ) {
			remove_filter( $spy[0], $spy[1], $spy[2] );
		}
		$this->spies = [];
		parent::tear_down();
	}

	/**
	 * Record calls to a hook under a label. Filters get their value back.
	 *
	 * @param string $hook  Hook name.
	 * @param string $label Label to record under.
	 * @param int    $args  Accepted arguments.
	 */
	private function spy( string $hook, string $label, int $args ) {
		$callback = function ( ...$received ) use ( $hook, $label ) {
			$this->spied[ $label ][] = [ $hook, $received ];
			return $received[0] ?? null;
		};
		add_filter( $hook, $callback, 10, $args );
		$this->spies[] = [ $hook, $callback, 10 ];
	}

	/**
	 * Ability names recorded by a name-carrying spy.
	 *
	 * @param string $label Spy label.
	 *
	 * @return string[]
	 */
	private function spied_names( string $label ): array {
		return array_map(
			static function ( $call ) {
				return $call[1][0];
			},
			$this->spied[ $label ] ?? []
		);
	}

	/**
	 * Send one JSON-RPC message to the default server.
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
	 * Run the initialize handshake and keep the session ID.
	 */
	private function initialize() {
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
		$this->assertSame( 200, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Mcp-Session-Id', $headers );
		$this->session_id = $headers['Mcp-Session-Id'];
		$this->send( 'notifications/initialized' );
	}

	/**
	 * Call an ability through the gateway tool and return the JSON-RPC result.
	 *
	 * @param string $ability_name Ability name as the gateway expects it.
	 * @param array  $parameters   Ability parameters.
	 *
	 * @return array
	 */
	private function call_gateway( string $ability_name, array $parameters ): array {
		$data = $this->decode(
			$this->send(
				'tools/call',
				[
					'name'      => self::GATEWAY_TOOL,
					'arguments' => [
						'ability_name' => $ability_name,
						'parameters'   => (object) $parameters,
					],
				]
			)
		);
		$this->assertArrayHasKey( 'result', $data, wp_json_encode( $data ) );
		return $data['result'];
	}

	/**
	 * Everything about the fixture an ability could change.
	 *
	 * @return array
	 */
	private function snapshot(): array {
		clean_post_cache( $this->id );
		wp_cache_delete( $this->id, 'post_meta' );
		$post = get_post( $this->id );
		$meta = get_post_meta( $this->id );
		unset( $meta['_edit_lock'], $meta['_edit_last'] );
		ksort( $meta );

		return [
			'post_title'     => $post->post_title,
			'post_excerpt'   => $post->post_excerpt,
			'post_content'   => $post->post_content,
			'post_name'      => $post->post_name,
			'post_status'    => $post->post_status,
			'post_parent'    => $post->post_parent,
			'post_mime_type' => $post->post_mime_type,
			'guid'           => $post->guid,
			'attached_file'  => get_attached_file( $this->id ),
			'metadata'       => wp_get_attachment_metadata( $this->id ),
			'meta'           => $meta,
			'groups'         => wp_get_object_terms( $this->id, Fns::CATEGORY, [ 'fields' => 'ids' ] ),
		];
	}

	/**
	 * Replace placeholders with fixture IDs.
	 *
	 * @param array $input Input with `{id}` / `{term}` placeholders.
	 *
	 * @return array
	 */
	private function resolve( array $input ): array {
		array_walk_recursive(
			$input,
			function ( &$value ) {
				if ( '{id}' === $value ) {
					$value = $this->id;
				} elseif ( '{term}' === $value ) {
					$value = $this->term_id;
				}
			}
		);
		return $input;
	}

	/**
	 * Valid parameters per ability. The update baseline writes a caption, so
	 * an extra property that was ignored instead of refused would show.
	 *
	 * @return array<string, array>
	 */
	private static function baselines(): array {
		return [
			'tsmlt/search-media'          => [
				'search'   => 'Gateway probe',
				'per_page' => 5,
			],
			'tsmlt/get-media-details'     => [ 'id' => '{id}' ],
			'tsmlt/update-media-metadata' => [
				'id'      => '{id}',
				'caption' => 'Changed by a Pro-style probe',
			],
		];
	}

	/**
	 * Arguments that select Pro-only or admin-only behaviour elsewhere.
	 *
	 * @return array<string, mixed>
	 */
	private static function pro_style_properties(): array {
		return [
			'bulkEditPostTitle'         => [ 'post_title', 'alt_text', 'caption', 'post_description' ],
			'newname'                   => 'renamed-by-probe',
			'post_categories'           => [ '{term}' ],
			'custom_meta:_photographer' => 'Probe photographer',
			'exif_camera'               => 'Probe camera',
			'exif_has_gps'              => 'yes',
			'status'                    => 'trash',
			'filtering'                 => true,
		];
	}

	/**
	 * @return array
	 */
	public function data_abilities(): array {
		$cases = [];
		foreach ( array_keys( self::baselines() ) as $name ) {
			$cases[ $name ] = [ $name ];
		}
		return $cases;
	}

	/**
	 * Each baseline succeeds through the gateway: the fixture, the session,
	 * the request format and the spies all work, and the target ability ran.
	 *
	 * @dataProvider data_abilities
	 *
	 * @param string $name Ability name.
	 */
	public function test_baseline_runs_through_gateway( string $name ) {
		$result = $this->call_gateway( $name, $this->resolve( self::baselines()[ $name ] ) );

		$this->assertFalse( $result['isError'], wp_json_encode( $result ) );
		$this->assertTrue( $result['structuredContent']['success'], wp_json_encode( $result ) );
		$this->assertIsArray( $result['structuredContent']['data'] );
		$this->assertSame( [ self::GATEWAY_ABILITY, $name ], $this->spied_names( 'invoked' ) );
		$this->assertSame( [ self::GATEWAY_ABILITY, $name ], $this->spied_names( 'executed' ) );

		if ( 'tsmlt/update-media-metadata' === $name ) {
			$this->assertSame( [ 'caption' ], $result['structuredContent']['data']['updated_fields'] );
			$this->assertSame( 'Changed by a Pro-style probe', $this->snapshot()['post_excerpt'] );
		}
	}

	/**
	 * A Pro-style argument sent through the gateway is refused by the target
	 * ability's own input validation, and nothing about the attachment
	 * changes.
	 *
	 * @dataProvider data_pro_style_arguments
	 *
	 * @param string $name     Ability name.
	 * @param string $property Unexpected property.
	 * @param mixed  $value    Its value.
	 */
	public function test_pro_style_argument_is_refused_through_gateway( string $name, string $property, $value ) {
		$parameters              = self::baselines()[ $name ];
		$parameters[ $property ] = $value;
		$parameters              = $this->resolve( $parameters );
		$before                  = $this->snapshot();

		$result = $this->call_gateway( $name, $parameters );

		// 1. The gateway's real error format: an isError tool result whose text
		// is the target ability's validation message.
		$this->assertTrue( $result['isError'], wp_json_encode( $result ) );
		$this->assertArrayNotHasKey( 'structuredContent', $result );
		$this->assertSame( 'text', $result['content'][0]['type'] );
		$this->assertSame(
			sprintf( 'Ability "%s" has invalid input. Reason: %s is not a valid property of Object.', $name, $property ),
			$result['content'][0]['text']
		);

		// 2. The attachment and its metadata are unchanged.
		$this->assertSame( $before, $this->snapshot() );

		// 3. The gateway dispatched to the target, which refused the input
		// before its execute callback began.
		$this->assertSame( [ self::GATEWAY_ABILITY, $name ], $this->spied_names( 'invoked' ), 'The gateway must reach the target ability.' );
		$this->assertSame( [ self::GATEWAY_ABILITY ], $this->spied_names( 'executed' ), 'The target ability must not begin execution.' );
		$this->assertArrayNotHasKey( 'rename', $this->spied, 'No rename may be attempted.' );
		$this->assertArrayNotHasKey( 'post_update', $this->spied, 'No post update may run.' );
		$this->assertArrayNotHasKey( 'terms', $this->spied, 'No group may be assigned.' );
		foreach ( $this->spied['meta'] ?? [] as $call ) {
			$this->assertNotSame( $this->id, (int) $call[1][1], 'No meta of the attachment may be written: ' . $call[0] );
		}
	}

	/**
	 * Every ability crossed with every Pro-style property.
	 *
	 * @return array
	 */
	public function data_pro_style_arguments(): array {
		$cases = [];
		foreach ( array_keys( self::baselines() ) as $name ) {
			foreach ( self::pro_style_properties() as $property => $value ) {
				$cases[ $name . ' + ' . $property ] = [ $name, $property, $value ];
			}
		}
		return $cases;
	}

	/**
	 * The gateway resolves abilities by their registered name. The sanitized
	 * MCP tool name of a Free ability does not resolve, so it fails closed.
	 *
	 * @dataProvider data_mcp_tool_names
	 *
	 * @param string $tool_name Sanitized MCP tool name.
	 * @param string $name      Registered ability name.
	 */
	public function test_mcp_tool_name_does_not_resolve_on_gateway( string $tool_name, string $name ) {
		// Core reports a lookup of an unregistered ability name as incorrect usage.
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );
		$before = $this->snapshot();

		$result = $this->call_gateway( $tool_name, $this->resolve( self::baselines()[ $name ] ) );

		$this->assertTrue( $result['isError'], wp_json_encode( $result ) );
		$this->assertSame( "Ability '{$tool_name}' not found", $result['content'][0]['text'] );
		$this->assertSame( [], $this->spied_names( 'invoked' ) );
		$this->assertSame( $before, $this->snapshot() );
	}

	/**
	 * @return array
	 */
	public function data_mcp_tool_names(): array {
		return [
			'tsmlt-search-media'          => [ 'tsmlt-search-media', 'tsmlt/search-media' ],
			'tsmlt-get-media-details'     => [ 'tsmlt-get-media-details', 'tsmlt/get-media-details' ],
			'tsmlt-update-media-metadata' => [ 'tsmlt-update-media-metadata', 'tsmlt/update-media-metadata' ],
		];
	}
}
