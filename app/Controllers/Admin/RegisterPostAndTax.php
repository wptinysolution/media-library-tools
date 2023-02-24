<?php
/**
 * Post Type and Taxonomy
 */
namespace TheTinyTools\WM\Controllers\Admin;

use TheTinyTools\WM\Traits\SingletonTrait;

/**
 * Register Post and Taxonomy class
 */
class RegisterPostAndTax {
    /**
     * Singleton
     */
    use SingletonTrait;

    /**
     * Autoload method
     * @return void
     */
    private function __construct() {
        add_action('init', [ $this , 'register_taxonomies'] );
    }

    public function register_taxonomies() {
        if ( ! is_blog_installed() || ! post_type_exists( 'attachment' ) ) {
            return;
        }
        do_action('tttwp_register_taxonomy');

        $args = array(
            'label'        => esc_html__( 'Category', 'ttt-wp-media' ),
            'public'       => true,
            'rewrite'      => array( 'slug' => 'tttwp-category' ),
            'update_count_callback' => '_update_generic_term_count',
            'hierarchical' => true
        );

        register_taxonomy( tttwm()->category, 'attachment', $args );

        do_action('tttwp_after_register_taxonomy');
    }

}
