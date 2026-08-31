<?php
/**
 * Restores original images from the compression backup tree.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Puts backed-up originals back in place.
 *
 * Restoring is the inverse of compression and follows the same discipline: a
 * backup is validated before it is trusted, and the live file is only replaced
 * once a good copy is confirmed. Backups are left on disk afterwards so a
 * restore can be repeated.
 */
class RestoreManager {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Restore an attachment's original files.
	 *
	 * Restores the full-size image plus every generated size that has a backup.
	 * Sizes without a backup are reported as skipped rather than failing the
	 * operation — thumbnails regenerated after compression legitimately have
	 * no original to return to.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return array|WP_Error
	 */
	public function restore( int $attachment_id ) {
		$access = CompressionAccess::instance();

		if ( ! $access->can_restore_originals() ) {
			return new WP_Error(
				'tsmlt_compression_forbidden',
				esc_html__( 'Restoring original images is a Pro feature.', 'media-library-tools' )
			);
		}

		$processor = AttachmentProcessor::instance();
		$valid     = $processor->validate_attachment( $attachment_id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$backup   = BackupManager::instance();
		$metadata = CompressionMetadata::instance();
		$file     = get_attached_file( $attachment_id );

		$relative_full = $backup->to_relative_path( $file );

		if ( is_wp_error( $relative_full ) ) {
			return $relative_full;
		}

		if ( ! $backup->backup_exists( $relative_full ) ) {
			return new WP_Error(
				'tsmlt_compression_backup_missing',
				esc_html__( 'No backup is available for this image.', 'media-library-tools' )
			);
		}

		$mime_type = (string) get_post_mime_type( $attachment_id );
		$restored  = [];
		$skipped   = [];

		// ── Full-size image ──────────────────────────────────────────────────
		$result = $this->restore_file( $relative_full, $file, $mime_type );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$restored[] = 'full';

		// ── Generated sizes ──────────────────────────────────────────────────
		$base_dir  = trailingslashit( dirname( $file ) );
		$subdir    = ltrim( dirname( $relative_full ), '.' );
		$subdir    = '' === $subdir ? '' : trailingslashit( $subdir );
		$meta_data = wp_get_attachment_metadata( $attachment_id );

		if ( is_array( $meta_data ) && ! empty( $meta_data['sizes'] ) && is_array( $meta_data['sizes'] ) ) {
			foreach ( $meta_data['sizes'] as $size_name => $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}

				$filename      = basename( (string) $size['file'] );
				$relative_size = $subdir . $filename;

				if ( ! $backup->backup_exists( $relative_size ) ) {
					$skipped[] = (string) $size_name;
					continue;
				}

				$size_path   = $base_dir . $filename;
				$size_mime   = wp_check_filetype( $size_path );
				$size_mime   = ! empty( $size_mime['type'] ) ? (string) $size_mime['type'] : $mime_type;
				$size_result = $this->restore_file( $relative_size, $size_path, $size_mime );

				if ( is_wp_error( $size_result ) ) {
					$skipped[] = (string) $size_name;
					continue;
				}

				$restored[] = (string) $size_name;
			}
		}

		// Restoring changes file bytes, not dimensions, so only the cached
		// filesize needs refreshing.
		$this->refresh_metadata_filesize( $attachment_id );
		clean_post_cache( $attachment_id );

		// The attachment is back to its original bytes, so its compression
		// record no longer describes reality and is removed. The backups stay
		// on disk, so a later compression run can still be undone.
		$metadata->delete( $attachment_id );

		return [
			'attachment_id' => $attachment_id,
			'status'        => 'restored',
			'restored'      => $restored,
			'skipped'       => $skipped,
			'message'       => esc_html__( 'Original image restored.', 'media-library-tools' ),
		];
	}

	/**
	 * Copy one backed-up file back over its live counterpart.
	 *
	 * The backup is validated and staged into a temporary file first, so a
	 * corrupt or unreadable backup can never destroy the current image.
	 *
	 * @param string $relative_path Uploads-relative path of the backup.
	 * @param string $destination   Absolute path of the live file to replace.
	 * @param string $mime_type     Expected MIME type.
	 *
	 * @return true|WP_Error
	 */
	private function restore_file( string $relative_path, string $destination, string $mime_type ) {
		$backup      = BackupManager::instance();
		$manager     = CompressionManager::instance();
		$backup_path = $backup->get_backup_path( $relative_path );

		if ( is_wp_error( $backup_path ) ) {
			return $backup_path;
		}

		if ( ! file_exists( $backup_path ) ) {
			return new WP_Error(
				'tsmlt_compression_backup_missing',
				esc_html__( 'The backup file is missing.', 'media-library-tools' )
			);
		}

		// Confirm the backup itself is a sound image before relying on it.
		$validated = $manager->validate_image_file( $backup_path, $mime_type );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$directory = dirname( $destination );

		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return new WP_Error(
				'tsmlt_compression_restore_failed',
				esc_html__( 'The original image location is not writable.', 'media-library-tools' )
			);
		}

		$temp_file = wp_tempnam( basename( $destination ), $directory );

		if ( ! $temp_file ) {
			return new WP_Error(
				'tsmlt_compression_temp_failed',
				esc_html__( 'A temporary file could not be created.', 'media-library-tools' )
			);
		}

		if ( ! copy( $backup_path, $temp_file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Direct copy; WP_Filesystem requires credentials unavailable during AJAX.
			$manager->delete_temp_file( $temp_file );

			return new WP_Error(
				'tsmlt_compression_restore_failed',
				esc_html__( 'The backup could not be read.', 'media-library-tools' )
			);
		}

		$validated = $manager->validate_image_file( $temp_file, $mime_type );

		if ( is_wp_error( $validated ) ) {
			$manager->delete_temp_file( $temp_file );

			return $validated;
		}

		$perms = @fileperms( $destination ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Falls back to the WP default below.
		$perms = false !== $perms ? $perms & 0777 : ( defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 );

		if ( ! @rename( $temp_file, $destination ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- Atomic same-directory replacement; WP_Filesystem has no atomic equivalent. Failure is reported as WP_Error.
			$manager->delete_temp_file( $temp_file );

			return new WP_Error(
				'tsmlt_compression_restore_failed',
				esc_html__( 'The original image could not be put back in place.', 'media-library-tools' )
			);
		}

		@chmod( $destination, $perms ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- Best-effort permission restore.

		return true;
	}

	/**
	 * Refresh the cached filesize in attachment metadata after a restore.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return void
	 */
	private function refresh_metadata_filesize( int $attachment_id ): void {
		$meta_data = wp_get_attachment_metadata( $attachment_id );

		if ( ! is_array( $meta_data ) ) {
			return;
		}

		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			return;
		}

		clearstatcache( true, $file );
		$meta_data['filesize'] = (int) filesize( $file );

		wp_update_attachment_metadata( $attachment_id, $meta_data );
	}
}
