<?php
/**
 * Ability: tsmlt/preview-trash-unused-media.
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

/**
 * Read-only dry run of tsmlt/trash-unused-media: the same checks
 * (TrashEligibility) for the exact IDs given, and which of them would be
 * moved to the trash now. Nothing is changed or scheduled. The result is a
 * snapshot: tsmlt/trash-unused-media checks everything again itself.
 *
 * Registered with `meta.mcp.public` false: offered only on the dedicated
 * Media Library Tools server.
 */
class PreviewTrashUnusedMedia extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/preview-trash-unused-media';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Preview moving unused media to the trash', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Dry run for tsmlt/trash-unused-media: for up to 50 exact attachment IDs, report which would be moved to the trash now and why the others would not (used, uploaded after the scan, already in the trash, not found, not allowed). Requires a completed usage scan (tsmlt/start-usage-scan, then tsmlt/get-usage-scan-status until complete). Read-only: nothing is changed. Show the eligible IDs to the user before calling tsmlt/trash-unused-media with them.', 'media-library-tools' );
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
			'required'             => [ 'scan', 'eligible', 'results' ],
			'properties'           => [
				'scan'     => TrashEligibility::scan_block_schema(),
				'eligible' => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'IDs that would be moved to the trash now.',
				],
				'results'  => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'eligible', 'reason', 'message' ],
						'properties'           => [
							'id'       => [ 'type' => 'integer' ],
							'eligible' => [ 'type' => 'boolean' ],
							'reason'   => [
								'type'        => [ 'string', 'null' ],
								'enum'        => array_merge( TrashEligibility::REASONS, [ null ] ),
								'description' => 'Why it would not be moved; null when eligible.',
							],
							'message'  => [ 'type' => 'string' ],
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
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		];
	}

	/**
	 * Check the IDs.
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

		$results  = [];
		$eligible = [];
		foreach ( $ids as $id ) {
			$reason    = TrashEligibility::check_id( $id, $status );
			$results[] = [
				'id'       => $id,
				'eligible' => null === $reason,
				'reason'   => $reason,
				'message'  => null === $reason ? __( 'Would be moved to the trash.', 'media-library-tools' ) : TrashEligibility::message( $reason ),
			];
			if ( null === $reason ) {
				$eligible[] = $id;
			}
		}

		if ( ! TrashEligibility::same_scan( $status, UsageScanStatus::read() ) ) {
			return TrashEligibility::scan_changed();
		}

		return [
			'scan'     => TrashEligibility::scan_block( $status ),
			'eligible' => $eligible,
			'results'  => $results,
		];
	}
}
