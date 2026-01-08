<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Media Library Tools - Rename, Clean & CSV Import/Export
 * Plugin URI:        https://www.wptinysolutions.com/tiny-products/media-library-tools/
 * Description:       Bulk Rename media file, Bulk Edit Title, ALT tags, captions, and descriptions of your media files can improve the organization and SEO score.
 * Version:           2.0.2
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

define( 'TSMLT_VERSION', '2.0.2' );

define( 'TSMLT_FILE', __FILE__ );

define( 'TSMLT_BASENAME', plugin_basename( __FILE__ ) );

define( 'TSMLT_URL', plugins_url( '', TSMLT_FILE ) );

define( 'TSMLT_ABSPATH', dirname( TSMLT_FILE ) );

define( 'TSMLT_PATH', plugin_dir_path( __FILE__ ) );

require_once TSMLT_PATH . 'vendor/autoload.php';

use TinySolutions\mlt\Controllers\Installation;

// Register Plugin Active Hook.
register_activation_hook( TSMLT_FILE, [ Installation::class, 'activate' ] );
// Register Plugin Deactivate Hook.
register_deactivation_hook( TSMLT_FILE, [ Installation::class, 'deactivation' ] );

/**
 * App Init.
 */
require_once 'app/Tsmlt.php';
