<?php
/**
 * JPEG EXIF binary writer — writes EXIF metadata to JPEG files using pure PHP.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\ExifData;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * JpegExifWriter — pure PHP JPEG EXIF binary writer.
 * Constructs a valid TIFF/EXIF IFD structure and injects as APP1 segment.
 */
class JpegExifWriter {

	/**
	 * EXIF tag IDs and their types.
	 *
	 * @var array
	 */
	private static $exif_tags = [
		'Make'                => [ 'id' => 0x010F, 'type' => 2, 'ifd' => 'IFD0' ],      // ASCII, IFD0
		'Model'               => [ 'id' => 0x0110, 'type' => 2, 'ifd' => 'IFD0' ],      // ASCII, IFD0
		'DateTime'            => [ 'id' => 0x0132, 'type' => 2, 'ifd' => 'IFD0' ],      // ASCII, IFD0
		'DateTimeOriginal'    => [ 'id' => 0x9003, 'type' => 2, 'ifd' => 'EXIF' ],     // ASCII, SubIFD
		'ISOSpeedRatings'     => [ 'id' => 0x8827, 'type' => 3, 'ifd' => 'EXIF' ],     // SHORT, SubIFD
		'FNumber'             => [ 'id' => 0x829D, 'type' => 5, 'ifd' => 'EXIF' ],     // RATIONAL, SubIFD
		'ExposureTime'        => [ 'id' => 0x829A, 'type' => 5, 'ifd' => 'EXIF' ],     // RATIONAL, SubIFD
		'GPSLatitudeRef'      => [ 'id' => 0x0001, 'type' => 2, 'ifd' => 'GPS' ],      // ASCII, GPS IFD
		'GPSLatitude'         => [ 'id' => 0x0002, 'type' => 5, 'ifd' => 'GPS' ],      // RATIONAL×3, GPS IFD
		'GPSLongitudeRef'     => [ 'id' => 0x0003, 'type' => 2, 'ifd' => 'GPS' ],      // ASCII, GPS IFD
		'GPSLongitude'        => [ 'id' => 0x0004, 'type' => 5, 'ifd' => 'GPS' ],      // RATIONAL×3, GPS IFD
	];

	/**
	 * Write EXIF data to a JPEG file.
	 *
	 * @param string $file_path Path to JPEG file.
	 * @param array  $fields    EXIF fields to write (key => value).
	 *
	 * @return array{success: bool, message: string}
	 */
	public static function write( string $file_path, array $fields ): array {
		// Validate file.
		if ( ! file_exists( $file_path ) || ! is_writable( $file_path ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'File does not exist or is not writable.', 'media-library-tools' ),
			];
		}

		// Validate MIME type.
		$mime_type = mime_content_type( $file_path );
		if ( ! in_array( $mime_type, [ 'image/jpeg', 'image/jpg' ], true ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'EXIF writing is only supported for JPEG images.', 'media-library-tools' ),
			];
		}

		// Read file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$file_data = file_get_contents( $file_path );
		if ( false === $file_data ) {
			return [
				'success' => false,
				'message' => esc_html__( 'Failed to read file.', 'media-library-tools' ),
			];
		}

		// Verify JPEG SOI marker.
		if ( strlen( $file_data ) < 2 || "\xFF\xD8" !== substr( $file_data, 0, 2 ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'Invalid JPEG file format.', 'media-library-tools' ),
			];
		}

		// Create backup.
		$backup_path = $file_path . '.backup';
		if ( ! copy( $file_path, $backup_path ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'Failed to create backup file.', 'media-library-tools' ),
			];
		}

		try {
			// Remove old APP1 segment and inject new one.
			$new_data = self::inject_app1_segment( $file_data, $fields );
			if ( false === $new_data ) {
				@unlink( $backup_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return [
					'success' => false,
					'message' => esc_html__( 'Failed to build EXIF data.', 'media-library-tools' ),
				];
			}

			// Write new data.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === file_put_contents( $file_path, $new_data ) ) {
				// Restore backup.
				if ( file_exists( $backup_path ) ) {
					copy( $backup_path, $file_path );
				}
				@unlink( $backup_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return [
					'success' => false,
					'message' => esc_html__( 'Failed to write file.', 'media-library-tools' ),
				];
			}

			// Clean up backup.
			@unlink( $backup_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			return [
				'success' => true,
				'message' => esc_html__( 'EXIF data updated successfully.', 'media-library-tools' ),
			];
		} catch ( \Exception $e ) {
			// Restore backup on exception.
			if ( file_exists( $backup_path ) ) {
				copy( $backup_path, $file_path );
				@unlink( $backup_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			return [
				'success' => false,
				'message' => esc_html__( 'An error occurred while updating EXIF data.', 'media-library-tools' ),
			];
		}
	}

	/**
	 * Inject a new APP1 segment into JPEG data.
	 *
	 * @param string $file_data Original JPEG file data.
	 * @param array  $fields    Fields to write.
	 *
	 * @return string|false New JPEG data or false on error.
	 */
	private static function inject_app1_segment( string $file_data, array $fields ) {
		// Parse JPEG segments.
		$segments = self::parse_jpeg_segments( $file_data );
		if ( false === $segments ) {
			return false;
		}

		// Remove old APP1 (EXIF) segments.
		$segments['segments'] = array_values(
			array_filter(
				$segments['segments'],
				function ( $seg ) {
					// Only remove APP1 segments that contain EXIF data.
					if ( isset( $seg['marker'] ) && 0xE1 === $seg['marker'] ) {
						return 0 !== strncmp( $seg['data'] ?? '', "Exif\x00\x00", 6 );
					}
					return true;
				}
			)
		);

		// Build new APP1 segment.
		$app1_data = self::build_app1_segment( $fields );
		if ( false === $app1_data ) {
			return false;
		}

		// Construct new JPEG: SOI + new APP1 + remaining segments + SOS/image data.
		$new_data = "\xFF\xD8"; // SOI marker.

		// Insert APP1 right after SOI.
		$app1_length = strlen( $app1_data ) + 2;
		$new_data   .= "\xFF\xE1" . pack( 'n', $app1_length ) . $app1_data;

		// Add remaining segments.
		foreach ( $segments['segments'] as $seg ) {
			if ( ! isset( $seg['marker'] ) ) {
				continue;
			}

			$marker = $seg['marker'];

			// Skip EOI marker (we add it at end) and SOS (handled with image data).
			if ( 0xD9 === $marker ) {
				continue;
			}

			// SOS marker: append marker + length + data + everything after (image data).
			if ( 0xDA === $marker ) {
				$new_data .= "\xFF\xDA" . pack( 'n', strlen( $seg['data'] ) + 2 ) . $seg['data'];
				// Append raw image data after SOS.
				if ( isset( $seg['image_data'] ) ) {
					$new_data .= $seg['image_data'];
				}
				continue;
			}

			$new_data .= "\xFF" . pack( 'C', $marker ) . pack( 'n', strlen( $seg['data'] ) + 2 ) . $seg['data'];
		}

		// Add EOI marker.
		$new_data .= "\xFF\xD9";

		return $new_data;
	}

	/**
	 * Parse JPEG segments.
	 *
	 * @param string $file_data JPEG file data.
	 *
	 * @return array|false Array with segments or false on error.
	 */
	private static function parse_jpeg_segments( string $file_data ) {
		$segments = [];
		$pos      = 2; // Skip SOI marker.
		$length   = strlen( $file_data );

		while ( $pos < $length - 1 ) {
			// Find next marker.
			if ( "\xFF" !== $file_data[ $pos ] ) {
				break; // Invalid marker.
			}
			$pos++;

			// Skip padding bytes (0xFF).
			while ( $pos < $length && "\xFF" === $file_data[ $pos ] ) {
				$pos++;
			}

			if ( $pos >= $length ) {
				break;
			}

			$segment_type = ord( $file_data[ $pos ] );
			$pos++;

			// EOI marker (no data).
			if ( 0xD9 === $segment_type ) {
				$segments[] = [ 'marker' => $segment_type, 'data' => '' ];
				break;
			}

			// SOS marker — everything after the SOS header is image data until EOI.
			if ( 0xDA === $segment_type ) {
				if ( $pos + 1 >= $length ) {
					break;
				}
				$seg_length  = unpack( 'n', substr( $file_data, $pos, 2 ) )[1];
				$header_data = substr( $file_data, $pos + 2, $seg_length - 2 );
				$pos        += $seg_length;

				// Everything from here to the end (minus EOI) is compressed image data.
				// Find EOI marker.
				$eoi_pos = strrpos( $file_data, "\xFF\xD9" );
				if ( false === $eoi_pos || $eoi_pos < $pos ) {
					$image_data = substr( $file_data, $pos );
				} else {
					$image_data = substr( $file_data, $pos, $eoi_pos - $pos );
				}

				$segments[] = [
					'marker'     => $segment_type,
					'data'       => $header_data,
					'image_data' => $image_data,
				];
				break; // SOS is always last segment before image data.
			}

			// Regular marker with length prefix.
			if ( $pos + 1 >= $length ) {
				break;
			}

			$seg_length   = unpack( 'n', substr( $file_data, $pos, 2 ) )[1];
			$segment_data = substr( $file_data, $pos + 2, $seg_length - 2 );
			$pos         += $seg_length;

			$segments[] = [
				'marker' => $segment_type,
				'data'   => $segment_data,
			];
		}

		return [ 'segments' => $segments ];
	}

	/**
	 * Build APP1 segment with EXIF data.
	 *
	 * @param array $fields EXIF fields to write.
	 *
	 * @return string|false APP1 data (without APP1 marker/length) or false on error.
	 */
	private static function build_app1_segment( array $fields ) {
		// EXIF header (6 bytes).
		$exif_header = "Exif\x00\x00";

		// Separate fields by IFD.
		$ifd0_fields = [];
		$exif_fields = [];
		$gps_fields  = [];

		foreach ( $fields as $key => $value ) {
			if ( ! isset( self::$exif_tags[ $key ] ) ) {
				continue;
			}
			$ifd = self::$exif_tags[ $key ]['ifd'];
			if ( 'IFD0' === $ifd ) {
				$ifd0_fields[ $key ] = $value;
			} elseif ( 'EXIF' === $ifd ) {
				$exif_fields[ $key ] = $value;
			} elseif ( 'GPS' === $ifd ) {
				$gps_fields[ $key ] = $value;
			}
		}

		$has_exif_ifd = ! empty( $exif_fields );
		$has_gps_ifd  = ! empty( $gps_fields );

		// Count IFD0 entries (user fields + pointer entries).
		$ifd0_count = count( $ifd0_fields );
		if ( $has_exif_ifd ) {
			$ifd0_count++; // ExifIFD pointer.
		}
		if ( $has_gps_ifd ) {
			$ifd0_count++; // GPSIFD pointer.
		}

		$exif_count = count( $exif_fields );
		$gps_count  = count( $gps_fields );

		// Calculate fixed structure sizes (offsets relative to TIFF header start).
		$tiff_header_size = 8;

		$ifd0_offset = $tiff_header_size;
		$ifd0_size   = 2 + ( 12 * $ifd0_count ) + 4;

		$exif_ifd_offset = $ifd0_offset + $ifd0_size;
		$exif_ifd_size   = $has_exif_ifd ? ( 2 + ( 12 * $exif_count ) + 4 ) : 0;

		$gps_ifd_offset = $exif_ifd_offset + $exif_ifd_size;
		$gps_ifd_size   = $has_gps_ifd ? ( 2 + ( 12 * $gps_count ) + 4 ) : 0;

		// Overflow data starts after all IFDs.
		$overflow_start = $gps_ifd_offset + $gps_ifd_size;
		$overflow_data  = '';

		// Build IFD entries.
		$ifd0_entries = self::build_ifd_entries( $ifd0_fields, $overflow_start, $overflow_data );

		// Add ExifIFD pointer to IFD0.
		if ( $has_exif_ifd ) {
			$ifd0_entries[] = pack( 'v', 0x8769 ) . pack( 'v', 4 ) . pack( 'V', 1 ) . pack( 'V', $exif_ifd_offset );
		}

		// Add GPSIFD pointer to IFD0.
		if ( $has_gps_ifd ) {
			$ifd0_entries[] = pack( 'v', 0x8825 ) . pack( 'v', 4 ) . pack( 'V', 1 ) . pack( 'V', $gps_ifd_offset );
		}

		// Sort IFD0 entries by tag ID (EXIF spec requires sorted tags).
		usort(
			$ifd0_entries,
			function ( $a, $b ) {
				return unpack( 'v', substr( $a, 0, 2 ) )[1] - unpack( 'v', substr( $b, 0, 2 ) )[1];
			}
		);

		// Build EXIF IFD entries.
		$exif_entries = $has_exif_ifd ? self::build_ifd_entries( $exif_fields, $overflow_start, $overflow_data ) : [];

		// Build GPS IFD entries.
		$gps_entries = $has_gps_ifd ? self::build_ifd_entries( $gps_fields, $overflow_start, $overflow_data ) : [];

		// Assemble TIFF structure.
		$tiff = '';

		// TIFF header: byte order (II = little-endian) + magic 42 + offset to IFD0.
		$tiff .= "II";
		$tiff .= pack( 'v', 42 );
		$tiff .= pack( 'V', $ifd0_offset );

		// IFD0.
		$tiff .= pack( 'v', $ifd0_count );
		foreach ( $ifd0_entries as $entry ) {
			$tiff .= $entry;
		}
		$tiff .= pack( 'V', 0 ); // Next IFD offset (0 = none).

		// EXIF SubIFD.
		if ( $has_exif_ifd ) {
			$tiff .= pack( 'v', $exif_count );
			foreach ( $exif_entries as $entry ) {
				$tiff .= $entry;
			}
			$tiff .= pack( 'V', 0 );
		}

		// GPS IFD.
		if ( $has_gps_ifd ) {
			$tiff .= pack( 'v', $gps_count );
			foreach ( $gps_entries as $entry ) {
				$tiff .= $entry;
			}
			$tiff .= pack( 'V', 0 );
		}

		// Overflow value data.
		$tiff .= $overflow_data;

		return $exif_header . $tiff;
	}

	/**
	 * Build IFD entries for a set of fields.
	 *
	 * @param array  $fields         EXIF fields (key => value).
	 * @param int    $overflow_start Offset where overflow data begins.
	 * @param string $overflow_data  Reference to overflow data buffer.
	 *
	 * @return array Array of 12-byte IFD entry strings.
	 */
	private static function build_ifd_entries( array $fields, int $overflow_start, string &$overflow_data ): array {
		$entries = [];

		foreach ( $fields as $key => $value ) {
			if ( ! isset( self::$exif_tags[ $key ] ) ) {
				continue;
			}

			$tag_info = self::$exif_tags[ $key ];
			$tag_id   = $tag_info['id'];
			$type     = $tag_info['type'];

			$encoded = self::encode_value( $value, $type );
			if ( false === $encoded ) {
				continue;
			}

			list( $data, $count ) = $encoded;

			// Build 12-byte IFD entry.
			$entry = pack( 'v', $tag_id );  // Tag ID (2 bytes).
			$entry .= pack( 'v', $type );   // Data type (2 bytes).
			$entry .= pack( 'V', $count );  // Value count (4 bytes).

			if ( strlen( $data ) <= 4 ) {
				// Value fits inline — pad to 4 bytes.
				$entry .= str_pad( $data, 4, "\x00" );
			} else {
				// Value goes to overflow — store offset.
				$offset  = $overflow_start + strlen( $overflow_data );
				$entry  .= pack( 'V', $offset );
				$overflow_data .= $data;
			}

			$entries[] = $entry;
		}

		// Sort by tag ID (EXIF spec requires ascending order).
		usort(
			$entries,
			function ( $a, $b ) {
				return unpack( 'v', substr( $a, 0, 2 ) )[1] - unpack( 'v', substr( $b, 0, 2 ) )[1];
			}
		);

		return $entries;
	}

	/**
	 * Encode a value based on EXIF type.
	 *
	 * @param mixed $value Value to encode.
	 * @param int   $type  EXIF type.
	 *
	 * @return array|false [encoded_data, count] or false on error.
	 */
	private static function encode_value( $value, int $type ) {
		switch ( $type ) {
			case 2: // ASCII string (null-terminated).
				if ( ! is_string( $value ) ) {
					$value = (string) $value;
				}
				if ( '' === $value || "\x00" !== substr( $value, -1 ) ) {
					$value .= "\x00";
				}
				return [ $value, strlen( $value ) ];

			case 3: // SHORT (2 bytes, unsigned, little-endian).
				$num = (int) $value;
				return [ pack( 'v', $num ), 1 ];

			case 4: // LONG (4 bytes, unsigned, little-endian).
				$num = (int) $value;
				return [ pack( 'V', $num ), 1 ];

			case 5: // RATIONAL (two LONGs: numerator/denominator, 8 bytes total).
				return self::encode_rational_value( $value );

			default:
				return false;
		}
	}

	/**
	 * Encode a rational value.
	 *
	 * @param mixed $value The value to encode.
	 *
	 * @return array|false [encoded_data, count] or false on error.
	 */
	private static function encode_rational_value( $value ) {
		if ( is_string( $value ) && strpos( $value, ' ' ) !== false ) {
			// GPS DMS format: "deg/1 min/1 sec/100" — 3 rationals.
			$parts = explode( ' ', trim( $value ) );
			if ( 3 !== count( $parts ) ) {
				return false;
			}
			$data = '';
			foreach ( $parts as $part ) {
				$rational = explode( '/', $part, 2 );
				if ( 2 !== count( $rational ) ) {
					return false;
				}
				$data .= pack( 'V', (int) $rational[0] ) . pack( 'V', (int) $rational[1] );
			}
			return [ $data, 3 ];
		}

		if ( is_string( $value ) && strpos( $value, '/' ) !== false ) {
			// Single rational: "N/D".
			$parts = explode( '/', $value, 2 );
			$num   = (int) $parts[0];
			$denom = (int) $parts[1];
			if ( 0 === $denom ) {
				$denom = 1;
			}
			return [ pack( 'V', $num ) . pack( 'V', $denom ), 1 ];
		}

		if ( is_numeric( $value ) ) {
			// Convert float to rational.
			$float = (float) $value;
			$denom = 1000;
			$num   = (int) round( $float * $denom );
			return [ pack( 'V', $num ) . pack( 'V', $denom ), 1 ];
		}

		return false;
	}
}
