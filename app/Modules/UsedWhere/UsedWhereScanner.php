<?php
/**
 * Used-Where module — tracks where images are used across the website.
 *
 * Stores usage data as attachment post meta (`_tsmlt_image_usages`) and sets
 * `post_parent` on each attachment for the "Attached Post" column.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\UsedWhere;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * UsedWhereScanner
 */
class UsedWhereScanner {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Meta key for storing image usage data on attachments.
	 */
	const META_KEY = '_tsmlt_image_usages';

	/**
	 * Buffer: accumulates usages per attachment_id during a batch scan.
	 *
	 * @var array<int, array>
	 */
	private $usages_buffer = [];

	/**
	 * Site-wide URL→attachment_id lookup map, built once per batch.
	 * Keys are relative paths (after /uploads/), values are attachment IDs.
	 *
	 * @var array<string, int>|null
	 */
	private $url_lookup_map = null;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Scan all posts and detect where images (attachments) are used.
	 *
	 * Processes in batches to avoid timeouts. Stores results as post meta
	 * on each attachment and sets post_parent.
	 *
	 * @param int $offset Batch offset.
	 * @param int $batch_size Number of posts per batch.
	 *
	 * @return array{processed: int, total: int, complete: bool}
	 */
	public function scan_batch( int $offset = 0, int $batch_size = 20 ): array {
		// Clear old usage meta on first batch only.
		if ( 0 === $offset ) {
			$this->clear_all_usage_meta();
		}

		// Scan all public post types (post, page, product, portfolio, etc.).
		$post_types = get_post_types( [ 'public' => true ], 'names' );
		unset( $post_types['attachment'] );
		$post_types = array_values( $post_types );

		$posts = get_posts( [
			'post_type'      => $post_types,
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'orderby'        => 'ID',
			'order'          => 'ASC',
		] );

		// Count total posts across all public post types (all relevant statuses).
		$total_count = 0;
		foreach ( $post_types as $pt ) {
			$counts = wp_count_posts( $pt );
			$total_count += (int) ( $counts->publish ?? 0 );
			$total_count += (int) ( $counts->draft ?? 0 );
			$total_count += (int) ( $counts->pending ?? 0 );
			$total_count += (int) ( $counts->private ?? 0 );
			$total_count += (int) ( $counts->future ?? 0 );
		}

		if ( empty( $posts ) ) {
			return [
				'processed' => $offset,
				'total'     => $total_count,
				'complete'  => true,
			];
		}

		// Build the site-wide URL→ID lookup map once per batch (2 queries total
		// instead of 1–2 queries per URL found in content).
		$this->build_url_lookup_map();

		// Reset buffer for this batch.
		$this->usages_buffer = [];

		// On first batch: detect site-wide image usage (favicon, site logo).
		if ( 0 === $offset ) {
			$this->detect_sitewide_usage();
		}

		foreach ( $posts as $post ) {
			$this->detect_usage_in_post( $post );
		}

		// Flush buffer: save usages to post meta and set post_parent.
		$this->flush_usages_buffer();

		// Free the map; it will be rebuilt on the next batch call.
		$this->url_lookup_map = null;

		return [
			'processed' => $offset + count( $posts ),
			'total'     => $total_count,
			'complete'  => ( $offset + $batch_size ) >= $total_count,
		];
	}

	/**
	 * Detect where images are used in a specific post.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	private function detect_usage_in_post( \WP_Post $post ): void {
		// 1. Featured image.
		$featured_id = get_post_thumbnail_id( $post->ID );
		if ( $featured_id ) {
			$this->record_usage( $featured_id, $post, 'featured' );
		}

		// 2. Images in post content (URLs + Gutenberg block IDs).
		$this->detect_images_in_content( $post->post_content, $post, 'content' );

		// 3. Images in post excerpt.
		if ( ! empty( $post->post_excerpt ) ) {
			$this->detect_images_in_content( $post->post_excerpt, $post, 'excerpt' );
		}

		// 4. WooCommerce product gallery (comma-separated IDs in _product_image_gallery).
		$this->detect_woo_gallery( $post );

		// 5. Elementor (meta-based).
		$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
		if ( ! empty( $elementor_data ) ) {
			$this->detect_images_in_elementor( $elementor_data, $post );
		}

		// 6. Other page builders (Beaver Builder, Divi, Brizy, etc.).
		$this->detect_images_in_builders( $post );

		// 7. Custom meta fields (if enabled) — includes _prefixed keys with serialized data.
		$options = Fns::get_options();
		if ( ! empty( $options['scan_custom_meta_usage'] ) ) {
			$this->detect_images_in_meta( $post );
		}
	}

	/**
	 * Detect image attachments in HTML content.
	 *
	 * @param string   $content Content to search.
	 * @param \WP_Post $post Post object.
	 * @param string   $type Usage type ('content', 'excerpt', etc.).
	 *
	 * @return void
	 */
	private function detect_images_in_content( string $content, \WP_Post $post, string $type ): void {
		if ( empty( $content ) ) {
			return;
		}

		// Build a set of known attachment IDs for quick validation.
		$known_ids = array_flip( array_values( $this->url_lookup_map ?? [] ) );

		// 1. Gutenberg block IDs: <!-- wp:image {"id":449} --> or wp:media-text, wp:cover, etc.
		if ( preg_match_all( '/<!--\s+wp:\S+\s+(\{[^}]+\})\s+-->/i', $content, $block_matches ) ) {
			foreach ( $block_matches[1] as $json_str ) {
				$block_attrs = json_decode( $json_str, true );
				if ( is_array( $block_attrs ) && ! empty( $block_attrs['id'] ) ) {
					$block_id = absint( $block_attrs['id'] );
					if ( $block_id && isset( $known_ids[ $block_id ] ) ) {
						$this->record_usage( $block_id, $post, $type );
					}
				}
				// wp:gallery stores ids as array.
				if ( is_array( $block_attrs ) && ! empty( $block_attrs['ids'] ) && is_array( $block_attrs['ids'] ) ) {
					foreach ( $block_attrs['ids'] as $gallery_id ) {
						$gallery_id = absint( $gallery_id );
						if ( $gallery_id && isset( $known_ids[ $gallery_id ] ) ) {
							$this->record_usage( $gallery_id, $post, $type );
						}
					}
				}
			}
		}

		// 2. wp-image-{ID} CSS class (both Gutenberg and Classic editor).
		if ( preg_match_all( '/wp-image-(\d+)/i', $content, $class_matches ) ) {
			foreach ( $class_matches[1] as $class_id ) {
				$class_id = absint( $class_id );
				if ( $class_id && isset( $known_ids[ $class_id ] ) ) {
					$this->record_usage( $class_id, $post, $type );
				}
			}
		}

		// 3. Image URLs in content (/wp-content/uploads/...).
		if ( preg_match_all( '/\/wp-content\/uploads\/([^\s"\'<>]+)/i', $content, $matches ) ) {
			$upload_dir = wp_upload_dir();
			$base_url   = trailingslashit( $upload_dir['baseurl'] );

			foreach ( $matches[1] as $relative_path ) {
				$full_url      = $base_url . $relative_path;
				$attachment_id = $this->get_attachment_id_by_url( $full_url );
				if ( $attachment_id ) {
					$this->record_usage( $attachment_id, $post, $type );
				}
			}
		}
	}

	/**
	 * Detect images in Elementor meta data.
	 *
	 * @param string   $elementor_data JSON data from Elementor.
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	private function detect_images_in_elementor( string $elementor_data, \WP_Post $post ): void {
		$data = json_decode( $elementor_data, true );
		if ( ! is_array( $data ) ) {
			return;
		}

		$this->extract_attachment_ids_from_array( $data, $post, 'elementor' );
	}

	/**
	 * Detect WooCommerce product gallery images.
	 *
	 * The _product_image_gallery meta stores comma-separated attachment IDs.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	private function detect_woo_gallery( \WP_Post $post ): void {
		if ( 'product' !== $post->post_type ) {
			return;
		}

		$gallery = get_post_meta( $post->ID, '_product_image_gallery', true );
		if ( empty( $gallery ) ) {
			return;
		}

		$known_ids = array_flip( array_values( $this->url_lookup_map ?? [] ) );

		$ids = explode( ',', $gallery );
		foreach ( $ids as $id ) {
			$id = absint( trim( $id ) );
			if ( $id && isset( $known_ids[ $id ] ) ) {
				$this->record_usage( $id, $post, 'woo_gallery' );
			}
		}
	}

	/**
	 * Detect images stored by other page builders.
	 *
	 * Checks known meta keys used by Beaver Builder, Divi, Brizy, and
	 * other popular builders. Uses the same recursive array search
	 * as Elementor detection.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	private function detect_images_in_builders( \WP_Post $post ): void {
		$builder_keys = [
			'_fl_builder_data'       => 'beaver_builder',   // Beaver Builder.
			'_et_builder_settings'   => 'divi',             // Divi (JSON).
			'brizy_post_uid'         => 'brizy',            // Brizy stores data in content, but check meta too.
			'_wpb_shortcodes_custom_css' => 'wpbakery',     // WPBakery (CSS may have bg images).
		];

		foreach ( $builder_keys as $meta_key => $builder_name ) {
			$meta_value = get_post_meta( $post->ID, $meta_key, true );
			if ( empty( $meta_value ) ) {
				continue;
			}

			if ( is_string( $meta_value ) ) {
				// Try JSON decode first.
				$decoded = json_decode( $meta_value, true );
				if ( is_array( $decoded ) ) {
					$this->extract_attachment_ids_from_array( $decoded, $post, $builder_name );
					continue;
				}
				// Try unserialized.
				$unserialized = maybe_unserialize( $meta_value );
				if ( is_array( $unserialized ) ) {
					$this->extract_attachment_ids_from_array( $unserialized, $post, $builder_name );
					continue;
				}
				// Search for upload URLs in raw string.
				$this->detect_images_in_content( $meta_value, $post, $builder_name );
			} elseif ( is_array( $meta_value ) ) {
				$this->extract_attachment_ids_from_array( $meta_value, $post, $builder_name );
			}
		}
	}

	/**
	 * Recursively extract attachment IDs from nested arrays.
	 *
	 * @param array    $data Array to search.
	 * @param \WP_Post $post Post object.
	 * @param string   $type Usage type.
	 * @param int      $depth Current recursion depth (max 10).
	 *
	 * @return void
	 */
	private function extract_attachment_ids_from_array( array $data, \WP_Post $post, string $type, int $depth = 0 ): void {
		if ( $depth > 10 ) {
			return;
		}

		// Use the lookup map to verify attachment IDs without DB queries.
		$attachment_ids = array_flip( array_values( $this->url_lookup_map ?? [] ) );

		foreach ( $data as $key => $value ) {
			if ( is_numeric( $value ) && in_array( $key, [ 'id', 'image', 'attachment_id' ], true ) ) {
				$attachment_id = absint( $value );
				if ( $attachment_id && isset( $attachment_ids[ $attachment_id ] ) ) {
					$this->record_usage( $attachment_id, $post, $type );
				}
			}

			if ( is_string( $value ) && strpos( $value, '/wp-content/uploads/' ) !== false ) {
				$attachment_id = $this->get_attachment_id_by_url( $value );
				if ( $attachment_id ) {
					$this->record_usage( $attachment_id, $post, $type );
				}
			}

			if ( is_array( $value ) ) {
				$this->extract_attachment_ids_from_array( $value, $post, $type, $depth + 1 );
			}
		}
	}

	/**
	 * Detect images in custom post meta fields.
	 *
	 * Scans all meta keys including _prefixed ones. For _prefixed keys, only
	 * checks serialized arrays/JSON (where ACF, WooCommerce, etc. store IDs).
	 * For non-prefixed keys, also checks plain numeric values and URLs.
	 *
	 * Uses the preloaded lookup map — zero DB queries per value.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	private function detect_images_in_meta( \WP_Post $post ): void {
		$meta = get_post_meta( $post->ID );
		if ( empty( $meta ) ) {
			return;
		}

		// Build a set of known attachment IDs from the lookup map for O(1) checks.
		$attachment_ids = array_flip( array_values( $this->url_lookup_map ?? [] ) );

		// Keys already handled by dedicated methods — skip to avoid duplicates.
		$skip_keys = [
			'_thumbnail_id',
			'_elementor_data',
			'_product_image_gallery',
			'_fl_builder_data',
			'_et_builder_settings',
			'brizy_post_uid',
			'_wpb_shortcodes_custom_css',
			'_tsmlt_image_usages',
			'_tsmlt_usage_tracked',
		];

		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, $skip_keys, true ) ) {
				continue;
			}

			$is_private = strpos( $key, '_' ) === 0;

			foreach ( (array) $values as $value ) {
				// For _prefixed keys: only scan serialized arrays and JSON (not plain values).
				if ( $is_private ) {
					$this->scan_meta_value_deep( $value, $post, $attachment_ids );
					continue;
				}

				// For non-prefixed keys: check plain values too.
				if ( is_numeric( $value ) ) {
					$id = absint( $value );
					if ( $id && isset( $attachment_ids[ $id ] ) ) {
						$this->record_usage( $id, $post, 'meta' );
					}
				} elseif ( is_string( $value ) ) {
					$this->scan_meta_value_deep( $value, $post, $attachment_ids );
				}
			}
		}
	}

	/**
	 * Deeply scan a meta value for attachment IDs and URLs.
	 *
	 * Handles serialized PHP arrays, JSON strings, comma-separated IDs,
	 * and plain URLs. Used for both _prefixed and non-prefixed meta keys.
	 *
	 * @param mixed    $value          Meta value to scan.
	 * @param \WP_Post $post           Post object.
	 * @param array    $attachment_ids Set of known attachment IDs.
	 *
	 * @return void
	 */
	private function scan_meta_value_deep( $value, \WP_Post $post, array $attachment_ids ): void {
		if ( ! is_string( $value ) || strlen( $value ) < 2 ) {
			return;
		}

		// 1. Try unserialized array.
		$unserialized = maybe_unserialize( $value );
		if ( is_array( $unserialized ) ) {
			$this->extract_attachment_ids_from_array( $unserialized, $post, 'meta' );
			return;
		}

		// 2. Try JSON.
		if ( '{' === $value[0] || '[' === $value[0] ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$this->extract_attachment_ids_from_array( $decoded, $post, 'meta' );
				return;
			}
		}

		// 3. Comma-separated IDs (e.g. "123,456,789").
		if ( preg_match( '/^\d+(?:,\s*\d+)+$/', $value ) ) {
			$ids = explode( ',', $value );
			foreach ( $ids as $id ) {
				$id = absint( trim( $id ) );
				if ( $id && isset( $attachment_ids[ $id ] ) ) {
					$this->record_usage( $id, $post, 'meta' );
				}
			}
			return;
		}

		// 4. URL containing /wp-content/uploads/.
		if ( strpos( $value, '/wp-content/uploads/' ) !== false ) {
			$attachment_id = $this->get_attachment_id_by_url( $value );
			if ( $attachment_id ) {
				$this->record_usage( $attachment_id, $post, 'meta' );
			}
		}
	}

	/**
	 * Buffer a usage record. Deduplicated by attachment+post+type.
	 *
	 * @param int      $attachment_id Attachment ID.
	 * @param \WP_Post $post Post where the image is used.
	 * @param string   $usage_type Type of usage.
	 *
	 * @return void
	 */
	private function record_usage( int $attachment_id, \WP_Post $post, string $usage_type ): void {
		$key = $attachment_id . ':' . $post->ID . ':' . $usage_type;

		if ( ! isset( $this->usages_buffer[ $attachment_id ] ) ) {
			$this->usages_buffer[ $attachment_id ] = [];
		}

		// Avoid duplicates within the buffer.
		if ( isset( $this->usages_buffer[ $attachment_id ][ $key ] ) ) {
			return;
		}

		$this->usages_buffer[ $attachment_id ][ $key ] = [
			'post_id'    => $post->ID,
			'post_title' => $post->post_title,
			'post_type'  => $post->post_type,
			'usage_type' => $usage_type,
		];
	}

	/**
	 * Flush the usages buffer to post meta and set post_parent.
	 *
	 * @return void
	 */
	private function flush_usages_buffer(): void {
		foreach ( $this->usages_buffer as $attachment_id => $entries ) {
			$new_usages = array_values( $entries );

			// Merge with any existing meta (from previous batches).
			$existing = get_post_meta( $attachment_id, self::META_KEY, true );
			if ( ! empty( $existing ) && is_array( $existing ) ) {
				// Deduplicate by key.
				$existing_keys = [];
				foreach ( $existing as $item ) {
					$existing_keys[ $item['post_id'] . ':' . $item['usage_type'] ] = true;
				}
				foreach ( $new_usages as $item ) {
					$k = $item['post_id'] . ':' . $item['usage_type'];
					if ( ! isset( $existing_keys[ $k ] ) ) {
						$existing[] = $item;
					}
				}
				$new_usages = $existing;
			}

			update_post_meta( $attachment_id, self::META_KEY, $new_usages );

			// Set post_parent if not already set.
			$current_parent = (int) get_post_field( 'post_parent', $attachment_id );
			if ( ! $current_parent && ! empty( $new_usages[0]['post_id'] ) ) {
				wp_update_post( [
					'ID'          => $attachment_id,
					'post_parent' => (int) $new_usages[0]['post_id'],
				] );
			}
		}

		$this->usages_buffer = [];
	}

	/**
	 * Build a site-wide relative-path → attachment_id lookup map.
	 *
	 * Loads all _wp_attached_file meta values (relative paths stored by WP, e.g.
	 * "2024/01/photo.jpg") in a single query and builds a map keyed by the
	 * basename (photo.jpg) pointing to the attachment ID. A second query loads
	 * full GUIDs as a fallback for unusual attachment configurations.
	 *
	 * Called once per scan_batch() — eliminates per-URL DB queries.
	 *
	 * @return void
	 */
	private function build_url_lookup_map(): void {
		if ( null !== $this->url_lookup_map ) {
			return;
		}

		$this->url_lookup_map = [];

		// Single query: load all _wp_attached_file entries (relative path → post_id).
		// This covers all standard WordPress attachments — one query instead of two.
		$meta_rows = Fns::DB()->select( 'post_id', 'meta_value' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', '_wp_attached_file' )
			->get();

		$upload_dir = wp_upload_dir();
		$base_url   = trailingslashit( $upload_dir['baseurl'] );

		foreach ( ( $meta_rows ?: [] ) as $row ) {
			$post_id   = absint( $row['post_id'] );
			$rel_path  = $row['meta_value'] ?? '';
			if ( ! $post_id || ! $rel_path ) {
				continue;
			}
			// Index by basename for quick lookup (handles scaled/sized filenames too).
			$basename = basename( $rel_path );
			if ( ! isset( $this->url_lookup_map[ $basename ] ) ) {
				$this->url_lookup_map[ $basename ] = $post_id;
			}
			// Index by relative path for exact matches.
			$this->url_lookup_map[ $rel_path ] = $post_id;
			// Index by full URL for direct GUID-style matches.
			$this->url_lookup_map[ $base_url . $rel_path ] = $post_id;
		}
	}

	/**
	 * Get attachment ID by its URL using the preloaded lookup map.
	 *
	 * Falls back to basename lookup for scaled/sized variants (e.g., image-300x200.jpg).
	 *
	 * @param string $url Attachment URL or partial path.
	 *
	 * @return int Attachment ID, or 0 if not found.
	 */
	private function get_attachment_id_by_url( string $url ): int {
		if ( null === $this->url_lookup_map ) {
			// Safety fallback if called outside a batch context.
			$this->build_url_lookup_map();
		}

		// 1. Exact GUID match.
		if ( isset( $this->url_lookup_map[ $url ] ) ) {
			return $this->url_lookup_map[ $url ];
		}

		// 2. Extract the relative path after /uploads/ and try that.
		$pos = strpos( $url, '/uploads/' );
		if ( false !== $pos ) {
			$rel_path = ltrim( substr( $url, $pos + strlen( '/uploads/' ) ), '/' );
			if ( isset( $this->url_lookup_map[ $rel_path ] ) ) {
				return $this->url_lookup_map[ $rel_path ];
			}

			// 3. Basename match.
			$basename = basename( $rel_path );
			if ( isset( $this->url_lookup_map[ $basename ] ) ) {
				return $this->url_lookup_map[ $basename ];
			}

			// 4. Strip WP size suffix (e.g. image-300x200.jpg → image.jpg)
			//    to match the original attachment file.
			$stripped = preg_replace( '/-\d+x\d+(\.[a-zA-Z]+)$/', '$1', $basename );
			if ( $stripped !== $basename && isset( $this->url_lookup_map[ $stripped ] ) ) {
				return $this->url_lookup_map[ $stripped ];
			}

			// 5. Strip size suffix from relative path (e.g. 2026/04/image-300x200.jpg → 2026/04/image.jpg).
			$stripped_rel = preg_replace( '/-\d+x\d+(\.[a-zA-Z]+)$/', '$1', $rel_path );
			if ( $stripped_rel !== $rel_path && isset( $this->url_lookup_map[ $stripped_rel ] ) ) {
				return $this->url_lookup_map[ $stripped_rel ];
			}
		}

		return 0;
	}

	/**
	 * Get usage statistics for a specific attachment from post meta.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array{total_usage: int, by_type: array, by_post: array}
	 */
	public function get_usage_stats( int $attachment_id ): array {
		$result = [
			'total_usage' => 0,
			'by_type'     => [],
			'by_post'     => [],
		];

		$usages = get_post_meta( $attachment_id, self::META_KEY, true );
		if ( empty( $usages ) || ! is_array( $usages ) ) {
			return $result;
		}

		$result['total_usage'] = count( $usages );

		$by_type = [];
		$by_post = [];

		foreach ( $usages as $usage ) {
			$type    = $usage['usage_type'] ?? 'unknown';
			$post_id = $usage['post_id'] ?? 0;
			$by_type[ $type ] = ( $by_type[ $type ] ?? 0 ) + 1;

			$by_post[] = [
				'post_id'    => $post_id,
				'post_title' => $usage['post_title'] ?? '',
				'post_type'  => $usage['post_type'] ?? '',
				'post_link'  => $post_id ? get_permalink( $post_id ) : '',
				'usage_type' => $type,
			];
		}

		$result['by_type'] = $by_type;
		$result['by_post'] = $by_post;

		return $result;
	}

	/**
	 * Scan a single post for image usage on save.
	 *
	 * Removes old usage records for this post from affected attachments only,
	 * then re-detects and records current usages.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function scan_single_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || 'attachment' === $post->post_type ) {
			return;
		}

		$this->remove_post_usages( $post_id );

		// Build the lookup map and detect usages in this post.
		$this->build_url_lookup_map();
		$this->usages_buffer = [];
		$this->detect_usage_in_post( $post );
		$this->flush_usages_buffer();
		$this->url_lookup_map = null;
	}

	/**
	 * Remove old usage records for a specific post from all affected attachments.
	 *
	 * Uses a targeted LIKE query to find only attachments that reference this post_id,
	 * instead of loading ALL usage meta rows.
	 *
	 * WordPress stores arrays via `update_post_meta()` as PHP serialized strings.
	 * The post_id inside looks like: `s:7:"post_id";i:123;`
	 *
	 * @param int $post_id Post ID to remove.
	 *
	 * @return void
	 */
	private function remove_post_usages( int $post_id ): void {
		global $wpdb;

		// Match serialized format: s:7:"post_id";i:{ID};
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$affected_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
				self::META_KEY,
				'%' . $wpdb->esc_like( '"post_id";i:' . $post_id . ';' ) . '%'
			),
			ARRAY_A
		);

		foreach ( ( $affected_rows ?: [] ) as $row ) {
			$att_id   = absint( $row['post_id'] );
			$existing = maybe_unserialize( $row['meta_value'] );
			if ( ! is_array( $existing ) ) {
				continue;
			}

			$filtered = array_filter(
				$existing,
				fn( $item ) => (int) ( $item['post_id'] ?? 0 ) !== $post_id
			);

			if ( count( $filtered ) !== count( $existing ) ) {
				if ( empty( $filtered ) ) {
					delete_post_meta( $att_id, self::META_KEY );
				} else {
					update_post_meta( $att_id, self::META_KEY, array_values( $filtered ) );
				}
			}
		}
	}

	/**
	 * Get scan status.
	 *
	 * @return array{scanned: int, total: int, complete: bool, last_update: string}
	 */
	public function get_scan_status(): array {
		$last_scan = get_option( 'tsmlt_used_where_scan_status', [] );

		return [
			'scanned'     => $last_scan['processed'] ?? 0,
			'total'       => $last_scan['total'] ?? 0,
			'complete'    => $last_scan['complete'] ?? false,
			'last_update' => $last_scan['timestamp'] ?? '',
		];
	}

	/**
	 * Clear all scan results — removes meta from all attachments and resets post_parent.
	 *
	 * @return array
	 */
	public function clear_scan(): array {
		$this->clear_all_usage_meta();
		delete_option( 'tsmlt_used_where_scan_status' );

		// Clear frontend visit tracking flags so posts get re-scanned on next visit.
		Fns::DB()->delete( 'postmeta' )
			->where( 'meta_key', '=', '_tsmlt_usage_tracked' )
			->execute();

		return [
			'updated' => true,
			'message' => esc_html__( 'Scan cleared successfully.', 'media-library-tools' ),
		];
	}

	/**
	 * Delete _tsmlt_image_usages meta from all attachments and reset post_parent to 0.
	 *
	 * Optimized: uses two bulk queries instead of loading all attachment IDs into
	 * PHP and looping. The query builder does not support JOINs in UPDATE, so we:
	 * 1. Fetch the affected attachment IDs in one SELECT.
	 * 2. Bulk-delete the meta rows in one DELETE.
	 * 3. If there are affected IDs, bulk-reset post_parent via one UPDATE with whereIn.
	 *
	 * @return void
	 */
	private function clear_all_usage_meta(): void {
		// 1. Find which attachment IDs have our meta key.
		$affected_rows = Fns::DB()->select( 'post_id' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', self::META_KEY )
			->get();

		// 2. Bulk-delete all meta rows for our key.
		Fns::DB()->delete( 'postmeta' )
			->where( 'meta_key', '=', self::META_KEY )
			->execute();

		if ( empty( $affected_rows ) ) {
			return;
		}

		// 3. Collect affected attachment IDs and bulk-reset post_parent.
		$affected_ids = array_unique(
			array_map( fn( $r ) => absint( $r['post_id'] ), $affected_rows )
		);

		Fns::DB()->update( 'posts', [ 'post_parent' => 0 ] )
			->whereIn( 'ID', ...$affected_ids )
			->execute();

		// Also reset the URL lookup map to avoid stale data.
		$this->url_lookup_map = null;
	}

	/**
	 * Record frontend image usage (passive tracking).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param int    $post_id Post ID.
	 * @param string $usage_type Usage type.
	 *
	 * @return void
	 */
	public function record_frontend_usage( int $attachment_id, int $post_id, string $usage_type ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$existing = get_post_meta( $attachment_id, self::META_KEY, true );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		// Check for duplicate.
		$key = $post_id . ':' . $usage_type;
		foreach ( $existing as $item ) {
			if ( ( $item['post_id'] . ':' . $item['usage_type'] ) === $key ) {
				return;
			}
		}

		$existing[] = [
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'post_type'  => $post->post_type,
			'usage_type' => $usage_type,
		];

		update_post_meta( $attachment_id, self::META_KEY, $existing );

		// Set post_parent if not set.
		$current_parent = (int) get_post_field( 'post_parent', $attachment_id );
		if ( ! $current_parent ) {
			wp_update_post( [
				'ID'          => $attachment_id,
				'post_parent' => $post_id,
			] );
		}
	}

	/**
	 * Detect site-wide image usage (favicon, site logo).
	 *
	 * Checks WordPress site settings:
	 * - site_icon: site favicon
	 * - site_logo: block theme logo
	 * - custom_logo: classic theme logo
	 *
	 * Skips duplicates (e.g., if custom_logo and site_logo point to same ID).
	 *
	 * @return void
	 */
	private function detect_sitewide_usage(): void {
		$known_ids = array_flip( array_values( $this->url_lookup_map ?? [] ) );

		// 1. Site icon (favicon).
		$site_icon_id = absint( get_option( 'site_icon', 0 ) );
		if ( $site_icon_id && isset( $known_ids[ $site_icon_id ] ) ) {
			$this->record_sitewide_usage( $site_icon_id, 'site_icon' );
		}

		// 2. Site logo (block theme).
		$site_logo_id = absint( get_option( 'site_logo', 0 ) );
		if ( $site_logo_id && isset( $known_ids[ $site_logo_id ] ) ) {
			$this->record_sitewide_usage( $site_logo_id, 'site_logo' );
		}

		// 3. Custom logo (classic theme).
		$custom_logo_id = absint( get_theme_mod( 'custom_logo', 0 ) );
		if ( $custom_logo_id && $custom_logo_id !== $site_logo_id && isset( $known_ids[ $custom_logo_id ] ) ) {
			$this->record_sitewide_usage( $custom_logo_id, 'site_logo' );
		}

		// 4. Header image (customizer).
		$header_image_data = get_custom_header();
		if ( ! empty( $header_image_data->attachment_id ) ) {
			$header_id = absint( $header_image_data->attachment_id );
			if ( $header_id && isset( $known_ids[ $header_id ] ) ) {
				$this->record_sitewide_usage( $header_id, 'header_image' );
			}
		}

		// 5. Background image (customizer).
		$bg_image_id = absint( get_theme_mod( 'background_image_thumb_id', 0 ) );
		if ( ! $bg_image_id ) {
			// Try to resolve from URL.
			$bg_url = get_theme_mod( 'background_image', '' );
			if ( $bg_url ) {
				$bg_image_id = $this->get_attachment_id_by_url( $bg_url );
			}
		}
		if ( $bg_image_id && isset( $known_ids[ $bg_image_id ] ) ) {
			$this->record_sitewide_usage( $bg_image_id, 'background_image' );
		}

		// 6. Navigation menu images (Menu Image plugin, etc.).
		$this->detect_nav_menu_images( $known_ids );

		// 7. Widget images (scan active widget options for upload URLs/IDs).
		$this->detect_widget_images( $known_ids );
	}

	/**
	 * Detect images used in navigation menus.
	 *
	 * @param array $known_ids Set of known attachment IDs.
	 *
	 * @return void
	 */
	private function detect_nav_menu_images( array $known_ids ): void {
		$nav_menus = wp_get_nav_menus();
		if ( empty( $nav_menus ) ) {
			return;
		}

		foreach ( $nav_menus as $menu ) {
			$menu_items = wp_get_nav_menu_items( $menu->term_id );
			if ( empty( $menu_items ) ) {
				continue;
			}
			foreach ( $menu_items as $item ) {
				// Menu Image plugin stores thumbnail ID in _menu_item_image_id or _thumbnail_id.
				$img_id = absint( get_post_meta( $item->ID, '_menu_item_image_id', true ) );
				if ( ! $img_id ) {
					$img_id = absint( get_post_meta( $item->ID, '_thumbnail_id', true ) );
				}
				if ( $img_id && isset( $known_ids[ $img_id ] ) ) {
					$this->record_sitewide_usage( $img_id, 'nav_menu' );
				}
			}
		}
	}

	/**
	 * Detect images used in active widgets.
	 *
	 * Scans widget option values for upload URLs.
	 *
	 * @param array $known_ids Set of known attachment IDs.
	 *
	 * @return void
	 */
	private function detect_widget_images( array $known_ids ): void {
		$sidebars = get_option( 'sidebars_widgets', [] );
		if ( empty( $sidebars ) || ! is_array( $sidebars ) ) {
			return;
		}

		// Collect all active widget IDs.
		$active_widgets = [];
		foreach ( $sidebars as $sidebar_id => $widgets ) {
			if ( 'wp_inactive_widgets' === $sidebar_id || ! is_array( $widgets ) ) {
				continue;
			}
			foreach ( $widgets as $widget_id ) {
				// Extract widget type: e.g. "media_image-2" → "media_image".
				$type = preg_replace( '/-\d+$/', '', $widget_id );
				$active_widgets[ $type ][] = $widget_id;
			}
		}

		// Check widget options for known image-related widgets.
		foreach ( $active_widgets as $type => $widget_ids ) {
			$option = get_option( 'widget_' . $type, [] );
			if ( empty( $option ) || ! is_array( $option ) ) {
				continue;
			}

			foreach ( $option as $instance ) {
				if ( ! is_array( $instance ) ) {
					continue;
				}
				// Check attachment_id field (Media Image, Media Gallery widgets).
				if ( ! empty( $instance['attachment_id'] ) ) {
					$id = absint( $instance['attachment_id'] );
					if ( $id && isset( $known_ids[ $id ] ) ) {
						$this->record_sitewide_usage( $id, 'widget' );
					}
				}
				// Check ids field (Gallery widget).
				if ( ! empty( $instance['ids'] ) && is_string( $instance['ids'] ) ) {
					$ids = explode( ',', $instance['ids'] );
					foreach ( $ids as $id ) {
						$id = absint( trim( $id ) );
						if ( $id && isset( $known_ids[ $id ] ) ) {
							$this->record_sitewide_usage( $id, 'widget' );
						}
					}
				}
				// Check for upload URLs in text/HTML content fields.
				foreach ( [ 'text', 'content', 'url' ] as $field ) {
					if ( ! empty( $instance[ $field ] ) && is_string( $instance[ $field ] ) && strpos( $instance[ $field ], '/wp-content/uploads/' ) !== false ) {
						$att_id = $this->get_attachment_id_by_url( $instance[ $field ] );
						if ( $att_id ) {
							$this->record_sitewide_usage( $att_id, 'widget' );
						}
					}
				}
			}
		}
	}

	/**
	 * Scan the fully rendered HTML output of a page to detect all image usages.
	 *
	 * Captures every image URL from the entire <html>...</html> output, including
	 * header, footer, sidebars, widgets, hardcoded images, and inline CSS backgrounds.
	 *
	 * @param string $html Full rendered HTML of the page.
	 * @param int    $post_id The current post ID.
	 *
	 * @return void
	 */
	public function scan_rendered_html( string $html, int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || 'attachment' === $post->post_type ) {
			return;
		}

		$this->build_url_lookup_map();
		$this->usages_buffer = [];

		// 1. Extract all image URLs from <img> src and srcset attributes.
		if ( preg_match_all( '/<img\s[^>]*>/is', $html, $img_matches ) ) {
			foreach ( $img_matches[0] as $img_tag ) {
				// src attribute.
				if ( preg_match( '/\bsrc=["\']([^"\']+)/i', $img_tag, $src_match ) ) {
					$this->match_url_to_attachment( $src_match[1], $post, 'rendered' );
				}
				// srcset attribute (multiple URLs).
				if ( preg_match( '/\bsrcset=["\']([^"\']+)/i', $img_tag, $srcset_match ) ) {
					$srcset_parts = explode( ',', $srcset_match[1] );
					foreach ( $srcset_parts as $part ) {
						$url = trim( explode( ' ', trim( $part ) )[0] );
						if ( $url ) {
							$this->match_url_to_attachment( $url, $post, 'rendered' );
						}
					}
				}
			}
		}

		// 2. Extract image URLs from CSS background-image: url(...).
		if ( preg_match_all( '/url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i', $html, $bg_matches ) ) {
			foreach ( $bg_matches[1] as $bg_url ) {
				$this->match_url_to_attachment( $bg_url, $post, 'rendered' );
			}
		}

		// 3. Extract image URLs from <source> tags (picture element, video poster).
		if ( preg_match_all( '/<source\s[^>]*srcset=["\']([^"\']+)/i', $html, $source_matches ) ) {
			foreach ( $source_matches[1] as $srcset_val ) {
				$srcset_parts = explode( ',', $srcset_val );
				foreach ( $srcset_parts as $part ) {
					$url = trim( explode( ' ', trim( $part ) )[0] );
					if ( $url ) {
						$this->match_url_to_attachment( $url, $post, 'rendered' );
					}
				}
			}
		}

		// 4. Extract from <a> href linking to uploads (downloadable images, lightbox, etc.).
		if ( preg_match_all( '/\/wp-content\/uploads\/([^\s"\'<>]+\.(?:jpg|jpeg|png|gif|webp|svg|bmp|ico))/i', $html, $url_matches ) ) {
			$upload_dir = wp_upload_dir();
			$base_url   = trailingslashit( $upload_dir['baseurl'] );
			foreach ( array_unique( $url_matches[1] ) as $relative_path ) {
				$full_url      = $base_url . $relative_path;
				$attachment_id = $this->get_attachment_id_by_url( $full_url );
				if ( $attachment_id ) {
					$this->record_usage( $attachment_id, $post, 'rendered' );
				}
			}
		}

		$this->flush_usages_buffer();
		$this->url_lookup_map = null;
	}

	/**
	 * Try to match a URL to an attachment and record usage.
	 *
	 * @param string   $url URL to match.
	 * @param \WP_Post $post Post object.
	 * @param string   $usage_type Usage type.
	 *
	 * @return void
	 */
	private function match_url_to_attachment( string $url, \WP_Post $post, string $usage_type ): void {
		if ( strpos( $url, '/wp-content/uploads/' ) === false ) {
			return;
		}

		$attachment_id = $this->get_attachment_id_by_url( $url );
		if ( $attachment_id ) {
			$this->record_usage( $attachment_id, $post, $usage_type );
		}
	}

	/**
	 * Record a site-wide usage entry (favicon, logo, etc.).
	 *
	 * Similar to record_usage() but for non-post contexts. Uses post_id=0
	 * since there is no WP_Post associated with site settings.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $usage_type Type of site-wide usage.
	 *
	 * @return void
	 */
	private function record_sitewide_usage( int $attachment_id, string $usage_type ): void {
		$key = $attachment_id . ':0:' . $usage_type;

		if ( ! isset( $this->usages_buffer[ $attachment_id ] ) ) {
			$this->usages_buffer[ $attachment_id ] = [];
		}

		if ( isset( $this->usages_buffer[ $attachment_id ][ $key ] ) ) {
			return;
		}

		$this->usages_buffer[ $attachment_id ][ $key ] = [
			'post_id'    => 0,
			'post_title' => esc_html__( 'Site Settings', 'media-library-tools' ),
			'post_type'  => 'site_settings',
			'usage_type' => $usage_type,
		];
	}
}
