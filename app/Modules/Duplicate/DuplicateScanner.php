<?php
/**
 * Duplicate media file detection via MD5 file hashing.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Duplicate;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Scans media library attachments for duplicate files using MD5 hashing.
 */
class DuplicateScanner {

	/**
	 * Option key for persistent scan state. Survives tab closes so background
	 * ticks can pick up where the user left off.
	 */
	const STATE_OPTION = 'tsmlt_duplicate_scan_state';

	/**
	 * Single-event hook fired between batches. Each tick processes one
	 * batch and self-reschedules until the scan completes or the user cancels.
	 */
	const TICK_HOOK = 'tsmlt_duplicate_scan_tick';

	/**
	 * Seconds between background ticks. Kept short so WP-Cron fires the
	 * next batch on the next visitor request.
	 */
	const TICK_INTERVAL = 1;

	/**
	 * Per-tick batch size. md5_file() is disk-bound; 100 files comfortably
	 * fits under a 30s tick budget on typical SSDs.
	 */
	const TICK_BATCH_SIZE = 100;

	/**
	 * Start lock option (ScanStartLock) shared by every way of starting a
	 * duplicate scan: the admin page's Start and tsmlt/start-duplicate-scan.
	 *
	 * @var string
	 */
	const START_LOCK_OPTION = 'tsmlt_duplicate_scan_start_lock';

	/**
	 * Seconds without progress after which a running scan counts as stalled
	 * (WP-Cron not running its next batch).
	 *
	 * @var int
	 */
	const STALL_SECONDS = 180;

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Scan a batch of attachments and store their file hashes.
	 *
	 * Optimized for large libraries: pre-loads all already-scanned IDs in one
	 * query, then bulk-inserts new rows and bulk-updates existing rows after the
	 * loop — reducing DB queries from O(2n) to O(4) per batch.
	 *
	 * @param int $offset     Offset to start scanning from.
	 * @param int $batch_size Number of attachments to scan per batch.
	 *
	 * @return array{processed: int, total: int, complete: bool}
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

		// Get batch of attachment IDs.
		$batch = Fns::DB()->select( 'ID' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->orderBy( 'ID', 'ASC' )
			->limit( $batch_size )
			->offset( $offset )
			->get();

		if ( empty( $batch ) ) {
			return [
				'processed' => $offset,
				'total'     => $total,
				'complete'  => true,
			];
		}

		// Collect the IDs in this batch.
		$batch_ids = array_map( fn( $row ) => (int) $row['ID'], $batch );

		// Pre-load which IDs are already in the duplicate table (1 query).
		$existing_rows = Fns::DB()->select( 'attachment_id' )
			->from( 'tsmlt_duplicate_file' )
			->whereIn( 'attachment_id', ...$batch_ids )
			->get();

		$already_scanned = [];
		foreach ( ( $existing_rows ?: [] ) as $r ) {
			$already_scanned[ (int) $r['attachment_id'] ] = true;
		}

		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] );

		// Rows to bulk-insert (new) and update payloads (existing).
		$rows_to_insert  = [];
		$rows_to_update  = [];
		$processed       = 0;

		foreach ( $batch as $row ) {
			$attachment_id = (int) $row['ID'];
			$file_path     = get_attached_file( $attachment_id );

			if ( ! $file_path || ! file_exists( $file_path ) ) {
				++$processed;
				continue;
			}

			$file_hash = md5_file( $file_path );
			if ( false === $file_hash ) {
				++$processed;
				continue;
			}

			$file_size = (int) filesize( $file_path );
			$rel_path  = str_replace( $base_dir, '', $file_path );

			if ( isset( $already_scanned[ $attachment_id ] ) ) {
				// Queue for update.
				$rows_to_update[] = [
					'attachment_id' => $attachment_id,
					'file_hash'     => $file_hash,
					'file_size'     => $file_size,
					'file_path'     => $rel_path,
				];
			} else {
				// Queue for insert.
				$rows_to_insert[] = [
					'attachment_id' => $attachment_id,
					'file_hash'     => $file_hash,
					'file_size'     => $file_size,
					'file_path'     => $rel_path,
				];
			}

			++$processed;
		}

		// Bulk insert all new rows (1 query).
		if ( ! empty( $rows_to_insert ) ) {
			Fns::DB()->insert( 'tsmlt_duplicate_file', $rows_to_insert )->execute();
		}

		// Update existing rows individually — query builder has no bulk UPDATE,
		// but these are rare (re-scanning already-scanned IDs).
		foreach ( $rows_to_update as $update ) {
			$attachment_id = $update['attachment_id'];
			unset( $update['attachment_id'] );
			Fns::DB()->update( 'tsmlt_duplicate_file', $update )
				->where( 'attachment_id', '=', $attachment_id )
				->execute();
		}

		$new_offset = $offset + $processed;
		$complete   = $new_offset >= $total;

		return [
			'processed' => $new_offset,
			'total'     => $total,
			'complete'  => $complete,
		];
	}

	/**
	 * Remove rows from tsmlt_duplicate_file for attachments that no longer exist.
	 */
	private function purge_stale_rows(): void {
		$all_ids_result = Fns::DB()->select( 'attachment_id' )
			->from( 'tsmlt_duplicate_file' )
			->get();
		if ( empty( $all_ids_result ) ) {
			return;
		}
		foreach ( $all_ids_result as $row ) {
			$att_id = (int) $row['attachment_id'];
			if ( ! get_post( $att_id ) ) {
				Fns::DB()->delete( 'tsmlt_duplicate_file' )
					->where( 'attachment_id', '=', $att_id )
					->execute();
			}
		}
	}

	/**
	 * Get duplicate groups with pagination.
	 *
	 * @param array $query {
	 *     @type int $paged       Current page number.
	 *     @type int $postsPerPage Items per page.
	 * }
	 *
	 * @return string JSON-encoded result.
	 */
	public function get_duplicates( array $query ): string {
		$page   = max( 1, absint( $query['paged'] ?? 1 ) );
		$limit  = max( 1, absint( $query['postsPerPage'] ?? 20 ) );
		$offset = ( $page - 1 ) * $limit;

		// Get duplicate hashes with pagination.
		$hashes = Fns::DB()->select( 'file_hash', 'file_size' )
			->count( '*', 'cnt' )
			->from( 'tsmlt_duplicate_file' )
			->groupBy( 'file_hash' )
			->raw( 'HAVING cnt > 1' )
			->orderBy( 'cnt', 'DESC' )
			->limit( $limit )
			->offset( $offset )
			->get();

		// Remove stale rows before counting so total_groups is accurate.
		$this->purge_stale_rows();

		// Get total count of duplicate groups.
		$total_result = Fns::DB()->select( 'file_hash' )
			->count( '*', 'cnt' )
			->from( 'tsmlt_duplicate_file' )
			->groupBy( 'file_hash' )
			->raw( 'HAVING cnt > 1' )
			->get();
		$total_groups = is_array( $total_result ) ? count( $total_result ) : 0;

		$groups = [];
		foreach ( ( $hashes ?: [] ) as $hash_row ) {
			$file_hash = $hash_row['file_hash'];

			// Get all items in this duplicate group.
			$items = Fns::DB()->select( 'attachment_id', 'file_size', 'file_path' )
				->from( 'tsmlt_duplicate_file' )
				->where( 'file_hash', '=', $file_hash )
				->orderBy( 'attachment_id', 'ASC' )
				->get();

			$group_items = [];
			foreach ( ( $items ?: [] ) as $item ) {
				$att_id = (int) $item['attachment_id'];
				$post   = get_post( $att_id );
				if ( ! $post ) {
					continue;
				}

				$thumbnail = wp_get_attachment_image_url( $att_id, 'thumbnail' );
				$url       = wp_get_attachment_url( $att_id );
				$mime_type = get_post_mime_type( $att_id );

				// Find all posts/pages where this image is used.
				$used_in    = [];
				$found_ids  = [];
				$post_ids   = $url ? Fns::search_image_at_content( $url ) : [];
				foreach ( $post_ids as $post_id ) {
					$used_post = get_post( (int) $post_id );
					if ( $used_post && ! in_array( $used_post->ID, $found_ids, true ) ) {
						$found_ids[] = $used_post->ID;
						$used_in[]   = [
							'title'     => get_the_title( $used_post ),
							'permalink' => get_the_permalink( $used_post ),
						];
					}
				}

				// Include parent post if not already found.
				if ( $post->post_parent && ! in_array( $post->post_parent, $found_ids, true ) ) {
					$parent = get_post( $post->post_parent );
					if ( $parent ) {
						$used_in[] = [
							'title'     => get_the_title( $parent ),
							'permalink' => get_the_permalink( $parent ),
						];
					}
				}

				$group_items[] = [
					'attachment_id' => $att_id,
					'title'         => $post->post_title,
					'url'           => $url ?: '',
					'thumbnail'     => $thumbnail ?: '',
					'mime_type'     => $mime_type ?: '',
					'file_path'     => $item['file_path'],
					'file_size'     => (int) $item['file_size'],
					'used_in'       => $used_in,
					'upload_date'   => $post->post_date,
				];
			}

			if ( count( $group_items ) > 1 ) {
				$groups[] = [
					'file_hash'  => $file_hash,
					'file_size'  => (int) $hash_row['file_size'],
					'item_count' => count( $group_items ),
					'items'      => $group_items,
				];
			}
		}

		return wp_json_encode(
			[
				'groups'       => $groups,
				'totalGroups'  => $total_groups,
				'paged'        => $page,
				'postsPerPage' => $limit,
			]
		);
	}

	/**
	 * Get scan status and summary statistics.
	 *
	 * @return array{total_attachments: int, scanned: int, duplicate_groups: int, potential_savings: int}
	 */
	public function get_scan_status(): array {
		$this->purge_stale_rows();

		// Total attachments.
		$total_result = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->get();
		$total_attachments = (int) ( $total_result[0]['total'] ?? 0 );

		// Scanned count.
		$scanned_result = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'tsmlt_duplicate_file' )
			->get();
		$scanned = (int) ( $scanned_result[0]['total'] ?? 0 );

		// Duplicate groups — count rows from GROUP BY HAVING query.
		$dup_rows = Fns::DB()->select( 'file_hash', 'file_size' )
			->count( '*', 'cnt' )
			->from( 'tsmlt_duplicate_file' )
			->groupBy( 'file_hash' )
			->raw( 'HAVING cnt > 1' )
			->get();
		$duplicate_groups = is_array( $dup_rows ) ? count( $dup_rows ) : 0;

		// Potential savings: for each duplicate group, file_size * (count - 1).
		$savings = 0;
		if ( ! empty( $dup_rows ) ) {
			foreach ( $dup_rows as $row ) {
				$file_size = isset( $row['file_size'] ) ? (int) $row['file_size'] : 0;
				$savings += $file_size * ( (int) $row['cnt'] - 1 );
			}
		}

		return [
			'total_attachments' => $total_attachments,
			'scanned'           => $scanned,
			'duplicate_groups'  => $duplicate_groups,
			'potential_savings' => $savings,
		];
	}

	/**
	 * Clear all scan results.
	 *
	 * @return array{updated: bool, message: string}
	 */
	public function clear_scan(): array {
		// Drop any in-flight background tick so a clear during scan stops everything.
		wp_clear_scheduled_hook( self::TICK_HOOK );
		delete_option( self::STATE_OPTION );

		Fns::DB()->truncate( 'tsmlt_duplicate_file' );
		Fns::DB()->alter( 'tsmlt_duplicate_file' )->modify( 'id' )->int()->autoIncrement()->execute();

		return [
			'updated' => true,
			'message' => esc_html__( 'Duplicate scan results cleared.', 'media-library-tools' ),
		];
	}

	// -------------------------------------------------------------------------
	// Background scan (self-chaining single events)
	// -------------------------------------------------------------------------

	/**
	 * Default shape for the persisted scan state.
	 *
	 * @return array
	 */
	private function default_state(): array {
		return [
			'status'     => 'idle',
			'offset'     => 0,
			'total'      => 0,
			'started_at' => 0,
			'updated_at' => 0,
		];
	}

	/**
	 * Read the persisted scan state, merged with defaults so callers always
	 * see the full shape even when older runs left partial data.
	 *
	 * @return array
	 */
	public function get_progress(): array {
		$stored = get_option( self::STATE_OPTION, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		$state = array_merge( $this->default_state(), $stored );

		$state['tick_scheduled'] = (bool) wp_next_scheduled( self::TICK_HOOK );

		return $state;
	}

	/**
	 * One page of duplicate groups, read-only (nothing is purged or written):
	 * files with the same hash shared by at least two distinct attachments
	 * that are still in the media library (`inherit` attachments; rows of
	 * deleted or trashed ones, and repeated rows of one attachment, are left
	 * out). Largest groups first, then by hash, so pages are stable.
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Groups per page.
	 *
	 * @return array[]|null Up to $per_page + 1 groups (`file_hash`,
	 *                      `file_size`), the extra one telling there is a
	 *                      next page; null on a database error.
	 */
	public function find_duplicate_groups( int $page, int $per_page ): ?array {
		global $wpdb;
		try {
			$rows = Fns::DB()->select( 'd.file_hash' )
				->max( 'd.file_size', 'file_size' )
				->from( 'tsmlt_duplicate_file d' )
				->innerJoin( 'posts p', 'p.ID', 'd.attachment_id' )
				->where( 'p.post_type', '=', 'attachment' )
				->andWhere( 'p.post_status', '=', 'inherit' )
				->groupBy( 'd.file_hash' )
				// Constant SQL: the query builder has no HAVING or aggregate ORDER BY.
				->raw( 'HAVING COUNT(DISTINCT d.attachment_id) > 1 ORDER BY COUNT(DISTINCT d.attachment_id) DESC, d.file_hash ASC' )
				->limit( $per_page + 1 )
				->offset( ( max( 1, $page ) - 1 ) * $per_page )
				->get();
		} catch ( \Throwable $e ) {
			return null;
		}
		return '' === (string) $wpdb->last_error && is_array( $rows ) ? $rows : null;
	}

	/**
	 * The attachments of duplicate groups, read-only: distinct attachment IDs
	 * per hash, in ID order, for attachments still in the media library.
	 *
	 * @param string[] $hashes Group hashes (find_duplicate_groups()).
	 *
	 * @return array<string, int[]>|null IDs by hash; null on a database error.
	 */
	public function find_duplicate_members( array $hashes ): ?array {
		global $wpdb;
		if ( empty( $hashes ) ) {
			return [];
		}
		try {
			$rows = Fns::DB()->select( 'd.file_hash', 'd.attachment_id' )
				->distinct()
				->from( 'tsmlt_duplicate_file d' )
				->innerJoin( 'posts p', 'p.ID', 'd.attachment_id' )
				->where( 'p.post_type', '=', 'attachment' )
				->andWhere( 'p.post_status', '=', 'inherit' )
				->andIn( 'd.file_hash', ...array_values( $hashes ) )
				->orderBy(
					[
						'd.file_hash'     => 'ASC',
						'd.attachment_id' => 'ASC',
					]
				)
				->get();
		} catch ( \Throwable $e ) {
			return null;
		}
		if ( '' !== (string) $wpdb->last_error || ! is_array( $rows ) ) {
			return null;
		}

		$members = [];
		foreach ( $rows as $row ) {
			$members[ (string) $row['file_hash'] ][] = (int) $row['attachment_id'];
		}
		return $members;
	}

	/**
	 * Start a fresh background scan. Snapshots the current total, marks the
	 * run as running, and schedules the first tick.
	 *
	 * Does not truncate `tsmlt_duplicate_file` — already-scanned rows are
	 * skipped by `scan_batch()`, so a re-start gracefully resumes any work
	 * that survived a prior cancellation.
	 *
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
	 * (resumed), otherwise a new scan starts (started). Under the same start
	 * lock as start(), so it cannot interleave with the admin page's Start.
	 *
	 * @return array{result: string, progress: array}|null The result and the
	 *         progress, or null when the lock could not be read or written.
	 */
	public function start_or_resume(): ?array {
		$lock = ScanStartLock::instance()->acquire( self::START_LOCK_OPTION );
		if ( false === $lock ) {
			return null;
		}
		if ( null === $lock ) {
			// Another request is starting a scan right now.
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
	 * scheduled now (replacing an overdue one) and WP-Cron is asked to run.
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
	 * Start a fresh background scan (see start()), without the start lock.
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
				'offset'     => 0,
				'total'      => $total,
				'started_at' => $now,
				'updated_at' => $now,
			]
		);
		update_option( self::STATE_OPTION, $state, false );

		if ( $total > 0 ) {
			// First tick fires almost immediately — no benefit to idling on Start.
			wp_schedule_single_event( $now + 1, self::TICK_HOOK );
		}

		return $this->get_progress();
	}

	/**
	 * Cancel an in-flight scan. Marks the run as cancelled and unschedules
	 * the pending tick. Already-scanned rows in `tsmlt_duplicate_file` are
	 * left in place so the user can still view partial results.
	 *
	 * @return array Progress after cancel.
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
	 * then self-reschedules unless the scan finished or the user cancelled
	 * mid-batch.
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

		$batch_size = (int) apply_filters( 'tsmlt_duplicate_scan_tick_batch_size', self::TICK_BATCH_SIZE );
		$batch_size = max( 1, min( 500, $batch_size ) );

		$result = $self->scan_batch( (int) $state['offset'], $batch_size );

		$state['offset']     = (int) $result['processed'];
		$state['total']      = (int) $result['total'];
		$state['updated_at'] = time();

		if ( $result['complete'] ) {
			$state['status'] = 'done';
		}

		unset( $state['tick_scheduled'] );
		update_option( self::STATE_OPTION, $state, false );

		// Re-read after the batch — status may have flipped to cancelled while we ran.
		$fresh = get_option( self::STATE_OPTION, [] );
		if ( is_array( $fresh ) && 'running' === ( $fresh['status'] ?? '' ) ) {
			wp_schedule_single_event( time() + self::TICK_INTERVAL, self::TICK_HOOK );
		}
	}
}
