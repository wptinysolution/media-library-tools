<?php
/**
 * Media trash service for the MCP trash abilities.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Trash;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\UsedWhere\PostRescanMarker;
use TinySolutions\mlt\Modules\UsedWhere\UsageClassifier;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;
use WP_Post;

/**
 * Moves attachments to the WordPress trash and restores them, for the MCP
 * trash abilities, through wp_trash_post() and wp_untrash_post() only: no
 * table, trash or deletion of its own.
 *
 * Trash is strict. An attachment can be trashed only when trash is enabled
 * (EMPTY_TRASH_DAYS above 0, as otherwise core deletes the post and its
 * files), the current user may delete it, it is in the library (`inherit`),
 * it is not the site icon or logo, and the latest Used Where scan is
 * complete, verified, covered it and recorded no usage. Trashing also needs
 * a confirmation token issued by a preview of exactly the same attachments
 * for the same user and scan; each token works once.
 *
 * Nothing here is transactional across attachments: each one is trashed or
 * restored on its own and reported as it ends up.
 */
class MediaTrash {

	/**
	 * Most attachments per preview, trash or restore call.
	 *
	 * @var int
	 */
	const MAX_IDS = 20;

	/**
	 * Seconds a confirmation token stays valid.
	 *
	 * @var int
	 */
	const TOKEN_TTL = 600;

	/**
	 * Prefix of the option holding one confirmation token, followed by the
	 * token's SHA-256 hash (the token itself is never stored).
	 *
	 * @var string
	 */
	const TOKEN_OPTION_PREFIX = 'tsmlt_trash_token_';

	/**
	 * Most expired token options removed when a token is issued.
	 *
	 * @var int
	 */
	const TOKEN_PRUNE_LIMIT = 100;

	/**
	 * Most token batches examined by one prune.
	 *
	 * @var int
	 */
	const TOKEN_PRUNE_BATCHES = 10;

	/**
	 * Cron hook of the deferred usage rescan of one saved post
	 * (ActionHooks::schedule_usage_scan_on_save()).
	 *
	 * @var string
	 */
	const POST_RESCAN_HOOK = 'tsmlt_scan_post_usage';

	/**
	 * Filters through which another plugin can take over WordPress's event
	 * storage; when any is in use, the `cron` option is not the whole
	 * schedule.
	 *
	 * @var string[]
	 */
	const CRON_BACKEND_FILTERS = [ 'pre_schedule_event', 'pre_reschedule_event', 'pre_unschedule_event', 'pre_clear_scheduled_hook', 'pre_unschedule_hook', 'pre_get_scheduled_event', 'pre_get_ready_cron_jobs' ];

	/**
	 * Filter that can lower (never raise) the trash days the MCP abilities
	 * see; 0 or less turns MCP trash off.
	 *
	 * @var string
	 */
	const DAYS_FILTER = 'tsmlt_mcp_trash_days';

	/**
	 * Refusal: missing, not an attachment, or not deletable by the user
	 * (told apart nowhere, so existence does not leak).
	 *
	 * @var string
	 */
	const REASON_NOT_AVAILABLE = 'not_available';

	/**
	 * Refusal: already in the trash.
	 *
	 * @var string
	 */
	const REASON_ALREADY_TRASHED = 'already_trashed';

	/**
	 * Refusal: not in the trash (restore).
	 *
	 * @var string
	 */
	const REASON_NOT_TRASHED = 'not_trashed';

	/**
	 * Refusal: a status other than `inherit` (trash).
	 *
	 * @var string
	 */
	const REASON_INVALID_STATUS = 'invalid_status';

	/**
	 * Refusal: the site icon or the site logo.
	 *
	 * @var string
	 */
	const REASON_PROTECTED = 'protected_site_media';

	/**
	 * Refusal: the Used Where scan recorded a usage.
	 *
	 * @var string
	 */
	const REASON_USED = 'used';

	/**
	 * Refusal: usage is unknown (no complete verified scan covered it).
	 *
	 * @var string
	 */
	const REASON_USAGE_UNKNOWN = 'usage_unknown';

	/**
	 * Refusal: trash is turned off.
	 *
	 * @var string
	 */
	const REASON_TRASH_DISABLED = 'trash_disabled';

	/**
	 * Refusal: the Used Where scan changed since the preview, or during the
	 * call.
	 *
	 * @var string
	 */
	const REASON_SCAN_CHANGED = 'scan_changed';

	/**
	 * Outcome reason: a pre_trash_post / pre_untrash_post filter stopped it.
	 *
	 * @var string
	 */
	const REASON_BLOCKED = 'blocked_by_filter';

	/**
	 * Outcome reason: WordPress could not update the attachment (no filter
	 * stopped it).
	 *
	 * @var string
	 */
	const REASON_UPDATE_FAILED = 'update_failed';

	/**
	 * Outcome reason: this call changed the attachment, but something else
	 * changed it back before it was read back.
	 *
	 * @var string
	 */
	const REASON_CHANGED_DURING_CALL = 'changed_during_call';

	/**
	 * Refusal: another request is trashing the same attachment.
	 *
	 * @var string
	 */
	const REASON_IN_PROGRESS = 'in_progress';

	/**
	 * Outcome reason: the attachment could not be claimed (database error).
	 *
	 * @var string
	 */
	const REASON_CLAIM_FAILED = 'claim_failed';

	/**
	 * Outcome reason: the attachment ended in an unexpected state.
	 *
	 * @var string
	 */
	const REASON_UNEXPECTED_STATUS = 'unexpected_status';

	/**
	 * Outcome reason: the attachment no longer exists after the call.
	 *
	 * @var string
	 */
	const REASON_DELETED = 'deleted';

	/**
	 * Error code: trash is turned off.
	 *
	 * @var string
	 */
	const ERROR_TRASH_DISABLED = 'tsmlt_trash_disabled';

	/**
	 * Error code: the confirmation token is missing, unknown, used,
	 * expired, or for another user or set of attachments.
	 *
	 * @var string
	 */
	const ERROR_TOKEN_INVALID = 'tsmlt_trash_token_invalid';

	/**
	 * Error code: the Used Where scan changed since the preview.
	 *
	 * @var string
	 */
	const ERROR_SCAN_CHANGED = 'tsmlt_trash_scan_changed';

	/**
	 * Error code: usage records could not be read.
	 *
	 * @var string
	 */
	const ERROR_READ_FAILED = 'tsmlt_trash_read_failed';

	/**
	 * Attachment whose permanent deletion is refused while it is being
	 * trashed (0 when none).
	 *
	 * @var int
	 */
	private static $guarded = 0;

	/**
	 * Whether a pre_trash_post / pre_untrash_post filter stopped the call
	 * on the guarded attachment.
	 *
	 * @var bool
	 */
	private static $short_circuited = false;

	/**
	 * Whether WordPress reported the guarded attachment trashed or restored
	 * (trashed_post / untrashed_post) during this call.
	 *
	 * @var bool
	 */
	private static $changed_by_call = false;

	/**
	 * What MCP clients are told about permanent deletion, which no MCP
	 * ability offers.
	 *
	 * @return string
	 */
	public static function permanent_delete_guidance(): string {
		return __( 'Permanent deletion is not available through MCP. Before permanently deleting media, inspect the entire website for dependencies, create and verify a backup, then log in to WordPress Admin and carefully perform the deletion manually.', 'media-library-tools' );
	}

	/**
	 * The site's trash days (EMPTY_TRASH_DAYS), as configured; 0 (trash off)
	 * when it is not defined or not a number.
	 *
	 * @return int
	 */
	public static function empty_trash_days(): int {
		return self::days_from( 'EMPTY_TRASH_DAYS' );
	}

	/**
	 * Trash days held by a constant: its whole-number value, or 0 (trash
	 * off) when it is not defined or not a number, so an unusable value
	 * fails closed.
	 *
	 * @param string $constant Constant name.
	 *
	 * @return int
	 */
	public static function days_from( string $constant ): int {
		if ( ! defined( $constant ) ) {
			return 0;
		}
		return self::whole_number( constant( $constant ) ) ?? 0;
	}

	/**
	 * A whole number given as an integer or a string of digits, or null for
	 * anything else (false, null, floats, other text).
	 *
	 * @param mixed $value Value.
	 *
	 * @return int|null
	 */
	private static function whole_number( $value ): ?int {
		if ( is_int( $value ) ) {
			return $value;
		}
		return is_string( $value ) && 1 === preg_match( '/^-?\d+$/', trim( $value ) ) ? (int) $value : null;
	}

	/**
	 * Trash days for the MCP abilities: EMPTY_TRASH_DAYS, lowered by the
	 * DAYS_FILTER filter if it returns less (it can never raise it). A
	 * filter result that is not a whole number turns MCP trash off.
	 *
	 * @return int
	 */
	public static function trash_days(): int {
		$days     = self::empty_trash_days();
		$filtered = apply_filters( self::DAYS_FILTER, $days );
		$filtered = self::whole_number( $filtered );
		return null === $filtered ? 0 : min( $days, $filtered );
	}

	/**
	 * Whether the MCP abilities may trash anything. When trash days are 0
	 * or less, wp_trash_post() deletes permanently, so it is never called.
	 *
	 * @return bool
	 */
	public static function trash_enabled(): bool {
		return self::trash_days() > 0;
	}

	/**
	 * Trash settings as the abilities report them.
	 *
	 * @return array{enabled: bool, empty_trash_days: int, media_trash: bool}
	 */
	public static function settings(): array {
		return [
			'enabled'          => self::trash_enabled(),
			'empty_trash_days' => self::empty_trash_days(),
			'media_trash'      => defined( 'MEDIA_TRASH' ) && (bool) MEDIA_TRASH,
		];
	}

	/**
	 * Output schema of settings().
	 *
	 * @return array
	 */
	public static function settings_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'enabled', 'empty_trash_days', 'media_trash' ],
			'properties'           => [
				'enabled'          => [
					'type'        => 'boolean',
					'description' => 'Whether MCP trash is on. Off when EMPTY_TRASH_DAYS is 0 or less (WordPress would delete permanently instead), or when a site filter turned it off.',
				],
				'empty_trash_days' => [
					'type'        => 'integer',
					'description' => 'Days WordPress keeps trashed items before deleting them permanently (EMPTY_TRASH_DAYS).',
				],
				'media_trash'      => [
					'type'        => 'boolean',
					'description' => 'The MEDIA_TRASH setting, for information: on WordPress\'s own media screens it decides whether media gets a Trash link and whether Delete moves media to the trash or deletes it permanently. The MCP tools always use the trash and never delete, whatever its value.',
				],
			],
		];
	}

	/**
	 * Input schema of an `ids` list.
	 *
	 * @return array
	 */
	public static function ids_schema(): array {
		return [
			'type'        => 'array',
			'minItems'    => 1,
			'maxItems'    => self::MAX_IDS,
			'uniqueItems' => true,
			'items'       => [
				'type'    => 'integer',
				'minimum' => 1,
			],
			'description' => 'Attachment IDs, 1 to 20, each once.',
		];
	}

	/**
	 * Output schema of one trash or restore result.
	 *
	 * @param string[] $results Result names.
	 * @param string[] $reasons Reason codes ('' included).
	 *
	 * @return array
	 */
	public static function result_schema( array $results, array $reasons ): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'id', 'result', 'reason' ],
			'properties'           => [
				'id'     => [ 'type' => 'integer' ],
				'result' => [
					'type' => 'string',
					'enum' => $results,
				],
				'reason' => [
					'type' => 'string',
					'enum' => $reasons,
				],
			],
		];
	}

	/**
	 * Output schema of summary().
	 *
	 * @param string[] $results Result names.
	 *
	 * @return array
	 */
	public static function summary_schema( array $results ): array {
		$properties = [ 'total' => [ 'type' => 'integer' ] ];
		foreach ( $results as $result ) {
			$properties[ $result ] = [ 'type' => 'integer' ];
		}
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array_keys( $properties ),
			'properties'           => $properties,
		];
	}

	/**
	 * When an attachment was trashed and the earliest time WordPress's daily
	 * trash cleanup may delete it permanently, both Unix times (0 when
	 * unknown).
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array{trashed: int, purge: int}
	 */
	public static function trash_times( int $attachment_id ): array {
		$trashed = get_post_meta( $attachment_id, '_wp_trash_meta_time', true );
		$trashed = is_numeric( $trashed ) ? max( 0, (int) $trashed ) : 0;
		return [
			'trashed' => $trashed,
			'purge'   => $trashed > 0 ? $trashed + max( 0, self::empty_trash_days() ) * DAY_IN_SECONDS : 0,
		];
	}

	/**
	 * Validate a list of attachment IDs: 1 to MAX_IDS distinct positive
	 * integers.
	 *
	 * @param mixed $ids Raw IDs.
	 *
	 * @return int[]|null The IDs, in the order given; null when invalid.
	 */
	public static function validate_ids( $ids ): ?array {
		if ( ! is_array( $ids ) || ! $ids || count( $ids ) > self::MAX_IDS || array_values( $ids ) !== $ids ) {
			return null;
		}
		foreach ( $ids as $id ) {
			if ( ! is_int( $id ) || $id < 1 ) {
				return null;
			}
		}
		return count( array_unique( $ids ) ) === count( $ids ) ? $ids : null;
	}

	/**
	 * Attachments the current user may delete, by ID; missing, other post
	 * types and refused ones are left out alike. Read fresh from the
	 * database.
	 *
	 * @param int[] $ids Attachment IDs.
	 *
	 * @return array<int, WP_Post>
	 */
	public static function deletable( array $ids ): array {
		$found = [];
		foreach ( $ids as $id ) {
			clean_post_cache( $id );
			$post = AbilityGuard::resolve_attachment( (string) $id, 'delete_post' );
			if ( $post instanceof WP_Post ) {
				$found[ $id ] = $post;
			}
		}
		return $found;
	}

	/**
	 * Attachments used as the site icon or the site logo. The theme's own
	 * custom_logo is read raw as well: core makes get_theme_mod() return
	 * site_logo instead when that is set.
	 *
	 * @return int[]
	 */
	public static function protected_ids(): array {
		$mods = get_theme_mods();
		$ids  = [ (int) get_option( 'site_icon' ), (int) get_theme_mod( 'custom_logo' ), (int) ( is_array( $mods ) ? ( $mods['custom_logo'] ?? 0 ) : 0 ), (int) get_option( 'site_logo' ) ];
		return array_values( array_filter( array_unique( $ids ) ) );
	}

	/**
	 * Usage of attachments, by the rules of tsmlt/get-media-usage. The scan
	 * status is read (fresh) before and after the usage records (fresh); a
	 * scan that changed meanwhile is not verified, and neither is one while
	 * a saved post's usage rescan is still pending (its new usages are not
	 * recorded yet), so media without a recorded usage is then unknown.
	 *
	 * @param WP_Post[] $attachments Attachments.
	 *
	 * @return array|null `states` (ID => true used, false unused, null
	 *                    unknown), `counts` (ID => recorded usages),
	 *                    `covered` (ID => whether a verified scan covered
	 *                    it), `status` (the scan status read after);
	 *                    null when the records could not be read.
	 */
	public static function usage( array $attachments ): ?array {
		global $wpdb;
		$ids    = array_map( 'intval', wp_list_pluck( $attachments, 'ID' ) );
		$before = self::scan_status();
		foreach ( $ids as $id ) {
			wp_cache_delete( $id, 'post_meta' );
		}
		$queries = (int) $wpdb->num_queries;
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
		}
		if ( (int) $wpdb->num_queries > $queries && '' !== (string) $wpdb->last_error ) {
			// The read failed and WordPress cached the records as empty: drop
			// that, so no one takes it as no usage.
			foreach ( $ids as $id ) {
				wp_cache_delete( $id, 'post_meta' );
			}
			return null;
		}

		$records = [];
		$posts   = [];
		foreach ( $ids as $id ) {
			$records[ $id ] = get_post_meta( $id, UsedWhereScanner::META_KEY, true );
			foreach ( is_array( $records[ $id ] ) ? $records[ $id ] : [] as $usage ) {
				if ( is_array( $usage ) && isset( $usage['post_id'] ) && is_numeric( $usage['post_id'] ) && (int) $usage['post_id'] > 0 ) {
					$posts[] = (int) $usage['post_id'];
				}
			}
		}
		$existing = self::existing_posts( $posts );
		if ( null === $existing ) {
			return null;
		}
		$after    = self::scan_status();
		$verified = UsageClassifier::verified_between( $before, $after ) && UsageClassifier::verified( $after ) && '' !== $after['scan_id'] && ! self::post_rescan_pending();

		$out = [
			'states'  => [],
			'counts'  => [],
			'covered' => [],
			'status'  => $after,
		];
		foreach ( $attachments as $attachment ) {
			$id    = (int) $attachment->ID;
			$count = 0;
			foreach ( is_array( $records[ $id ] ) ? $records[ $id ] : [] as $usage ) {
				if ( is_array( $usage ) && UsageClassifier::counts_usage(
					[
						'post_id'   => $usage['post_id'] ?? 0,
						'post_type' => $usage['post_type'] ?? '',
					],
					$existing
				) ) {
					++$count;
				}
			}
			$out['states'][ $id ]  = UsageClassifier::usage_state( $attachment, $count > 0, $verified, $after );
			$out['counts'][ $id ]  = $count;
			$out['covered'][ $id ] = $verified && UsageClassifier::covered( $attachment, $after );
		}
		return $out;
	}

	/**
	 * Why an attachment cannot be trashed now, or '' when it can.
	 *
	 * @param WP_Post|null $attachment    Deletable attachment, or null when not available.
	 * @param bool|null    $state         Usage state from usage().
	 * @param int[]        $protected_ids protected_ids().
	 *
	 * @return string A REASON_* constant, or ''.
	 */
	public static function trash_refusal( ?WP_Post $attachment, ?bool $state, array $protected_ids ): string {
		if ( ! self::trash_enabled() ) {
			return self::REASON_TRASH_DISABLED;
		}
		if ( ! $attachment instanceof WP_Post ) {
			return self::REASON_NOT_AVAILABLE;
		}
		if ( 'trash' === $attachment->post_status ) {
			return self::REASON_ALREADY_TRASHED;
		}
		if ( 'inherit' !== $attachment->post_status ) {
			return self::REASON_INVALID_STATUS;
		}
		if ( in_array( (int) $attachment->ID, $protected_ids, true ) ) {
			return self::REASON_PROTECTED;
		}
		if ( true === $state ) {
			return self::REASON_USED;
		}
		return false === $state ? '' : self::REASON_USAGE_UNKNOWN;
	}

	/**
	 * Issue a confirmation token for trashing exactly these attachments, by
	 * this user, under this scan. Expired tokens are pruned first.
	 *
	 * @param int[]  $ids     Attachment IDs.
	 * @param string $scan_id Used Where scan ID the preview used.
	 *
	 * @return array{token: string, expires: int}|null Null when it could not
	 *                                                 be stored.
	 */
	public static function issue_token( array $ids, string $scan_id ): ?array {
		self::prune_tokens();
		$token   = bin2hex( random_bytes( 32 ) );
		$expires = time() + self::TOKEN_TTL;
		$stored  = add_option(
			self::token_option( $token ),
			[
				'user_id' => get_current_user_id(),
				'ids'     => self::sorted( $ids ),
				'scan_id' => $scan_id,
				'expires' => $expires,
			],
			'',
			false
		);
		return $stored ? [
			'token'   => $token,
			'expires' => $expires,
		] : null;
	}

	/**
	 * Check a confirmation token against the user and attachments and, if it
	 * matches, use it up. Only one request can use a token: the token's
	 * option is never rewritten, its payload is read from the database (not
	 * a cache), and the token counts as used only by the request whose
	 * delete_option() removed the row (core returns true only when its
	 * DELETE affected it).
	 *
	 * @param mixed $token Token from the client.
	 * @param int[] $ids   Attachment IDs of the call.
	 *
	 * @return array|string The stored payload (`scan_id`), or an error
	 *                      message.
	 */
	public static function consume_token( $token, array $ids ) {
		if ( ! is_string( $token ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return __( 'A confirmation_token from tsmlt/preview-trash-media is required.', 'media-library-tools' );
		}
		$option  = self::token_option( $token );
		$payload = self::read_token( $option );
		if ( ! is_array( $payload ) ) {
			return __( 'The confirmation token is unknown or was already used. Run tsmlt/preview-trash-media again.', 'media-library-tools' );
		}
		if ( (int) ( $payload['expires'] ?? 0 ) < time() ) {
			delete_option( $option );
			return __( 'The confirmation token expired. Run tsmlt/preview-trash-media again.', 'media-library-tools' );
		}
		if ( (int) ( $payload['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return __( 'The confirmation token was issued to another user.', 'media-library-tools' );
		}
		if ( ( $payload['ids'] ?? null ) !== self::sorted( $ids ) ) {
			return __( 'The confirmation token was issued for a different set of attachments. Pass exactly the IDs that were previewed, or preview again.', 'media-library-tools' );
		}
		if ( ! delete_option( $option ) ) {
			return __( 'The confirmation token is unknown or was already used. Run tsmlt/preview-trash-media again.', 'media-library-tools' );
		}
		return $payload;
	}

	/**
	 * Move one attachment to the trash with wp_trash_post(), then read it
	 * back. Never called when trash is off; while it runs, any permanent
	 * deletion of the attachment is refused as a second safeguard.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array{result: string, reason: string}
	 */
	public static function trash( int $attachment_id ): array {
		if ( ! self::trash_enabled() ) {
			return self::outcome( 'refused', self::REASON_TRASH_DISABLED );
		}
		self::$guarded         = $attachment_id;
		self::$short_circuited = false;
		self::$changed_by_call = false;
		add_filter( 'pre_delete_attachment', [ __CLASS__, 'refuse_permanent_delete' ], PHP_INT_MAX, 2 );
		add_filter( 'pre_delete_post', [ __CLASS__, 'refuse_permanent_delete' ], PHP_INT_MAX, 2 );
		add_filter( 'pre_trash_post', [ __CLASS__, 'note_short_circuit' ], PHP_INT_MAX, 2 );
		add_action( 'trashed_post', [ __CLASS__, 'note_changed' ], PHP_INT_MAX );
		try {
			wp_trash_post( $attachment_id );
		} finally {
			remove_filter( 'pre_delete_attachment', [ __CLASS__, 'refuse_permanent_delete' ], PHP_INT_MAX );
			remove_filter( 'pre_delete_post', [ __CLASS__, 'refuse_permanent_delete' ], PHP_INT_MAX );
			remove_filter( 'pre_trash_post', [ __CLASS__, 'note_short_circuit' ], PHP_INT_MAX );
			remove_action( 'trashed_post', [ __CLASS__, 'note_changed' ], PHP_INT_MAX );
			self::$guarded = 0;
		}

		return self::read_back( $attachment_id, 'trash', 'inherit', 'trashed', 'already_trashed', self::REASON_ALREADY_TRASHED );
	}

	/**
	 * Restore one attachment from the trash with wp_untrash_post(), then
	 * read it back.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array{result: string, reason: string}
	 */
	public static function restore( int $attachment_id ): array {
		$trash_meta            = [
			'_wp_trash_meta_status' => get_post_meta( $attachment_id, '_wp_trash_meta_status', true ),
			'_wp_trash_meta_time'   => get_post_meta( $attachment_id, '_wp_trash_meta_time', true ),
		];
		self::$guarded         = $attachment_id;
		self::$short_circuited = false;
		self::$changed_by_call = false;
		add_filter( 'pre_untrash_post', [ __CLASS__, 'note_short_circuit' ], PHP_INT_MAX, 2 );
		add_action( 'untrashed_post', [ __CLASS__, 'note_changed' ], PHP_INT_MAX );
		try {
			wp_untrash_post( $attachment_id );
		} finally {
			remove_filter( 'pre_untrash_post', [ __CLASS__, 'note_short_circuit' ], PHP_INT_MAX );
			remove_action( 'untrashed_post', [ __CLASS__, 'note_changed' ], PHP_INT_MAX );
			self::$guarded = 0;
		}

		$outcome = self::read_back( $attachment_id, 'inherit', 'trash', 'restored', 'not_trashed', self::REASON_NOT_TRASHED );
		if ( self::REASON_UPDATE_FAILED === $outcome['reason'] ) {
			self::keep_trash_meta( $attachment_id, $trash_meta );
		}
		return $outcome;
	}

	/**
	 * After a failed restore, put back WordPress's trash records that
	 * wp_untrash_post() removed before its update failed, so the attachment
	 * still in the trash keeps its trash time (and purge date). Only records
	 * that are missing now are added back.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $trash_meta    Records read before the call, by key.
	 *
	 * @return void
	 */
	private static function keep_trash_meta( int $attachment_id, array $trash_meta ): void {
		foreach ( $trash_meta as $key => $value ) {
			if ( '' !== (string) $value && '' === (string) get_post_meta( $attachment_id, $key, true ) ) {
				add_post_meta( $attachment_id, $key, $value, true );
			}
		}
	}

	/**
	 * Outcome of a trash or restore call, from the attachment's status read
	 * back and whether WordPress reported this call's change
	 * (trashed_post / untrashed_post):
	 *
	 * - in the target status and changed by this call: done;
	 * - in the target status, not changed by this call (someone else did
	 *   it meanwhile): the "already" result, never success;
	 * - back in its first status although this call changed it (someone
	 *   changed it back): failed, changed_during_call;
	 * - in its first status otherwise: blocked when a pre_trash_post /
	 *   pre_untrash_post filter stopped the call, else failed,
	 *   update_failed (WordPress could not update it);
	 * - another status: failed, unexpected_status; gone: failed, deleted.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $target        Status the call should reach.
	 * @param string $start         Status it had before the call.
	 * @param string $done          Result name on success.
	 * @param string $already       Result name when it got there otherwise.
	 * @param string $already_why   Reason code for $already.
	 *
	 * @return array{result: string, reason: string}
	 */
	private static function read_back( int $attachment_id, string $target, string $start, string $done, string $already, string $already_why ): array {
		$status = self::status_now( $attachment_id );
		if ( null === $status ) {
			return self::outcome( 'failed', self::REASON_DELETED );
		}
		if ( $target === $status ) {
			return self::$changed_by_call ? self::outcome( $done, '' ) : self::outcome( $already, $already_why );
		}
		if ( $start !== $status ) {
			return self::outcome( 'failed', self::REASON_UNEXPECTED_STATUS );
		}
		if ( self::$changed_by_call ) {
			return self::outcome( 'failed', self::REASON_CHANGED_DURING_CALL );
		}
		return self::$short_circuited ? self::outcome( 'blocked', self::REASON_BLOCKED ) : self::outcome( 'failed', self::REASON_UPDATE_FAILED );
	}

	/**
	 * Note that WordPress trashed or restored the guarded attachment during
	 * this call. Hooked on trashed_post / untrashed_post only while trash()
	 * or restore() runs.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function note_changed( $post_id ): void {
		if ( self::$guarded > 0 && (int) $post_id === self::$guarded ) {
			self::$changed_by_call = true;
		}
	}

	/**
	 * Note whether a pre_trash_post / pre_untrash_post filter stopped the
	 * call on the guarded attachment. Hooked last, only while trash() or
	 * restore() runs; never changes the decision.
	 *
	 * @param mixed $check Earlier filters' decision (null to go ahead).
	 * @param mixed $post  Post.
	 *
	 * @return mixed $check, unchanged.
	 */
	public static function note_short_circuit( $check, $post ) {
		if ( null !== $check && self::$guarded > 0 && $post instanceof WP_Post && (int) $post->ID === self::$guarded ) {
			self::$short_circuited = true;
		}
		return $check;
	}

	/**
	 * Whether a saved post's deferred usage rescan (POST_RESCAN_HOOK) may
	 * be pending or running, so usages may be missing from the records:
	 * scheduled in the `cron` option, running (PostRescanMarker), or not
	 * known for sure (the option or the markers cannot be read, or another
	 * plugin handles WordPress's event storage). Fails closed: anything
	 * that cannot be verified counts as pending.
	 *
	 * @return bool
	 */
	public static function post_rescan_pending(): bool {
		if ( self::cron_backend_replaced() || false !== self::post_rescan_scheduled() ) {
			return true;
		}
		return false !== PostRescanMarker::running();
	}

	/**
	 * Whether a POST_RESCAN_HOOK event is in the `cron` option, read
	 * straight from the database (WordPress's option caches are neither read
	 * nor changed).
	 *
	 * @return bool|null Null when the option cannot be read or is not a
	 *                   schedule.
	 */
	private static function post_rescan_scheduled(): ?bool {
		global $wpdb;
		$suppressed = $wpdb->suppress_errors( true );
		try {
			$rows = Fns::DB()->select( 'option_value' )->from( 'options' )->where( 'option_name', '=', 'cron' )->limit( 1 )->get();
		} catch ( \Throwable $e ) {
			$rows = null;
		}
		$wpdb->suppress_errors( $suppressed );
		if ( '' !== (string) $wpdb->last_error || ! is_array( $rows ) || ! isset( $rows[0]['option_value'] ) ) {
			return null;
		}
		$cron = maybe_unserialize( $rows[0]['option_value'] );
		if ( ! is_array( $cron ) ) {
			return null;
		}
		foreach ( $cron as $hooks ) {
			if ( is_array( $hooks ) && ! empty( $hooks[ self::POST_RESCAN_HOOK ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether another plugin takes over WordPress's event storage (any
	 * CRON_BACKEND_FILTERS callback), so the `cron` option may not hold the
	 * whole schedule.
	 *
	 * @return bool
	 */
	private static function cron_backend_replaced(): bool {
		foreach ( self::CRON_BACKEND_FILTERS as $filter ) {
			if ( has_filter( $filter ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Refuse the permanent deletion of the attachment being trashed.
	 * Hooked on pre_delete_attachment and pre_delete_post only while
	 * trash() calls wp_trash_post().
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
	 * Counts of results, for every result name given.
	 *
	 * @param array[]  $items Items with a `result`.
	 * @param string[] $names Result names.
	 *
	 * @return array<string, int>
	 */
	public static function summary( array $items, array $names ): array {
		$summary = array_fill_keys( $names, 0 );
		foreach ( $items as $item ) {
			++$summary[ $item['result'] ];
		}
		return [ 'total' => count( $items ) ] + $summary;
	}

	/**
	 * The Used Where scan status, read from the database rather than a cache.
	 *
	 * @return array UsageScanStatus::read().
	 */
	public static function scan_status(): array {
		self::forget_cached_option( UsageScanStatus::OPTION );
		return UsageScanStatus::read();
	}

	/**
	 * An attachment's status read fresh, or null when it no longer exists.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string|null
	 */
	private static function status_now( int $attachment_id ): ?string {
		clean_post_cache( $attachment_id );
		$post = get_post( $attachment_id );
		return $post instanceof WP_Post && 'attachment' === $post->post_type ? (string) $post->post_status : null;
	}

	/**
	 * One outcome.
	 *
	 * @param string $result Result.
	 * @param string $reason Reason code, or ''.
	 *
	 * @return array{result: string, reason: string}
	 */
	private static function outcome( string $result, string $reason ): array {
		return [
			'result' => $result,
			'reason' => $reason,
		];
	}

	/**
	 * IDs sorted ascending, as tokens bind them.
	 *
	 * @param int[] $ids IDs.
	 *
	 * @return int[]
	 */
	private static function sorted( array $ids ): array {
		$ids = array_map( 'intval', $ids );
		sort( $ids );
		return $ids;
	}

	/**
	 * Option name of a token.
	 *
	 * @param string $token Token.
	 *
	 * @return string
	 */
	private static function token_option( string $token ): string {
		return self::TOKEN_OPTION_PREFIX . hash( 'sha256', $token );
	}

	/**
	 * A token's payload read from the database, or null when there is none.
	 *
	 * @param string $option Option name.
	 *
	 * @return array|null
	 */
	private static function read_token( string $option ): ?array {
		try {
			$rows = Fns::DB()->select( 'option_value' )->from( 'options' )->where( 'option_name', '=', $option )->limit( 1 )->get();
		} catch ( \Throwable $e ) {
			return null;
		}
		if ( ! is_array( $rows ) || ! isset( $rows[0]['option_value'] ) ) {
			return null;
		}
		$payload = maybe_unserialize( $rows[0]['option_value'] );
		return is_array( $payload ) ? $payload : null;
	}

	/**
	 * Remove expired token options, oldest first. Every token lives
	 * TOKEN_TTL seconds, so the oldest rows expire first: batches of
	 * TOKEN_PRUNE_LIMIT are read in insertion order until a live token is
	 * met, at most TOKEN_PRUNE_BATCHES per call. Each preview adds one
	 * token and can remove up to TOKEN_PRUNE_LIMIT x TOKEN_PRUNE_BATCHES
	 * expired ones, so they cannot build up.
	 *
	 * @return void
	 */
	private static function prune_tokens(): void {
		for ( $batch = 0; $batch < self::TOKEN_PRUNE_BATCHES; $batch++ ) {
			$rows = self::token_rows();
			if ( ! $rows ) {
				return;
			}
			foreach ( $rows as $row ) {
				$payload = maybe_unserialize( $row['option_value'] ?? '' );
				if ( is_array( $payload ) && (int) ( $payload['expires'] ?? 0 ) >= time() ) {
					return;
				}
				delete_option( (string) $row['option_name'] );
			}
			if ( count( $rows ) < self::TOKEN_PRUNE_LIMIT ) {
				return;
			}
		}
	}

	/**
	 * The oldest TOKEN_PRUNE_LIMIT token options, in insertion order.
	 *
	 * @return array[] Rows (`option_name`, `option_value`); empty on a
	 *                 database error.
	 */
	private static function token_rows(): array {
		global $wpdb;
		try {
			$rows = Fns::DB()->select( 'option_name', 'option_value' )->from( 'options' )
				->where( 'option_name', 'LIKE', $wpdb->esc_like( self::TOKEN_OPTION_PREFIX ) . '%' )
				->orderBy( 'option_id', 'ASC' )
				->limit( self::TOKEN_PRUNE_LIMIT )->get();
		} catch ( \Throwable $e ) {
			return [];
		}
		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Which of these posts exist, in one query per 1,000 IDs.
	 *
	 * @param int[] $ids Post IDs.
	 *
	 * @return array<int, true>|null Existing IDs (keys); null on a database
	 *                               error.
	 */
	private static function existing_posts( array $ids ): ?array {
		global $wpdb;
		$existing = [];
		foreach ( array_chunk( array_values( array_unique( $ids ) ), 1000 ) as $chunk ) {
			try {
				$rows = Fns::DB()->select( 'ID' )->from( 'posts' )->whereIn( 'ID', ...$chunk )->get();
			} catch ( \Throwable $e ) {
				return null;
			}
			if ( '' !== (string) $wpdb->last_error || ! is_array( $rows ) ) {
				return null;
			}
			foreach ( $rows as $row ) {
				$existing[ (int) $row['ID'] ] = true;
			}
		}
		return $existing;
	}

	/**
	 * Drop cached copies of an option so the next read sees the database.
	 *
	 * @param string $option Option name.
	 *
	 * @return void
	 */
	private static function forget_cached_option( string $option ): void {
		wp_cache_delete( $option, 'options' );
		foreach ( [ 'alloptions', 'notoptions' ] as $key ) {
			$cached = wp_cache_get( $key, 'options' );
			if ( is_array( $cached ) && isset( $cached[ $option ] ) ) {
				unset( $cached[ $option ] );
				wp_cache_set( $key, $cached, 'options' );
			}
		}
	}
}
