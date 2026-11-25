<?php
/**
 * tsmlt/get-settings: the read-only settings tool (Free side; Pro is not
 * active in this suite, see the Pro suite's GetSettingsProTest).
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\McpAccess;
use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Abilities\Media\GetSettings;
use TinySolutions\mlt\Controllers\AI\Credentials;
use TinySolutions\mlt\Modules\Settings\SettingsCatalog;

/**
 * GetSettingsAbilityTest
 */
class GetSettingsAbilityTest extends WP_UnitTestCase {

	/**
	 * The Free settings, in order.
	 *
	 * @var string[]
	 */
	const FREE_KEYS = [
		'media_per_page',
		'rubbish_per_page',
		'deregistered_image_sizes',
		'default_alt_text',
		'default_caption_text',
		'default_desc_text',
		'media_default_alt',
		'media_default_caption',
		'media_default_desc',
		'auto_process_exif',
		'auto_fill_metadata_from_exif',
		'auto_set_date_from_exif',
		'compression_mode',
		'conversion_webp_enabled',
		'conversion_serve_modern',
		'ai_provider',
		'ai_language',
		'ai_chatgpt_model',
		'ai_gemini_model',
		'ai_claude_model',
		'ai_max_suggestion_count',
		'track_frontend_usage',
	];

	/**
	 * Option writes seen during a call.
	 *
	 * @var string[]
	 */
	private $writes = [];

	/**
	 * Administrator, clean settings.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/get-settings' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		$this->assertFalse( tsmlt()->has_pro(), 'Precondition: Pro is not active in this suite.' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( 'tsmlt_settings' );
		McpAccess::set_disabled( false );
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		delete_option( 'tsmlt_settings' );
		McpAccess::set_disabled( false );
		remove_filter( SettingsCatalog::DEFINITIONS_FILTER, [ $this, 'pro_like_definitions' ] );
		parent::tear_down();
	}

	/**
	 * Run the ability.
	 *
	 * @param array $input Input.
	 *
	 * @return array|WP_Error
	 */
	private static function call( array $input = [] ) {
		return wp_get_ability( 'tsmlt/get-settings' )->execute( $input );
	}

	/**
	 * Settings by key.
	 *
	 * @param array $result Result.
	 *
	 * @return array
	 */
	private static function by_key( array $result ): array {
		return array_column( $result['settings'], null, 'key' );
	}

	/**
	 * Definitions a Pro-like plugin would add.
	 *
	 * @param array $definitions Definitions.
	 *
	 * @return array
	 */
	public function pro_like_definitions( $definitions ) {
		$definitions['media_rename_prefix'] = [
			'group'   => 'rename',
			'label'   => 'Prefix',
			'type'    => 'string',
			'default' => '',
		];
		return $definitions;
	}

	/**
	 * Record an option write.
	 *
	 * @param string $option Option name.
	 */
	public function record_write( $option ) {
		$this->writes[] = $option;
	}

	/**
	 * Registered read-only, not gateway-runnable, listed on the dedicated
	 * server (and so on the default server, which lists every dedicated
	 * tool directly).
	 */
	public function test_registration_and_exposure() {
		$ability = wp_get_ability( 'tsmlt/get-settings' );
		$this->assertFalse( $ability->get_meta()['mcp']['public'] );
		$this->assertTrue( $ability->get_meta()['annotations']['readonly'] );
		$this->assertFalse( $ability->get_meta()['annotations']['destructive'] );
		$this->assertTrue( $ability->get_meta()['annotations']['idempotent'] );
		$this->assertContains( 'tsmlt/get-settings', McpServerRegistration::instance()->get_tool_names() );
	}

	/**
	 * Administrators only, in the permission callback and in execute();
	 * refused while MCP access is turned off.
	 */
	public function test_permissions_and_access_switch() {
		$ability = wp_get_ability( 'tsmlt/get-settings' );
		$this->assertTrue( $ability->check_permissions( [] ) );

		foreach ( [ 'editor', 'author', 'subscriber' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertNotTrue( $ability->check_permissions( [] ), $role );
			$this->assertWPError( GetSettings::instance()->execute( [] ), $role );
		}
		wp_set_current_user( 0 );
		$this->assertNotTrue( $ability->check_permissions( [] ), 'Anonymous.' );
		$this->assertWPError( GetSettings::instance()->execute( [] ), 'Anonymous.' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		McpAccess::set_disabled( true );
		$this->assertNotTrue( $ability->check_permissions( [] ) );
		$this->assertSame( 'tsmlt_mcp_disabled', GetSettings::instance()->execute( [] )->get_error_code() );
	}

	/**
	 * Every Free setting, with its definition and current value; nothing
	 * else while Pro is not active, even when a plugin adds definitions.
	 */
	public function test_free_settings() {
		update_option(
			'tsmlt_settings',
			[
				'media_per_page'          => 40,
				'default_alt_text'        => 'custom_text_to_alt',
				'media_default_alt'       => 'Product photo',
				'auto_process_exif'       => 1,
				'compression_mode'        => 'maximum',
				'conversion_webp_enabled' => 0,
				'ai_provider'             => 'claude',
				'ai_language'             => 'de_DE',
				'ai_claude_model'         => 'claude-test-model',
				'track_frontend_usage'    => 'on',
			]
		);
		add_filter( SettingsCatalog::DEFINITIONS_FILTER, [ $this, 'pro_like_definitions' ] );

		$result = self::call();
		$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), wp_get_ability( 'tsmlt/get-settings' )->get_output_schema(), 'result' ) );
		$this->assertSame( self::FREE_KEYS, array_column( $result['settings'], 'key' ), 'Exactly the Free settings.' );
		$this->assertSame( count( self::FREE_KEYS ), $result['total'] );
		$this->assertFalse( $result['pro_active'] );
		$this->assertSame( [ 'free' ], array_values( array_unique( array_column( $result['settings'], 'owner' ) ) ) );

		$settings = self::by_key( $result );
		$this->assertSame(
			[
				'key'     => 'media_per_page',
				'group'   => 'media',
				'label'   => 'Media per page',
				'type'    => 'integer',
				'value'   => 40,
				'default' => 20,
				'allowed' => null,
				'min'     => 1,
				'max'     => 1000,
				'owner'   => 'free',
			],
			$settings['media_per_page']
		);
		$this->assertSame( 'custom_text_to_alt', $settings['default_alt_text']['value'] );
		$this->assertSame( [ 'image_name_to_alt', 'custom_text_to_alt', 'none' ], $settings['default_alt_text']['allowed'] );
		$this->assertSame( 'Product photo', $settings['media_default_alt']['value'] );
		$this->assertTrue( $settings['auto_process_exif']['value'] );
		$this->assertFalse( $settings['auto_fill_metadata_from_exif']['value'], 'Default.' );
		$this->assertSame( 'maximum', $settings['compression_mode']['value'] );
		$this->assertSame( [ 'high_quality', 'balanced', 'maximum' ], $settings['compression_mode']['allowed'] );
		$this->assertFalse( $settings['conversion_webp_enabled']['value'] );
		$this->assertTrue( $settings['conversion_webp_enabled']['default'] );
		$this->assertSame( 'claude', $settings['ai_provider']['value'] );
		$this->assertSame( [ 'chatgpt', 'gemini', 'claude' ], $settings['ai_provider']['allowed'] );
		$this->assertSame( 'de_DE', $settings['ai_language']['value'] );
		$this->assertSame( 'claude-test-model', $settings['ai_claude_model']['value'] );
		$this->assertSame( 1, $settings['ai_max_suggestion_count']['value'] );
		$this->assertTrue( $settings['track_frontend_usage']['value'] );

		// A Pro setting is neither listed nor available without Pro.
		$this->assertArrayNotHasKey( 'media_rename_prefix', $settings );
		$this->assertSame( GetSettings::ERROR_NOT_SUPPORTED, self::call( [ 'keys' => [ 'media_rename_prefix' ] ] )->get_error_code() );
	}

	/**
	 * API keys (encrypted or legacy plain text), credentials and excluded
	 * settings never appear; key statuses do.
	 */
	public function test_no_secrets() {
		$legacy = 'legacy-plaintext-SECRET-0123456789';
		update_option(
			'tsmlt_settings',
			[
				'ai_gemini_key'            => $legacy,
				'ai_chatgpt_key'           => $legacy,
				'delete_data_on_uninstall' => 1,
				'others_file_support'      => [ 'svg' ],
				'custom_image_sizes'       => [ [ 'sizeKey' => 'tsmlt_secret_size' ] ],
				'media_table_column'       => [ 'Image' ],
			]
		);
		$saved = 'encrypted-key-SECRET-9876543210';
		$has_saved_key = Credentials::is_available() && Credentials::set( 'claude', $saved );

		$result = self::call();
		$json   = (string) wp_json_encode( $result );
		foreach ( [ $legacy, $saved, 'tsmlt_secret_size', 'svg', 'tsmlt_ai_credentials', 'tsmlt_credentials_version', 'tsmlt_mcp_access_disabled', '_key"', 'delete_data_on_uninstall', 'others_file_support', 'custom_image_sizes', 'media_table_column' ] as $secret ) {
			$this->assertStringNotContainsString( $secret, $json, $secret );
		}
		$this->assertSame( [ 'chatgpt', 'gemini', 'claude' ], array_keys( $result['ai_key_status'] ) );
		foreach ( $result['ai_key_status'] as $status ) {
			$this->assertContains( $status, [ 'saved', 'empty', 'unreadable' ] );
		}
		if ( $has_saved_key ) {
			$this->assertSame( 'saved', $result['ai_key_status']['claude'] );
		}
		$this->assertStringNotContainsString( $legacy, (string) wp_json_encode( self::call( [ 'keys' => [ 'ai_key_status' ] ] ) ) );
	}

	/**
	 * Unknown, excluded and secret keys get the same error, which names only
	 * what was asked for.
	 */
	public function test_unknown_and_excluded_keys() {
		$messages = [];
		foreach ( [ 'no_such_setting', 'delete_data_on_uninstall', 'others_file_support', 'ai_gemini_key', 'tsmlt_mcp_access_disabled', 'tsmlt_ai_credentials', 'custom_image_sizes', 'compression_backup_originals' ] as $key ) {
			$error = self::call( [ 'keys' => [ 'media_per_page', $key ] ] );
			$this->assertSame( GetSettings::ERROR_NOT_SUPPORTED, $error->get_error_code(), $key );
			$this->assertSame( 400, $error->get_error_data()['status'], $key );
			$messages[] = str_replace( $key, 'KEY', $error->get_error_message() );
		}
		$this->assertCount( 1, array_unique( $messages ), 'One answer, whatever the key is.' );
	}

	/**
	 * group, keys, and their limits.
	 */
	public function test_filters_and_limits() {
		$exif = self::call( [ 'group' => 'exif' ] );
		$this->assertSame( [ 'auto_process_exif', 'auto_fill_metadata_from_exif', 'auto_set_date_from_exif' ], array_column( $exif['settings'], 'key' ) );
		$this->assertSame( [], self::call( [ 'group' => 'rename' ] )['settings'], 'No Free rename settings.' );
		$this->assertArrayHasKey( 'ai_key_status', $exif );

		$keys = self::call( [ 'keys' => [ 'ai_provider', 'media_per_page' ] ] );
		$this->assertSame( [ 'ai_provider', 'media_per_page' ], array_column( $keys['settings'], 'key' ), 'In the order asked.' );
		$status = self::call( [ 'keys' => [ 'ai_key_status' ] ] );
		$this->assertSame( [], $status['settings'] );
		$this->assertCount( 3, $status['ai_key_status'] );

		$invalid = [
			[ 'keys' => [] ],
			[ 'keys' => array_map( static function ( $i ) { return 'key_' . $i; }, range( 1, GetSettings::MAX_KEYS + 1 ) ) ],
			[ 'keys' => [ 'media_per_page', 'media_per_page' ] ],
			[ 'keys' => [ 'Media-Per-Page' ] ],
			[ 'keys' => [ [ 'nested' ] ] ],
			[ 'keys' => 'media_per_page' ],
			[ 'group' => 'nope' ],
			[
				'group' => 'exif',
				'keys'  => [ 'media_per_page' ],
			],
			[ 'option' => 'siteurl' ],
		];
		foreach ( $invalid as $input ) {
			$this->assertSame( 'tsmlt_invalid_input', GetSettings::instance()->execute( $input )->get_error_code(), wp_json_encode( $input ) );
		}
		$this->assertSame( count( self::FREE_KEYS ), GetSettings::instance()->execute( null )['total'], 'No input: everything.' );
	}

	/**
	 * Values stored unchecked by the admin save are reported normalised,
	 * the way the plugin reads them.
	 */
	public function test_unchecked_values_are_normalised() {
		update_option(
			'tsmlt_settings',
			[
				'media_per_page'           => '99999',
				'rubbish_per_page'         => 'lots',
				'default_alt_text'         => '',
				'default_caption_text'     => '<script>alert(1)</script>',
				'media_default_desc'       => "<b>Bold</b> <script>alert(1)</script>text\nmore",
				'deregistered_image_sizes' => [ 'medium', '<b>Large</b>', [ 'nested' ], 'medium', '' ],
				'compression_mode'         => 'ultra',
				'ai_provider'              => 'other-ai',
				'ai_language'              => [ 'not', 'a', 'string' ],
				'auto_set_date_from_exif'  => 'yes',
				'track_frontend_usage'     => '',
			]
		);
		$settings = self::by_key( self::call() );

		$this->assertSame( 1000, $settings['media_per_page']['value'], 'Capped.' );
		$this->assertSame( 20, $settings['rubbish_per_page']['value'], 'Not a number: the default.' );
		$this->assertSame( 'none', $settings['default_alt_text']['value'], 'Empty behaves as none.' );
		$this->assertSame( 'none', $settings['default_caption_text']['value'] );
		$this->assertSame( 'Bold text more', $settings['media_default_desc']['value'] );
		$this->assertSame( [ 'medium' ], $settings['deregistered_image_sizes']['value'], 'Invalid entries dropped, duplicates removed.' );
		$this->assertSame( 'balanced', $settings['compression_mode']['value'] );
		$this->assertSame( 'gemini', $settings['ai_provider']['value'] );
		$this->assertSame( '', $settings['ai_language']['value'] );
		$this->assertTrue( $settings['auto_set_date_from_exif']['value'] );
		$this->assertFalse( $settings['track_frontend_usage']['value'] );
		$this->assertStringNotContainsString( '<script', (string) wp_json_encode( $settings ) );
	}

	/**
	 * Reading changes nothing: no option is added, updated or deleted.
	 */
	public function test_read_only() {
		update_option( 'tsmlt_settings', [ 'media_per_page' => '99999' ] );
		$before = get_option( 'tsmlt_settings' );
		$this->writes = [];
		foreach ( [ 'added_option', 'updated_option', 'deleted_option' ] as $hook ) {
			add_action( $hook, [ $this, 'record_write' ] );
		}

		self::call();
		self::call( [ 'group' => 'ai' ] );
		self::call( [ 'keys' => [ 'media_per_page', 'ai_key_status' ] ] );
		self::call( [ 'keys' => [ 'no_such_setting' ] ] );

		foreach ( [ 'added_option', 'updated_option', 'deleted_option' ] as $hook ) {
			remove_action( $hook, [ $this, 'record_write' ] );
		}
		$this->assertSame( [], $this->writes );
		$this->assertSame( $before, get_option( 'tsmlt_settings' ), 'The stored value is kept, even when it is reported normalised.' );
	}
}
