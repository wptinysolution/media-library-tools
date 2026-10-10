<?php
/**
 * Regression tests for the `tsmlt_get_media` AJAX endpoint.
 *
 * These pin the media table's existing query semantics and response format, so
 * refactors of Api::get_media() / Api::build_media_query_args() cannot change
 * what the admin UI receives.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Helpers\Fns;

/**
 * GetMediaAjaxTest
 */
class GetMediaAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Keys of every post entry in the response, in order.
	 *
	 * @var string[]
	 */
	const POST_KEYS = [
		'ID',
		'url',
		'title',
		'post_parents',
		'post_parent',
		'menu_order',
		'caption',
		'description',
		'slug',
		'guid',
		'uploaddir',
		'alt_text',
		'categories',
		'metadata',
		'thefile',
		'post_mime_type',
		'custom_meta',
	];

	/**
	 * Set up an administrator for each test.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( 'tsmlt_settings' );
	}

	/**
	 * Create an attachment.
	 *
	 * @param array $args Post arguments, plus optional `alt`.
	 *
	 * @return int
	 */
	private function make_attachment( array $args = [] ): int {
		$alt = $args['alt'] ?? null;
		unset( $args['alt'] );

		$id = self::factory()->attachment->create(
			array_merge(
				[
					'post_mime_type' => 'image/jpeg',
					'post_status'    => 'inherit',
					'file'           => '2026/10/image-' . wp_generate_password( 6, false ) . '.jpg',
				],
				$args
			)
		);
		if ( null !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}
		return $id;
	}

	/**
	 * Call the endpoint and return the decoded `data` payload.
	 *
	 * @param array $params Request params.
	 *
	 * @return array
	 */
	private function get_media( array $params = [] ): array {
		$response = $this->call_endpoint( $params );
		$this->assertTrue( $response['success'] );
		// The endpoint double-encodes: `data` is itself a JSON string.
		$this->assertIsString( $response['data'] );
		return json_decode( $response['data'], true );
	}

	/**
	 * Call the endpoint and return the raw decoded response.
	 *
	 * @param array $params Request params.
	 *
	 * @return array|null
	 */
	private function call_endpoint( array $params ) {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['nonce']            = wp_create_nonce( Fns::NONCE_ID );
		$_POST['params']           = wp_slash( wp_json_encode( $params ) );
		$this->_last_response      = '';
		try {
			$this->_handleAjax( 'tsmlt_get_media' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}
		return json_decode( $this->_last_response, true );
	}

	/**
	 * IDs from a response, in response order.
	 *
	 * @param array $data Decoded data payload.
	 *
	 * @return int[]
	 */
	private function ids( array $data ): array {
		return array_map( 'intval', wp_list_pluck( $data['posts'], 'ID' ) );
	}

	/**
	 * The response envelope and per-post fields are unchanged.
	 */
	public function test_response_format() {
		$id = $this->make_attachment(
			[
				'post_title'   => 'Usage & Unused',
				'post_excerpt' => 'A caption',
				'post_content' => 'A description',
				'alt'          => 'Alt words',
				'file'         => '2026/10/usage.jpg',
			]
		);
		wp_update_attachment_metadata(
			$id,
			[
				'file'   => '2026/10/usage.jpg',
				'width'  => 10,
				'height' => 10,
			]
		);

		$data = $this->get_media();

		$this->assertSame( [ 'posts', 'posts_per_page', 'total_post', 'paged', 'total_page' ], array_keys( $data ) );
		$this->assertSame( 20, $data['posts_per_page'] );
		$this->assertSame( 1, $data['total_post'] );
		$this->assertSame( 1, $data['paged'] );
		$this->assertSame( 1, $data['total_page'] );

		$post = $data['posts'][0];
		$this->assertSame( self::POST_KEYS, array_keys( $post ) );
		$this->assertSame( $id, $post['ID'] );
		$this->assertSame( 'Usage & Unused', $post['title'] );
		$this->assertSame( 'A caption', $post['caption'] );
		$this->assertSame( 'A description', $post['description'] );
		$this->assertSame( 'Alt words', $post['alt_text'] );
		$this->assertSame( 'image/jpeg', $post['post_mime_type'] );
		$this->assertSame( 0, $post['post_parent'] );
		$this->assertSame( '[]', $post['categories'] );
		$this->assertSame( [ 'mainfilepath', 'mainfilename', 'fileextension', 'filebasename', 'originalname', 'file' ], array_keys( $post['thefile'] ) );
		$this->assertSame( 'usage', $post['thefile']['filebasename'] );
		$this->assertSame( 'usage.jpg', $post['thefile']['mainfilename'] );
		$this->assertSame( 10, $post['metadata']['width'] );
	}

	/**
	 * Only `inherit` attachments are listed unless filtering by status.
	 */
	public function test_status_defaults_to_inherit_and_honours_filtering() {
		$listed  = $this->make_attachment();
		$trashed = $this->make_attachment( [ 'post_status' => 'trash' ] );

		$this->assertSame( [ $listed ], $this->ids( $this->get_media() ) );

		// `status` alone is ignored without `filtering`.
		$this->assertSame( [ $listed ], $this->ids( $this->get_media( [ 'status' => 'trash' ] ) ) );

		$data = $this->get_media(
			[
				'filtering' => 1,
				'status'    => 'trash',
			]
		);
		$this->assertSame( [ $trashed ], $this->ids( $data ) );
	}

	/**
	 * Page size, paging and page count.
	 */
	public function test_paging() {
		$this->make_attachment();
		$this->make_attachment();
		$this->make_attachment();

		$first = $this->get_media( [ 'media_per_page' => 2 ] );
		$this->assertCount( 2, $first['posts'] );
		$this->assertSame( 2, $first['posts_per_page'] );
		$this->assertSame( 3, $first['total_post'] );
		$this->assertSame( 2, $first['total_page'] );

		$second = $this->get_media(
			[
				'media_per_page' => 2,
				'paged'          => 2,
			]
		);
		$this->assertCount( 1, $second['posts'] );
		$this->assertSame( 2, $second['paged'] );
	}

	/**
	 * Page size falls back to the saved setting and is capped by the filter.
	 */
	public function test_page_size_setting_and_cap() {
		$this->make_attachment();
		$this->make_attachment();

		update_option( 'tsmlt_settings', [ 'media_per_page' => 1 ] );
		$this->assertSame( 1, $this->get_media()['posts_per_page'] );

		$cap = static function () {
			return 1;
		};
		add_filter( 'tsmlt_maximum_media_per_page', $cap );
		$data = $this->get_media( [ 'media_per_page' => 500 ] );
		remove_filter( 'tsmlt_maximum_media_per_page', $cap );

		$this->assertSame( 1, $data['posts_per_page'] );
		$this->assertCount( 1, $data['posts'] );
	}

	/**
	 * Keyword search.
	 */
	public function test_search_keywords() {
		$match = $this->make_attachment( [ 'post_title' => 'Sunset over the harbour' ] );
		$this->make_attachment( [ 'post_title' => 'Office desk' ] );

		$this->assertSame( [ $match ], $this->ids( $this->get_media( [ 'searchKeyWords' => 'harbour' ] ) ) );
	}

	/**
	 * Ordering by ID and title, both directions.
	 */
	public function test_orderby_id_and_title() {
		$b = $this->make_attachment( [ 'post_title' => 'Bravo' ] );
		$a = $this->make_attachment( [ 'post_title' => 'Alpha' ] );
		$c = $this->make_attachment( [ 'post_title' => 'Charlie' ] );

		$this->assertSame(
			[ $b, $a, $c ],
			$this->ids(
				$this->get_media(
					[
						'orderby' => 'id',
						'order'   => 'ASC',
					]
				)
			)
		);
		$this->assertSame(
			[ $c, $b, $a ],
			$this->ids(
				$this->get_media(
					[
						'orderby' => 'title',
						'order'   => 'DESC',
					]
				)
			)
		);
	}

	/**
	 * Ordering by caption and description goes through the custom clause filter.
	 */
	public function test_orderby_caption() {
		$z = $this->make_attachment( [ 'post_excerpt' => 'Zulu' ] );
		$a = $this->make_attachment( [ 'post_excerpt' => 'Alpha' ] );

		$this->assertSame(
			[ $a, $z ],
			$this->ids(
				$this->get_media(
					[
						'orderby' => 'caption',
						'order'   => 'ASC',
					]
				)
			)
		);
		$this->assertFalse( has_filter( 'posts_clauses', [ Fns::class, 'custom_orderby_post_excerpt_content' ] ) );
	}

	/**
	 * Ordering by alt text includes attachments that have none.
	 */
	public function test_orderby_alt_includes_missing_alt() {
		$z    = $this->make_attachment( [ 'alt' => 'zebra' ] );
		$a    = $this->make_attachment( [ 'alt' => 'apple' ] );
		$none = $this->make_attachment();

		$ids = $this->ids(
			$this->get_media(
				[
					'orderby' => 'alt',
					'order'   => 'ASC',
				]
			)
		);

		$this->assertCount( 3, $ids );
		$this->assertContains( $none, $ids );
		$this->assertLessThan( array_search( $z, $ids, true ), array_search( $a, $ids, true ) );
	}

	/**
	 * Group (taxonomy) filter.
	 */
	public function test_category_filter() {
		$term   = self::factory()->term->create( [ 'taxonomy' => Fns::CATEGORY ] );
		$inside = $this->make_attachment();
		$this->make_attachment();
		wp_set_object_terms( $inside, [ $term ], Fns::CATEGORY );

		$this->assertSame( [ $inside ], $this->ids( $this->get_media( [ 'categories' => [ $term ] ] ) ) );
	}

	/**
	 * Year-month date filter.
	 */
	public function test_date_filter() {
		$march = $this->make_attachment( [ 'post_date' => '2024-03-15 10:00:00' ] );
		$this->make_attachment( [ 'post_date' => '2025-07-01 10:00:00' ] );

		$this->assertSame( [ $march ], $this->ids( $this->get_media( [ 'date' => '2024-03' ] ) ) );
	}

	/**
	 * Used / unused filter on post_parent.
	 */
	public function test_usage_filter() {
		$parent   = self::factory()->post->create();
		$attached = $this->make_attachment( [ 'post_parent' => $parent ] );
		$loose    = $this->make_attachment();

		$this->assertSame( [ $attached ], $this->ids( $this->get_media( [ 'usage_filter' => 'used' ] ) ) );
		$this->assertSame( [ $loose ], $this->ids( $this->get_media( [ 'usage_filter' => 'unused' ] ) ) );
	}

	/**
	 * EXIF camera filter is still applied.
	 */
	public function test_exif_camera_filter() {
		$canon = $this->make_attachment();
		$this->make_attachment();
		update_post_meta( $canon, '_tsmlt_exif_camera', 'Canon EOS R5' );

		$this->assertSame( [ $canon ], $this->ids( $this->get_media( [ 'exif_camera' => 'Canon' ] ) ) );
	}

	/**
	 * Non-administrators are refused.
	 */
	public function test_requires_manage_options() {
		$this->make_attachment();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$response = $this->call_endpoint( [] );

		$this->assertFalse( $response['success'] );
	}
}
