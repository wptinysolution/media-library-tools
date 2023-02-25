<?php
/**
 * @wordpress-plugin
 * Plugin Name:       WP Media Tools
 * Plugin URI:        https://wordpress.org/plugins/
 * Description:       Having proper image meta data is essential for better search engine visibility and accessibility. With our plugin, you can quickly update and optimize all of your image meta data, without having to visit each individual image page.
 Our plugin is user-friendly and saves you time by allowing you to easily find and edit image meta data directly from your media library. Don't let the task of image meta data management overwhelm you - download our plugin today and start optimizing your website's SEO score with just a few clicks.
 * Version:           0.0.15
 * Author:            TheTinyTools
 * Author URI:        https://profiles.wordpress.org/tinysolution/
 * Text Domain:       ttt-wp-media
 * Domain Path:       /languages
 *
 * @package TheTinyTools\WM
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Define media edit Constant.
 */
define( 'TTTWM_VERSION', '1.0.0' );
define( 'TTTWM_FILE', __FILE__ );
define( 'TTTWM_BASENAME', plugin_basename( __FILE__ ) );
define( 'TTTWM_URL', plugins_url('', TTTWM_FILE));
define( 'TTTWM_ABSPATH', dirname(TTTWM_FILE) );

/**
 * App Init.
 */
require_once 'app/WpMedia.php';
