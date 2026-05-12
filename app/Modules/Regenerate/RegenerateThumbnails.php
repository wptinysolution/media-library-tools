<?php
/**
 * Regenerate Thumbnails — batch reprocesses attachment metadata and image sizes.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Regenerate;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Handles batch regeneration of WordPress image thumbnail sizes.
 */
class RegenerateThumbnails {

	/**
	 * Option key for persistent regen state. Survives page reloads and tab
	 * closes so background ticks can resume across sessions.
	 */
	const STATE_OPTION = 'tsmlt_regenerate_state';

	/**
	 * Single-event hook fired by WP-Cron between batches. Each invocation
	 * processes one batch and self-reschedules until the run completes
	 * or the user cancels.
	 */
	const TICK_HOOK = 'tsmlt_regenerate_tick';

	/**
	 * Seconds between background ticks. Kept to 1 second so WP-Cron fires
	 * the next batch on the very next visitor request — there's no benefit
	 * to idling between batches, and WP-Cron's own locking prevents overlap.
	 */
	const TICK_INTERVAL = 1;

	/**
	 * Per-tick batch size for background runs.
	 *
	 * Sized to finish a tick comfortably under typical `max_execution_time`
	 * (30s) on a mid-range host with Imagick. Sites with very large source
	 * images can lower this via the `tsmlt_regenerate_tick_batch_size` filter.
	 */
	const TICK_BATCH_SIZE = 50;

	/**
	 * Cap on retained recent-error / recent-success entries in the state
	 * row. Prevents the option payload from growing unbounded on huge runs.
	 */
	const RECENT_CAP = 50;

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Return the total number of image attachments in the media library.
	 *
	 * @return int
	 */
	public function get_total(): int {
		$result = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_mime_type', 'LIKE', 'image/%' )
			->andWhere( 'post_status', '=', 'inherit' )
			->get();

		return (int) ( $result[0]['total'] ?? 0 );
	}

	/**
	 * Process one batch of image attachments: regenerate thumbnails for all
	 * currently registered sizes, then delete any orphan thumbnail files that
	 * belong to sizes no longer registered in WordPress.
	 *
	 * @param int $offset     Zero-based offset into the full attachment list.
	 * @param int $batch_size Number of attachments to process per call.
	 *
	 * @return array{
	 *     processed:     int,
	 *     total:         int,
	 *     complete:      bool,
	 *     deleted_total: int,
	 *     errors:        array<array{id: int, file: string, error: string}>,
	 *     succeeded:     array<array{id: int, file: string, deleted_sizes: string[]}>
	 * }
	 */
	public function regenerate_batch( int $offset = 0, int $batch_size = 10 ): array {
		$total = $this->get_total();

		// Fetch a batch of image attachment IDs ordered deterministically.
		$rows = Fns::DB()->select( 'ID' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_mime_type', 'LIKE', 'image/%' )
			->andWhere( 'post_status', '=', 'inherit' )
			->orderBy( 'ID', 'ASC' )
			->limit( $batch_size )
			->offset( $offset )
			->get();

		$errors        = [];
		$succeeded     = [];
		$deleted_total = 0;

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'wp_read_video_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$registered = array_keys( wp_get_registered_image_subsizes() );

		foreach ( ( $rows ?: [] ) as $row ) {
			$attachment_id = (int) $row['ID'];
			$file          = get_attached_file( $attachment_id );
			$filename      = $file ? basename( $file ) : "ID:{$attachment_id}";

			if ( ! $file || ! file_exists( $file ) ) {
				$errors[] = [
					'id'    => $attachment_id,
					'file'  => $filename,
					'error' => esc_html__( 'File not found on disk.', 'media-library-tools' ),
				];
				continue;
			}

			// Check if the image editor supports this file's MIME type.
			$mime_type = get_post_mime_type( $attachment_id );
			$editor    = wp_get_image_editor( $file );

			if ( is_wp_error( $editor ) ) {
				$errors[] = [
					'id'    => $attachment_id,
					'file'  => $filename,
					'error' => sprintf(
						/* translators: 1: MIME type, 2: error message */
						esc_html__( 'Image editor cannot handle %1$s: %2$s', 'media-library-tools' ),
						$mime_type,
						$editor->get_error_message()
					),
				];
				continue;
			}

			// ── Step 1: capture old sizes before regenerating ────────────────
			$old_metadata  = wp_get_attachment_metadata( $attachment_id );
			$old_sizes     = is_array( $old_metadata ) && ! empty( $old_metadata['sizes'] )
				? $old_metadata['sizes']
				: [];

			// ── Step 2: regenerate all registered thumbnail sizes ────────────
			$metadata = wp_generate_attachment_metadata( $attachment_id, $file );

			if ( is_wp_error( $metadata ) ) {
				$errors[] = [
					'id'    => $attachment_id,
					'file'  => $filename,
					'error' => $metadata->get_error_message(),
				];
				continue;
			}

			if ( empty( $metadata ) ) {
				$errors[] = [
					'id'    => $attachment_id,
					'file'  => $filename,
					'error' => esc_html__( 'Could not generate metadata (unsupported format?).', 'media-library-tools' ),
				];
				continue;
			}

			// ── Step 3: delete orphan files for unregistered sizes ───────────
			$upload_dir    = trailingslashit( dirname( $file ) );
			$deleted_sizes = [];

			// New metadata only contains registered sizes, but old sizes may
			// still have files on disk that were never regenerated into new metadata.
			foreach ( $old_sizes as $size_name => $size_data ) {
				if ( in_array( $size_name, $registered, true ) ) {
					continue;
				}
				$thumb_file = $upload_dir . ( $size_data['file'] ?? '' );
				if ( ! empty( $size_data['file'] ) && file_exists( $thumb_file ) ) {
					wp_delete_file( $thumb_file );
					$deleted_sizes[] = $size_name;
					++$deleted_total;
				}
			}

			// ── Step 4: save the clean, registered-only metadata ─────────────
			wp_update_attachment_metadata( $attachment_id, $metadata );

			$succeeded[] = [
				'id'            => $attachment_id,
				'file'          => $filename,
				'deleted_sizes' => $deleted_sizes,
			];
		}

		$processed_count = count( $rows ?: [] );
		$new_offset      = $offset + $processed_count;
		$complete        = $new_offset >= $total || 0 === $processed_count;

		return [
			'processed'     => $new_offset,
			'total'         => $total,
			'complete'      => $complete,
			'deleted_total' => $deleted_total,
			'errors'        => $errors,
			'succeeded'     => $succeeded,
		];
	}

	// -------------------------------------------------------------------------
	// Background regeneration (self-chaining single events)
	// -------------------------------------------------------------------------

	/**
	 * Default shape for the persisted state row.
	 *
	 * @return array
	 */
	private function default_state(): array {
		return [
			'status'        => 'idle',
			'offset'        => 0,
			'total'         => 0,
			'started_at'    => 0,
			'updated_at'    => 0,
			'errors_count'  => 0,
			'success_count' => 0,
			'deleted_total' => 0,
			'recent_errors' => [],
			'recent_done'   => [],
		];
	}

	/**
	 * Load the current state, merged with defaults so callers always get
	 * the full shape even when keys were added later.
	 *
	 * @return array
	 */
	public function get_progress(): array {
		$stored = get_option( self::STATE_OPTION, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		$state = array_merge( $this->default_state(), $stored );

		// Surface whether a tick is actually scheduled, so the UI can tell
		// "running but tick was lost" apart from "running and on track".
		$state['tick_scheduled'] = (bool) wp_next_scheduled( self::TICK_HOOK );

		return $state;
	}

	/**
	 * Start a fresh regeneration run. Wipes any previous state, snapshots
	 * the current total, and schedules the first background tick.
	 *
	 * @return array Current progress after start.
	 */
	public function start(): array {
		// Drop any pending tick from a prior run so we don't double-fire.
		wp_clear_scheduled_hook( self::TICK_HOOK );

		$total = $this->get_total();
		$now   = time();
		$state = array_merge(
			$this->default_state(),
			[
				'status'     => $total > 0 ? 'running' : 'done',
				'total'      => $total,
				'started_at' => $now,
				'updated_at' => $now,
			]
		);
		update_option( self::STATE_OPTION, $state, false );

		if ( $total > 0 ) {
			// First tick fires immediately — no point waiting on the interval
			// when the user just clicked "Start".
			wp_schedule_single_event( $now + 1, self::TICK_HOOK );
		}

		return $this->get_progress();
	}

	/**
	 * Cancel an in-flight run. Marks state as cancelled and unschedules
	 * any pending tick. Progress and errors stay visible until the next start.
	 *
	 * @return array Current progress after cancel.
	 */
	public function cancel(): array {
		wp_clear_scheduled_hook( self::TICK_HOOK );

		$state               = $this->get_progress();
		$state['status']     = 'cancelled';
		$state['updated_at'] = time();
		unset( $state['tick_scheduled'] );
		update_option( self::STATE_OPTION, $state, false );

		return $this->get_progress();
	}

	/**
	 * Background tick handler. Processes one batch, updates the state row,
	 * then self-reschedules unless the run is complete or has been cancelled.
	 *
	 * Registered against `self::TICK_HOOK` in `CronJobHooks`.
	 *
	 * @return void
	 */
	public static function run_tick(): void {
		$self  = self::instance();
		$state = $self->get_progress();

		// User cancelled (or status was never set to running) — exit without rescheduling.
		if ( 'running' !== ( $state['status'] ?? '' ) ) {
			return;
		}

		// Try to extend the PHP execution limit for this tick. Safe-mode and
		// some FastCGI configs ignore this; treat the call as best-effort.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$batch_size = (int) apply_filters( 'tsmlt_regenerate_tick_batch_size', self::TICK_BATCH_SIZE );
		$batch_size = max( 1, min( 200, $batch_size ) );

		$result = $self->regenerate_batch( (int) $state['offset'], $batch_size );

		// Merge counters.
		$state['offset']        = (int) $result['processed'];
		$state['total']         = (int) $result['total'];
		$state['deleted_total'] = (int) ( $state['deleted_total'] ?? 0 ) + (int) $result['deleted_total'];
		$state['success_count'] = (int) ( $state['success_count'] ?? 0 ) + count( $result['succeeded'] );
		$state['errors_count']  = (int) ( $state['errors_count'] ?? 0 ) + count( $result['errors'] );
		$state['updated_at']    = time();

		// Append to recent lists, capped to keep the option payload small.
		if ( ! empty( $result['succeeded'] ) ) {
			$state['recent_done'] = array_slice(
				array_merge( (array) ( $state['recent_done'] ?? [] ), $result['succeeded'] ),
				-self::RECENT_CAP
			);
		}
		if ( ! empty( $result['errors'] ) ) {
			$state['recent_errors'] = array_slice(
				array_merge( (array) ( $state['recent_errors'] ?? [] ), $result['errors'] ),
				-self::RECENT_CAP
			);
		}

		if ( $result['complete'] ) {
			$state['status'] = 'done';
		}

		unset( $state['tick_scheduled'] );
		update_option( self::STATE_OPTION, $state, false );

		// Re-check status — it may have flipped to cancelled while we were
		// processing this batch, in which case stop the chain here.
		$fresh = get_option( self::STATE_OPTION, [] );
		if ( is_array( $fresh ) && 'running' === ( $fresh['status'] ?? '' ) ) {
			wp_schedule_single_event( time() + self::TICK_INTERVAL, self::TICK_HOOK );
		}
	}
}
