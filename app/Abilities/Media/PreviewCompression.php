<?php
/**
 * Ability: tsmlt/preview-compression.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities\Media;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abs\Ability;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Modules\Compress\CompressionAccess;
use TinySolutions\mlt\Modules\Compress\CompressionJob;
use TinySolutions\mlt\Modules\Compress\CompressionSettings;
use TinySolutions\mlt\Modules\Compress\McpCompression;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Check what a compression run would do — selected images (1 to 20) or the
 * whole library — with which effective settings, and issue the one-time
 * confirmation token tsmlt/compress-media needs.
 *
 * Changes no image, metadata, backup or job: the only write is the token
 * itself (and pruning expired ones), so it is not annotated read-only.
 */
class PreviewCompression extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/preview-compression';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Preview image compression', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Check an image compression run before starting it, and get the confirmation_token tsmlt/compress-media requires. scope "selected" (default) checks 1 to 20 ids; scope "library" counts the supported images of the whole library. Per image: ready, already_compressed (skipped unless include_compressed is true), unsupported (JPEG, PNG and WebP only), file_missing, locked (being compressed, converted or having its EXIF removed), not_found or forbidden. mode: high_quality, balanced or maximum (default: the Compress Images setting). quality (1-100), backup_originals: true and compress_generated_sizes: true need Media Library Tools Pro and are refused otherwise. Returns the effective settings, whether the result can be undone (reversible: only when a backup of the original is kept, a Pro feature; otherwise compression permanently replaces the image file), the per-job limit (20 images on Free; 0 means no limit) and, when something is ready, a token that works once, for you, within 10 minutes. Show the user the items, settings and whether it is reversible, and ask them to confirm before calling tsmlt/compress-media. Changes no image; it only stores the short-lived token.', 'media-library-tools' );
	}

	/**
	 * Listed by name only; not run through the default gateway.
	 *
	 * @return bool
	 */
	protected function is_mcp_public(): bool {
		return false;
	}

	/**
	 * Input schema.
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'scope'                    => [
					'type'    => 'string',
					'enum'    => McpCompression::SCOPES,
					'default' => 'selected',
				],
				'ids'                      => [
					'type'        => 'array',
					'description' => 'Attachment IDs, with scope "selected" only.',
					'minItems'    => 1,
					'maxItems'    => McpCompression::MAX_IDS,
					'uniqueItems' => true,
					'items'       => [
						'type'    => 'integer',
						'minimum' => 1,
					],
				],
				'mode'                     => [
					'type' => 'string',
					'enum' => array_keys( CompressionSettings::PRESETS ),
				],
				'quality'                  => [
					'type'        => 'integer',
					'description' => 'Custom quality (Pro).',
					'minimum'     => 1,
					'maximum'     => 100,
				],
				'backup_originals'         => [
					'type'        => 'boolean',
					'description' => 'Keep a backup of each original (Pro).',
				],
				'compress_generated_sizes' => [
					'type'        => 'boolean',
					'description' => 'Also compress generated sizes (Pro).',
				],
				'include_compressed'       => [
					'type'        => 'boolean',
					'description' => 'Compress images that were already compressed again.',
				],
			],
		];
	}

	/**
	 * Output schema.
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'scope', 'settings', 'reversible', 'job_limit', 'job_running', 'items', 'summary', 'library', 'ready_count', 'confirmation_token', 'expires_at', 'message' ],
			'properties'           => [
				'scope'              => [
					'type' => 'string',
					'enum' => McpCompression::SCOPES,
				],
				'settings'           => self::settings_schema(),
				'reversible'         => [
					'type'        => 'boolean',
					'description' => 'True only when a backup of each original is kept (Pro).',
				],
				'job_limit'          => [
					'type'        => 'integer',
					'description' => 'Most images per job; 0 means no limit.',
				],
				'job_running'        => [
					'type'        => 'boolean',
					'description' => 'A compression or conversion job is running now; tsmlt/compress-media refuses to start until it ends.',
				],
				'items'              => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'status' ],
						'properties'           => [
							'id'     => [ 'type' => 'integer' ],
							'status' => [
								'type' => 'string',
								'enum' => McpCompression::STATUSES,
							],
						],
					],
				],
				'summary'            => [
					'type'                 => 'object',
					'additionalProperties' => [ 'type' => 'integer' ],
				],
				'library'            => [
					'type'                 => [ 'object', 'null' ],
					'additionalProperties' => false,
					'properties'           => [
						'total'      => [ 'type' => 'integer' ],
						'compressed' => [ 'type' => 'integer' ],
						'to_queue'   => [ 'type' => 'integer' ],
					],
				],
				'ready_count'        => [ 'type' => 'integer' ],
				'confirmation_token' => [ 'type' => [ 'string', 'null' ] ],
				'expires_at'         => [
					'type'        => 'string',
					'description' => 'When the token expires, UTC (Y-m-d H:i:s), or empty.',
				],
				'message'            => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * Effective settings schema, shared with tsmlt/compress-media.
	 *
	 * @return array
	 */
	public static function settings_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'mode', 'quality', 'use_custom_quality', 'backup_originals', 'compress_generated_sizes' ],
			'properties'           => [
				'mode'                     => [ 'type' => 'string' ],
				'quality'                  => [
					'type'        => 'integer',
					'description' => 'Custom quality, or 0 for the mode\'s preset.',
				],
				'use_custom_quality'       => [ 'type' => 'boolean' ],
				'backup_originals'         => [ 'type' => 'boolean' ],
				'compress_generated_sizes' => [ 'type' => 'boolean' ],
			],
		];
	}

	/**
	 * Annotations.
	 *
	 * @return array<string, bool>
	 */
	protected function get_annotations(): array {
		return [
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		];
	}

	/**
	 * Check the run and issue the token.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		// The callback can be reached without the permission callback.
		$base = AbilityGuard::check_base_capability();
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$run = McpCompression::sanitize_preview( $input );
		if ( is_wp_error( $run ) ) {
			return $run;
		}
		$refusal = McpCompression::pro_refusal( $run['request'] );
		if ( null === $refusal ) {
			$refusal = McpCompression::engine_refusal();
		}
		if ( null !== $refusal ) {
			return $refusal;
		}

		$settings = McpCompression::run_settings( $run['request'] );
		$items    = [];
		$summary  = [];
		$library  = null;
		if ( 'selected' === $run['scope'] ) {
			_prime_post_caches( $run['ids'], false, true );
			$summary = array_fill_keys( McpCompression::STATUSES, 0 );
			foreach ( $run['ids'] as $id ) {
				$status = McpCompression::item_status( $id, $run['include_compressed'] );
				++$summary[ $status ];
				$items[] = [
					'id'     => $id,
					'status' => $status,
				];
			}
			$ready = $summary['ready'];
		} else {
			$library = McpCompression::library( $run['include_compressed'] );
			$ready   = $library['to_queue'];
		}

		$token = null;
		if ( $ready > 0 ) {
			$run['settings'] = $settings;
			$token           = McpCompression::issue_token( $run );
			if ( null === $token ) {
				return new WP_Error( McpCompression::ERROR_START_FAILED, __( 'The confirmation token could not be stored. Try again.', 'media-library-tools' ), [ 'status' => 500 ] );
			}
		}
		$job_running = 'running' === McpCompression::job_status( CompressionJob::instance()->get_state() );

		return [
			'scope'              => $run['scope'],
			'settings'           => $settings,
			'reversible'         => $settings['backup_originals'],
			'job_limit'          => CompressionAccess::instance()->get_compression_limit(),
			'job_running'        => $job_running,
			'items'              => $items,
			'summary'            => $summary,
			'library'            => $library,
			'ready_count'        => $ready,
			'confirmation_token' => $token ? $token['token'] : null,
			'expires_at'         => $token ? gmdate( 'Y-m-d H:i:s', $token['expires'] ) : '',
			'message'            => self::message( $ready, $settings['backup_originals'], $job_running ),
		];
	}

	/**
	 * Guidance for the client.
	 *
	 * @param int  $ready       Images that would be queued.
	 * @param bool $reversible  Whether backups are kept.
	 * @param bool $job_running Whether a job is running.
	 *
	 * @return string
	 */
	private static function message( int $ready, bool $reversible, bool $job_running ): string {
		if ( $ready < 1 ) {
			return __( 'Nothing is ready to compress, so no token was issued.', 'media-library-tools' );
		}
		$message  = $reversible
			? __( 'A backup of each original is kept, so the result can be restored in WordPress Admin.', 'media-library-tools' )
			: __( 'No backup is kept: compression permanently replaces each image file and cannot be undone.', 'media-library-tools' );
		$message .= ' ' . __( 'Ask the user to confirm, then call tsmlt/compress-media with the confirmation_token and confirm: true. Compression runs in the background; follow it with tsmlt/get-compression-job-status.', 'media-library-tools' );
		if ( $job_running ) {
			$message .= ' ' . __( 'A compression or conversion job is running now; wait for it to finish (the token stays valid for 10 minutes).', 'media-library-tools' );
		}
		return $message;
	}
}
