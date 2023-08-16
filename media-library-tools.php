<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Media Library Tools
 * Plugin URI:        https://wordpress.org/plugins/media-library-tools
 * Description:       Proper Naming of media file, Bulk Edit Title, ALT tags, captions, and descriptions of your media files can improve the organization and SEO score.
 * Version:           1.1.2.1
 * Author:            Tiny Solutions
 * Author URI:        https://profiles.wordpress.org/tinysolution
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

define( 'TSMLT_VERSION', '1.1.2' );

define( 'TSMLT_FILE', __FILE__ );

define( 'TSMLT_BASENAME', plugin_basename( __FILE__ ) );

define( 'TSMLT_URL', plugins_url('', TSMLT_FILE));

define( 'TSMLT_ABSPATH', dirname(TSMLT_FILE) );

define( 'TSMLT_PATH', plugin_dir_path( __FILE__ ) );

// 11695/7

/**
 * App Init.
 */
require_once 'app/Tsmlt.php';
