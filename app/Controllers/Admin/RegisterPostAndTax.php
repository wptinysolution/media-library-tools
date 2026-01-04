<?php
/**
 * Post Type and Taxonomy
 */
namespace TinySolutions\mlt\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
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
			'labels'                => [
				'name'              => esc_html__( 'Groups', 'media-library-tools' ),
				'singular_name'     => esc_html__( 'Group', 'media-library-tools' ),
				'search_items'      => esc_html__( 'Search Groups', 'media-library-tools' ),
				'all_items'         => esc_html__( 'All Groups', 'media-library-tools' ),
				'parent_item'       => esc_html__( 'Parent Group', 'media-library-tools' ),
				'parent_item_colon' => esc_html__( 'Parent Group:', 'media-library-tools' ),
				'edit_item'         => esc_html__( 'Edit Group', 'media-library-tools' ),
				'update_item'       => esc_html__( 'Update Group', 'media-library-tools' ),
				'add_new_item'      => esc_html__( 'Add New Group', 'media-library-tools' ),
				'new_item_name'     => esc_html__( 'New Group Name', 'media-library-tools' ),
				'menu_name'         => esc_html__( 'Groups', 'media-library-tools' ),
			],
			'public'                => true,
			'rewrite'               => [ 'slug' => 'tsmlt-category' ],
			'update_count_callback' => '_update_generic_term_count',
			'hierarchical'          => true,
		];

		register_taxonomy( tsmlt()->category, 'attachment', $args );

		do_action( 'tsmlt_after_register_taxonomy' );
	}
}
