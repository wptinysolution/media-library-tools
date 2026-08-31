<?php
/**
 * GD-backed compression engine.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Compressors;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use WP_Error;

/**
 * Compresses images using the GD extension.
 *
 * Fallback engine used when Imagick is unavailable. GD is present on virtually
 * every PHP install but produces slightly larger files and cannot preserve ICC
 * profiles.
 */
class GDCompressor implements CompressorInterface {

	/**
	 * MIME types this engine handles.
	 *
	 * @var string[]
	 */
	const SUPPORTED_MIME_TYPES = [
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/webp',
	];

	/**
	 * Machine-readable engine identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'gd';
	}

	/**
	 * Whether the GD extension is loaded.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return extension_loaded( 'gd' ) && function_exists( 'imagecreatetruecolor' );
	}

	/**
	 * Whether GD was built with read and write support for the MIME type.
	 *
	 * @param string $mime_type MIME type to test.
	 *
	 * @return bool
	 */
	public function supports_mime_type( string $mime_type ): bool {
		if ( ! in_array( $mime_type, self::SUPPORTED_MIME_TYPES, true ) || ! $this->is_available() ) {
			return false;
		}

		switch ( $mime_type ) {
			case 'image/jpeg':
			case 'image/jpg':
				return function_exists( 'imagecreatefromjpeg' ) && function_exists( 'imagejpeg' );
			case 'image/png':
				return function_exists( 'imagecreatefrompng' ) && function_exists( 'imagepng' );
			case 'image/webp':
				return function_exists( 'imagecreatefromwebp' ) && function_exists( 'imagewebp' );
			default:
				return false;
		}
	}

	/**
	 * Compress a file with GD.
	 *
	 * @param string $source      Absolute source path.
	 * @param string $destination Absolute destination path.
	 * @param string $mime_type   Source MIME type.
	 * @param int    $quality     Quality value, 1–100.
	 *
	 * @return true|WP_Error
	 */
	public function compress( string $source, string $destination, string $mime_type, int $quality ) {
		$image = $this->create_image( $source, $mime_type );

		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$written = false;

		switch ( $mime_type ) {
			case 'image/jpeg':
			case 'image/jpg':
				// Progressive encoding: smaller output, earlier first paint.
				imageinterlace( $image, 1 );
				$written = @imagejpeg( $image, $destination, $quality ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure is reported via the return value.
				break;

			case 'image/png':
				// Preserve transparency, which imagecreatefrompng() alone does not.
				imagealphablending( $image, false );
				imagesavealpha( $image, true );
				// PNG is lossless — map 1–100 quality onto the 0–9 zlib scale.
				$png_level = (int) round( ( 100 - $quality ) / 100 * 9 );
				$png_level = max( 0, min( 9, $png_level ) );
				$written   = @imagepng( $image, $destination, $png_level ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure is reported via the return value.
				break;

			case 'image/webp':
				imagealphablending( $image, false );
				imagesavealpha( $image, true );
				$written = @imagewebp( $image, $destination, $quality ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure is reported via the return value.
				break;
		}

		// GD handles became garbage-collected objects in PHP 8.0, and the call is
		// deprecated from 8.5. Older supported versions still need it to free
		// the underlying resource.
		if ( PHP_VERSION_ID < 80000 ) {
			imagedestroy( $image ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated -- Required on PHP 7.4; skipped on 8.0+ where it is a no-op.
		}

		if ( ! $written ) {
			return new WP_Error(
				'tsmlt_compression_write_failed',
				esc_html__( 'The compression engine could not write the output file.', 'media-library-tools' )
			);
		}

		return true;
	}

	/**
	 * Build a GD image resource from a source file.
	 *
	 * @param string $source    Absolute source path.
	 * @param string $mime_type Source MIME type.
	 *
	 * @return \GdImage|resource|WP_Error
	 */
	private function create_image( string $source, string $mime_type ) {
		$image = false;

		switch ( $mime_type ) {
			case 'image/jpeg':
			case 'image/jpg':
				$image = @imagecreatefromjpeg( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Corrupt files are handled below.
				break;
			case 'image/png':
				$image = @imagecreatefrompng( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Corrupt files are handled below.
				break;
			case 'image/webp':
				$image = @imagecreatefromwebp( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Corrupt files are handled below.
				break;
		}

		if ( ! $image ) {
			return new WP_Error(
				'tsmlt_compression_read_failed',
				esc_html__( 'The image could not be read. It may be corrupt or in an unsupported format.', 'media-library-tools' )
			);
		}

		return $image;
	}
}
