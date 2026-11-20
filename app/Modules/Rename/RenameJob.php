<?php
/**
 * Background job renaming up to 20 attachments.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Rename;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * A bulk rename: a fixed list of up to MAX_ITEMS attachment IDs with their
 * requested names, renamed one by one by MediaRenamer in WP-Cron batches,
 * for the user who started it (permissions are checked when each item is
 * processed). One job at a time; each has an ID; cancel stops it before the
 * next item. Every item's outcome is recorded with codes only.
 */
class RenameJob {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Option holding the job.
	 *
	 * @var string
	 */
	const STATE_OPTION = 'tsmlt_rename_job';

	/**
	 * WP-Cron hook processing a batch.
	 *
	 * @var string
	 */
	const TICK_HOOK = 'tsmlt_rename_tick';

	/**
	 * Most items in one job.
	 *
	 * @var int
	 */
	const MAX_ITEMS = 20;

	/**
	 * Items per batch.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 5;

	/**
	 * Seconds after which a running job without a scheduled tick or any
	 * progress counts as stalled and may be replaced.
	 *
	 * @var int
	 */
	const STALE_AFTER = 900;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Default job shape.
	 *
	 * @return array
	 */
	private function default_state(): array {
		return [
			'job_id'       => '',
			'user_id'      => 0,
			'status'       => 'idle',
			'queue'        => [],
			'total'        => 0,
			'processed'    => 0,
			'results'      => [],
			'started_at'   => 0,
			'updated_at'   => 0,
			'cancelled_at' => 0,
		];
	}

	/**
	 * The stored job, read past the object cache.
	 *
	 * @return array
	 */
	public function get_state(): array {
		wp_cache_delete( self::STATE_OPTION, 'options' );
		$stored = get_option( self::STATE_OPTION, [] );
		return array_merge( $this->default_state(), is_array( $stored ) ? $stored : [] );
	}

	/**
	 * Store the job.
	 *
	 * @param array $state State.
	 *
	 * @return void
	 */
	private function save_state( array $state ): void {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * Whether a running job stalled (no tick scheduled, no progress for
	 * STALE_AFTER seconds).
	 *
	 * @param array $state State.
	 *
	 * @return bool
	 */
	public function is_stale( array $state ): bool {
		return 'running' === $state['status']
			&& ! wp_next_scheduled( self::TICK_HOOK )
			&& ( time() - (int) $state['updated_at'] ) > self::STALE_AFTER;
	}

	/**
	 * Whether a job is running and not stalled.
	 *
	 * @return bool
	 */
	public function has_active_job(): bool {
		$state = $this->get_state();
		return 'running' === $state['status'] && ! $this->is_stale( $state );
	}

	/**
	 * Start a job for the current user.
	 *
	 * @param array<int, string> $items Attachment ID => requested name, 1 to MAX_ITEMS.
	 *
	 * @return array|WP_Error Progress.
	 */
	public function start( array $items ) {
		if ( $this->has_active_job() ) {
			return new WP_Error( 'tsmlt_rename_running', __( 'A bulk rename is already running.', 'media-library-tools' ) );
		}
		$queue = [];
		foreach ( $items as $id => $name ) {
			if ( absint( $id ) > 0 && is_string( $name ) && '' !== trim( $name ) ) {
				$queue[] = [
					'id'   => absint( $id ),
					'name' => $name,
				];
			}
		}
		if ( [] === $queue || count( $queue ) > self::MAX_ITEMS || count( $queue ) !== count( $items ) ) {
			return new WP_Error( 'tsmlt_rename_invalid', __( 'A bulk rename takes 1 to 20 attachments, each with a name.', 'media-library-tools' ) );
		}
		wp_clear_scheduled_hook( self::TICK_HOOK );
		$now = time();
		$this->save_state(
			array_merge(
				$this->default_state(),
				[
					'job_id'     => 'tsmlt_rename_' . bin2hex( random_bytes( 8 ) ),
					'user_id'    => get_current_user_id(),
					'status'     => 'running',
					'queue'      => $queue,
					'total'      => count( $queue ),
					'started_at' => $now,
					'updated_at' => $now,
				]
			)
		);
		wp_schedule_single_event( $now + 1, self::TICK_HOOK );
		return $this->get_progress();
	}

	/**
	 * Progress: the job without its queue or owner, with per-item results.
	 *
	 * @return array
	 */
	public function get_progress(): array {
		$state = $this->get_state();
		$stale = $this->is_stale( $state );
		unset( $state['queue'], $state['user_id'] );
		$state['remaining']      = max( 0, (int) $state['total'] - (int) $state['processed'] );
		$state['tick_scheduled'] = (bool) wp_next_scheduled( self::TICK_HOOK );
		$state['stale']          = $stale;
		return $state;
	}

	/**
	 * Cancel the running job with this ID; finished jobs are left as they are.
	 *
	 * @param string $job_id Job ID.
	 *
	 * @return array|WP_Error Progress.
	 */
	public function cancel( string $job_id ) {
		$state = $this->get_state();
		if ( '' === $job_id || $job_id !== $state['job_id'] ) {
			return new WP_Error( 'tsmlt_rename_job_mismatch', __( 'That is not the current bulk rename.', 'media-library-tools' ) );
		}
		if ( 'running' === $state['status'] ) {
			wp_clear_scheduled_hook( self::TICK_HOOK );
			$state['status']       = 'cancelled';
			$state['queue']        = [];
			$state['cancelled_at'] = time();
			$state['updated_at']   = time();
			$this->save_state( $state );
		}
		return $this->get_progress();
	}

	/**
	 * Rename the next batch. Items are taken off the queue before they are
	 * processed; the job is re-read before each item, and a cancelled or
	 * replaced job stops the batch.
	 *
	 * @return array Progress.
	 */
	public function run_batch(): array {
		$state = $this->get_state();
		if ( 'running' !== $state['status'] ) {
			return $this->get_progress();
		}
		$job_id              = (string) $state['job_id'];
		$batch               = array_splice( $state['queue'], 0, self::BATCH_SIZE );
		$state['updated_at'] = time();
		$this->save_state( $state );

		$results = [];
		foreach ( $batch as $item ) {
			$now = $this->get_state();
			if ( $now['job_id'] !== $job_id || 'running' !== $now['status'] ) {
				break;
			}
			$results[] = self::safe_result( MediaRenamer::rename( (int) $item['id'], (string) $item['name'], (int) $state['user_id'] ) );
		}

		$fresh = $this->get_state();
		if ( $fresh['job_id'] !== $job_id ) {
			return $this->get_progress();
		}
		$fresh['results']    = array_merge( (array) $fresh['results'], $results );
		$fresh['processed']  = (int) $fresh['processed'] + count( $results );
		$fresh['updated_at'] = time();
		if ( 'running' === $fresh['status'] && [] === $fresh['queue'] ) {
			$fresh['status'] = 'done';
		}
		$this->save_state( $fresh );
		return $this->get_progress();
	}

	/**
	 * Background tick: one batch, then the next tick while running.
	 *
	 * @return void
	 */
	public static function run_tick(): void {
		$self = self::instance();
		if ( 'running' !== $self->get_state()['status'] ) {
			return;
		}
		$self->run_batch();
		if ( 'running' === $self->get_state()['status'] && ! wp_next_scheduled( self::TICK_HOOK ) ) {
			wp_schedule_single_event( time() + 1, self::TICK_HOOK );
		}
	}

	/**
	 * An item's outcome as stored: codes, counts and the new file name only.
	 *
	 * @param array $result MediaRenamer result.
	 *
	 * @return array
	 */
	private static function safe_result( array $result ): array {
		return [
			'id'             => (int) $result['id'],
			'status'         => (string) $result['status'],
			'code'           => (string) $result['code'],
			'file'           => (string) $result['file'],
			'sizes'          => (string) $result['sizes'],
			'sizes_code'     => (string) $result['sizes_code'],
			'content_posts'  => array_map( 'intval', (array) $result['content']['posts'] ),
			'content_errors' => (int) $result['content']['errors'],
		];
	}
}
