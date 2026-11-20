<?php
/**
 * Test double for JpegMetadataStripper failure handling.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\ExifData\JpegMetadataStripper;

/**
 * A stripper whose final rename fails.
 */
class TsmltFailedRenameStripper extends JpegMetadataStripper {

	/**
	 * Refuse the rename.
	 *
	 * @param string $from Temporary file.
	 * @param string $to   Original file.
	 *
	 * @return bool
	 */
	protected static function replace_file( string $from, string $to ): bool {
		return false;
	}
}
