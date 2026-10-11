<?php
/**
 * WordPress test suite configuration, read from environment variables.
 *
 * WARNING: the test suite DROPS AND RECREATES every table in WP_TESTS_DB_NAME.
 * Point it at a dedicated, empty database — never at a site's own database.
 *
 * Required environment variables:
 *   WP_CORE_DIR          Absolute path to a WordPress core checkout (contains wp-settings.php).
 *   WP_TESTS_DB_NAME     Dedicated test database name.
 *
 * Optional environment variables:
 *   WP_TESTS_DB_USER     Default "root".
 *   WP_TESTS_DB_PASSWORD Default "".
 *   WP_TESTS_DB_HOST     Default "localhost". Accepts "localhost:/path/to/mysqld.sock".
 *
 * @package TinySolutions\mlt
 */

$tsmlt_tests_core_dir = getenv( 'WP_CORE_DIR' );
$tsmlt_tests_db_name  = getenv( 'WP_TESTS_DB_NAME' );

if ( ! $tsmlt_tests_core_dir || ! file_exists( rtrim( $tsmlt_tests_core_dir, '/\\' ) . '/wp-settings.php' ) ) {
	echo 'WP_CORE_DIR must point to a WordPress core directory. See tests/README.md.' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

if ( ! $tsmlt_tests_db_name ) {
	echo 'WP_TESTS_DB_NAME must name a dedicated, disposable test database. See tests/README.md.' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

define( 'ABSPATH', rtrim( $tsmlt_tests_core_dir, '/\\' ) . '/' );

define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );

define( 'DB_NAME', $tsmlt_tests_db_name );
define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) ? getenv( 'WP_TESTS_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', false !== getenv( 'WP_TESTS_DB_PASSWORD' ) ? getenv( 'WP_TESTS_DB_PASSWORD' ) : '' );
define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) ? getenv( 'WP_TESTS_DB_HOST' ) : 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

// Test-only salts; this configuration is never used by a real site.
define( 'AUTH_KEY', 'tsmlt-tests' );
define( 'SECURE_AUTH_KEY', 'tsmlt-tests' );
define( 'LOGGED_IN_KEY', 'tsmlt-tests' );
define( 'NONCE_KEY', 'tsmlt-tests' );
define( 'AUTH_SALT', 'tsmlt-tests' );
define( 'SECURE_AUTH_SALT', 'tsmlt-tests' );
define( 'LOGGED_IN_SALT', 'tsmlt-tests' );
define( 'NONCE_SALT', 'tsmlt-tests' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required by the WordPress test suite.

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );
