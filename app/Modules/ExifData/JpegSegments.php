<?php
/**
 * JPEG segment parser shared by the EXIF tools.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\ExifData;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Locates the marker segments of a JPEG before its image data. Used by the
 * lossless metadata stripper and by Pro's GPS-only stripper, so both read a
 * JPEG the same way.
 */
class JpegSegments {

	/**
	 * Locate the metadata segments of a JPEG: every marker segment from the
	 * start of the file up to the first start-of-scan (or end-of-image)
	 * marker, where the image data begins.
	 *
	 * @param string $bytes JPEG bytes.
	 *
	 * @return array{segments: array<int, array{marker: int, start: int, length: int}>, image_start: int}|null
	 *         Each segment's marker, payload offset and payload length
	 *         (without the marker and length bytes); null when malformed.
	 */
	public static function layout( string $bytes ): ?array {
		$length = strlen( $bytes );
		if ( $length < 4 || "\xFF\xD8" !== substr( $bytes, 0, 2 ) ) {
			return null;
		}

		$segments = [];
		$pos      = 2;
		while ( $pos + 2 <= $length ) {
			if ( "\xFF" !== $bytes[ $pos ] ) {
				return null;
			}
			$marker = ord( $bytes[ $pos + 1 ] );
			if ( 0xFF === $marker ) {
				++$pos; // Fill byte.
				continue;
			}
			if ( 0xDA === $marker || 0xD9 === $marker ) {
				// Start of scan or end of image: no metadata after this point.
				return [
					'segments'    => $segments,
					'image_start' => $pos,
				];
			}
			if ( 0x01 === $marker || ( $marker >= 0xD0 && $marker <= 0xD7 ) ) {
				$pos += 2; // Markers without a length.
				continue;
			}
			if ( $pos + 4 > $length ) {
				return null;
			}
			$segment_length = unpack( 'n', substr( $bytes, $pos + 2, 2 ) )[1];
			if ( $segment_length < 2 || $pos + 2 + $segment_length > $length ) {
				return null;
			}
			$segments[] = [
				'marker' => $marker,
				'start'  => $pos + 4,
				'length' => $segment_length - 2,
			];
			$pos       += 2 + $segment_length;
		}

		return null;
	}

	/**
	 * A segment's payload (without marker and length bytes).
	 *
	 * @param string $bytes   JPEG bytes.
	 * @param array  $segment Segment from layout().
	 *
	 * @return string
	 */
	public static function payload( string $bytes, array $segment ): string {
		return (string) substr( $bytes, $segment['start'], $segment['length'] );
	}

	/**
	 * A segment's complete bytes (marker, length and payload).
	 *
	 * @param string $bytes   JPEG bytes.
	 * @param array  $segment Segment from layout().
	 *
	 * @return string
	 */
	public static function raw( string $bytes, array $segment ): string {
		return (string) substr( $bytes, $segment['start'] - 4, $segment['length'] + 4 );
	}
}
