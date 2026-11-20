<?php
/**
 * Thumbnail regeneration service for the MCP regeneration abilities.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Regenerate;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\ConfirmationToken;
use TinySolutions\mlt\Modules\Compress\CompressionMetadata;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionMetadata;
use WP_Error;
use WP_Post;

/**
 * Shared rules of the MCP regeneration abilities: input checks, the
 * per-image status (RegenerateThumbnails::preflight(), the engine's own
 * rules), the one-time confirmation token and a read-only view of the
 * regeneration job.
 *
 * Regeneration itself is never done here: the abilities start the existing
 * background job for a fixed list of 1 to 20 attachment IDs. Whole-library
 * regeneration is not offered through MCP. Nothing here returns file paths,
 * file names, titles or stored error texts.
 */
class McpRegenerate {

	/**
	 * Most IDs in one preview.
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
	 * Option-name prefix of stored tokens.
	 *
	 * @var string
	 */
	const TOKEN_OPTION_PREFIX = 'tsmlt_regenerate_token_';

	/**
	 * Per-image preview statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'ready', 'not_found', 'forbidden', 'not_image', 'file_missing', 'remote_storage', 'original_missing', 'locked' ];

	/**
	 * Job statuses the engine stores.
	 *
	 * @var string[]
	 */
	const JOB_STATUSES = [ 'idle', 'running', 'done', 'cancelled' ];

	/**
	 * Per-image result codes the engine records.
	 *
	 * @var string[]
	 */
	const RESULT_CODES = [ '', 'not_found', 'trashed', 'not_image', 'forbidden', 'locked', 'remote_storage', 'file_missing', 'original_missing', 'editor_unavailable', 'copy_failed', 'generation_failed', 'replace_failed' ];

	/**
	 * The confirmation token is missing, unknown, used, expired or another user's.
	 *
	 * @var string
	 */
	const ERROR_TOKEN = 'tsmlt_regenerate_token_invalid';

	/**
	 * A regeneration job is running.
	 *
	 * @var string
	 */
	const ERROR_JOB_RUNNING = 'tsmlt_regenerate_job_running';

	/**
	 * The image sizes changed since the preview.
	 *
	 * @var string
	 */
	const ERROR_CHANGED = 'tsmlt_regenerate_sizes_changed';

	/**
	 * None of the previewed images is ready any more.
	 *
	 * @var string
	 */
	const ERROR_NOTHING = 'tsmlt_regenerate_nothing_to_do';

	/**
	 * The job could not be started.
	 *
	 * @var string
	 */
	const ERROR_START_FAILED = 'tsmlt_regenerate_start_failed';

	/**
	 * The job ID is not the current job's.
	 *
	 * @var string
	 */
	const ERROR_JOB_MISMATCH = 'tsmlt_regenerate_job_mismatch';

	/**
	 * The job is not running.
	 *
	 * @var string
	 */
	const ERROR_NOT_RUNNING = 'tsmlt_regenerate_job_not_running';

	/**
	 * Shape of a size name or job ID that is returned.
	 *
	 * @var string
	 */
	const CODE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/';

	/**
	 * Validate a list of 1 to MAX_IDS distinct positive integer IDs, the only
	 * input of a preview.
	 *
	 * @param mixed $input Raw input.
	 *
	 * @return int[]|WP_Error
	 */
	public static function sanitize_ids( $input ) {
		$ids = is_array( $input ) && [ 'ids' ] === array_keys( $input ) ? $input['ids'] : null;
		$ok  = is_array( $ids ) && [] !== $ids && count( $ids ) <= self::MAX_IDS && array_values( $ids ) === $ids;
		foreach ( $ok ? $ids : [] as $id ) {
			$ok = $ok && is_int( $id ) && $id > 0;
		}
		if ( ! $ok || count( array_unique( $ids ) ) !== count( $ids ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Pass ids: a list of 1 to 20 distinct attachment IDs (positive integers). Nothing else is accepted; the whole library cannot be regenerated through MCP.', 'media-library-tools' ), 400 );
		}
		return $ids;
	}

	/**
	 * Preview of one image for the current user. Read-only.
	 *
	 * Images the user may not read, and trashed ones, are not_found; images
	 * readable but not editable are forbidden. Otherwise the engine's own
	 * checks decide (RegenerateThumbnails::preflight()).
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array
	 */
	public static function item( int $id ): array {
		$item       = [
			'id'          => $id,
			'status'      => 'not_found',
			'upload_copy' => '',
			'original'    => 'none',
			'compressed'  => false,
			'converted'   => false,
		];
		$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );
		if ( ! $attachment instanceof WP_Post || 'inherit' !== $attachment->post_status ) {
			return $item;
		}
		if ( ! AbilityGuard::can_access_attachment( $attachment, 'edit_post' ) ) {
			$item['status'] = 'forbidden';
			return $item;
		}
		$code           = RegenerateThumbnails::instance()->preflight( $id, get_current_user_id() );
		$item['status'] = '' === $code ? 'ready' : ( 'trashed' === $code ? 'not_found' : $code );
		if ( ! in_array( $item['status'], self::STATUSES, true ) ) {
			$item['status'] = 'not_found';
		}
		if ( 'not_found' === $item['status'] || 'not_image' === $item['status'] ) {
			return $item;
		}

		$meta = wp_get_attachment_metadata( $id );
		if ( is_array( $meta ) && ! empty( $meta['original_image'] ) ) {
			$name                = wp_basename( (string) get_attached_file( $id ) );
			$item['upload_copy'] = 'other';
			if ( false !== strpos( $name, '-rotated' ) ) {
				$item['upload_copy'] = 'rotated';
			} elseif ( false !== strpos( $name, '-scaled' ) ) {
				$item['upload_copy'] = 'scaled';
			}
			$item['original'] = 'original_missing' === $item['status'] ? 'missing' : 'available';
		}
		$compression        = CompressionMetadata::instance()->get( $id );
		$item['compressed'] = in_array( (string) ( $compression['status'] ?? '' ), [ 'completed', 'skipped' ], true );
		$item['converted']  = [] !== ConversionMetadata::instance()->get( $id );
		return $item;
	}

	/**
	 * Sizes a regeneration makes and the sizes disabled in Media Library
	 * Tools (kept, not made), as names only.
	 *
	 * @return array{sizes: string[], disabled_sizes: string[], fingerprint: string}
	 */
	public static function sizes(): array {
		$registered = wp_get_registered_image_subsizes();
		$wanted     = apply_filters( 'intermediate_image_sizes_advanced', $registered, [], 0 );
		$wanted     = is_array( $wanted ) ? $wanted : [];
		$disabled   = array_values( array_diff( array_keys( $registered ), array_keys( $wanted ) ) );
		$names      = array_values( array_filter( array_map( 'strval', array_keys( $wanted ) ), [ __CLASS__, 'is_code' ] ) );
		return [
			'sizes'          => $names,
			'disabled_sizes' => array_values( array_filter( array_map( 'strval', $disabled ), [ __CLASS__, 'is_code' ] ) ),
			'fingerprint'    => md5( (string) wp_json_encode( [ $wanted, $disabled ] ) ),
		];
	}

	/**
	 * Whether a value is a plain code that may be returned.
	 *
	 * @param string $value Value.
	 *
	 * @return bool
	 */
	public static function is_code( string $value ): bool {
		return 1 === preg_match( self::CODE_PATTERN, $value );
	}

	/**
	 * Issue a confirmation token for these images and sizes, for the
	 * current user.
	 *
	 * @param int[]  $ids         Ready attachment IDs.
	 * @param string $fingerprint Sizes fingerprint.
	 *
	 * @return array{token: string, expires: int}|null
	 */
	public static function issue_token( array $ids, string $fingerprint ): ?array {
		$ids = array_map( 'intval', $ids );
		sort( $ids );
		return ConfirmationToken::issue(
			self::TOKEN_OPTION_PREFIX,
			[
				'ids'         => $ids,
				'fingerprint' => $fingerprint,
			],
			self::TOKEN_TTL
		);
	}

	/**
	 * Check a confirmation token and, if it is valid, use it up.
	 *
	 * @param mixed $token Token from the client.
	 *
	 * @return array|WP_Error
	 */
	public static function consume_token( $token ) {
		return ConfirmationToken::consume( self::TOKEN_OPTION_PREFIX, $token, 'tsmlt/preview-thumbnail-regeneration', self::ERROR_TOKEN );
	}

	/**
	 * The regeneration job as the MCP tools report it: counts, codes and the
	 * IDs of recent results the current user may read; no file names, queue
	 * or error texts. Read-only: never saves, restarts or advances the job.
	 *
	 * @return array
	 */
	public static function job(): array {
		$engine    = RegenerateThumbnails::instance();
		$state     = $engine->get_state();
		$status    = (string) $state['status'];
		$total     = max( 0, (int) $state['total'] );
		$processed = max( 0, (int) $state['offset'] );
		$job_id    = (string) $state['job_id'];
		$job_type  = (string) $state['job_type'];
		return [
			'job_id'         => self::is_code( $job_id ) ? $job_id : '',
			'job_type'       => in_array( $job_type, [ RegenerateThumbnails::JOB_SELECTED, RegenerateThumbnails::JOB_LIBRARY ], true ) ? $job_type : '',
			'status'         => in_array( $status, self::JOB_STATUSES, true ) ? $status : 'idle',
			'stale'          => $engine->is_stale( $state ),
			'total'          => $total,
			'processed'      => $processed,
			'succeeded'      => max( 0, (int) $state['success_count'] ),
			'skipped'        => max( 0, (int) $state['skipped_count'] ),
			'failed'         => max( 0, (int) $state['failed_count'] ),
			'remaining'      => max( 0, $total - $processed ),
			'percent'        => $total > 0 ? min( 100, (int) floor( $processed / $total * 100 ) ) : 0,
			'tick_scheduled' => (bool) wp_next_scheduled( RegenerateThumbnails::TICK_HOOK ),
			'started_at'     => self::utc( (int) $state['started_at'] ),
			'updated_at'     => self::utc( (int) $state['updated_at'] ),
			'results'        => self::results( (array) $state['recent_done'], (array) $state['recent_errors'] ),
		];
	}

	/**
	 * Recent per-image results (at most RegenerateThumbnails::RECENT_CAP of
	 * each kind): ID, result and code, only for images the current user may
	 * read; the others are counted in `hidden`.
	 *
	 * @param array $done   Recent successes.
	 * @param array $errors Recent skips and failures.
	 *
	 * @return array{items: array, hidden: int}
	 */
	private static function results( array $done, array $errors ): array {
		$entries = [];
		foreach ( $done as $entry ) {
			$entries[] = [ (int) ( $entry['id'] ?? 0 ), 'regenerated', '' ];
		}
		foreach ( $errors as $entry ) {
			$code      = (string) ( $entry['code'] ?? '' );
			$code      = in_array( $code, self::RESULT_CODES, true ) && '' !== $code ? $code : 'generation_failed';
			$entries[] = [ (int) ( $entry['id'] ?? 0 ), in_array( $code, RegenerateThumbnails::SKIP_CODES, true ) ? 'skipped' : 'failed', $code ];
		}
		$items  = [];
		$hidden = 0;
		foreach ( $entries as $entry ) {
			$post = $entry[0] > 0 ? get_post( $entry[0] ) : null;
			if ( $post instanceof WP_Post && 'attachment' === $post->post_type && current_user_can( 'read_post', $entry[0] ) ) {
				$items[] = [
					'id'     => $entry[0],
					'result' => $entry[1],
					'code'   => $entry[2],
				];
			} elseif ( $post instanceof WP_Post || 'not_found' !== $entry[2] ) {
				++$hidden;
			} else {
				// Deleted since: its ID reveals nothing.
				$items[] = [
					'id'     => $entry[0],
					'result' => $entry[1],
					'code'   => $entry[2],
				];
			}
		}
		return [
			'items'  => $items,
			'hidden' => $hidden,
		];
	}

	/**
	 * A "Y-m-d H:i:s" UTC time, or '' for none.
	 *
	 * @param int $timestamp Unix time.
	 *
	 * @return string
	 */
	private static function utc( int $timestamp ): string {
		return $timestamp > 0 ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
	}
}
