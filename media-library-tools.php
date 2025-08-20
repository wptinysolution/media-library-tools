<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Media Library Tools
 * Plugin URI:        https://www.wptinysolutions.com/tiny-products/media-library-tools/
 * Description:       Bulk Rename media file, Bulk Edit Title, ALT tags, captions, and descriptions of your media files can improve the organization and SEO score.
 * Version:           1.6.12
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

define( 'TSMLT_VERSION', '1.6.12' );

define( 'TSMLT_FILE', __FILE__ );

define( 'TSMLT_BASENAME', plugin_basename( __FILE__ ) );

define( 'TSMLT_URL', plugins_url( '', TSMLT_FILE ) );

define( 'TSMLT_ABSPATH', dirname( TSMLT_FILE ) );

define( 'TSMLT_PATH', plugin_dir_path( __FILE__ ) );

/**
 * App Init.
 */
require_once 'app/Tsmlt.php';
