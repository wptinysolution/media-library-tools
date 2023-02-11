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
    }
    /**
     * Grab latest post title by an author!
     *
     * @param array $data Options for the function.
     * @return string|null Post title for the latest,  * or null if none.
     */
    public function bulk_update_media( $request_data )
    {

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
            'message' => 'Update Failed'
        ] ;
        $submit = [];
        if (empty($parameters['current_user'])  ) {
            return new WP_Error('no_author', 'Invalid author', array('status' => 404));
        }
        if (empty($parameters['ID'])) {
            return $result;
        }

        if ( ! empty( $parameters['post_title'] ) ) {
            $submit['post_title'] = $parameters['post_title'];
            $result['message'] = 'The Title has been saved.';
        }
        if ( isset( $parameters['post_excerpt'] ) ) {
            $submit['post_excerpt'] = $parameters['post_excerpt'];
            $result['message'] = 'The Caption has been saved.';
        }
        if ( isset( $parameters['post_content'] ) ) {
            $submit['post_content'] = $parameters['post_content'];
            $result['message'] = 'The Content has been saved.';
        }
        if ( isset( $parameters['alt_text'] ) ) {
            $result['updated'] =  update_post_meta( $parameters['ID'] , '_wp_attachment_image_alt', $parameters['alt_text'] );
            $result['message'] = 'The Text has been saved.';
        }
        if( ! empty( $submit ) ){
            $submit['ID'] = $parameters['ID'];
            // Update the post into the database
            $result['updated'] = wp_update_post( $submit );
        }

        return $result;
    }
    /**
     * Grab latest post title by an author!
     *
     * @param array $data Options for the function.
     * @return string|null Post title for the latest,  * or null if none.
     */
    public function get_media( $request_data ) {

        $parameters = $request_data->get_params();

        if (empty($parameters['current_user'])  ) {
            return new WP_Error('no_author', 'Invalid author', array('status' => 404));
        }

        unset(
            $parameters['post_type'],
            $parameters['posts_per_page']
        );

        $per_page = (int)get_user_option('upload_per_page', $parameters['current_user']);
        $query_images_args = array_merge(
            array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => $per_page,
                'orderby' => 'menu_order title',
                'order'   => 'DESC',
            ),
            $parameters
        );

        if( 'alt' === $query_images_args['orderby'] ){
            $query_images_args['meta_key'] =  '_wp_attachment_image_alt';
            $query_images_args['orderby'] =  'meta_value';
        }

        if( 'description' === $query_images_args['orderby'] ){
            $query_images_args['orderby'] =  'post_content';
        }
        if( 'caption' === $query_images_args['orderby'] ){
            $query_images_args['orderby'] =  'post_excerpt';
        }

        unset(
            $query_images_args['current_user']
        );



//        global $wpdb;
//        $postsee = $wpdb->get_results ("SELECT * FROM $wpdb->posts WHERE post_status = 'inherit' AND  post_type = 'attachment' ORDER BY {$query_images_args['orderby']} {$query_images_args['order']}");
//
//        error_log( print_r($postsee, true) . "\n\n", 3, __DIR__.'/logg.txt');



        $the_query = new WP_Query($query_images_args);
        $posts = [];
        if ( $the_query->have_posts() ) {
            // Ignore While loop. Becouse some Data are not showing currently.
            foreach ( $the_query->posts as $post) {
                $post_id = $post->ID ;
                $data['ID'] = $post_id ;
                $data['post_title'] = $post->post_title ;
                $data['post_excerpt'] = $post->post_excerpt ;
                $data['alt_text'] = get_post_meta( $post_id , '_wp_attachment_image_alt', true );
                $data['post_content'] = $post->post_content ;
                $data['guid'] = $post->guid;
                $posts[] = $data ;
            }
        }
        wp_reset_postdata();
        $query_data = [
            'posts' => $posts,
            'posts_per_page' => absint( $per_page ),
            'total_post' => absint( $the_query->found_posts ),
            'max_pages' => absint( $the_query->max_num_pages ),
            'current_page' => absint( $the_query->query_vars['paged'] ) + 1,
        ];
        return wp_json_encode(  $query_data );
    }

}
