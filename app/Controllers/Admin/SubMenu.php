<?php

namespace TheTinyTools\WM\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

use TheTinyTools\WM\Traits\SingletonTrait;

/**
 * Sub menu class
 *
 * @author Mostafa <mostafa.soufi@hotmail.com>
 */
class SubMenu {
    /**
     * Singleton
     */
    use SingletonTrait;

    /**
     * Autoload method
     * @return void
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_sub_menu') );
    }

    /**
     * Register submenu
     * @return void
     */
    public function register_sub_menu() {
        add_submenu_page(
            'upload.php',
            esc_html__('Media Tools', 'ttt-wp-media'),
            esc_html__('Media Tools', 'ttt-wp-media'),
            'manage_options',
            'ttt-wp-media',
            array(&$this, 'wp_media_page_callback')
        );
    }

    /**
     * Render submenu
     * @return void
     */
    public function wp_media_page_callback() {
        echo '<div class="wrap"><div id="media_root"></div></div>';
    }

}
