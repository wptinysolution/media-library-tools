<?php
/**
 * Regenerate Thumbnails — batch reprocesses attachment metadata and image sizes.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Regenerate;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Handles batch regeneration of WordPress image thumbnail sizes.
 */
class RegenerateThumbnails {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Return the total number of image attachments in the media library.
	 *
	 * @return int
	 */
	public function get_total(): int {
		$result = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_mime_type', 'LIKE', 'image/%' )
			->andWhere( 'post_status', '=', 'inherit' )
			->get();

		return (int) ( $result[0]['total'] ?? 0 );
	}

	/**
	 * Process one batch of image attachments: regenerate thumbnails for all
	 * currently registered sizes, then delete any orphan thumbnail files that
	 * belong to sizes no longer registered in WordPress.
	 *
	 * @param int $offset     Zero-based offset into the full attachment list.
	 * @param int $batch_size Number of attachments to process per call.
	 *
	 * @return array{
	 *     processed:     int,
	 *     total:         int,
	 *     complete:      bool,
	 *     deleted_total: int,
	 *     errors:        array<array{id: int, file: string, error: string}>,
	 *     succeeded:     array<array{id: int, file: string, deleted_sizes: string[]}>
	 * }
	 */
	public function regenerate_batch( int $offset = 0, int $batch_size = 10 ): array {
		$total = $this->get_total();

		// Fetch a batch of image attachment IDs ordered deterministically.
		$rows = Fns::DB()->select( 'ID' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_mime_type', 'LIKE', 'image/%' )
			->andWhere( 'post_status', '=', 'inherit' )
			->orderBy( 'ID', 'ASC' )
			->limit( $batch_size )
			->offset( $offset )
			->get();

		$errors        = [];
		$succeeded     = [];
		$deleted_total = 0;

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'wp_read_video_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$registered = array_keys( wp_get_registered_image_subsizes() );

		foreach ( ( $rows ?: [] ) as $row ) {
			$attachment_id = (int) $row['ID'];
			$file          = get_attached_file( $attachment_id );
			$filename      = $file ? basename( $file ) : "ID:{$attachment_id}";

			if ( ! $file || ! file_exists( $file ) ) {
				$errors[] = [
					'id'    => $attachment_id,
					'file'  => $filename,
					'error' => esc_html__( 'File not found on disk.', 'media-library-tools' ),
				];
				continue;
			}

			// ── Step 1: capture old sizes before regenerating ────────────────
			$old_metadata  = wp_get_attachment_metadata( $attachment_id );
			$old_sizes     = is_array( $old_metadata ) && ! empty( $old_metadata['sizes'] )
				? $old_metadata['sizes']
				: [];

			// ── Step 2: regenerate all registered thumbnail sizes ────────────
			$metadata = wp_generate_attachment_metadata( $attachment_id, $file );

			if ( is_wp_error( $metadata ) ) {
				$errors[] = [
					'id'    => $attachment_id,
					'file'  => $filename,
					'error' => $metadata->get_error_message(),
				];
				continue;
			}

			if ( empty( $metadata ) ) {
				$errors[] = [
					'id'    => $attachment_id,
					'file'  => $filename,
					'error' => esc_html__( 'Could not generate metadata (unsupported format?).', 'media-library-tools' ),
				];
				continue;
			}

			// ── Step 3: delete orphan files for unregistered sizes ───────────
			$upload_dir    = trailingslashit( dirname( $file ) );
			$deleted_sizes = [];

			// New metadata only contains registered sizes, but old sizes may
			// still have files on disk that were never regenerated into new metadata.
			foreach ( $old_sizes as $size_name => $size_data ) {
				if ( in_array( $size_name, $registered, true ) ) {
					continue;
				}
				$thumb_file = $upload_dir . ( $size_data['file'] ?? '' );
				if ( ! empty( $size_data['file'] ) && file_exists( $thumb_file ) ) {
					wp_delete_file( $thumb_file );
					$deleted_sizes[] = $size_name;
					++$deleted_total;
				}
			}

			// ── Step 4: save the clean, registered-only metadata ─────────────
			wp_update_attachment_metadata( $attachment_id, $metadata );

			$succeeded[] = [
				'id'            => $attachment_id,
				'file'          => $filename,
				'deleted_sizes' => $deleted_sizes,
			];
		}

		$processed_count = count( $rows ?: [] );
		$new_offset      = $offset + $processed_count;
		$complete        = $new_offset >= $total || 0 === $processed_count;

		return [
			'processed'     => $new_offset,
			'total'         => $total,
			'complete'      => $complete,
			'deleted_total' => $deleted_total,
			'errors'        => $errors,
			'succeeded'     => $succeeded,
		];
	}
}
