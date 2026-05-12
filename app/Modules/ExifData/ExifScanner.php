<?php
/**
 * EXIF Data availability scanner — analyzes EXIF presence in media library.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\ExifData;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * ExifScanner — scans media library for EXIF data availability.
 */
class ExifScanner {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * WordPress option key for scan status.
	 */
	const SCAN_STATUS_KEY = 'tsmlt_exif_scan_status';

	/**
	 * Single-event hook fired between batches by WP-Cron. Each tick
	 * processes one batch and self-reschedules until the run completes
	 * or the user cancels.
	 */
	const TICK_HOOK = 'tsmlt_exif_scan_tick';

	/**
	 * Seconds between background ticks. Kept short so WP-Cron picks up
	 * the next batch on the next visitor request.
	 */
	const TICK_INTERVAL = 1;

	/**
	 * Per-tick batch size. EXIF header parsing is fast (1–10ms per JPEG),
	 * so we can process many images per tick without timeout risk.
	 */
	const TICK_BATCH_SIZE = 100;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Scan a batch of attachments for EXIF data availability.
	 *
	 * @param int $offset     Offset to start scanning from.
	 * @param int $batch_size Number of attachments to scan per batch.
	 *
	 * @return array{processed: int, total: int, complete: bool, with_exif: int, without_exif: int}
	 */
	public function scan_batch( int $offset = 0, int $batch_size = 50 ): array {
		// Get total attachment count.
		$total_result = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->get();
		$total        = (int) ( $total_result[0]['total'] ?? 0 );

		// Get current scan status (accumulated counts from previous batches).
		$status             = get_option( self::SCAN_STATUS_KEY, [] );
		$with_exif_count    = isset( $status['with_exif'] ) ? (int) $status['with_exif'] : 0;
		$without_exif_count = isset( $status['without_exif'] ) ? (int) $status['without_exif'] : 0;

		// Get batch of attachment IDs.
		$batch = Fns::DB()->select( 'ID', 'post_mime_type' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->orderBy( 'ID', 'ASC' )
			->limit( $batch_size )
			->offset( $offset )
			->get();

		if ( empty( $batch ) ) {
			// No more items — return accumulated counts from saved status.
			return [
				'processed'    => $offset,
				'total'        => $total,
				'complete'     => true,
				'with_exif'    => $with_exif_count,
				'without_exif' => $without_exif_count,
			];
		}

		// List of MIME types that support EXIF.
		$supported_mimes = [ 'image/jpeg', 'image/jpg', 'image/tiff', 'image/webp' ];

		// Scan each attachment in the batch.
		foreach ( $batch as $row ) {
			$attachment_id = (int) $row['ID'];
			$mime          = $row['post_mime_type'];

			// Check if MIME type supports EXIF.
			if ( ! in_array( $mime, $supported_mimes, true ) ) {
				$without_exif_count++;
				continue;
			}

			// Get file path.
			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$without_exif_count++;
				continue;
			}

			// Check if PHP EXIF extension is available.
			if ( ! function_exists( 'exif_read_data' ) ) {
				// Can't determine, count as without (safe assumption).
				$without_exif_count++;
				continue;
			}

			// Try to read EXIF data.
			$exif = @exif_read_data( $file_path, null, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $exif ) && ! empty( $exif ) ) {
				$with_exif_count++;
			} else {
				$without_exif_count++;
			}
		}

		// Update scan status in options.
		$processed = $offset + count( $batch );
		$status    = [
			'processed'    => $processed,
			'total'        => $total,
			'with_exif'    => $with_exif_count,
			'without_exif' => $without_exif_count,
			'timestamp'    => current_time( 'mysql' ),
		];
		update_option( self::SCAN_STATUS_KEY, $status );

		return [
			'processed'    => $processed,
			'total'        => $total,
			'complete'     => $processed >= $total,
			'with_exif'    => $with_exif_count,
			'without_exif' => $without_exif_count,
		];
	}

	/**
	 * Get current scan status.
	 *
	 * @return array{processed: int, total: int, with_exif: int, without_exif: int, timestamp: string}
	 */
	public function get_scan_status(): array {
		$status = get_option( self::SCAN_STATUS_KEY, [] );

		// Get total attachment count if no scan started yet.
		if ( empty( $status['total'] ) ) {
			$total_result = Fns::DB()->select()
				->count( '*', 'total' )
				->from( 'posts' )
				->where( 'post_type', '=', 'attachment' )
				->andWhere( 'post_status', '=', 'inherit' )
				->get();
			$status['total'] = (int) ( $total_result[0]['total'] ?? 0 );
		}

		return [
			'processed'    => isset( $status['processed'] ) ? (int) $status['processed'] : 0,
			'total'        => isset( $status['total'] ) ? (int) $status['total'] : 0,
			'with_exif'    => isset( $status['with_exif'] ) ? (int) $status['with_exif'] : 0,
			'without_exif' => isset( $status['without_exif'] ) ? (int) $status['without_exif'] : 0,
			'timestamp'    => isset( $status['timestamp'] ) ? $status['timestamp'] : '',
		];
	}

	/**
	 * Clear all scan results.
	 *
	 * @return array{updated: bool, message: string}
	 */
	public function clear_scan(): array {
		// Make sure a background tick isn't left scheduled after a clear.
		wp_clear_scheduled_hook( self::TICK_HOOK );

		delete_option( self::SCAN_STATUS_KEY );

		// Reset the static cache in ExifDataReader.
		ExifDataReader::clear_cache();

		return [
			'updated' => true,
			'message' => esc_html__( 'EXIF scan results cleared.', 'media-library-tools' ),
		];
	}

	// -------------------------------------------------------------------------
	// Background scan (self-chaining single events)
	// -------------------------------------------------------------------------

	/**
	 * Default shape for the persisted state row. Keeps the legacy keys
	 * (`processed`, `total`, `with_exif`, `without_exif`, `timestamp`) so
	 * existing callers of `get_scan_status` keep working, and adds a
	 * `status` field for the start/cancel/done lifecycle.
	 *
	 * @return array
	 */
	private function default_state(): array {
		return [
			'status'       => 'idle',
			'processed'    => 0,
			'total'        => 0,
			'with_exif'    => 0,
			'without_exif' => 0,
			'started_at'   => 0,
			'updated_at'   => 0,
			'timestamp'    => '',
		];
	}

	/**
	 * Read the persisted scan state, merged with defaults so callers always
	 * see the full shape even when older runs left a partial option payload.
	 *
	 * @return array
	 */
	public function get_progress(): array {
		$stored = get_option( self::SCAN_STATUS_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		$state = array_merge( $this->default_state(), $stored );

		// If the option came from a legacy (pre-tick) scan, it won't have a
		// status field but it will have `processed/total`. Infer status so
		// the UI can still resume / display correctly.
		if ( empty( $stored['status'] ) ) {
			$state['status'] = ( $state['processed'] > 0 && $state['processed'] >= $state['total'] ) ? 'done' : 'idle';
		}

		$state['tick_scheduled'] = (bool) wp_next_scheduled( self::TICK_HOOK );

		return $state;
	}

	/**
	 * Start a fresh background scan. Wipes accumulated counts, snapshots the
	 * current total, marks the run as running, and schedules the first tick.
	 *
	 * @return array Progress after start.
	 */
	public function start(): array {
		wp_clear_scheduled_hook( self::TICK_HOOK );

		$total_result = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->get();
		$total        = (int) ( $total_result[0]['total'] ?? 0 );

		$now   = time();
		$state = array_merge(
			$this->default_state(),
			[
				'status'     => $total > 0 ? 'running' : 'done',
				'total'      => $total,
				'started_at' => $now,
				'updated_at' => $now,
				'timestamp'  => current_time( 'mysql' ),
			]
		);
		update_option( self::SCAN_STATUS_KEY, $state, false );

		if ( $total > 0 ) {
			// First tick fires almost immediately — no benefit to idling on Start.
			wp_schedule_single_event( $now + 1, self::TICK_HOOK );
		}

		return $this->get_progress();
	}

	/**
	 * Cancel an in-flight scan. Marks the run as cancelled and unschedules
	 * the pending tick. Accumulated counts stay visible until the next start.
	 *
	 * @return array Progress after cancel.
	 */
	public function cancel(): array {
		wp_clear_scheduled_hook( self::TICK_HOOK );

		$state               = $this->get_progress();
		$state['status']     = 'cancelled';
		$state['updated_at'] = time();
		unset( $state['tick_scheduled'] );
		update_option( self::SCAN_STATUS_KEY, $state, false );

		return $this->get_progress();
	}

	/**
	 * Background tick handler. Processes one batch, updates the persisted
	 * state, then self-reschedules unless the scan finished or the user
	 * cancelled mid-batch.
	 *
	 * Registered against `self::TICK_HOOK` in `CronJobHooks`.
	 *
	 * @return void
	 */
	public static function run_tick(): void {
		$self  = self::instance();
		$state = $self->get_progress();

		// Bail if cancelled or already finished — no rescheduling.
		if ( 'running' !== ( $state['status'] ?? '' ) ) {
			return;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$batch_size = (int) apply_filters( 'tsmlt_exif_scan_tick_batch_size', self::TICK_BATCH_SIZE );
		$batch_size = max( 1, min( 500, $batch_size ) );

		// `scan_batch()` already updates the option row with new counters.
		// We re-read after it to layer our status/lifecycle fields on top
		// without dropping anything it just wrote.
		$result = $self->scan_batch( (int) $state['processed'], $batch_size );

		$persisted = get_option( self::SCAN_STATUS_KEY, [] );
		if ( ! is_array( $persisted ) ) {
			$persisted = [];
		}

		$persisted['status']     = $result['complete'] ? 'done' : ( $persisted['status'] ?? 'running' );
		$persisted['updated_at'] = time();
		$persisted['timestamp']  = current_time( 'mysql' );

		update_option( self::SCAN_STATUS_KEY, $persisted, false );

		// Re-read status — it may have flipped to cancelled while we ran.
		$fresh = get_option( self::SCAN_STATUS_KEY, [] );
		if ( is_array( $fresh ) && 'running' === ( $fresh['status'] ?? '' ) ) {
			wp_schedule_single_event( time() + self::TICK_INTERVAL, self::TICK_HOOK );
		}
	}
}
