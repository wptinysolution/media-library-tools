<?php
/**
 * Ability: tsmlt/get-compression-job-status.
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
use TinySolutions\mlt\Modules\Compress\McpCompression;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Progress of the Compress Images job (compression, or a conversion run,
 * which shares the job). Strictly read-only: never saves the job state,
 * processes a batch or schedules a tick. Counts and codes only; no image
 * IDs, titles, settings or error texts.
 */
class GetCompressionJobStatus extends Ability {

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
		return 'tsmlt/get-compression-job-status';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get compression job status', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Return the progress of the Compress Images job started by tsmlt/compress-media or in WordPress Admin: job_id, job_type (compression, or conversion: both features share one job, so a running conversion also blocks compression), status (idle, running, completed, partial: finished with failures, failed or cancelled), total, processed, succeeded, skipped, failed, remaining, percent, bytes saved, whether the next background (WP-Cron) step is scheduled, and when it started and was last updated (UTC). Per-image results are in tsmlt/get-compression-status. Error messages are not returned. Read-only; never processes or changes the job.', 'media-library-tools' );
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
	 * Output schema, shared with tsmlt/cancel-compression-job.
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
			'required'             => [ 'job_id', 'job_type', 'status', 'total', 'processed', 'succeeded', 'skipped', 'failed', 'remaining', 'percent', 'saved_bytes', 'tick_scheduled', 'started_at', 'updated_at' ],
			'properties'           => [
				'job_id'         => [ 'type' => 'string' ],
				'job_type'       => [
					'type' => 'string',
					'enum' => [ 'compression', 'conversion', '' ],
				],
				'status'         => [
					'type' => 'string',
					'enum' => McpCompression::JOB_STATUSES,
				],
				'total'          => $integer,
				'processed'      => $integer,
				'succeeded'      => $integer,
				'skipped'        => $integer,
				'failed'         => $integer,
				'remaining'      => $integer,
				'percent'        => $integer,
				'saved_bytes'    => $integer,
				'tick_scheduled' => [ 'type' => 'boolean' ],
				'started_at'     => [ 'type' => 'string' ],
				'updated_at'     => [ 'type' => 'string' ],
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
		return McpCompression::job();
	}
}
