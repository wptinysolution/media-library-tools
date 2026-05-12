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
use TinySolutions\mlt\Modules\Rubbish\RubbishScanner;

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
		add_action( 'tsmlt_upload_dir_scan', [ RubbishScanner::class, 'get_directory_list_cron_job' ] );
		// Rubbish Cron Job.
		add_action( 'init', [ $this, 'schedule_rubbish_file_cron_job' ] );
		add_action( 'tsmlt_upload_inner_file_scan', [ $this, 'scan_rubbish_file_cron_job' ] );
		// Unschedule legacy thumbnail parent cron on existing installs — superseded by UsedWhereScanner.
		add_action( 'init', [ $this, 'unschedule_legacy_thumbnail_cron' ] );
	}

	/**
	 * Clear the obsolete `tsmlt_five_times_thumbnail_event` cron from prior installs.
	 *
	 * Parent-post detection is now handled by `UsedWhereScanner` (on-demand + on save).
	 *
	 * @return void
	 */
	public function unschedule_legacy_thumbnail_cron() {
		if ( wp_next_scheduled( 'tsmlt_five_times_thumbnail_event' ) ) {
			wp_clear_scheduled_hook( 'tsmlt_five_times_thumbnail_event' );
		}
		delete_option( 'tsmlt_thumbnail_cron_offset' );
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
		RubbishScanner::scan_rubbish_file_cron_job();
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
