<?php
/**
 * Ability: tsmlt/get-thumbnail-regeneration-status.
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
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Progress of the thumbnail regeneration job (started through MCP or on
 * the Regenerate page). Strictly read-only: never saves, restarts or
 * advances the job. Counts, codes and attachment IDs only; no file names,
 * paths or error texts.
 */
class GetThumbnailRegenerationStatus extends Ability {

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
		return 'tsmlt/get-thumbnail-regeneration-status';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get thumbnail regeneration status', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Return the progress of the thumbnail regeneration job, started by tsmlt/regenerate-thumbnails (job_type selected) or on the Regenerate page (library): job_id, status (idle, running, done or cancelled), stale (running but no progress for 15 minutes and no background step scheduled), total, processed, succeeded, skipped, failed, remaining, percent, whether the next background (WP-Cron) step is scheduled, when it started and was last updated (UTC), and recent per-image results (attachment ID, regenerated, skipped or failed, and a reason code) for images you may read; others are counted in hidden. Error messages and file names are not returned. Read-only; never changes or advances the job.', 'media-library-tools' );
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
	 * Input schema: no input.
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			// Lets clients call the ability with no arguments at all.
			'default'              => [],
			'properties'           => [],
		];
	}

	/**
	 * Output schema, shared with tsmlt/cancel-thumbnail-regeneration.
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {
		return self::job_schema();
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
			'required'             => [ 'job_id', 'job_type', 'status', 'stale', 'total', 'processed', 'succeeded', 'skipped', 'failed', 'remaining', 'percent', 'tick_scheduled', 'started_at', 'updated_at', 'results' ],
			'properties'           => [
				'job_id'         => [ 'type' => 'string' ],
				'job_type'       => [
					'type' => 'string',
					'enum' => [ 'selected', 'library', '' ],
				],
				'status'         => [
					'type' => 'string',
					'enum' => McpRegenerate::JOB_STATUSES,
				],
				'stale'          => [ 'type' => 'boolean' ],
				'total'          => $integer,
				'processed'      => $integer,
				'succeeded'      => $integer,
				'skipped'        => $integer,
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
								'required'             => [ 'id', 'result', 'code' ],
								'properties'           => [
									'id'     => $integer,
									'result' => [
										'type' => 'string',
										'enum' => [ 'regenerated', 'skipped', 'failed' ],
									],
									'code'   => [
										'type' => 'string',
										'enum' => McpRegenerate::RESULT_CODES,
									],
								],
							],
						],
						'hidden' => $integer,
					],
				],
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
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		];
	}

	/**
	 * Report the job.
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
		if ( null !== $input && ( ! is_array( $input ) || ! empty( $input ) ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'This tool takes no input.', 'media-library-tools' ), 400 );
		}
		return McpRegenerate::job();
	}
}
