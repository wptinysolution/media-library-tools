<?php
/**
 * Authorization and validation helpers shared by every ability.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use WP_Error;
use WP_Post;

/**
 * AbilityGuard — static authorization helpers and stable error responses.
 *
 * Abilities can be reached by any authenticated MCP client, not only through
 * the nonce-protected admin screens, so every check lives here rather than
 * being re-implemented per ability.
 */
class AbilityGuard {

	/**
	 * Capability required to run any ability. Matches the admin AJAX endpoints,
	 * so MCP access never reaches users the media table itself refuses.
	 *
	 * @var string
	 */
	const BASE_CAPABILITY = 'manage_options';

	/**
	 * Error code: request is not authenticated.
	 *
	 * @var string
	 */
	const ERROR_UNAUTHENTICATED = 'tsmlt_unauthenticated';

	/**
	 * Error code: authenticated user lacks the required capability.
	 *
	 * @var string
	 */
	const ERROR_FORBIDDEN = 'tsmlt_forbidden';

	/**
	 * Error code: input failed validation.
	 *
	 * @var string
	 */
	const ERROR_INVALID_INPUT = 'tsmlt_invalid_input';

	/**
	 * Error code: attachment ID is not a positive integer.
	 *
	 * @var string
	 */
	const ERROR_INVALID_ATTACHMENT_ID = 'tsmlt_invalid_attachment_id';

	/**
	 * Error code: no attachment exists with the given ID.
	 *
	 * @var string
	 */
	const ERROR_ATTACHMENT_NOT_FOUND = 'tsmlt_attachment_not_found';

	/**
	 * Check the base capability for the current user.
	 *
	 * @return true|WP_Error
	 */
	public static function check_base_capability() {
		if ( ! is_user_logged_in() ) {
			return self::error(
				self::ERROR_UNAUTHENTICATED,
				__( 'You must be logged in to use this ability.', 'media-library-tools' ),
				401
			);
		}

		if ( ! current_user_can( self::BASE_CAPABILITY ) ) {
			return self::error(
				self::ERROR_FORBIDDEN,
				__( 'You are not allowed to use this ability.', 'media-library-tools' ),
				403
			);
		}

		return true;
	}

	/**
	 * Resolve an attachment the current user may act on.
	 *
	 * The capability is checked against this specific attachment, so private
	 * or otherwise restricted items are refused even when the base capability
	 * passes.
	 *
	 * @param mixed  $attachment_id Raw attachment ID.
	 * @param string $capability    Meta capability to check, e.g. `read_post` or `edit_post`.
	 *
	 * @return WP_Post|WP_Error
	 */
	public static function resolve_attachment( $attachment_id, string $capability = 'read_post' ) {
		// Reject anything that is not a whole positive number, rather than letting
		// absint() turn "12abc" or -5 into a different, valid ID.
		if ( ! is_numeric( $attachment_id ) || (string) absint( $attachment_id ) !== (string) $attachment_id || ! absint( $attachment_id ) ) {
			return self::error(
				self::ERROR_INVALID_ATTACHMENT_ID,
				__( 'The attachment ID must be a positive integer.', 'media-library-tools' ),
				400
			);
		}

		$attachment = get_post( absint( $attachment_id ) );

		// A non-attachment post is reported as "not found" so the response does
		// not reveal which post types exist at that ID.
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type ) {
			return self::error(
				self::ERROR_ATTACHMENT_NOT_FOUND,
				__( 'No attachment exists with that ID.', 'media-library-tools' ),
				404
			);
		}

		if ( ! self::can_access_attachment( $attachment, $capability ) ) {
			return self::error(
				self::ERROR_FORBIDDEN,
				__( 'You are not allowed to access this attachment.', 'media-library-tools' ),
				403
			);
		}

		return $attachment;
	}

	/**
	 * Whether the current user holds a meta capability for an attachment.
	 *
	 * @param WP_Post $attachment Attachment post.
	 * @param string  $capability Meta capability, e.g. `read_post` or `edit_post`.
	 *
	 * @return bool
	 */
	public static function can_access_attachment( WP_Post $attachment, string $capability = 'read_post' ): bool {
		return 'attachment' === $attachment->post_type && current_user_can( $capability, $attachment->ID );
	}

	/**
	 * Build an error with a stable code and an HTTP-style status.
	 *
	 * @param string $code    Stable error code.
	 * @param string $message Human-readable message.
	 * @param int    $status  HTTP status.
	 *
	 * @return WP_Error
	 */
	public static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, [ 'status' => $status ] );
	}
}
