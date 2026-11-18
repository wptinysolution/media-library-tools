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
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;
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
	 * Per-image privacy result of the last scan run that reached the image:
	 * "{run}:{result}|{category}|...|", for example "3f9a…:sensitive|gps|camera|"
	 * or "3f9a…:no_exif|", so lists can be filtered in SQL. Presence only:
	 * no EXIF value is stored.
	 *
	 * @var string
	 */
	const PRIVACY_META_KEY = '_tsmlt_exif_privacy';

	/**
	 * Privacy results: sensitive categories found; EXIF without them; no
	 * EXIF; a format PHP cannot read EXIF from (WebP included); the file is
	 * missing; the file cannot be read; PHP has no EXIF extension.
	 *
	 * @var string[]
	 */
	const PRIVACY_RESULTS = [ 'sensitive', 'exif_no_sensitive', 'no_exif', 'unsupported', 'missing_file', 'unreadable', 'no_extension' ];

	/**
	 * MIME types whose EXIF PHP's exif_read_data() reads (JPEG and TIFF).
	 *
	 * @var string[]
	 */
	const PRIVACY_READABLE_MIMES = [ 'image/jpeg', 'image/jpg', 'image/tiff' ];

	/**
	 * Start lock option (ScanStartLock) shared by every way of starting an
	 * EXIF scan: the admin page's Start and tsmlt/start-exif-privacy-scan.
	 *
	 * @var string
	 */
	const START_LOCK_OPTION = 'tsmlt_exif_scan_start_lock';

	/**
	 * Seconds without progress after which a running scan counts as stalled.
	 *
	 * @var int
	 */
	const STALL_SECONDS = 180;

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

		// Privacy results are kept only for a scan run started with start().
		$run = is_array( $status ) && isset( $status['run'] ) && is_string( $status['run'] ) ? $status['run'] : '';

		// Scan each attachment in the batch.
		foreach ( $batch as $row ) {
			$attachment_id = (int) $row['ID'];
			$mime          = $row['post_mime_type'];

			// Check if MIME type supports EXIF.
			if ( ! in_array( $mime, $supported_mimes, true ) ) {
				$without_exif_count++;
				$this->record_privacy( $attachment_id, $run, 'unsupported' );
				continue;
			}

			// Get file path.
			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$without_exif_count++;
				$this->record_privacy( $attachment_id, $run, 'missing_file' );
				continue;
			}

			// Check if PHP EXIF extension is available.
			if ( ! function_exists( 'exif_read_data' ) ) {
				// Can't determine, count as without (safe assumption).
				$without_exif_count++;
				$this->record_privacy( $attachment_id, $run, 'no_extension' );
				continue;
			}

			// Try to read EXIF data.
			$exif = @exif_read_data( $file_path, null, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $exif ) && ! empty( $exif ) ) {
				$with_exif_count++;
			} else {
				$without_exif_count++;
			}
			$this->record_privacy( $attachment_id, $run, ...self::privacy_result( $mime, $file_path, $exif ) );
		}

		// Update scan status in options, keeping the run's lifecycle fields.
		$processed = $offset + count( $batch );
		$status    = array_merge(
			is_array( $status ) ? $status : [],
			[
				'processed'    => $processed,
				'total'        => $total,
				'with_exif'    => $with_exif_count,
				'without_exif' => $without_exif_count,
				'timestamp'    => current_time( 'mysql' ),
			]
		);
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
		delete_post_meta_by_key( self::PRIVACY_META_KEY );

		// Reset the static cache in ExifDataReader.
		ExifDataReader::clear_cache();

		return [
			'updated' => true,
			'message' => esc_html__( 'EXIF scan results cleared.', 'media-library-tools' ),
		];
	}

	/**
	 * The privacy result of an image whose file exists and whose MIME type
	 * the scanner reads: unsupported when PHP cannot read EXIF from that
	 * format (WebP); unreadable when the file cannot be read; no_exif;
	 * sensitive with its categories, or exif_no_sensitive.
	 *
	 * @param string      $mime      MIME type.
	 * @param string      $file_path File.
	 * @param array|false $exif      exif_read_data() result.
	 *
	 * @return array{0: string, 1: string[]}
	 */
	private static function privacy_result( string $mime, string $file_path, $exif ): array {
		if ( ! in_array( $mime, self::PRIVACY_READABLE_MIMES, true ) ) {
			return [ 'unsupported', [] ];
		}
		if ( ! is_readable( $file_path ) ) {
			return [ 'unreadable', [] ];
		}
		// exif_read_data() also returns its FILE and COMPUTED sections for a
		// file without EXIF; only the EXIF sections themselves count.
		if ( ! is_array( $exif ) || ( empty( $exif['IFD0'] ) && empty( $exif['EXIF'] ) && empty( $exif['GPS'] ) ) ) {
			return [ 'no_exif', [] ];
		}
		$categories = ExifDataReader::privacy_categories( $exif );
		return [ $categories ? 'sensitive' : 'exif_no_sensitive', $categories ];
	}

	/**
	 * Store an image's privacy result for a scan run (nothing without a
	 * run): presence flags only, never EXIF values.
	 *
	 * @param int      $attachment_id Attachment ID.
	 * @param string   $run           Scan run ID ('' for none).
	 * @param string   $result        One of PRIVACY_RESULTS.
	 * @param string[] $categories    Categories found.
	 *
	 * @return void
	 */
	private function record_privacy( int $attachment_id, string $run, string $result, array $categories = [] ): void {
		if ( '' === $run ) {
			return;
		}
		update_post_meta( $attachment_id, self::PRIVACY_META_KEY, self::privacy_value( $run, $result, $categories ) );
	}

	/**
	 * The stored PRIVACY_META_KEY value of a result.
	 *
	 * @param string   $run        Scan run ID.
	 * @param string   $result     One of PRIVACY_RESULTS.
	 * @param string[] $categories Categories found.
	 *
	 * @return string
	 */
	public static function privacy_value( string $run, string $result, array $categories = [] ): string {
		return $run . ':' . $result . '|' . ( $categories ? implode( '|', $categories ) . '|' : '' );
	}

	/**
	 * Parse a stored PRIVACY_META_KEY value for a scan run.
	 *
	 * @param mixed  $value Stored value.
	 * @param string $run   Scan run ID.
	 *
	 * @return array{result: string, categories: string[]}|null Null when the
	 *         value is not a valid result of that run.
	 */
	public static function parse_privacy( $value, string $run ): ?array {
		if ( '' === $run || ! is_string( $value ) || 0 !== strpos( $value, $run . ':' ) ) {
			return null;
		}
		$parts  = explode( '|', substr( $value, strlen( $run ) + 1 ) );
		$result = array_shift( $parts );
		if ( ! in_array( $result, self::PRIVACY_RESULTS, true ) ) {
			return null;
		}
		$categories = array_values( array_intersect( ExifDataReader::PRIVACY_CATEGORIES, $parts ) );
		if ( ( 'sensitive' === $result ) !== ( [] !== $categories ) ) {
			return null;
		}
		return [
			'result'     => $result,
			'categories' => $categories,
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
	 * Starts are taken one at a time (START_LOCK_OPTION): while another
	 * request is starting a scan, the current progress is returned and
	 * nothing is changed.
	 *
	 * @return array Progress after start.
	 */
	public function start(): array {
		$lock = ScanStartLock::instance()->acquire( self::START_LOCK_OPTION );
		if ( ! is_string( $lock ) ) {
			return $this->get_progress();
		}
		try {
			return $this->begin();
		} finally {
			ScanStartLock::instance()->release( $lock, self::START_LOCK_OPTION );
		}
	}

	/**
	 * Start a scan unless one is running: a running scan is left alone
	 * (already_running), a stalled one is resumed from where it stopped
	 * (resumed), otherwise a new scan run starts (started). Under the same
	 * start lock as start().
	 *
	 * @return array{result: string, progress: array}|null Null when the lock
	 *         could not be read or written.
	 */
	public function start_or_resume(): ?array {
		$lock = ScanStartLock::instance()->acquire( self::START_LOCK_OPTION );
		if ( false === $lock ) {
			return null;
		}
		if ( null === $lock ) {
			return [
				'result'   => 'already_running',
				'progress' => $this->get_progress(),
			];
		}
		try {
			$state = $this->get_progress();
			if ( 'running' === $state['status'] ) {
				if ( ! self::is_stalled( $state ) ) {
					$result = 'already_running';
				} else {
					$this->resume();
					$result = 'resumed';
				}
			} else {
				$this->begin();
				$result = 'started';
			}
			return [
				'result'   => $result,
				'progress' => $this->get_progress(),
			];
		} finally {
			ScanStartLock::instance()->release( $lock, self::START_LOCK_OPTION );
		}
	}

	/**
	 * Whether a scan state is running with no progress for STALL_SECONDS.
	 *
	 * @param array $state A get_progress() state.
	 *
	 * @return bool
	 */
	public static function is_stalled( array $state ): bool {
		return 'running' === ( $state['status'] ?? '' ) && time() - (int) ( $state['updated_at'] ?? 0 ) > self::STALL_SECONDS;
	}

	/**
	 * Resume a stalled scan from its stored offset: the next batch is
	 * scheduled now and WP-Cron is asked to run.
	 *
	 * @return void
	 */
	private function resume(): void {
		wp_clear_scheduled_hook( self::TICK_HOOK );
		wp_schedule_single_event( time(), self::TICK_HOOK );
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Start a fresh background scan run (see start()), without the start
	 * lock. Each run has its own ID, so privacy results of earlier runs are
	 * never read as this run's.
	 *
	 * @return array Progress after start.
	 */
	private function begin(): array {
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
				'run'        => bin2hex( random_bytes( 8 ) ),
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
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged
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
