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
}
