<?php
/**
 * Imagick-backed format conversion engine.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Conversion\Converters;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use Imagick;
use WP_Error;

/**
 * Converts images to WebP or AVIF using the Imagick extension.
 *
 * Preferred over GD when present: it preserves ICC colour profiles and its
 * AVIF encoder is generally faster. Support for each output format is probed
 * with `Imagick::queryFormats()` because delegates are compiled in separately —
 * many builds have WebP but not AVIF.
 */
class ImagickConverter implements ImageConverterInterface {

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
		return 'imagick';
	}

	/**
	 * Whether the Imagick extension is loaded and usable.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
	}

	/**
	 * Whether Imagick can read this source MIME type.
	 *
	 * @param string $mime_type Source MIME type.
	 *
	 * @return bool
	 */
	public function supports_source( string $mime_type ): bool {
		if ( ! $this->is_available() || ! in_array( strtolower( $mime_type ), self::SUPPORTED_SOURCES, true ) ) {
			return false;
		}

		$format = 'image/png' === $mime_type ? 'PNG' : ( 'image/webp' === $mime_type ? 'WEBP' : 'JPEG' );

		return $this->query_format( $format );
	}

	/**
	 * Whether this Imagick build has a delegate for the target format.
	 *
	 * @param string $format Target format key.
	 *
	 * @return bool
	 */
	public function supports_format( string $format ): bool {
		if ( ! $this->is_available() ) {
			return false;
		}

		switch ( $format ) {
			case 'webp':
				return $this->query_format( 'WEBP' );
			case 'avif':
				return $this->query_format( 'AVIF' );
			default:
				return false;
		}
	}

	/**
	 * Convert a file to the target format with Imagick.
	 *
	 * @param string $source      Absolute source path.
	 * @param string $destination Absolute destination path.
	 * @param string $format      Target format key.
	 * @param int    $quality     Quality value, 1–100.
	 *
	 * @return true|WP_Error
	 */
	public function convert( string $source, string $destination, string $format, int $quality ) {
		$image = null;

		try {
			$image = new Imagick();
			$image->readImage( $source );

			// Drop EXIF/IPTC payloads but keep the ICC profile so colours do not
			// shift in the converted copy.
			$profiles    = $image->getImageProfiles( 'icc', true );
			$icc_profile = ! empty( $profiles['icc'] ) ? $profiles['icc'] : '';
			$image->stripImage();
			if ( '' !== $icc_profile ) {
				$image->profileImage( 'icc', $icc_profile );
			}

			// Flatten only when the source has no alpha; otherwise transparency
			// must survive into WebP/AVIF, both of which support it.
			if ( ! $image->getImageAlphaChannel() ) {
				$image->setImageBackgroundColor( 'white' );
			}

			switch ( $format ) {
				case 'webp':
					$image->setImageFormat( 'webp' );
					$image->setImageCompressionQuality( $quality );
					$image->setOption( 'webp:method', '6' );
					break;

				case 'avif':
					$image->setImageFormat( 'avif' );
					$image->setImageCompressionQuality( $quality );
					break;

				default:
					$this->release( $image );

					return new WP_Error(
						'tsmlt_conversion_unsupported_format',
						esc_html__( 'This output format is not supported.', 'media-library-tools' )
					);
			}

			$written = $image->writeImage( $destination );
			$this->release( $image );

			if ( ! $written ) {
				return new WP_Error(
					'tsmlt_conversion_write_failed',
					esc_html__( 'The converted image could not be written.', 'media-library-tools' )
				);
			}

			return true;
		} catch ( \Exception $e ) {
			$this->release( $image );

			return new WP_Error( 'tsmlt_conversion_engine_error', $e->getMessage() );
		}
	}

	/**
	 * Ask Imagick whether a format delegate is compiled in.
	 *
	 * @param string $format Imagick format name, e.g. `AVIF`.
	 *
	 * @return bool
	 */
	private function query_format( string $format ): bool {
		try {
			return ! empty( Imagick::queryFormats( $format ) );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Free an Imagick handle, ignoring teardown failures.
	 *
	 * @param Imagick|null $image Imagick instance or null.
	 *
	 * @return void
	 */
	private function release( $image ): void {
		if ( ! $image instanceof Imagick ) {
			return;
		}
		try {
			$image->clear();
			$image->destroy();
		} catch ( \Exception $e ) {
			unset( $e ); // Nothing actionable while discarding the handle.
		}
	}
}
