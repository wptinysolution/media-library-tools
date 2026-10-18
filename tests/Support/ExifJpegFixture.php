<?php
/**
 * Builds JPEG test fixtures with known EXIF data.
 *
 * Inserts a minimal little-endian EXIF (APP1) segment — IFD0, EXIF and GPS
 * directories — after the SOI marker of an EXIF-free JPEG. Used so the tests
 * control exactly which camera, exposure, serial number and GPS values an
 * image carries, without committing binary fixtures or using the plugin's own
 * EXIF writer.
 *
 * @package TinySolutions\mlt
 */

/**
 * ExifJpegFixture
 */
class TsmltExifJpegFixture {

	/**
	 * Tag types.
	 */
	const ASCII    = 2;
	const SHORT    = 3;
	const LONG     = 4;
	const RATIONAL = 5;

	/**
	 * Build a JPEG with the given EXIF entries.
	 *
	 * Each entry is [ tag, type, value ]: a string for ASCII, an int for SHORT
	 * and LONG, and a list of [ numerator, denominator ] pairs for RATIONAL.
	 *
	 * @param string $base_jpeg Path to a JPEG without an EXIF segment.
	 * @param array  $ifd0      IFD0 entries (camera).
	 * @param array  $exif      EXIF sub-IFD entries (exposure, serial).
	 * @param array  $gps       GPS sub-IFD entries.
	 *
	 * @return string JPEG bytes.
	 */
	public static function build( string $base_jpeg, array $ifd0, array $exif, array $gps ): string {
		$jpeg = (string) file_get_contents( $base_jpeg ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.

		$ifd0[] = [ 0x8769, self::LONG, 0 ];
		$ifd0[] = [ 0x8825, self::LONG, 0 ];
		usort(
			$ifd0,
			static function ( $a, $b ) {
				return $a[0] <=> $b[0];
			}
		);

		// Two passes: sizes do not depend on the pointer values.
		$ifd0_bytes = self::ifd( $ifd0, 8 );
		$exif_at    = 8 + strlen( $ifd0_bytes );
		$exif_bytes = self::ifd( $exif, $exif_at );
		$gps_at     = $exif_at + strlen( $exif_bytes );
		foreach ( $ifd0 as &$entry ) {
			if ( 0x8769 === $entry[0] ) {
				$entry[2] = $exif_at;
			} elseif ( 0x8825 === $entry[0] ) {
				$entry[2] = $gps_at;
			}
		}
		unset( $entry );
		$ifd0_bytes = self::ifd( $ifd0, 8 );
		$gps_bytes  = self::ifd( $gps, $gps_at );

		$tiff = 'II' . pack( 'v', 42 ) . pack( 'V', 8 ) . $ifd0_bytes . $exif_bytes . $gps_bytes;
		$app1 = "Exif\0\0" . $tiff;

		return substr( $jpeg, 0, 2 ) . "\xFF\xE1" . pack( 'n', strlen( $app1 ) + 2 ) . $app1 . substr( $jpeg, 2 );
	}

	/**
	 * Serialize one image file directory (entries, next-IFD pointer, values).
	 *
	 * @param array $entries Entries.
	 * @param int   $offset  Offset of the IFD from the TIFF header.
	 *
	 * @return string
	 */
	private static function ifd( array $entries, int $offset ): string {
		$data_at = $offset + 2 + 12 * count( $entries ) + 4;
		$ifd     = pack( 'v', count( $entries ) );
		$data    = '';

		foreach ( $entries as list( $tag, $type, $value ) ) {
			if ( self::ASCII === $type ) {
				$bytes = $value . "\0";
				$count = strlen( $bytes );
			} elseif ( self::SHORT === $type ) {
				$bytes = pack( 'v', $value );
				$count = 1;
			} elseif ( self::LONG === $type ) {
				$bytes = pack( 'V', $value );
				$count = 1;
			} else {
				$bytes = '';
				foreach ( $value as list( $numerator, $denominator ) ) {
					$bytes .= pack( 'VV', $numerator, $denominator );
				}
				$count = count( $value );
			}

			if ( strlen( $bytes ) <= 4 ) {
				$ifd .= pack( 'vvV', $tag, $type, $count ) . str_pad( $bytes, 4, "\0" );
			} else {
				$ifd  .= pack( 'vvVV', $tag, $type, $count, $data_at + strlen( $data ) );
				$data .= $bytes . ( strlen( $bytes ) % 2 ? "\0" : '' );
			}
		}

		return $ifd . pack( 'V', 0 ) . $data;
	}
}
