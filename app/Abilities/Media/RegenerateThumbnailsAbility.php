<?php
/**
 * Ability: tsmlt/regenerate-thumbnails.
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
use TinySolutions\mlt\Modules\Regenerate\McpRegenerate;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Start regenerating the thumbnails of the images a
 * tsmlt/preview-thumbnail-regeneration token approved, as the existing
 * background regeneration job with a fixed ID queue
 * (RegenerateThumbnails::start()); never in the request, never the whole
 * library. Every image is checked again first; only images still ready are
 * queued.
 */
class RegenerateThumbnailsAbility extends Ability {

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
		return 'tsmlt/regenerate-thumbnails';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Regenerate thumbnails', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Start regenerating the thumbnails of the images a tsmlt/preview-thumbnail-regeneration call found ready. Requires its confirmation_token (works once, for the same user, within 10 minutes) and confirm: true; only call this after the user confirmed the preview. Refused while a regeneration job is running (the token is then kept). Everything is checked again: images no longer ready are left out and reported, and the run is refused if the image sizes changed since the preview. The images are regenerated in the background, never in this request; follow the job with tsmlt/get-thumbnail-regeneration-status and stop it with tsmlt/cancel-thumbnail-regeneration. Not reversible: existing size files are replaced.', 'media-library-tools' );
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
			'required'             => [ 'job_id', 'job_type', 'status', 'total', 'items', 'message' ],
			'properties'           => [
				'job_id'   => [ 'type' => 'string' ],
				'job_type' => [ 'type' => 'string' ],
				'status'   => [ 'type' => 'string' ],
				'total'    => [
					'type'        => 'integer',
					'description' => 'Images queued.',
				],
				'items'    => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'status', 'queued' ],
						'properties'           => [
							'id'     => [ 'type' => 'integer' ],
							'status' => [
								'type' => 'string',
								'enum' => McpRegenerate::STATUSES,
							],
							'queued' => [ 'type' => 'boolean' ],
						],
					],
				],
				'message'  => [ 'type' => 'string' ],
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
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Pass the confirmation_token from tsmlt/preview-thumbnail-regeneration and confirm: true.', 'media-library-tools' ), 400 );
		}
		$engine = RegenerateThumbnails::instance();
		// Checked before the token is used, so it stays valid for a retry.
		if ( $engine->has_active_job() ) {
			return AbilityGuard::error( McpRegenerate::ERROR_JOB_RUNNING, __( 'A thumbnail regeneration job is already running. Nothing was started; the confirmation token was not used. Try again when tsmlt/get-thumbnail-regeneration-status no longer reports it running.', 'media-library-tools' ), 409 );
		}

		$run = McpRegenerate::consume_token( $input['confirmation_token'] ?? null );
		if ( is_wp_error( $run ) ) {
			return $run;
		}
		if ( ( $run['fingerprint'] ?? '' ) !== McpRegenerate::sizes()['fingerprint'] ) {
			return AbilityGuard::error( McpRegenerate::ERROR_CHANGED, __( 'The image sizes changed since the preview. Nothing was started; run tsmlt/preview-thumbnail-regeneration again.', 'media-library-tools' ), 409 );
		}

		$ids = array_map( 'intval', (array) ( $run['ids'] ?? [] ) );
		_prime_post_caches( $ids, false, true );
		$items = [];
		$ready = [];
		foreach ( $ids as $id ) {
			$status  = McpRegenerate::item( $id )['status'];
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
			return AbilityGuard::error( McpRegenerate::ERROR_NOTHING, __( 'None of the previewed images is ready to regenerate any more. Nothing was started.', 'media-library-tools' ), 409 );
		}

		$started = $engine->start( $ready );
		if ( is_wp_error( $started ) ) {
			return 'tsmlt_regenerate_running' === $started->get_error_code()
				? AbilityGuard::error( McpRegenerate::ERROR_JOB_RUNNING, __( 'A thumbnail regeneration job started meanwhile. Nothing was started; run tsmlt/preview-thumbnail-regeneration again when it has finished.', 'media-library-tools' ), 409 )
				: AbilityGuard::error( McpRegenerate::ERROR_START_FAILED, __( 'The regeneration job could not be started. Nothing was changed.', 'media-library-tools' ), 500 );
		}

		$job = McpRegenerate::job();
		return [
			'job_id'   => $job['job_id'],
			'job_type' => $job['job_type'],
			'status'   => $job['status'],
			'total'    => $job['total'],
			'items'    => $items,
			'message'  => __( 'Thumbnail regeneration started in the background. Follow it with tsmlt/get-thumbnail-regeneration-status; per-image thumbnail status is available from tsmlt/get-thumbnail-status.', 'media-library-tools' ),
		];
	}
}
