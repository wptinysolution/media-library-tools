<?php
/**
 * Ability: tsmlt/cancel-media-rename.
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
use TinySolutions\mlt\Modules\Rename\McpRename;
use TinySolutions\mlt\Modules\Rename\RenameJob;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Stop the running bulk rename job with the given ID (RenameJob::cancel()).
 * Only that rename job: compression, conversion and regeneration jobs are
 * never touched.
 */
class CancelMediaRename extends Ability {

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
		return 'tsmlt/cancel-media-rename';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Cancel media rename', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Stop the running bulk rename. Pass the job_id from tsmlt/rename-media or tsmlt/get-media-rename-status; it must be the current rename job and running (a done or cancelled job is refused). Compression, conversion and thumbnail regeneration jobs are never cancelled. Files already renamed keep their new names; the item being renamed finishes. Returns the job as tsmlt/get-media-rename-status reports it.', 'media-library-tools' );
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
		return McpRename::job_schema();
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
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Pass the job_id of the running rename job.', 'media-library-tools' ), 400 );
		}
		$engine = RenameJob::instance();
		$state  = $engine->get_state();
		if ( '' === (string) $state['job_id'] || (string) $state['job_id'] !== $job_id ) {
			return AbilityGuard::error( McpRename::ERROR_JOB_MISMATCH, __( 'That is not the current rename job. Nothing was cancelled; check tsmlt/get-media-rename-status.', 'media-library-tools' ), 404 );
		}
		if ( 'running' !== (string) $state['status'] ) {
			return AbilityGuard::error( McpRename::ERROR_NOT_RUNNING, __( 'The rename job is not running. Nothing was cancelled.', 'media-library-tools' ), 409 );
		}
		$cancelled = $engine->cancel( $job_id );
		if ( is_wp_error( $cancelled ) ) {
			return AbilityGuard::error( McpRename::ERROR_JOB_MISMATCH, __( 'The job changed meanwhile. Nothing was cancelled.', 'media-library-tools' ), 409 );
		}
		return McpRename::job();
	}
}
