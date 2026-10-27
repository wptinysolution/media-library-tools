<?php
/**
 * Read-only view of the Used Where scan status for the abilities.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * The Used Where scan status, read from its option with no side effects.
 *
 * UsedWhereScanner::get_scan_status() also heals a stalled scan (it
 * re-schedules the next batch), so the read-only abilities use this instead.
 * The state is derived the same way, including rows written before the
 * scanner recorded a `state`. The scanner's `last_error` text is never
 * returned (it can hold server details); only whether there is one.
 */
class UsageScanStatus {

	/**
	 * Option holding the scan status.
	 *
	 * @var string
	 */
	const OPTION = 'tsmlt_used_where_scan_status';

	/**
	 * States the scanner records.
	 *
	 * @var string[]
	 */
	const STATES = [ 'idle', 'queued', 'running', 'complete', 'cancelled', 'error' ];

	/**
	 * States in which a scan is in progress.
	 *
	 * @var string[]
	 */
	const ACTIVE_STATES = [ 'queued', 'running' ];

	/**
	 * Seconds without a batch after which an active scan counts as stalled.
	 * Same threshold as UsedWhereScanner::get_scan_status() uses to recover.
	 *
	 * @var int
	 */
	const STALL_SECONDS = 180;

	/**
	 * The status.
	 *
	 * @return array{scan_id: string, state: string, complete: bool, processed: int, total: int, percent: int, last_update: string, started_at: string, last_tick_at: string, ever_started: bool, stalled: bool, has_error: bool}
	 */
	public static function read(): array {
		$status = get_option( self::OPTION, [] );
		$status = is_array( $status ) ? $status : [];

		$state = isset( $status['state'] ) ? (string) $status['state'] : ( empty( $status['complete'] ) ? 'idle' : 'complete' );
		if ( ! in_array( $state, self::STATES, true ) ) {
			$state = 'idle';
		}

		$last_tick_at = self::time_value( $status['last_tick_at'] ?? '' );
		$started_at   = self::time_value( $status['started_at'] ?? '' );
		$processed    = max( 0, (int) ( $status['processed'] ?? 0 ) );
		$total        = max( 0, (int) ( $status['total'] ?? 0 ) );
		$scan_id      = isset( $status['scan_id'] ) && is_string( $status['scan_id'] ) && preg_match( '/^[a-f0-9-]{36}$/', $status['scan_id'] ) ? $status['scan_id'] : '';

		return [
			'scan_id'      => $scan_id,
			'state'        => $state,
			'complete'     => 'complete' === $state,
			'processed'    => $processed,
			'total'        => $total,
			'percent'      => self::percent( $state, $processed, $total ),
			'last_update'  => self::time_value( $status['timestamp'] ?? '' ),
			'started_at'   => $started_at,
			'last_tick_at' => $last_tick_at,
			'ever_started' => ! empty( $status ),
			'stalled'      => in_array( $state, self::ACTIVE_STATES, true ) && self::is_stale( '' !== $last_tick_at ? $last_tick_at : $started_at ),
			'has_error'    => 'error' === $state,
		];
	}

	/**
	 * Progress as a whole percentage: 100 only when complete, at most 99
	 * before, 0 when the total is not known yet.
	 *
	 * @param string $state     State.
	 * @param int    $processed Processed.
	 * @param int    $total     Total.
	 *
	 * @return int
	 */
	private static function percent( string $state, int $processed, int $total ): int {
		if ( 'complete' === $state ) {
			return 100;
		}
		if ( $total < 1 ) {
			return 0;
		}
		return (int) min( 99, floor( min( $processed, $total ) * 100 / $total ) );
	}

	/**
	 * Output schema of read().
	 *
	 * @return array
	 */
	public static function schema(): array {
		$time = static function ( string $description ): array {
			return [
				'type'        => 'string',
				'description' => $description . ' Site-local time (Y-m-d H:i:s), or empty.',
			];
		};
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'scan_id', 'state', 'complete', 'processed', 'total', 'percent', 'last_update', 'started_at', 'last_tick_at', 'ever_started', 'stalled', 'has_error' ],
			'properties'           => [
				'scan_id'      => [
					'type'        => 'string',
					'description' => 'Identifies this scan run; it changes whenever a new scan starts. Empty for a scan started by an older plugin version, or when none has run.',
				],
				'state'        => [
					'type'        => 'string',
					'enum'        => self::STATES,
					'description' => 'idle (never started, or cleared), queued, running, complete, cancelled, or error.',
				],
				'complete'     => [
					'type'        => 'boolean',
					'description' => 'Whether the last scan finished successfully.',
				],
				'processed'    => [
					'type'        => 'integer',
					'description' => 'Posts the scan has processed.',
				],
				'total'        => [
					'type'        => 'integer',
					'description' => 'Posts the scan has to process.',
				],
				'percent'      => [
					'type'        => 'integer',
					'minimum'     => 0,
					'maximum'     => 100,
					'description' => 'Progress, 0-100: processed / total, at most 99 until complete.',
				],
				'last_update'  => $time( 'When the status last changed.' ),
				'started_at'   => $time( 'When the last scan started.' ),
				'last_tick_at' => $time( 'When the last batch ran.' ),
				'ever_started' => [
					'type'        => 'boolean',
					'description' => 'false when no scan has run since the results were last cleared.',
				],
				'stalled'      => [
					'type'        => 'boolean',
					'description' => 'Queued or running, but no batch for over 3 minutes (WP-Cron not running). tsmlt/start-usage-scan resumes it.',
				],
				'has_error'    => [
					'type'        => 'boolean',
					'description' => 'The last scan stopped with an error. Details are shown under Media Library Tools > Used Where.',
				],
			],
		];
	}

	/**
	 * A site-local MySQL time (Y-m-d H:i:s), or '' for anything else.
	 *
	 * @param mixed $value Stored value.
	 *
	 * @return string
	 */
	private static function time_value( $value ): string {
		return is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ? $value : '';
	}

	/**
	 * Whether a site-local time is more than STALL_SECONDS ago (or unknown).
	 *
	 * @param string $local_time Site-local MySQL time, or ''.
	 *
	 * @return bool
	 */
	private static function is_stale( string $local_time ): bool {
		if ( '' === $local_time ) {
			return false;
		}
		$timestamp = (int) strtotime( get_gmt_from_date( $local_time ) . ' UTC' );
		return $timestamp > 0 && ( time() - $timestamp ) > self::STALL_SECONDS;
	}
}
