<?php
/**
 * Imagick-backed compression engine.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Compressors;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use Imagick;
use ImagickException;
use WP_Error;

/**
 * Compresses images using the Imagick PHP extension.
 *
 * Preferred over GD: it preserves colour profiles, supports progressive JPEG
 * encoding and offers real PNG quantisation, all of which produce smaller files
 * at equivalent visual quality.
 */
class ImagickCompressor implements CompressorInterface {

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
	 * Whether Imagick was compiled with support for the given MIME type.
	 *
	 * Checks the compiled format list rather than assuming: many builds ship
	 * without WEBP delegates.
	 *
	 * @param string $mime_type MIME type to test.
	 *
	 * @return bool
	 */
	public function supports_mime_type( string $mime_type ): bool {
		if ( ! in_array( $mime_type, self::SUPPORTED_MIME_TYPES, true ) ) {
			return false;
		}

		if ( ! $this->is_available() ) {
			return false;
		}

		$format = $this->mime_to_format( $mime_type );

		try {
			$supported = Imagick::queryFormats( $format );
		} catch ( \Exception $e ) {
			return false;
		}

		return ! empty( $supported );
	}

	/**
	 * Compress a file with Imagick.
	 *
	 * @param string $source      Absolute source path.
	 * @param string $destination Absolute destination path.
	 * @param string $mime_type   Source MIME type.
	 * @param int    $quality     Quality value, 1–100.
	 *
	 * @return true|WP_Error
	 */
	public function compress( string $source, string $destination, string $mime_type, int $quality ) {
		$image = null;

		try {
			$image = new Imagick();
			$image->readImage( $source );

			// Drop EXIF/IPTC/XMP payloads but keep the ICC colour profile so the
			// image does not shift hue after compression.
			$icc_profile = '';
			$profiles    = $image->getImageProfiles( 'icc', true );
			if ( ! empty( $profiles['icc'] ) ) {
				$icc_profile = $profiles['icc'];
			}
			$image->stripImage();
			if ( '' !== $icc_profile ) {
				$image->profileImage( 'icc', $icc_profile );
			}

			switch ( $mime_type ) {
				case 'image/jpeg':
				case 'image/jpg':
					$image->setImageFormat( 'jpeg' );
					$image->setImageCompression( Imagick::COMPRESSION_JPEG );
					$image->setImageCompressionQuality( $quality );
					// Progressive JPEGs are smaller and render sooner.
					$image->setInterlaceScheme( Imagick::INTERLACE_PLANE );
					// Chroma subsampling — invisible on photos, meaningful savings.
					$image->setSamplingFactors( [ '2x2', '1x1', '1x1' ] );
					break;

				case 'image/png':
					$image->setImageFormat( 'png' );
					// PNG is lossless: quality maps to the zlib/filter pair rather
					// than to visual fidelity. Lower requested quality means more
					// aggressive palette reduction.
					$image->setOption( 'png:compression-level', '9' );
					$image->setOption( 'png:compression-strategy', '1' );
					if ( $quality < 90 && $image->getImageColors() > 256 ) {
						$image->quantizeImage( 256, Imagick::COLORSPACE_SRGB, 0, false, false );
					}
					break;

				case 'image/webp':
					$image->setImageFormat( 'webp' );
					$image->setImageCompressionQuality( $quality );
					$image->setOption( 'webp:method', '6' );
					break;

				default:
					$image->clear();
					return new WP_Error(
						'tsmlt_compression_unsupported_mime',
						esc_html__( 'This image type is not supported by the compression engine.', 'media-library-tools' )
					);
			}

			$written = $image->writeImage( $destination );
			$image->clear();
			$image->destroy();
			$image = null;

			if ( ! $written ) {
				return new WP_Error(
					'tsmlt_compression_write_failed',
					esc_html__( 'The compression engine could not write the output file.', 'media-library-tools' )
				);
			}

			return true;
		} catch ( ImagickException $e ) {
			$this->release( $image );

			return new WP_Error( 'tsmlt_compression_engine_error', $e->getMessage() );
		} catch ( \Exception $e ) {
			$this->release( $image );

			return new WP_Error( 'tsmlt_compression_engine_error', $e->getMessage() );
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
			unset( $e ); // Nothing actionable during cleanup — the handle is being discarded anyway.
		}
	}

	/**
	 * Map a MIME type to the Imagick format name.
	 *
	 * @param string $mime_type MIME type.
	 *
	 * @return string
	 */
	private function mime_to_format( string $mime_type ): string {
		switch ( $mime_type ) {
			case 'image/png':
				return 'PNG';
			case 'image/webp':
				return 'WEBP';
			default:
				return 'JPEG';
		}
	}
}
