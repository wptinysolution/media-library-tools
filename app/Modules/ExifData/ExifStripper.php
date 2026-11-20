<?php
/**
 * EXIF Stripper module — removes EXIF and other metadata from an image's JPEG files, losslessly.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\ExifData;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\ExifFilter;
use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\BackupManager;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionCapabilities;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionMetadata;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;
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
	 * Option-name prefix of the per-attachment strip lock (ScanStartLock).
	 *
	 * @var string
	 */
	const LOCK_PREFIX = 'tsmlt_exif_strip_lock_';

	/**
	 * Per-file statuses that leave a file not confirmed clean.
	 *
	 * @var string[]
	 */
	const NOT_CLEAN = [ 'failed', 'unreadable', 'unsupported' ];

	/**
	 * Remove EXIF and other image metadata from an attachment, losslessly.
	 *
	 * Every JPEG file of the attachment is processed with
	 * JpegMetadataStripper: the attached image, the full-size original of a
	 * scaled or rotated upload, the generated sizes and WordPress's pre-edit
	 * backups. Each file is checked on its own; one that holds no metadata is
	 * not rewritten. When the attached image is clean afterwards, WordPress's
	 * stored image data is cleared of private fields, file sizes refreshed,
	 * and the plugin's stored EXIF, filter index, cache and privacy-scan
	 * result removed. Compression backups and WebP/AVIF copies are not
	 * changed; whether they exist is reported.
	 *
	 * Refused while another strip, a compression or a conversion of the same
	 * attachment is running.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array{success: bool, message: string, status?: string, files?: array, untouched_copies?: array}
	 *         success and message as before; status is stripped,
	 *         already_clean, partial or failed; files one result per file
	 *         (role, size, status, code; never a path).
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

		// Only JPEG supported.
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

		// Files are replaced by renaming within their folder, which needs local storage.
		if ( false !== strpos( $file_path, '://' ) || wp_is_stream( $file_path ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'EXIF stripping needs images stored on this server; offloaded storage is not supported.', 'media-library-tools' ),
			];
		}

		if ( AttachmentProcessor::instance()->is_locked( $attachment_id ) || AttachmentConverter::instance()->is_locked( $attachment_id ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'This image is being compressed or converted. Try again when that has finished.', 'media-library-tools' ),
			];
		}

		$lock = ScanStartLock::instance()->acquire( self::LOCK_PREFIX . $attachment_id );
		if ( ! is_string( $lock ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'EXIF is already being removed from this image. Try again in a moment.', 'media-library-tools' ),
			];
		}
		try {
			return $this->strip_files( $attachment_id, $file_path );
		} finally {
			ScanStartLock::instance()->release( $lock, self::LOCK_PREFIX . $attachment_id );
		}
	}

	/**
	 * Strip every file of an attachment, refresh its data and report.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     Attached file.
	 *
	 * @return array
	 */
	private function strip_files( int $attachment_id, string $file_path ): array {
		$files    = [];
		$stripped = 0;
		$failures = 0;
		$attached = '';
		foreach ( self::attachment_files( $attachment_id, $file_path ) as $file ) {
			$result = JpegMetadataStripper::strip_file( $file['path'] );
			if ( 'attached' === $file['role'] ) {
				$attached = $result['status'];
			}
			$stripped += 'stripped' === $result['status'] ? 1 : 0;
			$failures += in_array( $result['status'], self::NOT_CLEAN, true ) ? 1 : 0;
			$files[]   = [
				'role'   => $file['role'],
				'size'   => $file['size'],
				'status' => $result['status'],
				'code'   => $result['code'],
			];
		}
		$attached_clean = in_array( $attached, [ 'stripped', 'already_clean' ], true );

		if ( $attached_clean ) {
			// Also after a run that found nothing to remove, so data left
			// stale by an interrupted earlier run is brought up to date.
			$this->refresh_metadata( $attachment_id, $file_path );
			/**
			 * Fires after all EXIF was removed from an attachment's file.
			 *
			 * @param int    $attachment_id Attachment ID.
			 * @param string $mode          What was removed: "all".
			 */
			do_action( 'tsmlt_exif_stripped', $attachment_id, 'all' );
		}

		if ( ! $attached_clean && 0 < $stripped ) {
			$status  = 'partial';
			$message = esc_html__( 'EXIF metadata was removed from some of this image\'s files, but not from the image itself.', 'media-library-tools' );
		} elseif ( ! $attached_clean ) {
			$status  = 'failed';
			$message = esc_html__( 'The EXIF metadata could not be removed from this image. The file was not changed.', 'media-library-tools' );
		} elseif ( $failures > 0 ) {
			$status  = 'partial';
			$message = esc_html__( 'EXIF metadata was removed from the image, but some of its other files could not be cleaned.', 'media-library-tools' );
		} elseif ( $stripped > 0 ) {
			$status  = 'stripped';
			$message = esc_html__( 'EXIF metadata stripped successfully.', 'media-library-tools' );
		} else {
			$status  = 'already_clean';
			$message = esc_html__( 'This image has no EXIF metadata to remove.', 'media-library-tools' );
		}

		return [
			'success'          => in_array( $status, [ 'stripped', 'already_clean' ], true ),
			'message'          => $message,
			'status'           => $status,
			'files'            => $files,
			'untouched_copies' => self::untouched_copies( $attachment_id ),
		];
	}

	/**
	 * The files of an attachment to process, without duplicates: the
	 * attached image, the original of a scaled or rotated upload, the
	 * generated sizes and the pre-edit backups. Stored names are reduced to
	 * file names, so only files in the attachment's folder are considered.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     Attached file.
	 *
	 * @return array<int, array{role: string, size: string, path: string}>
	 */
	private static function attachment_files( int $attachment_id, string $file_path ): array {
		$folder   = trailingslashit( dirname( $file_path ) );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$metadata = is_array( $metadata ) ? $metadata : [];
		$entries  = [
			[ 'attached', '', wp_basename( $file_path ) ],
			[ 'original', '', is_string( $metadata['original_image'] ?? null ) ? wp_basename( $metadata['original_image'] ) : '' ],
		];
		foreach ( (array) ( $metadata['sizes'] ?? [] ) as $name => $size ) {
			$entries[] = [ 'size', (string) $name, is_array( $size ) && is_string( $size['file'] ?? null ) ? wp_basename( $size['file'] ) : '' ];
		}
		$backups = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		foreach ( is_array( $backups ) ? $backups : [] as $name => $size ) {
			$entries[] = [ 'backup', (string) $name, is_array( $size ) && is_string( $size['file'] ?? null ) ? wp_basename( $size['file'] ) : '' ];
		}

		$files = [];
		$seen  = [];
		foreach ( $entries as $entry ) {
			list( $role, $size, $name ) = $entry;
			if ( '' === $name || isset( $seen[ $name ] ) ) {
				continue;
			}
			$seen[ $name ] = true;
			$files[]       = [
				'role' => $role,
				'size' => preg_match( '/^[A-Za-z0-9_-]{1,100}$/', $size ) ? $size : '',
				'path' => $folder . $name,
			];
		}
		return $files;
	}

	/**
	 * Bring stored data in line with a stripped image: WordPress's image
	 * data without private fields (Orientation kept), refreshed file sizes,
	 * and the plugin's stored EXIF, filter index and privacy-scan result
	 * removed.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     Attached file.
	 *
	 * @return void
	 */
	private function refresh_metadata( int $attachment_id, string $file_path ): void {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && [] !== $metadata ) {
			$updated = $metadata;
			if ( isset( $updated['image_meta'] ) && is_array( $updated['image_meta'] ) ) {
				foreach ( $updated['image_meta'] as $key => $value ) {
					if ( 'orientation' === $key ) {
						continue;
					}
					if ( is_array( $value ) ) {
						$updated['image_meta'][ $key ] = [];
					} elseif ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) {
						$updated['image_meta'][ $key ] = 0;
					} else {
						$updated['image_meta'][ $key ] = '';
					}
				}
			}
			clearstatcache();
			if ( file_exists( $file_path ) ) {
				$updated['filesize'] = wp_filesize( $file_path );
			}
			$folder = trailingslashit( dirname( $file_path ) );
			foreach ( (array) ( $updated['sizes'] ?? [] ) as $name => $size ) {
				if ( is_array( $size ) && isset( $size['filesize'], $size['file'] ) && is_string( $size['file'] ) && file_exists( $folder . wp_basename( $size['file'] ) ) ) {
					$updated['sizes'][ $name ]['filesize'] = wp_filesize( $folder . wp_basename( $size['file'] ) );
				}
			}
			if ( $updated !== $metadata ) {
				wp_update_attachment_metadata( $attachment_id, $updated );
			}
		}

		// The file no longer has any EXIF, so values saved by the EXIF editor
		// (which readers overlay on the file's EXIF), the camera and GPS index
		// kept for the media filters and the privacy-scan result would only
		// be stale.
		delete_post_meta( $attachment_id, '_tsmlt_exif_meta' );
		ExifFilter::remove_exif_meta( $attachment_id );
		delete_post_meta( $attachment_id, ExifScanner::PRIVACY_META_KEY );
		ExifDataReader::clear_cache();
		clean_post_cache( $attachment_id );
	}

	/**
	 * Other stored copies of the image that are not changed and may still
	 * hold metadata: the compression backup and WebP/AVIF copies.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array{compression_backup: bool, converted_formats: string[]}
	 */
	private static function untouched_copies( int $attachment_id ): array {
		$formats    = [];
		$conversion = ConversionMetadata::instance()->get( $attachment_id );
		foreach ( (array) ( $conversion['formats'] ?? [] ) as $format => $info ) {
			if ( is_array( $info ) && 'completed' === ( $info['status'] ?? '' ) && in_array( $format, ConversionCapabilities::FORMATS, true ) ) {
				$formats[] = (string) $format;
			}
		}
		return [
			'compression_backup' => BackupManager::instance()->has_backup_read_only( $attachment_id ),
			'converted_formats'  => $formats,
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
