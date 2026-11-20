<?php
/**
 * Per-attachment lock held while an image's thumbnails are regenerated.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Regenerate;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Marks an attachment as being regenerated, in post meta, like the
 * compression and conversion locks (`_tsmlt_compression_lock`,
 * `_tsmlt_conversion_lock`): the value starts with the time it was taken
 * and the lock expires after ten minutes, so a fatal error cannot block the
 * image for ever. The value also holds a random token, and only the holder
 * of that token releases it.
 *
 * Compression, conversion and EXIF removal refuse an image while this lock
 * is held; regeneration refuses an image while theirs is.
 */
class RegenerateLock {

	/**
	 * Post meta key.
	 *
	 * @var string
	 */
	const META_KEY = '_tsmlt_regenerate_lock';

	/**
	 * Seconds after which an abandoned lock no longer counts.
	 *
	 * @var int
	 */
	const EXPIRY = 600;

	/**
	 * Take the lock unless it is held.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string|null The lock value to release with, or null when held.
	 */
	public static function acquire( int $attachment_id ): ?string {
		if ( self::is_held( $attachment_id ) ) {
			return null;
		}
		$value = time() . ':' . bin2hex( random_bytes( 8 ) );
		update_post_meta( $attachment_id, self::META_KEY, $value );
		return $value;
	}

	/**
	 * Release the lock if it still holds this value.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $value         Value acquire() returned.
	 *
	 * @return void
	 */
	public static function release( int $attachment_id, string $value ): void {
		delete_post_meta( $attachment_id, self::META_KEY, $value );
	}

	/**
	 * Whether the attachment is being regenerated (a lock taken within EXPIRY).
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool
	 */
	public static function is_held( int $attachment_id ): bool {
		$value = (string) get_post_meta( $attachment_id, self::META_KEY, true );
		if ( '' === $value ) {
			return false;
		}
		return ( time() - (int) strtok( $value, ':' ) ) < self::EXPIRY;
	}
}
