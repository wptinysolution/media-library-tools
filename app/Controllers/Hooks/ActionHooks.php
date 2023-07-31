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
class ActionHooks {

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

		add_action( 'manage_media_custom_column', [ $this, 'display_column_value' ], 10, 2 );
		add_action( 'add_attachment', [ $this, 'add_image_info_to' ] );

		// Hook the function to a cron job
		add_action( 'init', [ $this, 'schedule_directory_cron_job' ] );
		add_action( 'tsmlt_upload_dir_scan', [ $this, 'get_directory_list_cron_job' ] );

		// Rabbis Cron Job.
		add_action( 'init', [ $this, 'schedule_rabbis_file_cron_job' ] );
		add_action( 'tsmlt_upload_inner_file_scan', [ $this, 'scan_upload_rabbis_file_cron_job' ] );

	}

	/***
	 * @param $mimes
	 *
	 * @return mixed
	 */
	public function add_image_info_to( $post_id ) {
		$options     = Fns::get_options();
		$image_title = get_the_title( $post_id );

		if ( ! empty( $options['default_alt_text'] ) && 'image_name_to_alt' === $options['default_alt_text'] ) {
			update_post_meta( $post_id, '_wp_attachment_image_alt', $image_title );
		} else if ( ! empty( $options['media_default_alt'] ) && 'custom_text_to_alt' === $options['default_alt_text'] ) {
			update_post_meta( $post_id, '_wp_attachment_image_alt', $options['media_default_alt'] );
		}

		$image_meta = [];

		if ( ! empty( $options['default_caption_text'] ) && 'image_name_to_caption' === $options['default_caption_text'] ) {
			$image_meta['post_excerpt'] = $image_title;
		} else if ( ! empty( $options['media_default_caption'] ) && 'custom_text_to_caption' === $options['default_caption_text'] ) {
			$image_meta['post_excerpt'] = $options['media_default_caption'];
		}

		if ( ! empty( $options['default_desc_text'] ) && 'image_name_to_desc' === $options['default_desc_text'] ) {
			$image_meta['post_content'] = $image_title;
		} else if ( ! empty( $options['media_default_desc'] ) && 'custom_text_to_desc' === $options['default_desc_text'] ) {
			$image_meta['post_content'] = $options['media_default_desc'];
		}

		if ( ! empty( $image_meta ) ) {
			$image_meta['ID'] = $post_id;
			wp_update_post( $image_meta );
		}
	}

	/**
	 * @param $column
	 * @param $post_id
	 *
	 * @return void
	 */
	public function display_column_value( $column, $post_id ) {
		$image = Fns::wp_get_attachment( $post_id );
		switch ( $column ) {
			case 'alt':
				echo esc_html( wp_strip_all_tags( $image['alt'] ) );
				break;
			case 'caption':
				echo esc_html( $image['caption'] );
				break;
			case 'description':
				echo esc_html( $image['description'] );
				break;
			case 'category':
				$taxonomy_object = get_taxonomy( tsmlt()->category );

				if ( $terms = get_the_terms( $post_id, tsmlt()->category ) ) {
					$out = array();
					foreach ( $terms as $t ) {
						$posts_in_term_qv              = array();
						$posts_in_term_qv['post_type'] = get_post_type( $post_id );

						if ( $taxonomy_object->query_var ) {
							$posts_in_term_qv[ $taxonomy_object->query_var ] = $t->slug;
						} else {
							$posts_in_term_qv['taxonomy'] = tsmlt()->category;
							$posts_in_term_qv['term']     = $t->slug;
						}

						$out[] = sprintf( '<a href="%s">%s</a>',
							esc_url( add_query_arg( $posts_in_term_qv, 'upload.php' ) ),
							esc_html( sanitize_term_field( 'name', $t->name, $t->term_id, tsmlt()->category, 'display' ) )
						);
					}

					/* translators: used between list items, there is a space after the comma */
					echo join( __( ', ' ), $out );
				};
				break;
			default:
				break;
		}
	}

	/**
	 * Schedule the cron job
	 * @return void
	 */
	public function schedule_rabbis_file_cron_job() {
		$event_hook = 'tsmlt_upload_inner_file_scan';
		// Check if the cron job is already scheduled
		$is_scheduled = wp_next_scheduled( $event_hook );
		if ( $is_scheduled ) {
			return; // Cron job is already scheduled, no need to proceed further
		}
		// Clear any existing scheduled events with the same hook
		wp_clear_scheduled_hook( $event_hook );
		$schedule = 'daily';
		if( Fns::isLocalhost() ){
			$schedule = 'everyminute';
		}
		// Schedule the cron job to run every minute
		wp_schedule_event( time(), $schedule, $event_hook );
	}

	/**
	 * Function to scan the upload directory and search for files.
	 *
	 * @param WP_Filesystem|WP_Filesystem_Direct $filesystem The WP_Filesystem instance.
	 * @param string $directory The directory to scan.
	 * @param int $offset The offset to start scanning from.
	 *
	 * @return array The list of found files.
	 */
	public function scan_upload_directory( $directory ) {
		if ( ! $directory ) {
			return [];
		}
		$filesystem = Fns::get_wp_filesystem_instance(); // Get the proper WP_Filesystem instance
		// Ensure the directory exists before scanning.
		if ( ! $filesystem->is_dir( $directory ) ) {
			return [];
		}
		$scanned_files = [];
		$files         = $filesystem->dirlist( $directory );
		if ( ! is_array( $files ) ) {
			return [];
		}
		foreach ( $files as $file ) {
			$file_path = trailingslashit( $directory ) . $file['name'];
			if ( $filesystem->is_dir( $file_path ) ) {
				continue;
			}
			$scanned_files[] = $file_path;
		}

		return $scanned_files;
	}

	/**
	 * Function to scan the upload directory and search for files
	 */
	public function scan_upload_rabbis_file_cron_job() {

		$dis_list = get_option( 'tsmlt_get_directory_list', [] );
		if ( ! count( $dis_list ) ) {
			return;
		}
		$directory = '';
		foreach ( $dis_list as $key => $item ) {
			if ( absint( $item['total_items'] ) && ( absint( $item['total_items'] ) <= absint( $item['counted'] ) ) ) {
				continue;
			}
			if ( 'available' !== ( $item['status'] ?? 'available' ) ) {
				continue;
			}
			$directory = $key;
		}

		$found_files = $this->scan_upload_directory( $directory ); // Scan the directory and search for files
		if ( ! count( $found_files ) ) {
			return;
		}

		$dis_list[ $directory ]['total_items'] = count( $found_files );

		$last_processed_offset = absint( $dis_list[ $directory ]['counted'] );

		// Skip the files until the offset is reached
		$files = array_slice( $found_files, $last_processed_offset, 20 );

		$found_files_count = count( $files );

		$dis_list[ $directory ]['counted'] = $last_processed_offset + $found_files_count;

		if ( ! $found_files_count > 0 ) {
			return;
		}

		global $wpdb;

		foreach ( $found_files as $file_path ) {
			$search_string = '';
			$str           = explode( 'wp-content/uploads/', $file_path );
			if ( is_array( $str ) && ! empty( $str[1] ) ) {
				$search_string = $str[1];
			}
			$attachment_id = 0;
			if ( $search_string ) {
				$attachment_id = attachment_url_to_postid( $search_string );
			}
			if ( ! $attachment_id ) {
				$search_basename = basename( $search_string );
				$attachment_id   = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta}
				            WHERE meta_key = '_wp_attachment_metadata'
				            AND meta_value LIKE %s",
						'%' . $wpdb->esc_like( $search_basename ) . '%'
					)
				);
			}

			if ( absint( $attachment_id ) ) {
				continue;
			}

			$cache_key  = "tsmlt_existing_row_" . sanitize_title( $file_path );
			$table_name = $wpdb->prefix . 'tsmlt_unlisted_file';
			// Check if the file_path already exists in the table using cached data
			$existing_row = wp_cache_get( $cache_key );
			if ( $existing_row === false ) {
				$existing_row = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table_name WHERE file_path = %s", $search_string ) );
				// Cache the query result
				if ( $existing_row ) {
					continue;
				}
				$save_data = array(
					'file_path'     => $search_string,
					'attachment_id' => 0,
					'file_type'     => pathinfo( $search_string, PATHINFO_EXTENSION ),
					'meta_data'     => serialize( [] ),
				);
				$wpdb->insert( $table_name, $save_data );

				wp_cache_set( $cache_key, $existing_row );
			}
		}
		update_option( 'tsmlt_get_directory_list', $dis_list );
	}

	/**
	 * Schedule the cron job
	 * @return void
	 * Schedule the cron job
	 */
	public function schedule_directory_cron_job() {
		$event_hook = 'tsmlt_upload_dir_scan';
		// Check if the cron job is already scheduled
		$is_scheduled = wp_next_scheduled( $event_hook );

		if ( $is_scheduled ) {
			return; // Cron job is already scheduled, no need to proceed further
		}
		// Clear any existing scheduled events with the same hook
		wp_clear_scheduled_hook( $event_hook );
		// Schedule the cron job to run every minute
		$schedule = 'monthly';
		if( Fns::isLocalhost() ){
			$schedule = 'daily';
		}

		wp_schedule_event( time(), $schedule, $event_hook );
		//error_log( print_r( 'wp_schedule_event', true ) . "\n\n", 3, __DIR__ . '/the_log.txt' );
	}

	/**
	 * Function to retrieve the list of directories with paths from a given directory.
	 *
	 * @param WP_Filesystem|WP_Filesystem_Direct $filesystem The WP_Filesystem instance.
	 * @param string $directory The directory to scan.
	 *
	 * @return array The list of directories with their paths.
	 */
	public function scan_directory_list( $directory ) {
		if ( ! $directory || ! is_string( $directory ) ) {
			return [];
		}
		$filesystem  = Fns::get_wp_filesystem_instance(); // Get the proper WP_Filesystem instance
		$directories = [];
		// Ensure the directory exists before scanning
		if ( ! $filesystem->is_dir( $directory ) ) {
			return [];
		}

		$files = $filesystem->dirlist( $directory );
		foreach ( $files as $file ) {
			$file_path = trailingslashit( $directory ) . $file['name'];

			if ( $filesystem->is_dir( $file_path ) ) {
				$subdirectories = $this->scan_directory_list( $file_path );
				$directories    = array_merge( $directories, $subdirectories );
			} else {
				// Extract the directory path from the file path
				$dir_path = dirname( $file_path );
				// Add the directory to the list if it doesn't exist
				if ( ! in_array( $dir_path, $directories ) ) {
					$directories[ $dir_path ] = [
						'total_items' => 0,
						'counted'     => 0,
						'status'      => 'available'
					];
				}
			}
		}

		return $directories;
	}

	public function get_directory_list_cron_job() {

		$cache_key      = 'get_directory_list';
		$subdirectories = wp_cache_get( $cache_key );

		if ( ! $subdirectories ) {
			$upload_dir     = wp_upload_dir(); // Get the upload directory path
			$directory      = $upload_dir['basedir']; // Get the base directory path
			$subdirectories = $this->scan_directory_list( $directory );
			wp_cache_set( $cache_key, $subdirectories );
		}

		$dir_status     = get_option( 'tsmlt_get_directory_list', [] );
		$subdirectories = wp_parse_args( $dir_status, $subdirectories );
		update_option( 'tsmlt_get_directory_list', $subdirectories );

	}


}
