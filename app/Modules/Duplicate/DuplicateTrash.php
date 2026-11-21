<?php
/**
 * Move one duplicate image to the trash from the Duplicates page.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Duplicate;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\Regenerate\RegenerateLock;
use TinySolutions\mlt\Modules\Trash\MediaTrash;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Post;

/**
 * Move to Trash on the Duplicates page (Free). One image at a time,
 * through MediaTrash::trash(), which never deletes permanently: the
 * attachment, its files, WebP/AVIF copies and backups stay restorable.
 * References are not checked or changed (the page always warns); Duplicate
 * Merge (Pro) is the way to move references first.
 */
class DuplicateTrash {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Move one image to the trash for the current user.
	 *
	 * Refused for anything but a library attachment the user may delete,
	 * when it is already in the trash, while the trash is off, for the site
	 * logo or icon, and while compression, conversion, EXIF work, a rename or
	 * a thumbnail regeneration holds it. The image is locked
	 * (RegenerateLock) while it is trashed. Only after a successful trash is
	 * it removed from the duplicate results; a failure changes nothing.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array{updated: bool, code: string, message: string} code is '' on success.
	 */
	public function trash( int $attachment_id ): array {
		$post = $attachment_id > 0 ? get_post( $attachment_id ) : null;
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return self::refused( 'not_found', esc_html__( 'The image was not found.', 'media-library-tools' ) );
		}
		if ( 'trash' === $post->post_status ) {
			return self::refused( 'already_trashed', esc_html__( 'The image is already in the trash.', 'media-library-tools' ) );
		}
		if ( 'inherit' !== $post->post_status ) {
			return self::refused( 'not_found', esc_html__( 'The image was not found.', 'media-library-tools' ) );
		}
		if ( ! current_user_can( 'delete_post', $attachment_id ) ) {
			return self::refused( 'forbidden', esc_html__( 'You may not delete this image.', 'media-library-tools' ) );
		}
		if ( ! MediaTrash::trash_enabled() ) {
			return self::refused( 'trash_disabled', esc_html__( 'The trash is turned off on this site (EMPTY_TRASH_DAYS is 0), so the image was not moved. Images are never deleted permanently here.', 'media-library-tools' ) );
		}
		if ( in_array( $attachment_id, MediaTrash::protected_ids(), true ) ) {
			return self::refused( 'site_media', esc_html__( 'This image is the site logo or site icon, so it was not moved to the trash.', 'media-library-tools' ) );
		}

		$token = RegenerateLock::acquire( $attachment_id );
		if ( null === $token ) {
			return self::locked();
		}
		try {
			if ( AttachmentProcessor::instance()->is_locked( $attachment_id )
				|| AttachmentConverter::instance()->is_locked( $attachment_id )
				|| ScanStartLock::instance()->is_held( ExifStripper::LOCK_PREFIX . $attachment_id ) ) {
				return self::locked();
			}
			$trashed = MediaTrash::trash( $attachment_id );
			if ( 'trashed' !== ( $trashed['result'] ?? '' ) ) {
				return 'trash_disabled' === ( $trashed['reason'] ?? '' )
					? self::refused( 'trash_disabled', esc_html__( 'The trash is turned off on this site, so the image was not moved.', 'media-library-tools' ) )
					: self::refused( 'trash_failed', esc_html__( 'The image could not be moved to the trash. Nothing was changed.', 'media-library-tools' ) );
			}
			Fns::DB()->delete( 'tsmlt_duplicate_file' )
				->where( 'attachment_id', '=', $attachment_id )
				->execute();
			return [
				'updated' => true,
				'code'    => '',
				'message' => esc_html__( 'The image was moved to the trash. You can restore it from the Media Library trash.', 'media-library-tools' ),
			];
		} finally {
			RegenerateLock::release( $attachment_id, $token );
		}
	}

	/**
	 * A refusal.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 *
	 * @return array{updated: bool, code: string, message: string}
	 */
	private static function refused( string $code, string $message ): array {
		return [
			'updated' => false,
			'code'    => $code,
			'message' => $message,
		];
	}

	/**
	 * Refusal while another operation holds the image.
	 *
	 * @return array{updated: bool, code: string, message: string}
	 */
	private static function locked(): array {
		return self::refused( 'locked', esc_html__( 'Another operation (compression, conversion, EXIF, rename or thumbnail regeneration) is working on this image. Try again when it has finished.', 'media-library-tools' ) );
	}
}
