<?php

namespace TheTinyTools\ME\Controllers;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

use TheTinyTools\ME\Traits\SingletonTrait;
use TheTinyTools\ME\Helpers\Fns;

class Installation {

    use SingletonTrait;

    private function __construct() {
    }

    public function activate() {
    }

    public function deactivation() {
    }

    /**
     * @param array $links default plugin action link
     *
     * @return array [array] plugin action link
     */
    public static function plugins_setting_links( $links ) {
        $links['mediaedit_settings'] = '<a href="' . admin_url( 'upload.php?page=submenu-upload' ) . '">' . esc_html__( 'Start Edit Media', 'media-edit' ) . '</a>';
        if( ! Fns::is_plugins_installed('media-edit-pro/media-edit-pro.php') ){
            $links['mediaedit_pro'] = sprintf( '<a href="#" target="_blank" style="color: #39b54a; font-weight: bold;">' . esc_html__( 'Go Pro', 'media-edit' ) . '</a>' );
        }
        return $links;
    }
}