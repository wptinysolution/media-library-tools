<?php
/**
 * Media › MCP: menu position, the access switch and credential AJAX actions
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
	 * The MCP page sits directly above MCP Docs and renders the React mount.
	 */
	public function test_menu_position_and_render() {
		global $submenu;
		wp_set_current_user( $this->users['administrator'] );
		$submenu = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test fixture.
		SubMenu::instance()->register_sub_menu();
		$slugs = array_column( $submenu['upload.php'], 2 );
		$mcp   = array_search( McpPage::PAGE_SLUG, $slugs, true );
		$docs  = array_search( McpDocsPage::PAGE_SLUG, $slugs, true );
		$this->assertNotFalse( $mcp );
		$this->assertSame( $docs - 1, $mcp, 'Directly above MCP Docs.' );
		$this->assertSame( 'MCP', wp_strip_all_tags( $submenu['upload.php'][ array_keys( $submenu['upload.php'] )[ $mcp ] ][0] ) );

		ob_start();
		McpPage::instance()->render();
		$this->assertStringContainsString( 'id="tsmlt_mcp_root"', ob_get_clean() );
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
