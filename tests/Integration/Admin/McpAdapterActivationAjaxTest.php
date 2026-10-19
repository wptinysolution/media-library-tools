<?php
/**
 * The MCP Docs page's "Activate MCP Adapter plugin" button calls WordPress
 * core's `activate-plugin` AJAX action. These tests send that action exactly
 * the parameters the button's script sends, and check core accepts them for
 * an administrator and refuses them otherwise.
 *
 * Uses a throwaway, header-only plugin at `mcp-adapter/mcp-adapter.php` in
 * the test WordPress copy's plugins directory, created and removed by the
 * tests. Skipped if a real plugin already exists there. The install action
 * downloads from WordPress.org, so it is not exercised here.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Controllers\Admin\McpDocsPage;

/**
 * McpAdapterActivationAjaxTest
 */
class McpAdapterActivationAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Whether this test created the fake plugin (and so must remove it).
	 *
	 * @var bool
	 */
	private $created = false;

	/**
	 * Create the fake plugin.
	 */
	public function set_up() {
		parent::set_up();
		// _handleAjax() fires admin_init; keep core's update checks off the network.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$file = WP_PLUGIN_DIR . '/' . McpDocsPage::ADAPTER_PLUGIN_FILE;
		if ( file_exists( $file ) ) {
			$this->markTestSkipped( 'A plugin already exists at ' . McpDocsPage::ADAPTER_PLUGIN_FILE . '; not overwriting it.' );
		}
		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, "<?php\n/**\n * Plugin Name: MCP Adapter\n * Version: 0.0.0-test\n */\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->created = true;
		wp_clean_plugins_cache( false );
	}

	/**
	 * Remove the fake plugin.
	 */
	public function tear_down() {
		if ( $this->created ) {
			$file = WP_PLUGIN_DIR . '/' . McpDocsPage::ADAPTER_PLUGIN_FILE;
			deactivate_plugins( McpDocsPage::ADAPTER_PLUGIN_FILE, true );
			wp_delete_file( $file );
			rmdir( dirname( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture.
			wp_clean_plugins_cache( false );
			$this->created = false;
		}
		parent::tear_down();
	}

	/**
	 * Send core's `activate-plugin` exactly as the button's script does.
	 *
	 * @param bool $valid_nonce Whether to send a valid `updates` nonce.
	 *
	 * @return array{response: array|null, died: string}
	 */
	private function activate_like_the_button( bool $valid_nonce = true ): array {
		// Wire core's handler the way wp-admin/admin-ajax.php does on a real
		// request; the test library does not register this action itself.
		require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';
		if ( ! has_action( 'wp_ajax_activate-plugin', 'wp_ajax_activate_plugin' ) ) {
			add_action( 'wp_ajax_activate-plugin', 'wp_ajax_activate_plugin', 1 );
		}
		$this->assertNotFalse( has_action( 'wp_ajax_activate-plugin', 'wp_ajax_activate_plugin' ), 'Core activate-plugin handler must be hooked.' );

		$_POST['_ajax_nonce'] = $valid_nonce ? wp_create_nonce( 'updates' ) : 'invalid';
		$_POST['slug']        = McpDocsPage::ADAPTER_SLUG;
		$_POST['plugin']      = McpDocsPage::ADAPTER_PLUGIN_FILE;
		$_POST['name']        = 'MCP Adapter';
		$this->_last_response = '';
		$died                 = '';
		try {
			$this->_handleAjax( 'activate-plugin' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			$died = $e->getMessage();
		}
		return [
			'response' => json_decode( $this->_last_response, true ),
			'died'     => $died,
		];
	}

	/**
	 * The page reports the fake plugin as installed but inactive, which is
	 * when it shows the Activate button.
	 */
	public function test_page_sees_installed_inactive_adapter() {
		if ( class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'The real MCP Adapter is loaded, so the page reports it as active.' );
		}
		$this->assertSame( McpDocsPage::ADAPTER_INACTIVE, McpDocsPage::instance()->get_adapter_status() );
	}

	/**
	 * An administrator's request activates the plugin.
	 */
	public function test_administrator_activates_adapter() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertFalse( is_plugin_active( McpDocsPage::ADAPTER_PLUGIN_FILE ) );

		$result = $this->activate_like_the_button();

		$this->assertTrue( $result['response']['success'], wp_json_encode( $result ) );
		$this->assertSame( McpDocsPage::ADAPTER_PLUGIN_FILE, $result['response']['data']['plugin'] );
		$this->assertTrue( is_plugin_active( McpDocsPage::ADAPTER_PLUGIN_FILE ) );
	}

	/**
	 * A user who may not activate plugins is refused.
	 */
	public function test_editor_is_refused() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$result = $this->activate_like_the_button();

		$this->assertFalse( $result['response']['success'] );
		$this->assertFalse( is_plugin_active( McpDocsPage::ADAPTER_PLUGIN_FILE ) );
	}

	/**
	 * A request without a valid nonce is refused.
	 */
	public function test_invalid_nonce_is_refused() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$result = $this->activate_like_the_button( false );

		$this->assertSame( '-1', $result['died'] );
		$this->assertFalse( is_plugin_active( McpDocsPage::ADAPTER_PLUGIN_FILE ) );
	}
}
