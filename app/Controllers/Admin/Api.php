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
            'permission_callback' => [ $this, 'login_permission_callback' ],
        ) );
        register_rest_route( $this->namespace, $this->resource_name . '/update', array(
            'methods' => 'POST',
            'callback' => [ $this, 'update_single_media'],
            'permission_callback' => [ $this, 'login_permission_callback' ],
        ) );

        register_rest_route( $this->namespace, $this->resource_name . '/bulk/submit', array(
            'methods' => 'POST',
            'callback' => [ $this, 'media_submit_bulk_action'],
            'permission_callback' => [ $this, 'login_permission_callback' ],
        ) );
        register_rest_route( $this->namespace, $this->resource_name . '/filter/getdates', array(
            'methods' => 'GET',
            'callback' => [ $this, 'get_dates'],
            'permission_callback' => [ $this, 'login_permission_callback' ],
        ) );
        register_rest_route( $this->namespace, $this->resource_name . '/getterms', array(
            'methods' => 'GET',
            'callback' => [ $this, 'get_terms'],
            'permission_callback' => [ $this, 'login_permission_callback' ],
        ) );
    }

    /**
     * @return false|string
     */
    public function get_terms() {
        $terms = get_terms( array(
            'taxonomy' => 'tttme_category',
            'hide_empty' => false,
        ) );
        $terms_array = [] ;
        if ( ! is_wp_error( $terms ) && $terms ) {
            foreach ( $terms as $term) {
                $terms_array[] = [
                    'value' => $term->term_id,
                    'label' => $term->name,
                ];
            }
        }
        return wp_json_encode( $terms_array );
    }

    /**
     * @return true
     */
    public function login_permission_callback() {
        return current_user_can( 'manage_options' );
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
     * @return array|WP_Error
     */
    public function update_single_media( $request_data ) {
        $parameters = $request_data->get_params();
        $result = [
            'updated' => false,
            'message' => esc_html__('Update failed. Please try to fix', 'tttme-wp-media')
        ] ;
        $submit = [];

        if (empty($parameters['ID'])) {
            return $result;
        }

        if ( ! empty( $parameters['post_title'] ) ) {
            $submit['post_title'] = trim( $parameters['post_title'] );
            $result['message'] = esc_html__('The Title has been saved.', 'tttme-wp-media');
        }
        if ( isset( $parameters['post_excerpt'] ) ) {
            $submit['post_excerpt'] = trim( $parameters['post_excerpt'] );
            $result['message'] = esc_html__('The Caption has been saved.', 'tttme-wp-media');
        }
        if ( isset( $parameters['post_content'] ) ) {
            $submit['post_content'] = trim( $parameters['post_content'] );
            $result['message'] = esc_html__('Content has been saved.', 'tttme-wp-media');
        }
        if ( isset( $parameters['alt_text'] ) ) {
            $result['updated'] =  update_post_meta( $parameters['ID'] , '_wp_attachment_image_alt', trim( $parameters['alt_text'] ) );
            $result['message'] = esc_html__('The Text has been saved.', 'tttme-wp-media');
        }
        if( ! empty( $submit ) ){
            $submit['ID'] = $parameters['ID'];
            $result['updated'] = wp_update_post( $submit );
        }
        $result['message'] = $result['updated'] ? $result['message'] : esc_html__('Update failed. Please try to fix', 'tttme-wp-media');

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

        $join_query = ! empty( $parameters['categories'] ) ? " JOIN $wpdb->term_relationships AS tr ON p.ID = tr.object_id JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id " : null;

        $additional_query  = ! empty( $parameters['categories'] ) ? $wpdb->prepare(  "AND tt.taxonomy = 'tttme_category' AND tt.term_id = %1\$d",  $parameters['categories'] ) : null;

        $additional_query  .= ! empty( $parameters['date'] ) ? $wpdb->prepare(  "AND DATE_FORMAT(p.post_date, '%1\$s') = '%2\$s'", '%Y-%m', $parameters['date'] ) : null;

        $join_query .= " LEFT JOIN $wpdb->postmeta AS pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'";

        $total = Fns::get_post_count('attachment', $status, 'attachment-query',$join_query, $additional_query  );

        /*
            SELECT p.*, IFNULL(pm.meta_value, '') AS alt_text
            FROM wp_posts AS p
            JOIN wp_term_relationships AS tr ON p.ID = tr.object_id
            JOIN wp_term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            LEFT JOIN wp_postmeta AS pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_status = 'inherit' AND p.post_type = 'attachment' AND tt.taxonomy = 'tttme_category' AND tt.term_id = 5
            ORDER BY menu_order DESC
            LIMIT 0, 4
        */

        $query =  $wpdb->prepare(
            "SELECT p.*, IFNULL(pm.meta_value, '') AS alt_text
            FROM $wpdb->posts AS p            
            $join_query
            WHERE p.post_status = '%1\$s' AND p.post_type = 'attachment' $additional_query
            ORDER BY $order_by_sql
            LIMIT %2\$d, %3\$d",
            $status,
            $offset,
            $limit
        );

        //error_log( print_r( $query , true) . "\n\n", 3, __DIR__.'/logg.txt');

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
            'message' => esc_html__('Update failed. Please try to fix', 'tttme-wp-media')
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
                    $result['message'] = $updated ? esc_html__('Done. Be happy.', 'tttme-wp-media') : esc_html__('Failed. Please try to fix', 'tttme-wp-media');
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
                    $result['message'] = $delete ? esc_html__('Deleted. Be happy.', 'tttme-wp-media') : esc_html__('Deleted failed. Please try to fix', 'tttme-wp-media');
                    break;
                case 'bulkedit':

                    $data = $parameters['data'];
                    $categories = $parameters['post_categories'];
                    $set_data = '';
                    if( ! empty( $data['post_title'] ) ){
                        $set_data .= "post_title= '{$data['post_title']}', " ;
                    }
                    if( ! empty( $data['caption'] ) ){
                        $set_data .= "post_excerpt='{$data['caption']}', ";
                    }
                    if( ! empty( $data['post_description'] ) ){
                        $set_data .= "post_content ='{$data['post_description']}', ";
                    }
                    $set_data = rtrim( $set_data,", ");
                    if( ! empty( $set_data ) ){
                        // UPDATE wp_posts SET post_title= 'The string values for the column-value', post_excerpt='The string values for the column-value', post_content ='The string values for the column-value' WHERE post_type = 'attachment' AND ID IN (72,73,74,75)
                        $query =  $wpdb->prepare( "UPDATE $wpdb->posts SET $set_data WHERE post_type = 'attachment' AND ID IN (".implode(',', array_fill(0, count($ids), '%d')).")",
                            ...$ids
                        );
                        $update = wp_cache_get( md5( $query ), 'attachment-query' );
                        if ( false === $update ) {
                            $update = $wpdb->query( $query );
                            wp_cache_set( md5( $query ), $update,'attachment-query' );
                        }

                    }
                   // error_log( print_r( $categories  , true) . "\n\n", 3, __DIR__.'/logg.txt');
                    $update = false;
                    $alt = ! empty( $data['alt_text'] ) ? $data['alt_text'] : null;
                    foreach ( $ids as $id) {
                        if( $alt ){
                            $update = update_post_meta( $id , '_wp_attachment_image_alt', trim( $alt ) );
                        }
                        if( ! empty( $categories ) ){
                            $update = wp_set_object_terms( $id, $categories, tttme()->category );
                        }
                    }
                    $result['updated'] = (bool) $update;
                    $result['message'] = $update ? esc_html__('Updated. Be happy.', 'tttme-wp-media') : esc_html__('Update failed. Please try to fix', 'tttme-wp-media');

                    break;
                default:
                   // error_log( print_r( 'default', true) . "\n\n", 3, __DIR__.'/logg.txt');
            }
        }

        return $result;
    }

}




