<?php

namespace TinySolutions\mlt\Controllers;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

class Installation {
    /**
     * @return void
     */
    public static function activate() {
        if ( ! get_option( 'tsmlt_plugin_version' ) ) {
            $get_activation_time = strtotime( 'now' );
            update_option('tsmlt_plugin_version', TSMLT_VERSION);
            update_option('tsmlt_plugin_activation_time', $get_activation_time);
        }
    }

    /**
     * @return void
     */
    public static function deactivation() { }

}