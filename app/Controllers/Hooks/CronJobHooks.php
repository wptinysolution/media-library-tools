<?php
/**
 * Main ActionHooks class.
 *
 * @package TinySolutions\WM
 */

namespace TinySolutions\mlt\Controllers\Hooks;

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
		// Hook the function to a cron job.
		add_action( 'init', [ $this, 'schedule_directory_cron_job' ] );
		add_action( 'tsmlt_upload_dir_scan', [ Fns::class, 'get_directory_list_cron_job' ] );
		// Rubbish Cron Job.
		add_action( 'init', [ $this, 'schedule_rubbish_file_cron_job' ] );
		add_action( 'tsmlt_upload_inner_file_scan', [ $this, 'scan_rubbish_file_cron_job' ] );
	}

	/**
	 * Schedule the cron job
	 *
	 * @return void
	 */
	public function schedule_rubbish_file_cron_job() {
		$event_hook = 'tsmlt_upload_inner_file_scan';
		// Check if the cron job is already scheduled.
		$is_scheduled = wp_next_scheduled( $event_hook );
		if ( $is_scheduled ) {
			return; // Cron job is already scheduled, no need to proceed further.
		}
		// Clear any existing scheduled events with the same hook.
		wp_clear_scheduled_hook( $event_hook );
		$schedule = 'daily';
		// Schedule the cron job to run every minute.
		wp_schedule_event( time(), $schedule, $event_hook );
	}
	
	/**
	 * Function to scan the upload directory and search for files
	 */
	public function scan_rubbish_file_cron_job() {
		Fns::scan_rubbish_file_cron_job();
	}
	
	/**
	 * Schedule the cron job
	 *
	 * @return void
	 * Schedule the cron job
	 */
	public function schedule_directory_cron_job() {
		$event_hook = 'tsmlt_upload_dir_scan';
		// Check if the cron job is already scheduled.
		$is_scheduled = wp_next_scheduled( $event_hook );
		
		if ( $is_scheduled ) {
			return; // Cron job is already scheduled, no need to proceed further.
		}
		// Clear any existing scheduled events with the same hook.
		wp_clear_scheduled_hook( $event_hook );
		wp_schedule_event( time(), 'weekly', $event_hook );
	}
	
}
