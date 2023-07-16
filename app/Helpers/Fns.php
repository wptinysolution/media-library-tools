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
		} elseif ( 'post_content' === $query->get( 'orderby' ) ){
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


}
