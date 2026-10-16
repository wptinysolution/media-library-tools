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
	 * Not installed: a warning with an install link.
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
		$this->assertStringContainsString( esc_url( admin_url( 'plugin-install.php?s=mcp-adapter&tab=search&type=term' ) ), $html );
	}

	/**
	 * Installed but inactive: a warning with a nonce-protected activate link.
	 */
	public function test_notice_when_adapter_is_inactive() {
		$this->login_as( 'administrator' );

		$html = $this->capture(
			static function () {
				McpDocsPage::instance()->render_status_notice( McpDocsPage::ADAPTER_INACTIVE, true );
			}
		);

		$this->assertStringContainsString( 'The MCP Adapter plugin is installed but not active.', $html );
		$this->assertStringContainsString( 'action=activate', $html );
		$this->assertStringContainsString( 'plugin=mcp-adapter%2Fmcp-adapter.php', $html );
		$this->assertStringContainsString( '_wpnonce=' . wp_create_nonce( 'activate-plugin_mcp-adapter/mcp-adapter.php' ), $html );
	}

	/**
	 * Active adapter on a supported WordPress: no message.
	 */
	public function test_no_notice_when_ready() {
		$this->login_as( 'administrator' );

		$html = $this->capture(
			static function () {
				McpDocsPage::instance()->render_status_notice( McpDocsPage::ADAPTER_ACTIVE, true );
			}
		);

		$this->assertSame( '', trim( $html ) );
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
		foreach ( [ 'tsmlt-search-media', 'tsmlt-get-media-details', 'tsmlt-update-media-metadata' ] as $tool ) {
			$this->assertStringContainsString( $tool, $html );
		}
		$this->assertStringContainsString( esc_html( wp_get_current_user()->user_login ), $html );

		// Match the notice markup, not the class name, which the page's styles also contain.
		$notice = '<div class="notice notice-warning inline tsmlt-mcp-docs__notice">';
		$ready  = class_exists( '\WP\MCP\Core\McpAdapter' );
		$this->assertSame( ! $ready, false !== strpos( $html, $notice ), 'The message shows only when the adapter is not active.' );
		if ( ! $ready ) {
			$this->assertLessThan( strpos( $html, '<h2>Requirements</h2>' ), strpos( $html, $notice ), 'The message is above the documentation.' );
		}
	}
}
