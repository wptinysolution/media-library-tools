<?php
/**
 * Main ActionHooks class.
 *
 * @package TinySolutions\WM
 */

namespace TinySolutions\mlt\Controllers\Hooks;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit();

/**
 * Main ActionHooks class.
 */
class CronJobHooks {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Init Hooks.
	 *
	 * @return void
	 */
	private function __construct() {
		// Add custom interval for 5 times a day.
		add_filter( 'cron_schedules', [ $this, 'add_custom_cron_schedules' ] );
		// Hook the function to a cron job.
		add_action( 'init', [ $this, 'schedule_directory_cron_job' ] );
		add_action( 'tsmlt_upload_dir_scan', [ Fns::class, 'get_directory_list_cron_job' ] );
		// Rubbish Cron Job.
		add_action( 'init', [ $this, 'schedule_rubbish_file_cron_job' ] );
		add_action( 'tsmlt_upload_inner_file_scan', [ $this, 'scan_rubbish_file_cron_job' ] );
		// Thumbnail Cron Job (5 times a day).
		add_action( 'init', [ $this, 'schedule_thumbnail_cron_job' ] );
		add_action( 'tsmlt_five_times_thumbnail_event', [ $this, 'execute_thumbnail_cron_job' ] );
	}

	/**
	 * Add custom cron intervals.
	 *
	 * @param array $schedules The existing cron schedules.
	 * @return array The modified cron schedules.
	 */
	public function add_custom_cron_schedules( $schedules ) {
		$schedules['every_six_hours'] = [
			'interval' => 6 * 3600,
			'display'  => __( 'Every 6 Hours', 'media-library-tools' ),
		];
		return $schedules;
	}

	/**
	 * Schedule the thumbnail cron job to run 5 times a day.
	 *
	 * @return void
	 */
	public function schedule_thumbnail_cron_job() {
		$event_hook = 'tsmlt_five_times_thumbnail_event';
		if ( ! wp_next_scheduled( $event_hook ) ) {
			wp_clear_scheduled_hook( $event_hook );
			wp_schedule_event( time(), 'every_six_hours', $event_hook );
			Fns::add_to_scheduled_hook_list( $event_hook );
		}
	}

	/**
	 * Execute the thumbnail cron job in batches of 100.
	 *
	 * @return void
	 */
	public function execute_thumbnail_cron_job() {
		 $batch_size        = 100;
		 $offset_option_key = 'tsmlt_thumbnail_cron_offset';
		$offset             = (int) get_option( $offset_option_key, 0 );
		$args               = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $batch_size,
			'post_parent'    => 0,
			'offset'         => $offset,
			'fields'         => 'ids',
			'order'          => 'DESC',
			'orderby'        => 'ID',
		];
		$media_files        = get_posts( $args );
		if ( ! empty( $media_files ) ) {
			foreach ( $media_files as $media_id ) {
				delete_post_meta( $media_id, '_parent_post_found' );
				Fns::set_thumbnail_parent_id( $media_id );
			}
			// Update offset for the next batch.
			$offset += $batch_size;
			update_option( $offset_option_key, $offset );
		} else {
			// Reset the offset when no more media files are found.
			update_option( $offset_option_key, 0 );
		}
	}

	/**
	 * Schedule the rubbish file cron job (daily).
	 *
	 * @return void
	 */
	public function schedule_rubbish_file_cron_job() {
		$file_scan_event_hook = 'tsmlt_upload_inner_file_scan';
		$is_scheduled         = wp_next_scheduled( $file_scan_event_hook );
		if ( ! $is_scheduled ) {
			wp_clear_scheduled_hook( $file_scan_event_hook );
			$schedule = 'daily';
			wp_schedule_event( time(), $schedule, $file_scan_event_hook );
			Fns::add_to_scheduled_hook_list( $file_scan_event_hook );
		}
	}

	/**
	 * Execute the rubbish file cron job.
	 *
	 * @return void
	 */
	public function scan_rubbish_file_cron_job() {
		Fns::scan_rubbish_file_cron_job();
	}

	/**
	 * Schedule the directory scan cron job (weekly).
	 *
	 * @return void
	 */
	public function schedule_directory_cron_job() {
		$dir_scan_event_hook = 'tsmlt_upload_dir_scan';
		$is_scheduled        = wp_next_scheduled( $dir_scan_event_hook );
		if ( ! $is_scheduled ) {
			wp_clear_scheduled_hook( $dir_scan_event_hook );
			wp_schedule_event( time(), 'weekly', $dir_scan_event_hook );
			Fns::add_to_scheduled_hook_list( $dir_scan_event_hook );
		}
	}
}
