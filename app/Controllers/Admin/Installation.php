<?php

namespace TheTinyTools\ME\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

use TheTinyTools\ME\Traits\SingletonTrait;

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
        $links['mediaedit_settings'] = '<a href="' . admin_url( 'admin.php?page=media-edit' ) . '">' . esc_html__( 'Settings', 'media-edit' ) . '</a>';
        if( ! function_exists('tttmepro') ){
            $links['mediaedit_pro'] = sprintf( '<a href="#" target="_blank" style="color: #39b54a; font-weight: bold;">' . esc_html__( 'Go Pro', 'media-edit' ) . '</a>' );
        }
        return $links;
    }
}