<?php

namespace TinySolutions\mlt\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;

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
	 * Parent Menu Page Slug
	 */
	const MENU_PAGE_SLUG = 'upload.php';

	/**
	 * Menu capability
	 */
	const MENU_CAPABILITY = 'manage_options';

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
	        self::MENU_PAGE_SLUG,
            esc_html__('Media Tools', 'tsmlt-media-tools'),
            esc_html__('Media Tools', 'tsmlt-media-tools'),
	        self::MENU_CAPABILITY,
            'tsmlt-media-tools',
            array($this, 'wp_media_page_callback')
        );

		do_action( 'tsmlt/add/more/submenu', self::MENU_PAGE_SLUG, self::MENU_CAPABILITY  );

    }

    /**
     * Render submenu
     * @return void
     */
    public function wp_media_page_callback() {
        echo '<div class="wrap"><div id="media_root"></div></div>';
    }

}
