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
		$this->assertSame( [ $image ], array_keys( $this->found( $this->elementor_page( $valid, '', '' ) ) ), 'Read whatever the edit mode.' );

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
	 * Block markup as the block editor saves it: image blocks in a group and
	 * columns, a gallery of image blocks and an older gallery with an ids
	 * list, a cover and media & text; images used twice; image blocks
	 * without an ID whose URL is a sized copy or the original of a large
	 * image WordPress scaled down (stored as "-scaled"); invalid, trashed and
	 * unreadable IDs; an external image. Through tsmlt/search-media the
	 * valid local images are listed once each, the rest left out, the
	 * output matches the schema, mime_type "image" keeps them, and nothing
	 * is fetched.
	 */
	public function test_realistic_gutenberg_page() {
		$u       = wp_get_upload_dir()['baseurl'] . '/';
		$hero    = $this->image( '2026/05/hero.jpg' );
		$g1      = $this->image( '2026/05/g1.jpg' );
		$g2      = $this->image( '2026/05/g2.jpg' );
		$cover   = $this->image( '2026/05/cover.jpg' );
		$mt      = $this->image( '2026/05/mt.jpg' );
		$old     = $this->image( '2026/05/old-gallery.jpg' );
		$big     = $this->image( '2026/05/big-photo-scaled.jpg' );
		$second  = $this->image( '2026/05/second-scaled.jpg' );
		$trashed = $this->image( '2026/05/trashed.jpg' );
		$hidden  = $this->image( '2026/05/hidden.jpg' );
		$a_post  = (int) self::factory()->post->create();
		$image   = static function ( $id, string $src, string $css = '' ) {
			$attrs = $id ? '{"id":' . $id . ',"sizeSlug":"large","linkDestination":"none"} ' : '';
			return '<!-- wp:image ' . $attrs . '--><figure class="wp-block-image size-large"><img src="' . $src . '" alt=""' . ( $css ? ' class="' . $css . '"' : '' ) . '/></figure><!-- /wp:image -->';
		};
		$content = '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column">'
			. $image( $hero, $u . '2026/05/hero-1024x683.jpg', 'wp-image-' . $hero )
			. '</div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column">'
			. $image( $hero, $u . '2026/05/hero.jpg', 'wp-image-' . $hero )
			. '</div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->'
			. '<!-- wp:gallery {"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped">'
			. $image( $g1, $u . '2026/05/g1.jpg', 'wp-image-' . $g1 ) . $image( $g2, $u . '2026/05/g2-300x300.jpg', 'wp-image-' . $g2 )
			. $image( $trashed, $u . '2026/05/trashed.jpg', 'wp-image-' . $trashed ) . $image( $hidden, $u . '2026/05/hidden.jpg', 'wp-image-' . $hidden )
			. '</figure><!-- /wp:gallery -->'
			. '<!-- wp:gallery {"ids":[' . $old . ',' . $g1 . ',999999],"linkTo":"none"} --><figure class="wp-block-gallery columns-2"><ul class="blocks-gallery-grid"><li class="blocks-gallery-item"><figure><img src="' . $u . '2026/05/old-gallery.jpg" data-id="' . $old . '" class="wp-image-' . $old . '"/></figure></li></ul></figure><!-- /wp:gallery -->'
			. '<!-- wp:cover {"url":"' . $u . '2026/05/cover.jpg","id":' . $cover . ',"dimRatio":50,"isDark":false} --><div class="wp-block-cover is-light"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background wp-image-' . $cover . '" alt="" src="' . $u . '2026/05/cover.jpg" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->'
			. '<!-- wp:media-text {"mediaId":' . $mt . ',"mediaLink":"' . home_url( '/?attachment_id=' . $mt ) . '","mediaType":"image"} --><div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="' . $u . '2026/05/mt-1024x768.jpg" alt="" class="wp-image-' . $mt . ' size-full"/></figure><div class="wp-block-media-text__content"></div></div><!-- /wp:media-text -->'
			. $image( 0, $u . '2026/05/big-photo-1024x683.jpg' )
			. $image( 0, $u . '2026/05/second.jpg' )
			. $image( $a_post, $u . '2026/05/not-media.jpg' )
			. $image( 0, 'https://cdn.example.net/2026/05/hero.jpg' );
		$page    = (int) self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_content' => $content,
			]
		);
		wp_trash_post( $trashed );
		$this->denied = [ $hidden ];

		$ability = wp_get_ability( 'tsmlt/search-media' );
		$result  = $ability->execute(
			[
				'content_url' => get_permalink( $page ),
				'per_page'    => 50,
			]
		);
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		$this->assertSame( $page, $result['content']['id'] );
		$ids = wp_list_pluck( $result['items'], 'id' );
		$this->assertEqualsCanonicalizing( [ $hero, $g1, $g2, $old, $cover, $mt, $big, $second ], $ids );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ), 'Images used twice are listed once.' );
		foreach ( $result['items'] as $item ) {
			$this->assertSame( [ 'content' ], $item['associations'] );
		}
		$this->assertTrue( rest_validate_value_from_schema( $result, $ability->get_output_schema(), 'result' ) );

		$images = $ability->execute(
			[
				'content_url' => get_permalink( $page ),
				'mime_type'   => 'image',
				'per_page'    => 50,
			]
		);
		$this->assertEqualsCanonicalizing( $ids, wp_list_pluck( $images['items'], 'id' ) );
		$this->assertSame( 0, $this->requests, 'The external image is not fetched.' );
	}

	/**
	 * No editor is named or marked: one product with no Elementor edit mode
	 * and mixed content gets every source read: attached, featured, product
	 * gallery, nested Gutenberg blocks, classic HTML (wp-image class and a
	 * sized local URL), [gallery ids] and [gallery include], and saved
	 * Elementor data (image, gallery, background). An image found several
	 * ways is listed once with every way; an unsupported builder's shortcode
	 * and an external image give nothing and no error; the output matches
	 * the schema.
	 */
	public function test_every_source_is_read_without_an_editor_mode() {
		$registered = false;
		if ( ! post_type_exists( 'product' ) ) {
			register_post_type( 'product', [ 'public' => true ] );
			$registered = true;
		}
		$main     = $this->image( 'auto/main.jpg' );
		$gallery  = $this->image( 'auto/gallery.jpg' );
		$block    = $this->image( 'auto/block.jpg' );
		$classic  = $this->image( 'auto/classic.jpg' );
		$sized    = $this->image( 'auto/sized.jpg' );
		$sc_ids   = $this->image( 'auto/shortcode-ids.jpg' );
		$sc_inc   = $this->image( 'auto/shortcode-include.jpg' );
		$el_image = $this->image( 'auto/el-image.jpg' );
		$el_gal   = $this->image( 'auto/el-gallery.jpg' );
		$el_bg    = $this->image( 'auto/el-background.jpg' );
		$builder  = $this->image( 'auto/other-builder.jpg' );
		$content  = '<!-- wp:group --><div class="wp-block-group"><!-- wp:image {"id":' . $block . '} --><figure class="wp-block-image"><img src="x.jpg" class="wp-image-' . $block . '"/></figure><!-- /wp:image -->'
			. '<!-- wp:image {"id":' . $main . '} --><figure class="wp-block-image"><img src="m.jpg" class="wp-image-' . $main . '"/></figure><!-- /wp:image --></div><!-- /wp:group -->'
			. '<p><img class="alignleft wp-image-' . $classic . '" src="c.jpg"> <img src="' . self::upload_url( 'auto/sized-300x200.jpg' ) . '"></p>'
			. '[gallery ids="' . $sc_ids . '"] [gallery include="' . $sc_inc . '" columns="2"]'
			. '[et_pb_image src="' . self::upload_url( 'auto/other-builder.jpg' ) . '" _builder_version="4.0"][/et_pb_image]<img src="https://cdn.example.net/auto/main.jpg">';
		$product  = (int) self::factory()->post->create(
			[
				'post_type'    => 'product',
				'post_content' => $content,
			]
		);
		wp_update_post(
			[
				'ID'          => $main,
				'post_parent' => $product,
			]
		);
		set_post_thumbnail( $product, $main );
		update_post_meta( $product, '_product_image_gallery', $gallery . ',' . $main );
		update_post_meta(
			$product,
			'_elementor_data',
			wp_slash(
				wp_json_encode(
					[
						self::holder(
							[
								self::widget( 'image', [ 'image' => self::media( $el_image, self::upload_url( 'auto/el-image.jpg' ) ) ] ),
								self::widget( 'image-gallery', [ 'wp_gallery' => [ self::media( $el_gal, self::upload_url( 'auto/el-gallery.jpg' ) ), self::media( $main, self::upload_url( 'auto/main.jpg' ) ) ] ] ),
							],
							[ 'background_image' => self::media( $el_bg, self::upload_url( 'auto/el-background.jpg' ) ) ]
						),
					]
				)
			)
		);
		$this->assertSame( '', get_post_meta( $product, '_elementor_edit_mode', true ), 'No editor mode is set.' );

		$ability = wp_get_ability( 'tsmlt/search-media' );
		$result  = $ability->execute(
			[
				'content_id' => $product,
				'per_page'   => 50,
			]
		);
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		$how = wp_list_pluck( $result['items'], 'associations', 'id' );
		$this->assertEqualsCanonicalizing( [ $main, $gallery, $block, $classic, $sized, $sc_ids, $sc_inc, $el_image, $el_gal, $el_bg ], array_keys( $how ) );
		$this->assertSame( [ 'attached', 'featured', 'gallery', 'content', 'elementor' ], $how[ $main ], 'Found five ways, listed once with all of them.' );
		$this->assertSame( [ 'gallery' ], $how[ $gallery ] );
		foreach ( [ $block, $classic, $sized, $sc_ids, $sc_inc ] as $id ) {
			$this->assertSame( [ 'content' ], $how[ $id ] );
		}
		foreach ( [ $el_image, $el_gal, $el_bg ] as $id ) {
			$this->assertSame( [ 'elementor' ], $how[ $id ] );
		}
		$this->assertArrayNotHasKey( $builder, $how, 'Other builders (here a Divi image) are not read; no error.' );
		$this->assertTrue( rest_validate_value_from_schema( $result, $ability->get_output_schema(), 'result' ) );
		$this->assertSame( 0, $this->requests );

		if ( $registered ) {
			unregister_post_type( 'product' );
		}
	}

	/**
	 * A page built with WPBakery, as it is stored: a row with a background
	 * in its design CSS (url with ?id=), columns with an inner row, single
	 * image, gallery (an ID repeated) and image carousel elements, a single
	 * image from an external link given by a local sized URL (and one by an
	 * external URL with a stale ID), a scaled image by URL, a section with a
	 * parallax image holding a gallery of encoded external-link URLs.
	 * Invalid, non-media, trashed and unreadable IDs are left out, and a PDF
	 * by mime_type "image"; external URLs give nothing and are not fetched.
	 */
	public function test_wpbakery_page() {
		$u       = wp_get_upload_dir()['baseurl'] . '/';
		$row_bg  = $this->image( 'wpb/row-bg.jpg' );
		$col_bg  = $this->image( 'wpb/col-bg.jpg' );
		$single  = $this->image( 'wpb/single.jpg' );
		$g1      = $this->image( 'wpb/g1.jpg' );
		$g2      = $this->image( 'wpb/g2.jpg' );
		$c1      = $this->image( 'wpb/c1.jpg' );
		$c2      = $this->image( 'wpb/c2.jpg' );
		$by_url  = $this->image( 'wpb/by-url.jpg' );
		$big     = $this->image( 'wpb/big-scaled.jpg' );
		$stale   = $this->image( 'wpb/stale.jpg' );
		$foreign = $this->image( 'wpb/unrelated-local.jpg' );
		$par     = $this->image( 'wpb/parallax.jpg' );
		$encoded = $this->image( 'wpb/encoded.jpg' );
		$trashed = $this->image( 'wpb/trashed.jpg' );
		$hidden  = $this->image( 'wpb/hidden.jpg' );
		$pdf     = (int) self::factory()->attachment->create(
			[
				'file'           => 'wpb/sheet.pdf',
				'post_mime_type' => 'application/pdf',
			]
		);
		$a_post  = (int) self::factory()->post->create();
		$srcs    = '#E-8_' . base64_encode( rawurlencode( $u . 'wpb/encoded-300x300.jpg,https://cdn.example.net/y.jpg' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WPBakery's attribute encoding.
		$content = '[vc_row css=".vc_custom_1600000000001{background-image: url(' . $u . 'wpb/row-bg.jpg?id=' . $row_bg . ') !important;}"]'
			. '[vc_column width="1/2"][vc_single_image image="' . $single . '" img_size="large" alignment="center"]'
			. '[vc_gallery type="image_grid" images="' . $g1 . ',' . $g2 . ',' . $g1 . ',999999,' . $a_post . ',' . $trashed . ',' . $hidden . ',' . $pdf . '" img_size="thumbnail"][/vc_column]'
			. '[vc_column width="1/2" css=".vc_custom_1600000000002{background-image: url(' . $u . 'wpb/col-bg-1024x683.jpg) !important;}"]'
			. '[vc_row_inner][vc_column_inner css=".vc_custom_1600000000003{background-image: url(https://demo.example.com/wp-content/uploads/2023/11/demo-bg.jpg?id=' . $foreign . ') !important;}"][vc_images_carousel images="' . $c1 . ',' . $c2 . '" img_size="medium"]'
			. '[vc_single_image source="external_link" custom_src="' . $u . 'wpb/by-url-300x200.jpg"]'
			. '[vc_single_image source="external_link" custom_src="https://cdn.example.net/x.jpg" image="' . $stale . '"]'
			. '[vc_single_image source="external_link" custom_src="' . $u . 'wpb/big-1024x683.jpg"]'
			. '[/vc_column_inner][/vc_row_inner][/vc_column][/vc_row]'
			. '[vc_section parallax="content-moving" parallax_image="' . $par . '"][vc_row][vc_column]'
			. '[vc_gallery source="external_link" custom_srcs="' . $srcs . '"][vc_column_text]<p>Text</p>[/vc_column_text]'
			. '[/vc_column][/vc_row][/vc_section]';
		$page    = (int) self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_content' => $content,
			]
		);
		update_post_meta( $page, '_wpb_vc_js_status', 'true' );
		wp_trash_post( $trashed );
		$this->denied = [ $hidden ];

		$ability = wp_get_ability( 'tsmlt/search-media' );
		$result  = $ability->execute(
			[
				'content_url' => get_permalink( $page ),
				'per_page'    => 50,
			]
		);
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		$how = wp_list_pluck( $result['items'], 'associations', 'id' );
		$this->assertEqualsCanonicalizing( [ $row_bg, $col_bg, $single, $g1, $g2, $c1, $c2, $by_url, $big, $par, $encoded, $pdf ], array_keys( $how ) );
		foreach ( $how as $associations ) {
			$this->assertSame( [ 'wpbakery' ], $associations );
		}
		$this->assertArrayNotHasKey( $stale, $how, 'An external link ignores the stale image ID.' );
		$this->assertArrayNotHasKey( $foreign, $how, 'A background ?id= from another site is not taken for an unrelated local image.' );
		$this->assertTrue( rest_validate_value_from_schema( $result, $ability->get_output_schema(), 'result' ) );

		$images = $ability->execute(
			[
				'content_url' => get_permalink( $page ),
				'mime_type'   => 'image',
				'per_page'    => 50,
			]
		);
		$this->assertNotContains( $pdf, wp_list_pluck( $images['items'], 'id' ) );
		$this->assertCount( 11, $images['items'] );
		$this->assertSame( 0, $this->requests );
	}

	/**
	 * WPBakery, Gutenberg and Elementor references in one page: an image
	 * used by all three is listed once with all three associations; each
	 * source's own images are found too.
	 */
	public function test_mixed_wpbakery_gutenberg_elementor() {
		$shared   = $this->image( 'mixed/shared.jpg' );
		$wpb_only = $this->image( 'mixed/wpb.jpg' );
		$gb_only  = $this->image( 'mixed/gb.jpg' );
		$el_only  = $this->image( 'mixed/el.jpg' );
		$content  = '<!-- wp:image {"id":' . $shared . '} --><figure class="wp-block-image"><img src="s.jpg" class="wp-image-' . $shared . '"/></figure><!-- /wp:image -->'
			. '<!-- wp:image {"id":' . $gb_only . '} --><figure class="wp-block-image"><img src="g.jpg" class="wp-image-' . $gb_only . '"/></figure><!-- /wp:image -->'
			. '[vc_row][vc_column][vc_single_image image="' . $shared . '"][vc_single_image image="' . $wpb_only . '"][/vc_column][/vc_row]';
		$page     = $this->elementor_page(
			[
				self::holder(
					[
						self::widget( 'image', [ 'image' => self::media( $shared, self::upload_url( 'mixed/shared.jpg' ) ) ] ),
						self::widget( 'image', [ 'image' => self::media( $el_only, self::upload_url( 'mixed/el.jpg' ) ) ] ),
					]
				),
			],
			$content
		);

		$ability = wp_get_ability( 'tsmlt/search-media' );
		$result  = $ability->execute( [ 'content_id' => $page ] );
		$how     = wp_list_pluck( $result['items'], 'associations', 'id' );
		$this->assertSame( [ 'content', 'wpbakery', 'elementor' ], $how[ $shared ] );
		$this->assertSame( [ 'wpbakery' ], $how[ $wpb_only ] );
		$this->assertSame( [ 'content' ], $how[ $gb_only ] );
		$this->assertSame( [ 'elementor' ], $how[ $el_only ] );
		$this->assertCount( 4, $how );
		$this->assertTrue( rest_validate_value_from_schema( $result, $ability->get_output_schema(), 'result' ) );
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
		$this->assertSame( [ 'attached', 'featured', 'gallery', 'content', 'elementor' ], $how[ $all ], 'All five, in order.' );
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
