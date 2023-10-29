<?php
/**
 * Fns Helpers class
 *
 * @package  TinySolutions\WM
 */

namespace TinySolutions\mlt\Helpers;

use WP_Filesystem;
use WP_Filesystem_Direct;
use WP_Filesystem_Base;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Fns class
 */
class Fns {

	/**
	 * @param $plugin_file_path
	 *
	 * @return bool
	 */
	public static function is_plugins_installed( $plugin_file_path = null ) {
		$installed_plugins_list = get_plugins();

		return isset( $installed_plugins_list[ $plugin_file_path ] );
	}

	/**
	 * Image attachment details
	 *
	 * @param init $attachment_id image id.
	 *
	 * @return array
	 */
	public static function wp_get_attachment( $attachment_id ) {
		$attachment = get_post( $attachment_id );

		return array(
			'alt'         => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
			'caption'     => $attachment->post_excerpt,
			'description' => $attachment->post_content,
			'title'       => $attachment->post_title,
		);
	}

	/**
	 * Image attachment details
	 *
	 * @param int $attachment_id image id.
	 *
	 * @return bool
	 */
	public static function wp_rename_attachment( $attachment_id, $new_file_name = '' ) {
		$updated = false;

		$new_file_name = sanitize_file_name( $new_file_name );

		if ( empty( $new_file_name ) || ! $attachment_id ) {
			return $updated;
		}

		// Get the current file path and name
		$file_path = get_attached_file( $attachment_id );

		if ( ! file_exists( $file_path ) ) {
			return $updated;
		}

		// Get the current metadata for the media file
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( empty( $metadata['file'] ) ) {
			$attac            = get_attached_file( $attachment_id );
			$metadata['file'] = basename( $attac );
		}

		$fileextension = pathinfo( $metadata['file'], PATHINFO_EXTENSION );
		$filebasename  = basename( $metadata['file'], '.' . $fileextension );

		$new_file_name = $new_file_name . '.' . $fileextension;
		if ( $filebasename == basename( $new_file_name, '.' . $fileextension ) ) {
			return $updated;
		}

		$path_being_saved_to = dirname( $file_path );

		$unique_filename = $path_being_saved_to . '/' . wp_unique_filename( $path_being_saved_to, $new_file_name );

		// Rename the file on the server
		$renamed = rename( $file_path, $unique_filename );

		$new_file_name = basename( $unique_filename );

		$new_filebasename = basename( $new_file_name, '.' . $fileextension );
		// If the file was successfully renamed, update the metadata for each size

		if ( $renamed ) {
			wp_update_post(
				array(
					'ID'        => $attachment_id,
					'post_name' => $new_filebasename
				)
			);
			// Update the metadata with the new file name
			$metadata['file'] = $unique_filename;
			// Loop through each size and rename the file
			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size => $fileinfo ) {
					$old_file_path = dirname( $file_path ) . '/' . $fileinfo['file'];
					if ( ! file_exists( $old_file_path ) ) {
						continue;
					}
					$new_file_path = dirname( $file_path ) . '/' . str_replace( $filebasename, $new_filebasename, $fileinfo['file'] );
					$renamed_size  = rename( $old_file_path, $new_file_path );

					if ( $renamed_size ) {
						$metadata['sizes'][ $size ]['file'] = str_replace( $filebasename, $new_filebasename, $fileinfo['file'] );
					}

				}
			}
			$new_file = str_replace( basename( $file_path ), $new_file_name, $file_path );
			update_attached_file( $attachment_id, $new_file );

			$metadata['file'] = _wp_relative_upload_path( $new_file );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$updated = self::permalink_to_post_guid( $attachment_id );

		}

		return $renamed;
	}

	/**
	 * @param $post_id
	 *
	 * @return bool|int|\mysqli_result|resource|null
	 */
	public static function permalink_to_post_guid( $post_id ) {
		global $wpdb;
		$guid    = wp_get_attachment_url( $post_id );
		$updated = $wpdb->update( $wpdb->posts, [ 'guid' => $guid ], [ 'ID' => $post_id ] );
		clean_post_cache( $post_id );

		return $updated;
	}

	/**
	 * @return false|string
	 */
	public static function get_options() {
		$defaults = array(
			'media_per_page'         => 20,
			'media_table_column'     => [ 'ID', 'Image', 'Title', 'Alt', 'Caption', 'Category' ],
			'media_default_alt'      => '',
			'media_default_caption'  => '',
			'media_default_desc'     => '',
			'default_alt_text'       => "image_name_to_alt",
			'default_caption_text'   => "none",
			'default_desc_text'      => "none",
			'others_file_support'    => [],
			'enable_auto_rename'     => '',
			'media_auto_rename_text' => '',
		);
		$options  = get_option( 'tsmlt_settings', [] );

		return wp_parse_args( $options, $defaults );
	}

	/**
	 * @param $clauses
	 * @param $query
	 *
	 * @return mixed
	 */
	public static function custom_orderby_post_excerpt_content( $clauses, $query ) {
		global $wpdb;
		if ( 'post_excerpt' === $query->get( 'orderby' ) ) {
			$clauses['orderby'] = "$wpdb->posts.post_excerpt {$query->get( 'order' )}";
		} elseif ( 'post_content' === $query->get( 'orderby' ) ) {
			$clauses['orderby'] = "$wpdb->posts.post_content {$query->get( 'order' )}";
		}

		return $clauses;
	}

	/**
	 * Get the WP_Filesystem instance
	 *
	 * @return WP_Filesystem|WP_Filesystem_Direct The WP_Filesystem instance
	 */
	public static function get_wp_filesystem_instance() {
		global $wp_filesystem;
		// Initialize the WP filesystem
		if ( empty( $wp_filesystem ) ) {
			// Include the file.php for WP filesystem functions
			include_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		// Check if WP_Filesystem_Direct is already instantiated
		if ( $wp_filesystem instanceof WP_Filesystem_Base && $wp_filesystem instanceof WP_Filesystem_Direct ) {
			if ( method_exists( $wp_filesystem, 'request_filesystem_credentials' ) ) {
				$wp_filesystem = new WP_Filesystem_Direct( null );
			}
		}

		return $wp_filesystem;
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
	public static function scan_file_in_directory( $directory ) {
		if ( ! $directory ) {
			return [];
		}
		$filesystem = self::get_wp_filesystem_instance(); // Get the proper WP_Filesystem instance
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
	 * @param $directory
	 *
	 * @return bool|void
	 */
	public static function update_rubbish_file_to_database( $directory ){

		$found_files = self::scan_file_in_directory( $directory ); // Scan the directory and search for files
		if ( ! count( $found_files ) ) {
			return;
		}
		$dis_list     = get_option( 'tsmlt_get_directory_list', [] );

		$dis_list[ $directory ]['total_items'] = count( $found_files );

		$last_processed_offset = absint( $dis_list[ $directory ]['counted'] );

		// Skip the files until the offset is reached
		$files = array_slice( $found_files, $last_processed_offset, 50 );

		$found_files_count = count( $files );

		$dis_list[ $directory ]['counted'] = $last_processed_offset + $found_files_count;

		if ( ! $found_files_count > 0 ) {
			return;
		}

		global $wpdb;

		$upload_dir      = wp_upload_dir();
		$uploaddir       = $upload_dir['basedir'] ?? 'wp-content/uploads/';

		foreach ( $found_files as $file_path ) {
			$search_string = '';
			$str           = explode( $uploaddir.'/', $file_path );
			
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

			if ( absint( $attachment_id ) && get_post_type( $attachment_id ) ) {
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
		$updated = update_option( 'tsmlt_get_directory_list', $dis_list );
		return $updated;
	}

	/**
	 * @return void
	 */
	public static function get_directory_list_cron_job(  $isRescan = false ) {
		if( $isRescan ){
			update_option( 'tsmlt_get_directory_list', [] );
		}
		$cache_key      = 'get_directory_list';
		$subdirectories = wp_cache_get( $cache_key );
		if ( ! $subdirectories ) {
			$upload_dir     = wp_upload_dir(); // Get the upload directory path
			$directory      = $upload_dir['basedir']; // Get the base directory path
			$subdirectories = self::scan_directory_list( $directory );
			wp_cache_set( $cache_key, $subdirectories );
		}
		$dir_status     = get_option( 'tsmlt_get_directory_list', [] );

		$subdirectories = wp_parse_args( $dir_status, $subdirectories );

		update_option( 'tsmlt_get_directory_list', $subdirectories );

	}


	/**
	 * Function to retrieve the list of directories with paths from a given directory.
	 *
	 * @param WP_Filesystem|WP_Filesystem_Direct $filesystem The WP_Filesystem instance.
	 * @param string $directory The directory to scan.
	 *
	 * @return array The list of directories with their paths.
	 */
	public static function scan_directory_list( $directory ) {
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
				$subdirectories = self::scan_directory_list( $file_path );
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

	/**
	 * @param $attachment_id
	 *
	 * @return int|void
	 */
	public static function set_thumbnail_parent_id( $attachment_id ){

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return;
		}

		if( get_post_field('post_parent', $attachment_id) ){
			return;
		}

		global $wpdb;
		$meta_key = '_thumbnail_id';
		$query = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %d", $meta_key, $attachment_id );
		$parent_id = $wpdb->get_var($query);

		if( ! $parent_id ){
			return;
		}
		// Update the attachment's parent ID
		$attachment_data = array(
			'ID' => $attachment_id,
			'post_parent' => $parent_id,
		);
		// Update the attachment using wp_update_post
		wp_update_post($attachment_data);
		return $parent_id;
	}


}
