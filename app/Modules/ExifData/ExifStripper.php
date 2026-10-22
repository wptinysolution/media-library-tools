<?php
/**
 * EXIF Stripper module — removes EXIF metadata from images (single image only).
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\ExifData;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * ExifStripper — strips EXIF metadata from single images.
 */
class ExifStripper {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Strip EXIF metadata from a single image.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function strip_exif_from_attachment( int $attachment_id ): array {
		// Get file path.
		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'Image file not found on server.', 'media-library-tools' ),
			];
		}

		// Get MIME type: the attached file's when the stored one is not JPEG
		// (WordPress keeps `image/heic` for HEIC uploads it converts to JPEG).
		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime_type, [ 'image/jpeg', 'image/jpg' ], true ) ) {
			$mime_type = (string) wp_get_image_mime( $file_path );
		}

		// Only JPEG supported (best compatibility with GD library).
		if ( ! in_array( $mime_type, [ 'image/jpeg', 'image/jpg' ], true ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'EXIF stripping is only supported for JPEG images.', 'media-library-tools' ),
			];
		}

		// Check if file is writable.
		if ( ! wp_is_writable( $file_path ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'Image file is not writable. Check file permissions.', 'media-library-tools' ),
			];
		}

		// Check if GD library is available.
		if ( ! function_exists( 'imagecreatefromjpeg' ) || ! function_exists( 'imagejpeg' ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'PHP GD library is not available on this server.', 'media-library-tools' ),
			];
		}

		// Create backup before stripping (safety measure).
		$backup_path = $file_path . '.backup';
		if ( ! copy( $file_path, $backup_path ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'Failed to create image backup.', 'media-library-tools' ),
			];
		}

		// Load image from file.
		$image = @imagecreatefromjpeg( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $image ) {
			// Restore backup.
			wp_delete_file( $backup_path );
			return [
				'success' => false,
				'message' => esc_html__( 'Failed to load image. The file may be corrupted.', 'media-library-tools' ),
			];
		}

		// Save image back to file (GD functions automatically strip EXIF).
		// Quality set to 90 to maintain visual quality while removing metadata.
		$result = @imagejpeg( $image, $file_path, 90 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		// GD images are objects freed automatically from PHP 8.0; the call is deprecated from 8.5.
		if ( PHP_VERSION_ID < 80000 ) {
			imagedestroy( $image ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated -- Required on PHP 7.4; skipped on 8.0+ where it is a no-op.
		}

		if ( ! $result ) {
			// Restore backup.
			@copy( $backup_path, $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			wp_delete_file( $backup_path );
			return [
				'success' => false,
				'message' => esc_html__( 'Failed to save image after stripping EXIF.', 'media-library-tools' ),
			];
		}

		// Clean up backup.
		wp_delete_file( $backup_path );

		// The file no longer has any EXIF, so values saved by the EXIF editor
		// (which readers overlay on the file's EXIF) would only be stale.
		delete_post_meta( $attachment_id, '_tsmlt_exif_meta' );

		// Clear the EXIF cache.
		ExifDataReader::clear_cache();

		return [
			'success' => true,
			'message' => esc_html__( 'EXIF metadata stripped successfully.', 'media-library-tools' ),
		];
	}

	/**
	 * Check if an image has strippable EXIF data.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array{has_exif: bool, mime_type: string, supported: bool}
	 */
	public function check_strippable_exif( int $attachment_id ): array {
		$mime_type = get_post_mime_type( $attachment_id );
		$file_path = get_attached_file( $attachment_id );

		// Check if file exists.
		$file_exists = $file_path && file_exists( $file_path );

		// Check if JPEG, by the attached file when the stored type differs.
		$is_jpeg = in_array( $mime_type, [ 'image/jpeg', 'image/jpg' ], true )
			|| ( $file_exists && 'image/jpeg' === wp_get_image_mime( $file_path ) );

		// Try to read EXIF data.
		$has_exif = false;
		if ( $file_exists && $is_jpeg && function_exists( 'exif_read_data' ) ) {
			$exif = @exif_read_data( $file_path, null, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$has_exif = is_array( $exif ) && ! empty( $exif );
		}

		return [
			'has_exif'  => $has_exif,
			'mime_type' => $mime_type,
			'supported' => $is_jpeg && $file_exists,
		];
	}
}
