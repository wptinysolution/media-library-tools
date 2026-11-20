<?php
/**
 * Media rename service for the MCP rename abilities.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Rename;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\ConfirmationToken;
use WP_Post;

/**
 * Shared rules of the MCP rename abilities: the per-item status from
 * MediaRenamer::plan() (the engine's own checks and name choice), the
 * one-time confirmation token, and a view of the bulk rename job
 * (RenameJob) limited to what the current user may read.
 *
 * Renaming itself is never done here: tsmlt/rename-media runs
 * MediaRenamer::rename() for one item or starts RenameJob for several.
 * Nothing here returns folders, paths or stored error texts; file names
 * only for the user's own readable attachments.
 */
class McpRename {

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
	const TOKEN_OPTION_PREFIX = 'tsmlt_rename_token_';

	/**
	 * Per-item statuses of the preview and of the execution check.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'ready', 'unchanged', 'invalid_filename', 'pro_strategy_not_supported', 'invalid_id', 'duplicate_id', 'not_found', 'forbidden', 'file_missing', 'remote_storage', 'original_missing', 'locked', 'name_unavailable' ];

	/**
	 * Rename results the engine records.
	 *
	 * @var string[]
	 */
	const RESULTS = [ '', 'renamed', 'unchanged', 'refused', 'failed' ];

	/**
	 * Result codes the engine records.
	 *
	 * @var string[]
	 */
	const RESULT_CODES = [ '', 'not_found', 'forbidden', 'invalid_name', 'file_missing', 'remote_storage', 'locked', 'original_missing', 'name_unavailable', 'rename_failed' ];

	/**
	 * Size outcomes the engine records.
	 *
	 * @var string[]
	 */
	const SIZES = [ 'none', 'regenerated', 'kept' ];

	/**
	 * Job statuses RenameJob stores.
	 *
	 * @var string[]
	 */
	const JOB_STATUSES = [ 'idle', 'running', 'done', 'cancelled' ];

	/**
	 * The confirmation token is missing, unknown, used, expired or another user's.
	 *
	 * @var string
	 */
	const ERROR_TOKEN = 'tsmlt_rename_token_invalid';

	/**
	 * A bulk rename job is running.
	 *
	 * @var string
	 */
	const ERROR_JOB_RUNNING = 'tsmlt_rename_job_running';

	/**
	 * The job could not be started, or the token could not be stored.
	 *
	 * @var string
	 */
	const ERROR_START_FAILED = 'tsmlt_rename_start_failed';

	/**
	 * The job ID is not the current job's.
	 *
	 * @var string
	 */
	const ERROR_JOB_MISMATCH = 'tsmlt_rename_job_mismatch';

	/**
	 * The job is not running.
	 *
	 * @var string
	 */
	const ERROR_NOT_RUNNING = 'tsmlt_rename_job_not_running';

	/**
	 * Shape of a job ID that is returned.
	 *
	 * @var string
	 */
	const JOB_ID_PATTERN = '/^tsmlt_rename_[a-f0-9]{16}$/';

	/**
	 * Engine refusal codes and the preview status each maps to.
	 *
	 * @var array<string, string>
	 */
	const CODE_STATUS = [
		'not_found'        => 'not_found',
		'forbidden'        => 'forbidden',
		'invalid_name'     => 'invalid_filename',
		'file_missing'     => 'file_missing',
		'remote_storage'   => 'remote_storage',
		'locked'           => 'locked',
		'original_missing' => 'original_missing',
		'name_unavailable' => 'name_unavailable',
	];

	/**
	 * Status and new names of one item for the current user, from the
	 * engine's own checks. Read-only.
	 *
	 * @param int    $id    Attachment ID.
	 * @param string $typed Typed name, already passed through sanitize_text_field().
	 *
	 * @return array{status: string, current: string, file: string, original: string, numbered: bool}
	 */
	public static function check( int $id, string $typed ): array {
		$plan   = MediaRenamer::plan( $id, $typed, get_current_user_id() );
		$status = 'ready' === $plan['status'] || 'unchanged' === $plan['status']
			? $plan['status']
			: ( self::CODE_STATUS[ $plan['code'] ] ?? 'not_found' );
		return [
			'status'   => $status,
			'current'  => (string) $plan['current'],
			'file'     => (string) $plan['file'],
			'original' => (string) $plan['original'],
			'numbered' => (bool) $plan['numbered'],
		];
	}

	/**
	 * Issue a confirmation token for these items, for the current user.
	 *
	 * @param array<int, string> $items Attachment ID => typed name, ready items only.
	 *
	 * @return array{token: string, expires: int}|null
	 */
	public static function issue_token( array $items ): ?array {
		$stored = [];
		foreach ( $items as $id => $name ) {
			$stored[] = [
				'id'   => (int) $id,
				'name' => (string) $name,
			];
		}
		return ConfirmationToken::issue( self::TOKEN_OPTION_PREFIX, [ 'items' => $stored ], self::TOKEN_TTL );
	}

	/**
	 * Check a confirmation token and, if it is valid, use it up.
	 *
	 * @param mixed $token Token from the client.
	 *
	 * @return array|\WP_Error
	 */
	public static function consume_token( $token ) {
		return ConfirmationToken::consume( self::TOKEN_OPTION_PREFIX, $token, 'tsmlt/preview-media-rename', self::ERROR_TOKEN );
	}

	/**
	 * One engine result as the MCP tools report it: the new file name and
	 * the changed posts only where the current user may read them.
	 *
	 * @param array $result MediaRenamer or RenameJob result.
	 *
	 * @return array{result: string, code: string, filename: string, sizes: string, content_posts: int[], content_posts_hidden: int, content_errors: int}
	 */
	public static function result( array $result ): array {
		$status = (string) ( $result['status'] ?? '' );
		$code   = (string) ( $result['code'] ?? '' );
		$sizes  = (string) ( $result['sizes'] ?? 'none' );
		$posts  = isset( $result['content_posts'] ) ? (array) $result['content_posts'] : (array) ( $result['content']['posts'] ?? [] );
		$errors = isset( $result['content_errors'] ) ? (int) $result['content_errors'] : (int) ( $result['content']['errors'] ?? 0 );
		$shown  = [];
		$hidden = 0;
		foreach ( array_map( 'intval', $posts ) as $post_id ) {
			if ( $post_id > 0 && current_user_can( 'read_post', $post_id ) ) {
				$shown[] = $post_id;
			} else {
				++$hidden;
			}
		}
		$file = wp_basename( (string) ( $result['file'] ?? '' ) );
		return [
			'result'               => in_array( $status, self::RESULTS, true ) ? $status : 'failed',
			'code'                 => in_array( $code, self::RESULT_CODES, true ) ? $code : 'rename_failed',
			'filename'             => sanitize_file_name( $file ) === $file ? $file : '',
			'sizes'                => in_array( $sizes, self::SIZES, true ) ? $sizes : 'none',
			'content_posts'        => $shown,
			'content_posts_hidden' => $hidden,
			'content_errors'       => max( 0, $errors ),
		];
	}

	/**
	 * The bulk rename job as the MCP tools report it: counts and per-item
	 * results for attachments the current user may read (the others are
	 * counted in `hidden`); no queue, owner or folders. Read-only: never
	 * saves, restarts or advances the job.
	 *
	 * @return array
	 */
	public static function job(): array {
		$engine   = RenameJob::instance();
		$progress = $engine->get_progress();
		$status   = (string) $progress['status'];
		$job_id   = (string) $progress['job_id'];
		$total    = max( 0, (int) $progress['total'] );
		$items    = [];
		$hidden   = 0;
		$counts   = array_fill_keys( [ 'renamed', 'unchanged', 'refused', 'failed' ], 0 );
		foreach ( (array) $progress['results'] as $entry ) {
			$entry = is_array( $entry ) ? $entry : [];
			$id    = (int) ( $entry['id'] ?? 0 );
			$row   = self::result( $entry );
			if ( isset( $counts[ $row['result'] ] ) ) {
				++$counts[ $row['result'] ];
			}
			$post = $id > 0 ? get_post( $id ) : null;
			if ( $post instanceof WP_Post && 'attachment' === $post->post_type && current_user_can( 'read_post', $id ) ) {
				$items[] = array_merge( [ 'id' => $id ], $row );
			} else {
				++$hidden;
			}
		}
		$processed = max( 0, (int) $progress['processed'] );
		return [
			'job_id'         => 1 === preg_match( self::JOB_ID_PATTERN, $job_id ) ? $job_id : '',
			'status'         => in_array( $status, self::JOB_STATUSES, true ) ? $status : 'idle',
			'stale'          => (bool) $progress['stale'],
			'total'          => $total,
			'processed'      => $processed,
			'renamed'        => $counts['renamed'],
			'unchanged'      => $counts['unchanged'],
			'refused'        => $counts['refused'],
			'failed'         => $counts['failed'],
			'remaining'      => max( 0, $total - $processed ),
			'percent'        => $total > 0 ? min( 100, (int) floor( $processed / $total * 100 ) ) : 0,
			'tick_scheduled' => (bool) $progress['tick_scheduled'],
			'started_at'     => self::utc( (int) $progress['started_at'] ),
			'updated_at'     => self::utc( (int) $progress['updated_at'] ),
			'results'        => [
				'items'  => $items,
				'hidden' => $hidden,
			],
		];
	}

	/**
	 * Schema of one reported rename result (without the ID).
	 *
	 * @return array<string, array>
	 */
	public static function result_properties(): array {
		$ids = [
			'type'  => 'array',
			'items' => [ 'type' => 'integer' ],
		];
		return [
			'result'               => [
				'type'        => 'string',
				'enum'        => self::RESULTS,
				'description' => 'renamed, unchanged, refused (checked again and not renamed) or failed; empty until processed.',
			],
			'code'                 => [
				'type' => 'string',
				'enum' => self::RESULT_CODES,
			],
			'filename'             => [
				'type'        => 'string',
				'description' => 'The new file name (no folders) when renamed.',
			],
			'sizes'                => [
				'type'        => 'string',
				'enum'        => self::SIZES,
				'description' => 'regenerated (made again under the new name), kept (could not be made again; old sizes kept) or none.',
			],
			'content_posts'        => array_merge( $ids, [ 'description' => 'Posts whose content links were updated, that you may read.' ] ),
			'content_posts_hidden' => [ 'type' => 'integer' ],
			'content_errors'       => [
				'type'        => 'integer',
				'description' => 'Content updates that failed; the file was still renamed.',
			],
		];
	}

	/**
	 * Schema of a job report.
	 *
	 * @return array
	 */
	public static function job_schema(): array {
		$integer = [ 'type' => 'integer' ];
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'job_id', 'status', 'stale', 'total', 'processed', 'renamed', 'unchanged', 'refused', 'failed', 'remaining', 'percent', 'tick_scheduled', 'started_at', 'updated_at', 'results' ],
			'properties'           => [
				'job_id'         => [ 'type' => 'string' ],
				'status'         => [
					'type' => 'string',
					'enum' => self::JOB_STATUSES,
				],
				'stale'          => [ 'type' => 'boolean' ],
				'total'          => $integer,
				'processed'      => $integer,
				'renamed'        => $integer,
				'unchanged'      => $integer,
				'refused'        => $integer,
				'failed'         => $integer,
				'remaining'      => $integer,
				'percent'        => $integer,
				'tick_scheduled' => [ 'type' => 'boolean' ],
				'started_at'     => [ 'type' => 'string' ],
				'updated_at'     => [ 'type' => 'string' ],
				'results'        => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => [ 'items', 'hidden' ],
					'properties'           => [
						'items'  => [
							'type'  => 'array',
							'items' => [
								'type'                 => 'object',
								'additionalProperties' => false,
								'required'             => array_merge( [ 'id' ], array_keys( self::result_properties() ) ),
								'properties'           => array_merge( [ 'id' => $integer ], self::result_properties() ),
							],
						],
						'hidden' => $integer,
					],
				],
			],
		];
	}

	/**
	 * Refuse-everything error while a bulk rename runs.
	 *
	 * @return \WP_Error
	 */
	public static function job_running_error() {
		return AbilityGuard::error( self::ERROR_JOB_RUNNING, __( 'A bulk rename is already running. Nothing was renamed; the confirmation token was not used. Try again when tsmlt/get-media-rename-status no longer reports it running.', 'media-library-tools' ), 409 );
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
