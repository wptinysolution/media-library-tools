<?php
/**
 * @wordpress-plugin
 * Plugin Name:       WP Media
 * Plugin URI:        https://wordpress.org/plugins/
 * Description:       WP Media
 * Version:           0.0.2
 * Author:            TheTinyTools
 * Author URI:        https://profiles.wordpress.org/tinysolution/
 * Text Domain:       ttt-wp-media
 * Domain Path:       /languages
 *
 * @package TheTinyTools\ME
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Define media edit Constant.
 */
define( 'TTTME_VERSION', '0.0.1' );
define( 'TTTME_FILE', __FILE__ );
define( 'TTTME_BASENAME', plugin_basename( __FILE__ ) );
define( 'TTTME_URL', plugins_url('', TTTME_FILE));
define( 'TTTME_ABSPATH', dirname(TTTME_FILE) );

/**
 * App Init.
 */
require_once 'app/WpMedia.php';
