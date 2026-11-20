<?php
/**
 * Ability: tsmlt/convert-media.
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
use TinySolutions\mlt\Modules\Compress\Conversion\McpConversion;
use TinySolutions\mlt\Modules\Compress\McpCompression;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Start the WebP/AVIF conversion a tsmlt/preview-media-conversion token
 * approved, as the existing asynchronous conversion job
 * (CompressionJob::start_conversion()); never in the request. Everything the
 * preview checked is checked again; only images still ready are queued.
 */
class ConvertMedia extends Ability {

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
		return 'tsmlt/convert-media';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Convert images', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Start converting the images a tsmlt/preview-media-conversion call found ready to WebP/AVIF copies. Requires its confirmation_token (works once, for the same user, within 10 minutes) and confirm: true; only call this after the user confirmed the preview. Refused while any compression or conversion job is running (the token is then kept). Everything is checked again: the Pro licence for Pro options, the server\'s formats, the settings, and each image\'s access, file, locks and copy names; images no longer ready are left out and reported. Conversion runs in the background, never in this request; follow it with tsmlt/get-compression-job-status (job_type conversion), see each image with tsmlt/get-conversion-status, and stop it with tsmlt/cancel-conversion-job. Copies are extra files; the images themselves are not changed.', 'media-library-tools' );
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
			'required'             => [ 'job_id', 'job_type', 'status', 'total', 'job_limit', 'limit_applied', 'settings', 'items', 'message' ],
			'properties'           => [
				'job_id'        => [ 'type' => 'string' ],
				'job_type'      => [ 'type' => 'string' ],
				'status'        => [ 'type' => 'string' ],
				'total'         => [
					'type'        => 'integer',
					'description' => 'Images queued.',
				],
				'job_limit'     => [ 'type' => 'integer' ],
				'limit_applied' => [ 'type' => 'boolean' ],
				'settings'      => PreviewMediaConversion::settings_schema(),
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
								'enum' => McpConversion::STATUSES,
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
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Pass the confirmation_token from tsmlt/preview-media-conversion and confirm: true.', 'media-library-tools' ), 400 );
		}

		// Checked before the token is used, so it stays valid for a retry.
		$job   = CompressionJob::instance();
		$state = $job->get_state();
		if ( 'running' === McpCompression::job_status( $state ) ) {
			return self::running_error( true );
		}
		if ( 'running' === (string) $state['status'] ) {
			// Finished or cancelled but still stored as running: let the job
			// record that, as the Compress Images page does on load.
			$job->get_progress();
		}

		$run = McpConversion::consume_token( $input['confirmation_token'] ?? null );
		if ( is_wp_error( $run ) ) {
			return $run;
		}
		$request = is_array( $run['request'] ?? null ) ? $run['request'] : [];
		$include = ! empty( $request['include_converted'] );
		unset( $request['include_converted'] );
		$request['formats'] = (array) ( $request['formats'] ?? [] );

		$refusal = McpConversion::refusal( $request );
		if ( null !== $refusal ) {
			return $refusal;
		}
		$settings = McpConversion::run_settings( $request );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		if ( ( $run['settings'] ?? null ) !== $settings ) {
			return AbilityGuard::error( McpConversion::ERROR_SETTINGS_CHANGED, __( 'The conversion settings changed since the preview. Nothing was started; run tsmlt/preview-media-conversion again.', 'media-library-tools' ), 409 );
		}

		$ids = array_map( 'intval', (array) ( $run['ids'] ?? [] ) );
		_prime_post_caches( $ids, false, true );
		$items = [];
		$ready = [];
		foreach ( $ids as $id ) {
			$status  = McpConversion::item_status( $id, $settings['formats'], $include );
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
			return AbilityGuard::error( McpConversion::ERROR_NOTHING, __( 'None of the previewed images is ready to convert any more. Nothing was started.', 'media-library-tools' ), 409 );
		}

		$started = $job->start_conversion( $ready, $request );
		if ( is_wp_error( $started ) ) {
			return self::start_error( $started );
		}

		$state = McpCompression::job();
		return [
			'job_id'        => $state['job_id'],
			'job_type'      => $state['job_type'],
			'status'        => $state['status'],
			'total'         => $state['total'],
			'job_limit'     => CompressionAccess::instance()->get_conversion_limit(),
			'limit_applied' => ! empty( $started['limit_applied'] ),
			'settings'      => $settings,
			'items'         => $items,
			'message'       => __( 'Conversion started in the background. Follow it with tsmlt/get-compression-job-status; per-image results are available from tsmlt/get-conversion-status.', 'media-library-tools' ),
		];
	}

	/**
	 * A job is running.
	 *
	 * @param bool $token_kept Whether the token was not used.
	 *
	 * @return WP_Error
	 */
	private static function running_error( bool $token_kept ): WP_Error {
		return AbilityGuard::error(
			McpConversion::ERROR_JOB_RUNNING,
			$token_kept
				? __( 'A compression or conversion job is already running. Nothing was started; the confirmation token was not used. Try again when tsmlt/get-compression-job-status no longer reports it running.', 'media-library-tools' )
				: __( 'A compression or conversion job started meanwhile. Nothing was started; run tsmlt/preview-media-conversion again when it has finished.', 'media-library-tools' ),
			409
		);
	}

	/**
	 * Map a job refusal to a stable code and a fixed message, never the
	 * job's own text.
	 *
	 * @param WP_Error $error Job error.
	 *
	 * @return WP_Error
	 */
	private static function start_error( WP_Error $error ): WP_Error {
		switch ( $error->get_error_code() ) {
			case 'tsmlt_compression_job_running':
				return self::running_error( false );
			case 'tsmlt_conversion_no_images':
				return AbilityGuard::error( McpConversion::ERROR_NOTHING, __( 'There is nothing left to convert. Nothing was started.', 'media-library-tools' ), 409 );
			case 'tsmlt_conversion_engine_unavailable':
			case 'tsmlt_conversion_no_formats':
				return AbilityGuard::error( McpConversion::ERROR_FORMAT_UNAVAILABLE, __( 'This server cannot produce the requested formats. Nothing was started.', 'media-library-tools' ), 503 );
			default:
				return AbilityGuard::error( McpConversion::ERROR_START_FAILED, __( 'The conversion job could not be started. Nothing was converted.', 'media-library-tools' ), 500 );
		}
	}
}
