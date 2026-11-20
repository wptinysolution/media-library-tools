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
 * Only ever deletes files that are positively this attachment's own converted
 * copies (ConversionOwnership::is_owned_copy()): recorded in its conversion
 * data, named after its own attached or size file, inside uploads, used by
 * no attachment as a media file and recorded by no other attachment. A
 * stored record alone is never enough, so a wrong or tampered record cannot
 * delete another attachment's file.
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
		$kept    = 0;
		$basedir = trailingslashit( (string) wp_get_upload_dir()['basedir'] );
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
				if ( ! is_file( $basedir . $relative ) ) {
					continue;
				}
				if ( ConversionOwnership::is_owned_copy( $attachment_id, $basedir . $relative, (string) $format_key ) && $this->delete_upload_file( $relative ) ) {
					++$deleted;
				} else {
					// Not provably this attachment's copy: kept, no longer recorded.
					++$kept;
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
			'kept'          => $kept,
			'conversion'    => $metadata->get_for_display( $attachment_id ),
			'message'       => sprintf(
				/* translators: %d: number of deleted files */
				esc_html( _n( '%d converted file deleted.', '%d converted files deleted.', $deleted, 'media-library-tools' ) ),
				$deleted
			),
		];
	}

	/**
	 * Make an attachment's converted copies again (for example after its
	 * image changed), all or nothing: the new copies are made and checked
	 * first, old copies are replaced only when every one was made, and a
	 * failure keeps the old copies and records
	 * (AttachmentConverter::regenerate_copies()).
	 *
	 * @param int   $attachment_id Attachment post ID.
	 * @param array $params        Request parameters.
	 *
	 * @return array|WP_Error
	 */
	public function regenerate( int $attachment_id, array $params ) {
		$converter = AttachmentConverter::instance();
		$valid     = $converter->validate_attachment( $attachment_id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$existing = ConversionMetadata::instance()->get( $attachment_id );

		// Reuse the formats already generated unless the caller names others, so
		// "regenerate" reproduces what was there rather than silently changing it.
		if ( empty( $params['formats'] ) && ! empty( $existing['formats'] ) ) {
			$params['formats'] = array_keys( $existing['formats'] );
		}

		$run_settings = ConversionSettings::instance()->resolve_run_settings( $params );

		return $converter->regenerate_copies( $attachment_id, $run_settings );
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
