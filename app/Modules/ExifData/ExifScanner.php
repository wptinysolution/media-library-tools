<?php
/**
 * EXIF Data availability scanner — analyzes EXIF presence in media library.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\ExifData;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * ExifScanner — scans media library for EXIF data availability.
 */
class ExifScanner {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * WordPress option key for scan status.
	 */
	const SCAN_STATUS_KEY = 'tsmlt_exif_scan_status';

	/**
	 * WordPress option key for EXIF scan results.
	 */
	const SCAN_RESULTS_KEY = 'tsmlt_exif_scan_results';

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Scan a batch of attachments for EXIF data availability.
	 *
	 * @param int $offset     Offset to start scanning from.
	 * @param int $batch_size Number of attachments to scan per batch.
	 *
	 * @return array{processed: int, total: int, complete: bool, with_exif: int, without_exif: int}
	 */
	public function scan_batch( int $offset = 0, int $batch_size = 50 ): array {
		// Get total attachment count.
		$total_result = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->get();
		$total        = (int) ( $total_result[0]['total'] ?? 0 );

		// Get current scan status (accumulated counts from previous batches).
		$status             = get_option( self::SCAN_STATUS_KEY, [] );
		$with_exif_count    = isset( $status['with_exif'] ) ? (int) $status['with_exif'] : 0;
		$without_exif_count = isset( $status['without_exif'] ) ? (int) $status['without_exif'] : 0;

		// Get batch of attachment IDs.
		$batch = Fns::DB()->select( 'ID', 'post_mime_type' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->orderBy( 'ID', 'ASC' )
			->limit( $batch_size )
			->offset( $offset )
			->get();

		if ( empty( $batch ) ) {
			// No more items — return accumulated counts from saved status.
			return [
				'processed'    => $offset,
				'total'        => $total,
				'complete'     => true,
				'with_exif'    => $with_exif_count,
				'without_exif' => $without_exif_count,
			];
		}

		// List of MIME types that support EXIF.
		$supported_mimes = [ 'image/jpeg', 'image/jpg', 'image/tiff', 'image/webp' ];

		// Scan each attachment in the batch.
		foreach ( $batch as $row ) {
			$attachment_id = (int) $row['ID'];
			$mime          = $row['post_mime_type'];

			// Check if MIME type supports EXIF.
			if ( ! in_array( $mime, $supported_mimes, true ) ) {
				$without_exif_count++;
				continue;
			}

			// Get file path.
			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$without_exif_count++;
				continue;
			}

			// Check if PHP EXIF extension is available.
			if ( ! function_exists( 'exif_read_data' ) ) {
				// Can't determine, count as without (safe assumption).
				$without_exif_count++;
				continue;
			}

			// Try to read EXIF data.
			$exif = @exif_read_data( $file_path, null, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( is_array( $exif ) && ! empty( $exif ) ) {
				$with_exif_count++;
			} else {
				$without_exif_count++;
			}
		}

		// Update scan status in options.
		$processed = $offset + count( $batch );
		$status    = [
			'processed'    => $processed,
			'total'        => $total,
			'with_exif'    => $with_exif_count,
			'without_exif' => $without_exif_count,
			'timestamp'    => current_time( 'mysql' ),
		];
		update_option( self::SCAN_STATUS_KEY, $status );

		return [
			'processed'    => $processed,
			'total'        => $total,
			'complete'     => $processed >= $total,
			'with_exif'    => $with_exif_count,
			'without_exif' => $without_exif_count,
		];
	}

	/**
	 * Get current scan status.
	 *
	 * @return array{processed: int, total: int, with_exif: int, without_exif: int, timestamp: string}
	 */
	public function get_scan_status(): array {
		$status = get_option( self::SCAN_STATUS_KEY, [] );

		// Get total attachment count if no scan started yet.
		if ( empty( $status['total'] ) ) {
			$total_result = Fns::DB()->select()
				->count( '*', 'total' )
				->from( 'posts' )
				->where( 'post_type', '=', 'attachment' )
				->andWhere( 'post_status', '=', 'inherit' )
				->get();
			$status['total'] = (int) ( $total_result[0]['total'] ?? 0 );
		}

		return [
			'processed'    => isset( $status['processed'] ) ? (int) $status['processed'] : 0,
			'total'        => isset( $status['total'] ) ? (int) $status['total'] : 0,
			'with_exif'    => isset( $status['with_exif'] ) ? (int) $status['with_exif'] : 0,
			'without_exif' => isset( $status['without_exif'] ) ? (int) $status['without_exif'] : 0,
			'timestamp'    => isset( $status['timestamp'] ) ? $status['timestamp'] : '',
		];
	}

	/**
	 * Clear all scan results.
	 *
	 * @return array{updated: bool, message: string}
	 */
	public function clear_scan(): array {
		delete_option( self::SCAN_STATUS_KEY );
		delete_option( self::SCAN_RESULTS_KEY );

		// Reset the static cache in ExifDataReader.
		ExifDataReader::clear_cache();

		return [
			'updated' => true,
			'message' => esc_html__( 'EXIF scan results cleared.', 'media-library-tools' ),
		];
	}
}
