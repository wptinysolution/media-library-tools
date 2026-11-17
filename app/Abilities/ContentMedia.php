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
 * with WordPress's url_to_postid(). Media are found through relationships
 * WordPress records: attached ("Uploaded to"), featured image, WooCommerce
 * product gallery, and media inserted in the content with core image,
 * gallery, cover or media-and-text blocks, wp-image-N classes or [gallery
 * ids] shortcodes. Media used only by URL, as CSS backgrounds, by page
 * builders, widgets or the theme, or hosted elsewhere are not found. Only
 * IDs are returned: callers check that each is readable media.
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
	 *                              featured, gallery, content).
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

		$content = (string) $post->post_content;
		self::add_found( $found, self::block_media_ids( parse_blocks( $content ) ), 'content' );
		if ( preg_match_all( '/\bwp-image-(\d+)\b/', $content, $classes ) ) {
			self::add_found( $found, $classes[1], 'content' );
		}
		if ( preg_match_all( '/\[gallery\b[^\]]*\bids\s*=\s*["\']?([\d,\s]+)/i', $content, $galleries ) ) {
			foreach ( $galleries[1] as $list ) {
				self::add_found( $found, explode( ',', $list ), 'content' );
			}
		}

		ksort( $found );
		return $found;
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
	 * nested blocks included.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param int   $depth  Nesting depth.
	 *
	 * @return int[]
	 */
	private static function block_media_ids( array $blocks, int $depth = 0 ): array {
		$ids = [];
		if ( $depth > 20 ) {
			return $ids;
		}
		foreach ( $blocks as $block ) {
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
			switch ( $block['blockName'] ?? '' ) {
				case 'core/image':
				case 'core/cover':
					$ids[] = $attrs['id'] ?? 0;
					break;
				case 'core/media-text':
					$ids[] = $attrs['mediaId'] ?? 0;
					break;
				case 'core/gallery':
					$ids = array_merge( $ids, isset( $attrs['ids'] ) && is_array( $attrs['ids'] ) ? $attrs['ids'] : [] );
					break;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$ids = array_merge( $ids, self::block_media_ids( $block['innerBlocks'], $depth + 1 ) );
			}
		}
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}
}
