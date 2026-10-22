<?php
/**
 * What the MCP Adapter's default gateway reveals about the Free abilities
 * through `mcp-adapter-discover-abilities` and `mcp-adapter-get-ability-info`.
 *
 * Behaviour of MCP Adapter 0.7.0, read from its source:
 *
 * - discover-abilities (DiscoverAbilitiesAbility): any logged-in user with
 *   `read` gets `{ abilities: [ { name, label, description } ] }` for every
 *   ability whose meta resolves to MCP-public and whose mcp.type is `tool`.
 *   Each ability's own permission callback is not consulted.
 * - get-ability-info (GetAbilityInfoAbility): any logged-in user with `read`
 *   gets `{ name, label, description, input_schema, output_schema?, meta? }`
 *   for an MCP-public ability. A non-public ability is refused by the
 *   permission check with "is not exposed via MCP (mcp.public!=true)".
 *
 * Both answer through ToolsHandler: success is `isError: false` with the
 * ability output as `structuredContent`; a refused permission check is
 * `isError: true` with the WP_Error message as text.
 *
 * Skipped unless MCP_ADAPTER_DIR is set (see tests/README.md).
 *
 * @package TinySolutions\mlt
 */

require_once __DIR__ . '/DefaultGatewayClientTrait.php';

/**
 * McpDefaultGatewayDiscoveryTest
 */
class McpDefaultGatewayDiscoveryTest extends WP_UnitTestCase {

	use DefaultGatewayClientTrait;

	/**
	 * The MCP-public Free abilities, in registration order.
	 *
	 * @var string[]
	 */
	const FREE_ABILITIES = [ 'tsmlt/search-media', 'tsmlt/get-media-details', 'tsmlt/update-media-metadata' ];

	/**
	 * A Free ability that is not MCP-public: offered only on the dedicated server.
	 *
	 * @var string
	 */
	const FREE_NON_PUBLIC_ABILITY = 'tsmlt/get-exif-fields';

	/**
	 * Input properties each Free ability accepts.
	 *
	 * @var array<string, string[]>
	 */
	const INPUT_PROPERTIES = [
		'tsmlt/search-media'          => [ 'search', 'filename', 'mime_type', 'missing_alt', 'group_id', 'attached', 'page', 'per_page', 'orderby', 'order' ],
		'tsmlt/get-media-details'     => [ 'id' ],
		'tsmlt/update-media-metadata' => [ 'id', 'title', 'alt_text', 'caption', 'description', 'expected' ],
	];

	/**
	 * A plugin-namespaced ability registered without MCP exposure, standing
	 * in for a future Pro ability that is not meant for MCP.
	 *
	 * @var string
	 */
	const HIDDEN_ABILITY = 'tsmlt/hidden-pro-probe';

	/**
	 * Marker placed in private data; must never appear in a response.
	 *
	 * @var string
	 */
	const SECRET = 'TSMLT-SECRET-MARKER-7f3a';

	/**
	 * Skip without the adapter; seed private data and the hidden ability.
	 */
	public function set_up() {
		parent::set_up();
		$this->gw_require_adapter();

		// Private data that discovery and ability info must never reveal.
		$id = $this->gw_make_attachment( 'Discovery probe' );
		update_post_meta( $id, '_private_custom_field', self::SECRET );
		update_post_meta( $id, '_tsmlt_exif_gps_lat', self::SECRET );
		$settings                          = (array) get_option( 'tsmlt_settings', [] );
		$settings['ai_custom_instruction'] = self::SECRET;
		update_option( 'tsmlt_settings', $settings );
		update_option( 'tsmlt_ai_credentials', [ 'chatgpt' => [ 'ct' => self::SECRET ] ], false );

		if ( ! WP_Abilities_Registry::get_instance()->is_registered( self::HIDDEN_ABILITY ) ) {
			// The registry is already initialised, so register directly, as
			// wp_register_ability() only works during wp_abilities_api_init.
			WP_Abilities_Registry::get_instance()->register(
				self::HIDDEN_ABILITY,
				[
					'label'               => 'Hidden probe',
					'description'         => 'Not exposed via MCP.',
					'category'            => \TinySolutions\mlt\Abilities\AbilitiesInit::CATEGORY,
					'input_schema'        => [
						'type'    => 'object',
						'default' => [],
					],
					'output_schema'       => [ 'type' => 'object' ],
					'execute_callback'    => '__return_empty_array',
					'permission_callback' => '__return_true',
					'meta'                => [ 'mcp' => [ 'public' => false ] ],
				]
			);
		}
	}

	/**
	 * Remove the hidden ability.
	 */
	public function tear_down() {
		if ( class_exists( 'WP_Abilities_Registry' ) && WP_Abilities_Registry::get_instance()->is_registered( self::HIDDEN_ABILITY ) ) {
			WP_Abilities_Registry::get_instance()->unregister( self::HIDDEN_ABILITY );
		}
		parent::tear_down();
	}

	/**
	 * Assert a response reveals no private data or server paths.
	 *
	 * @param array $response Decoded response.
	 */
	private function assert_no_sensitive_data( array $response ) {
		$json = wp_json_encode( $response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		$this->assertStringNotContainsString( self::SECRET, $json );
		foreach ( [ ABSPATH, WP_CONTENT_DIR, wp_upload_dir()['basedir'], untrailingslashit( TSMLT_PATH ) ] as $path ) {
			$this->assertStringNotContainsString( untrailingslashit( $path ), $json, 'No server path may be revealed.' );
		}
		foreach ( [ 'tsmlt_ai_credentials', 'ai_chatgpt_key', 'ai_gemini_key', 'ai_claude_key', 'ai_custom_instruction', '_wp_attached_file', 'image_meta' ] as $key ) {
			$this->assertStringNotContainsString( $key, $json );
		}
	}

	/**
	 * Discovery entries for this plugin's abilities, in response order.
	 *
	 * @param array $result Tool result.
	 *
	 * @return array<int, array>
	 */
	private function plugin_entries( array $result ): array {
		return array_values(
			array_filter(
				$result['structuredContent']['abilities'],
				static function ( $entry ) {
					return 0 === strpos( $entry['name'], 'tsmlt' ) || false !== strpos( $entry['name'], 'media-library-tools' );
				}
			)
		);
	}

	/**
	 * @return array
	 */
	public function data_reader_roles(): array {
		return [
			'administrator' => [ 'administrator' ],
			'subscriber'    => [ 'subscriber' ],
		];
	}

	/**
	 * Discovery lists exactly the three Free abilities from this plugin, with
	 * only name, label and description, and no hidden or Pro ability.
	 *
	 * Subscribers see the same list: the adapter gates discovery on `read`
	 * only. Executing still requires manage_options (see
	 * McpDefaultGatewayAuthorizationTest).
	 *
	 * @dataProvider data_reader_roles
	 *
	 * @param string $role Role.
	 */
	public function test_discovery_lists_only_the_free_abilities( string $role ) {
		$this->gw_connect_as( $role );

		$result = $this->gw_call( 'mcp-adapter-discover-abilities', [] );

		$this->assertFalse( $result['isError'], wp_json_encode( $result ) );
		$entries = $this->plugin_entries( $result );
		$this->assertSame( self::FREE_ABILITIES, array_column( $entries, 'name' ) );
		foreach ( $entries as $entry ) {
			$ability = wp_get_ability( $entry['name'] );
			$this->assertSame( [ 'name', 'label', 'description' ], array_keys( $entry ) );
			$this->assertSame( $ability->get_label(), $entry['label'] );
			$this->assertSame( $ability->get_description(), $entry['description'] );
		}

		$names = array_column( $result['structuredContent']['abilities'], 'name' );
		$this->assertNotContains( self::HIDDEN_ABILITY, $names );
		$this->assertNotContains( self::FREE_NON_PUBLIC_ABILITY, $names );
		foreach ( $names as $name ) {
			$this->assertStringNotContainsString( 'tsmltpro', $name, 'No Pro ability may be listed.' );
		}
		$this->assert_no_sensitive_data( $result );
	}

	/**
	 * @return array
	 */
	public function data_free_abilities_and_roles(): array {
		$cases = [];
		foreach ( self::FREE_ABILITIES as $name ) {
			foreach ( [ 'administrator', 'subscriber' ] as $role ) {
				$cases[ $name . ' as ' . $role ] = [ $name, $role ];
			}
		}
		return $cases;
	}

	/**
	 * Ability info returns exactly the registered definition: the closed
	 * input schema with only the documented properties, the output schema,
	 * and the MCP metadata — and nothing private.
	 *
	 * @dataProvider data_free_abilities_and_roles
	 *
	 * @param string $name Ability name.
	 * @param string $role Role.
	 */
	public function test_ability_info_returns_the_registered_definition( string $name, string $role ) {
		$this->gw_connect_as( $role );
		$ability = wp_get_ability( $name );

		$result = $this->gw_call( 'mcp-adapter-get-ability-info', [ 'ability_name' => $name ] );

		$this->assertFalse( $result['isError'], wp_json_encode( $result ) );
		$info = $result['structuredContent'];
		$this->assertSame( [ 'name', 'label', 'description', 'input_schema', 'output_schema', 'meta' ], array_keys( $info ) );
		$this->assertSame( $name, $info['name'] );
		$this->assertSame( $ability->get_label(), $info['label'] );
		$this->assertSame( $ability->get_description(), $info['description'] );

		// Schemas and meta exactly as registered (compared after the same JSON round trip).
		$as_json = static function ( $value ) {
			return json_decode( wp_json_encode( $value ), true );
		};
		$this->assertSame( $as_json( $ability->get_input_schema() ), $info['input_schema'] );
		$this->assertSame( $as_json( $ability->get_output_schema() ), $info['output_schema'] );
		$this->assertSame( $as_json( $ability->get_meta() ), $info['meta'] );

		// Closed input schema with only the documented properties.
		$this->assertFalse( $info['input_schema']['additionalProperties'] );
		$this->assertSame( self::INPUT_PROPERTIES[ $name ], array_keys( $info['input_schema']['properties'] ) );
		$forbidden = [ 'bulkEditPostTitle', 'newname', 'post_categories', 'custom_meta', 'exif_camera', 'exif_has_gps', 'status', 'filtering', 'filename' ];
		if ( 'tsmlt/search-media' === $name ) {
			// There `filename` is a documented, read-only search filter, not the
			// rename key this list guards against on the write ability.
			$forbidden = array_diff( $forbidden, [ 'filename' ] );
		}
		foreach ( $forbidden as $property ) {
			$this->assertArrayNotHasKey( $property, $info['input_schema']['properties'] );
		}

		// MCP visibility metadata.
		$this->assertSame(
			[
				'public' => true,
				'type'   => 'tool',
			],
			$info['meta']['mcp']
		);
		$this->assertFalse( $info['meta']['show_in_rest'] );

		$this->assert_no_sensitive_data( $result );
	}

	/**
	 * Ability info refuses a plugin-namespaced ability that is not MCP-public,
	 * revealing neither its schema nor its description.
	 */
	public function test_ability_info_refuses_non_public_ability() {
		$this->gw_connect_as( 'administrator' );

		$result = $this->gw_call( 'mcp-adapter-get-ability-info', [ 'ability_name' => self::HIDDEN_ABILITY ] );

		$this->assertTrue( $result['isError'], wp_json_encode( $result ) );
		$this->assertSame(
			sprintf( 'Ability "%s" is not exposed via MCP (mcp.public!=true)', self::HIDDEN_ABILITY ),
			$result['content'][0]['text']
		);
		$this->assertArrayNotHasKey( 'structuredContent', $result );
		$this->assertStringNotContainsString( 'Not exposed via MCP.', wp_json_encode( $result ) );
	}

	/**
	 * The non-public Free ability is registered, but ability info refuses it
	 * and execution through the gateway is refused, without any EXIF.
	 */
	public function test_non_public_free_ability_is_refused() {
		$this->assertInstanceOf( WP_Ability::class, wp_get_ability( self::FREE_NON_PUBLIC_ABILITY ), 'Precondition: registered.' );
		$this->gw_connect_as( 'administrator' );

		$info = $this->gw_call( 'mcp-adapter-get-ability-info', [ 'ability_name' => self::FREE_NON_PUBLIC_ABILITY ] );
		$this->assertTrue( $info['isError'], wp_json_encode( $info ) );
		$this->assertSame(
			sprintf( 'Ability "%s" is not exposed via MCP (mcp.public!=true)', self::FREE_NON_PUBLIC_ABILITY ),
			$info['content'][0]['text']
		);
		$this->assertArrayNotHasKey( 'structuredContent', $info );

		$id       = $this->gw_make_attachment( 'EXIF gateway probe' );
		$executed = $this->gw_call( 'mcp-adapter-execute-ability', self::gw_execute_args( self::FREE_NON_PUBLIC_ABILITY, [ 'id' => $id ] ) );
		$this->assertTrue( $executed['isError'], wp_json_encode( $executed ) );
		$this->assertStringNotContainsString( '"fields"', wp_json_encode( $executed ) );
	}

	/**
	 * Ability info for an unregistered plugin-style name is refused.
	 *
	 * @dataProvider data_unregistered_names
	 *
	 * @param string $name Unregistered ability name.
	 */
	public function test_ability_info_refuses_unregistered_names( string $name ) {
		// Core reports a lookup of an unregistered ability name as incorrect usage.
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );
		$this->gw_connect_as( 'administrator' );

		$result = $this->gw_call( 'mcp-adapter-get-ability-info', [ 'ability_name' => $name ] );

		$this->assertTrue( $result['isError'], wp_json_encode( $result ) );
		$this->assertSame( "Ability '{$name}' not found", $result['content'][0]['text'] );
	}

	/**
	 * @return array
	 */
	public function data_unregistered_names(): array {
		return [
			'MCP tool name, not ability name' => [ 'tsmlt-update-media-metadata' ],
			'Pro-style bulk ability'          => [ 'tsmlt/bulk-ai-generate' ],
			'Pro namespace'                   => [ 'tsmltpro/bulk-edit-exif' ],
		];
	}

	/**
	 * Logged-out requests never reach discovery or ability info: the
	 * transport refuses them with 401 before any MCP handler runs.
	 *
	 * @dataProvider data_metadata_tools
	 *
	 * @param string $tool      Tool name.
	 * @param array  $arguments Arguments.
	 */
	public function test_logged_out_metadata_requests_are_refused( string $tool, array $arguments ) {
		$this->gw_connect_as( 'administrator' );
		wp_set_current_user( 0 );

		$response = $this->gw_call_raw( $tool, $arguments );
		$json     = wp_json_encode( $this->gw_decode( $response ) );

		$this->assertSame( 401, $response->get_status() );
		$this->assertStringNotContainsString( 'tsmlt/', $json );
		$this->assertStringNotContainsString( 'input_schema', $json );
	}

	/**
	 * @return array
	 */
	public function data_metadata_tools(): array {
		return [
			'discover-abilities' => [ 'mcp-adapter-discover-abilities', [] ],
			'get-ability-info'   => [ 'mcp-adapter-get-ability-info', [ 'ability_name' => 'tsmlt/update-media-metadata' ] ],
		];
	}
}
