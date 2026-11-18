<?php
/**
 * Ability: tsmlt/start-exif-privacy-scan.
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
use TinySolutions\mlt\Modules\ExifData\ExifScanner;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Start the EXIF scan, the same one as the EXIF Data page's Start
 * (ExifScanner, WP-Cron batches), which also records each image's privacy
 * result, or report it:
 * - A running scan is not restarted (already_running).
 * - A stalled scan (running, no progress for 3 minutes) is resumed from
 *   where it stopped (resumed).
 * - Otherwise a new scan run starts (started); results of earlier runs are
 *   no longer listed.
 * Starts take the scanner's start lock, shared with the EXIF Data page, so
 * a start here and one there cannot interleave. Media files and EXIF are
 * never changed; no paths or EXIF values are returned.
 *
 * Registered with `meta.mcp.public` false: offered only on the dedicated
 * Media Library Tools server.
 */
class StartExifPrivacyScan extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Possible results.
	 *
	 * @var string[]
	 */
	const RESULTS = [ 'started', 'already_running', 'resumed' ];

	/**
	 * Error code: the scan could not be started.
	 *
	 * @var string
	 */
	const ERROR_START_FAILED = 'tsmlt_exif_scan_start_failed';

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/start-exif-privacy-scan';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Start EXIF privacy scan', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Start the EXIF privacy scan, which reads the EXIF of every media file and records, per image, whether it holds location (GPS), camera, date taken, author or copyright data (presence only). It runs in the background in batches (WP-Cron); this call only starts it and returns the status. If a scan is already running, nothing restarts and result is already_running; a stalled scan is resumed (result resumed). Then poll tsmlt/get-exif-privacy-scan-status every few seconds until complete is true, and list the findings with tsmlt/list-exif-privacy-results. Media files and their EXIF are never changed.', 'media-library-tools' );
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
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'result', 'scan' ],
			'properties'           => [
				'result' => [
					'type'        => 'string',
					'enum'        => self::RESULTS,
					'description' => 'started: a new scan run was started. already_running: a scan was already running (or being started); nothing changed. resumed: a stalled scan was set to continue.',
				],
				'scan'   => GetExifPrivacyScanStatus::schema(),
			],
		];
	}

	/**
	 * Starts background work but changes no media.
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
	 * Start, resume or report the scan.
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

		try {
			$outcome = ExifScanner::instance()->start_or_resume();
		} catch ( \Throwable $e ) {
			$outcome = null;
		}
		if ( null === $outcome ) {
			return AbilityGuard::error(
				self::ERROR_START_FAILED,
				__( 'The EXIF scan could not be started. Try again, or start it on the EXIF Data page of Media Library Tools.', 'media-library-tools' ),
				500
			);
		}
		if ( 'started' === $outcome['result'] && 'running' === $outcome['progress']['status'] && function_exists( 'spawn_cron' ) ) {
			// Ask WP-Cron to run now, as the admin page's Start does on its next request.
			spawn_cron();
		}

		return [
			'result' => $outcome['result'],
			'scan'   => GetExifPrivacyScanStatus::present( $outcome['progress'] ),
		];
	}
}
