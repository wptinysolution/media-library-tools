<?php
/**
 * Ability: tsmlt/compress-media.
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
use TinySolutions\mlt\Modules\Compress\McpCompression;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Start the compression run a tsmlt/preview-compression token describes, as
 * the existing asynchronous CompressionJob (WP-Cron), never in the request.
 *
 * Everything the preview checked is checked again: the user, the Pro-only
 * options, the effective settings, each image's access, file and locks.
 * Only images still ready are queued.
 */
class CompressMedia extends Ability {

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
		return 'tsmlt/compress-media';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Compress images', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Start compressing the images a tsmlt/preview-compression call checked. Requires its confirmation_token (works once, for the same user, within 10 minutes) and confirm: true; only call this after the user confirmed the preview. Everything is checked again: images no longer ready are left out and reported, and the run is refused if the settings changed or a Pro option is no longer available. Refused while any compression or conversion job is running (the token is then kept). Compression runs in the background through the Compress Images job (WP-Cron), never in this request; follow it with tsmlt/get-compression-job-status and stop it with tsmlt/cancel-compression-job. Without a backup (Pro) compression permanently replaces each image file.', 'media-library-tools' );
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
			'required'             => [ 'confirmation_token', 'confirm' ],
			'properties'           => [
				'confirmation_token' => [
					'type'    => 'string',
					'pattern' => '^[a-f0-9]{64}$',
				],
				'confirm'            => [
					'type' => 'boolean',
					'enum' => [ true ],
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
			'required'             => [ 'job_id', 'job_type', 'status', 'scope', 'total', 'job_limit', 'limit_applied', 'settings', 'reversible', 'items', 'message' ],
			'properties'           => [
				'job_id'        => [ 'type' => 'string' ],
				'job_type'      => [ 'type' => 'string' ],
				'status'        => [ 'type' => 'string' ],
				'scope'         => [
					'type' => 'string',
					'enum' => McpCompression::SCOPES,
				],
				'total'         => [
					'type'        => 'integer',
					'description' => 'Images queued.',
				],
				'job_limit'     => [
					'type'        => 'integer',
					'description' => 'Most images per job; 0 means no limit.',
				],
				'limit_applied' => [ 'type' => 'boolean' ],
				'settings'      => PreviewCompression::settings_schema(),
				'reversible'    => [ 'type' => 'boolean' ],
				'items'         => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'status', 'queued' ],
						'properties'           => [
							'id'     => [ 'type' => 'integer' ],
							'status' => [
								'type' => 'string',
								'enum' => McpCompression::STATUSES,
							],
							'queued' => [ 'type' => 'boolean' ],
						],
					],
				],
				'message'       => [ 'type' => 'string' ],
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
			'destructive' => true,
			'idempotent'  => false,
		];
	}

	/**
	 * Check the token and start the job.
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
		if ( ! is_array( $input ) || [] !== array_diff( array_keys( $input ), [ 'confirmation_token', 'confirm' ] ) || true !== ( $input['confirm'] ?? null ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Pass the confirmation_token from tsmlt/preview-compression and confirm: true.', 'media-library-tools' ), 400 );
		}
		$engine = McpCompression::engine_refusal();
		if ( null !== $engine ) {
			return $engine;
		}

		// Checked before the token is used, so it stays valid for a retry.
		$job   = CompressionJob::instance();
		$state = $job->get_state();
		if ( 'running' === McpCompression::job_status( $state ) ) {
			return self::running_error();
		}
		if ( 'running' === (string) $state['status'] ) {
			// Finished or cancelled but still stored as running: let the job
			// record that, as the Compress Images page does on load.
			$job->get_progress();
		}

		$run = McpCompression::consume_token( $input['confirmation_token'] ?? null );
		if ( is_wp_error( $run ) ) {
			return $run;
		}
		$request = is_array( $run['request'] ?? null ) ? $run['request'] : [];
		$refusal = McpCompression::pro_refusal( $request );
		if ( null !== $refusal ) {
			return $refusal;
		}
		$settings = McpCompression::run_settings( $request );
		if ( ( $run['settings'] ?? null ) !== $settings ) {
			return AbilityGuard::error( McpCompression::ERROR_SETTINGS_CHANGED, __( 'The compression settings changed since the preview. Nothing was started; run tsmlt/preview-compression again.', 'media-library-tools' ), 409 );
		}

		$include = ! empty( $run['include_compressed'] );
		$scope   = 'library' === ( $run['scope'] ?? '' ) ? 'library' : 'selected';
		$items   = [];
		if ( 'selected' === $scope ) {
			$ids = array_map( 'intval', (array) ( $run['ids'] ?? [] ) );
			_prime_post_caches( $ids, false, true );
			$ready = [];
			foreach ( $ids as $id ) {
				$status  = McpCompression::item_status( $id, $include );
				$items[] = [
					'id'     => $id,
					'status' => $status,
					'queued' => 'ready' === $status,
				];
				if ( 'ready' === $status ) {
					$ready[] = $id;
				}
			}
			if ( ! $ready ) {
				return AbilityGuard::error( McpCompression::ERROR_NOTHING, __( 'None of the previewed images is ready to compress any more. Nothing was started.', 'media-library-tools' ), 409 );
			}
			$started = $job->start( $ready, $request );
		} else {
			$started = $job->start_library( $request + [ 'include_compressed' => $include ] );
		}

		if ( is_wp_error( $started ) ) {
			return self::start_error( $started );
		}

		$state = McpCompression::job();
		return [
			'job_id'        => $state['job_id'],
			'job_type'      => $state['job_type'],
			'status'        => $state['status'],
			'scope'         => $scope,
			'total'         => $state['total'],
			'job_limit'     => CompressionAccess::instance()->get_compression_limit(),
			'limit_applied' => ! empty( $started['limit_applied'] ),
			'settings'      => $settings,
			'reversible'    => $settings['backup_originals'],
			'items'         => $items,
			'message'       => __( 'Compression started in the background. Follow it with tsmlt/get-compression-job-status; per-image results are available from tsmlt/get-compression-status.', 'media-library-tools' ),
		];
	}

	/**
	 * A job is running.
	 *
	 * @return WP_Error
	 */
	private static function running_error(): WP_Error {
		return AbilityGuard::error( McpCompression::ERROR_JOB_RUNNING, __( 'A compression or conversion job is already running. Nothing was started; the confirmation token was not used. Try again when tsmlt/get-compression-job-status no longer reports it running.', 'media-library-tools' ), 409 );
	}

	/**
	 * Map a CompressionJob refusal to a stable code and a fixed message,
	 * never the job's own text.
	 *
	 * @param WP_Error $error Job error.
	 *
	 * @return WP_Error
	 */
	private static function start_error( WP_Error $error ): WP_Error {
		switch ( $error->get_error_code() ) {
			case 'tsmlt_compression_job_running':
				return AbilityGuard::error( McpCompression::ERROR_JOB_RUNNING, __( 'A compression or conversion job started meanwhile. Nothing was started; run tsmlt/preview-compression again when it has finished.', 'media-library-tools' ), 409 );
			case 'tsmlt_compression_no_images':
			case 'tsmlt_compression_nothing_to_do':
				return AbilityGuard::error( McpCompression::ERROR_NOTHING, __( 'There is nothing left to compress. Nothing was started.', 'media-library-tools' ), 409 );
			case 'tsmlt_compression_engine_unavailable':
				return McpCompression::engine_refusal() ?? AbilityGuard::error( McpCompression::ERROR_ENGINE, __( 'No image compression library is available.', 'media-library-tools' ), 503 );
			default:
				return AbilityGuard::error( McpCompression::ERROR_START_FAILED, __( 'The compression job could not be started. Nothing was compressed.', 'media-library-tools' ), 500 );
		}
	}
}
