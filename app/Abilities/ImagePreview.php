<?php
/**
 * Downscaled, metadata-free preview of a media-library image for MCP clients.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Modules\ExifData\JpegMetadataStripper;
use TinySolutions\mlt\Modules\ExifData\JpegSegments;
use WP_Error;
use WP_Post;

/**
 * Builds the image tsmlt/get-media-image-preview returns: a JPEG, PNG or
 * WebP library image, decoded from the smallest local file of the
 * attachment that is large enough, turned upright, downscaled to at most
 * MAX_EDGE pixels on its longest side, re-encoded, and then stripped of every
 * metadata block the format can carry (and checked) so no EXIF, GPS, IPTC,
 * XMP, comment or text chunk can leave the site. The original files and the
 * attachment's data are never changed; the encoder writes to a temporary
 * file that is always removed.
 */
class ImagePreview {

	/**
	 * Longest edge of the preview, in pixels.
	 *
	 * @var int
	 */
	const MAX_EDGE = 1024;

	/**
	 * Smaller longest edges tried when a preview is over MAX_BYTES.
	 *
	 * @var int[]
	 */
	const FALLBACK_EDGES = [ 768, 512 ];

	/**
	 * Most bytes a preview may have (before base64).
	 *
	 * @var int
	 */
	const MAX_BYTES = 1000000;

	/**
	 * Largest source file decoded, in bytes.
	 *
	 * @var int
	 */
	const MAX_SOURCE_BYTES = 52428800;

	/**
	 * Largest source image decoded, in pixels.
	 *
	 * @var int
	 */
	const MAX_SOURCE_PIXELS = 50000000;

	/**
	 * Encoder quality for JPEG and WebP.
	 *
	 * @var int
	 */
	const QUALITY = 82;

	/**
	 * Supported MIME types.
	 *
	 * @var string[]
	 */
	const MIME_TYPES = [ 'image/jpeg', 'image/png', 'image/webp' ];

	/**
	 * PNG chunks a preview keeps: image data and colour information only.
	 *
	 * @var string[]
	 */
	const PNG_KEEP = [ 'IHDR', 'PLTE', 'tRNS', 'IDAT', 'IEND', 'gAMA', 'cHRM', 'sRGB', 'iCCP', 'sBIT', 'bKGD', 'pHYs' ];

	/**
	 * WebP chunks a preview drops.
	 *
	 * @var string[]
	 */
	const WEBP_DROP = [ 'EXIF', 'XMP ' ];

	/**
	 * Unsupported image type.
	 *
	 * @var string
	 */
	const ERROR_UNSUPPORTED = 'tsmlt_preview_unsupported';

	/**
	 * The image file is missing, unreadable or not local.
	 *
	 * @var string
	 */
	const ERROR_FILE = 'tsmlt_preview_file_unavailable';

	/**
	 * The source is too large to decode safely.
	 *
	 * @var string
	 */
	const ERROR_SOURCE_TOO_LARGE = 'tsmlt_preview_source_too_large';

	/**
	 * The image could not be decoded or encoded.
	 *
	 * @var string
	 */
	const ERROR_FAILED = 'tsmlt_preview_failed';

	/**
	 * The preview stays over MAX_BYTES.
	 *
	 * @var string
	 */
	const ERROR_TOO_LARGE = 'tsmlt_preview_too_large';

	/**
	 * Build the preview of one attachment for the current user.
	 *
	 * @param mixed $attachment_id Attachment ID.
	 *
	 * @return array{bytes: string, mime_type: string, width: int, height: int}|WP_Error
	 */
	public static function build( $attachment_id ) {
		$attachment = AbilityGuard::resolve_attachment( $attachment_id, 'read_post' );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}
		if ( ! $attachment instanceof WP_Post || 'inherit' !== $attachment->post_status ) {
			return AbilityGuard::error( AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND, __( 'No attachment exists with that ID.', 'media-library-tools' ), 404 );
		}
		if ( ! in_array( strtolower( (string) $attachment->post_mime_type ), self::MIME_TYPES, true ) ) {
			return self::error( self::ERROR_UNSUPPORTED, __( 'Only JPEG, PNG and WebP images have a preview.', 'media-library-tools' ), 415 );
		}

		$file = self::local_file( (int) $attachment->ID );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$source = self::source( (int) $attachment->ID, $file );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		foreach ( array_merge( [ self::MAX_EDGE ], self::FALLBACK_EDGES ) as $edge ) {
			$preview = self::encode( $source, $edge );
			if ( is_wp_error( $preview ) ) {
				return $preview;
			}
			if ( strlen( $preview['bytes'] ) <= self::max_bytes() ) {
				return $preview;
			}
		}
		return self::error( self::ERROR_TOO_LARGE, __( 'A preview of this image within the size limit could not be made.', 'media-library-tools' ), 422 );
	}

	/**
	 * The byte limit: MAX_BYTES, or lower through the
	 * `tsmlt_mcp_image_preview_max_bytes` filter (never higher).
	 *
	 * @return int
	 */
	public static function max_bytes(): int {
		$limit = (int) apply_filters( 'tsmlt_mcp_image_preview_max_bytes', self::MAX_BYTES );
		return max( 1, min( self::MAX_BYTES, $limit ) );
	}

	/**
	 * The attachment's file, only when it is a readable local file inside
	 * the uploads folder.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string|WP_Error
	 */
	private static function local_file( int $attachment_id ) {
		$file = (string) get_attached_file( $attachment_id );
		if ( '' === $file || false !== strpos( $file, '://' ) || wp_is_stream( $file ) ) {
			return self::unavailable();
		}
		$real    = realpath( $file );
		$uploads = realpath( (string) wp_get_upload_dir()['basedir'] );
		if ( false === $real || false === $uploads || 0 !== strpos( wp_normalize_path( $real ), trailingslashit( wp_normalize_path( $uploads ) ) ) ) {
			return self::unavailable();
		}
		if ( ! is_file( $real ) || ! is_readable( $real ) ) {
			return self::unavailable();
		}
		return $real;
	}

	/**
	 * The smallest local file of the attachment (the attached file or one of
	 * its recorded sizes, in the same folder) whose longest edge still covers
	 * the preview, checked for type and size before anything is decoded.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file          Attached file.
	 *
	 * @return string|WP_Error
	 */
	private static function source( int $attachment_id, string $file ) {
		$full = self::probe( $file );
		if ( null === $full ) {
			return self::unavailable();
		}
		$need   = min( self::MAX_EDGE, max( $full['width'], $full['height'] ) );
		$best   = $file;
		$best_e = max( $full['width'], $full['height'] );
		$meta   = wp_get_attachment_metadata( $attachment_id );
		foreach ( is_array( $meta['sizes'] ?? null ) ? $meta['sizes'] : [] as $size ) {
			$name = (string) ( $size['file'] ?? '' );
			if ( '' === $name || wp_basename( $name ) !== $name ) {
				continue;
			}
			$path  = dirname( $file ) . '/' . $name;
			$probe = is_file( $path ) && is_readable( $path ) ? self::probe( $path ) : null;
			if ( null === $probe ) {
				continue;
			}
			$edge = max( $probe['width'], $probe['height'] );
			if ( $edge >= $need && $edge < $best_e ) {
				$best   = $path;
				$best_e = $edge;
			}
		}

		$chosen = $best === $file ? $full : self::probe( $best );
		if ( (int) filesize( $best ) > self::MAX_SOURCE_BYTES || $chosen['width'] * $chosen['height'] > self::MAX_SOURCE_PIXELS ) {
			return self::error( self::ERROR_SOURCE_TOO_LARGE, __( 'The image is too large to make a preview from.', 'media-library-tools' ), 422 );
		}
		return $best;
	}

	/**
	 * Read an image's header: dimensions and actual type. Never decodes it.
	 *
	 * @param string $path File.
	 *
	 * @return array{width: int, height: int, mime: string}|null Null when it is not a supported image.
	 */
	private static function probe( string $path ): ?array {
		$info = wp_getimagesize( $path );
		if ( ! $info || empty( $info[0] ) || empty( $info[1] ) || ! in_array( strtolower( (string) ( $info['mime'] ?? '' ) ), self::MIME_TYPES, true ) ) {
			return null;
		}
		return [
			'width'  => (int) $info[0],
			'height' => (int) $info[1],
			'mime'   => strtolower( (string) $info['mime'] ),
		];
	}

	/**
	 * Decode, turn upright, downscale, re-encode and strip one preview.
	 *
	 * @param string $source Source file.
	 * @param int    $edge   Longest edge.
	 *
	 * @return array{bytes: string, mime_type: string, width: int, height: int}|WP_Error
	 */
	private static function encode( string $source, int $edge ) {
		$mime   = (string) self::probe( $source )['mime'];
		$editor = wp_get_image_editor( $source );
		if ( is_wp_error( $editor ) ) {
			return self::failed();
		}
		if ( is_wp_error( $editor->maybe_exif_rotate() ) ) {
			return self::failed();
		}
		$size = $editor->get_size();
		if ( max( (int) $size['width'], (int) $size['height'] ) > $edge && is_wp_error( $editor->resize( $edge, $edge, false ) ) ) {
			return self::failed();
		}
		$editor->set_quality( self::QUALITY );

		$temp  = wp_tempnam( 'tsmlt-preview' );
		$saved = $temp ? $editor->save( $temp, $mime ) : new WP_Error( 'no_temp' );
		try {
			if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_file( $saved['path'] ) ) {
				return self::failed();
			}
			$bytes = (string) file_get_contents( $saved['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temporary file just written by the image editor.
		} finally {
			foreach ( array_unique( array_filter( [ $temp, is_array( $saved ) ? (string) ( $saved['path'] ?? '' ) : '' ] ) ) as $path ) {
				if ( is_file( $path ) ) {
					wp_delete_file( $path );
				}
			}
		}

		$out_mime = self::detect( $bytes );
		$clean    = '' === $out_mime ? null : self::strip( $bytes, $out_mime );
		if ( null === $clean || ! self::is_clean( $clean, $out_mime ) ) {
			return self::failed();
		}
		$final = @getimagesizefromstring( $clean ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- An unreadable header is refused below.
		if ( false === $final ) {
			return self::failed();
		}
		return [
			'bytes'     => $clean,
			'mime_type' => $out_mime,
			'width'     => (int) ( $final[0] ?? 0 ),
			'height'    => (int) ( $final[1] ?? 0 ),
		];
	}

	/**
	 * The image type of encoded bytes, from their signature.
	 *
	 * @param string $bytes Bytes.
	 *
	 * @return string MIME type, or '' when not JPEG, PNG or WebP.
	 */
	public static function detect( string $bytes ): string {
		if ( 0 === strncmp( $bytes, "\xFF\xD8\xFF", 3 ) ) {
			return 'image/jpeg';
		}
		if ( 0 === strncmp( $bytes, "\x89PNG\r\n\x1A\n", 8 ) ) {
			return 'image/png';
		}
		if ( 0 === strncmp( $bytes, 'RIFF', 4 ) && 'WEBP' === substr( $bytes, 8, 4 ) ) {
			return 'image/webp';
		}
		return '';
	}

	/**
	 * Remove every metadata block from encoded bytes.
	 *
	 * @param string $bytes Bytes.
	 * @param string $mime  MIME type.
	 *
	 * @return string|null Null when the bytes cannot be parsed.
	 */
	public static function strip( string $bytes, string $mime ): ?string {
		switch ( $mime ) {
			case 'image/jpeg':
				$result = JpegMetadataStripper::strip_bytes( $bytes );
				// After turning upright the orientation is normal; anything else is refused.
				return $result['ok'] && 0 === $result['orientation'] ? $result['bytes'] : null;
			case 'image/png':
				return self::strip_png( $bytes );
			case 'image/webp':
				return self::strip_webp( $bytes );
		}
		return null;
	}

	/**
	 * Whether encoded bytes carry no metadata block.
	 *
	 * @param string $bytes Bytes.
	 * @param string $mime  MIME type.
	 *
	 * @return bool
	 */
	public static function is_clean( string $bytes, string $mime ): bool {
		if ( 'image/jpeg' === $mime ) {
			$layout = JpegSegments::layout( $bytes );
			if ( null === $layout ) {
				return false;
			}
			foreach ( $layout['segments'] as $segment ) {
				$payload = JpegSegments::payload( $bytes, $segment );
				$colour  = ( 0xE2 === $segment['marker'] && 0 === strpos( $payload, "ICC_PROFILE\0" ) ) || ( 0xEE === $segment['marker'] && 0 === strpos( $payload, 'Adobe' ) ) || 0xE0 === $segment['marker'];
				if ( $segment['marker'] >= 0xE0 && $segment['marker'] <= 0xEF && ! $colour ) {
					return false;
				}
				if ( 0xFE === $segment['marker'] ) {
					return false;
				}
			}
			return true;
		}
		$chunks = 'image/png' === $mime ? self::png_chunks( $bytes ) : self::webp_chunks( $bytes );
		if ( null === $chunks ) {
			return false;
		}
		foreach ( $chunks as $chunk ) {
			if ( 'image/png' === $mime ? ! in_array( $chunk['type'], self::PNG_KEEP, true ) : in_array( $chunk['type'], self::WEBP_DROP, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * PNG without ancillary chunks other than colour information.
	 *
	 * @param string $bytes PNG bytes.
	 *
	 * @return string|null
	 */
	private static function strip_png( string $bytes ): ?string {
		$chunks = self::png_chunks( $bytes );
		if ( null === $chunks ) {
			return null;
		}
		$out = substr( $bytes, 0, 8 );
		foreach ( $chunks as $chunk ) {
			if ( in_array( $chunk['type'], self::PNG_KEEP, true ) ) {
				$out .= substr( $bytes, $chunk['offset'], $chunk['length'] );
			}
		}
		return $out;
	}

	/**
	 * PNG chunks: type, offset and total length (length, type, data, CRC).
	 *
	 * @param string $bytes PNG bytes.
	 *
	 * @return array|null Null when malformed.
	 */
	private static function png_chunks( string $bytes ): ?array {
		if ( 0 !== strncmp( $bytes, "\x89PNG\r\n\x1A\n", 8 ) ) {
			return null;
		}
		$chunks = [];
		$pos    = 8;
		$total  = strlen( $bytes );
		while ( $pos + 12 <= $total ) {
			$length = unpack( 'N', substr( $bytes, $pos, 4 ) )[1];
			$type   = substr( $bytes, $pos + 4, 4 );
			if ( $pos + 12 + $length > $total ) {
				return null;
			}
			$chunks[] = [
				'type'   => $type,
				'offset' => $pos,
				'length' => 12 + $length,
			];
			$pos     += 12 + $length;
			if ( 'IEND' === $type ) {
				return $chunks;
			}
		}
		return null;
	}

	/**
	 * WebP without EXIF and XMP chunks, with the extended header's flags and
	 * the RIFF size updated.
	 *
	 * @param string $bytes WebP bytes.
	 *
	 * @return string|null
	 */
	private static function strip_webp( string $bytes ): ?string {
		$chunks = self::webp_chunks( $bytes );
		if ( null === $chunks ) {
			return null;
		}
		$body = 'WEBP';
		foreach ( $chunks as $chunk ) {
			if ( in_array( $chunk['type'], self::WEBP_DROP, true ) ) {
				continue;
			}
			$raw = substr( $bytes, $chunk['offset'], $chunk['length'] );
			if ( 'VP8X' === $chunk['type'] && strlen( $raw ) >= 9 ) {
				// Clear the EXIF (0x08) and XMP (0x04) flags.
				$raw[8] = chr( ord( $raw[8] ) & ~0x0C );
			}
			$body .= $raw;
		}
		return 'RIFF' . pack( 'V', strlen( $body ) ) . $body;
	}

	/**
	 * WebP chunks: type, offset and total length (header, data, padding).
	 *
	 * @param string $bytes WebP bytes.
	 *
	 * @return array|null Null when malformed.
	 */
	private static function webp_chunks( string $bytes ): ?array {
		if ( 0 !== strncmp( $bytes, 'RIFF', 4 ) || 'WEBP' !== substr( $bytes, 8, 4 ) ) {
			return null;
		}
		$chunks = [];
		$pos    = 12;
		$total  = min( strlen( $bytes ), 8 + unpack( 'V', substr( $bytes, 4, 4 ) )[1] );
		while ( $pos + 8 <= $total ) {
			$size   = unpack( 'V', substr( $bytes, $pos + 4, 4 ) )[1];
			$length = 8 + $size + ( $size % 2 );
			if ( $pos + 8 + $size > $total ) {
				return null;
			}
			$chunks[] = [
				'type'   => substr( $bytes, $pos, 4 ),
				'offset' => $pos,
				'length' => min( $length, $total - $pos ),
			];
			$pos     += $length;
		}
		return $chunks ? $chunks : null;
	}

	/**
	 * File-unavailable error.
	 *
	 * @return WP_Error
	 */
	private static function unavailable(): WP_Error {
		return self::error( self::ERROR_FILE, __( 'The image file is missing, unreadable or not stored on this server.', 'media-library-tools' ), 422 );
	}

	/**
	 * Decode/encode failure.
	 *
	 * @return WP_Error
	 */
	private static function failed(): WP_Error {
		return self::error( self::ERROR_FAILED, __( 'A preview of this image could not be made.', 'media-library-tools' ), 422 );
	}

	/**
	 * Error with a fixed code and message.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 *
	 * @return WP_Error
	 */
	private static function error( string $code, string $message, int $status ): WP_Error {
		return AbilityGuard::error( $code, $message, $status );
	}
}
