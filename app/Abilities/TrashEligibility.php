<?php
/**
 * Which attachments may be moved to the trash as unused.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;
use WP_Error;
use WP_Post;

/**
 * Server-side checks shared by tsmlt/preview-trash-unused-media and
 * tsmlt/trash-unused-media. Nothing here changes anything.
 *
 * Request-level gates (check_request()): the site's trash is enabled
 * (with EMPTY_TRASH_DAYS at 0, wp_trash_post() deletes permanently); the
 * Used Where scan is complete, identified by a `scan_id`, not stalled, and
 * its coverage is verified (for example, not made while WooCommerce was
 * inactive, when product category images could not be checked);
 * and no per-post usage re-check (scheduled when a post is saved) is still
 * waiting, since until it runs the records may be out of date.
 *
 * Per attachment (check_ids()), in this order: it exists and the user can
 * read it (otherwise "not_found"), it is not in the trash already, it has
 * the normal "inherit" status, the user may delete it, it was uploaded
 * before the scan started (otherwise the scan did not cover it), and no
 * usage is recorded for it. Any recorded usage counts, including usages in
 * posts the user cannot read and records whose post was deleted later.
 */
class TrashEligibility {

	/**
	 * Most IDs per request.
	 *
	 * @var int
	 */
	const MAX_IDS = 50;

	/**
	 * Hook of the per-post usage re-check scheduled on save.
	 *
	 * @var string
	 */
	const POST_RESCAN_HOOK = 'tsmlt_scan_post_usage';

	/**
	 * Per-attachment reasons.
	 *
	 * @var string[]
	 */
	const REASONS = [ 'not_found', 'already_trashed', 'not_eligible', 'not_allowed', 'not_covered', 'used' ];

	/**
	 * Error codes for the whole request.
	 */
	const ERROR_TRASH_DISABLED  = 'tsmlt_trash_disabled';
	const ERROR_SCAN_INCOMPLETE = 'tsmlt_scan_not_complete';
	const ERROR_SCAN_OUTDATED   = 'tsmlt_scan_outdated';
	const ERROR_UPDATES_PENDING = 'tsmlt_usage_update_pending';
	const ERROR_SCAN_CHANGED    = 'tsmlt_scan_changed';
	const ERROR_COVERAGE        = 'tsmlt_scan_coverage_incomplete';

	/**
	 * Validate the input: exactly `ids`, 1 to 50 unique positive integers.
	 *
	 * @param mixed $input Input.
	 *
	 * @return int[]|WP_Error
	 */
	public static function validate_input( $input ) {
		$input = is_array( $input ) ? $input : [];
		if ( array_diff( array_keys( $input ), [ 'ids' ] ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Only ids is accepted.', 'media-library-tools' ), 400 );
		}
		$ids = $input['ids'] ?? null;
		if ( ! is_array( $ids ) || ! $ids || count( $ids ) > self::MAX_IDS || array_keys( $ids ) !== range( 0, count( $ids ) - 1 ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'ids must be a list of 1 to 50 attachment IDs.', 'media-library-tools' ), 400 );
		}
		foreach ( $ids as $id ) {
			if ( ! is_int( $id ) || $id < 1 ) {
				return AbilityGuard::error( AbilityGuard::ERROR_INVALID_ATTACHMENT_ID, __( 'The attachment ID must be a positive integer.', 'media-library-tools' ), 400 );
			}
		}
		if ( count( array_unique( $ids ) ) !== count( $ids ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'ids must not repeat an ID.', 'media-library-tools' ), 400 );
		}
		return $ids;
	}

	/**
	 * Request-level gates.
	 *
	 * @param array $status From UsageScanStatus::read().
	 *
	 * @return true|WP_Error
	 */
	public static function check_request( array $status ) {
		if ( ! self::trash_enabled() ) {
			return AbilityGuard::error(
				self::ERROR_TRASH_DISABLED,
				__( 'The trash is turned off on this site (EMPTY_TRASH_DAYS is 0), so media would be deleted permanently. Nothing was changed.', 'media-library-tools' ),
				409
			);
		}
		if ( ! $status['complete'] || $status['stalled'] ) {
			$error = AbilityGuard::error(
				self::ERROR_SCAN_INCOMPLETE,
				sprintf(
					/* translators: %s: scan state. */
					__( 'The usage scan is not complete (state: %s). Run tsmlt/start-usage-scan and wait until tsmlt/get-usage-scan-status reports complete. Nothing was changed.', 'media-library-tools' ),
					$status['stalled'] ? 'stalled' : $status['state']
				),
				409
			);
			$error->add_data(
				[
					'status' => 409,
					'state'  => $status['stalled'] ? 'stalled' : $status['state'],
				]
			);
			return $error;
		}
		if ( '' === $status['scan_id'] || '' === $status['started_at'] ) {
			return AbilityGuard::error(
				self::ERROR_SCAN_OUTDATED,
				__( 'The completed usage scan was made by an older plugin version and cannot be verified. Run tsmlt/start-usage-scan again. Nothing was changed.', 'media-library-tools' ),
				409
			);
		}
		if ( ! $status['coverage']['verified'] ) {
			$gaps    = $status['coverage']['gaps'];
			$message = in_array( UsageScanStatus::GAP_NOT_RECORDED, $gaps, true )
				? __( 'The completed usage scan did not record what it covered (it was made by an earlier plugin version). Run tsmlt/start-usage-scan again.', 'media-library-tools' )
				: UsedWhereScanner::coverage_message( $gaps );
			$error   = AbilityGuard::error( self::ERROR_COVERAGE, $message . ' ' . __( 'Nothing was changed.', 'media-library-tools' ), 409 );
			$error->add_data(
				[
					'status' => 409,
					'gaps'   => $gaps,
				]
			);
			return $error;
		}
		if ( self::rescans_pending() ) {
			return AbilityGuard::error(
				self::ERROR_UPDATES_PENDING,
				__( 'Recently saved posts are still waiting to be checked for media usage (WP-Cron). Try again in a minute. Nothing was changed.', 'media-library-tools' ),
				409
			);
		}
		return true;
	}

	/**
	 * Whether two status reads describe the same completed scan run.
	 *
	 * @param array $a Status.
	 * @param array $b Status.
	 *
	 * @return bool
	 */
	public static function same_scan( array $a, array $b ): bool {
		return $a['complete'] && $b['complete'] && ! $b['stalled'] && '' !== $a['scan_id']
			&& $a['scan_id'] === $b['scan_id'] && $a['started_at'] === $b['started_at']
			&& $a['coverage']['verified'] && $b['coverage']['verified'];
	}

	/**
	 * Error for a scan that changed while the request was checked.
	 *
	 * @return WP_Error
	 */
	public static function scan_changed(): WP_Error {
		return AbilityGuard::error(
			self::ERROR_SCAN_CHANGED,
			__( 'The usage scan changed while the request was checked (a new scan started or usage is being updated). Nothing was changed; check again once the scan is complete.', 'media-library-tools' ),
			409
		);
	}

	/**
	 * Per-attachment check: null when eligible, else a reason from REASONS.
	 *
	 * @param int   $id     Attachment ID.
	 * @param array $status Status of the scan being relied on.
	 *
	 * @return string|null
	 */
	public static function check_id( int $id, array $status ): ?string {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || ! current_user_can( 'read_post', $id ) ) {
			return 'not_found';
		}
		if ( 'trash' === $post->post_status ) {
			return 'already_trashed';
		}
		if ( 'inherit' !== $post->post_status ) {
			return 'not_eligible';
		}
		if ( ! current_user_can( 'delete_post', $id ) ) {
			return 'not_allowed';
		}
		$started = strtotime( get_gmt_from_date( $status['started_at'] ) . ' UTC' );
		$created = strtotime( $post->post_date_gmt . ' UTC' );
		if ( ! $started || ! $created || $created > $started ) {
			return 'not_covered';
		}
		$records = get_post_meta( $id, UsedWhereScanner::META_KEY, true );
		if ( ! empty( $records ) ) {
			return 'used';
		}
		return null;
	}

	/**
	 * Human-readable reason.
	 *
	 * @param string $reason Reason code.
	 *
	 * @return string
	 */
	public static function message( string $reason ): string {
		$messages = [
			'not_found'       => __( 'No attachment exists with this ID, or you cannot read it.', 'media-library-tools' ),
			'already_trashed' => __( 'The attachment is already in the trash.', 'media-library-tools' ),
			'not_eligible'    => __( 'The attachment does not have the normal status, so it is not moved.', 'media-library-tools' ),
			'not_allowed'     => __( 'You are not allowed to delete this attachment.', 'media-library-tools' ),
			'not_covered'     => __( 'The attachment was uploaded after the usage scan started, so its usage is unknown. Run a new scan.', 'media-library-tools' ),
			'used'            => __( 'The usage scan recorded a usage of this attachment.', 'media-library-tools' ),
			'scan_changed'    => __( 'Not moved: the usage scan changed before this attachment was reached.', 'media-library-tools' ),
			'trash_failed'    => __( 'WordPress did not move the attachment to the trash.', 'media-library-tools' ),
		];
		return $messages[ $reason ] ?? '';
	}

	/**
	 * Whether wp_trash_post() moves to the trash (rather than deleting).
	 *
	 * @return bool
	 */
	public static function trash_enabled(): bool {
		return defined( 'EMPTY_TRASH_DAYS' ) && (bool) EMPTY_TRASH_DAYS;
	}

	/**
	 * Whether any per-post usage re-check is still scheduled.
	 *
	 * @return bool
	 */
	public static function rescans_pending(): bool {
		foreach ( (array) _get_cron_array() as $hooks ) {
			if ( is_array( $hooks ) && ! empty( $hooks[ self::POST_RESCAN_HOOK ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Scan block returned by both abilities.
	 *
	 * @param array $status From UsageScanStatus::read().
	 *
	 * @return array
	 */
	public static function scan_block( array $status ): array {
		return [
			'scan_id'     => $status['scan_id'],
			'state'       => $status['state'],
			'complete'    => $status['complete'],
			'last_update' => $status['last_update'],
		];
	}

	/**
	 * Schema of scan_block().
	 *
	 * @return array
	 */
	public static function scan_block_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'scan_id', 'state', 'complete', 'last_update' ],
			'properties'           => [
				'scan_id'     => [
					'type'        => 'string',
					'description' => 'The completed scan the check relied on.',
				],
				'state'       => [
					'type'        => 'string',
					'enum'        => UsageScanStatus::STATES,
					'description' => 'Scan state.',
				],
				'complete'    => [
					'type'        => 'boolean',
					'description' => 'Whether the scan is complete.',
				],
				'last_update' => [
					'type'        => 'string',
					'description' => 'Site-local time the scan status last changed.',
				],
			],
		];
	}

	/**
	 * Input schema shared by both abilities.
	 *
	 * @return array
	 */
	public static function input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'ids' ],
			'properties'           => [
				'ids' => [
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => self::MAX_IDS,
					'uniqueItems' => true,
					'items'       => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'description' => 'Exact attachment IDs, 1 to 50, each once.',
				],
			],
		];
	}
}
