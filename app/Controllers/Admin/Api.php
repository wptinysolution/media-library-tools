<?php

namespace TheTinyTools\ME\Controllers\Admin;
use TheTinyTools\ME\Traits\SingletonTrait;

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
        unset(
            $parameters['post_type'],
            $parameters['posts_per_page']
        );
        // error_log( print_r($parameters, true)  );
        $query_images_args = array_merge(
            array(
                'post_type'      => 'attachment',
                'posts_per_page' => (int) get_user_option( 'upload_per_page', $parameters['current_user'] ),
            ),
            $parameters
        );


         $posts = get_posts( $query_images_args );

         // $posts = new \WP_Query( $query_images_args );
        // error_log( print_r( $posts, true ));

        if ( empty( $posts ) ) {
            return [];
        }
        return $posts;
    }

}
