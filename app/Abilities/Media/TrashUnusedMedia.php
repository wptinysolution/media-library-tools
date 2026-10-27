<?php
/**
 * Ability: tsmlt/trash-unused-media.
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
use TinySolutions\mlt\Abilities\TrashEligibility;
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;

/**
 * Move up to 50 exact attachment IDs to the trash, only those the current,
 * completed usage scan found unused (TrashEligibility). Uses wp_trash_post()
 * only: files and attachment records stay, and media can be restored from
 * the trash (Media Library Tools > Used Where). Never deletes permanently:
 * the request is refused when the site's trash is turned off.
 *
 * Everything is checked here, at execution, whatever a client or preview
 * said: the scan gates and every ID; the scan is read again after the
 * checks and before each attachment is moved, and when it is no longer the
 * same completed scan nothing more is moved. Each result is read back: an
 * attachment counts as trashed only when it exists with the trash status.
 *
 * Registered with `meta.mcp.public` false: offered only on the dedicated
 * Media Library Tools server.
 */
class TrashUnusedMedia extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Per-attachment results.
	 *
	 * @var string[]
	 */
	const RESULTS = [ 'trashed', 'skipped', 'failed' ];

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/trash-unused-media';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Move unused media to the trash', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Move up to 50 exact attachment IDs to the WordPress trash, only those the latest completed usage scan found unused; every other ID is skipped with a reason. Requires a completed usage scan (tsmlt/start-usage-scan, then poll tsmlt/get-usage-scan-status until complete) and checks every ID again itself. Preview with tsmlt/preview-trash-unused-media and confirm the IDs with the user first. Files and attachment records are kept and can be restored from the trash; nothing is deleted permanently. Destructive.', 'media-library-tools' );
	}

	/**
	 * Not offered on the MCP Adapter's default gateway.
	 *
	 * @return bool
	 */
	protected function is_mcp_public(): bool {
		return false;
	}

	/**
	 * @return array
	 */
	protected function get_input_schema(): array {
		return TrashEligibility::input_schema();
	}

	/**
	 * @return array
	 */
	protected function get_output_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'scan', 'trashed', 'results' ],
			'properties'           => [
				'scan'    => TrashEligibility::scan_block_schema(),
				'trashed' => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'IDs moved to the trash by this request.',
				],
				'results' => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'result', 'reason', 'message' ],
						'properties'           => [
							'id'      => [ 'type' => 'integer' ],
							'result'  => [
								'type'        => 'string',
								'enum'        => self::RESULTS,
								'description' => 'trashed; skipped (not eligible, unchanged); failed (eligible but not moved).',
							],
							'reason'  => [
								'type'        => [ 'string', 'null' ],
								'enum'        => array_merge( TrashEligibility::REASONS, [ 'scan_changed', 'trash_failed', null ] ),
								'description' => 'Why it was not moved; null when trashed.',
							],
							'message' => [ 'type' => 'string' ],
						],
					],
				],
			],
		];
	}

	/**
	 * @return array<string, bool>
	 */
	protected function get_annotations(): array {
		return [
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => true,
		];
	}

	/**
	 * Move the eligible attachments to the trash.
	 *
	 * @param mixed $input Ability input, already validated against the schema.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		// The callback can be reached without the permission callback.
		$base = AbilityGuard::check_base_capability();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$ids = TrashEligibility::validate_input( $input );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}

		$status = UsageScanStatus::read();
		$gate   = TrashEligibility::check_request( $status );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		// Check every ID first.
		$reasons = [];
		foreach ( $ids as $id ) {
			$reasons[ $id ] = TrashEligibility::check_id( $id, $status );
		}
		// The scan must still be the same completed one, with nothing pending.
		if ( ! TrashEligibility::same_scan( $status, UsageScanStatus::read() ) || TrashEligibility::rescans_pending() ) {
			return TrashEligibility::scan_changed();
		}

		$results = [];
		$trashed = [];
		$stopped = false;
		foreach ( $ids as $id ) {
			if ( null !== $reasons[ $id ] ) {
				$results[] = $this->row( $id, 'skipped', $reasons[ $id ] );
				continue;
			}
			// Right before each move: same scan, and the attachment still eligible.
			if ( ! $stopped && ( ! TrashEligibility::same_scan( $status, UsageScanStatus::read() ) || TrashEligibility::rescans_pending() ) ) {
				$stopped = true;
			}
			if ( $stopped ) {
				$results[] = $this->row( $id, 'failed', 'scan_changed' );
				continue;
			}
			$reason = TrashEligibility::check_id( $id, $status );
			if ( null !== $reason ) {
				$results[] = $this->row( $id, 'skipped', $reason );
				continue;
			}

			wp_trash_post( $id );
			clean_post_cache( $id );
			$after = get_post( $id );
			if ( $after instanceof WP_Post && 'trash' === $after->post_status ) {
				$trashed[] = $id;
				$results[] = $this->row( $id, 'trashed', null );
			} else {
				$results[] = $this->row( $id, 'failed', 'trash_failed' );
			}
		}

		return [
			'scan'    => TrashEligibility::scan_block( $status ),
			'trashed' => $trashed,
			'results' => $results,
		];
	}

	/**
	 * One result row.
	 *
	 * @param int         $id     Attachment ID.
	 * @param string      $result One of RESULTS.
	 * @param string|null $reason Reason, or null when trashed.
	 *
	 * @return array
	 */
	private function row( int $id, string $result, ?string $reason ): array {
		return [
			'id'      => $id,
			'result'  => $result,
			'reason'  => $reason,
			'message' => null === $reason ? __( 'Moved to the trash.', 'media-library-tools' ) : TrashEligibility::message( $reason ),
		];
	}
}
