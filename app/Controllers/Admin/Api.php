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
        $result = [
            'updated' => false
        ] ;
        return $result;
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
//        || ! Fns::verify_nonce()
        // error_log( print_r( $_REQUEST[ rtsb()->nonceId ] , true ) . "\n\n" , 3, __DIR__ . '/log.txt' );
        if (empty($parameters['current_user'])  ) {
            return new WP_Error('no_author', 'Invalid author', array('status' => 404));
        }


        if (empty($parameters['current_user'])) {
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
            ),
            $parameters
        );

        unset(
            $parameters['current_user']
        );

        $posts = new WP_Query($query_images_args);
        // error_log( print_r(  $posts , true), 3, __DIR__.'/data.log');
        $post_data = [];
        if ( $posts->have_posts() ) :
            while ( $posts->have_posts() ) :  $posts->the_post();
                $post_data[] = [
                    'key' => get_the_ID(),
                    'ID' => get_the_ID(),
                    'guid' =>  wp_get_attachment_url(get_the_ID(),'thumbnail'),
                    'post_title' => get_the_title(),
                    'post_excerpt' => get_the_excerpt(),
                    'post_content' => get_the_content(),
                    'alt_text' => get_post_meta( get_the_ID(), '_wp_attachment_image_alt', true)
                ];
            endwhile;
        endif;
        // error_log( print_r($posts->query_vars['paged'], true) );
        wp_reset_postdata();
        $query_data = [
            'posts' => $post_data,
            'posts_per_page' => absint( $per_page ),
            'total_post' => absint( $posts->found_posts ),
            'max_pages' => absint( $posts->max_num_pages ),
            'current_page' => absint( $posts->query_vars['paged'] ) + 1,
        ];

        return wp_json_encode(  $query_data );
    }

}
