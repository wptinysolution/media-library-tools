<?php
/**
 * Lifecycle operations for generated WebP/AVIF files.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Conversion;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Deletes and regenerates the converted files belonging to an attachment.
 *
 * Only ever touches files this plugin recorded in conversion metadata, and only
 * those resolving inside the uploads directory — so a tampered meta row cannot
 * be used to delete arbitrary files.
 */
class ConversionManager {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Delete every generated file for an attachment, or just one format.
	 *
	 * The original attachment is never touched.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $format        Format key, or empty for all formats.
	 *
	 * @return array|WP_Error
	 */
	public function delete_conversions( int $attachment_id, string $format = '' ) {
		$converter = AttachmentConverter::instance();
		$valid     = $converter->validate_attachment( $attachment_id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( '' !== $format && ! in_array( $format, ConversionCapabilities::FORMATS, true ) ) {
			return new WP_Error(
				'tsmlt_conversion_unsupported_format',
				esc_html__( 'Unknown image format.', 'media-library-tools' )
			);
		}

		$metadata = ConversionMetadata::instance();
		$data     = $metadata->get( $attachment_id );

		if ( empty( $data['formats'] ) ) {
			return new WP_Error(
				'tsmlt_conversion_nothing_to_delete',
				esc_html__( 'This image has no converted files.', 'media-library-tools' )
			);
		}

		$deleted = 0;
		$formats = $data['formats'];

		foreach ( $formats as $format_key => $info ) {
			if ( '' !== $format && $format_key !== $format ) {
				continue;
			}

			// Full-size output plus every generated size recorded for it.
			$relative_paths = [];
			if ( ! empty( $info['file'] ) ) {
				$relative_paths[] = (string) $info['file'];
			}
			foreach ( (array) ( $info['sizes'] ?? [] ) as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$relative_paths[] = (string) $size['file'];
				}
			}

			foreach ( $relative_paths as $relative ) {
				if ( $this->delete_upload_file( $relative ) ) {
					++$deleted;
				}
			}

			unset( $formats[ $format_key ] );
		}

		if ( empty( $formats ) ) {
			$metadata->delete( $attachment_id );
		} else {
			$data['formats'] = $formats;
			$metadata->save( $attachment_id, $data );
		}

		return [
			'attachment_id' => $attachment_id,
			'deleted'       => $deleted,
			'conversion'    => $metadata->get_for_display( $attachment_id ),
			'message'       => sprintf(
				/* translators: %d: number of deleted files */
				esc_html( _n( '%d converted file deleted.', '%d converted files deleted.', $deleted, 'media-library-tools' ) ),
				$deleted
			),
		];
	}

	/**
	 * Delete existing conversions and produce them again.
	 *
	 * Used when the source image has changed — a regenerated thumbnail set or a
	 * re-upload leaves the previous output describing an image that no longer
	 * exists.
	 *
	 * @param int   $attachment_id Attachment post ID.
	 * @param array $params        Raw request parameters for run settings.
	 *
	 * @return array|WP_Error
	 */
	public function regenerate( int $attachment_id, array $params ) {
		$converter = AttachmentConverter::instance();
		$valid     = $converter->validate_attachment( $attachment_id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$metadata = ConversionMetadata::instance();
		$existing = $metadata->get( $attachment_id );

		// Reuse the formats already generated unless the caller names others, so
		// "regenerate" reproduces what was there rather than silently changing it.
		if ( empty( $params['formats'] ) && ! empty( $existing['formats'] ) ) {
			$params['formats'] = array_keys( $existing['formats'] );
		}

		// Remove the old output first so a failed run cannot leave a mix of old
		// and new files behind.
		if ( ! empty( $existing['formats'] ) ) {
			$this->delete_conversions( $attachment_id );
		}

		$run_settings = ConversionSettings::instance()->resolve_run_settings( $params );

		return $converter->convert( $attachment_id, $run_settings );
	}

	/**
	 * Delete one uploads-relative file.
	 *
	 * Refuses anything that resolves outside the uploads directory, so a
	 * tampered metadata row cannot reach arbitrary paths.
	 *
	 * @param string $relative_path Uploads-relative path.
	 *
	 * @return bool
	 */
	private function delete_upload_file( string $relative_path ): bool {
		$uploads = wp_get_upload_dir();

		if ( empty( $uploads['basedir'] ) ) {
			return false;
		}

		$relative_path = ltrim( wp_normalize_path( $relative_path ), '/' );

		if ( '' === $relative_path || false !== strpos( $relative_path, '../' ) ) {
			return false;
		}

		$basedir = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
		$path    = $basedir . $relative_path;

		if ( 0 !== strpos( $path, $basedir ) || ! file_exists( $path ) ) {
			return false;
		}

		wp_delete_file( $path );

		return true;
	}
}
