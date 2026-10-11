<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite with this plugin active.
 *
 * @package TinySolutions\mlt
 */

$tsmlt_plugin_dir = dirname( __DIR__ );

require_once $tsmlt_plugin_dir . '/vendor/autoload.php';

// The suite's own config, driven by environment variables (see tests/README.md).
define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );

// Set by the wp-phpunit Composer package; WP_TESTS_DIR overrides it for a core checkout.
$tsmlt_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : getenv( 'WP_PHPUNIT__DIR' );

if ( ! $tsmlt_tests_dir || ! file_exists( $tsmlt_tests_dir . '/includes/functions.php' ) ) {
	echo 'Could not find the WordPress test library. Run `composer install` first.' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once $tsmlt_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $tsmlt_plugin_dir ) {
		// Optional: the official MCP Adapter plugin, for the end-to-end MCP tests.
		$mcp_adapter_dir = getenv( 'MCP_ADAPTER_DIR' );
		if ( $mcp_adapter_dir && file_exists( rtrim( $mcp_adapter_dir, '/\\' ) . '/mcp-adapter.php' ) ) {
			require rtrim( $mcp_adapter_dir, '/\\' ) . '/mcp-adapter.php';
		}

		require $tsmlt_plugin_dir . '/media-library-tools.php';
	}
);

require $tsmlt_tests_dir . '/includes/bootstrap.php';
