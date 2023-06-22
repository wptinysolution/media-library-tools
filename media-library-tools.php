<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Media Library Tools
 * Plugin URI:        https://wordpress.org/support/plugin/media-library-tools
 * Description:       Proper Naming of media file, Bulk Edit Title, ALT tags, captions, and descriptions of your media files can improve the organization and SEO score.
 * Version:           1.0.10.1
 * Author:            Tiny Solutions
 * Author URI:        https://wptinysolutions.com/
 * Text Domain:       tsmlt-media-tools
 * Domain Path:       /languages
 *
 * @package TinySolutions\WM
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Define media edit Constant.
 */

define( 'TSMLT_VERSION', '1.0.10' );

define( 'TSMLT_FILE', __FILE__ );

define( 'TSMLT_BASENAME', plugin_basename( __FILE__ ) );

define( 'TSMLT_URL', plugins_url('', TSMLT_FILE));

define( 'TSMLT_ABSPATH', dirname(TSMLT_FILE) );

/**
 * App Init.
 */
require_once 'app/Tsmlt.php';
