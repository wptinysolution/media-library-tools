<?php
/**
 * Compression service for the MCP compression abilities.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Helpers\Fns;
use WP_Error;
use WP_Post;

/**
 * Shared rules of the MCP compression abilities: input checks, the Pro-only
 * options, per-image eligibility, the one-time confirmation token and a
 * read-only view of the compression job.
 *
 * Compression itself is never done here. The abilities start the existing
 * asynchronous CompressionJob (WP-Cron), which compresses each image through
 * AttachmentProcessor with its locks; the run settings come from
 * CompressionSettings::resolve_run_settings(), so Free and Pro get exactly
 * what the Compress Images page gives them.
 *
 * Nothing here returns file paths, stored error texts or image titles.
 */
class McpCompression {

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
	 * Option-name prefix of stored tokens (followed by the token's SHA-256).
	 *
	 * @var string
	 */
	const TOKEN_OPTION_PREFIX = 'tsmlt_compression_token_';

	/**
	 * Expired tokens read per pruning query.
	 *
	 * @var int
	 */
	const TOKEN_PRUNE_LIMIT = 100;

	/**
	 * Most pruning queries per issued token.
	 *
	 * @var int
	 */
	const TOKEN_PRUNE_BATCHES = 10;

	/**
	 * Scopes of a run.
	 *
	 * @var string[]
	 */
	const SCOPES = [ 'selected', 'library' ];

	/**
	 * Per-image preview statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'ready', 'already_compressed', 'unsupported', 'file_missing', 'locked', 'not_found', 'forbidden' ];

	/**
	 * Job statuses CompressionJob stores.
	 *
	 * @var string[]
	 */
	const JOB_STATUSES = [ 'idle', 'running', 'completed', 'partial', 'failed', 'cancelled' ];

	/**
	 * Recorded compression statuses that count as already compressed.
	 *
	 * @var string[]
	 */
	const DONE_STATUSES = [ 'completed', 'skipped' ];

	/**
	 * Input keys of a preview.
	 *
	 * @var string[]
	 */
	const PREVIEW_KEYS = [ 'scope', 'ids', 'mode', 'quality', 'backup_originals', 'compress_generated_sizes', 'include_compressed' ];

	/**
	 * A Pro-only option was requested without an active Pro licence.
	 *
	 * @var string
	 */
	const ERROR_PRO_REQUIRED = 'tsmlt_pro_required';

	/**
	 * No image library (Imagick or GD) is available.
	 *
	 * @var string
	 */
	const ERROR_ENGINE = 'tsmlt_compression_engine_unavailable';

	/**
	 * The confirmation token is missing, unknown, used, expired or another user's.
	 *
	 * @var string
	 */
	const ERROR_TOKEN = 'tsmlt_compression_token_invalid';

	/**
	 * A compression or conversion job is running.
	 *
	 * @var string
	 */
	const ERROR_JOB_RUNNING = 'tsmlt_compression_job_running';

	/**
	 * The effective settings changed since the preview.
	 *
	 * @var string
	 */
	const ERROR_SETTINGS_CHANGED = 'tsmlt_compression_settings_changed';

	/**
	 * Nothing is left to compress.
	 *
	 * @var string
	 */
	const ERROR_NOTHING = 'tsmlt_compression_nothing_to_do';

	/**
	 * The job could not be started.
	 *
	 * @var string
	 */
	const ERROR_START_FAILED = 'tsmlt_compression_start_failed';

	/**
	 * The job to cancel is not the current one.
	 *
	 * @var string
	 */
	const ERROR_JOB_MISMATCH = 'tsmlt_compression_job_mismatch';

	/**
	 * The current job is not a compression job.
	 *
	 * @var string
	 */
	const ERROR_NOT_COMPRESSION = 'tsmlt_compression_not_compression_job';

	/**
	 * The job is not running.
	 *
	 * @var string
	 */
	const ERROR_NOT_RUNNING = 'tsmlt_compression_job_not_running';

	/**
	 * Shape of a job ID that is returned.
	 *
	 * @var string
	 */
	const JOB_ID_PATTERN = '/^[A-Za-z0-9_.-]{1,64}$/';

	/**
	 * Validate a preview's input.
	 *
	 * @param mixed $input Raw input.
	 *
	 * @return array{scope: string, ids: int[], request: array, include_compressed: bool}|WP_Error
	 */
	public static function sanitize_preview( $input ) {
		$input = null === $input ? [] : $input;
		if ( ! is_array( $input ) || [] !== array_diff( array_keys( $input ), self::PREVIEW_KEYS ) ) {
			return self::invalid( __( 'Unknown input. Allowed: scope, ids, mode, quality, backup_originals, compress_generated_sizes, include_compressed.', 'media-library-tools' ) );
		}
		$scope = $input['scope'] ?? 'selected';
		if ( ! in_array( $scope, self::SCOPES, true ) ) {
			return self::invalid( __( 'scope must be "selected" or "library".', 'media-library-tools' ) );
		}
		$ids = [];
		if ( 'selected' === $scope ) {
			$ids = self::validate_ids( $input['ids'] ?? null );
			if ( null === $ids ) {
				return self::invalid( __( 'ids must be a list of 1 to 20 distinct attachment IDs (positive integers).', 'media-library-tools' ) );
			}
		} elseif ( array_key_exists( 'ids', $input ) ) {
			return self::invalid( __( 'ids cannot be used with scope "library".', 'media-library-tools' ) );
		}

		$request = [];
		if ( array_key_exists( 'mode', $input ) ) {
			if ( ! is_string( $input['mode'] ) || ! CompressionSettings::instance()->is_valid_mode( $input['mode'] ) ) {
				return self::invalid( __( 'mode must be high_quality, balanced or maximum.', 'media-library-tools' ) );
			}
			$request['mode'] = $input['mode'];
		}
		if ( array_key_exists( 'quality', $input ) ) {
			if ( ! is_int( $input['quality'] ) || $input['quality'] < 1 || $input['quality'] > 100 ) {
				return self::invalid( __( 'quality must be a whole number from 1 to 100.', 'media-library-tools' ) );
			}
			$request['use_custom_quality'] = true;
			$request['quality']            = $input['quality'];
		}
		foreach ( [ 'backup_originals', 'compress_generated_sizes', 'include_compressed' ] as $key ) {
			if ( array_key_exists( $key, $input ) && ! is_bool( $input[ $key ] ) ) {
				return self::invalid(
					/* translators: %s: input name. */
					sprintf( __( '%s must be true or false.', 'media-library-tools' ), $key )
				);
			}
		}
		foreach ( [ 'backup_originals', 'compress_generated_sizes' ] as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$request[ $key ] = $input[ $key ];
			}
		}

		return [
			'scope'              => $scope,
			'ids'                => $ids,
			'request'            => $request,
			'include_compressed' => ! empty( $input['include_compressed'] ),
		];
	}

	/**
	 * Refuse Pro-only options the site is not entitled to. Asking for them
	 * to be off is always allowed.
	 *
	 * @param array $request Requested run settings.
	 *
	 * @return WP_Error|null
	 */
	public static function pro_refusal( array $request ): ?WP_Error {
		$access = CompressionAccess::instance();
		$denied = [];
		if ( ! empty( $request['use_custom_quality'] ) && ! $access->can_use_custom_quality() ) {
			$denied[] = 'quality';
		}
		if ( ! empty( $request['backup_originals'] ) && ! $access->can_backup_originals() ) {
			$denied[] = 'backup_originals';
		}
		if ( ! empty( $request['compress_generated_sizes'] ) && ! $access->can_compress_generated_sizes() ) {
			$denied[] = 'compress_generated_sizes';
		}
		if ( ! $denied ) {
			return null;
		}
		return AbilityGuard::error(
			self::ERROR_PRO_REQUIRED,
			sprintf(
				/* translators: %s: comma-separated option names. */
				__( 'These options need an active Media Library Tools Pro licence: %s. Leave them out to compress with the Free settings.', 'media-library-tools' ),
				implode( ', ', $denied )
			),
			403
		);
	}

	/**
	 * Effective run settings, from the Compress Images settings and the
	 * requested values, by the Compress Images page's own rules.
	 *
	 * @param array $request Requested run settings.
	 *
	 * @return array
	 */
	public static function run_settings( array $request ): array {
		$settings = CompressionSettings::instance()->resolve_run_settings( $request );
		return [
			'mode'                     => (string) $settings['mode'],
			'quality'                  => (int) $settings['quality'],
			'use_custom_quality'       => (bool) $settings['use_custom_quality'],
			'backup_originals'         => (bool) $settings['backup_originals'],
			'compress_generated_sizes' => (bool) $settings['compress_generated_sizes'],
		];
	}

	/**
	 * Whether an image library is available to compress with.
	 *
	 * @return WP_Error|null
	 */
	public static function engine_refusal(): ?WP_Error {
		if ( CompressionAccess::instance()->is_compression_feature_available() ) {
			return null;
		}
		return AbilityGuard::error( self::ERROR_ENGINE, __( 'No image compression library (ImageMagick or GD) is available on this server.', 'media-library-tools' ), 503 );
	}

	/**
	 * Preview status of one image for the current user. Read-only.
	 *
	 * Inaccessible or trashed images are not_found; images the user may read
	 * but not edit are forbidden.
	 *
	 * @param int  $id                 Attachment ID.
	 * @param bool $include_compressed Whether already compressed images are compressed again.
	 *
	 * @return string One of STATUSES.
	 */
	public static function item_status( int $id, bool $include_compressed ): string {
		$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );
		if ( ! $attachment instanceof WP_Post || 'inherit' !== $attachment->post_status ) {
			return 'not_found';
		}
		if ( ! AbilityGuard::can_access_attachment( $attachment, 'edit_post' ) ) {
			return 'forbidden';
		}
		$processor = AttachmentProcessor::instance();
		$valid     = $processor->validate_attachment_file( $id );
		if ( is_wp_error( $valid ) ) {
			$statuses = [
				'tsmlt_compression_missing_source'   => 'file_missing',
				'tsmlt_compression_unsupported_mime' => 'unsupported',
			];
			return $statuses[ $valid->get_error_code() ] ?? 'not_found';
		}
		if ( $processor->is_locked( $id ) || $processor->is_busy_elsewhere( $id ) ) {
			return 'locked';
		}
		$data = CompressionMetadata::instance()->get( $id );
		if ( ! $include_compressed && in_array( (string) ( $data['status'] ?? '' ), self::DONE_STATUSES, true ) ) {
			return 'already_compressed';
		}
		return 'ready';
	}

	/**
	 * Library figures for a library run: supported images, how many already
	 * carry compression data, and how many a run would queue (at most the
	 * Free limit). Two count queries; read-only.
	 *
	 * @param bool $include_compressed Whether already compressed images are compressed again.
	 *
	 * @return array{total: int, compressed: int, to_queue: int}
	 */
	public static function library( bool $include_compressed ): array {
		$stats    = CompressionJob::instance()->get_library_stats();
		$limit    = CompressionAccess::instance()->get_compression_limit();
		$to_queue = $include_compressed ? (int) $stats['total'] : (int) $stats['remaining'];
		if ( $limit > 0 ) {
			$to_queue = min( $to_queue, $limit );
		}
		return [
			'total'      => (int) $stats['total'],
			'compressed' => (int) $stats['compressed'],
			'to_queue'   => $to_queue,
		];
	}

	/**
	 * Issue a confirmation token for this run, for the current user. Expired
	 * tokens are pruned first.
	 *
	 * @param array $run Scope, ids, request, include_compressed and settings.
	 *
	 * @return array{token: string, expires: int}|null Null when it could not be stored.
	 */
	public static function issue_token( array $run ): ?array {
		self::prune_tokens();
		$token   = bin2hex( random_bytes( 32 ) );
		$expires = time() + self::TOKEN_TTL;
		$ids     = array_map( 'intval', (array) ( $run['ids'] ?? [] ) );
		sort( $ids );
		$stored = add_option(
			self::token_option( $token ),
			[
				'user_id'            => get_current_user_id(),
				'scope'              => (string) $run['scope'],
				'ids'                => $ids,
				'request'            => (array) $run['request'],
				'include_compressed' => (bool) $run['include_compressed'],
				'settings'           => (array) $run['settings'],
				'expires'            => $expires,
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
	 * Check a confirmation token and, if it is valid, use it up. Only the
	 * request whose delete_option() removed the row may use it, so a token
	 * works once; its payload is read from the database, not a cache.
	 *
	 * @param mixed $token Token from the client.
	 *
	 * @return array|WP_Error The stored run.
	 */
	public static function consume_token( $token ) {
		$again = __( 'Run tsmlt/preview-compression again.', 'media-library-tools' );
		if ( ! is_string( $token ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return self::token_error( __( 'A confirmation_token from tsmlt/preview-compression is required.', 'media-library-tools' ) );
		}
		$option  = self::token_option( $token );
		$payload = self::read_token( $option );
		if ( ! is_array( $payload ) ) {
			return self::token_error( __( 'The confirmation token is unknown or was already used.', 'media-library-tools' ) . ' ' . $again );
		}
		if ( (int) ( $payload['expires'] ?? 0 ) < time() ) {
			delete_option( $option );
			return self::token_error( __( 'The confirmation token expired.', 'media-library-tools' ) . ' ' . $again );
		}
		if ( (int) ( $payload['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return self::token_error( __( 'The confirmation token was issued to another user.', 'media-library-tools' ) );
		}
		if ( ! delete_option( $option ) ) {
			return self::token_error( __( 'The confirmation token is unknown or was already used.', 'media-library-tools' ) . ' ' . $again );
		}
		return $payload;
	}

	/**
	 * The stored job as the MCP tools report it: counts and codes only,
	 * without its queue, titles, settings or error texts.
	 *
	 * Read-only. CompressionJob::get_progress() is not used, because it saves
	 * the state when it corrects it; the same corrections (a cancelled job
	 * is cancelled, a running job with an empty queue is finished) are
	 * applied to the returned copy only.
	 *
	 * @return array
	 */
	public static function job(): array {
		$state     = CompressionJob::instance()->get_state();
		$status    = self::job_status( $state );
		$job_type  = (string) ( $state['job_type'] ?? '' );
		$job_id    = (string) ( $state['job_id'] ?? '' );
		$total     = max( 0, (int) $state['total'] );
		$processed = max( 0, (int) $state['processed'] );
		return [
			'job_id'         => preg_match( self::JOB_ID_PATTERN, $job_id ) ? $job_id : '',
			'job_type'       => in_array( $job_type, [ CompressionJob::TYPE_COMPRESSION, CompressionJob::TYPE_CONVERSION ], true ) ? $job_type : '',
			'status'         => $status,
			'total'          => $total,
			'processed'      => $processed,
			'succeeded'      => max( 0, (int) $state['succeeded'] ),
			'skipped'        => max( 0, (int) $state['skipped'] ),
			'failed'         => max( 0, (int) $state['failed'] ),
			'remaining'      => max( 0, $total - $processed ),
			'percent'        => $total > 0 ? min( 100, (int) floor( $processed / $total * 100 ) ) : 0,
			'saved_bytes'    => max( 0, (int) $state['saved_bytes'] ),
			'tick_scheduled' => (bool) wp_next_scheduled( CompressionJob::TICK_HOOK ),
			'started_at'     => self::utc( (int) $state['started_at'] ),
			'updated_at'     => self::utc( (int) $state['updated_at'] ),
		];
	}

	/**
	 * Job status with CompressionJob::get_progress()'s corrections, without
	 * saving them.
	 *
	 * @param array $state Stored state.
	 *
	 * @return string One of JOB_STATUSES.
	 */
	public static function job_status( array $state ): string {
		$status = (string) ( $state['status'] ?? 'idle' );
		if ( 'running' === $status && ! empty( $state['cancelled_at'] ) ) {
			return 'cancelled';
		}
		if ( 'running' === $status && empty( $state['queue'] ) ) {
			return (int) $state['failed'] > 0 ? 'partial' : 'completed';
		}
		return in_array( $status, self::JOB_STATUSES, true ) ? $status : 'failed';
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

	/**
	 * Validate a list of 1 to MAX_IDS distinct positive integer IDs.
	 *
	 * @param mixed $ids Raw IDs.
	 *
	 * @return int[]|null
	 */
	private static function validate_ids( $ids ): ?array {
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
	 * Invalid-input error.
	 *
	 * @param string $message Message.
	 *
	 * @return WP_Error
	 */
	private static function invalid( string $message ): WP_Error {
		return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, $message, 400 );
	}

	/**
	 * Invalid-token error.
	 *
	 * @param string $message Message.
	 *
	 * @return WP_Error
	 */
	private static function token_error( string $message ): WP_Error {
		return AbilityGuard::error( self::ERROR_TOKEN, $message, 400 );
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
	 * A token's payload, read from the database.
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
	 * Delete expired tokens, oldest first, stopping at the first live one.
	 *
	 * @return void
	 */
	private static function prune_tokens(): void {
		global $wpdb;
		for ( $batch = 0; $batch < self::TOKEN_PRUNE_BATCHES; $batch++ ) {
			try {
				$rows = Fns::DB()->select( 'option_name', 'option_value' )->from( 'options' )
					->where( 'option_name', 'LIKE', $wpdb->esc_like( self::TOKEN_OPTION_PREFIX ) . '%' )
					->orderBy( 'option_id', 'ASC' )
					->limit( self::TOKEN_PRUNE_LIMIT )->get();
			} catch ( \Throwable $e ) {
				return;
			}
			if ( ! is_array( $rows ) || ! $rows ) {
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
}
