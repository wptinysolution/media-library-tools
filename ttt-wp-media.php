<?php
/**
 * @wordpress-plugin
 * Plugin Name:       WP Media Tools
 * Plugin URI:        https://wordpress.org/plugins/
 * Description:       WP Media
 * Version:           0.0.10
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
define( 'TTTWM_VERSION', '0.0.10' );
define( 'TTTWM_FILE', __FILE__ );
define( 'TTTWM_BASENAME', plugin_basename( __FILE__ ) );
define( 'TTTWM_URL', plugins_url('', TTTWM_FILE));
define( 'TTTWM_ABSPATH', dirname(TTTWM_FILE) );

/**
 * App Init.
 */
require_once 'app/WpMedia.php';
