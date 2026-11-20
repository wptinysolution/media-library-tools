<?php
/**
 * Test double for JpegMetadataStripper failure handling.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\ExifData\JpegMetadataStripper;

/**
 * A stripper whose writes stop half way (a full disk).
 */
class TsmltShortWriteStripper extends JpegMetadataStripper {

	/**
	 * Write only half of the bytes.
	 *
	 * @param string $path  Path.
	 * @param string $bytes Bytes.
	 *
	 * @return int
	 */
	protected static function write_file( string $path, string $bytes ): int {
		return parent::write_file( $path, substr( $bytes, 0, (int) ( strlen( $bytes ) / 2 ) ) );
	}
}
