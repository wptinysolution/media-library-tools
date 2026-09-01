<?php
/**
 * Image format conversion engine contract.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Conversion\Converters;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Contract every format-conversion engine must satisfy.
 *
 * Engines are pure file-to-file transformers: they read a source image and
 * write a new file in a different format, never touching the source. All
 * orchestration — temporary files, validation, metadata — lives in
 * `AttachmentConverter`, so a future engine (an external API, `cwebp`,
 * `avifenc`) can be added by implementing this interface alone.
 *
 * Deliberately separate from `CompressorInterface`: compression re-encodes to
 * the same format, conversion produces a different one, and conflating them
 * would force every engine to answer questions it does not care about.
 */
interface ImageConverterInterface {

	/**
	 * Machine-readable engine identifier, stored in conversion metadata.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Whether this engine can run on the current server at all.
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Whether this engine can read the given source MIME type.
	 *
	 * @param string $mime_type Source MIME type, e.g. `image/jpeg`.
	 *
	 * @return bool
	 */
	public function supports_source( string $mime_type ): bool;

	/**
	 * Whether this engine can write the given target format.
	 *
	 * Must reflect the actual build: many GD and Imagick builds ship without
	 * AVIF, and some without WebP, so this cannot be assumed from the extension
	 * being loaded.
	 *
	 * @param string $format Target format key, `webp` or `avif`.
	 *
	 * @return bool
	 */
	public function supports_format( string $format ): bool;

	/**
	 * Convert a single image file into another format.
	 *
	 * Implementations must write to `$destination` and must never modify
	 * `$source`. On a `WP_Error` return the destination is undefined; the
	 * caller is responsible for cleaning it up.
	 *
	 * @param string $source      Absolute path to a readable source image.
	 * @param string $destination Absolute path to write the converted file to.
	 * @param string $format      Target format key, `webp` or `avif`.
	 * @param int    $quality     Quality value, 1–100.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function convert( string $source, string $destination, string $format, int $quality );
}
