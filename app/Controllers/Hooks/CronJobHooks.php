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
use TinySolutions\mlt\Traits\SingletonTrait;
use TinySolutions\mlt\Modules\Rubbish\RubbishScanner;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;
use TinySolutions\mlt\Modules\ExifData\ExifScanner;
use TinySolutions\mlt\Modules\Duplicate\DuplicateScanner;

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
		// Handler for user-initiated, self-chaining rubbish scan ticks (single events, not recurring).
		add_action( RubbishScanner::SCAN_TICK_HOOK, [ RubbishScanner::class, 'run_scan_tick' ] );
		// Handler for user-initiated, self-chaining regenerate-thumbnails ticks.
		add_action( RegenerateThumbnails::TICK_HOOK, [ RegenerateThumbnails::class, 'run_tick' ] );
		// Handler for user-initiated, self-chaining EXIF data scanner ticks.
		add_action( ExifScanner::TICK_HOOK, [ ExifScanner::class, 'run_tick' ] );
		// Handler for user-initiated, self-chaining duplicate file scanner ticks.
		add_action( DuplicateScanner::TICK_HOOK, [ DuplicateScanner::class, 'run_tick' ] );
		// Unschedule legacy recurring crons on existing installs — replaced by on-demand scans.
		add_action( 'init', [ $this, 'unschedule_legacy_crons' ] );
	}

	/**
	 * Clear obsolete recurring crons from prior installs.
	 *
	 * - `tsmlt_five_times_thumbnail_event` — replaced by `UsedWhereScanner`.
	 * - `tsmlt_upload_dir_scan` / `tsmlt_upload_inner_file_scan` — replaced by
	 *   the user-initiated rubbish scan that chains single events.
	 *
	 * @return void
	 */
	public function unschedule_legacy_crons() {
		$legacy_hooks = [
			'tsmlt_five_times_thumbnail_event',
			'tsmlt_upload_dir_scan',
			'tsmlt_upload_inner_file_scan',
		];
		foreach ( $legacy_hooks as $hook ) {
			if ( wp_next_scheduled( $hook ) ) {
				wp_clear_scheduled_hook( $hook );
			}
		}
		delete_option( 'tsmlt_thumbnail_cron_offset' );
	}
}
