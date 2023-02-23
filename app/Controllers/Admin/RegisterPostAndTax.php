<?php
/**
 * Post Type and Taxonomy
 */
namespace TheTinyTools\ME\Controllers\Admin;

use TheTinyTools\ME\Traits\SingletonTrait;

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
        do_action('tttme_register_taxonomy');

        $args = array(
            'label'        => esc_html__( 'Category', 'ttt-wp-media' ),
            'public'       => true,
            'rewrite'      => array( 'slug' => 'tttme-category' ),
            'update_count_callback' => '_update_generic_term_count',
            'hierarchical' => true
        );

        register_taxonomy( tttme()->category, 'attachment', $args );

        do_action('tttme_after_register_taxonomy');
    }


    /**
     * Flush rewrite rules.
     */
    public function flush_rewrite_rules() {
        flush_rewrite_rules();
    }

}
