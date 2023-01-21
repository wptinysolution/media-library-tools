<?php

namespace TheTinyTools\ME\Controllers\Admin\Ajax;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

/**
 * Default Template Switch.
 */
class MediaAjax {

    /**
     * Construct function
     */
    public static function init() {
        //  error_log( print_r( 'sdfasd', true), 3 , __DIR__.'/log-txt' );


    }
    /**
     * Create template
     *
     * @return void
     */
    public static function response() {

        $return = [
            'success'       => true,
            'hello'         => 'hello',
        ];

        error_log( print_r( $return, true), 3 , __DIR__.'/log-txt.log' );

        wp_send_json( $return );

        wp_die();
    }
}
