<?php
/**
 * Compression engine contract.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Compressors;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Contract every compression engine must satisfy.
 *
 * Engines are pure file-to-file transformers: they read a source path, write a
 * destination path, and report success. They never touch attachment metadata,
 * backups or the media library — that orchestration lives in
 * `AttachmentProcessor`, which is why a future API-backed engine (TinyPNG,
 * ShortPixel, Imagify) can be added by implementing this interface alone.
 */
interface CompressorInterface {

	/**
	 * Machine-readable engine identifier, stored in compression metadata.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Whether this engine can run on the current server.
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Whether this engine can compress the given MIME type.
	 *
	 * @param string $mime_type MIME type, e.g. `image/jpeg`.
	 *
	 * @return bool
	 */
	public function supports_mime_type( string $mime_type ): bool;

	/**
	 * Compress a single image file.
	 *
	 * Implementations must write to `$destination` and must never modify
	 * `$source`. Returning a `WP_Error` leaves the destination undefined; the
	 * caller is responsible for cleaning it up.
	 *
	 * @param string $source      Absolute path to the readable source file.
	 * @param string $destination Absolute path to write the compressed file to.
	 * @param string $mime_type   MIME type of the source file.
	 * @param int    $quality     Quality value, 1–100.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function compress( string $source, string $destination, string $mime_type, int $quality );
}
