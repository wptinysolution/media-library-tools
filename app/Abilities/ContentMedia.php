<?php
/**
 * Media associated with a post, page or product.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use WP_Post;
use WP_Query;

/**
 * Finds a post, page or WooCommerce product the current user may read (by
 * ID, title or a URL of this site) and the media associated with it.
 *
 * Shared by `tsmlt/search-media` and the Pro CSV export of pages and posts.
 * A URL is never fetched: it must be on this site's own host and is resolved
 * with WordPress's url_to_postid(). Media are found through what is saved
 * with the content: attached ("Uploaded to"), featured image, WooCommerce
 * product gallery; in the post content, core image, gallery, cover and
 * media-and-text blocks (nested ones too), wp-image-N classes, [gallery ids]
 * shortcodes and images whose src is a file in this site's uploads; and,
 * for pages built with Elementor, the media fields of its saved data
 * (`_elementor_data`, read as data: Elementor need not be active and
 * nothing is rendered). An ID saved with a URL is used only when it is that
 * file; otherwise a URL of this site's uploads is resolved to its
 * attachment, and other URLs are ignored. Not found: images output by the
 * theme, widgets, shortcodes or other page builders, CSS set outside
 * Elementor's media fields, and files of other sites or not in the Media
 * Library. Only IDs are returned: callers check that each is readable
 * media.
 */
class ContentMedia {

	/**
	 * Content types that can be searched (product only when WooCommerce
	 * registers it).
	 *
	 * @var string[]
	 */
	const CONTENT_TYPES = [ 'post', 'page', 'product' ];

	/**
	 * Most content items listed when a title matches several.
	 *
	 * @var int
	 */
	const MAX_CANDIDATES = 10;

	/**
	 * Longest URL accepted.
	 *
	 * @var int
	 */
	const MAX_URL_LENGTH = 2000;

	/**
	 * WooCommerce's product gallery meta key (comma-separated attachment IDs).
	 *
	 * @var string
	 */
	const PRODUCT_GALLERY_META_KEY = '_product_image_gallery';

	/**
	 * Every way media can be associated with content, as associated()
	 * reports it: attached ("Uploaded to"), featured image, product gallery,
	 * the post content, the Elementor design. Tool output schemas list these.
	 *
	 * @var string[]
	 */
	const ASSOCIATIONS = [ 'attached', 'featured', 'gallery', 'content', 'elementor' ];

	/**
	 * Elementor's saved document data (JSON).
	 *
	 * @var string
	 */
	const ELEMENTOR_DATA_META_KEY = '_elementor_data';

	/**
	 * Elementor's edit mode; "builder" when the page is built with it.
	 *
	 * @var string
	 */
	const ELEMENTOR_MODE_META_KEY = '_elementor_edit_mode';

	/**
	 * Largest Elementor data read, in bytes.
	 *
	 * @var int
	 */
	const MAX_ELEMENTOR_BYTES = 5242880;

	/**
	 * Deepest nesting followed in blocks and Elementor data.
	 *
	 * @var int
	 */
	const MAX_DEPTH = 40;

	/**
	 * Most distinct upload URLs resolved to attachments per content.
	 *
	 * @var int
	 */
	const MAX_URL_LOOKUPS = 200;

	/**
	 * Content types that exist on this site.
	 *
	 * @return string[]
	 */
	public static function types(): array {
		return array_values( array_filter( self::CONTENT_TYPES, 'post_type_exists' ) );
	}

	/**
	 * A post, page or product the current user may read, or null (missing,
	 * another type, trashed, an auto-draft or not readable, alike).
	 *
	 * @param int $content_id Content ID.
	 *
	 * @return WP_Post|null
	 */
	public static function readable( int $content_id ): ?WP_Post {
		$post = get_post( $content_id );
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::types(), true ) || in_array( $post->post_status, [ 'trash', 'auto-draft' ], true ) || ! current_user_can( 'read_post', $post->ID ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * Whether a found post is readable content (see readable()).
	 *
	 * @param mixed $post Post.
	 *
	 * @return bool
	 */
	public static function is_readable( $post ): bool {
		return $post instanceof WP_Post && null !== self::readable( (int) $post->ID );
	}

	/**
	 * Readable posts, pages and products with this title: exact titles when
	 * there are any, otherwise titles containing it; at most
	 * MAX_CANDIDATES + 1, so several are known to be several.
	 *
	 * @param string $title Title.
	 *
	 * @return WP_Post[]
	 */
	public static function by_title( string $title ): array {
		$base = [
			'post_type'              => self::types(),
			'post_status'            => 'any',
			'posts_per_page'         => self::MAX_CANDIDATES + 1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];
		foreach ( [
			[ 'title' => $title ],
			[
				's'              => $title,
				'search_columns' => [ 'post_title' ],
			],
		] as $match ) {
			$posts = array_values( array_filter( ( new WP_Query( $base + $match ) )->posts, [ self::class, 'is_readable' ] ) );
			if ( $posts ) {
				return $posts;
			}
		}
		return [];
	}

	/**
	 * The readable post, page or product a URL of this site points to, or
	 * null. Only http(s) URLs on this site's host (and port), under its home
	 * path and without credentials are resolved, with url_to_postid(); the
	 * home page resolves to the static front page. Nothing is fetched.
	 *
	 * @param string $url URL.
	 *
	 * @return WP_Post|null
	 */
	public static function from_url( string $url ): ?WP_Post {
		$url = trim( $url );
		if ( '' === $url || strlen( $url ) > self::MAX_URL_LENGTH || preg_match( '/[\x00-\x20\x7F]/', $url ) ) {
			return null;
		}
		$parts = wp_parse_url( $url );
		$home  = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $parts ) || ! is_array( $home ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] )
			|| ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), [ 'http', 'https' ], true )
			|| strtolower( $parts['host'] ) !== strtolower( (string) ( $home['host'] ?? '' ) )
			|| (int) ( $parts['port'] ?? 0 ) !== (int) ( $home['port'] ?? 0 ) ) {
			return null;
		}
		$home_path = trailingslashit( (string) ( $home['path'] ?? '/' ) );
		$path      = trailingslashit( (string) ( $parts['path'] ?? '/' ) );
		if ( 0 !== strpos( $path, $home_path ) ) {
			return null;
		}

		$id = url_to_postid( $url );
		if ( ! $id && $path === $home_path && empty( $parts['query'] ) && 'page' === get_option( 'show_on_front' ) ) {
			$id = (int) get_option( 'page_on_front' );
		}
		return $id ? self::readable( (int) $id ) : null;
	}

	/**
	 * Media associated with a post, page or product, by how (see the class
	 * description). IDs only, in ascending order.
	 *
	 * @param WP_Post $post Content.
	 *
	 * @return array<int, string[]> Attachment ID => associations (attached,
	 *                              featured, gallery, content, elementor).
	 */
	public static function associated( WP_Post $post ): array {
		$found = [];

		$attached = new WP_Query(
			[
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_parent'            => $post->ID,
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);
		self::add_found( $found, $attached->posts, 'attached' );
		self::add_found( $found, get_post_thumbnail_id( $post ), 'featured' );
		if ( 'product' === $post->post_type ) {
			self::add_found( $found, explode( ',', (string) get_post_meta( $post->ID, self::PRODUCT_GALLERY_META_KEY, true ) ), 'gallery' );
		}

		$lookups    = [];
		$content    = (string) $post->post_content;
		$block_urls = [];
		self::add_found( $found, self::block_media_ids( parse_blocks( $content ), 0, $block_urls ), 'content' );
		foreach ( $block_urls as $block_url ) {
			self::add_found( $found, self::local_attachment_id( $block_url, $lookups ), 'content' );
		}
		if ( preg_match_all( '/\bwp-image-(\d+)\b/', $content, $classes ) ) {
			self::add_found( $found, $classes[1], 'content' );
		}
		if ( preg_match_all( '/\[gallery\b[^\]]*\bids\s*=\s*["\']?([\d,\s]+)/i', $content, $galleries ) ) {
			foreach ( $galleries[1] as $list ) {
				self::add_found( $found, explode( ',', $list ), 'content' );
			}
		}
		if ( preg_match_all( '/<img\b[^>]*?\bsrc\s*=\s*["\']([^"\']+)["\']/i', $content, $sources ) ) {
			foreach ( $sources[1] as $source ) {
				self::add_found( $found, self::local_attachment_id( html_entity_decode( $source ), $lookups ), 'content' );
			}
		}

		if ( 'builder' === get_post_meta( $post->ID, self::ELEMENTOR_MODE_META_KEY, true ) ) {
			self::add_found( $found, self::elementor_media_ids( $post->ID, $lookups ), 'elementor' );
		}

		ksort( $found );
		return $found;
	}

	/**
	 * Media IDs in the saved data of a page built with Elementor: every
	 * media field ({id, url}) and list of them (galleries) in the settings
	 * of every element, nested sections, columns and containers included.
	 * Read as data only; unreadable, oversized or malformed data gives none.
	 *
	 * @param int   $post_id Content ID.
	 * @param array $lookups URL lookups so far (see local_attachment_id()).
	 *
	 * @return int[]
	 */
	private static function elementor_media_ids( int $post_id, array &$lookups ): array {
		$raw = get_post_meta( $post_id, self::ELEMENTOR_DATA_META_KEY, true );
		if ( is_string( $raw ) ) {
			if ( '' === $raw || strlen( $raw ) > self::MAX_ELEMENTOR_BYTES ) {
				return [];
			}
			$raw = json_decode( $raw, true, self::MAX_DEPTH * 4 );
		}
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$ids = [];
		self::elementor_walk( $raw, $ids, $lookups, 0 );
		return $ids;
	}

	/**
	 * Collect media IDs from Elementor data, depth first.
	 *
	 * @param array $node    Elements, an element, its settings or a value.
	 * @param int[] $ids     IDs found, updated.
	 * @param array $lookups URL lookups so far.
	 * @param int   $depth   Nesting depth.
	 *
	 * @return void
	 */
	private static function elementor_walk( array $node, array &$ids, array &$lookups, int $depth ): void {
		if ( $depth > self::MAX_DEPTH * 4 ) {
			return;
		}
		if ( array_key_exists( 'url', $node ) && ( array_key_exists( 'id', $node ) || 'library' === ( $node['source'] ?? '' ) ) ) {
			$id = self::media_field_id( $node['id'] ?? 0, $node['url'], $lookups );
			if ( $id ) {
				$ids[] = $id;
			}
			return;
		}
		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				self::elementor_walk( $value, $ids, $lookups, $depth + 1 );
			}
		}
	}

	/**
	 * The attachment of a saved media field: its ID when it is the file of
	 * the saved URL (or no URL is saved), else the attachment of the URL when
	 * it is a file of this site's uploads, else 0. An ID kept from another
	 * site (an imported page) is thus not taken for an unrelated attachment.
	 *
	 * @param mixed $id      Saved ID.
	 * @param mixed $url     Saved URL.
	 * @param array $lookups URL lookups so far.
	 *
	 * @return int
	 */
	private static function media_field_id( $id, $url, array &$lookups ): int {
		$id  = is_numeric( $id ) ? absint( $id ) : 0;
		$url = is_string( $url ) ? trim( $url ) : '';
		if ( $id && ( '' === $url || self::same_file( $id, $url ) ) ) {
			return $id;
		}
		return '' === $url ? 0 : self::local_attachment_id( $url, $lookups );
	}

	/**
	 * Whether a URL names the file of an attachment (size suffixes and
	 * "-scaled" ignored).
	 *
	 * @param int    $id  Attachment ID.
	 * @param string $url URL.
	 *
	 * @return bool
	 */
	private static function same_file( int $id, string $url ): bool {
		$file = get_post_meta( $id, '_wp_attached_file', true );
		if ( ! is_string( $file ) || '' === $file ) {
			return false;
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return '' !== $path && self::base_name( wp_basename( $file ) ) === self::base_name( wp_basename( $path ) );
	}

	/**
	 * A file name without an image size suffix (-300x200) or "-scaled".
	 *
	 * @param string $name File name.
	 *
	 * @return string
	 */
	private static function base_name( string $name ): string {
		return strtolower( (string) preg_replace( '/(-\d+x\d+|-scaled)(?=\.[a-z0-9]+$)/i', '', $name ) );
	}

	/**
	 * The attachment whose file a URL of this site's uploads names (a sized
	 * copy counts as its original), or 0. Other URLs are not looked up and
	 * nothing is fetched; at most MAX_URL_LOOKUPS distinct URLs per content.
	 *
	 * @param string $url     URL.
	 * @param array  $lookups URL => attachment ID, shared per content.
	 *
	 * @return int
	 */
	private static function local_attachment_id( string $url, array &$lookups ): int {
		$url = (string) strtok( trim( $url ), '?#' );
		if ( '' === $url ) {
			return 0;
		}
		if ( array_key_exists( $url, $lookups ) ) {
			return $lookups[ $url ];
		}
		if ( count( $lookups ) >= self::MAX_URL_LOOKUPS ) {
			return 0;
		}
		$uploads = wp_get_upload_dir();
		$base    = wp_parse_url( (string) ( $uploads['baseurl'] ?? '' ) );
		$parts   = wp_parse_url( $url );
		$id      = 0;
		if ( is_array( $base ) && is_array( $parts ) && ! empty( $parts['host'] ) && ! isset( $parts['user'] )
			&& strtolower( $parts['host'] ) === strtolower( (string) ( $base['host'] ?? '' ) )
			&& (int) ( $parts['port'] ?? 0 ) === (int) ( $base['port'] ?? 0 )
			&& 0 === strpos( (string) ( $parts['path'] ?? '' ), trailingslashit( (string) ( $base['path'] ?? '' ) ) ) ) {
			$id = attachment_url_to_postid( $url );
			if ( ! $id ) {
				$original = preg_replace( '/(-\d+x\d+|-scaled)(?=\.[a-z0-9]+$)/i', '', $url );
				$id       = $original !== $url ? attachment_url_to_postid( (string) $original ) : 0;
			}
		}
		$lookups[ $url ] = (int) $id;
		return (int) $id;
	}

	/**
	 * A post, page or product as tool output shows it.
	 *
	 * @param WP_Post $post Content.
	 *
	 * @return array{id: int, title: string, type: string, date: string}
	 */
	public static function present( WP_Post $post ): array {
		return [
			'id'    => (int) $post->ID,
			'title' => Fns::prepare_text_for_json( get_the_title( $post ) ),
			'type'  => (string) $post->post_type,
			'date'  => (string) get_post_time( 'c', true, $post ),
		];
	}

	/**
	 * Output schema of present().
	 *
	 * @return array
	 */
	public static function schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'id', 'title', 'type', 'date' ],
			'properties'           => [
				'id'    => [ 'type' => 'integer' ],
				'title' => [ 'type' => 'string' ],
				'type'  => [
					'type' => 'string',
					'enum' => self::CONTENT_TYPES,
				],
				'date'  => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * Record attachment IDs as associated in one way (each way once per ID).
	 *
	 * @param array  $found Attachment ID => associations, updated.
	 * @param mixed  $ids   An ID or IDs.
	 * @param string $how   Association.
	 *
	 * @return void
	 */
	private static function add_found( array &$found, $ids, string $how ): void {
		foreach ( (array) $ids as $id ) {
			$id = absint( $id );
			if ( $id && ! in_array( $how, $found[ $id ] ?? [], true ) ) {
				$found[ $id ][] = $how;
			}
		}
	}

	/**
	 * Media IDs in core image, gallery, cover and media-and-text blocks,
	 * nested blocks included; the image URLs of such blocks saved without
	 * an ID are collected for resolving.
	 *
	 * @param array    $blocks Parsed blocks.
	 * @param int      $depth  Nesting depth.
	 * @param string[] $urls   Image URLs without an ID, updated.
	 *
	 * @return int[]
	 */
	private static function block_media_ids( array $blocks, int $depth, array &$urls ): array {
		$ids = [];
		if ( $depth > self::MAX_DEPTH ) {
			return $ids;
		}
		foreach ( $blocks as $block ) {
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
			switch ( $block['blockName'] ?? '' ) {
				case 'core/image':
				case 'core/cover':
					$ids[] = $attrs['id'] ?? 0;
					if ( empty( $attrs['id'] ) && isset( $attrs['url'] ) && is_string( $attrs['url'] ) ) {
						$urls[] = $attrs['url'];
					}
					break;
				case 'core/media-text':
					$ids[] = $attrs['mediaId'] ?? 0;
					if ( empty( $attrs['mediaId'] ) && isset( $attrs['mediaUrl'] ) && is_string( $attrs['mediaUrl'] ) ) {
						$urls[] = $attrs['mediaUrl'];
					}
					break;
				case 'core/gallery':
					$ids = array_merge( $ids, isset( $attrs['ids'] ) && is_array( $attrs['ids'] ) ? $attrs['ids'] : [] );
					break;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$ids = array_merge( $ids, self::block_media_ids( $block['innerBlocks'], $depth + 1, $urls ) );
			}
		}
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}
}
