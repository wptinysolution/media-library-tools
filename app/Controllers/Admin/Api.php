<?php

namespace TheTinyTools\ME\Controllers\Admin;

use TheTinyTools\ME\Helpers\Fns;
use TheTinyTools\ME\Traits\SingletonTrait;
use WP_Error;

class Api {

    /**
     * Singleton
     */
    use SingletonTrait;

    /**
     * Construct
     */
    private function __construct() {
        $this->namespace     = 'TheTinyTools/ME/v1';
        $this->resource_name = '/media';
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register our routes.
     * @return void
     */
    public function register_routes() {
        register_rest_route( $this->namespace, $this->resource_name, array(
            'methods' => 'GET',
            'callback' => [ $this, 'get_media'],
            //'permission_callback' => [ $this, 'login_permission_callback' ] , //'__return_true'
        ) );
        register_rest_route( $this->namespace, $this->resource_name . '/update', array(
            'methods' => 'POST',
            'callback' => [ $this, 'update_media'],
        ) );
        register_rest_route( $this->namespace, $this->resource_name . '/bulk/update', array(
            'methods' => 'POST',
            'callback' => [ $this, 'bulk_update_media'],
        ) );
        register_rest_route( $this->namespace, $this->resource_name . '/bulk/trash', array(
            'methods' => 'POST',
            'callback' => [ $this, 'media_submit_bulk_action'],
        ) );
        register_rest_route( $this->namespace, $this->resource_name . '/filter/getdates', array(
            'methods' => 'GET',
            'callback' => [ $this, 'get_dates'],
        ) );
    }

    /**
     * @return true
     */
    public function login_permission_callback() {
        // get_current_user_id();
         error_log( current_user_can( 'manage_options' ) );
        // error_log( get_current_user_id() );

        return true;
    }
    /**
     * @return false|string
     */
    public function get_dates() {
        global $wpdb;
        $date_query =  $wpdb->prepare( "SELECT DISTINCT DATE_FORMAT( post_date, '%Y-%m') AS YearMonth FROM $wpdb->posts WHERE post_type = %s", 'attachment');
        $get_date = wp_cache_get( md5( $date_query ), 'attachment-query' );
        if ( false === $get_date ) {
            $get_date = $wpdb->get_col( $date_query );
            wp_cache_set( md5( $date_query ), $get_date,'attachment-query' );
        }
        $dates[] = [
            'value' => '',
            'label' => 'All dates',
        ];
        if ( $get_date ) {
            foreach ( $get_date as $date ) {
                $dates[] = [
                    'value' => $date,
                    'label' => date('F Y', strtotime( $date ) ),
                ];
            }
        }
        return wp_json_encode( $dates );
    }

    /**
     * @param $request_data
     * @return bool[]|WP_Error
     */
    public function bulk_update_media( $request_data ) {

        $parameters = $request_data->get_params();

        $ids = $parameters['ids'];
        $type = $parameters['type'];
        $data = $parameters['data'];
        $result = [];
        if( is_array( $ids ) && ! empty( $ids ) && ! empty( $type ) && ! empty( $data ) ){
            $column = '';
            switch ( $type ){
                case 'title':
                    $column = 'post_title';
                    break;
                case 'caption':
                    $column = 'post_excerpt';
                    break;
                case 'description':
                    $column = 'post_content';
                    break;
                case 'alt':
                    $column = 'post_alt';
                    break;
                default:
            }

            foreach (  $ids as $id ) {
                $submit = [];
                if( 'post_alt' !== $column ){
                    $submit['ID'] = $id;
                    $submit[$column] = $data;
                    // Update the post into the database
                    $result['updated'][] = wp_update_post( $submit );
                } else if ( 'post_alt' === $column ) {
                    $result['updated'][] =  update_post_meta( $id , '_wp_attachment_image_alt', $data );
                }
            }
        }

        return [
            'updated' => ! empty(  $result['updated'] ),
        ] ;
    }

    /**
     * @param $request_data
     * @return array|WP_Error
     */
    public function update_media( $request_data )
    {
        $parameters = $request_data->get_params();
        $result = [
            'updated' => false,
            'message' => esc_html__('Update failed. Please try to fix', 'ttt-wp-media')
        ] ;
        $submit = [];

        if (empty($parameters['ID'])) {
            return $result;
        }

        if ( ! empty( $parameters['post_title'] ) ) {
            $submit['post_title'] = trim( $parameters['post_title'] );
            $result['message'] = esc_html__('The Title has been saved.', 'ttt-wp-media');
        }
        if ( isset( $parameters['post_excerpt'] ) ) {
            $submit['post_excerpt'] = trim( $parameters['post_excerpt'] );
            $result['message'] = esc_html__('The Caption has been saved.', 'ttt-wp-media');
        }
        if ( isset( $parameters['post_content'] ) ) {
            $submit['post_content'] = trim( $parameters['post_content'] );
            $result['message'] = esc_html__('Content has been saved.', 'ttt-wp-media');
        }
        if ( isset( $parameters['alt_text'] ) ) {
            $result['updated'] =  update_post_meta( $parameters['ID'] , '_wp_attachment_image_alt', trim( $parameters['alt_text'] ) );
            $result['message'] = esc_html__('The Text has been saved.', 'ttt-wp-media');
        }
        if( ! empty( $submit ) ){
            $submit['ID'] = $parameters['ID'];
            $result['updated'] = wp_update_post( $submit );
        }
        $result['message'] = $result['updated'] ? $result['message'] : esc_html__('Update failed. Please try to fix', 'ttt-wp-media');

        return $result;
    }

    /**
     * @param $request_data
     * @return false|string|WP_Error
     */
    public function get_media( $request_data ) {
        global $wpdb;
        $parameters = $request_data->get_params();

        $limit = (int)get_user_option('upload_per_page', get_current_user_id());
        $limit =  ! $limit ? 20 : $limit;
        // error_log( print_r( $limit , true) . "\n\n", 3, __DIR__.'/logg.txt');

        $orderby  = 'menu_order';
        $status  = 'inherit';
        if( ! empty( $parameters['filtering'] ) && boolval( $parameters['filtering'] ) ){
            $status  = ! empty( $parameters['status'] ) ? $parameters['status'] : $status;
        }

        $order  = ! empty( $parameters['order'] ) ? $parameters['order'] : 'DESC';
        $paged  = ! empty( $parameters['paged'] ) ? $parameters['paged'] : 1;

        if( ! empty( $parameters['orderby'] ) ){
            switch ( $parameters['orderby'] ){
                case 'id':
                    $orderby =  'ID';
                    break;
                case 'title':
                    $orderby =  'post_title';
                    break;
                case 'description':
                    $orderby =  'post_content';
                    break;
                case 'caption':
                    $orderby =  'post_excerpt';
                    break;
                case 'alt':
                    $orderby =  'alt_text';
                    break;
                default:
                    $orderby  = 'menu_order';
            }
        }


        $offset = ( $paged - 1 ) * $limit;

        $order_by_sql       = sanitize_sql_orderby( "$orderby $order" );
        /*
         * You can modify the query to include another value from the postmeta table by adding another
         * LEFT JOIN and selecting the additional meta_value in the SELECT statement. Here's an example
         * of how to join another meta_key with the existing query:
         Example:
             SELECT p.*,
                IFNULL(pm.meta_value, '') AS alt_text,
                IFNULL(pm2.meta_value, '') AS other_meta_value
            FROM wp_posts AS p
            LEFT JOIN wp_postmeta AS pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
            LEFT JOIN wp_postmeta AS pm2 ON pm2.post_id = p.ID AND pm2.meta_key = 'other_meta_key'
            WHERE p.post_status = 'inherit' AND p.post_type = 'attachment'
            ORDER BY alt_text {$order}
            LIMIT %d, %d
        */
        $additional_query  = ! empty( $parameters['date'] ) ? $wpdb->prepare(  "AND DATE_FORMAT(p.post_date, '%1\$s') = '%2\$s'", '%Y-%m', $parameters['date'] ) : null;

        $total = Fns::get_post_count('attachment', $status, 'attachment-query', $additional_query  );

        $query =  $wpdb->prepare(
            "SELECT p.*, IFNULL(pm.meta_value, '') AS alt_text
            FROM $wpdb->posts AS p
            LEFT JOIN $wpdb->postmeta AS pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_status = '%1\$s' AND p.post_type = 'attachment' $additional_query
            ORDER BY $order_by_sql
            LIMIT %2\$d, %3\$d",
            $status,
            $offset,
            $limit
        );

        // error_log( print_r( $query , true) . "\n\n", 3, __DIR__.'/logg.txt');

        $_posts = wp_cache_get( md5( $query ), 'attachment-query' );
        if ( false === $_posts ) {
            $_posts = $wpdb->get_results( $query );
            wp_cache_set( md5( $query ), $_posts,'attachment-query' );
        }

        $query_data = [
            'posts' => $_posts,
            'posts_per_page' => absint( $limit ),
            'total_post' => $total,
            'paged' => absint( $paged ),
        ];
       // error_log( print_r( $query_data , true) . "\n\n", 3, __DIR__.'/logg.txt');

        return wp_json_encode(  $query_data );
    }

    /***
     * @param $request_data
     * @return array|WP_Error
     */
    public function media_submit_bulk_action( $request_data ) {
        global $wpdb;
        $parameters = $request_data->get_params();
        $result = [
            'updated' => false,
            'message' => esc_html__('Update failed. Please try to fix', 'ttt-wp-media')
        ] ;

        if ( ! empty($parameters['type']) ) {
            $ids = $parameters['ids'];
            switch ( $parameters['type'] ){
                case 'trash':
                case 'inherit':
                    $query =  $wpdb->prepare( "UPDATE $wpdb->posts SET post_status = %s WHERE post_type = 'attachment' AND ID IN (".implode(',', array_fill(0, count($ids), '%d')).")",
                        $parameters['type'],
                        ...$ids
                    );
                    $updated = wp_cache_get( md5( $query ), 'attachment-query' );
                    if ( false === $updated ) {
                        $updated = $wpdb->query( $query );
                        wp_cache_set( md5( $query ), $updated,'attachment-query' );
                    }
                    $result['updated'] = (bool) $updated;
                    break;
                case 'delete':
                    $query =  $wpdb->prepare( "DELETE FROM $wpdb->posts WHERE post_type = 'attachment' AND ID IN (".implode(',', array_fill(0, count($ids), '%d')).")",
                        ...$ids
                    );

                    $delete = wp_cache_get( md5( $query ), 'attachment-query' );
                    if ( false === $delete ) {
                        $delete = $wpdb->query( $query );
                        wp_cache_set( md5( $query ), $delete,'attachment-query' );
                    }
                    $result['updated'] = (bool) $delete;
                    break;
                case 'update':
                    error_log( print_r( 'update', true) . "\n\n", 3, __DIR__.'/logg.txt');
                    break;
                default:
                    error_log( print_r( $parameters, true) . "\n\n", 3, __DIR__.'/logg.txt');
            }
        }

        $result['message'] = $result['updated'] ? $result['message'] : esc_html__('Update failed. Please try to fix', 'ttt-wp-media');

        return $result;
    }

}




