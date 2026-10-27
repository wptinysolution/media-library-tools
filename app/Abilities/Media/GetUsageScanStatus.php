<?php
/**
 * Ability: tsmlt/get-usage-scan-status.
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
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Status of the Used Where scan, read-only: it never starts a scan and never
 * re-schedules a stalled one (it reads UsageScanStatus, not
 * UsedWhereScanner::get_scan_status()). A stalled scan is reported as
 * `stalled`; tsmlt/start-usage-scan resumes it. The scanner's error text is
 * not returned, only `has_error`.
 *
 * Registered with `meta.mcp.public` false: offered only on the dedicated
 * Media Library Tools server, with the scan control.
 */
class GetUsageScanStatus extends Ability {

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
		return 'tsmlt/get-usage-scan-status';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get usage scan status', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Return the status of the Used Where scan that records where media is used: state (idle = never started or cleared, queued, running, complete, cancelled, error), complete, processed and total posts, start and last-update times, whether it is stalled (no progress for over 3 minutes; call tsmlt/start-usage-scan to resume it) and whether it failed. Poll this after tsmlt/start-usage-scan until the state is complete, cancelled or error; only after complete can tsmlt/get-media-usage report media as not used. Read-only; never starts or resumes a scan.', 'media-library-tools' );
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
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			// Lets clients call the ability with no arguments at all.
			'default'              => [],
			'properties'           => [],
		];
	}

	/**
	 * @return array
	 */
	protected function get_output_schema(): array {
		return UsageScanStatus::schema();
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
	 * Return the status.
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

		if ( null !== $input && ( ! is_array( $input ) || ! empty( $input ) ) ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				__( 'This ability takes no input.', 'media-library-tools' ),
				400
			);
		}

		return UsageScanStatus::read();
	}
}
