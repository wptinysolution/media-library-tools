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

		// Reset buffer for this batch.
		$this->usages_buffer = [];

		foreach ( $posts as $post ) {
			$this->detect_usage_in_post( $post );
		}

		// Flush buffer: save usages to post meta and set post_parent.
		$this->flush_usages_buffer();

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
	 * Get attachment ID by its URL.
	 *
	 * @param string $url Attachment URL.
	 *
	 * @return int Attachment ID, or 0 if not found.
	 */
	private function get_attachment_id_by_url( string $url ): int {
		static $cache = [];

		if ( isset( $cache[ $url ] ) ) {
			return $cache[ $url ];
		}

		$result = Fns::DB()->select( 'post_id' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', '_wp_attached_file' )
			->andWhere( 'meta_value', 'LIKE', '%' . basename( $url ) . '%' )
			->limit( 1 )
			->get();

		$attachment_id = ! empty( $result ) ? absint( $result[0]['post_id'] ?? 0 ) : 0;

		if ( ! $attachment_id ) {
			$result = Fns::DB()->select( 'ID' )
				->from( 'posts' )
				->where( 'guid', '=', $url )
				->andWhere( 'post_type', '=', 'attachment' )
				->limit( 1 )
				->get();

			$attachment_id = ! empty( $result ) ? absint( $result[0]['ID'] ?? 0 ) : 0;
		}

		$cache[ $url ] = $attachment_id;

		return $attachment_id;
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
	 * @return void
	 */
	private function clear_all_usage_meta(): void {
		// Get all attachment IDs that have our meta key.
		$attachments = get_posts( [
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => self::META_KEY,
					'compare' => 'EXISTS',
				],
			],
		] );

		foreach ( $attachments as $attachment_id ) {
			delete_post_meta( $attachment_id, self::META_KEY );
			wp_update_post( [
				'ID'          => $attachment_id,
				'post_parent' => 0,
			] );
		}
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
}
