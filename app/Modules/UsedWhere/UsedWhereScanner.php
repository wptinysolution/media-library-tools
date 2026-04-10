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
			'post_status'    => 'publish',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		] );

		// Count total published posts across all public post types.
		$total_count = 0;
		foreach ( $post_types as $pt ) {
			$counts = wp_count_posts( $pt );
			$total_count += (int) ( $counts->publish ?? 0 );
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

		// 2. Images in post content.
		$this->detect_images_in_content( $post->post_content, $post, 'content' );

		// 3. Images in post excerpt.
		if ( ! empty( $post->post_excerpt ) ) {
			$this->detect_images_in_content( $post->post_excerpt, $post, 'excerpt' );
		}

		// 4. Elementor (meta-based).
		$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
		if ( ! empty( $elementor_data ) ) {
			$this->detect_images_in_elementor( $elementor_data, $post );
		}

		// 5. Custom meta fields (if enabled).
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
		if ( ! preg_match_all( '/\/wp-content\/uploads\/([^\s"\'<>]+)/i', $content, $matches ) ) {
			return;
		}

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

		foreach ( $data as $key => $value ) {
			if ( is_numeric( $value ) && in_array( $key, [ 'id', 'image', 'attachment_id' ], true ) ) {
				$attachment_id = absint( $value );
				if ( $attachment_id && 'attachment' === get_post_type( $attachment_id ) ) {
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
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	private function detect_images_in_meta( \WP_Post $post ): void {
		$meta = get_post_meta( $post->ID );
		if ( empty( $meta ) ) {
			return;
		}

		foreach ( $meta as $key => $values ) {
			if ( strpos( $key, '_' ) === 0 ) {
				continue;
			}

			foreach ( (array) $values as $value ) {
				if ( is_numeric( $value ) && 'attachment' === get_post_type( $value ) ) {
					$this->record_usage( absint( $value ), $post, 'meta' );
				} elseif ( is_string( $value ) && strpos( $value, '/wp-content/uploads/' ) !== false ) {
					$attachment_id = $this->get_attachment_id_by_url( $value );
					if ( $attachment_id ) {
						$this->record_usage( $attachment_id, $post, 'meta' );
					}
				}
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

		// 1. Load all _wp_attached_file entries: relative path → post_id.
		$meta_rows = Fns::DB()->select( 'post_id', 'meta_value' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', '_wp_attached_file' )
			->get();

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
			// Also index by relative path for exact matches.
			$this->url_lookup_map[ $rel_path ] = $post_id;
		}

		// 2. Fallback: load GUIDs (full URLs) → ID for non-standard setups.
		$guid_rows = Fns::DB()->select( 'ID', 'guid' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->get();

		foreach ( ( $guid_rows ?: [] ) as $row ) {
			$att_id = absint( $row['ID'] );
			$guid   = $row['guid'] ?? '';
			if ( $att_id && $guid ) {
				// Store full URL so it can be matched directly.
				$this->url_lookup_map[ $guid ] = $att_id;
			}
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
			// 3. Basename match (covers scaled/sized variants like image-300x200.jpg).
			$basename = basename( $rel_path );
			if ( isset( $this->url_lookup_map[ $basename ] ) ) {
				return $this->url_lookup_map[ $basename ];
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
			$type = $usage['usage_type'] ?? 'unknown';
			$by_type[ $type ] = ( $by_type[ $type ] ?? 0 ) + 1;

			$by_post[] = [
				'post_id'    => $usage['post_id'],
				'post_title' => $usage['post_title'] ?? '',
				'post_type'  => $usage['post_type'] ?? '',
				'post_link'  => get_permalink( $usage['post_id'] ),
				'usage_type' => $type,
			];
		}

		$result['by_type'] = $by_type;
		$result['by_post'] = $by_post;

		return $result;
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
		$site_icon_id = absint( get_option( 'site_icon', 0 ) );
		if ( $site_icon_id && 'attachment' === get_post_type( $site_icon_id ) ) {
			$this->record_sitewide_usage( $site_icon_id, 'site_icon' );
		}

		$site_logo_id = absint( get_option( 'site_logo', 0 ) );
		if ( $site_logo_id && 'attachment' === get_post_type( $site_logo_id ) ) {
			$this->record_sitewide_usage( $site_logo_id, 'site_logo' );
		}

		$custom_logo_id = absint( get_theme_mod( 'custom_logo', 0 ) );
		if ( $custom_logo_id && $custom_logo_id !== $site_logo_id && 'attachment' === get_post_type( $custom_logo_id ) ) {
			$this->record_sitewide_usage( $custom_logo_id, 'site_logo' );
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
