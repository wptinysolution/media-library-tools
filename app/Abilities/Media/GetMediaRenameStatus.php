<?php
/**
 * Ability: tsmlt/get-media-rename-status.
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
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Progress of the bulk rename job started by tsmlt/rename-media. Strictly
 * read-only: never saves, restarts or advances the job. Counts, codes and
 * per-item results for attachments the user may read.
 */
class GetMediaRenameStatus extends Ability {

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
		return 'tsmlt/get-media-rename-status';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get media rename status', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Return the progress of the bulk rename started by tsmlt/rename-media: job_id, status (idle, running, done or cancelled), stale (running but no progress for 15 minutes and no background step scheduled), total, processed, renamed, unchanged, refused and failed counts, remaining, percent, whether the next background (WP-Cron) step is scheduled, when it started and was last updated (UTC), and per-item results (attachment ID, result, code, new file name, sizes, posts whose links were updated, failed content updates) for attachments you may read; others are counted in hidden. Read-only; never changes or advances the job.', 'media-library-tools' );
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
	 * Output schema, shared with tsmlt/cancel-media-rename.
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
		return McpRename::job();
	}
}
