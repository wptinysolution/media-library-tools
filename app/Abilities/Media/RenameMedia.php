<?php
/**
 * Ability: tsmlt/rename-media.
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
use TinySolutions\mlt\Modules\Rename\MediaRenamer;
use TinySolutions\mlt\Modules\Rename\RenameJob;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;

/**
 * Rename the files a tsmlt/preview-media-rename token approved, with the
 * names typed there. Every item is checked again first (permissions, status,
 * file, locks, name); items no longer ready are reported and not renamed.
 * A token for one item is renamed in the request by MediaRenamer; a token
 * for several starts the background RenameJob, which checks each item once
 * more when it renames it.
 */
class RenameMedia extends Ability {

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
		return 'tsmlt/rename-media';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Rename media files', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Rename the media files a tsmlt/preview-media-rename call found ready, to the names typed there. Requires its confirmation_token (works once, for the same user, within 10 minutes) and confirm: true; only call this after the user confirmed the preview. Refused while a bulk rename is running (the token is then kept). Everything is checked again; items no longer ready are reported with their status and not renamed, and a taken name may get a "-1"-style number. One item is renamed in this request (mode single, with its result); several are renamed in the background (mode bulk, with a job_id: follow with tsmlt/get-media-rename-status, stop with tsmlt/cancel-media-rename). The full-size original of a scaled or rotated upload is renamed too, sizes are made again under the new name, and links are updated in post content, excerpts and Elementor data (failures reported in content_errors). Not undone automatically.', 'media-library-tools' );
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
			'required'             => [ 'mode', 'job_id', 'status', 'total', 'items', 'message' ],
			'properties'           => [
				'mode'    => [
					'type'        => 'string',
					'enum'        => [ 'single', 'bulk', 'none' ],
					'description' => 'single: renamed in this request; bulk: queued as a background job; none: nothing was ready any more.',
				],
				'job_id'  => [ 'type' => 'string' ],
				'status'  => [
					'type'        => 'string',
					'description' => 'The job status (bulk), or done (single, none).',
				],
				'total'   => [
					'type'        => 'integer',
					'description' => 'Items renamed or queued.',
				],
				'items'   => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array_merge( [ 'id', 'status', 'queued' ], array_keys( McpRename::result_properties() ) ),
						'properties'           => array_merge(
							[
								'id'     => [ 'type' => 'integer' ],
								'status' => [
									'type'        => 'string',
									'enum'        => McpRename::STATUSES,
									'description' => 'The status when checked again now.',
								],
								'queued' => [ 'type' => 'boolean' ],
							],
							McpRename::result_properties()
						),
					],
				],
				'message' => [ 'type' => 'string' ],
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
	 * Check the token, check every item again, then rename.
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
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Pass the confirmation_token from tsmlt/preview-media-rename and confirm: true.', 'media-library-tools' ), 400 );
		}
		$job = RenameJob::instance();
		// Checked before the token is used, so it stays valid for a retry.
		if ( $job->has_active_job() ) {
			return McpRename::job_running_error();
		}

		$run = McpRename::consume_token( $input['confirmation_token'] ?? null );
		if ( is_wp_error( $run ) ) {
			return $run;
		}
		$stored = [];
		foreach ( (array) ( $run['items'] ?? [] ) as $entry ) {
			$id = (int) ( $entry['id'] ?? 0 );
			if ( $id > 0 && is_string( $entry['name'] ?? null ) && ! isset( $stored[ $id ] ) ) {
				$stored[ $id ] = $entry['name'];
			}
		}
		_prime_post_caches( array_keys( $stored ), false, true );

		$items = [];
		$ready = [];
		foreach ( $stored as $id => $name ) {
			$status  = self::status( $id, $name );
			$items[] = array_merge(
				[
					'id'     => $id,
					'status' => $status,
					'queued' => false,
				],
				McpRename::result( [ 'status' => '' ] )
			);
			if ( 'ready' === $status ) {
				$ready[ $id ] = $name;
			}
		}

		if ( ! $ready ) {
			return self::report( 'none', '', 'done', 0, $items, __( 'None of the previewed items is ready to rename any more. Nothing was renamed; see each item\'s status.', 'media-library-tools' ) );
		}

		if ( 1 === count( $stored ) ) {
			$id     = (int) key( $ready );
			$result = McpRename::result( MediaRenamer::rename( $id, (string) $ready[ $id ], get_current_user_id() ) );
			$items  = [ array_merge( $items[0], $result ) ];
			return self::report( 'single', '', 'done', 'renamed' === $result['result'] ? 1 : 0, $items, self::single_message( $result ) );
		}

		$started = $job->start( $ready );
		if ( is_wp_error( $started ) ) {
			return 'tsmlt_rename_running' === $started->get_error_code()
				? AbilityGuard::error( McpRename::ERROR_JOB_RUNNING, __( 'A bulk rename started meanwhile. Nothing was renamed; run tsmlt/preview-media-rename again when it has finished.', 'media-library-tools' ), 409 )
				: AbilityGuard::error( McpRename::ERROR_START_FAILED, __( 'The bulk rename could not be started. Nothing was renamed.', 'media-library-tools' ), 500 );
		}
		foreach ( $items as $index => $item ) {
			$items[ $index ]['queued'] = isset( $ready[ $item['id'] ] );
		}
		$report = McpRename::job();
		return self::report( 'bulk', $report['job_id'], $report['status'], count( $ready ), $items, __( 'The rename started in the background. Follow it with tsmlt/get-media-rename-status (per-item results appear as items are renamed); stop it with tsmlt/cancel-media-rename.', 'media-library-tools' ) );
	}

	/**
	 * An item's status now, as the preview reports it; inaccessible items
	 * are not_found.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $name Typed name.
	 *
	 * @return string
	 */
	private static function status( int $id, string $name ): string {
		$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );
		if ( ! $attachment instanceof WP_Post || 'inherit' !== $attachment->post_status ) {
			return 'not_found';
		}
		if ( ! AbilityGuard::can_access_attachment( $attachment, 'edit_post' ) ) {
			return 'forbidden';
		}
		return McpRename::check( $id, $name )['status'];
	}

	/**
	 * The response.
	 *
	 * @param string $mode    single, bulk or none.
	 * @param string $job_id  Job ID (bulk).
	 * @param string $status  Status.
	 * @param int    $total   Items renamed or queued.
	 * @param array  $items   Items.
	 * @param string $message Message.
	 *
	 * @return array
	 */
	private static function report( string $mode, string $job_id, string $status, int $total, array $items, string $message ): array {
		return [
			'mode'    => $mode,
			'job_id'  => $job_id,
			'status'  => $status,
			'total'   => $total,
			'items'   => $items,
			'message' => $message,
		];
	}

	/**
	 * Message for a single rename.
	 *
	 * @param array $result Reported result.
	 *
	 * @return string
	 */
	private static function single_message( array $result ): string {
		if ( 'renamed' !== $result['result'] ) {
			return __( 'The file was not renamed; see the item\'s result and code.', 'media-library-tools' );
		}
		$message = __( 'The file was renamed.', 'media-library-tools' );
		if ( 'kept' === $result['sizes'] ) {
			$message .= ' ' . __( 'Its sizes could not be made again under the new name; the old size files were kept and still work.', 'media-library-tools' );
		}
		if ( $result['content_errors'] > 0 ) {
			$message .= ' ' . __( 'Some links in content could not be updated.', 'media-library-tools' );
		}
		return $message;
	}
}
