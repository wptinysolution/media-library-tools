<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Media Edit
 * Plugin URI:
 * Description:       Media File edit
 * Version:           0.0.1
 * Author:            TheTinyTools
 * Author URI:        #
 * Text Domain:       media-edit
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
define( 'TTTME_ACTIVE_FILE_NAME', plugin_basename( __FILE__ ) );

/**
 * App Init.
 */
require_once 'app/MediaEdit.php';
