<?php

namespace TheTinyTools\ME\Controllers\Admin;

use TheTinyTools\ME\Helpers\Fns;
use TheTinyTools\ME\Traits\SingletonTrait;
use WP_Error;
use WP_Query;

class Api {

    use SingletonTrait;

    public $schema ;
    /**
     * Autoload method
     * @return void
     */
    private function __construct() {
        $this->namespace     = 'TheTinyTools/ME/v1';
        $this->resource_name = '/media';
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    // Register our routes.
    public function register_routes() {
        register_rest_route( $this->namespace, $this->resource_name, array(
            'methods' => 'GET',
            'callback' => [ $this, 'get_media'],
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
    }
    /**
     * Grab latest post title by an author!
     *
     * @param array $data Options for the function.
     * @return string|null Post title for the latest,  * or null if none.
     */
    public function bulk_update_media( $request_data ) {

        $parameters = $request_data->get_params();

        if (empty($parameters['current_user'])  ) {
            return new WP_Error('no_author', 'Invalid author', array('status' => 404));
        }

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
     * Grab latest post title by an author!
     *
     * @param array $data Options for the function.
     * @return string|null Post title for the latest,  * or null if none.
     */
    public function update_media( $request_data )
    {
        $parameters = $request_data->get_params();
        $result = [
            'updated' => false,
            'message' => esc_html__('Update failed. Please try to fix', 'ttt-wp-media')
        ] ;
        $submit = [];
        if (empty($parameters['current_user'])  ) {
            return new WP_Error('no_author', 'Invalid author', array('status' => 404));
        }
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
     * Grab latest post title by an author!
     *
     * @param array $data Options for the function.
     * @return string|null Post title for the latest,  * or null if none.
     */
    public function get_media( $request_data ) {
        global $wpdb;
        $parameters = $request_data->get_params();

        if (empty($parameters['current_user'])  ) {
            return new WP_Error('no_author', 'Invalid author', array('status' => 404));
        }

        $limit = (int)get_user_option('upload_per_page', $parameters['current_user']);

        $orderby  = 'menu_order';
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

        $total = Fns::get_post_count('attachment', 'inherit', 'attachment-query' );

        $num_of_pages = ceil( $total / $limit );

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

        $query =  $wpdb->prepare(
            "SELECT p.*, IFNULL(pm.meta_value, '') AS alt_text
            FROM $wpdb->posts AS p
            LEFT JOIN $wpdb->postmeta AS pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_status = 'inherit' AND p.post_type = 'attachment'
            ORDER BY $order_by_sql
            LIMIT %d, %d",
            $offset,
            $limit
        );

        $_posts = wp_cache_get( md5( $query ), 'attachment-query' );
        if ( false === $_posts ) {
            $_posts = $wpdb->get_results( $query );
            wp_cache_set( md5( $query ), $_posts,'attachment-query' );
        }

      // error_log( print_r( $query, true));
      // error_log( print_r( $_posts, true));

        $query_data = [
            'posts' => $_posts,
            'posts_per_page' => absint( $limit ),
            'total_post' => absint( $total ),
            'max_pages' => absint( $num_of_pages ),
        ];
        return wp_json_encode(  $query_data );
    }

    /**
     * Grab latest post title by an author!
     *
     * @param array $data Options for the function.
     * @return string|null Post title for the latest,  * or null if none.
     */
    public function media_submit_bulk_action( $request_data ) {
        global $wpdb;
        $parameters = $request_data->get_params();
        $result = [
            'updated' => false,
            'message' => esc_html__('Update failed. Please try to fix', 'ttt-wp-media')
        ] ;

        $submit = [];
        if (empty($parameters['current_user'])  ) {
            return new WP_Error('no_author', 'Invalid author', array('status' => 404));
        }
        if ( ! empty($parameters['type']) ) {
            $ids = $parameters['ids'];
            switch ( $parameters['type'] ){
                case 'trash':
                    $query =  $wpdb->prepare( "UPDATE $wpdb->posts SET post_status = %s WHERE ID IN (".implode(',', array_fill(0, count($ids), '%d')).")",
                        'trash',
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
                    error_log( print_r( 'delete', true) . "\n\n", 3, __DIR__.'/logg.txt');
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
