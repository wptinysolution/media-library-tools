<?php

namespace TinySolutions\mlt\Controllers;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
use TinySolutions\mlt\Helpers\Fns;

/**
 * Installation class.
 */
class Installation {
	/**
	 * @return void
	 */
	public static function activate() {
		$current_version = get_option( 'tsmlt_plugin_version' );

		if ( ! $current_version ) {
			$tsmlt_media                   = get_option( 'tsmlt_settings', [] );
			$tsmlt_media['media_per_page'] = absint( $tsmlt_media['media_per_page'] ?? 20 );
			if ( empty( $tsmlt_media['media_table_column'] ) ) {
				$tsmlt_media['media_table_column'] = [
					'Image',
					'Parents',
					'Title',
					'Alt',
					'Caption',
					'Description',
				];
			}
			// Create tables.
			self::create_tables();
			update_option( 'tsmlt_settings', $tsmlt_media );
			update_option( 'tsmlt_plugin_version', TSMLT_VERSION );
			update_option( 'tsmlt_plugin_activation_time', strtotime( 'now' ) );
		}

		// Existing installs upgrading — create all tables if not exist.
		if ( $current_version && version_compare( $current_version, TSMLT_VERSION, '<' ) ) {
			self::create_tables();
			update_option( 'tsmlt_plugin_version', TSMLT_VERSION );
		}
	}

	/**
	 * Create missing tables if the stored version doesn't match current version.
	 *
	 * @return void
	 */
	public static function maybe_create_tables() {
		$current_version = get_option( 'tsmlt_plugin_version' );
		if ( $current_version && version_compare( $current_version, TSMLT_VERSION, '>=' ) ) {
			return;
		}
		self::create_tables();
		update_option( 'tsmlt_plugin_version', TSMLT_VERSION );
	}

	/**
	 * @return void
	 */
	public static function deactivation() {
		Fns::clear_scheduled_events();
	}

	/**
	 * @return void
	 */
	public static function create_tables() {
		Fns::DB()->create( 'tsmlt_unlisted_file' )
			->column( 'id' )->int()->autoIncrement()->primary()
			->column( 'attachment_id' )->int()->default( 0 )
			->column( 'file_path' )->string( 255 )->required()
			->column( 'file_type' )->string( 50 )
			->column( 'status' )->string( 50 )->default( 'show' )
			->column( 'meta_data' )->string( 50 )
			->execute();

		self::create_duplicate_table();
	}

	/**
	 * Create the duplicate file detection table.
	 *
	 * @return void
	 */
	public static function create_duplicate_table() {
		Fns::DB()->create( 'tsmlt_duplicate_file' )
			->column( 'id' )->int()->autoIncrement()->primary()
			->column( 'attachment_id' )->int()->required()
			->column( 'file_hash' )->string( 32 )->required()
			->column( 'file_size' )->bigInt()->default( 0 )
			->column( 'file_path' )->string( 255 )->required()
			->execute();
	}

	/**
	 * Uninstall callback — wipes plugin data when `delete_data_on_uninstall` is on.
	 *
	 * Registered via `register_uninstall_hook()` in the main plugin file. Must be
	 * static: WordPress calls this in a fresh process with no plugin instance.
	 *
	 * @return void
	 */
	public static function uninstall() {
		$settings = get_option( 'tsmlt_settings', [] );
		if ( empty( $settings['delete_data_on_uninstall'] ) ) {
			return;
		}

		global $wpdb;

		// 1. Cron events — clear list-tracked + dynamic-arg hooks.
		$schedule = get_option( 'tsmlt_cron_schedule', [] );
		if ( is_array( $schedule ) ) {
			foreach ( $schedule as $hook ) {
				wp_clear_scheduled_hook( $hook );
			}
		}
		wp_unschedule_hook( 'tsmlt_scan_post_usage' );
		wp_unschedule_hook( 'tsmlt_used_where_scan_tick' );

		// 2. Tables — drop both feature tables.
		$tables = [
			$wpdb->prefix . 'tsmlt_unlisted_file',
			$wpdb->prefix . 'tsmlt_duplicate_file',
		];
		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		// 3. Post meta — wipe all plugin-owned attachment/post meta keys.
		$meta_keys = [
			'_tsmlt_image_usages',
			'_tsmlt_usage_tracked',
			'_tsmlt_permalink_fp',
			'_tsmlt_exif_camera',
			'_tsmlt_exif_gps_lat',
			'_tsmlt_exif_gps_lng',
			'_tsmlt_exif_logs',
			'_tsmlt_exif_meta',
		];
		foreach ( $meta_keys as $meta_key ) {
			delete_post_meta_by_key( $meta_key );
		}

		// 4. Options — enumerated keys (safe through WP API, fires hooks, busts cache).
		$options = [
			'tsmlt_settings',
			'tsmlt_plugin_version',
			'tsmlt_plugin_activation_time',
			'tsmlt_cron_schedule',
			'tsmlt_get_directory_list',
			'tsmlt_used_where_scan_status',
			'tsmlt_exif_scan_status',
			'tsmlt_exif_strip_status',
			'tsmlt_thumbnail_cron_offset',
			'tsmlt_spare_me',
			'tsmlt_rated',
			'tsmlt_remind_me',
		];
		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// 5. Transients — wildcard delete (no WP core API for "delete all by
		//    prefix"; raw query is the only option). Covers tsmlt_dir_scan_*,
		//    tsmlt_tables_checked_*, tsmlt_date_query_*, plus pro transients.
		$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE '\_transient\_tsmlt\_%' OR option_name LIKE '\_transient\_timeout\_tsmlt\_%' OR option_name LIKE '\_transient\_tsmltpro\_%' OR option_name LIKE '\_transient\_timeout\_tsmltpro\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
}
