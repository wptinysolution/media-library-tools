<?php
/**
 * GD-backed format conversion engine.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Conversion\Converters;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use WP_Error;

/**
 * Converts images to WebP or AVIF using the GD extension.
 *
 * Fallback engine, but on most modern PHP builds it is the only one present.
 * AVIF support arrived in PHP 8.1 and is compiled in separately, so it is
 * probed through `gd_info()` rather than assumed.
 */
class GDConverter implements ImageConverterInterface {

	/**
	 * Source MIME types this engine can read.
	 *
	 * @var string[]
	 */
	const SUPPORTED_SOURCES = [
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
	 * Whether the GD extension is loaded and usable.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return extension_loaded( 'gd' ) && function_exists( 'imagecreatetruecolor' );
	}

	/**
	 * Whether GD can read this source MIME type.
	 *
	 * @param string $mime_type Source MIME type.
	 *
	 * @return bool
	 */
	public function supports_source( string $mime_type ): bool {
		if ( ! $this->is_available() || ! in_array( strtolower( $mime_type ), self::SUPPORTED_SOURCES, true ) ) {
			return false;
		}

		switch ( strtolower( $mime_type ) ) {
			case 'image/jpeg':
			case 'image/jpg':
				return function_exists( 'imagecreatefromjpeg' );
			case 'image/png':
				return function_exists( 'imagecreatefrompng' );
			case 'image/webp':
				return function_exists( 'imagecreatefromwebp' );
			default:
				return false;
		}
	}

	/**
	 * Whether this GD build can write the target format.
	 *
	 * Checks both the writer function and the `gd_info()` flag: the function can
	 * exist while the build lacks the underlying encoder.
	 *
	 * @param string $format Target format key.
	 *
	 * @return bool
	 */
	public function supports_format( string $format ): bool {
		if ( ! $this->is_available() ) {
			return false;
		}

		$info = function_exists( 'gd_info' ) ? gd_info() : [];

		switch ( $format ) {
			case 'webp':
				return function_exists( 'imagewebp' ) && ! empty( $info['WebP Support'] );
			case 'avif':
				return function_exists( 'imageavif' ) && ! empty( $info['AVIF Support'] );
			default:
				return false;
		}
	}

	/**
	 * Convert a file to the target format with GD.
	 *
	 * @param string $source      Absolute source path.
	 * @param string $destination Absolute destination path.
	 * @param string $format      Target format key.
	 * @param int    $quality     Quality value, 1–100.
	 *
	 * @return true|WP_Error
	 */
	public function convert( string $source, string $destination, string $format, int $quality ) {
		$mime_type = $this->detect_source_mime( $source );

		if ( '' === $mime_type ) {
			return new WP_Error(
				'tsmlt_conversion_unsupported_source',
				esc_html__( 'This image type cannot be converted.', 'media-library-tools' )
			);
		}

		$image = $this->create_image( $source, $mime_type );

		if ( is_wp_error( $image ) ) {
			return $image;
		}

		// Both WebP and AVIF carry an alpha channel, so transparency from a
		// source PNG must be preserved rather than flattened to black.
		imagealphablending( $image, false );
		imagesavealpha( $image, true );

		$written = false;

		switch ( $format ) {
			case 'webp':
				$written = @imagewebp( $image, $destination, $quality ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure is reported via the return value.
				break;
			case 'avif':
				// GD's AVIF encoder takes a speed argument; 6 is a reasonable
				// balance, as the default (or -1) can be extremely slow.
				$written = @imageavif( $image, $destination, $quality, 6 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure is reported via the return value.
				break;
		}

		// GD handles became garbage-collected objects in PHP 8.0, and the call
		// is deprecated from 8.5. Older supported versions still need it.
		if ( PHP_VERSION_ID < 80000 ) {
			imagedestroy( $image ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated -- Required on PHP 7.4.
		}

		if ( ! $written ) {
			return new WP_Error(
				'tsmlt_conversion_write_failed',
				esc_html__( 'The converted image could not be written.', 'media-library-tools' )
			);
		}

		return true;
	}

	/**
	 * Determine the source file's MIME type from its contents.
	 *
	 * @param string $source Absolute source path.
	 *
	 * @return string Empty string when the type is unreadable or unsupported.
	 */
	private function detect_source_mime( string $source ): string {
		$size = @getimagesize( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid images handled by the caller.

		if ( false === $size || empty( $size['mime'] ) ) {
			return '';
		}

		$mime = strtolower( (string) $size['mime'] );

		return in_array( $mime, self::SUPPORTED_SOURCES, true ) ? $mime : '';
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
				$image = @imagecreatefromjpeg( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Corrupt files handled below.
				break;
			case 'image/png':
				$image = @imagecreatefrompng( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Corrupt files handled below.
				break;
			case 'image/webp':
				$image = @imagecreatefromwebp( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Corrupt files handled below.
				break;
		}

		if ( ! $image ) {
			return new WP_Error(
				'tsmlt_conversion_read_failed',
				esc_html__( 'The image could not be read. It may be corrupt or in an unsupported format.', 'media-library-tools' )
			);
		}

		return $image;
	}
}
