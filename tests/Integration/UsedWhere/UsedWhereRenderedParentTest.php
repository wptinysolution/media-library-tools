<?php
/**
 * Used Where scans set an attachment's parent only from a usage that owns
 * it (UsedWhereScanner::OWNING_USAGE_TYPES). An image found only in a post's
 * rendered page — the site logo in the header, a sidebar image — is recorded
 * as a `permalink` usage but never becomes attached to that post.
 *
 * Rendered-page checking stays on: each post's public page is served from a
 * stand-in HTTP response containing a header with the logo. No real request
 * is made. Every post and attachment is a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereRenderedParentTest
 */
class UsedWhereRenderedParentTest extends WP_UnitTestCase {

	/**
	 * Rendered HTML for each permalink.
	 *
	 * @var array<string, string>
	 */
	private $pages = [];

	/**
	 * Permalink requests made by the scanner.
	 *
	 * @var string[]
	 */
	private $fetched = [];

	/**
	 * Administrator; no scan; rendered-page checking on; pages served locally.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( UsageScanStatus::OPTION );
		delete_option( 'site_logo' );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		$this->pages   = [];
		$this->fetched = [];
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_true' );
		add_filter( 'pre_http_request', [ $this, 'serve_rendered_page' ], 10, 3 );
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		remove_filter( 'tsmlt_scan_permalink_enabled_default', '__return_true' );
		remove_filter( 'pre_http_request', [ $this, 'serve_rendered_page' ], 10 );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		parent::tear_down();
	}

	/**
	 * Answer the scanner's permalink request with the page set for it;
	 * refuse every other request.
	 *
	 * @param false|array|WP_Error $pre  Short-circuit value.
	 * @param array                $args Request arguments.
	 * @param string               $url  Request URL.
	 *
	 * @return array|WP_Error
	 */
	public function serve_rendered_page( $pre, $args, $url ) {
		if ( ! isset( $this->pages[ $url ] ) ) {
			return new WP_Error( 'blocked', 'No HTTP in tests.' );
		}
		$this->fetched[] = $url;
		return [
			'headers'  => [],
			'body'     => $this->pages[ $url ],
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * An image, uploaded (attached) to a post or unattached.
	 *
	 * @param string $file   File.
	 * @param int    $parent Parent post.
	 *
	 * @return int
	 */
	private function image( string $file, int $parent = 0 ): int {
		return (int) self::factory()->attachment->create(
			[
				'file'           => 'rendered/' . $file,
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => $parent,
			]
		);
	}

	/**
	 * A published post whose rendered page shows the header images and its
	 * own content.
	 *
	 * @param string $title   Title.
	 * @param string $content Content.
	 * @param int[]  $header  Images rendered in the page header.
	 *
	 * @return int
	 */
	private function post( string $title, string $content = '', array $header = [] ): int {
		$id   = (int) self::factory()->post->create(
			[
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_content' => $content,
			]
		);
		$html = '<html><body><header>';
		foreach ( $header as $image ) {
			$html .= '<img src="' . esc_url( wp_get_attachment_url( $image ) ) . '" class="custom-logo">';
		}
		$this->pages[ get_permalink( $id ) ] = $html . '</header><main>' . $content . '</main></body></html>';
		return $id;
	}

	/**
	 * Image block markup.
	 *
	 * @param int $image Attachment.
	 *
	 * @return string
	 */
	private static function image_block( int $image ): string {
		return '<!-- wp:image {"id":' . $image . '} --><figure class="wp-block-image"><img src="' . esc_url( wp_get_attachment_url( $image ) ) . '" class="wp-image-' . $image . '"/></figure><!-- /wp:image -->';
	}

	/**
	 * Parent of an attachment, from the database.
	 *
	 * @param int $id Attachment.
	 *
	 * @return int
	 */
	private static function parent_of( int $id ): int {
		clean_post_cache( $id );
		return (int) get_post( $id )->post_parent;
	}

	/**
	 * Usage records of an attachment as "post_id:usage_type".
	 *
	 * @param int $id Attachment.
	 *
	 * @return string[]
	 */
	private static function usages_of( int $id ): array {
		$usages = get_post_meta( $id, UsedWhereScanner::META_KEY, true );
		$out    = array_map(
			static function ( $usage ) {
				return (int) $usage['post_id'] . ':' . $usage['usage_type'];
			},
			is_array( $usages ) ? $usages : []
		);
		sort( $out );
		return $out;
	}

	/**
	 * Run every scheduled scan batch.
	 */
	private static function run_batches() {
		for ( $i = 0; $i < 50; $i++ ) {
			$tick = null;
			foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
				foreach ( $hooks[ UsedWhereScanner::SCAN_TICK_HOOK ] ?? [] as $event ) {
					$tick = [ $timestamp, (int) $event['args'][0] ];
					break 2;
				}
			}
			if ( ! $tick ) {
				return;
			}
			wp_unschedule_event( $tick[0], UsedWhereScanner::SCAN_TICK_HOOK, [ $tick[1] ] );
			UsedWhereScanner::instance()->run_tick_batch( $tick[1] );
		}
	}

	/**
	 * The reported defect: a post is saved and rescanned before any full
	 * scan; the site logo in its rendered header is recorded as a rendered
	 * usage, but the logo stays unattached.
	 */
	public function test_logo_in_rendered_header_is_not_attached_on_post_rescan() {
		$logo = $this->image( 'logo.jpg' );
		update_option( 'site_logo', $logo );
		$post = $this->post( 'Post A', '', [ $logo ] );

		UsedWhereScanner::instance()->scan_single_post( $post );

		$this->assertSame( [ get_permalink( $post ) ], $this->fetched, 'The rendered page was fetched.' );
		$this->assertSame( [ $post . ':permalink' ], self::usages_of( $logo ), 'The logo was found in the rendered page.' );
		$this->assertSame( 0, self::parent_of( $logo ), 'A rendered-page match sets no parent.' );
	}

	/**
	 * Any image found only in a rendered page stays unattached, while an
	 * image in the post's content on the same page is attached to it.
	 */
	public function test_only_owning_usage_attaches_on_post_rescan() {
		$sidebar = $this->image( 'sidebar.jpg' );
		$inline  = $this->image( 'inline.jpg' );
		$post    = $this->post( 'Post B', self::image_block( $inline ), [ $sidebar ] );

		UsedWhereScanner::instance()->scan_single_post( $post );

		$this->assertSame( [ $post . ':permalink' ], self::usages_of( $sidebar ) );
		$this->assertSame( 0, self::parent_of( $sidebar ), 'Rendered only: no parent.' );
		$this->assertContains( $post . ':content', self::usages_of( $inline ) );
		$this->assertSame( $post, self::parent_of( $inline ), 'Used in the content: attached.' );
	}

	/**
	 * A featured image is attached to its post; an image already uploaded
	 * to another post keeps that parent.
	 */
	public function test_post_rescan_attaches_featured_and_keeps_existing_parent() {
		$other    = $this->post( 'Post C' );
		$featured = $this->image( 'featured.jpg' );
		$moved    = $this->image( 'moved.jpg', $other );
		$post     = $this->post( 'Post D', self::image_block( $moved ) );
		set_post_thumbnail( $post, $featured );

		UsedWhereScanner::instance()->scan_single_post( $post );

		$this->assertSame( $post, self::parent_of( $featured ), 'Featured image: attached.' );
		$this->assertSame( $other, self::parent_of( $moved ), 'Uploaded to Post C, used on Post D: still Post C.' );
	}

	/**
	 * A full scan with rendered-page checking on: the logo is recorded as
	 * site-wide and stays unattached; images in content get their post; an
	 * existing parent is kept.
	 */
	public function test_full_scan_with_rendered_pages_sets_parents_from_owning_usage_only() {
		$logo    = $this->image( 'logo-full.jpg' );
		$sidebar = $this->image( 'sidebar-full.jpg' );
		$inline  = $this->image( 'inline-full.jpg' );
		update_option( 'site_logo', $logo );
		$other = $this->post( 'Post E', '', [ $logo, $sidebar ] );
		$moved = $this->image( 'moved-full.jpg', $other );
		$post  = $this->post( 'Post F', self::image_block( $inline ) . self::image_block( $moved ), [ $logo, $sidebar ] );

		UsedWhereScanner::instance()->start_scheduled_scan();
		self::run_batches();

		$this->assertSame( 'complete', UsageScanStatus::read()['state'] );
		$this->assertContains( get_permalink( $post ), $this->fetched, 'The rendered pages were fetched.' );
		$this->assertSame( 0, self::parent_of( $logo ), 'Site logo: no parent.' );
		$this->assertSame( [ '0:site_logo' ], self::usages_of( $logo ) );
		$this->assertSame( 0, self::parent_of( $sidebar ), 'Rendered on every page only: no parent.' );
		$this->assertSame( $post, self::parent_of( $inline ), 'Used in content: attached.' );
		$this->assertSame( $other, self::parent_of( $moved ), 'Existing parent kept.' );
	}

	/**
	 * Parent selection from usage records: featured first, then the first
	 * other owning usage; rendered-page, site-wide, post 0 and malformed
	 * records never count.
	 */
	public function test_owning_parent_selection_rules() {
		$pick = new ReflectionMethod( UsedWhereScanner::class, 'owning_parent_post_id' );
		if ( PHP_VERSION_ID < 80100 ) {
			$pick->setAccessible( true );
		}
		$row  = static function ( $post_id, $type ) {
			return [
				'post_id'    => $post_id,
				'usage_type' => $type,
			];
		};

		$this->assertSame( 0, $pick->invoke( null, [] ) );
		$this->assertSame( 0, $pick->invoke( null, [ $row( 5, 'permalink' ), $row( 6, 'rendered' ), $row( 0, 'site_logo' ), $row( 0, 'term_meta' ), $row( 0, 'featured' ), 'not-a-row', [ 'usage_type' => 'content' ] ] ), 'No owning usage on a real post.' );
		$this->assertSame( 8, $pick->invoke( null, [ $row( 5, 'permalink' ), $row( 7, 'content' ), $row( 8, 'featured' ) ] ), 'Featured wins over an earlier content usage.' );
		$this->assertSame( 7, $pick->invoke( null, [ $row( 5, 'rendered' ), $row( 7, 'meta' ), $row( 9, 'woo_gallery' ) ] ), 'Otherwise the first owning usage.' );
		$this->assertSame( 0, $pick->invoke( null, [ $row( 4, 'unknown_type' ) ] ), 'Types outside OWNING_USAGE_TYPES never count.' );
	}

	/**
	 * Captured front-end HTML (scan_rendered_html) records rendered usages
	 * but sets no parent.
	 */
	public function test_captured_rendered_html_does_not_attach() {
		$logo = $this->image( 'captured-logo.jpg' );
		$post = $this->post( 'Post H' );

		UsedWhereScanner::instance()->scan_rendered_html( '<header><img src="' . esc_url( wp_get_attachment_url( $logo ) ) . '"></header>', $post );

		$this->assertSame( [ $post . ':rendered' ], self::usages_of( $logo ) );
		$this->assertSame( 0, self::parent_of( $logo ) );
	}

	/**
	 * An attachment, a revision or a missing post never becomes a parent,
	 * even through an owning usage type.
	 */
	public function test_invalid_owners_never_become_parents() {
		$image    = $this->image( 'invalid-owner.jpg' );
		$owner    = $this->image( 'owner-attachment.jpg' );
		$post     = $this->post( 'Post I' );
		$revision = (int) self::factory()->post->create(
			[
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $post,
			]
		);

		UsedWhereScanner::instance()->record_frontend_usage( $image, $owner, 'content' );
		$this->assertSame( 0, self::parent_of( $image ), 'Attachment owner.' );
		UsedWhereScanner::instance()->record_frontend_usage( $image, $revision, 'content' );
		$this->assertSame( 0, self::parent_of( $image ), 'Revision owner.' );
		UsedWhereScanner::instance()->record_frontend_usage( $image, PHP_INT_MAX, 'featured' );
		$this->assertSame( 0, self::parent_of( $image ), 'Missing post.' );
	}

	/**
	 * The legacy admin batch scan (scan_batch) follows the same rule.
	 */
	public function test_legacy_batch_scan_sets_parents_from_owning_usage_only() {
		$logo    = $this->image( 'logo-legacy.jpg' );
		$sidebar = $this->image( 'sidebar-legacy.jpg' );
		$inline  = $this->image( 'inline-legacy.jpg' );
		update_option( 'site_logo', $logo );
		$other = $this->post( 'Post J', '', [ $logo, $sidebar ] );
		$moved = $this->image( 'moved-legacy.jpg', $other );
		$post  = $this->post( 'Post K', self::image_block( $inline ) . self::image_block( $moved ), [ $logo, $sidebar ] );

		$offset = 0;
		for ( $i = 0; $i < 20; $i++ ) {
			$result = UsedWhereScanner::instance()->scan_batch( $offset, 20 );
			if ( $result['complete'] ) {
				break;
			}
			$offset = (int) $result['processed'];
		}

		$this->assertTrue( $result['complete'] );
		$this->assertContains( get_permalink( $post ), $this->fetched, 'The rendered pages were fetched.' );
		$this->assertSame( 0, self::parent_of( $logo ) );
		$this->assertSame( 0, self::parent_of( $sidebar ) );
		$this->assertSame( $post, self::parent_of( $inline ) );
		$this->assertSame( $other, self::parent_of( $moved ) );
	}

	/**
	 * A front-end visit records a usage, but only an owning usage type sets
	 * a parent.
	 */
	public function test_frontend_usage_attaches_only_for_owning_types() {
		$post     = $this->post( 'Post G' );
		$rendered = $this->image( 'frontend-rendered.jpg' );
		$content  = $this->image( 'frontend-content.jpg' );

		UsedWhereScanner::instance()->record_frontend_usage( $rendered, $post, 'rendered' );
		UsedWhereScanner::instance()->record_frontend_usage( $content, $post, 'content' );

		$this->assertSame( [ $post . ':rendered' ], self::usages_of( $rendered ) );
		$this->assertSame( 0, self::parent_of( $rendered ) );
		$this->assertSame( $post, self::parent_of( $content ) );
	}
}
