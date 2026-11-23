<?php
/**
 * Media › MCP Connection: one menu item, the Connection and Documentation
 * tabs, the old MCP Docs address, the connection URLs, the access switch and
 * credential AJAX actions
 * (nonce, manage_options, validation), and the Application Password they
 * create.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\McpAccess;
use TinySolutions\mlt\Controllers\Admin\McpDocsPage;
use TinySolutions\mlt\Controllers\Admin\McpPage;
use TinySolutions\mlt\Controllers\Admin\SubMenu;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Mcp\McpConnection;

/**
 * McpPageTest
 */
class McpPageTest extends WP_Ajax_UnitTestCase {

	/**
	 * Users by role.
	 *
	 * @var array<string, int>
	 */
	private $users = [];

	/**
	 * A user per role; Application Passwords available; access on.
	 */
	public function set_up() {
		parent::set_up();
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		update_option( 'tsmlt_plugin_version', TSMLT_VERSION );
		foreach ( [ 'administrator', 'editor' ] as $role ) {
			$this->users[ $role ] = self::factory()->user->create( [ 'role' => $role ] );
		}
		add_filter( 'wp_is_application_passwords_available', '__return_true' );
		delete_option( McpAccess::OPTION );
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		remove_filter( 'wp_is_application_passwords_available', '__return_true' );
		remove_filter( 'wp_is_application_passwords_available_for_user', '__return_false' );
		delete_option( McpAccess::OPTION );
		parent::tear_down();
	}

	/**
	 * Call an AJAX action.
	 *
	 * @param string $role   Role.
	 * @param string $action Action.
	 * @param array  $params Params.
	 *
	 * @return array|null
	 */
	private function ajax( string $role, string $action, array $params ) {
		wp_set_current_user( $this->users[ $role ] );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['nonce']            = wp_create_nonce( Fns::NONCE_ID );
		$_POST['params']           = wp_slash( wp_json_encode( $params ) );
		$this->_last_response      = '';
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}
		return json_decode( $this->_last_response, true );
	}

	/**
	 * Render the page for a tab.
	 *
	 * @param string $tab Tab ('' for the default).
	 *
	 * @return string
	 */
	private function render_tab( string $tab ): string {
		wp_set_current_user( $this->users['administrator'] );
		if ( '' === $tab ) {
			unset( $_GET[ McpPage::TAB_PARAM ] );
		} else {
			$_GET[ McpPage::TAB_PARAM ] = $tab;
		}
		ob_start();
		McpPage::instance()->render();
		$html = (string) ob_get_clean();
		unset( $_GET[ McpPage::TAB_PARAM ] );
		return $html;
	}

	/**
	 * One MCP menu item, "MCP Connection", for the merged page.
	 */
	public function test_single_menu_item() {
		global $submenu;
		wp_set_current_user( $this->users['administrator'] );
		$submenu = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test fixture.
		SubMenu::instance()->register_sub_menu();
		$items = array_values(
			array_filter(
				$submenu['upload.php'],
				static function ( $item ) {
					return false !== stripos( wp_strip_all_tags( $item[0] ), 'MCP' );
				}
			)
		);
		$this->assertCount( 1, $items, 'Only one MCP menu item.' );
		$this->assertSame( 'MCP Connection', trim( wp_strip_all_tags( $items[0][0] ) ) );
		$this->assertSame( 'MCP Connection', $items[0][3], 'Page title.' );
		$this->assertSame( McpPage::PAGE_SLUG, $items[0][2], 'The existing address is kept.' );
		$this->assertNotContains( McpDocsPage::PAGE_SLUG, array_column( $submenu['upload.php'], 2 ), 'The old MCP Docs submenu is gone.' );
	}

	/**
	 * Both tabs: Connection (the default) holds the React app; Documentation
	 * holds the MCP Docs reference, with one heading for the page.
	 */
	public function test_tabs() {
		$connection = $this->render_tab( '' );
		$this->assertSame( 1, substr_count( $connection, '<h1>' ) );
		$this->assertStringContainsString( '<h1>MCP Connection</h1>', $connection );
		$this->assertStringContainsString( 'Connect AI assistants such as Claude, Codex and Cursor to your media library', $connection, 'A short description under the title.' );
		$this->assertSame( substr_count( $connection, '<div' ), substr_count( $connection, '</div>' ) );
		$this->assertStringContainsString( '>Connection</a>', $connection );
		$this->assertStringContainsString( '>Documentation</a>', $connection );
		$this->assertStringContainsString( 'nav-tab nav-tab-active" aria-current="page">Connection', $connection );
		$this->assertStringContainsString( 'id="tsmlt_mcp_root"', $connection );
		$this->assertStringNotContainsString( 'tsmlt-mcp-docs__section', $connection );
		$this->assertStringContainsString( esc_url( McpPage::tab_url( McpPage::TAB_DOCUMENTATION ) ), $connection );

		$docs = $this->render_tab( McpPage::TAB_DOCUMENTATION );
		$this->assertSame( 1, substr_count( $docs, '<h1>' ) );
		$this->assertStringContainsString( 'nav-tab nav-tab-active" aria-current="page">Documentation', $docs );
		$this->assertStringNotContainsString( 'id="tsmlt_mcp_root"', $docs );
		foreach ( [ 'Available tools', 'tsmlt-search-media', 'Access control', 'Troubleshooting', 'Endpoints', 'Usage examples' ] as $part ) {
			$this->assertStringContainsString( esc_html( $part ), $docs, $part );
		}
		$this->assertSame( McpPage::TAB_CONNECTION, McpPage::current_tab(), 'Unknown or missing tab: Connection.' );
	}

	/**
	 * With MCP access turned off the Documentation tab is still there and
	 * complete; the Connection tab keeps its mount (the app shows only the
	 * switch and a message).
	 */
	public function test_documentation_while_access_is_off() {
		McpAccess::set_disabled( true );
		$docs = $this->render_tab( McpPage::TAB_DOCUMENTATION );
		$this->assertStringContainsString( 'Available tools', $docs );
		$this->assertStringContainsString( 'tsmlt-search-media', $docs );
		$this->assertStringContainsString( 'id="tsmlt_mcp_root"', $this->render_tab( '' ) );
	}

	/**
	 * The old MCP Docs address goes to the Documentation tab, for
	 * administrators only.
	 */
	public function test_legacy_docs_address() {
		// SubMenu registers the redirect when built; build it again, as the
		// test suite restores hooks between tests.
		$constructor = new ReflectionMethod( SubMenu::class, '__construct' );
		$constructor->setAccessible( true );
		$constructor->invoke( SubMenu::instance() );
		$this->assertNotFalse( has_action( 'admin_page_access_denied', [ McpPage::instance(), 'redirect_legacy_docs' ] ) );
		wp_set_current_user( $this->users['administrator'] );
		$this->assertSame( McpPage::tab_url( McpPage::TAB_DOCUMENTATION ), McpPage::legacy_redirect_url( McpDocsPage::PAGE_SLUG ) );
		$this->assertStringContainsString( 'page=media-library-tools-mcp&tab=documentation', McpPage::legacy_redirect_url( McpDocsPage::PAGE_SLUG ) );
		$this->assertSame( '', McpPage::legacy_redirect_url( 'something-else' ) );
		wp_set_current_user( $this->users['editor'] );
		$this->assertSame( '', McpPage::legacy_redirect_url( McpDocsPage::PAGE_SLUG ) );
	}

	/**
	 * Both connection URLs come from the site's REST URL: an HTTPS
	 * subdirectory install, and a custom REST prefix.
	 */
	public function test_connection_urls_are_built_from_the_site() {
		wp_set_current_user( $this->users['administrator'] );
		update_option( 'siteurl', 'https://media.example.org/wp' );
		update_option( 'home', 'https://media.example.org/wp' );
		$settings = McpConnection::instance()->settings();
		$this->assertSame( rest_url( 'mcp/mcp-adapter-default-server' ), $settings['serverUrl'] );
		$this->assertSame( rest_url( 'media-library-tools/mcp' ), $settings['dedicatedUrl'] );
		$this->assertStringStartsWith( 'https://media.example.org/wp/', $settings['serverUrl'] );
		$this->assertStringStartsWith( 'https://media.example.org/wp/', $settings['dedicatedUrl'] );
		$this->assertSame( McpPage::tab_url( McpPage::TAB_DOCUMENTATION ), $settings['docsUrl'] );

		$this->set_permalink_structure( '/%postname%/' );
		add_filter( 'rest_url_prefix', [ $this, 'custom_prefix' ] );
		$custom = McpConnection::instance()->settings();
		remove_filter( 'rest_url_prefix', [ $this, 'custom_prefix' ] );
		$this->assertStringContainsString( '/api/mcp/mcp-adapter-default-server', $custom['serverUrl'] );
		$this->assertStringContainsString( '/api/media-library-tools/mcp', $custom['dedicatedUrl'] );
		$this->set_permalink_structure( '' );
	}

	/**
	 * A custom REST prefix.
	 *
	 * @return string
	 */
	public function custom_prefix() {
		return 'api';
	}

	/**
	 * Make the MCP Adapter look missing, installed but inactive, or running,
	 * whatever the suite loaded; the test case restores hooks and actions
	 * afterwards.
	 *
	 * @param string $status One of McpDocsPage::ADAPTER_*.
	 */
	private function simulate_adapter( string $status ) {
		global $wp_filter, $wp_actions;
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( McpDocsPage::ADAPTER_ACTIVE !== $status ) {
			unset( $wp_actions['mcp_adapter_init'] );
			foreach ( [ 'rest_api_init', 'init' ] as $hook ) {
				unset( $wp_filter[ $hook ] );
			}
		}
		$plugins = McpDocsPage::ADAPTER_INACTIVE === $status ? [ McpDocsPage::ADAPTER_PLUGIN_FILE => [ 'Name' => 'MCP Adapter' ] ] : [];
		wp_cache_set( 'plugins', [ '' => $plugins ], 'plugins' );
		if ( McpDocsPage::ADAPTER_ACTIVE === $status ) {
			// Started, as after the adapter's init; the action itself is not fired.
			$wp_actions['mcp_adapter_init'] = 1; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test fixture, restored by the test case.
		}
		$this->assertSame( $status, McpDocsPage::instance()->get_adapter_status(), 'Precondition.' );
	}

	/**
	 * The MCP Adapter notice is the page's: under the heading and above the
	 * tabs, on both tabs, worded for each state, with the matching action;
	 * none while the adapter runs. Shown while MCP access is off as well.
	 */
	public function test_adapter_notice_above_the_tabs() {
		$cases = [
			McpDocsPage::ADAPTER_MISSING  => [ 'MCP Adapter is not installed.', '>Install Now</a>', 'update.php?action=install-plugin' ],
			McpDocsPage::ADAPTER_INACTIVE => [ 'MCP Adapter is inactive.', '>Activate Now</a>', 'plugins.php?action=activate' ],
		];
		foreach ( $cases as $status => $expected ) {
			$this->simulate_adapter( $status );
			foreach ( [ '', McpPage::TAB_DOCUMENTATION ] as $tab ) {
				$html = $this->render_tab( $tab );
				foreach ( $expected as $part ) {
					$this->assertStringContainsString( $part, $html, "$status / $tab" );
				}
				$heading = strpos( $html, '<h1>MCP Connection</h1>' );
				$notice  = strpos( $html, '<div class="notice notice-warning tsmlt-mcp-notice">' );
				$tabs    = strpos( $html, '<nav class="nav-tab-wrapper"' );
				$this->assertTrue( $heading < $notice && $notice < $tabs, "Heading, notice, tabs: $status / $tab" );
				$this->assertSame( 1, substr_count( $html, 'tsmlt-mcp-notice"' ), 'Once per page.' );
				// WordPress moves notices to just after `wp-header-end`; it sits right before this one.
				$this->assertSame( strpos( $html, '<hr class="wp-header-end">' ) < $notice && $heading < strpos( $html, '<hr class="wp-header-end">' ), true );
				$this->assertDoesNotMatchRegularExpression( '/class="[^"]*\binline\b[^"]*tsmlt-mcp-notice/', $html, 'No `inline` class: the Tailwind `.inline` utility would collapse the box.' );
			}
		}

		McpAccess::set_disabled( true );
		$this->assertStringContainsString( 'MCP Adapter is inactive.', $this->render_tab( '' ), 'Also while MCP access is off.' );
		McpAccess::set_disabled( false );

		$this->simulate_adapter( McpDocsPage::ADAPTER_ACTIVE );
		foreach ( [ '', McpPage::TAB_DOCUMENTATION ] as $tab ) {
			$this->assertStringNotContainsString( 'tsmlt-mcp-notice"', $this->render_tab( $tab ), "No notice while the adapter runs: $tab" );
		}
	}

	/**
	 * With the adapter missing or inactive the page settings mark the
	 * recommended connection unavailable (the app then shows no copyable
	 * URL) and refuse to create credentials for it; the dedicated URL is
	 * still provided.
	 */
	public function test_settings_follow_the_adapter() {
		wp_set_current_user( $this->users['administrator'] );
		foreach ( [ McpDocsPage::ADAPTER_MISSING, McpDocsPage::ADAPTER_INACTIVE ] as $status ) {
			$this->simulate_adapter( $status );
			$settings = McpConnection::instance()->settings();
			$this->assertSame( $status, $settings['adapterStatus'] );
			$this->assertSame( rest_url( 'media-library-tools/mcp' ), $settings['dedicatedUrl'] );
		}
	}

	/**
	 * Turning access off and on: administrators only, a boolean only.
	 */
	public function test_save_access() {
		$off = $this->ajax( 'administrator', 'tsmlt_mcp_save_access', [ 'disabled' => true ] );
		$this->assertTrue( $off['success'] );
		$this->assertFalse( $off['data']['enabled'] );
		$this->assertFalse( McpAccess::is_enabled() );

		$on = $this->ajax( 'administrator', 'tsmlt_mcp_save_access', [ 'disabled' => false ] );
		$this->assertTrue( $on['data']['enabled'] );
		$this->assertTrue( McpAccess::is_enabled() );

		$this->assertFalse( $this->ajax( 'administrator', 'tsmlt_mcp_save_access', [ 'disabled' => 'yes' ] )['success'] );
		$this->assertTrue( McpAccess::is_enabled() );

		$refused = $this->ajax( 'editor', 'tsmlt_mcp_save_access', [ 'disabled' => true ] );
		$this->assertNotTrue( $refused['success'] ?? null );
		$this->assertTrue( McpAccess::is_enabled(), 'Editors cannot turn it off.' );
	}

	/**
	 * Credentials: consent and a known client required; refused while access
	 * is off, for editors, and without Application Passwords; on success a
	 * named Application Password exists and the returned credentials match it.
	 */
	public function test_create_credentials() {
		$this->assertFalse( $this->ajax( 'administrator', 'tsmlt_mcp_create_credentials', [ 'client' => 'codex' ] )['success'], 'No consent.' );
		$this->assertFalse(
			$this->ajax(
				'administrator',
				'tsmlt_mcp_create_credentials',
				[
					'client'  => 'unknown',
					'consent' => true,
				]
			)['success']
		);
		$this->assertNotTrue(
			$this->ajax(
				'editor',
				'tsmlt_mcp_create_credentials',
				[
					'client'  => 'codex',
					'consent' => true,
				]
			)['success'] ?? null
		);

		McpAccess::set_disabled( true );
		$this->assertFalse(
			$this->ajax(
				'administrator',
				'tsmlt_mcp_create_credentials',
				[
					'client'  => 'codex',
					'consent' => true,
				]
			)['success'],
			'Access off.'
		);
		McpAccess::set_disabled( false );

		add_filter( 'wp_is_application_passwords_available_for_user', '__return_false' );
		$this->assertFalse(
			$this->ajax(
				'administrator',
				'tsmlt_mcp_create_credentials',
				[
					'client'  => 'codex',
					'consent' => true,
				]
			)['success'],
			'Passwords off for the user.'
		);
		remove_filter( 'wp_is_application_passwords_available_for_user', '__return_false' );
		$this->assertSame( [], WP_Application_Passwords::get_user_application_passwords( $this->users['administrator'] ), 'Nothing created by refused requests.' );

		$result = $this->ajax(
			'administrator',
			'tsmlt_mcp_create_credentials',
			[
				'client'  => 'claude-code',
				'consent' => true,
			]
		);

		$this->assertTrue( $result['success'], wp_json_encode( $result['data']['message'] ?? '' ) );
		$data = $result['data'];
		$this->assertSame( get_userdata( $this->users['administrator'] )->user_login, $data['username'] );
		$this->assertSame( rest_url( 'mcp/mcp-adapter-default-server' ), $data['serverUrl'] );
		$raw = str_replace( ' ', '', $data['password'] );
		$this->assertSame( base64_encode( $data['username'] . ':' . $raw ), $data['encodedCredentials'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Comparing the Basic credentials.
		$passwords = WP_Application_Passwords::get_user_application_passwords( $this->users['administrator'] );
		$this->assertCount( 1, $passwords );
		$this->assertStringStartsWith( 'Media Library Tools MCP – Claude Code', $passwords[0]['name'] );
		$this->assertTrue( WP_Application_Passwords::check_password( $raw, $passwords[0]['password'] ) || wp_check_password( $raw, $passwords[0]['password'] ), 'The returned password is the stored one.' );
		$this->assertStringNotContainsString( $raw, (string) wp_json_encode( wp_load_alloptions() ), 'Not stored in options.' );
	}
}
