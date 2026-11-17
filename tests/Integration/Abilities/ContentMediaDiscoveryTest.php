<?php
/**
 * Media discovery for a page or post (ContentMedia, through
 * `tsmlt/search-media`): Gutenberg blocks (nested ones and URL-only ones),
 * classic editor images, featured images and shortcodes, and pages built
 * with Elementor, read from their saved `_elementor_data` without
 * Elementor. Includes a fixture shaped like the About Us page (2784) of the
 * development site: an Elementor page whose images were imported from a
 * demo site, so its saved IDs and URLs are not in this Media Library.
 *
 * Elementor is not loaded. No URL is fetched, no usage scan is started and
 * no attachment's parent changes. Every post and attachment is a generated
 * fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\ContentMedia;
use TinySolutions\mlt\Modules\Csv\MediaCsv;

/**
 * ContentMediaDiscoveryTest
 */
class ContentMediaDiscoveryTest extends WP_UnitTestCase {

	/**
	 * IDs for which read_post is denied (map_meta_cap filter).
	 *
	 * @var int[]
	 */
	private $denied = [];

	/**
	 * Outgoing HTTP requests attempted.
	 *
	 * @var int
	 */
	private $requests = 0;

	/**
	 * Administrator; read filter; HTTP blocked and counted.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/search-media' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->denied   = [];
		$this->requests = 0;
		add_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10, 4 );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
	}

	/**
	 * Remove the filters; nothing was fetched.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10 );
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
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
	 * Count and refuse any outgoing request.
	 *
	 * @return WP_Error
	 */
	public function block_http() {
		++$this->requests;
		return new WP_Error( 'blocked', 'No HTTP in this test.' );
	}

	/**
	 * An image attachment stored at uploads/<file>.
	 *
	 * @param string $file File, relative to uploads.
	 *
	 * @return int
	 */
	private function image( string $file ): int {
		return (int) self::factory()->attachment->create(
			[
				'file'           => $file,
				'post_mime_type' => 'image/jpeg',
				'post_title'     => wp_basename( $file ),
			]
		);
	}

	/**
	 * The URL of a file of this site's uploads.
	 *
	 * @param string $file File, relative to uploads.
	 *
	 * @return string
	 */
	private static function upload_url( string $file ): string {
		return wp_get_upload_dir()['baseurl'] . '/' . $file;
	}

	/**
	 * A page built with Elementor, with its saved data.
	 *
	 * @param mixed  $data    Elementor data (encoded to JSON unless a string).
	 * @param string $content Post content.
	 * @param string $mode    Elementor edit mode.
	 *
	 * @return int
	 */
	private function elementor_page( $data, string $content = '', string $mode = 'builder' ): int {
		$page = (int) self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_content' => $content,
			]
		);
		update_post_meta( $page, '_elementor_edit_mode', $mode );
		update_post_meta( $page, '_elementor_data', wp_slash( is_string( $data ) ? $data : wp_json_encode( $data ) ) );
		return $page;
	}

	/**
	 * An Elementor widget.
	 *
	 * @param string $type     Widget type.
	 * @param array  $settings Settings.
	 *
	 * @return array
	 */
	private static function widget( string $type, array $settings ): array {
		return [
			'id'         => substr( md5( $type . wp_json_encode( $settings ) ), 0, 7 ),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => [],
		];
	}

	/**
	 * An Elementor container (or section/column) holding elements.
	 *
	 * @param array  $elements Children.
	 * @param array  $settings Settings.
	 * @param string $type     Element type.
	 *
	 * @return array
	 */
	private static function holder( array $elements, array $settings = [], string $type = 'container' ): array {
		return [
			'id'       => substr( md5( wp_json_encode( $elements ) . $type ), 0, 7 ),
			'elType'   => $type,
			'settings' => $settings,
			'elements' => $elements,
		];
	}

	/**
	 * An Elementor media field.
	 *
	 * @param int    $id  Attachment ID ('' when none).
	 * @param string $url URL.
	 *
	 * @return array
	 */
	private static function media( $id, string $url ): array {
		return [
			'url'    => $url,
			'id'     => $id,
			'alt'    => '',
			'source' => 'library',
		];
	}

	/**
	 * Media of a content: ID => associations.
	 *
	 * @param int $content_id Content ID.
	 *
	 * @return array<int, string[]>
	 */
	private function found( int $content_id ): array {
		$result = wp_get_ability( 'tsmlt/search-media' )->execute(
			[
				'content_id' => $content_id,
				'per_page'   => 50,
			]
		);
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		return wp_list_pluck( $result['items'], 'associations', 'id' );
	}

	/**
	 * Shaped like page 2784 (About Us) of the development site: built with
	 * Elementor, its post content a shortcode block and rendered images of
	 * a demo site, its Elementor data image fields with the demo site's IDs
	 * and URLs (one of those IDs taken by an unrelated local image). None of
	 * them are in this Media Library, so nothing is found, and an unrelated
	 * image that happens to have a saved ID is not taken for one of them.
	 */
	public function test_page_2784_shape_finds_no_foreign_images() {
		$unrelated = $this->image( '2026/01/unrelated-local.jpg' );
		$demo      = 'https://demo.example.com/wordpress/themes/zilly/wp-content/uploads/2023/11/';
		$data      = [
			self::holder(
				[
					self::widget( 'rt-image', [ 'rt_image' => self::media( 295, $demo . 'about_1.jpg' ) ] ),
					self::widget( 'rt-image', [ 'rt_image' => self::media( $unrelated, $demo . 'about_2.jpg' ) ] ),
					self::holder(
						[
							self::widget( 'image', [ 'image' => self::media( 297, $demo . '100per_fresh.png' ) ] ),
							self::widget( 'rt-video', [ 'video_image' => self::media( 298, $demo . 'video.jpg' ) ] ),
							self::widget(
								'rt-testimonials',
								[
									'items' => [
										[ 'image' => self::media( 302, $demo . 'testimonial_01.jpg' ) ],
										[ 'image' => self::media( 303, $demo . 'testimonial_02.jpg' ) ],
									],
								]
							),
						]
					),
				]
			),
		];
		$content   = "<!-- wp:shortcode -->\n[media_library_tools_pricing/]\n<!-- /wp:shortcode -->\n<img src=\"" . $demo . 'team_1.jpg" alt="">';
		$page      = $this->elementor_page( $data, $content );

		$this->assertSame( [], $this->found( $page ) );
		$this->assertSame( 0, $this->requests, 'Nothing is fetched.' );
	}

	/**
	 * Page 2784's real content structure (sanitized): a shortcode block, then
	 * the static HTML a page builder saved, with plain <img src srcset> tags
	 * (no image blocks, no wp-image classes). When those images are files of
	 * this site's uploads, the page found by its ?page_id= URL lists them:
	 * once each (a sized copy and a repeat included), trashed and unreadable
	 * ones left out, external ones ignored, and a non-image attachment left
	 * out by mime_type "image".
	 */
	public function test_page_2784_structure_with_local_images() {
		$about    = $this->image( '2023/11/about_1.jpg' );
		$fresh    = $this->image( '2023/11/100per_fresh.jpg' );
		$team     = $this->image( '2023/11/team_1.jpg' );
		$trashed  = $this->image( '2023/11/team_2.jpg' );
		$hidden   = $this->image( '2023/11/team_3.jpg' );
		$external = 'https://demo.example.com/wordpress/themes/zilly/wp-content/uploads/2023/11/';
		$html     = "<!-- wp:shortcode -->\n[media_library_tools_pricing/]\n<!-- /wp:shortcode -->\n\n<p>Who We Are</p>\n<h2 data-wow-delay=\"0.4s\">About</h2>\n"
			. '<img width="420" height="520" src="' . self::upload_url( '2023/11/about_1.jpg' ) . '" alt="" />' . "\n"
			. '<img width="154" height="154" src="' . self::upload_url( '2023/11/100per_fresh-150x150.jpg' ) . '" alt="" srcset="' . self::upload_url( '2023/11/100per_fresh.jpg' ) . ' 154w, ' . self::upload_url( '2023/11/100per_fresh-100x100.jpg' ) . ' 100w" sizes="(max-width: 154px) 100vw, 154px" />' . "\n"
			. '<img width="360" height="440" src="' . self::upload_url( '2023/11/team_1.jpg' ) . '" alt="" decoding="async" />' . "\n"
			. '<img width="360" height="440" src="' . self::upload_url( '2023/11/team_1.jpg' ) . '" alt="" />' . "\n"
			. '<img width="360" height="440" src="' . self::upload_url( '2023/11/team_2.jpg' ) . '" alt="" />' . "\n"
			. '<img width="360" height="440" src="' . self::upload_url( '2023/11/team_3.jpg' ) . '" alt="" />' . "\n"
			. '<img width="420" height="520" src="' . $external . 'about_2.jpg" alt="" />';
		$page     = (int) self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'About Us',
				'post_content' => $html,
			]
		);
		$pdf      = (int) self::factory()->attachment->create(
			[
				'file'           => '2023/11/brochure.pdf',
				'post_mime_type' => 'application/pdf',
				'post_parent'    => $page,
			]
		);
		wp_trash_post( $trashed );
		$this->denied = [ $hidden ];

		$search = wp_get_ability( 'tsmlt/search-media' );
		$result = $search->execute( [ 'content_url' => home_url( '/?page_id=' . $page ) ] );
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		$this->assertSame( $page, $result['content']['id'], 'The URL resolves to the page.' );
		$ids = wp_list_pluck( $result['items'], 'id' );
		$this->assertEqualsCanonicalizing( [ $about, $fresh, $team, $pdf ], $ids );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ), 'No duplicates.' );
		$how = wp_list_pluck( $result['items'], 'associations', 'id' );
		$this->assertSame( [ 'content' ], $how[ $team ] );
		$this->assertSame( [ 'attached' ], $how[ $pdf ] );

		$images = $search->execute(
			[
				'content_url' => home_url( '/?page_id=' . $page ),
				'mime_type'   => 'image',
			]
		);
		$this->assertEqualsCanonicalizing( [ $about, $fresh, $team ], wp_list_pluck( $images['items'], 'id' ), 'mime_type image leaves the PDF out.' );
		$this->assertSame( 0, $this->requests, 'Nothing is fetched.' );
	}

	/**
	 * The same Elementor page with its images in this Media Library: every
	 * widget's image is found (by ID, or by its local URL), once each.
	 */
	public function test_elementor_page_with_local_images() {
		$about  = $this->image( '2023/11/about_1.jpg' );
		$fresh  = $this->image( '2023/11/100per_fresh.jpg' );
		$quote  = $this->image( '2023/11/testimonial_01.jpg' );
		$by_url = $this->image( '2023/11/team_1.jpg' );
		$data   = [
			self::holder(
				[
					self::widget( 'rt-image', [ 'rt_image' => self::media( $about, self::upload_url( '2023/11/about_1.jpg' ) ) ] ),
					self::holder(
						[
							self::widget( 'image', [ 'image' => self::media( $fresh, self::upload_url( '2023/11/100per_fresh.jpg' ) ) ] ),
							self::widget( 'rt-testimonials', [ 'items' => [ [ 'image' => self::media( $quote, self::upload_url( '2023/11/testimonial_01-300x300.jpg' ) ) ] ] ] ),
							self::widget( 'rt-team', [ 'items' => [ [ 'image' => self::media( '', self::upload_url( '2023/11/team_1.jpg' ) ) ] ] ] ),
							self::widget( 'image', [ 'image' => self::media( $about, self::upload_url( '2023/11/about_1.jpg' ) ) ] ),
						]
					),
				]
			),
		];
		$page   = $this->elementor_page( $data );

		$found = $this->found( $page );
		$this->assertEqualsCanonicalizing( [ $about, $fresh, $quote, $by_url ], array_keys( $found ) );
		foreach ( $found as $how ) {
			$this->assertSame( [ 'elementor' ], $how, 'Each image once, from the Elementor data.' );
		}
	}

	/**
	 * Image, Image Gallery and Basic Gallery widgets, a section's background,
	 * nested section > column and container > container structures.
	 */
	public function test_elementor_widgets_and_structures() {
		$single     = $this->image( 'el/single.jpg' );
		$wp_gallery = [ $this->image( 'el/gallery-1.jpg' ), $this->image( 'el/gallery-2.jpg' ) ];
		$basic      = [ $this->image( 'el/basic-1.jpg' ), $this->image( 'el/basic-2.jpg' ) ];
		$background = $this->image( 'el/background.jpg' );
		$deep       = $this->image( 'el/deep.jpg' );
		$data       = [
			self::holder(
				[
					self::holder(
						[ self::widget( 'image', [ 'image' => self::media( $single, self::upload_url( 'el/single.jpg' ) ) ] ) ],
						[],
						'column'
					),
				],
				[ 'background_image' => self::media( $background, self::upload_url( 'el/background.jpg' ) ) ],
				'section'
			),
			self::holder(
				[
					self::widget(
						'image-gallery',
						[
							'wp_gallery' => [
								self::media( $wp_gallery[0], self::upload_url( 'el/gallery-1.jpg' ) ),
								self::media( $wp_gallery[1], self::upload_url( 'el/gallery-2.jpg' ) ),
							],
						]
					),
					self::holder(
						[
							self::holder(
								[
									self::widget(
										'gallery',
										[
											'gallery' => [
												self::media( $basic[0], self::upload_url( 'el/basic-1.jpg' ) ),
												self::media( $basic[1], self::upload_url( 'el/basic-2.jpg' ) ),
											],
										]
									),
									self::widget( 'image', [ 'image' => self::media( $deep, '' ) ] ),
								]
							),
						]
					),
				]
			),
		];
		$page       = $this->elementor_page( $data );

		$this->assertEqualsCanonicalizing( array_merge( [ $single, $background, $deep ], $wp_gallery, $basic ), array_keys( $this->found( $page ) ) );
	}

	/**
	 * Saved IDs that are missing, trashed, not readable or not media are
	 * left out; an external URL alone is ignored; a link (no media) is
	 * ignored.
	 */
	public function test_elementor_unusable_references() {
		$ok      = $this->image( 'el/ok.jpg' );
		$trashed = $this->image( 'el/trashed.jpg' );
		$hidden  = $this->image( 'el/hidden.jpg' );
		$post    = self::factory()->post->create();
		wp_trash_post( $trashed );
		$this->denied = [ $hidden ];
		$page         = $this->elementor_page(
			[
				self::holder(
					[
						self::widget( 'image', [ 'image' => self::media( $ok, self::upload_url( 'el/ok.jpg' ) ) ] ),
						self::widget( 'image', [ 'image' => self::media( $trashed, self::upload_url( 'el/trashed.jpg' ) ) ] ),
						self::widget( 'image', [ 'image' => self::media( $hidden, self::upload_url( 'el/hidden.jpg' ) ) ] ),
						self::widget( 'image', [ 'image' => self::media( 999999, '' ) ] ),
						self::widget( 'image', [ 'image' => self::media( $post, '' ) ] ),
						self::widget( 'image', [ 'image' => self::media( '', 'https://cdn.example.net/photo.jpg' ) ] ),
						self::widget( 'button', [ 'link' => [ 'url' => self::upload_url( 'el/ok.jpg' ) ] ] ),
					]
				),
			]
		);

		$this->assertSame( [ $ok ], array_keys( $this->found( $page ) ) );
		$this->assertSame( 0, $this->requests );
	}

	/**
	 * Elementor data is read only for pages in Elementor's builder mode,
	 * and malformed, unexpected or deeply nested data gives nothing without
	 * an error. Elementor itself is not loaded here.
	 */
	public function test_elementor_data_edge_cases() {
		$this->assertFalse( class_exists( '\Elementor\Plugin' ), 'Elementor is not active in this test.' );
		$image = $this->image( 'el/edge.jpg' );
		$valid = [ self::holder( [ self::widget( 'image', [ 'image' => self::media( $image, self::upload_url( 'el/edge.jpg' ) ) ] ) ] ) ];

		$this->assertSame( [ $image ], array_keys( $this->found( $this->elementor_page( $valid ) ) ) );
		$this->assertSame( [], $this->found( $this->elementor_page( $valid, '', 'draft' ) ), 'Not built with Elementor.' );

		$deep = self::widget( 'image', [ 'image' => self::media( $image, self::upload_url( 'el/edge.jpg' ) ) ] );
		for ( $i = 0; $i < 200; $i++ ) {
			$deep = self::holder( [ $deep ] );
		}
		foreach ( [ '{not json', '"a string"', '42', 'null', '[]', '[{"elType":"widget","settings":"oops"}]', '[{"settings":{"image":{"url":5,"id":[1]}}}]', wp_json_encode( [ $deep ] ) ] as $raw ) {
			$this->assertSame( [], $this->found( $this->elementor_page( $raw ) ), $raw );
		}
	}

	/**
	 * Gutenberg: nested group and columns, a gallery of image blocks, a
	 * cover saved with only its URL, media and text, an image block whose
	 * ID attribute is missing but whose image is a sized copy of a local
	 * file, a classic image without a wp-image class, the featured image
	 * and a [gallery] shortcode; external images ignored; an image used
	 * several times once, with every way it is used.
	 */
	public function test_gutenberg_and_classic_content() {
		$nested   = $this->image( 'gb/nested.jpg' );
		$in_gal   = $this->image( 'gb/in-gallery.jpg' );
		$cover    = $this->image( 'gb/cover.jpg' );
		$media    = $this->image( 'gb/media-text.jpg' );
		$no_id    = $this->image( 'gb/no-id.jpg' );
		$classic  = $this->image( 'gb/classic.jpg' );
		$featured = $this->image( 'gb/featured.jpg' );
		$short    = $this->image( 'gb/shortcode.jpg' );
		$content  = '<!-- wp:group --><div class="wp-block-group"><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column">'
			. '<!-- wp:image {"id":' . $nested . '} --><figure class="wp-block-image"><img src="' . self::upload_url( 'gb/nested.jpg' ) . '" class="wp-image-' . $nested . '"/></figure><!-- /wp:image -->'
			. '</div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->'
			. '<!-- wp:gallery {"linkTo":"none"} --><figure class="wp-block-gallery"><!-- wp:image {"id":' . $in_gal . '} --><figure class="wp-block-image"><img src="x.jpg"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->'
			. '<!-- wp:cover {"url":"' . self::upload_url( 'gb/cover.jpg' ) . '"} --><div class="wp-block-cover"></div><!-- /wp:cover -->'
			. '<!-- wp:media-text {"mediaId":' . $media . '} --><div class="wp-block-media-text"></div><!-- /wp:media-text -->'
			. '<!-- wp:image --><figure class="wp-block-image"><img src="' . self::upload_url( 'gb/no-id-1024x768.jpg' ) . '"/></figure><!-- /wp:image -->'
			. '<p><img src="' . self::upload_url( 'gb/classic.jpg' ) . '?ver=2" alt=""></p>'
			. '<p><img src="https://cdn.example.net/gb/classic.jpg"></p>'
			. '[gallery ids="' . $short . ',' . $nested . '"]';
		$post     = (int) self::factory()->post->create( [ 'post_content' => $content ] );
		set_post_thumbnail( $post, $nested );
		$page = (int) self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_content' => '<p><img class="wp-image-' . $featured . '" src="f.jpg"></p>',
			]
		);
		set_post_thumbnail( $page, $featured );

		$found = $this->found( $post );
		$this->assertEqualsCanonicalizing( [ $nested, $in_gal, $cover, $media, $no_id, $classic, $short ], array_keys( $found ) );
		$this->assertSame( [ 'featured', 'content' ], $found[ $nested ], 'Used three ways, listed once.' );
		$this->assertSame( [ 'featured', 'content' ], $this->found( $page )[ $featured ] );
		$this->assertSame( 0, $this->requests );
	}

	/**
	 * Searching changes no attachment's parent, starts no usage scan, and
	 * the images found export with the ten standard columns.
	 */
	public function test_no_side_effects_and_export() {
		$image  = $this->image( 'side/effect.jpg' );
		$page   = $this->elementor_page( [ self::holder( [ self::widget( 'image', [ 'image' => self::media( $image, self::upload_url( 'side/effect.jpg' ) ) ] ) ] ) ] );
		$status = get_option( 'tsmlt_used_where_scan_status', 'none' );

		$found = array_keys( $this->found( $page ) );
		$this->assertSame( [ $image ], $found );

		$this->assertSame( 0, (int) get_post( $image )->post_parent, 'No parent set.' );
		$this->assertFalse( wp_next_scheduled( 'tsmlt_used_where_scan_tick' ), 'No usage scan scheduled.' );
		$this->assertSame( $status, get_option( 'tsmlt_used_where_scan_status', 'none' ), 'Scan status untouched.' );
		$export = wp_get_ability( 'tsmlt/export-media-csv' )->execute( [ 'ids' => $found ] );
		$this->assertSame( MediaCsv::COLUMNS, $export['columns'] );
		$this->assertSame( $found, $export['exported_ids'] );
	}

	/**
	 * Every association type, on one image (all five) and on images found
	 * one way each, passes tsmlt/search-media's declared output schema: the
	 * Abilities API validates the result, and so does the schema check MCP
	 * clients run on structured content.
	 */
	public function test_every_association_passes_the_output_schema() {
		$registered = false;
		if ( ! post_type_exists( 'product' ) ) {
			register_post_type( 'product', [ 'public' => true ] );
			$registered = true;
		}
		$all      = $this->image( 'assoc/all.jpg' );
		$featured = $this->image( 'assoc/featured-only.jpg' );
		$gallery  = $this->image( 'assoc/gallery-only.jpg' );
		$content  = $this->image( 'assoc/content-only.jpg' );
		$builder  = $this->image( 'assoc/elementor-only.jpg' );
		$product  = (int) self::factory()->post->create(
			[
				'post_type'    => 'product',
				'post_content' => '<p><img class="wp-image-' . $all . '" src="a.jpg"><img class="wp-image-' . $content . '" src="c.jpg"></p>',
			]
		);
		wp_update_post(
			[
				'ID'          => $all,
				'post_parent' => $product,
			]
		);
		set_post_thumbnail( $product, $all );
		update_post_meta( $product, '_product_image_gallery', $all . ',' . $gallery );
		update_post_meta( $product, '_elementor_edit_mode', 'builder' );
		update_post_meta(
			$product,
			'_elementor_data',
			wp_slash(
				wp_json_encode(
					[
						self::holder(
							[
								self::widget( 'image', [ 'image' => self::media( $all, self::upload_url( 'assoc/all.jpg' ) ) ] ),
								self::widget( 'image', [ 'image' => self::media( $builder, self::upload_url( 'assoc/elementor-only.jpg' ) ) ] ),
							]
						),
					]
				)
			)
		);
		$other = (int) self::factory()->post->create( [ 'post_type' => 'page' ] );
		set_post_thumbnail( $other, $featured );

		$ability = wp_get_ability( 'tsmlt/search-media' );
		$result  = $ability->execute( [ 'content_id' => $product ] );
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		$this->assertTrue( rest_validate_value_from_schema( $result, $ability->get_output_schema(), 'result' ) );
		$how = wp_list_pluck( $result['items'], 'associations', 'id' );
		$this->assertSame( ContentMedia::ASSOCIATIONS, $how[ $all ], 'All five, in order.' );
		$this->assertSame( [ 'gallery' ], $how[ $gallery ] );
		$this->assertSame( [ 'content' ], $how[ $content ] );
		$this->assertSame( [ 'elementor' ], $how[ $builder ] );

		$single = $ability->execute( [ 'content_id' => $other ] );
		$this->assertTrue( rest_validate_value_from_schema( $single, $ability->get_output_schema(), 'result' ) );
		$this->assertSame( [ 'featured' ], $single['items'][0]['associations'] );

		if ( $registered ) {
			unregister_post_type( 'product' );
		}
	}

	/**
	 * The schema lists exactly ContentMedia::ASSOCIATIONS, and every
	 * association ContentMedia records is one of them; a schema without
	 * "elementor" rejects an Elementor result as MCP clients did.
	 */
	public function test_schema_lists_every_association_produced() {
		$schema = wp_get_ability( 'tsmlt/search-media' )->get_output_schema();
		$enum   = $schema['properties']['items']['items']['properties']['associations']['items']['enum'];
		$this->assertSame( ContentMedia::ASSOCIATIONS, $enum );

		$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/app/Abilities/ContentMedia.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the source under test.
		preg_match_all( "/self::add_found\\(\\s*\\\$found,.*?,\\s*'([a-z_]+)'\\s*\\);/s", $source, $produced );
		$this->assertNotEmpty( $produced[1] );
		$this->assertSame( [], array_values( array_diff( array_unique( $produced[1] ), ContentMedia::ASSOCIATIONS ) ), 'Every recorded association is in the schema.' );
		$this->assertEqualsCanonicalizing( ContentMedia::ASSOCIATIONS, array_values( array_unique( $produced[1] ) ), 'Every listed association is recorded somewhere.' );

		$stale = $schema;
		$stale['properties']['items']['items']['properties']['associations']['items']['enum'] = [ 'attached', 'featured', 'gallery', 'content' ];
		$image = $this->image( 'assoc/stale.jpg' );
		$page  = $this->elementor_page( [ self::holder( [ self::widget( 'image', [ 'image' => self::media( $image, self::upload_url( 'assoc/stale.jpg' ) ) ] ) ] ) ] );
		set_post_thumbnail( $page, $image );
		$result = wp_get_ability( 'tsmlt/search-media' )->execute( [ 'content_id' => $page ] );
		$this->assertSame( [ 'featured', 'elementor' ], $result['items'][0]['associations'] );
		$this->assertWPError( rest_validate_value_from_schema( $result, $stale, 'result' ), 'The old schema rejects "elementor".' );
		$this->assertTrue( rest_validate_value_from_schema( $result, $schema, 'result' ) );
	}

	/**
	 * At most MAX_URL_LOOKUPS distinct URLs are resolved per content.
	 */
	public function test_url_lookups_are_bounded() {
		$html = '';
		for ( $i = 0; $i < ContentMedia::MAX_URL_LOOKUPS + 20; $i++ ) {
			$html .= '<img src="' . self::upload_url( 'bound/missing-' . $i . '.jpg' ) . '">';
		}
		$last  = $this->image( 'bound/last.jpg' );
		$html .= '<img src="' . self::upload_url( 'bound/last.jpg' ) . '">';
		$post  = (int) self::factory()->post->create( [ 'post_content' => $html ] );

		$queries = get_num_queries();
		$this->assertArrayNotHasKey( $last, ContentMedia::associated( get_post( $post ) ), 'Lookups past the limit are skipped.' );
		$this->assertLessThan( 3 * ContentMedia::MAX_URL_LOOKUPS + 20, get_num_queries() - $queries );
	}
}
