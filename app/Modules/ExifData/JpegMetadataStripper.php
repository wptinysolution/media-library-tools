<?php
/**
 * Lossless JPEG metadata stripper.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\ExifData;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Removes metadata from a JPEG without re-encoding it.
 *
 * The marker segments before the image data are filtered: EXIF (with GPS),
 * XMP and extended XMP (APP1), IPTC/Photoshop (APP13), comments (COM) and
 * every other APPn segment are dropped; APP0 (JFIF), the ICC colour profile
 * and Multi-Picture index (APP2) and APP14 (Adobe) are kept byte for byte,
 * as are the tables and frame headers and everything from the first
 * start-of-scan marker on. When the EXIF held an Orientation other than
 * "normal", a minimal EXIF segment holding only that Orientation is written
 * so the image is still shown the right way up.
 *
 * A file is replaced only after the new bytes are verified (same image data,
 * same dimensions, kept segments unchanged, no other metadata), written to a
 * temporary file in the same folder, read back and verified again, and renamed
 * over the original. The temporary file only ever holds stripped bytes and is
 * deleted on every failure. A file that holds nothing to remove is not
 * rewritten.
 *
 * The file operations are separate static methods so tests can simulate
 * failures in a subclass.
 */
class JpegMetadataStripper {

	/**
	 * Per-file statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'stripped', 'already_clean', 'missing', 'unreadable', 'unsupported', 'failed' ];

	/**
	 * Data after the image start that identifies metadata of an embedded
	 * image (for example a Multi-Picture preview), which cannot be removed
	 * without changing the image data.
	 *
	 * @var string[]
	 */
	const EMBEDDED_SIGNATURES = [ "Exif\0\0", 'http://ns.adobe.com/xap/1.0/', 'Photoshop 3.0' ];

	/**
	 * EXIF Orientation tag.
	 *
	 * @var int
	 */
	const ORIENTATION_TAG = 0x0112;

	/**
	 * Strip the metadata of a JPEG file in place, verified and atomically.
	 *
	 * @param string $path Absolute path of the file.
	 *
	 * @return array{status: string, code: string} status is one of STATUSES;
	 *         code a fixed reason code ('' when there is none).
	 */
	public static function strip_file( string $path ): array {
		// Atomic replacement needs a local file: refuse stream wrappers and
		// any other URL-style path (offloaded storage).
		if ( '' === $path || false !== strpos( $path, '://' ) || wp_is_stream( $path ) ) {
			return self::result( 'unsupported', 'remote_storage' );
		}
		if ( ! file_exists( $path ) ) {
			return self::result( 'missing', 'file_missing' );
		}
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return self::result( 'unreadable', 'file_unreadable' );
		}
		$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file, read as binary.
		if ( ! is_string( $bytes ) ) {
			return self::result( 'unreadable', 'file_unreadable' );
		}
		if ( 0 !== strpos( $bytes, "\xFF\xD8\xFF" ) ) {
			return self::result( 'unsupported', 'not_jpeg' );
		}

		$edit = self::strip_bytes( $bytes );
		if ( ! $edit['ok'] ) {
			return self::result( 'embedded_metadata' === $edit['code'] ? 'unsupported' : 'failed', $edit['code'] );
		}
		if ( ! $edit['changed'] ) {
			return self::result( 'already_clean', '' );
		}
		if ( true !== self::verify( $bytes, $edit['bytes'], $edit['orientation'] ) ) {
			return self::result( 'failed', 'verification_failed' );
		}
		if ( ! wp_is_writable( $path ) || ! wp_is_writable( dirname( $path ) ) ) {
			return self::result( 'failed', 'not_writable' );
		}

		$temp    = $path . '.tsmlt-strip-' . wp_generate_password( 12, false ) . '.tmp';
		$written = static::write_file( $temp, $edit['bytes'] );
		if ( strlen( $edit['bytes'] ) !== $written ) {
			self::remove( $temp );
			return self::result( 'failed', 'write_failed' );
		}
		clearstatcache( true, $temp );
		$read_back = file_get_contents( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read back the temporary file.
		if ( $read_back !== $edit['bytes'] || true !== self::verify( $bytes, $read_back, $edit['orientation'] ) ) {
			self::remove( $temp );
			return self::result( 'failed', 'verification_failed' );
		}
		$perms = fileperms( $path );
		if ( false !== $perms ) {
			chmod( $temp, $perms & 0777 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Keep the original permissions.
		}
		if ( ! static::replace_file( $temp, $path ) ) {
			self::remove( $temp );
			return self::result( 'failed', 'replace_failed' );
		}
		clearstatcache( true, $path );

		return self::result( 'stripped', '' );
	}

	/**
	 * Remove the metadata from JPEG bytes.
	 *
	 * @param string $bytes JPEG bytes.
	 *
	 * @return array{ok: bool, changed: bool, bytes: string, orientation: int, code: string}
	 *         orientation is the Orientation kept (0 when none is needed).
	 */
	public static function strip_bytes( string $bytes ): array {
		$fail   = [
			'ok'          => false,
			'changed'     => false,
			'bytes'       => $bytes,
			'orientation' => 0,
			'code'        => 'malformed',
		];
		$layout = JpegSegments::layout( $bytes );
		if ( null === $layout ) {
			return $fail;
		}
		$image = (string) substr( $bytes, $layout['image_start'] );
		foreach ( self::EMBEDDED_SIGNATURES as $signature ) {
			if ( false !== strpos( $image, $signature ) ) {
				$fail['code'] = 'embedded_metadata';
				return $fail;
			}
		}

		$orientation = 0;
		$kept        = [];
		foreach ( $layout['segments'] as $segment ) {
			$payload = JpegSegments::payload( $bytes, $segment );
			if ( 0xE1 === $segment['marker'] && 0 === strpos( $payload, "Exif\0\0" ) && 0 === $orientation ) {
				$orientation = self::orientation( (string) substr( $payload, 6 ) );
			}
			if ( self::keeps( $segment['marker'], $payload ) ) {
				$kept[] = $segment;
			}
		}
		$orientation = $orientation >= 2 && $orientation <= 8 ? $orientation : 0;

		$out      = "\xFF\xD8";
		$inserted = 0 === $orientation;
		foreach ( $kept as $segment ) {
			if ( ! $inserted && 0xE0 !== $segment['marker'] ) {
				$out     .= self::minimal_exif( $orientation );
				$inserted = true;
			}
			$out .= JpegSegments::raw( $bytes, $segment );
		}
		if ( ! $inserted ) {
			$out .= self::minimal_exif( $orientation );
		}
		$out .= $image;

		return [
			'ok'          => true,
			'changed'     => $out !== $bytes,
			'bytes'       => $out,
			'orientation' => $orientation,
			'code'        => '',
		];
	}

	/**
	 * Check stripped bytes against the original: valid JPEG, identical image
	 * data and dimensions, the kept segments unchanged and in order, nothing
	 * else but the expected minimal Orientation segment.
	 *
	 * @param string $original    Original bytes.
	 * @param string $stripped    Stripped bytes.
	 * @param int    $orientation Orientation that must be kept (0 for none).
	 *
	 * @return true|string True, or a fixed reason code.
	 */
	public static function verify( string $original, string $stripped, int $orientation ) {
		$before = JpegSegments::layout( $original );
		$after  = JpegSegments::layout( $stripped );
		if ( null === $before || null === $after ) {
			return 'malformed';
		}
		if ( substr( $original, $before['image_start'] ) !== substr( $stripped, $after['image_start'] ) ) {
			return 'image_data_changed';
		}
		$size_before = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $original ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid data warns.
		$size_after  = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $stripped ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid data warns.
		if ( ! $size_after || ( $size_before && array_slice( $size_before, 0, 3 ) !== array_slice( $size_after, 0, 3 ) ) ) {
			return 'dimensions_changed';
		}

		$kept_before = [];
		foreach ( $before['segments'] as $segment ) {
			if ( self::keeps( $segment['marker'], JpegSegments::payload( $original, $segment ) ) ) {
				$kept_before[] = JpegSegments::raw( $original, $segment );
			}
		}
		$kept_after   = [];
		$orientations = 0;
		$minimal      = $orientation > 0 ? self::minimal_exif( $orientation ) : null;
		foreach ( $after['segments'] as $segment ) {
			$raw = JpegSegments::raw( $stripped, $segment );
			if ( self::keeps( $segment['marker'], JpegSegments::payload( $stripped, $segment ) ) ) {
				$kept_after[] = $raw;
			} elseif ( null !== $minimal && $raw === $minimal ) {
				++$orientations;
			} else {
				return 'metadata_remains';
			}
		}
		if ( $kept_before !== $kept_after ) {
			return 'segments_changed';
		}
		if ( ( null === $minimal ? 0 : 1 ) !== $orientations ) {
			return 'orientation_changed';
		}
		return true;
	}

	/**
	 * Whether a segment is kept: anything that is not APPn or COM (tables,
	 * frame headers), APP0 (JFIF), APP2 with an ICC profile or Multi-Picture
	 * index, APP14 (Adobe).
	 *
	 * @param int    $marker  Marker.
	 * @param string $payload Payload.
	 *
	 * @return bool
	 */
	private static function keeps( int $marker, string $payload ): bool {
		if ( 0xFE === $marker ) {
			return false;
		}
		if ( $marker < 0xE0 || $marker > 0xEF ) {
			return true;
		}
		if ( 0xE0 === $marker ) {
			return true;
		}
		if ( 0xE2 === $marker ) {
			return 0 === strpos( $payload, "ICC_PROFILE\0" ) || 0 === strpos( $payload, "MPF\0" );
		}
		if ( 0xEE === $marker ) {
			return 0 === strpos( $payload, 'Adobe' );
		}
		return false;
	}

	/**
	 * The Orientation in IFD0 of an EXIF TIFF structure, or 0.
	 *
	 * @param string $tiff TIFF bytes (the EXIF payload after "Exif\0\0").
	 *
	 * @return int
	 */
	private static function orientation( string $tiff ): int {
		if ( strlen( $tiff ) < 8 ) {
			return 0;
		}
		$order = substr( $tiff, 0, 2 );
		if ( 'II' !== $order && 'MM' !== $order ) {
			return 0;
		}
		$short = 'II' === $order ? 'v' : 'n';
		$long  = 'II' === $order ? 'V' : 'N';
		$ifd   = unpack( $long, substr( $tiff, 4, 4 ) )[1];
		if ( $ifd < 8 || $ifd + 2 > strlen( $tiff ) ) {
			return 0;
		}
		$count = unpack( $short, substr( $tiff, $ifd, 2 ) )[1];
		for ( $i = 0; $i < $count; $i++ ) {
			$entry = $ifd + 2 + 12 * $i;
			if ( $entry + 12 > strlen( $tiff ) ) {
				return 0;
			}
			$tag  = unpack( $short, substr( $tiff, $entry, 2 ) )[1];
			$type = unpack( $short, substr( $tiff, $entry + 2, 2 ) )[1];
			if ( self::ORIENTATION_TAG === $tag && 3 === $type ) {
				return (int) unpack( $short, substr( $tiff, $entry + 8, 2 ) )[1];
			}
		}
		return 0;
	}

	/**
	 * A complete APP1 segment holding EXIF with only the Orientation tag.
	 *
	 * @param int $orientation Orientation (2 to 8).
	 *
	 * @return string
	 */
	private static function minimal_exif( int $orientation ): string {
		$tiff = 'II*' . "\0" . pack( 'V', 8 ) . pack( 'v', 1 )
			. pack( 'vvVv', self::ORIENTATION_TAG, 3, 1, $orientation ) . "\0\0"
			. pack( 'V', 0 );
		$body = "Exif\0\0" . $tiff;
		return "\xFF\xE1" . pack( 'n', strlen( $body ) + 2 ) . $body;
	}

	/**
	 * Write bytes to a file.
	 *
	 * @param string $path  Path.
	 * @param string $bytes Bytes.
	 *
	 * @return int Bytes written (0 on failure).
	 */
	protected static function write_file( string $path, string $bytes ): int {
		$written = @file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Binary write to a temporary file next to the original; failures are reported.
		return false === $written ? 0 : (int) $written;
	}

	/**
	 * Replace a file with another in the same folder.
	 *
	 * @param string $from Temporary file.
	 * @param string $to   Original file.
	 *
	 * @return bool
	 */
	protected static function replace_file( string $from, string $to ): bool {
		return @rename( $from, $to ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Atomic replace within one folder; failures are reported.
	}

	/**
	 * Delete a temporary file if it exists.
	 *
	 * @param string $path Path.
	 *
	 * @return void
	 */
	private static function remove( string $path ): void {
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * A per-file result.
	 *
	 * @param string $status Status.
	 * @param string $code   Reason code.
	 *
	 * @return array{status: string, code: string}
	 */
	private static function result( string $status, string $code ): array {
		return [
			'status' => $status,
			'code'   => $code,
		];
	}
}
