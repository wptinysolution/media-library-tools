<?php
/**
 * Tests for Api::build_media_query_args().
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Controllers\Admin\Api;
use TinySolutions\mlt\Helpers\Fns;

/**
 * BuildMediaQueryArgsTest
 */
class BuildMediaQueryArgsTest extends WP_UnitTestCase {

	/**
	 * Reset the plugin settings for each test.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( 'tsmlt_settings' );
	}

	/**
	 * Defaults when no parameters are given.
	 */
	public function test_defaults() {
		$this->assertSame(
			[
				'post_type'      => 'attachment',
				'posts_per_page' => 20,
				'post_status'    => 'inherit',
				'orderby'        => 'menu_order',
				'order'          => 'DESC',
				'paged'          => 1,
			],
			Api::instance()->build_media_query_args( [] )
		);
	}

	/**
	 * Every supported parameter maps to the expected query argument.
	 */
	public function test_all_parameters() {
		$args = Api::instance()->build_media_query_args(
			[
				'media_per_page' => 5,
				'paged'          => '3',
				'order'          => 'ASC',
				'orderby'        => 'title',
				'searchKeyWords' => '<b>sunset</b>',
				'categories'     => [ 7 ],
				'date'           => '2024-03',
				'usage_filter'   => 'unused',
				'exif_camera'    => 'Canon',
			]
		);

		$this->assertSame( 5, $args['posts_per_page'] );
		$this->assertSame( 3, $args['paged'] );
		$this->assertSame( 'ASC', $args['order'] );
		$this->assertSame( 'post_title', $args['orderby'] );
		$this->assertSame( 'sunset', $args['s'] );
		$this->assertSame( Fns::CATEGORY, $args['tax_query'][0]['taxonomy'] );
		$this->assertSame( [ 7 ], $args['tax_query'][0]['terms'] );
		$this->assertSame(
			[
				[
					'year'  => 2024,
					'month' => 3,
				],
			],
			$args['date_query']
		);
		$this->assertSame( 0, $args['post_parent'] );
		$this->assertSame( '_tsmlt_exif_camera', $args['meta_query'][0]['key'] );
	}

	/**
	 * Status is only taken from the parameters when `filtering` is set.
	 */
	public function test_status_requires_filtering() {
		$api = Api::instance();

		$this->assertSame( 'inherit', $api->build_media_query_args( [ 'status' => 'trash' ] )['post_status'] );
		$this->assertSame(
			'trash',
			$api->build_media_query_args(
				[
					'filtering' => true,
					'status'    => 'trash',
				]
			)['post_status']
		);
	}

	/**
	 * Alt ordering switches to a meta_value sort.
	 */
	public function test_alt_ordering() {
		$args = Api::instance()->build_media_query_args( [ 'orderby' => 'alt' ] );

		$this->assertSame( 'meta_value', $args['orderby'] );
		$this->assertSame( 'OR', $args['meta_query']['relation'] );
	}

	/**
	 * Page size falls back to the setting, then is capped.
	 */
	public function test_page_size_setting_and_cap() {
		update_option( 'tsmlt_settings', [ 'media_per_page' => 7 ] );
		$this->assertSame( 7, Api::instance()->build_media_query_args( [] )['posts_per_page'] );
		$this->assertSame( 1000, Api::instance()->build_media_query_args( [ 'media_per_page' => 5000 ] )['posts_per_page'] );
	}
}
