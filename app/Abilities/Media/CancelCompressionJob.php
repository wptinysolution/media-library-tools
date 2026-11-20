<?php
/**
 * Ability: tsmlt/cancel-compression-job.
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
use TinySolutions\mlt\Modules\Compress\CompressionJob;
use TinySolutions\mlt\Modules\Compress\McpCompression;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Stop the running compression job through CompressionJob::cancel(), the
 * Compress Images page's Stop. Only a running job of type compression with
 * the given job_id is cancelled; a conversion job is never touched. Images
 * already compressed stay compressed.
 */
class CancelCompressionJob extends Ability {

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
		return 'tsmlt/cancel-compression-job';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Cancel compression job', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Stop the running image compression job, like Stop on the Compress Images page. Pass the job_id from tsmlt/compress-media or tsmlt/get-compression-job-status; it must be the current job, of type compression, and running. A conversion job is never cancelled. Images already compressed stay compressed (nothing is undone); the rest are not processed. Returns the job as tsmlt/get-compression-job-status reports it.', 'media-library-tools' );
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
			'required'             => [ 'job_id' ],
			'properties'           => [
				'job_id' => [
					'type'      => 'string',
					'minLength' => 1,
					'maxLength' => 64,
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
		return GetCompressionJobStatus::job_schema();
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
	 * Cancel the job.
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
		$job_id = is_array( $input ) && [ 'job_id' ] === array_keys( $input ) ? $input['job_id'] : null;
		if ( ! is_string( $job_id ) || '' === $job_id ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Pass the job_id of the running compression job.', 'media-library-tools' ), 400 );
		}

		$job   = CompressionJob::instance();
		$state = $job->get_state();
		if ( (string) ( $state['job_id'] ?? '' ) !== $job_id ) {
			return AbilityGuard::error( McpCompression::ERROR_JOB_MISMATCH, __( 'That is not the current job. Nothing was cancelled; check tsmlt/get-compression-job-status.', 'media-library-tools' ), 404 );
		}
		if ( CompressionJob::TYPE_COMPRESSION !== (string) ( $state['job_type'] ?? CompressionJob::TYPE_COMPRESSION ) ) {
			return AbilityGuard::error( McpCompression::ERROR_NOT_COMPRESSION, __( 'The current job is a conversion job, which this tool does not cancel. Nothing was cancelled.', 'media-library-tools' ), 409 );
		}
		if ( 'running' !== McpCompression::job_status( $state ) ) {
			return AbilityGuard::error( McpCompression::ERROR_NOT_RUNNING, __( 'The compression job is not running. Nothing was cancelled.', 'media-library-tools' ), 409 );
		}

		$job->cancel();
		return McpCompression::job();
	}
}
