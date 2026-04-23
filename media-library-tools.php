<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Media Library Tools - AI-Powered Rename, Clean & CSV Import/Export
 * Plugin URI:        https://www.wptinysolutions.com/tiny-products/media-library-tools/
 * Description:       AI-Powered Bulk Rename media file, Bulk Edit Title, ALT tags, captions, and descriptions of your media files can improve the organization and SEO score.
 * Version:           2.2.3
 * Author:            Tiny Solutions
 * Author URI:        https://www.wptinysolutions.com/
 * Text Domain:       media-library-tools
 * Domain Path:       /languages
 * License:           GPLv3
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * @package TinySolutions\mlt
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Define media edit Constant.
 */

define( 'TSMLT_VERSION', '2.2.3' );

define( 'TSMLT_FILE', __FILE__ );

define( 'TSMLT_BASENAME', plugin_basename( __FILE__ ) );

define( 'TSMLT_URL', plugins_url( '', TSMLT_FILE ) );

define( 'TSMLT_ABSPATH', dirname( TSMLT_FILE ) );

define( 'TSMLT_PATH', plugin_dir_path( __FILE__ ) );

require_once TSMLT_PATH . 'autoload.php';

use TinySolutions\mlt\Tsmlt;
use TinySolutions\mlt\Controllers\Installation;

// Register Plugin Active Hook.
register_activation_hook(
	TSMLT_FILE,
	function () {
		Installation::activate();
		set_transient( 'tsmlt_activation_redirect', 1, 30 );
	}
);
// Register Plugin Deactivate Hook.
register_deactivation_hook( TSMLT_FILE, [ Installation::class, 'deactivation' ] );
add_action(
	'admin_init',
	function () {
		// Create missing tables if they don't exist (activation hook doesn't fire on updates).
		Installation::maybe_create_tables();

		if ( ! get_transient( 'tsmlt_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'tsmlt_activation_redirect' );
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore
			return;
		}
		wp_safe_redirect( admin_url( 'upload.php?page=media-library-tools' ) );
		exit;
	}
);

/**
 * App Init.
 */

/**
 * @return Tsmlt
 */
function tsmlt() {
	return Tsmlt::instance();
}
tsmlt();
