<?php
/**
 * Tests for the "MCP Docs" admin page.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Controllers\Admin\McpDocsPage;
use TinySolutions\mlt\Controllers\Admin\SubMenu;

/**
 * McpDocsPageTest
 */
class McpDocsPageTest extends WP_UnitTestCase {

	/**
	 * Load the admin menu API and reset the menu globals.
	 */
	public function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$GLOBALS['submenu'] = [];
	}

	/**
	 * Reset the menu globals.
	 */
	public function tear_down() {
		$GLOBALS['submenu'] = [];
		parent::tear_down();
	}

	/**
	 * Log in as a new user with a role.
	 *
	 * @param string $role Role.
	 */
	private function login_as( string $role ) {
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
	}

	/**
	 * Register the plugin's submenus and return the slugs under Media, in order.
	 *
	 * @return array<string, array> Slug => submenu entry.
	 */
	private function media_submenus(): array {
		SubMenu::instance()->register_sub_menu();
		$entries = [];
		foreach ( $GLOBALS['submenu']['upload.php'] ?? [] as $entry ) {
			$entries[ $entry[2] ] = $entry;
		}
		return $entries;
	}

	/**
	 * Capture a callable's output.
	 *
	 * @param callable $callback Callback.
	 *
	 * @return string
	 */
	private function capture( callable $callback ): string {
		ob_start();
		try {
			$callback();
		} finally {
			$output = (string) ob_get_clean();
		}
		return $output;
	}

	/**
	 * Administrators get "MCP Docs" under Media, directly after
	 * "Media Tools Settings".
	 */
	public function test_menu_is_registered_after_media_tools_settings() {
		$this->login_as( 'administrator' );

		$entries = $this->media_submenus();
		$slugs   = array_keys( $entries );

		$this->assertArrayHasKey( McpDocsPage::PAGE_SLUG, $entries );
		$this->assertSame( 'MCP Docs', trim( wp_strip_all_tags( $entries[ McpDocsPage::PAGE_SLUG ][0] ) ) );
		// Same sub-item markup (green, with the "↳" arrow span) as the plugin's other submenus.
		$this->assertStringContainsString( '<span class="tsmlt-submenu"', $entries[ McpDocsPage::PAGE_SLUG ][0] );
		$this->assertStringContainsString( '<span class="dashicons-icons"', $entries[ McpDocsPage::PAGE_SLUG ][0] );
		$this->assertSame( 'manage_options', $entries[ McpDocsPage::PAGE_SLUG ][1] );
		$this->assertSame(
			array_search( 'media-library-tools', $slugs, true ) + 1,
			array_search( McpDocsPage::PAGE_SLUG, $slugs, true )
		);
	}

	/**
	 * The "↳" marker for the plugin's sub-items is printed on every admin
	 * screen, not only on the plugin's own pages, because the menu is.
	 */
	public function test_submenu_marker_style_is_printed_on_every_admin_screen() {
		// The singleton may have been built in an earlier test, whose hooks the
		// test suite has since restored, so run its constructor again here.
		$constructor = new ReflectionMethod( SubMenu::class, '__construct' );
		if ( PHP_VERSION_ID < 80100 ) {
			$constructor->setAccessible( true );
		}
		$constructor->invoke( SubMenu::instance() );
		$this->assertNotFalse( has_action( 'admin_head', [ SubMenu::instance(), 'menu_styles' ] ) );

		$html = $this->capture( [ SubMenu::instance(), 'menu_styles' ] );

		$this->assertStringContainsString( '#adminmenu .tsmlt-submenu span.dashicons-icons:before { content: "↳";', $html );
	}

	/**
	 * Users who can upload files but cannot use MCP do not get the menu.
	 */
	public function test_menu_is_hidden_from_non_administrators() {
		$this->login_as( 'editor' );

		$entries = $this->media_submenus();

		$this->assertArrayHasKey( 'media-library-tools', $entries, 'Precondition: editors still see the plugin page.' );
		$this->assertArrayNotHasKey( McpDocsPage::PAGE_SLUG, $entries );
	}

	/**
	 * The page refuses users without manage_options.
	 */
	public function test_render_refuses_non_administrators() {
		$this->login_as( 'editor' );

		$this->expectException( WPDieException::class );
		$this->capture( [ McpDocsPage::instance(), 'render' ] );
	}

	/**
	 * The adapter status follows whether the adapter is loaded.
	 */
	public function test_adapter_status_matches_environment() {
		$status = McpDocsPage::instance()->get_adapter_status();

		if ( class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->assertSame( McpDocsPage::ADAPTER_ACTIVE, $status );
		} else {
			$this->assertContains( $status, [ McpDocsPage::ADAPTER_MISSING, McpDocsPage::ADAPTER_INACTIVE ] );
		}
	}

	/**
	 * Extract the JSON config the button script is given.
	 *
	 * @param string $html Rendered status box.
	 *
	 * @return array
	 */
	private function button_config( string $html ): array {
		$this->assertSame( 1, preg_match( '/var config = (\{.*?\});\n/', $html, $match ), 'The button script must be printed.' );
		return json_decode( $match[1], true );
	}

	/**
	 * Not installed: a warning with an AJAX install button.
	 */
	public function test_notice_when_adapter_is_missing() {
		$this->login_as( 'administrator' );

		$html = $this->capture(
			static function () {
				McpDocsPage::instance()->render_status_notice( McpDocsPage::ADAPTER_MISSING, true );
			}
		);

		$this->assertStringContainsString( 'notice-warning inline', $html );
		$this->assertStringContainsString( 'The MCP Adapter plugin is not installed.', $html );
		$this->assertStringContainsString( '>Install MCP Adapter plugin</a>', $html );
		$this->assertStringContainsString( 'data-step="install"', $html );
		// Without JavaScript the button is WordPress's own nonce-protected installer link.
		$this->assertStringContainsString( 'href="' . esc_url( McpDocsPage::instance()->get_install_url() ) . '"', $html );
		$this->assertStringContainsString( 'update.php?action=install-plugin', $html );
		$this->assertStringContainsString( '_wpnonce=' . wp_create_nonce( 'install-plugin_mcp-adapter' ), $html );

		// The script talks to core's AJAX actions with core's `updates` nonce.
		$config = $this->button_config( $html );
		$this->assertSame( admin_url( 'admin-ajax.php' ), $config['ajaxUrl'] );
		$this->assertSame( wp_create_nonce( 'updates' ), $config['nonce'] );
		$this->assertSame( 'mcp-adapter', $config['slug'] );
		$this->assertSame( 'mcp-adapter/mcp-adapter.php', $config['plugin'] );
		$this->assertStringContainsString( "post( 'install-plugin'", $html );
		$this->assertStringContainsString( "post( 'activate-plugin'", $html );
		// The button spins with core's update icon while it works.
		$this->assertStringContainsString( "button.classList.add( 'disabled', 'updating-message' )", $html );
		$this->assertStringContainsString( "button.classList.add( 'updated-message' )", $html );
	}

	/**
	 * Installed but inactive: a warning with an AJAX activate button.
	 */
	public function test_notice_when_adapter_is_inactive() {
		$this->login_as( 'administrator' );

		$html = $this->capture(
			static function () {
				McpDocsPage::instance()->render_status_notice( McpDocsPage::ADAPTER_INACTIVE, true );
			}
		);

		$this->assertStringContainsString( 'The MCP Adapter plugin is installed but not active.', $html );
		$this->assertStringContainsString( '>Activate MCP Adapter plugin</a>', $html );
		$this->assertStringContainsString( 'data-step="activate"', $html );
		$this->assertSame( wp_create_nonce( 'updates' ), $this->button_config( $html )['nonce'] );
		// Without JavaScript the button is WordPress's own nonce-protected activate link.
		$this->assertStringContainsString( 'href="' . esc_url( McpDocsPage::instance()->get_activate_url() ) . '"', $html );
		$this->assertStringContainsString( 'action=activate', $html );
		$this->assertStringContainsString( 'plugin=mcp-adapter%2Fmcp-adapter.php', $html );
		$this->assertStringContainsString( '_wpnonce=' . wp_create_nonce( 'activate-plugin_mcp-adapter/mcp-adapter.php' ), $html );
	}

	/**
	 * Active adapter on a supported WordPress: a success box, so the status
	 * is always visible, and no button or script.
	 */
	public function test_active_status_when_ready() {
		$this->login_as( 'administrator' );

		$html = $this->capture(
			static function () {
				McpDocsPage::instance()->render_status_notice( McpDocsPage::ADAPTER_ACTIVE, true );
			}
		);

		$this->assertStringContainsString( 'notice-success inline', $html );
		$this->assertStringContainsString( 'The MCP Adapter plugin is active', $html );
		$this->assertStringNotContainsString( 'notice-warning', $html );
		$this->assertStringNotContainsString( 'tsmlt-mcp-adapter-action', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * Without the Abilities API, an error explains the WordPress requirement.
	 */
	public function test_notice_without_abilities_api() {
		$this->login_as( 'administrator' );

		$html = $this->capture(
			static function () {
				McpDocsPage::instance()->render_status_notice( McpDocsPage::ADAPTER_ACTIVE, false );
			}
		);

		$this->assertStringContainsString( 'notice-error inline', $html );
		$this->assertStringContainsString( 'WordPress 6.9', $html );
		$this->assertStringNotContainsString( 'notice-success', $html, 'MCP cannot work, so the adapter is not reported as ready.' );
	}

	/**
	 * Install and activate buttons are only offered to users who may use them.
	 */
	public function test_buttons_need_plugin_capabilities() {
		$this->login_as( 'administrator' );
		$deny = static function ( $caps, $cap ) {
			return in_array( $cap, [ 'install_plugins', 'activate_plugin', 'activate_plugins' ], true ) ? [ 'do_not_allow' ] : $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 2 );

		$html = $this->capture(
			static function () {
				McpDocsPage::instance()->render_status_notice( McpDocsPage::ADAPTER_MISSING, true );
				McpDocsPage::instance()->render_status_notice( McpDocsPage::ADAPTER_INACTIVE, true );
			}
		);
		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assertStringContainsString( 'not installed', $html );
		$this->assertStringContainsString( 'not active', $html );
		$this->assertStringNotContainsString( 'button-primary', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( wp_create_nonce( 'updates' ), $html, 'No updates nonce for users who cannot use it.' );
	}

	/**
	 * The page documents this site's endpoint, the three tools and the
	 * current user, with the status message above the documentation.
	 */
	public function test_page_renders_documentation() {
		$this->login_as( 'administrator' );

		$html = $this->capture( [ McpDocsPage::instance(), 'render' ] );

		$this->assertStringContainsString( '<h1>MCP Docs</h1>', $html );
		$this->assertStringContainsString( esc_html( rest_url( 'media-library-tools/mcp' ) ), $html );
		foreach ( [ 'tsmlt-search-media', 'tsmlt-get-media-details', 'tsmlt-update-media-metadata', 'tsmlt-get-exif-fields', 'tsmlt-list-media-groups', 'tsmlt-set-media-groups', 'tsmlt-create-media-group', 'tsmlt-update-media-group', 'tsmlt-get-media-usage', 'tsmlt-start-usage-scan', 'tsmlt-get-usage-scan-status', 'tsmlt-preview-trash-unused-media', 'tsmlt-trash-unused-media', 'tsmlt-get-exif-caption', 'tsmlt-update-exif-fields' ] as $tool ) {
			$this->assertStringContainsString( $tool, $html );
		}
		$this->assertStringContainsString( esc_html( wp_get_current_user()->user_login ), $html );

		// Claude Desktop setup, with where its configuration file lives.
		$this->assertStringContainsString( 'Claude Desktop', $html );
		$this->assertStringContainsString( '~/Library/Application Support/Claude/claude_desktop_config.json', $html );
		$this->assertStringContainsString( '%APPDATA%\\Claude\\claude_desktop_config.json', $html );

		// Default server: its URL, gateway tools, slash-named abilities and its own configs.
		$gateway = rest_url( 'mcp/mcp-adapter-default-server' );
		$this->assertStringContainsString( 'Using the MCP Adapter default server', $html );
		$this->assertStringContainsString( 'Which endpoint should I use?', $html );
		$this->assertStringContainsString( 'Avoid configuring both:', $html );
		$this->assertStringContainsString( 'mcp-adapter-execute-ability', $html );
		$this->assertStringContainsString( 'tsmlt/search-media', $html );
		$this->assertStringContainsString( 'claude mcp add --transport http mcp-adapter-default', $html );
		$this->assertStringContainsString( esc_html( $gateway ), $html );

		// Every JSON configuration on the page is valid JSON pointing at a real endpoint.
		preg_match_all( '#<code>(\{.*?\})</code>#s', $html, $blocks );
		$configs = array_values(
			array_filter(
				array_map(
					static function ( $block ) {
						return json_decode( html_entity_decode( $block, ENT_QUOTES ), true );
					},
					$blocks[1]
				),
				static function ( $decoded ) {
					return isset( $decoded['mcpServers'] );
				}
			)
		);
		$urls = [];
		foreach ( $configs as $config ) {
			foreach ( $config['mcpServers'] as $server ) {
				$urls[] = $server['env']['WP_API_URL'];
			}
		}
		$this->assertSame( [ rest_url( 'media-library-tools/mcp' ), $gateway ], $urls );

		// Match the notice markup, not the class name, which the page's styles also contain.
		$ready  = class_exists( '\WP\MCP\Core\McpAdapter' );
		$notice = $ready
			? '<div class="notice notice-success inline tsmlt-mcp-docs__notice">'
			: '<div class="notice notice-warning inline tsmlt-mcp-docs__notice">';
		$this->assertStringContainsString( $notice, $html, 'A status box is always shown.' );
		$this->assertLessThan( strpos( $html, '<h2>Requirements</h2>' ), strpos( $html, $notice ), 'The status box is above the documentation.' );
	}
}
