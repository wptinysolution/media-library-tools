<?php
/**
 * Used-Where module — tracks where images are used across the website.
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
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Scan all posts and detect where images (attachments) are used.
	 *
	 * Processes in batches to avoid timeouts.
	 *
	 * @param int $offset Batch offset.
	 * @param int $batch_size Number of posts per batch.
	 *
	 * @return array{processed: int, total: int, complete: bool}
	 */
	public function scan_batch( int $offset = 0, int $batch_size = 20 ): array {
		// Clear old usage records first (only once at offset 0).
		if ( 0 === $offset ) {
			Fns::DB()->delete( 'tsmlt_image_usage' )->execute();
		}

		// Get all published posts.
		$posts = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'post_status'    => 'publish',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		] );

		// Get total published posts + pages.
		$total_count = (int) ( wp_count_posts( 'post' )->publish ?? 0 )
			+ (int) ( wp_count_posts( 'page' )->publish ?? 0 );

		if ( empty( $posts ) ) {
			return [
				'processed' => $offset,
				'total'     => $total_count,
				'complete'  => true,
			];
		}

		foreach ( $posts as $post ) {
			$this->detect_usage_in_post( $post );
		}

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
			$this->record_usage( $featured_id, $post->ID, 'featured', $post->post_type );
		}

		// 2. Images in post content.
		$this->detect_images_in_content( $post->post_content, $post->ID, 'content', $post->post_type );

		// 3. Images in post excerpt.
		if ( ! empty( $post->post_excerpt ) ) {
			$this->detect_images_in_content( $post->post_excerpt, $post->ID, 'excerpt', $post->post_type );
		}

		// 4. Elementor (meta-based).
		$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
		if ( ! empty( $elementor_data ) ) {
			$this->detect_images_in_elementor( $elementor_data, $post->ID, $post->post_type );
		}

		// 5. Custom meta fields (if enabled).
		$options = Fns::get_options();
		if ( ! empty( $options['scan_custom_meta_usage'] ) ) {
			$this->detect_images_in_meta( $post->ID, $post->post_type );
		}
	}

	/**
	 * Detect image attachments in HTML content (posts, pages, excerpts).
	 *
	 * @param string $content Content to search.
	 * @param int    $post_id Post ID.
	 * @param string $type Usage type ('content', 'excerpt', etc.).
	 * @param string $post_type Post type.
	 *
	 * @return void
	 */
	private function detect_images_in_content( string $content, int $post_id, string $type, string $post_type ): void {
		// Find all /wp-content/uploads/ paths in content.
		if ( ! preg_match_all( '/\/wp-content\/uploads\/([^\s"\'<>]+)/i', $content, $matches ) ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$base_url   = trailingslashit( $upload_dir['baseurl'] );

		foreach ( $matches[1] as $relative_path ) {
			$full_url = $base_url . $relative_path;

			// Find attachment by URL.
			$attachment_id = $this->get_attachment_id_by_url( $full_url );
			if ( $attachment_id ) {
				$this->record_usage( $attachment_id, $post_id, $type, $post_type );
			}
		}
	}

	/**
	 * Detect images in Elementor meta data.
	 *
	 * @param string $elementor_data JSON data from Elementor.
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type.
	 *
	 * @return void
	 */
	private function detect_images_in_elementor( string $elementor_data, int $post_id, string $post_type ): void {
		$data = json_decode( $elementor_data, true );
		if ( ! is_array( $data ) ) {
			return;
		}

		$this->extract_attachment_ids_from_array( $data, $post_id, 'elementor', $post_type );
	}

	/**
	 * Recursively extract attachment IDs from nested arrays (Elementor or other JSON data).
	 *
	 * @param array  $data Array to search.
	 * @param int    $post_id Post ID.
	 * @param string $type Usage type.
	 * @param string $post_type Post type.
	 * @param int    $depth Current recursion depth (max 10).
	 *
	 * @return void
	 */
	private function extract_attachment_ids_from_array( array $data, int $post_id, string $type, string $post_type, int $depth = 0 ): void {
		if ( $depth > 10 ) {
			return; // Prevent infinite recursion.
		}

		foreach ( $data as $key => $value ) {
			// Check for numeric ID keys (common in Elementor).
			if ( is_numeric( $value ) && in_array( $key, [ 'id', 'image', 'attachment_id' ], true ) ) {
				$attachment_id = absint( $value );
				if ( $attachment_id && 'attachment' === get_post_type( $attachment_id ) ) {
					$this->record_usage( $attachment_id, $post_id, $type, $post_type );
				}
			}

			// Check for full URLs to attachments.
			if ( is_string( $value ) && strpos( $value, '/wp-content/uploads/' ) !== false ) {
				$attachment_id = $this->get_attachment_id_by_url( $value );
				if ( $attachment_id ) {
					$this->record_usage( $attachment_id, $post_id, $type, $post_type );
				}
			}

			// Recurse into nested arrays.
			if ( is_array( $value ) ) {
				$this->extract_attachment_ids_from_array( $value, $post_id, $type, $post_type, $depth + 1 );
			}
		}
	}

	/**
	 * Detect images in custom post meta fields.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type.
	 *
	 * @return void
	 */
	private function detect_images_in_meta( int $post_id, string $post_type ): void {
		$meta = get_post_meta( $post_id );
		if ( empty( $meta ) ) {
			return;
		}

		foreach ( $meta as $key => $values ) {
			// Skip private meta keys (prefixed with _).
			if ( strpos( $key, '_' ) === 0 ) {
				continue;
			}

			foreach ( (array) $values as $value ) {
				if ( is_numeric( $value ) && 'attachment' === get_post_type( $value ) ) {
					$this->record_usage( absint( $value ), $post_id, 'meta', $post_type );
				} elseif ( is_string( $value ) && strpos( $value, '/wp-content/uploads/' ) !== false ) {
					$attachment_id = $this->get_attachment_id_by_url( $value );
					if ( $attachment_id ) {
						$this->record_usage( $attachment_id, $post_id, 'meta', $post_type );
					}
				}
			}
		}
	}

	/**
	 * Record an image usage instance in the database.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param int    $post_id Post ID where the image is used.
	 * @param string $usage_type Type of usage ('content', 'featured', 'elementor', 'meta').
	 * @param string $post_type Post type.
	 *
	 * @return void
	 */
	private function record_usage( int $attachment_id, int $post_id, string $usage_type, string $post_type ): void {
		// Avoid duplicate records for the same attachment+post combo.
		$existing = Fns::DB()->select( 'id' )
			->from( 'tsmlt_image_usage' )
			->where( 'attachment_id', '=', $attachment_id )
			->andWhere( 'post_id', '=', $post_id )
			->andWhere( 'usage_type', '=', $usage_type )
			->get();

		if ( ! empty( $existing ) ) {
			return;
		}

		Fns::DB()->insert( 'tsmlt_image_usage', [
			[
				'attachment_id' => $attachment_id,
				'post_id'       => $post_id,
				'usage_type'    => $usage_type,
				'post_type'     => $post_type,
				'detected_at'   => current_time( 'mysql' ),
			],
		] )->execute();
	}

	/**
	 * Get attachment ID by its URL.
	 *
	 * Optimized: uses a static cache to avoid repeated DB queries.
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

		// Search by URL in post_meta (attachment metadata).
		$result = Fns::DB()->select( 'post_id' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', '_wp_attached_file' )
			->andWhere( 'meta_value', 'LIKE', '%' . basename( $url ) . '%' )
			->limit( 1 )
			->get();

		$attachment_id = ! empty( $result ) ? absint( $result[0]['post_id'] ?? 0 ) : 0;

		if ( ! $attachment_id ) {
			// Fallback: search by GUID.
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
	 * Get usage statistics for a specific attachment.
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

		$usages = Fns::DB()->select( '*' )
			->from( 'tsmlt_image_usage' )
			->where( 'attachment_id', '=', $attachment_id )
			->get();

		if ( empty( $usages ) ) {
			return $result;
		}

		$result['total_usage'] = count( $usages );

		// Group by usage type.
		$by_type = [];
		$by_post = [];

		foreach ( $usages as $usage ) {
			$type = $usage['usage_type'] ?? 'unknown';
			$by_type[ $type ] = ( $by_type[ $type ] ?? 0 ) + 1;

			$post_id = $usage['post_id'];
			$post    = get_post( $post_id );
			if ( $post ) {
				$by_post[] = [
					'post_id'   => $post_id,
					'post_title' => $post->post_title,
					'post_type' => $post->post_type,
					'post_link' => get_permalink( $post_id ),
					'usage_type' => $type,
				];
			}
		}

		$result['by_type'] = $by_type;
		$result['by_post'] = $by_post;

		return $result;
	}

	/**
	 * Get scan status.
	 *
	 * @return array{scanned: int, total: int, status: string}
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
	 * Clear all scan results.
	 *
	 * @return array
	 */
	public function clear_scan(): array {
		Fns::DB()->delete( 'tsmlt_image_usage' )->execute();
		delete_option( 'tsmlt_used_where_scan_status' );

		return [
			'updated' => true,
			'message' => esc_html__( 'Scan cleared successfully.', 'media-library-tools' ),
		];
	}

	/**
	 * Record frontend image usage (for passive tracking on page visits).
	 *
	 * Public wrapper for record_usage() to be called from frontend hooks.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param int    $post_id Post ID.
	 * @param string $usage_type Usage type.
	 *
	 * @return void
	 */
	public function record_frontend_usage( int $attachment_id, int $post_id, string $usage_type ): void {
		$post = get_post( $post_id );
		if ( $post ) {
			$this->record_usage( $attachment_id, $post_id, $usage_type, $post->post_type );
		}
	}

	/**
	 * Detect images in post content (frontend tracking helper).
	 *
	 * @param string $content Post content.
	 * @param int    $post_id Post ID.
	 * @param string $type Usage type.
	 *
	 * @return void
	 */
	public function detect_content_images( string $content, int $post_id, string $type ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$this->detect_images_in_content( $content, $post_id, $type, $post->post_type );
	}
}
