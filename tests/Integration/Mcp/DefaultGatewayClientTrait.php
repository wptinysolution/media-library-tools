<?php
/**
 * Shared client for tests that talk to the MCP Adapter's default server.
 *
 * Not a test case itself (PHPUnit only collects *Test.php files); test classes
 * require this file and `use` the trait.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Helpers\Fns;

/**
 * DefaultGatewayClientTrait
 */
trait DefaultGatewayClientTrait {

	/**
	 * Session ID from the initialize handshake.
	 *
	 * @var string|null
	 */
	private $gw_session_id = null;

	/**
	 * JSON-RPC request counter.
	 *
	 * @var int
	 */
	private $gw_request_id = 0;

	/**
	 * What ran during the call under test, by spy label.
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
	 * Default server route.
	 *
	 * @return string
	 */
	private static function gw_route(): string {
		return '/mcp/mcp-adapter-default-server';
	}

	/**
	 * Skip when the adapter is not loaded.
	 */
	private function gw_require_adapter() {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}
	}

	/**
	 * Send one JSON-RPC message to the default server.
	 *
	 * @param string $method JSON-RPC method.
	 * @param array  $params Params.
	 *
	 * @return WP_REST_Response
	 */
	private function gw_send( string $method, array $params = [] ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', self::gw_route() );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'Accept', 'application/json, text/event-stream' );
		if ( null !== $this->gw_session_id ) {
			$request->set_header( 'Mcp-Session-Id', $this->gw_session_id );
			$request->set_header( 'MCP-Protocol-Version', '2025-11-25' );
		}
		$request->set_body(
			wp_json_encode(
				[
					'jsonrpc' => '2.0',
					'id'      => ++$this->gw_request_id,
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
	private function gw_decode( WP_REST_Response $response ): array {
		$data = $response->get_data();
		return is_array( $data ) ? $data : json_decode( wp_json_encode( $data ), true );
	}

	/**
	 * Run the initialize handshake as the current user.
	 *
	 * @return WP_REST_Response The initialize response.
	 */
	private function gw_initialize(): WP_REST_Response {
		$this->gw_session_id = null;
		$response            = $this->gw_send(
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
		$headers = $response->get_headers();
		if ( 200 === $response->get_status() && isset( $headers['Mcp-Session-Id'] ) ) {
			$this->gw_session_id = $headers['Mcp-Session-Id'];
			$this->gw_send( 'notifications/initialized' );
		}
		return $response;
	}

	/**
	 * Log in as a new user with a role and open a session.
	 *
	 * @param string $role Role, or '' for a user with no role.
	 *
	 * @return int User ID.
	 */
	private function gw_connect_as( string $role ): int {
		$user_id = self::factory()->user->create( [ 'role' => $role ] );
		wp_set_current_user( $user_id );
		$this->assertSame( 200, $this->gw_initialize()->get_status(), "A {$role} must be able to open a default-server session." );
		return $user_id;
	}

	/**
	 * Call a tool and return the full decoded response.
	 *
	 * @param string $tool      Tool name.
	 * @param array  $arguments Tool arguments.
	 *
	 * @return WP_REST_Response
	 */
	private function gw_call_raw( string $tool, array $arguments ): WP_REST_Response {
		return $this->gw_send(
			'tools/call',
			[
				'name'      => $tool,
				'arguments' => (object) $arguments,
			]
		);
	}

	/**
	 * Call a tool and return its JSON-RPC result.
	 *
	 * @param string $tool      Tool name.
	 * @param array  $arguments Tool arguments.
	 *
	 * @return array
	 */
	private function gw_call( string $tool, array $arguments ): array {
		$data = $this->gw_decode( $this->gw_call_raw( $tool, $arguments ) );
		$this->assertArrayHasKey( 'result', $data, wp_json_encode( $data ) );
		return $data['result'];
	}

	/**
	 * Arguments for the gateway's execute-ability tool.
	 *
	 * @param string $ability_name Ability name.
	 * @param array  $parameters   Ability parameters.
	 *
	 * @return array
	 */
	private static function gw_execute_args( string $ability_name, array $parameters ): array {
		return [
			'ability_name' => $ability_name,
			'parameters'   => (object) $parameters,
		];
	}

	/**
	 * Install the ability-lifecycle and mutation spies.
	 */
	private function gw_install_spies() {
		$this->spied = [];
		$this->gw_spy( 'wp_ability_invoked', 'invoked', 1 );
		$this->gw_spy( 'wp_before_execute_ability', 'executed', 1 );
		$this->gw_spy( 'tsmlt_attachment_rename_to', 'rename', 1 );
		$this->gw_spy( 'attachment_updated', 'post_update', 1 );
		$this->gw_spy( 'set_object_terms', 'terms', 1 );
		$this->gw_spy( 'added_post_meta', 'meta', 2 );
		$this->gw_spy( 'updated_post_meta', 'meta', 2 );
		$this->gw_spy( 'deleted_post_meta', 'meta', 2 );
	}

	/**
	 * Remove the spies.
	 */
	private function gw_remove_spies() {
		foreach ( $this->spies as $spy ) {
			remove_filter( $spy[0], $spy[1], $spy[2] );
		}
		$this->spies = [];
	}

	/**
	 * Record calls to a hook under a label. Filters get their value back.
	 *
	 * @param string $hook  Hook name.
	 * @param string $label Label.
	 * @param int    $args  Accepted arguments.
	 */
	private function gw_spy( string $hook, string $label, int $args ) {
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
	 * Assert no mutation hook fired for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private function assert_no_mutation_hooks( int $attachment_id ) {
		$this->assertArrayNotHasKey( 'rename', $this->spied, 'No rename may be attempted.' );
		$this->assertArrayNotHasKey( 'post_update', $this->spied, 'No post update may run.' );
		$this->assertArrayNotHasKey( 'terms', $this->spied, 'No group may be assigned.' );
		foreach ( $this->spied['meta'] ?? [] as $call ) {
			$this->assertNotSame( $attachment_id, (int) $call[1][1], 'No meta of the attachment may be written: ' . $call[0] );
		}
	}

	/**
	 * Create an attachment with known values in every field the tools touch.
	 *
	 * @param string $title Title.
	 *
	 * @return int
	 */
	private function gw_make_attachment( string $title ): int {
		$parent_id = self::factory()->post->create( [ 'post_title' => $title . ' parent' ] );
		$slug      = sanitize_title( $title );
		$id        = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
				'post_title'     => $title,
				'post_excerpt'   => 'Original caption',
				'post_content'   => 'Original description',
				'post_name'      => $slug,
				'post_parent'    => $parent_id,
				'file'           => '2026/10/' . $slug . '.jpg',
			]
		);
		update_post_meta( $id, '_wp_attachment_image_alt', 'Original alt' );
		wp_update_attachment_metadata(
			$id,
			[
				'width'  => 800,
				'height' => 600,
				'file'   => '2026/10/' . $slug . '.jpg',
			]
		);
		return $id;
	}

	/**
	 * Everything about an attachment a tool could change.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array
	 */
	private function gw_snapshot( int $attachment_id ): array {
		clean_post_cache( $attachment_id );
		wp_cache_delete( $attachment_id, 'post_meta' );
		$post = get_post( $attachment_id );
		$meta = get_post_meta( $attachment_id );
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
			'attached_file'  => get_attached_file( $attachment_id ),
			'metadata'       => wp_get_attachment_metadata( $attachment_id ),
			'meta'           => $meta,
			'groups'         => wp_get_object_terms( $attachment_id, Fns::CATEGORY, [ 'fields' => 'ids' ] ),
		];
	}
}
