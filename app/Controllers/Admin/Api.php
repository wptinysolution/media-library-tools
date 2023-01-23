<?php

namespace TheTinyTools\ME\Controllers\Admin;
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
    }

    /**
     * Grab latest post title by an author!
     *
     * @param array $data Options for the function.
     * @return string|null Post title for the latest,  * or null if none.
     */
    function get_media( $request_data ) {

        $parameters = $request_data->get_params();
        if( empty( $parameters['current_user'] ) ){
            return new WP_Error( 'no_author', 'Invalid author', array( 'status' => 404 ) );
        }
        unset(
            $parameters['post_type'],
            $parameters['posts_per_page']
        );

        $query_images_args = array_merge(
            array(
                'post_type'      => 'attachment',
                'posts_per_page' => (int) get_user_option( 'upload_per_page', $parameters['current_user'] ),
            ),
            $parameters
        );

        unset(
            $parameters['current_user']
        );

         $posts = get_posts( $query_images_args );

         $post_data = [];

        if ( empty( $posts ) ) {
            return wp_json_encode( [] );
        }
        /*
        $args = array(
            'post_type'      => 'attachment',
            'post_status' => 'any',
            'posts_per_page' => (int) get_user_option( 'upload_per_page', $parameters['current_user'] ),
        );
        $query = new WP_Query( $args  );
        */
        // error_log( print_r( $query, true ));
        //  error_log( print_r( $query_images_args, true ));
        foreach ( $posts as $p ){
            $post_data[] = [
                'ID' => $p->ID,
                'guid' =>  $p->guid,
                'post_title' => $p->post_title,
                'post_excerpt' => $p->post_excerpt,
                'post_content' => $p->post_content,
                'alt_text' => get_post_meta( $p->ID, '_wp_attachment_image_alt', true)
            ];
        }
        return wp_json_encode(  $post_data );
    }

}
