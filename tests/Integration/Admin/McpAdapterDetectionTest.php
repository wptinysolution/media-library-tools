<?php
/**
 * How the MCP Docs page decides the MCP Adapter is active.
 *
 * Another plugin can bundle the adapter library: its class is then loadable
 * although the MCP Adapter plugin is not installed and the adapter may never
 * start. The page must call it active only when an adapter instance has been
 * started, and must name the plugin that provides it.
 *
 * A stand-in `WP\MCP\Core\McpAdapter` class is declared, so each test runs in
 * its own process, and the tests skip when the real adapter is loaded. The
 * "bundling plugin" is a throwaway folder in the test WordPress copy's
 * plugins directory, created and removed by the test.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Controllers\Admin\McpDocsPage;

/**
 * McpAdapterDetectionTest
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class McpAdapterDetectionTest extends WP_UnitTestCase {

	/**
	 * Folder of the throwaway bundling plugin.
	 *
	 * @var string
	 */
	const BUNDLER = 'tsmlt-test-mcp-bundler';

	/**
	 * Files this test created, removed in tear_down().
	 *
	 * @var string[]
	 */
	private $created = [];

	/**
	 * Skip when the real adapter is loaded; log in as an administrator.
	 */
	public function set_up() {
		parent::set_up();
		if ( class_exists( 'WP\MCP\Core\McpAdapter', false ) ) {
			$this->markTestSkipped( 'The real MCP Adapter is loaded; these tests need a stand-in class.' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Remove the throwaway plugin.
	 */
	public function tear_down() {
		foreach ( array_reverse( $this->created ) as $path ) {
			if ( is_dir( $path ) ) {
				rmdir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture.
			} elseif ( file_exists( $path ) ) {
				wp_delete_file( $path );
			}
		}
		$this->created = [];
		wp_clean_plugins_cache( false );
		parent::tear_down();
	}

	/**
	 * Declare the stand-in adapter class inside a throwaway plugin folder, as
	 * a plugin that bundles the library would ship it.
	 */
	private function bundle_adapter_class() {
		$root = WP_PLUGIN_DIR . '/' . self::BUNDLER;
		$dir  = $root . '/vendor/wordpress/mcp-adapter';
		$this->assertFalse( file_exists( $root ), 'The throwaway plugin folder must not already exist.' );
		foreach ( [ $root, $root . '/vendor', $root . '/vendor/wordpress', $dir ] as $folder ) {
			wp_mkdir_p( $folder );
			$this->created[] = $folder;
		}
		$main  = $root . '/' . self::BUNDLER . '.php';
		$class = $dir . '/McpAdapter.php';
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		file_put_contents( $main, "<?php\n/**\n * Plugin Name: Test MCP Bundler\n */\n" );
		file_put_contents( $class, "<?php\nnamespace WP\\MCP\\Core;\nclass McpAdapter {\n\tconst VERSION = '9.9.9-test';\n\tpublic function init() {}\n}\n" );
		// phpcs:enable
		$this->created[] = $main;
		$this->created[] = $class;
		wp_clean_plugins_cache( false );
		require $class;
	}

	/**
	 * The status box HTML.
	 *
	 * @return string
	 */
	private function status_box(): string {
		$page = McpDocsPage::instance();
		ob_start();
		$page->render_status_notice( $page->get_adapter_status(), true );
		return (string) ob_get_clean();
	}

	/**
	 * A bundled adapter class that was never started is not "active": the
	 * MCP Adapter plugin is not installed and the endpoint does not exist.
	 */
	public function test_loadable_but_not_started_is_not_active() {
		$this->bundle_adapter_class();
		$this->assertTrue( class_exists( 'WP\MCP\Core\McpAdapter', false ), 'Precondition: the class is loaded.' );

		$page = McpDocsPage::instance();
		$this->assertFalse( $page->is_adapter_running() );
		$this->assertSame( McpDocsPage::ADAPTER_MISSING, $page->get_adapter_status() );

		$html = $this->status_box();
		$this->assertStringContainsString( 'The MCP Adapter plugin is not installed.', $html );
		$this->assertStringContainsString( '>Install MCP Adapter plugin</a>', $html );
		$this->assertStringNotContainsString( 'notice-success', $html );
		$this->assertStringNotContainsString( 'AI assistants can connect', $html );
	}

	/**
	 * Once a plugin starts the bundled adapter, it is active, and the page
	 * names the plugin that provides it instead of claiming the MCP Adapter
	 * plugin is installed.
	 */
	public function test_started_bundled_adapter_names_its_provider() {
		$this->bundle_adapter_class();
		// What McpAdapter::instance() does: start the adapter on REST requests.
		$adapter = new \WP\MCP\Core\McpAdapter();
		add_action( 'rest_api_init', [ $adapter, 'init' ], 15 );

		$page = McpDocsPage::instance();
		$this->assertTrue( $page->is_adapter_running() );
		$this->assertSame( McpDocsPage::ADAPTER_ACTIVE, $page->get_adapter_status() );
		$this->assertSame( 'Test MCP Bundler', $page->get_adapter_provider() );

		$html = $this->status_box();
		$this->assertStringContainsString( 'notice-success', $html );
		$this->assertStringContainsString( 'The MCP Adapter is running, provided by Test MCP Bundler.', $html );
		$this->assertStringNotContainsString( 'The MCP Adapter plugin is active', $html );
		$this->assertStringNotContainsString( 'tsmlt-mcp-adapter-action', $html );
	}

	/**
	 * After the adapter has initialized (mcp_adapter_init fired) it counts as
	 * running even without a pending hook.
	 */
	public function test_initialized_adapter_is_running() {
		$this->bundle_adapter_class();
		do_action( 'mcp_adapter_init', new \WP\MCP\Core\McpAdapter() );

		$this->assertTrue( McpDocsPage::instance()->is_adapter_running() );
	}

	/**
	 * Without any adapter class, nothing is running.
	 */
	public function test_no_adapter_class_is_not_running() {
		$this->assertFalse( class_exists( 'WP\MCP\Core\McpAdapter', false ) );

		$this->assertFalse( McpDocsPage::instance()->is_adapter_running() );
		$this->assertSame( '', McpDocsPage::instance()->get_adapter_provider() );
	}
}
