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
            $tsmlt_media = get_option( 'tsmlt_settings' , [] );
            $limit = absint( get_user_option('upload_per_page', get_current_user_id() ) ?? 20 );
	        $tsmlt_media['media_per_page'] = absint( $tsmlt_media['media_per_page'] ?? $limit ) ;
            if( empty( $tsmlt_media['media_table_column'] ) ){
                $tsmlt_media['media_table_column'] = [
                    'Image',
                    'Title',
                    'Alt',
                    'Caption',
                    'Description',
                ] ;
            }
            update_option( 'tsmlt_settings', $tsmlt_media );
            update_option('tsmlt_plugin_version', TSMLT_VERSION);
            update_option('tsmlt_plugin_activation_time', strtotime( 'now' ) );
        }
    }

    /**
     * @return void
     */
    public static function deactivation() {
		wp_clear_scheduled_hook( 'tsmltpro_upload_directory_scan' );
    }

}