<?php
/**
 * Fns Helpers class
 *
 * @package  TheTinyTools\WM
 */

namespace TheTinyTools\WM\Helpers;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Fns class
 */
class Fns {

    /**
     * @param $plugin_file_path
     *
     * @return bool
     */
    public static function is_plugins_installed( $plugin_file_path = null ) {
        $installed_plugins_list = get_plugins();
        return isset( $installed_plugins_list[ $plugin_file_path ] );
    }

    /**
     * @param $post_type
     * @param $post_status
     * @param $group
     * @param $additional_query
     * @return false|mixed|string|null
     */
    public static function get_post_count( $post_type, $post_status = 'publish', $group = 'default', $join = null, $additional_query = null ) {
        global $wpdb;
        $count_key = 'post_count_'.$post_type . '_' . $post_status;
        $count = wp_cache_get( $count_key, $group );
        if ( false === $count ) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(id) FROM $wpdb->posts AS p  
                    $join 
                    WHERE post_type = '%1\$s' AND post_status = '%2\$s'  
                    $additional_query ",
                    $post_type,
                    $post_status
                )
            );
            wp_cache_set( $count_key, $count, $group );
        }
        return $count;
    }

    /**
     * Image attachment details
     *
     * @param init $attachment_id image id.
     * @return array
     */
    public static function wp_get_attachment( $attachment_id ) {
        $attachment = get_post( $attachment_id );
        return array(
            'alt'         => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
            'caption'     => $attachment->post_excerpt,
            'description' => $attachment->post_content,
            'title'       => $attachment->post_title,
        );
    }
}
