<?php
/**
 * Ability: tsmlt/start-usage-scan.
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
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Start the Used Where scan in the background, the way the Used Where page
 * does (UsedWhereScanner::start_scheduled_scan(), then spawn_cron()). The
 * scan itself runs in WP-Cron batches; this request only queues it.
 *
 * - A scan already queued or running is left alone: its status is returned
 *   as "already_running". (The admin page's button restarts instead.)
 * - A stalled scan (queued or running, no batch for over 3 minutes) is
 *   resumed from where it stopped with the scanner's own recovery, then
 *   reported as "resumed"; the next batch is scheduled only if none is.
 * - Otherwise (never run, complete, cancelled, error) a new scan starts:
 *   the scanner replaces the previous usage records as it goes.
 *
 * Two requests at once cannot both start a scan: a short lock taken with
 * add_option() (an atomic insert) lets one through; the other reports
 * "already_running".
 *
 * Registered with `meta.mcp.public` false: offered only on the dedicated
 * Media Library Tools server.
 */
class StartUsageScan extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Option used as the start lock.
	 *
	 * @var string
	 */
	const START_LOCK_OPTION = 'tsmlt_used_where_scan_start_lock';

	/**
	 * Seconds after which a start lock left behind by a failed request is
	 * ignored.
	 *
	 * @var int
	 */
	const START_LOCK_SECONDS = 60;

	/**
	 * Results.
	 *
	 * @var string[]
	 */
	const RESULTS = [ 'started', 'already_running', 'resumed' ];

	/**
	 * Error code: the scan could not be started.
	 *
	 * @var string
	 */
	const ERROR_START_FAILED = 'tsmlt_scan_start_failed';

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/start-usage-scan';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Start usage scan', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Start the Used Where scan, which records where each media item is used (featured images, content, page builders, custom fields, logo, menus, widgets, options). It runs in the background in small batches; this call only queues it. If a scan is already queued or running, nothing new starts and result is already_running; a stalled scan is resumed (result resumed). A new scan replaces the previous results as it runs, so until it completes tsmlt/get-media-usage reports items without a recorded usage as unknown. Poll tsmlt/get-usage-scan-status until the state is complete, cancelled or error. Media files and attachment data are never changed.', 'media-library-tools' );
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
					'description' => 'started: a new scan was queued. already_running: a scan was already queued or running; nothing changed. resumed: a stalled scan was set to continue.',
				],
				'scan'   => UsageScanStatus::schema(),
			],
		];
	}

	/**
	 * Replaces the previous scan results, so clients should confirm first.
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
	 * Start (or report) the scan.
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

		// Another request is starting a scan right now.
		if ( ! $this->acquire_lock() ) {
			return $this->result( 'already_running' );
		}

		try {
			$status = UsageScanStatus::read();
			if ( in_array( $status['state'], UsageScanStatus::ACTIVE_STATES, true ) ) {
				if ( ! $status['stalled'] ) {
					return $this->result( 'already_running' );
				}
				$this->resume();
				return $this->result( 'resumed' );
			}

			UsedWhereScanner::instance()->start_scheduled_scan();
			$this->spawn_cron();
			return $this->result( 'started' );
		} catch ( \Throwable $e ) {
			return AbilityGuard::error(
				self::ERROR_START_FAILED,
				__( 'The usage scan could not be started. Try again, or start it under Media Library Tools > Used Where.', 'media-library-tools' ),
				500
			);
		} finally {
			delete_option( self::START_LOCK_OPTION );
		}
	}

	/**
	 * Take the start lock. add_option() inserts only when the option does not
	 * exist, so only one request gets it; a lock older than
	 * START_LOCK_SECONDS is treated as abandoned.
	 *
	 * @return bool
	 */
	private function acquire_lock(): bool {
		if ( add_option( self::START_LOCK_OPTION, time(), '', false ) ) {
			return true;
		}
		$taken = (int) get_option( self::START_LOCK_OPTION, 0 );
		if ( $taken > 0 && ( time() - $taken ) <= self::START_LOCK_SECONDS ) {
			return false;
		}
		delete_option( self::START_LOCK_OPTION );
		return add_option( self::START_LOCK_OPTION, time(), '', false );
	}

	/**
	 * Resume a stalled scan: the scanner's own recovery (which re-schedules
	 * the next batch when batches had run), and the next batch scheduled if
	 * there still is none (a scan whose first batch never ran).
	 *
	 * @return void
	 */
	private function resume(): void {
		UsedWhereScanner::instance()->get_scan_status();

		if ( ! $this->tick_scheduled() ) {
			$status = get_option( UsageScanStatus::OPTION, [] );
			$offset = is_array( $status ) ? absint( $status['next_offset'] ?? 0 ) : 0;
			wp_schedule_single_event( time() + 1, UsedWhereScanner::SCAN_TICK_HOOK, [ $offset ] );
		}
		$this->spawn_cron();
	}

	/**
	 * Whether a scan batch is scheduled.
	 *
	 * @return bool
	 */
	private function tick_scheduled(): bool {
		foreach ( (array) _get_cron_array() as $hooks ) {
			if ( is_array( $hooks ) && ! empty( $hooks[ UsedWhereScanner::SCAN_TICK_HOOK ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ask WP-Cron to run now, as the Used Where page does after starting.
	 *
	 * @return void
	 */
	private function spawn_cron(): void {
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Result with the current status.
	 *
	 * @param string $result One of RESULTS.
	 *
	 * @return array
	 */
	private function result( string $result ): array {
		return [
			'result' => $result,
			'scan'   => UsageScanStatus::read(),
		];
	}
}
