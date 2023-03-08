<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Media Library Tools
 * Plugin URI:        https://wordpress.org/plugins/
 * Description:       Having proper image metadata is essential for better search engine visibility and accessibility. With our plugin, you can quickly update and optimize all of your image meta data, without having to visit each individual image page.
 Our plugin is user-friendly and saves you time by allowing you to easily find and edit image meta data directly from your media library. Don't let the task of image meta data management overwhelm you - download our plugin today and start optimizing your website's SEO score with just a few clicks.
 * Version:           1.0.1
 * Author:            Tiny Solutions
 * Author URI:        https://profiles.wordpress.org/tinysolution/
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
define( 'TSMLT_VERSION', '1.0.1' );
define( 'TSMLT_FILE', __FILE__ );
define( 'TSMLT_BASENAME', plugin_basename( __FILE__ ) );
define( 'TSMLT_URL', plugins_url('', TSMLT_FILE));
define( 'TSMLT_ABSPATH', dirname(TSMLT_FILE) );

/**
 * App Init.
 */
require_once 'app/Tsmlt.php';
