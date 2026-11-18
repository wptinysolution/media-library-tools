<?php
/**
 * Ability: tsmlt/get-exif-privacy-scan-status.
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
 * Status of the EXIF scan (the EXIF Data page's scan), read-only: it reads
 * the stored state (ExifScanner::get_progress()) and never starts, resumes
 * or schedules anything. A stalled scan is reported as `stalled`;
 * tsmlt/start-exif-privacy-scan resumes it.
 *
 * Registered with `meta.mcp.public` false: offered only on the dedicated
 * Media Library Tools server.
 */
class GetExifPrivacyScanStatus extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Scan states reported.
	 *
	 * @var string[]
	 */
	const STATES = [ 'idle', 'running', 'complete', 'cancelled' ];

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/get-exif-privacy-scan-status';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get EXIF privacy scan status', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Return the status of the EXIF privacy scan, which reads the EXIF of every media file to find location (GPS), camera, date taken, author and copyright data: state (idle = never started or cleared, running, complete, cancelled), complete, stalled (no progress for over 3 minutes; call tsmlt/start-exif-privacy-scan to resume it), processed and total media, percent, start and last-update times, and has_privacy_results (false when the last scan ran before privacy results were recorded; start a new scan). Poll this every few seconds after tsmlt/start-exif-privacy-scan until complete is true, then list the findings with tsmlt/list-exif-privacy-results. Read-only; never starts or resumes a scan.', 'media-library-tools' );
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
		return self::schema();
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

		return self::present( ExifScanner::instance()->get_progress() );
	}

	/**
	 * A scan state (ExifScanner::get_progress()) as the tools report it.
	 *
	 * @param array $state Stored scan state.
	 *
	 * @return array
	 */
	public static function present( array $state ): array {
		$stored    = (string) ( $state['status'] ?? 'idle' );
		$status    = 'done' === $stored ? 'complete' : $stored;
		$status    = in_array( $status, self::STATES, true ) ? $status : 'idle';
		$processed = max( 0, (int) ( $state['processed'] ?? 0 ) );
		$total     = max( 0, (int) ( $state['total'] ?? 0 ) );
		if ( 'complete' === $status ) {
			$percent = 100;
		} elseif ( $total > 0 ) {
			$percent = (int) min( 99, floor( 100 * $processed / $total ) );
		} else {
			$percent = 0;
		}

		return [
			'state'               => $status,
			'complete'            => 'complete' === $status,
			'stalled'             => ExifScanner::is_stalled( $state ),
			'processed'           => $processed,
			'total'               => $total,
			'percent'             => $percent,
			'started_at'          => self::time_value( $state['started_at'] ?? 0 ),
			'last_update'         => self::time_value( $state['updated_at'] ?? 0 ),
			'has_privacy_results' => '' !== self::run( $state ),
		];
	}

	/**
	 * The scan run ID of a state ('' when the scan predates privacy results).
	 *
	 * @param array $state Stored scan state.
	 *
	 * @return string
	 */
	public static function run( array $state ): string {
		$run = $state['run'] ?? '';
		return is_string( $run ) && preg_match( '/^[0-9a-f]{16}$/', $run ) ? $run : '';
	}

	/**
	 * Output schema of present().
	 *
	 * @return array
	 */
	public static function schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'state', 'complete', 'stalled', 'processed', 'total', 'percent', 'started_at', 'last_update', 'has_privacy_results' ],
			'properties'           => [
				'state'               => [
					'type'        => 'string',
					'enum'        => self::STATES,
					'description' => 'idle: never started or cleared. running: in progress. complete: finished; findings are in tsmlt/list-exif-privacy-results. cancelled: stopped in WordPress Admin.',
				],
				'complete'            => [ 'type' => 'boolean' ],
				'stalled'             => [
					'type'        => 'boolean',
					'description' => 'Running with no progress for over 3 minutes (WP-Cron is not running the next batch); tsmlt/start-exif-privacy-scan resumes it.',
				],
				'processed'           => [
					'type'        => 'integer',
					'description' => 'Media checked so far.',
				],
				'total'               => [
					'type'        => 'integer',
					'description' => 'Media to check, counted when the scan started.',
				],
				'percent'             => [
					'type'    => 'integer',
					'minimum' => 0,
					'maximum' => 100,
				],
				'started_at'          => [
					'type'        => 'string',
					'description' => 'When the scan started, in site time (Y-m-d H:i:s), or empty.',
				],
				'last_update'         => [
					'type'        => 'string',
					'description' => 'When the scan last made progress, in site time (Y-m-d H:i:s), or empty.',
				],
				'has_privacy_results' => [
					'type'        => 'boolean',
					'description' => 'Whether this scan records per-image privacy results. False for a scan started before privacy results existed; start a new scan.',
				],
			],
		];
	}

	/**
	 * A Unix time in site time (Y-m-d H:i:s), or '' when unknown.
	 *
	 * @param mixed $timestamp Unix time.
	 *
	 * @return string
	 */
	private static function time_value( $timestamp ): string {
		$timestamp = (int) $timestamp;
		return $timestamp > 0 ? (string) wp_date( 'Y-m-d H:i:s', $timestamp ) : '';
	}
}
