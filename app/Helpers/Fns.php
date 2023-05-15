<?php
/**
 * Fns Helpers class
 *
 * @package  TinySolutions\WM
 */

namespace TinySolutions\mlt\Helpers;

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
	 * @param $post_type
	 * @param $post_status
	 * @param $group
	 * @param $additional_query
	 *
	 * @return false|mixed|string|null
	 */
	public static function get_post_count( $post_type, $post_status = 'publish', $group = 'default', $join = null, $additional_query = null ) {
		global $wpdb;
		$count_key = 'post_count_' . $post_type . '_' . $post_status;
		$count     = wp_cache_get( $count_key, $group );
		if ( false === $count ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT id) FROM $wpdb->posts AS p  
                    $join 
                    WHERE post_type = '%1\$s' AND post_status = '%2\$s'  
                    $additional_query ",
					$post_type,
					$post_status
				)
			);
			wp_cache_set( $count_key, $count, $group );
		}

		return $count;
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
	 * @param init $attachment_id image id.
	 *
	 * @return array
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

		$filebasename = basename( $metadata['file'], '.' . $fileextension );

		if ( $filebasename == basename( $new_file_name, '.' . $fileextension ) ) {
			return $updated;
		}

		$path_being_saved_to = dirname( $file_path );

		$unique_filename = $path_being_saved_to . '/' . wp_unique_filename( $path_being_saved_to, $new_file_name );

		// error_log( print_r( $unique_filename , true) . "\n\n", 3, __DIR__.'/unique_filenamelogg.txt');

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
			update_attached_file( $attachment_id, str_replace( basename( $file_path ), $new_file_name, $file_path ) );
			//error_log( print_r( str_replace( basename( $file_path ) , $new_file_name, $file_path) , true) . "\n\n", 3, __DIR__.'/unique_filenamelogg.txt');
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
			'media_per_page'        => 20,
			'media_table_column'    => [ 'ID', 'Image', 'Title', 'Alt', 'Caption', 'Category' ],
			'media_default_alt'     => '',
			'media_default_caption' => '',
			'media_default_desc'    => '',
			'default_alt_text'      => "none",
			'default_caption_text'  => "none",
			'default_desc_text'     => "none",
			'others_file_support'   => [],
		);
		$options  = get_option( 'tsmlt_settings' );

		return wp_parse_args( $options, $defaults );
	}


}
