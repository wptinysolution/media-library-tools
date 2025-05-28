<?php
/**
 * Post Type and Taxonomy
 */
namespace TinySolutions\mlt\Controllers\Admin;

use TinySolutions\mlt\Traits\SingletonTrait;

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
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'init', [ $this , 'register_taxonomies' ] );
	}

	/**
	 * Register Taxonomies
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		if ( ! is_blog_installed() || ! post_type_exists( 'attachment' ) ) {
			return;
		}
		do_action( 'tsmlt_register_taxonomy' );

		$args = [
			'label'                 => esc_html__( 'Category', 'media-library-tools' ),
			'public'                => true,
			'rewrite'               => [ 'slug' => 'tsmlt-category' ],
			'update_count_callback' => '_update_generic_term_count',
			'hierarchical'          => true,
		];

		register_taxonomy( tsmlt()->category, 'attachment', $args );

		do_action( 'tsmlt_after_register_taxonomy' );
	}
}
