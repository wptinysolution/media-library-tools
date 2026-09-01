<?php
/**
 * Persistent compression job queue.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionCapabilities;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionSettings;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Runs bulk compression as a resumable background job.
 *
 * Follows the same model as the plugin's other long-running features
 * (Regenerate Thumbnails, EXIF Scanner, Duplicate Scanner): one option row
 * holds the job state and a self-rescheduling WP-Cron single event processes
 * one batch per tick. Because progress lives server-side, closing the browser
 * never loses a run — the UI simply polls the same state when it returns.
 */
class CompressionJob {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Option key holding the current job state.
	 */
	const STATE_OPTION = 'tsmlt_compression_job';

	/**
	 * Job type: re-encode images in place to reduce file size.
	 */
	const TYPE_COMPRESSION = 'compression';

	/**
	 * Job type: generate WebP/AVIF companions alongside the originals.
	 */
	const TYPE_CONVERSION = 'conversion';

	/**
	 * Single-event hook fired between batches.
	 */
	const TICK_HOOK = 'tsmlt_compression_tick';

	/**
	 * Seconds between background ticks. Kept at 1 so WP-Cron picks up the next
	 * batch on the very next request; WP-Cron's own locking prevents overlap.
	 */
	const TICK_INTERVAL = 1;

	/**
	 * Images processed per tick.
	 *
	 * Compression is far heavier than metadata work, so this is deliberately
	 * small — a handful of large JPEGs can take several seconds each.
	 */
	const TICK_BATCH_SIZE = 5;

	/**
	 * Images per batch when AVIF output is requested.
	 *
	 * AVIF encoding is roughly 5x slower than WebP, so fewer images per batch
	 * keeps a request comfortably inside typical execution limits.
	 */
	const AVIF_BATCH_SIZE = 2;

	/**
	 * Hard ceiling on the batch size, whatever a caller or filter requests.
	 */
	const MAX_BATCH_SIZE = 25;

	/**
	 * Cap on retained per-image result entries, keeping the option row bounded.
	 */
	const RECENT_CAP = 50;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * The empty job shape.
	 *
	 * @return array
	 */
	private function default_state(): array {
		return [
			'job_id'         => '',
			// Which processor drives this run. Defaults to compression so job
			// rows written before conversion existed keep working unchanged.
			'job_type'       => self::TYPE_COMPRESSION,
			'status'         => 'idle',
			'queue'          => [],
			'failed_ids'     => [],
			'total'          => 0,
			'processed'      => 0,
			'succeeded'      => 0,
			'skipped'        => 0,
			'failed'         => 0,
			'saved_bytes'    => 0,
			'current_id'     => 0,
			'settings'       => [],
			'started_at'     => 0,
			'updated_at'     => 0,
			'recent_results' => [],
			'recent_errors'  => [],
			'last_error'     => '',
		];
	}

	/**
	 * Read the current job state, merged over defaults.
	 *
	 * @return array
	 */
	public function get_state(): array {
		$stored = get_option( self::STATE_OPTION, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return array_merge( $this->default_state(), $stored );
	}

	/**
	 * Persist the job state.
	 *
	 * Stored with autoload disabled: the queue can hold thousands of IDs and
	 * must never be loaded on every page request.
	 *
	 * @param array $state State to store.
	 *
	 * @return void
	 */
	private function save_state( array $state ): void {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * Progress payload for the UI.
	 *
	 * The queue itself is deliberately omitted — the browser has no use for
	 * thousands of IDs, and sending them would bloat every poll.
	 *
	 * @return array
	 */
	public function get_progress(): array {
		$state = $this->get_state();

		// A job with nothing left in the queue cannot make progress, so it must
		// never be reported as running — otherwise the UI reattaches to it on
		// load and appears to start a run the user never asked for.
		if ( 'running' === $state['status'] && empty( $state['queue'] ) ) {
			$state['status'] = $state['failed'] > 0 ? 'partial' : 'completed';
			$this->save_state( $state );
		}

		$total     = (int) $state['total'];
		$processed = (int) $state['processed'];
		$percent   = $total > 0 ? (int) floor( ( $processed / $total ) * 100 ) : 0;

		return [
			'job_id'         => (string) $state['job_id'],
			'status'         => (string) $state['status'],
			'total'          => $total,
			'processed'      => $processed,
			'succeeded'      => (int) $state['succeeded'],
			'skipped'        => (int) $state['skipped'],
			'failed'         => (int) $state['failed'],
			'remaining'      => max( 0, $total - $processed ),
			'percent'        => min( 100, max( 0, $percent ) ),
			'current_id'     => (int) $state['current_id'],
			'saved_bytes'    => (int) $state['saved_bytes'],
			'saved_readable' => size_format( (int) $state['saved_bytes'], 1 ),
			'settings'       => (array) $state['settings'],
			'recent_results' => (array) $state['recent_results'],
			'recent_errors'  => (array) $state['recent_errors'],
			'last_error'     => (string) $state['last_error'],
			'has_failed'     => ! empty( $state['failed_ids'] ),
			'tick_scheduled' => (bool) wp_next_scheduled( self::TICK_HOOK ),
		];
	}

	/**
	 * Create and start a compression job.
	 *
	 * Every submitted ID is revalidated here — existence, post type, MIME type
	 * and per-attachment edit capability — so IDs from the browser are never
	 * trusted. The Free-tier limit is applied to the validated list, after
	 * unusable IDs are dropped, so a user is not charged quota for images that
	 * were never eligible.
	 *
	 * @param int[] $attachment_ids Requested attachment IDs.
	 * @param array $params         Raw request parameters for run settings.
	 *
	 * @return array|WP_Error
	 */
	public function start( array $attachment_ids, array $params ) {
		$access = CompressionAccess::instance();

		if ( ! $access->is_compression_feature_available() ) {
			return new WP_Error(
				'tsmlt_compression_engine_unavailable',
				esc_html__( 'No image compression library (ImageMagick or GD) is available on this server.', 'media-library-tools' )
			);
		}

		$state = $this->get_state();

		if ( 'running' === $state['status'] ) {
			return new WP_Error(
				'tsmlt_compression_job_running',
				esc_html__( 'A compression job is already running. Wait for it to finish or cancel it first.', 'media-library-tools' )
			);
		}

		$eligible = $this->filter_eligible( $attachment_ids );

		if ( empty( $eligible ) ) {
			return new WP_Error(
				'tsmlt_compression_no_images',
				esc_html__( 'None of the selected items are images that can be compressed.', 'media-library-tools' )
			);
		}

		// Server-side Free-tier enforcement. The frontend shows the same limit,
		// but this is what actually binds.
		$limit         = $access->get_compression_limit();
		$limit_applied = false;

		if ( $limit > 0 && count( $eligible ) > $limit ) {
			$eligible      = array_slice( $eligible, 0, $limit );
			$limit_applied = true;
		}

		$run_settings = CompressionSettings::instance()->resolve_run_settings( $params );
		$now          = time();

		$state               = $this->default_state();
		$state['job_id']     = uniqid( 'tsmlt_', false );
		$state['status']     = 'running';
		$state['queue']      = $eligible;
		$state['total']      = count( $eligible );
		$state['settings']   = $run_settings;
		$state['started_at'] = $now;
		$state['updated_at'] = $now;

		$this->save_state( $state );

		wp_clear_scheduled_hook( self::TICK_HOOK );
		wp_schedule_single_event( $now + 1, self::TICK_HOOK );

		$progress                  = $this->get_progress();
		$progress['limit_applied'] = $limit_applied;
		$progress['limit']         = $limit;

		return $progress;
	}

	/**
	 * Create and start a conversion job over the given attachments.
	 *
	 * Mirrors `start()` but validates against conversion rules and stores a
	 * `job_type` of `conversion`, which is what makes `process_batch()` dispatch
	 * to the converter instead of the compressor.
	 *
	 * @param int[] $attachment_ids Requested attachment IDs.
	 * @param array $params         Raw request parameters for run settings.
	 *
	 * @return array|WP_Error
	 */
	public function start_conversion( array $attachment_ids, array $params ) {
		$access = CompressionAccess::instance();

		if ( ! ConversionCapabilities::instance()->is_available() ) {
			return new WP_Error(
				'tsmlt_conversion_engine_unavailable',
				esc_html__( 'This server cannot produce WebP or AVIF images. Ask your host to enable ImageMagick or GD with WebP support.', 'media-library-tools' )
			);
		}

		$state = $this->get_state();

		if ( 'running' === $state['status'] ) {
			return new WP_Error(
				'tsmlt_compression_job_running',
				esc_html__( 'A job is already running. Wait for it to finish or cancel it first.', 'media-library-tools' )
			);
		}

		$run_settings = ConversionSettings::instance()->resolve_run_settings( $params );

		if ( empty( $run_settings['formats'] ) ) {
			return new WP_Error(
				'tsmlt_conversion_no_formats',
				esc_html__( 'Select at least one output format that this server and your licence support.', 'media-library-tools' )
			);
		}

		$eligible = $this->filter_eligible_for_conversion( $attachment_ids );

		if ( empty( $eligible ) ) {
			return new WP_Error(
				'tsmlt_conversion_no_images',
				esc_html__( 'None of the selected items are images that can be converted.', 'media-library-tools' )
			);
		}

		// Server-side Free-tier enforcement; the frontend limit is cosmetic.
		$limit         = $access->get_conversion_limit();
		$limit_applied = false;

		if ( $limit > 0 && count( $eligible ) > $limit ) {
			$eligible      = array_slice( $eligible, 0, $limit );
			$limit_applied = true;
		}

		$now = time();

		$state               = $this->default_state();
		$state['job_id']     = uniqid( 'tsmlt_', false );
		$state['job_type']   = self::TYPE_CONVERSION;
		$state['status']     = 'running';
		$state['queue']      = $eligible;
		$state['total']      = count( $eligible );
		$state['settings']   = $run_settings;
		$state['started_at'] = $now;
		$state['updated_at'] = $now;

		$this->save_state( $state );

		wp_clear_scheduled_hook( self::TICK_HOOK );
		wp_schedule_single_event( $now + 1, self::TICK_HOOK );

		$progress                  = $this->get_progress();
		$progress['limit_applied'] = $limit_applied;
		$progress['limit']         = $limit;

		return $progress;
	}

	/**
	 * Reduce a raw ID list to attachments this user may actually convert.
	 *
	 * @param int[] $attachment_ids Requested attachment IDs.
	 *
	 * @return int[]
	 */
	private function filter_eligible_for_conversion( array $attachment_ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $attachment_ids ) ) ) );

		if ( empty( $ids ) ) {
			return [];
		}

		// One primed cache instead of a query per attachment.
		_prime_post_caches( $ids, false, true );

		$converter = AttachmentConverter::instance();
		$eligible  = [];

		foreach ( $ids as $attachment_id ) {
			if ( ! is_wp_error( $converter->validate_attachment( $attachment_id ) ) ) {
				$eligible[] = $attachment_id;
			}
		}

		return $eligible;
	}

	/**
	 * Count the compressible images in the library and how many are already done.
	 *
	 * Two aggregate queries rather than loading IDs, so the figure stays cheap on
	 * libraries with tens of thousands of attachments.
	 *
	 * @return array{total: int, compressed: int, remaining: int}
	 */
	public function get_library_stats(): array {
		$mime_types = CompressionManager::SUPPORTED_MIME_TYPES;

		$total_rows = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->andIn( 'post_mime_type', ...$mime_types )
			->get();

		$total = (int) ( $total_rows[0]['total'] ?? 0 );

		// Attachments carrying the compression meta key have already been run.
		$done_rows = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', CompressionMetadata::META_KEY )
			->get();

		$compressed = min( $total, (int) ( $done_rows[0]['total'] ?? 0 ) );

		return [
			'total'      => $total,
			'compressed' => $compressed,
			'remaining'  => max( 0, $total - $compressed ),
		];
	}

	/**
	 * Queue every not-yet-compressed image in the library.
	 *
	 * Only IDs that still need work are selected, so re-running after a partial
	 * pass picks up where the last one stopped instead of revisiting finished
	 * images. The Free-tier cap is applied by `start()` exactly as it is for a
	 * hand-picked selection.
	 *
	 * @param array $params Raw request parameters for run settings.
	 *
	 * @return array|WP_Error
	 */
	public function start_library( array $params ) {
		global $wpdb;

		$limit = CompressionAccess::instance()->get_compression_limit();

		// "Include already compressed" re-runs the whole library. Needed when the
		// user turns on backups or generated sizes after an initial pass: those
		// images carry compression data but still have outstanding work, so the
		// default exclusion would wrongly report nothing to do.
		$include_done = ! empty( $params['include_compressed'] );

		// Attachments that already carry the compression meta key are finished.
		// The query builder has no LEFT JOIN, so the exclusion is expressed as a
		// subquery through `raw()` — the meta key is a class constant and the
		// MIME list is a fixed whitelist, so no request data reaches the SQL.
		$excluded = $wpdb->prepare(
			"AND ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s )",
			CompressionMetadata::META_KEY
		);

		// Free installs only ever process up to the cap, so there is no reason to
		// pull more IDs than that. Pro fetches everything outstanding.
		$query = Fns::DB()->select( 'ID' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->andIn( 'post_mime_type', ...CompressionManager::SUPPORTED_MIME_TYPES );

		if ( ! $include_done ) {
			$query->raw( $excluded );
		}

		if ( $include_done && $limit > 0 ) {
			// A capped re-run must not keep picking the same newest images, or
			// repeated runs would never reach the rest of the library. The run
			// marker is rewritten on every pass, so ordering by it oldest-first
			// rotates through the library. Never-processed images have no marker
			// and sort first, which is the order we want anyway.
			$query->raw(
				$wpdb->prepare(
					"ORDER BY COALESCE( ( SELECT pm2.meta_value + 0 FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id = {$wpdb->posts}.ID AND pm2.meta_key = %s LIMIT 1 ), 0 ) ASC, {$wpdb->posts}.ID DESC",
					CompressionMetadata::RUN_META_KEY
				)
			);
		} else {
			$query->orderBy( 'ID', 'DESC' );
		}

		if ( $limit > 0 ) {
			$query->limit( $limit );
		}

		$rows = $query->get();
		$ids  = [];

		foreach ( ( $rows ?: [] ) as $row ) {
			$ids[] = (int) $row['ID'];
		}

		if ( empty( $ids ) ) {
			return new WP_Error(
				'tsmlt_compression_nothing_to_do',
				$include_done
					? esc_html__( 'There are no supported images to compress.', 'media-library-tools' )
					: esc_html__( 'Every supported image has already been compressed.', 'media-library-tools' )
			);
		}

		return $this->start( $ids, $params );
	}

	/**
	 * Reduce a raw ID list to the attachments this user may actually compress.
	 *
	 * @param int[] $attachment_ids Requested attachment IDs.
	 *
	 * @return int[]
	 */
	private function filter_eligible( array $attachment_ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $attachment_ids ) ) ) );

		if ( empty( $ids ) ) {
			return [];
		}

		// One primed cache instead of a query per attachment.
		_prime_post_caches( $ids, false, true );

		$processor = AttachmentProcessor::instance();
		$eligible  = [];

		foreach ( $ids as $attachment_id ) {
			if ( ! is_wp_error( $processor->validate_attachment( $attachment_id ) ) ) {
				$eligible[] = $attachment_id;
			}
		}

		return $eligible;
	}

	/**
	 * Cancel the running job, keeping results collected so far.
	 *
	 * @return array
	 */
	public function cancel(): array {
		wp_clear_scheduled_hook( self::TICK_HOOK );

		$state = $this->get_state();

		// Anything not already finished becomes cancelled. Testing only for
		// 'running' left a window where a batch that flipped the status first
		// kept the job resumable, so reopening the page restarted it.
		if ( ! in_array( $state['status'], [ 'completed', 'partial', 'failed', 'idle' ], true ) ) {
			$state['status'] = 'cancelled';
		}

		$state['queue']      = [];
		$state['current_id'] = 0;
		$state['updated_at'] = time();

		$this->save_state( $state );

		return $this->get_progress();
	}

	/**
	 * Requeue the images that failed in the last run.
	 *
	 * @return array|WP_Error
	 */
	public function retry() {
		$state = $this->get_state();

		if ( 'running' === $state['status'] ) {
			return new WP_Error(
				'tsmlt_compression_job_running',
				esc_html__( 'A compression job is already running.', 'media-library-tools' )
			);
		}

		if ( empty( $state['failed_ids'] ) ) {
			return new WP_Error(
				'tsmlt_compression_nothing_to_retry',
				esc_html__( 'There are no failed images to retry.', 'media-library-tools' )
			);
		}

		// Reuse the original run settings so a retry reproduces the same run.
		$params = is_array( $state['settings'] ) ? $state['settings'] : [];

		return $this->start( (array) $state['failed_ids'], $params );
	}

	/**
	 * Discard the stored job so the UI returns to its idle state.
	 *
	 * @return array
	 */
	public function reset(): array {
		wp_clear_scheduled_hook( self::TICK_HOOK );
		delete_option( self::STATE_OPTION );

		return $this->get_progress();
	}

	/**
	 * Process one batch and reschedule until the queue drains.
	 *
	 * Registered against `self::TICK_HOOK` in `CronJobHooks`.
	 *
	 * @return void
	 */
	public static function run_tick(): void {
		self::instance()->run_batch( true );
	}

	/**
	 * Process one batch of the current job.
	 *
	 * Shared by both drivers so their behaviour cannot drift:
	 *
	 * - WP-Cron ticks (`run_tick()`), which keep a job moving after the browser
	 *   has gone away;
	 * - the AJAX endpoint the open modal calls, which keeps a job moving on
	 *   installs where WP-Cron is disabled or simply never fires because the
	 *   site receives no other traffic.
	 *
	 * Both are safe to run concurrently: each batch splices its items off the
	 * front of the stored queue before processing, and `AttachmentProcessor`
	 * holds a per-attachment lock, so an image is never compressed twice.
	 *
	 * @param bool $reschedule Whether to queue the next WP-Cron tick.
	 *
	 * @return array Progress after the batch.
	 */
	public function run_batch( bool $reschedule = false ): array {
		$state = $this->get_state();

		// Cancelled or finished between batches — stop here.
		if ( 'running' !== $state['status'] ) {
			return $this->get_progress();
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- Best effort; ignored by some SAPIs.
		}

		$batch_size = (int) apply_filters( 'tsmlt_compression_tick_batch_size', $this->get_batch_size( $state ) );
		$batch_size = max( 1, min( self::MAX_BATCH_SIZE, $batch_size ) );

		$state = $this->process_batch( $state, $batch_size );

		// A batch takes seconds, so the user may have cancelled while it ran.
		// `$state` still carries the status captured before that, and writing it
		// back verbatim would resurrect a cancelled job — which is why Stop had
		// to be pressed repeatedly. Re-read first and let the cancellation win,
		// while still keeping the counts this batch produced.
		$fresh = $this->get_state();

		if ( 'running' !== $fresh['status'] ) {
			$state['status'] = $fresh['status'];
			$state['queue']  = [];
		}

		$this->save_state( $state );

		if ( $reschedule && 'running' === $state['status'] && ! empty( $state['queue'] ) ) {
			wp_schedule_single_event( time() + self::TICK_INTERVAL, self::TICK_HOOK );
		}

		return $this->get_progress();
	}

	/**
	 * Images to process per batch for the given job.
	 *
	 * AVIF encoding is several times slower than WebP or JPEG re-encoding — on
	 * this codebase's own benchmark roughly 5x — so a batch including AVIF is
	 * deliberately smaller to stay well inside `max_execution_time` on shared
	 * hosting. The value is never taken from the browser.
	 *
	 * @param array $state Current job state.
	 *
	 * @return int
	 */
	private function get_batch_size( array $state ): int {
		$is_conversion = self::TYPE_CONVERSION === ( $state['job_type'] ?? self::TYPE_COMPRESSION );

		if ( ! $is_conversion ) {
			return self::TICK_BATCH_SIZE;
		}

		$formats = (array) ( $state['settings']['formats'] ?? [] );

		return in_array( 'avif', $formats, true ) ? self::AVIF_BATCH_SIZE : self::TICK_BATCH_SIZE;
	}

	/**
	 * Compress up to `$batch_size` images from the front of the queue.
	 *
	 * @param array $state      Current job state.
	 * @param int   $batch_size Images to process this tick.
	 *
	 * @return array Updated state.
	 */
	private function process_batch( array $state, int $batch_size ): array {
		$is_conversion = self::TYPE_CONVERSION === ( $state['job_type'] ?? self::TYPE_COMPRESSION );
		$processor     = $is_conversion
			? AttachmentConverter::instance()
			: AttachmentProcessor::instance();
		$run_settings  = is_array( $state['settings'] ) ? $state['settings'] : [];
		$queue         = (array) $state['queue'];
		$batch         = array_splice( $queue, 0, $batch_size );

		if ( ! empty( $batch ) ) {
			_prime_post_caches( $batch, false, true );
		}

		foreach ( $batch as $attachment_id ) {
			$attachment_id       = absint( $attachment_id );
			$state['current_id'] = $attachment_id;

			$result = $is_conversion
				? $processor->convert( $attachment_id, $run_settings )
				: $processor->process( $attachment_id, $run_settings );

			++$state['processed'];

			if ( is_wp_error( $result ) ) {
				++$state['failed'];
				$state['failed_ids'][] = $attachment_id;
				$state['last_error']   = $result->get_error_message();

				$state['recent_errors'] = array_slice(
					array_merge(
						(array) $state['recent_errors'],
						[
							[
								'id'    => $attachment_id,
								'title' => $this->get_title( $attachment_id ),
								'error' => $result->get_error_message(),
							],
						]
					),
					-self::RECENT_CAP
				);

				continue;
			}

			// Normalise the two processors onto one result shape so the counters
			// and result rows below stay identical for both job types. For a
			// conversion, "before" is the source and "after" the generated
			// output, which makes the saved figure the space the modern format
			// would serve instead of the original.
			if ( $is_conversion ) {
				$result = [
					'status'        => 'partial' === $result['status'] ? 'completed' : $result['status'],
					'reason'        => $result['skipped'] > 0 && 0 === $result['succeeded'] ? 'same_format' : '',
					'before'        => (int) $result['source_size'],
					'after'         => (int) $result['output_size'],
					'saved_percent' => $result['source_size'] > 0 && $result['output_size'] > 0
						? round( ( 1 - $result['output_size'] / $result['source_size'] ) * 100, 2 )
						: 0.0,
					'formats'       => $result['formats'],
				];
			}

			if ( 'completed' === $result['status'] ) {
				++$state['succeeded'];
				$state['saved_bytes'] += max( 0, (int) $result['before'] - (int) $result['after'] );
			} else {
				++$state['skipped'];
			}

			$state['recent_results'] = array_slice(
				array_merge(
					(array) $state['recent_results'],
					[
						[
							'id'              => $attachment_id,
							'title'           => $this->get_title( $attachment_id ),
							'status'          => $result['status'],
							'reason'          => $result['reason'],
							'before'          => (int) $result['before'],
							'after'           => (int) $result['after'],
							'before_readable' => size_format( (int) $result['before'], 1 ),
							'after_readable'  => size_format( (int) $result['after'], 1 ),
							'saved_percent'   => (float) $result['saved_percent'],
						],
					]
				),
				-self::RECENT_CAP
			);
		}

		$state['queue']      = array_values( $queue );
		$state['current_id'] = 0;
		$state['updated_at'] = time();

		if ( empty( $state['queue'] ) ) {
			// "partial" distinguishes a run that finished with failures from a
			// clean one, so the UI can offer a retry without re-reading errors.
			$state['status'] = $state['failed'] > 0
				? ( $state['succeeded'] > 0 || $state['skipped'] > 0 ? 'partial' : 'failed' )
				: 'completed';
		}

		return $state;
	}

	/**
	 * Attachment title for result rows, falling back to the ID.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return string
	 */
	private function get_title( int $attachment_id ): string {
		$title = get_the_title( $attachment_id );

		return '' !== $title ? $title : sprintf( '#%d', $attachment_id );
	}
}
