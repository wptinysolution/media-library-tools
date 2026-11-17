<?php
/**
 * Search and select for the Free CSV export: `tsmlt/search-media` finds
 * media by name, file name, keyword, media IDs, or a post, page or
 * WooCommerce product (by ID or title), each result with its file name;
 * the chosen IDs then go to `tsmlt/export-media-csv`, which exports exactly
 * those, with all ten standard columns.
 *
 * WooCommerce is not loaded: a stand-in `product` post type is registered
 * for the product cases. Every post and attachment is a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\Csv\MediaCsv;

/**
 * SearchMediaSelectionTest
 */
class SearchMediaSelectionTest extends WP_UnitTestCase {

	/**
	 * IDs for which read_post is denied (map_meta_cap filter).
	 *
	 * @var int[]
	 */
	private $denied = [];

	/**
	 * Whether this test registered the stand-in product type.
	 *
	 * @var bool
	 */
	private $registered_product = false;

	/**
	 * Administrator; a stand-in product type; the read filter.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/search-media' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		if ( ! post_type_exists( 'product' ) ) {
			register_post_type( 'product', [ 'public' => true ] );
			$this->registered_product = true;
		}
		$this->denied = [];
		add_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10, 4 );
	}

	/**
	 * Remove the read filter and the stand-in product type.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10 );
		if ( $this->registered_product ) {
			unregister_post_type( 'product' );
		}
		parent::tear_down();
	}

	/**
	 * Deny read_post for the IDs in $denied.
	 *
	 * @param string[] $caps    Primitive caps.
	 * @param string   $cap     Meta cap.
	 * @param int      $user_id User ID.
	 * @param array    $args    Arguments.
	 *
	 * @return string[]
	 */
	public function deny_read( $caps, $cap, $user_id, $args ) {
		if ( 'read_post' === $cap && in_array( (int) ( $args[0] ?? 0 ), $this->denied, true ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
	}

	/**
	 * An image attachment.
	 *
	 * @param string $file   File, relative to uploads.
	 * @param array  $args   Post arguments.
	 *
	 * @return int
	 */
	private function image( string $file, array $args = [] ): int {
		return (int) self::factory()->attachment->create(
			array_merge(
				[
					'file'           => $file,
					'post_mime_type' => 'image/jpeg',
				],
				$args
			)
		);
	}

	/**
	 * Run the search.
	 *
	 * @param array $input Input.
	 *
	 * @return array|WP_Error
	 */
	private static function search( array $input ) {
		return wp_get_ability( 'tsmlt/search-media' )->execute( $input );
	}

	/**
	 * Result IDs of a search.
	 *
	 * @param array $input Input.
	 *
	 * @return int[]
	 */
	private function ids_of( array $input ): array {
		$result = self::search( $input );
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		return wp_list_pluck( $result['items'], 'id' );
	}

	/**
	 * Images by name, keyword and file name; results carry the file name
	 * (no folders) to tell them apart; nothing found is an empty page.
	 */
	public function test_search_by_name_keyword_and_file_name() {
		$logo   = $this->image( '2026/01/company-logo.png', [ 'post_title' => 'Company logo' ] );
		$banner = $this->image(
			'2026/02/home-banner.jpg',
			[
				'post_title'   => 'Home',
				'post_content' => 'Spring banner for the home page',
			]
		);
		$this->image( '2026/03/team.jpg', [ 'post_title' => 'Team' ] );

		$this->assertSame( [ $logo ], $this->ids_of( [ 'search' => 'logo' ] ) );
		$this->assertSame( [ $banner ], $this->ids_of( [ 'search' => 'banner' ] ), 'Keyword in the description.' );
		$this->assertSame( [ $logo ], $this->ids_of( [ 'filename' => 'company-logo.png' ] ) );

		$item = self::search( [ 'filename' => 'company-logo' ] )['items'][0];
		$this->assertSame( 'company-logo.png', $item['filename'] );
		$this->assertArrayNotHasKey( 'associations', $item );

		$none = self::search( [ 'search' => 'no-such-image-anywhere' ] );
		$this->assertSame( [], $none['items'] );
		$this->assertSame( 0, $none['total'] );
	}

	/**
	 * Images with the same file name are both returned, with IDs and dates
	 * that tell them apart; no path is shown.
	 */
	public function test_duplicate_file_names_are_told_apart() {
		$first  = $this->image(
			'2025/01/logo.png',
			[
				'post_title' => 'Logo',
				'post_date'  => '2025-01-10 10:00:00',
			]
		);
		$second = $this->image(
			'2026/05/logo.png',
			[
				'post_title' => 'Logo',
				'post_date'  => '2026-05-10 10:00:00',
			]
		);

		$result = self::search( [ 'filename' => 'logo.png' ] );
		$this->assertEqualsCanonicalizing( [ $first, $second ], wp_list_pluck( $result['items'], 'id' ) );
		$this->assertSame( [ 'logo.png', 'logo.png' ], wp_list_pluck( $result['items'], 'filename' ) );
		$this->assertNotSame( $result['items'][0]['date'], $result['items'][1]['date'] );
		$json = wp_json_encode( $result );
		$this->assertStringNotContainsString( wp_upload_dir()['basedir'], $json );
		$this->assertStringNotContainsString( ABSPATH, $json );
		foreach ( $result['items'] as $item ) {
			$this->assertStringNotContainsString( '/', $item['filename'], 'File names carry no folders.' );
		}
	}

	/**
	 * One or several media IDs, in the order given; IDs that are missing,
	 * not media, trashed or not readable are listed in missing_ids, alike.
	 */
	public function test_search_by_media_ids() {
		$a       = $this->image( 'ids/a.jpg' );
		$b       = $this->image( 'ids/b.jpg' );
		$c       = $this->image( 'ids/c.jpg' );
		$trashed = $this->image( 'ids/trashed.jpg' );
		wp_trash_post( $trashed );
		$hidden       = $this->image( 'ids/hidden.jpg' );
		$this->denied = [ $hidden ];
		$post         = self::factory()->post->create();

		$this->assertSame( [ $b ], $this->ids_of( [ 'ids' => [ $b ] ] ) );
		$result = self::search( [ 'ids' => [ $c, $a, $b ] ] );
		$this->assertSame( [ $c, $a, $b ], wp_list_pluck( $result['items'], 'id' ), 'Order given.' );
		$this->assertSame( [], $result['missing_ids'] );

		$result = self::search( [ 'ids' => [ $a, 999999, $post, $trashed, $hidden ] ] );
		$this->assertSame( [ $a ], wp_list_pluck( $result['items'], 'id' ) );
		$this->assertSame( [ 999999, $post, $trashed, $hidden ], $result['missing_ids'] );
		$this->assertSame( 1, $result['total'] );
	}

	/**
	 * The IDs input is bounded and strict.
	 */
	public function test_ids_input_is_validated() {
		$a = $this->image( 'ids/valid.jpg' );
		foreach (
			[
				'empty'      => [],
				'duplicates' => [ $a, $a ],
				'zero'       => [ 0 ],
				'string'     => [ (string) $a ],
				'too many'   => range( 1, 101 ),
			] as $label => $ids
		) {
			$this->assertWPError( self::search( [ 'ids' => $ids ] ), $label );
		}
		$this->assertWPError(
			self::search(
				[
					'content_id'    => 1,
					'content_title' => 'About',
				]
			),
			'Not both content filters.'
		);
		$this->assertCount( 1, $this->ids_of( [ 'ids' => [ $a ] ] ) );
	}

	/**
	 * A post's media: attached, featured, inserted with an image block, a
	 * wp-image class or a [gallery] shortcode; each with how it is
	 * associated; unrelated, trashed and unreadable media left out; the
	 * content is named in the output.
	 */
	public function test_media_of_a_post() {
		$attached  = $this->image( 'post/attached.jpg' );
		$featured  = $this->image( 'post/featured.jpg' );
		$block     = $this->image( 'post/block.jpg' );
		$classed   = $this->image( 'post/classed.jpg' );
		$shortcode = $this->image( 'post/shortcode.jpg' );
		$trashed   = $this->image( 'post/trashed.jpg' );
		$hidden    = $this->image( 'post/hidden.jpg' );
		$this->image( 'post/unrelated.jpg' );
		$post = self::factory()->post->create(
			[
				'post_title'   => 'Spring news',
				'post_content' => '<!-- wp:image {"id":' . $block . '} --><figure class="wp-block-image"><img src="x.jpg" class="wp-image-' . $block . '"/></figure><!-- /wp:image -->'
					. '<p><img class="alignnone wp-image-' . $classed . ' size-full" src="y.jpg"></p>'
					. '[gallery ids="' . $shortcode . ',' . $trashed . ',' . $hidden . '"]',
			]
		);
		wp_update_post(
			[
				'ID'          => $attached,
				'post_parent' => $post,
			]
		);
		set_post_thumbnail( $post, $featured );
		wp_trash_post( $trashed );
		$this->denied = [ $hidden ];

		$result = self::search( [ 'content_id' => $post ] );
		$this->assertEqualsCanonicalizing( [ $attached, $featured, $block, $classed, $shortcode ], wp_list_pluck( $result['items'], 'id' ) );
		$how = wp_list_pluck( $result['items'], 'associations', 'id' );
		$this->assertSame( [ 'attached' ], $how[ $attached ] );
		$this->assertSame( [ 'featured' ], $how[ $featured ] );
		$this->assertSame( [ 'content' ], $how[ $block ] );
		$this->assertSame( [ 'content' ], $how[ $classed ] );
		$this->assertSame( [ 'content' ], $how[ $shortcode ] );
		$this->assertSame( $post, $result['content']['id'] );
		$this->assertSame( 'Spring news', $result['content']['title'] );
		$this->assertSame( 'post', $result['content']['type'] );

		$this->assertSame(
			[ $block ],
			$this->ids_of(
				[
					'content_id' => $post,
					'filename'   => 'block',
				]
			),
			'Combined with other filters.'
		);
		$this->assertSame(
			[ $featured ],
			$this->ids_of(
				[
					'content_id' => $post,
					'ids'        => [ $featured, $this->image( 'post/other.jpg' ) ],
				]
			),
			'Combined with ids.'
		);
	}

	/**
	 * A page's media from gallery, cover and media-and-text blocks, found by
	 * title; several matching titles list the candidates and return no media.
	 */
	public function test_media_of_a_page_by_title() {
		$gallery_a = $this->image( 'page/gallery-a.jpg' );
		$gallery_b = $this->image( 'page/gallery-b.jpg' );
		$cover     = $this->image( 'page/cover.jpg' );
		$media     = $this->image( 'page/media.jpg' );
		$page      = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'About Us',
				'post_content' => '<!-- wp:gallery {"ids":[' . $gallery_a . '],"linkTo":"none"} --><figure class="wp-block-gallery"><!-- wp:image {"id":' . $gallery_b . '} --><figure class="wp-block-image"><img src="b.jpg"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->'
					. '<!-- wp:cover {"id":' . $cover . '} --><div class="wp-block-cover"></div><!-- /wp:cover -->'
					. '<!-- wp:media-text {"mediaId":' . $media . '} --><div class="wp-block-media-text"></div><!-- /wp:media-text -->',
			]
		);

		$result = self::search( [ 'content_title' => 'about us' ] );
		$this->assertSame( $page, $result['content']['id'] );
		$this->assertSame( 'page', $result['content']['type'] );
		$this->assertEqualsCanonicalizing( [ $gallery_a, $gallery_b, $cover, $media ], wp_list_pluck( $result['items'], 'id' ) );

		self::factory()->post->create(
			[
				'post_title' => 'About Us',
				'post_date'  => '2020-01-01 00:00:00',
			]
		);
		$ambiguous = self::search( [ 'content_title' => 'About Us' ] );
		$this->assertSame( [], $ambiguous['items'] );
		$this->assertNull( $ambiguous['content'] );
		$this->assertCount( 2, $ambiguous['content_candidates'] );
		$this->assertEqualsCanonicalizing( [ 'page', 'post' ], wp_list_pluck( $ambiguous['content_candidates'], 'type' ) );

		$this->assertSame( 'tsmlt_content_not_found', self::search( [ 'content_title' => 'No such page title' ] )->get_error_code() );
	}

	/**
	 * A product's media: its featured image and its product gallery.
	 */
	public function test_media_of_a_product() {
		$featured = $this->image( 'product/main.jpg' );
		$one      = $this->image( 'product/one.jpg' );
		$two      = $this->image( 'product/two.jpg' );
		$product  = self::factory()->post->create(
			[
				'post_type'  => 'product',
				'post_title' => 'Blue Mug',
			]
		);
		set_post_thumbnail( $product, $featured );
		update_post_meta( $product, '_product_image_gallery', $one . ',' . $two );

		$result = self::search( [ 'content_title' => 'Blue Mug' ] );
		$this->assertSame( 'product', $result['content']['type'] );
		$how = wp_list_pluck( $result['items'], 'associations', 'id' );
		$this->assertSame( [ 'featured' ], $how[ $featured ] );
		$this->assertSame( [ 'gallery' ], $how[ $one ] );
		$this->assertSame( [ 'gallery' ], $how[ $two ] );
		$this->assertCount( 3, $how );
	}

	/**
	 * Content that is missing, of another type, trashed or not readable is
	 * not found, alike; its media are not returned.
	 */
	public function test_inaccessible_content_is_not_found() {
		$image   = $this->image( 'hidden-content/image.jpg' );
		$private = self::factory()->post->create( [ 'post_title' => 'Hidden post' ] );
		wp_update_post(
			[
				'ID'          => $image,
				'post_parent' => $private,
			]
		);
		$trashed = self::factory()->post->create( [ 'post_title' => 'Old post' ] );
		wp_trash_post( $trashed );
		$this->denied = [ $private ];

		foreach (
			[
				'unreadable' => [ 'content_id' => $private ],
				'by title'   => [ 'content_title' => 'Hidden post' ],
				'trashed'    => [ 'content_id' => $trashed ],
				'attachment' => [ 'content_id' => $image ],
				'missing'    => [ 'content_id' => 999999 ],
			] as $label => $input
		) {
			$result = self::search( $input );
			$this->assertWPError( $result, $label );
			$this->assertSame( 'tsmlt_content_not_found', $result->get_error_code(), $label );
		}
	}

	/**
	 * A page, post or product URL of this site, plain or pretty, resolves to
	 * its media; the home page resolves to the static front page.
	 */
	public function test_media_of_a_url() {
		$image      = $this->image( 'url/about.jpg' );
		$page       = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'About Us',
				'post_name'    => 'about-us',
				'post_content' => '<p><img class="wp-image-' . $image . '" src="about.jpg"></p>',
			]
		);
		$post_image = $this->image( 'url/post.jpg' );
		$post       = self::factory()->post->create(
			[
				'post_title' => 'Launch',
				'post_name'  => 'launch',
			]
		);
		set_post_thumbnail( $post, $post_image );
		$product_image = $this->image( 'url/product.jpg' );
		$product       = self::factory()->post->create(
			[
				'post_type'  => 'product',
				'post_title' => 'Mug',
				'post_name'  => 'mug',
			]
		);
		update_post_meta( $product, '_product_image_gallery', (string) $product_image );

		// Plain permalinks.
		$this->assertSame( [ $image ], $this->ids_of( [ 'content_url' => get_permalink( $page ) ] ) );
		$this->assertSame( [ $post_image ], $this->ids_of( [ 'content_url' => get_permalink( $post ) ] ) );

		// Pretty permalinks, with the product type rewritten too.
		$this->set_permalink_structure( '/%postname%/' );
		register_post_type(
			'product',
			[
				'public'  => true,
				'rewrite' => [ 'slug' => 'product' ],
			]
		);
		flush_rewrite_rules();
		$result = self::search( [ 'content_url' => home_url( '/about-us/' ) ] );
		$this->assertSame( $page, $result['content']['id'] );
		$this->assertSame( [ $image ], wp_list_pluck( $result['items'], 'id' ) );
		$this->assertSame( [ $post_image ], $this->ids_of( [ 'content_url' => home_url( '/launch/' ) ] ) );
		$this->assertSame( [ $product_image ], $this->ids_of( [ 'content_url' => home_url( '/product/mug/' ) ] ) );
		$this->assertSame( [ $image ], $this->ids_of( [ 'content_url' => str_replace( 'http://', 'https://', home_url( '/about-us/?utm_source=x#team' ) ) ] ), 'Other scheme, query and fragment.' );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page );
		$this->assertSame( $page, self::search( [ 'content_url' => home_url( '/' ) ] )['content']['id'], 'Home page: the static front page.' );
		$this->set_permalink_structure( '' );
	}

	/**
	 * URLs of other sites, other schemes, ports or paths, with credentials,
	 * that resolve to nothing, to an attachment, to trashed or unreadable
	 * content are all not found, alike; a URL is never fetched.
	 */
	public function test_unresolvable_urls_are_not_found() {
		$page    = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$trashed = self::factory()->post->create();
		wp_trash_post( $trashed );
		$hidden       = self::factory()->post->create();
		$this->denied = [ $hidden ];
		$image        = $this->image( 'url/attachment.jpg' );
		$fetched      = 0;
		$block        = static function () use ( &$fetched ) {
			++$fetched;
			return new WP_Error( 'blocked', 'No HTTP in this test.' );
		};
		add_filter( 'pre_http_request', $block );

		$local = get_permalink( $page );
		$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		foreach (
			[
				'external domain'  => str_replace( $host, 'example.com', $local ),
				'lookalike domain' => str_replace( $host, $host . '.attacker.test', $local ),
				'other port'       => str_replace( $host, $host . ':8080', $local ),
				'ftp scheme'       => str_replace( 'http://', 'ftp://', $local ),
				'credentials'      => str_replace( 'http://', 'http://user:pass@', $local ),
				'no scheme'        => '//' . $host . '/?page_id=' . $page,
				'relative'         => '/?page_id=' . $page,
				'not found'        => home_url( '/?p=999999' ),
				'trashed'          => get_permalink( $trashed ) ? get_permalink( $trashed ) : home_url( '/?p=' . $trashed ),
				'unreadable'       => get_permalink( $hidden ),
				'attachment'       => home_url( '/?attachment_id=' . $image ),
				'control chars'    => str_replace( '?', "\t?", $local ),
			] as $label => $url
		) {
			$result = self::search( [ 'content_url' => $url ] );
			$this->assertWPError( $result, $label . ': ' . $url );
			$this->assertContains( $result->get_error_code(), [ 'tsmlt_content_not_found', 'tsmlt_invalid_input' ], $label );
		}
		remove_filter( 'pre_http_request', $block );
		$this->assertSame( 0, $fetched, 'No URL is fetched.' );
		$this->assertWPError(
			self::search(
				[
					'content_url' => $local,
					'content_id'  => $page,
				]
			),
			'One content filter only.'
		);
	}

	/**
	 * A page without images: its media search is empty, with the page named.
	 */
	public function test_a_page_without_images() {
		$page   = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$result = self::search( [ 'content_url' => get_permalink( $page ) ] );
		$this->assertSame( [], $result['items'] );
		$this->assertSame( 0, $result['total'] );
		$this->assertSame( $page, $result['content']['id'] );
	}

	/**
	 * Search, then export only the images chosen: the CSV has exactly those
	 * rows, with all ten standard columns in order; choosing all results
	 * exports all of them; more than 100 IDs are refused.
	 */
	public function test_search_select_and_export() {
		$images = [];
		for ( $i = 0; $i < 4; $i++ ) {
			$images[] = $this->image( 'select/logo-' . $i . '.png', [ 'post_title' => 'Logo ' . $i ] );
		}
		$this->image( 'select/team.png', [ 'post_title' => 'Team' ] );

		$found = $this->ids_of(
			[
				'search' => 'Logo',
				'order'  => 'ASC',
			]
		);
		$this->assertEqualsCanonicalizing( $images, $found );

		$chosen = [ $found[1], $found[3] ];
		$export = wp_get_ability( 'tsmlt/export-media-csv' )->execute( [ 'ids' => $chosen ] );
		$this->assertSame( $chosen, $export['exported_ids'] );
		$this->assertSame( MediaCsv::COLUMNS, $export['columns'] );
		$this->assertSame( 2, $export['rows'] );
		$this->assertStringStartsWith( 'ID,slug,url,title,alt_text,caption,description,post_parent,menu_order,groups', $export['csv'] );

		$all = wp_get_ability( 'tsmlt/export-media-csv' )->execute( [ 'ids' => $found ] );
		$this->assertSame( $found, $all['exported_ids'] );

		$this->assertWPError( wp_get_ability( 'tsmlt/export-media-csv' )->execute( [ 'ids' => range( 1, 101 ) ] ), 'At most 100.' );
	}

	/**
	 * The descriptions guide search and select for the Free export.
	 */
	public function test_search_description() {
		$description = wp_get_ability( 'tsmlt/search-media' )->get_description();
		foreach ( [ 'by ids (1 to 100 media IDs the user gave', 'by a post, page or WooCommerce product, given by content_id, content_title or content_url', 'never fetched', 'Not every image visible on a page can be found', 'featured image', 'product gallery', 'content_candidates lists them: ask the user which one', 'its file name, to tell similar images apart' ] as $phrase ) {
			$this->assertStringContainsString( $phrase, $description );
		}
	}
}
