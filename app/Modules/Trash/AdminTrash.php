<?php
/**
 * Native WordPress trash and restore for the plugin's admin actions.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Trash;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use WP_Post;

/**
 * Moves attachments to the WordPress trash and restores them for the admin
 * screens (the Used Where page and the media table's bulk actions), with
 * WordPress's own wp_trash_post() and wp_untrash_post() only. Separate from
 * the MCP trash service (MediaTrash), whose rules are stricter.
 *
 * Trash is refused while it is off (EMPTY_TRASH_DAYS is 0, negative or not
 * a whole number), because wp_trash_post() then deletes permanently; while
 * an attachment is being trashed, its permanent deletion is refused as well.
 * Each item must be an attachment the user may delete, and counts as done
 * only when it is read back in the expected status. Nothing here deletes.
 */
class AdminTrash {

	/**
	 * Filter that can lower (never raise) the trash days the admin trash
	 * actions see; 0 or less, or a value that is not a whole number, turns
	 * them off.
	 *
	 * @var string
	 */
	const DAYS_FILTER = 'tsmlt_admin_trash_days';

	/**
	 * Attachment whose permanent deletion is refused while it is being
	 * trashed (0 when none).
	 *
	 * @var int
	 */
	private static $guarded = 0;

	/**
	 * Trash days for the admin actions: EMPTY_TRASH_DAYS (read as
	 * MediaTrash::empty_trash_days() does: 0 when undefined or not a whole
	 * number), lowered by DAYS_FILTER if it returns less. A filter result
	 * that is not a whole number turns trash off.
	 *
	 * @return int
	 */
	public static function trash_days(): int {
		$days     = MediaTrash::empty_trash_days();
		$filtered = apply_filters( self::DAYS_FILTER, $days );
		if ( ! is_int( $filtered ) && ! ( is_string( $filtered ) && 1 === preg_match( '/^-?\d+$/', trim( $filtered ) ) ) ) {
			return 0;
		}
		return min( $days, (int) $filtered );
	}

	/**
	 * Whether the admin actions may move media to the trash.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return self::trash_days() > 0;
	}

	/**
	 * Message shown when trash is off.
	 *
	 * @return string
	 */
	public static function disabled_message(): string {
		return __( 'Trash is disabled on this site (EMPTY_TRASH_DAYS is 0 or not a whole number of days), so media cannot be moved to the trash. Nothing was changed.', 'media-library-tools' );
	}

	/**
	 * Move one attachment to the trash. Never called through to WordPress
	 * when trash is off.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool Whether it is in the trash now because of this call.
	 */
	public static function trash( int $attachment_id ): bool {
		if ( ! self::enabled() ) {
			return false;
		}
		$post = self::deletable( $attachment_id );
		if ( null === $post || 'trash' === $post->post_status ) {
			return false;
		}
		self::$guarded = $attachment_id;
		add_filter( 'pre_delete_attachment', [ __CLASS__, 'refuse_permanent_delete' ], PHP_INT_MAX, 2 );
		add_filter( 'pre_delete_post', [ __CLASS__, 'refuse_permanent_delete' ], PHP_INT_MAX, 2 );
		try {
			wp_trash_post( $attachment_id );
		} finally {
			remove_filter( 'pre_delete_attachment', [ __CLASS__, 'refuse_permanent_delete' ], PHP_INT_MAX );
			remove_filter( 'pre_delete_post', [ __CLASS__, 'refuse_permanent_delete' ], PHP_INT_MAX );
			self::$guarded = 0;
		}
		return 'trash' === self::status_now( $attachment_id );
	}

	/**
	 * Restore one attachment from the trash.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool Whether it is back in the media library now.
	 */
	public static function restore( int $attachment_id ): bool {
		$post = self::deletable( $attachment_id );
		if ( null === $post || 'trash' !== $post->post_status ) {
			return false;
		}
		wp_untrash_post( $attachment_id );
		return 'inherit' === self::status_now( $attachment_id );
	}

	/**
	 * Refuse the permanent deletion of the attachment being trashed. Hooked
	 * on pre_delete_attachment and pre_delete_post only while trash() calls
	 * wp_trash_post().
	 *
	 * @param mixed $check Earlier filters' decision.
	 * @param mixed $post  Post about to be deleted.
	 *
	 * @return mixed False for the guarded attachment, else $check.
	 */
	public static function refuse_permanent_delete( $check, $post ) {
		if ( self::$guarded > 0 && $post instanceof WP_Post && (int) $post->ID === self::$guarded ) {
			return false;
		}
		return $check;
	}

	/**
	 * An attachment the current user may delete, read fresh; null for
	 * anything else.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return WP_Post|null
	 */
	private static function deletable( int $attachment_id ): ?WP_Post {
		if ( $attachment_id < 1 ) {
			return null;
		}
		clean_post_cache( $attachment_id );
		$post = get_post( $attachment_id );
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || ! current_user_can( 'delete_post', $attachment_id ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * An attachment's status read fresh, or '' when it no longer exists.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string
	 */
	private static function status_now( int $attachment_id ): string {
		clean_post_cache( $attachment_id );
		$post = get_post( $attachment_id );
		return $post instanceof WP_Post && 'attachment' === $post->post_type ? (string) $post->post_status : '';
	}
}
