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
	 * @var array
	 */
	private static $cache                    = [];
	private static $useless_types_conditions = "
		post_status NOT IN ('inherit', 'trash', 'auto-draft')
		AND post_type NOT IN ('attachment', 'shop_order', 'shop_order_refund', 'nav_menu_item', 'revision', 'auto-draft', 'wphb_minify_group', 'customize_changeset', 'oembed_cache', 'nf_sub', 'jp_img_sitemap')
		AND post_type NOT LIKE 'dlssus_%'
		AND post_type NOT LIKE 'ml-slide%'
		AND post_type NOT LIKE '%acf-%'
		AND post_type NOT LIKE '%edd_%'
	";

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

		return [
			'alt'         => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
			'caption'     => $attachment->post_excerpt,
			'description' => $attachment->post_content,
			'title'       => $attachment->post_title,
		];
	}

	/**
	 * @param $element_id
	 * @param $old_filepath
	 * @param $new_filepath
	 * @param $undo
	 *
	 * @return void
	 */
	private static function wpml_update_translations( $attachment_id ) {
		if ( ! function_exists( 'icl_object_id' ) ) {
			return;
		}
		$args = [
			'element_id'   => $attachment_id,
			'element_type' => 'attachment',
		];
		$info = apply_filters( 'wpml_element_language_details', null, $args );
		if ( ! empty( $info->trid ) ) {
			$translations = apply_filters( 'wpml_get_element_translations', null, $info->trid, 'post_attachment' );
			foreach ( $translations as $translation ) {
				if ( $attachment_id != $translation->element_id ) {
					update_post_meta(
						$translation->element_id,
						'_wp_attached_file',
						get_post_meta(
							$attachment_id,
							'_wp_attached_file',
							true
						)
					);
					update_post_meta(
						$translation->element_id,
						'_wp_attachment_metadata',
						get_post_meta(
							$attachment_id,
							'_wp_attachment_metadata',
							true
						)
					);
				}
			}
		}
	}
	/**
	 * @param $field
	 * @param $orig_image_url
	 * @param $new_image_url
	 *
	 * @return array
	 */
	public static function search_image_at_content( $orig_image_url ) {
		global $wpdb;
		$useless_types_conditions = self::$useless_types_conditions;
		// Get the IDs that require an update.
		$query = $wpdb->prepare(
			"SELECT ID FROM $wpdb->posts WHERE (post_content LIKE %s OR post_excerpt LIKE %s)
    				AND {$useless_types_conditions}",
			'%' . $orig_image_url . '%',
			'%' . $orig_image_url . '%'
		);
		$ids   = $wpdb->get_col( $query );
		if ( empty( $ids ) ) {
			return [];
		}

		return $ids;
	}

	/**
	 * @param $field
	 * @param $orig_image_url
	 * @param $new_image_url
	 *
	 * @return array
	 */
	private static function replace_image_at_content( $field, $orig_image_url, $new_image_url ) {
		global $wpdb;
		// replace_image_at_content() ;
		// Validate the field to prevent SQL injection.
		if ( ! in_array( $field, [ 'post_content', 'post_excerpt' ], true ) ) {
			return [];
		}
		$useless_types_conditions = self::$useless_types_conditions;
		// Get the IDs that require an update.
		$query = $wpdb->prepare(
			"SELECT ID FROM $wpdb->posts
        WHERE {$field} LIKE '%s'
        AND {$useless_types_conditions}",
			'%' . $orig_image_url . '%'
		);
		$ids   = $wpdb->get_col( $query );
		if ( empty( $ids ) ) {
			return [];
		}

		// Prepare SQL (WHERE IN).
		$ids_to_update = array_map(
			function ( $id ) {
				return "'" . esc_sql( $id ) . "'";
			},
			$ids
		);
		$ids_to_update = implode( ',', $ids_to_update );

		// Execute updates.
		$query = $wpdb->prepare(
			"UPDATE $wpdb->posts
        SET {$field} = REPLACE({$field}, '%s', '%s')
        WHERE ID IN (" . $ids_to_update . ')',
			$orig_image_url,
			$new_image_url
		);
		$wpdb->query( $query );

		// Reverse updates.
		$query_revert = $wpdb->prepare(
			"UPDATE $wpdb->posts
        SET {$field} = REPLACE({$field}, '%s', '%s')
        WHERE ID IN (" . $ids_to_update . ')',
			$orig_image_url,
			$new_image_url
		);

		return $ids;
	}
	/**
	 * Search for occurrences of the original image URL in Elementor metadata.
	 *
	 * @param string $orig_image_url
	 * @return array List of post IDs where the URL is found.
	 */
	private static function search_elementor_metadata( $orig_image_url ) {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return [];
		}
		global $wpdb;
		$table_meta               = $wpdb->postmeta;
		$table_posts              = $wpdb->posts;
		$useless_types_conditions = self::$useless_types_conditions;
		$orig_image_url           = esc_sql( $orig_image_url );
		$orig_image_url           = str_replace( '/', '\/', $orig_image_url );
		$searchValue              = '%' . str_replace( '\/', '\\\/', $orig_image_url ) . '%';

		$query = $wpdb->prepare(
			"SELECT m.post_id FROM {$table_meta} AS m
		JOIN {$table_posts} AS p ON m.post_id = p.ID
		WHERE m.meta_key = '_elementor_data'
		AND m.meta_value LIKE %s
		AND {$useless_types_conditions}",
			$searchValue
		);
		return $wpdb->get_col( $query );
	}
	/**
	 * @param $orig_image_url
	 * @param $new_image_url
	 *
	 * @return void
	 */
	private static function update_elementor_metadata( $orig_image_url, $new_image_url ) {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}
		global $wpdb;
		$table_meta = $wpdb->postmeta;

		$orig_image_url = esc_sql( $orig_image_url );
		$new_image_url  = esc_sql( $new_image_url );
		$orig_image_url = str_replace( '/', '\/', $orig_image_url );
		$new_image_url  = str_replace( '/', '\/', $new_image_url );
		$searchValue    = '%' . str_replace( '\/', '\\\/', $orig_image_url ) . '%';

		$query = $wpdb->prepare(
			"UPDATE {$table_meta}
		  SET meta_value = REPLACE(meta_value, %s, %s)
		  WHERE meta_key = '_elementor_data'
		  AND meta_value LIKE %s",
			$orig_image_url,
			$new_image_url,
			$searchValue
		);
		$wpdb->query( $query );

		// Elementor to regenerate
		$query = "DELETE FROM $wpdb->postmeta WHERE meta_key = '_elementor_css'";
		$wpdb->query( $query );
		$query = "DELETE FROM $wpdb->postmeta WHERE meta_key = '_elementor_element_cache'";
		$wpdb->query( $query );
	}

	/**
	 * @param $name
	 *
	 * @return string
	 */
	public static function add_filename_prefix_suffix( $name ) {
		if ( empty( $name ) ) {
			return $name;
		}
		$options = self::get_options();
		if ( ! empty( $options['media_rename_prefix'] ) ) {
			$name = $options['media_rename_prefix'] . '-' . $name;
		}
		if ( ! empty( $options['media_rename_suffix'] ) ) {
			$name = $name . '-' . $options['media_rename_suffix'];
		}
		return $name;
	}
	/**
	 * Image attachment details
	 *
	 * @param int $attachment_id image id.
	 *
	 * @return bool
	 */
	public static function wp_rename_attachment( $attachment_id, $new_file_name = '' ) {
		$orig_image_url = wp_get_attachment_url( $attachment_id );
		$updated        = false;
		$new_file_name  = pathinfo( $new_file_name, PATHINFO_FILENAME );
		$new_file_name  = sanitize_file_name( $new_file_name );
		$new_file_name  = preg_replace( '/-(scaled|rotated)/', '', $new_file_name );
		if ( empty( $new_file_name ) || ! $attachment_id ) {
			return $updated;
		}
		// Get the file path.
		$file_path = get_attached_file( $attachment_id );
		if ( ! file_exists( $file_path ) ) {
			return $updated;
		}
		$metadata_file = basename( $file_path );
		$fileextension = pathinfo( $metadata_file, PATHINFO_EXTENSION );
		$filebasename  = basename( $metadata_file, '.' . $fileextension );
		$new_file_name = $new_file_name . '.' . $fileextension;
		// Check if the new name is different from the current one.
		if ( basename( $new_file_name, '.' . $fileextension ) === $filebasename ) {
			return $updated;
		}

		// Check file type to see if it's an image or other media (like video).
		$filetype         = wp_check_filetype( $file_path );
		$is_image         = strpos( $filetype['type'], 'image' ) !== false;
		$is_not_svg_image = $is_image && 'image/svg+xml' !== $filetype['type'];
		// Get the current metadata for the media file (images only).
		$old_sizes = [];
		if ( $is_image && $is_not_svg_image ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size => $fileinfo ) {
					$old_sizes[ $size ] = wp_get_attachment_image_url( $attachment_id, $size );
					$old_file_path      = dirname( $file_path ) . '/' . $fileinfo['file'];
					if ( file_exists( $old_file_path ) ) {
						 wp_delete_file( $old_file_path );
					}
				}
			}
		}
		// Renaming the file.
		$path_being_saved_to = dirname( $file_path );
		$unique_filename     = $path_being_saved_to . '/' . wp_unique_filename( $path_being_saved_to, $new_file_name );

		$renamed          = rename( $file_path, $unique_filename );
		$new_file_name    = basename( $unique_filename );
		$new_filebasename = basename( $new_file_name, '.' . $fileextension );

		// If successfully renamed, update metadata.
		if ( $renamed ) {
			wp_update_post(
				[
					'ID'        => $attachment_id,
					'post_name' => $new_filebasename,
				]
			);

			// Only regenerate metadata for images.
			if ( $is_image ) {
				if ( ! function_exists( 'wp_crop_image' ) ) {
					include ABSPATH . 'wp-admin/includes/image.php';
				}
				update_attached_file( $attachment_id, $unique_filename );
				$new_image_url = wp_get_attachment_url( $attachment_id );
				// $searchValue
				self::replace_image_at_content( 'post_content', $orig_image_url, $new_image_url );
				self::replace_image_at_content( 'post_excerpt', $orig_image_url, $new_image_url );
				self::update_elementor_metadata( $orig_image_url, $new_image_url );
				if ( empty( get_post_meta( $attachment_id, '_original_file_url', true ) ) ) {
					update_post_meta( $attachment_id, '_original_file_url', $orig_image_url );
				}
				try {
					$generate_meta = wp_generate_attachment_metadata( $attachment_id, $unique_filename );
					wp_update_attachment_metadata( $attachment_id, $generate_meta );
					if ( ! empty( $generate_meta['sizes'] ) ) {
						foreach ( $generate_meta['sizes'] as $size => $fileinfo ) {
							$new_size_url = wp_get_attachment_image_url( $attachment_id, $size );
							if ( ! empty( $old_sizes[ $size ] ) ) {
								self::replace_image_at_content( 'post_content', $old_sizes[ $size ], $new_size_url );
								self::replace_image_at_content( 'post_excerpt', $old_sizes[ $size ], $new_size_url );
								self::update_elementor_metadata( $old_sizes[ $size ], $new_size_url );
							}
						}
					}
				} catch ( \Exception $e ) {
					error_log( 'Error reading data: ' . $e->getMessage() );
				}
			} else {
				// For non-image files, just update the attached file path.
				update_attached_file( $attachment_id, $unique_filename );
			}
			// WPML.
			self::wpml_update_translations( $attachment_id );
			// Update permalink.
			self::permalink_to_post_guid( $attachment_id );
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
		$defaults = [
			'media_per_page'         => 20,
			'media_table_column'     => [ 'ID', 'Image', 'Title', 'Alt', 'Caption', 'Group' ],
			'media_default_alt'      => '',
			'media_default_caption'  => '',
			'media_default_desc'     => '',
			'default_alt_text'       => 'image_name_to_alt',
			'default_caption_text'   => 'none',
			'default_desc_text'      => 'none',
			'others_file_support'    => [],
			'enable_auto_rename'     => '',
			'media_auto_rename_text' => '',
			'media_rename_prefix'    => '',
			'media_rename_suffix'    => '',

		];
		$options                   = get_option( 'tsmlt_settings', [] );
		$limit                     = absint( $options['media_per_page'] ?? 20 );
		$options['media_per_page'] = self::maximum_media_per_page() < $limit ? self::maximum_media_per_page() : $limit;

		if ( ! empty( $options['rubbish_per_page'] ) ) {
			$total_rabbis_count          = absint( $options['rubbish_per_page'] ?? 20 );
			$options['rubbish_per_page'] = self::maximum_media_per_page() < $total_rabbis_count ? self::maximum_media_per_page() : $total_rabbis_count;
		}

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
		// Initialize the WP filesystem.
		if ( empty( $wp_filesystem ) ) {
			// Include the file.php for WP filesystem functions.
			include_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		// Check if WP_Filesystem_Direct is already instantiated.
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
	 * @param string $directory The directory to scan.
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
	public static function update_rubbish_file_to_database( $directory ) {

		$dir_cache_key = md5( $directory );
		if ( isset( self::$cache[ $dir_cache_key ] ) ) {
			$found_files = self::$cache[ $dir_cache_key ];
		} else {
			$found_files                   = self::scan_file_in_directory( $directory ); // Scan the directory and search for files.
			self::$cache[ $dir_cache_key ] = $found_files;
		}

		$dis_list = get_option( 'tsmlt_get_directory_list', [] );

		$dis_list[ $directory ]['total_items'] = count( $found_files );

		$last_processed_offset = absint( $dis_list[ $directory ]['counted'] );

		// Skip the files until the offset is reached.
		// $files = array_slice( $found_files, $last_processed_offset, 50 );.
		$files = $found_files;

		$found_files_count = count( $files );

		$dis_list[ $directory ]['counted'] = $last_processed_offset + $found_files_count;
		global $wpdb;

		$upload_dir      = wp_upload_dir();
		$uploaddir       = $upload_dir['basedir'] ?? 'wp-content/uploads/';
		$instantDeletion = tsmlt()->has_pro() && wp_doing_ajax() && 'instant' === ( $_REQUEST['instantDeletion'] ?? '' );
		$table_name      = $wpdb->prefix . 'tsmlt_unlisted_file';
		foreach ( $found_files as $file_path ) {
			if ( ! file_exists( $file_path ) ) {
				continue;
			}
			$search_string = '';
			$str           = explode( $uploaddir . '/', $file_path );

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

			$metadata_file = basename( $file_path );
			$fileextension = pathinfo( $metadata_file, PATHINFO_EXTENSION );
			if ( $instantDeletion && in_array( $fileextension, self::default_file_extensions(), true ) ) {
				wp_delete_file( $file_path );
				$wpdb->delete( $table_name, [ 'file_path' => $file_path ], [ '%s' ] );
				continue;
			}

			$cache_key  = 'tsmlt_existing_row_' . sanitize_title( $file_path );
			$table_name = $wpdb->prefix . 'tsmlt_unlisted_file';
			// Check if the file_path already exists in the table using cached data
			$existing_row = wp_cache_get( $cache_key );
			if ( ! $existing_row ) {
				$existing_row = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table_name WHERE file_path = %s", $search_string ) );
				// Cache the query result
				if ( $existing_row ) {
					continue;
				}
				$save_data = [
					'file_path'     => $search_string,
					'attachment_id' => 0,
					'file_type'     => pathinfo( $search_string, PATHINFO_EXTENSION ),
					'meta_data'     => serialize( [] ),
				];
				$wpdb->insert( $table_name, $save_data );

				wp_cache_set( $cache_key, $existing_row );
			}
		}
		return update_option( 'tsmlt_get_directory_list', $dis_list );
	}

	/**
	 * @return void
	 */
	public static function get_directory_list_cron_job( $isRescan = false ) {
		if ( $isRescan ) {
			update_option( 'tsmlt_get_directory_list', [] );
		}
		$cache_key      = 'get_directory_list';
		$subdirectories = wp_cache_get( $cache_key );
		if ( ! $subdirectories ) {
			$upload_dir     = wp_upload_dir(); // Get the upload directory path.
			$directory      = $upload_dir['basedir']; // Get the base directory path.
			$subdirectories = self::scan_directory_list( $directory );
			wp_cache_set( $cache_key, $subdirectories );
		}
		$dir_status = get_option( 'tsmlt_get_directory_list', [] );

		$subdirectories = wp_parse_args( $dir_status, $subdirectories );

		update_option( 'tsmlt_get_directory_list', $subdirectories );
	}

	/**
	 * Function to retrieve the list of directories with paths from a given directory.
	 *
	 * @param string $directory The directory to scan.
	 *
	 * @return array The list of directories with their paths.
	 */
	public static function scan_directory_list( $directory ) {
		if ( ! $directory || ! is_string( $directory ) ) {
			return [];
		}
		$filesystem  = self::get_wp_filesystem_instance(); // Get the proper WP_Filesystem instance.
		$directories = [];
		// Ensure the directory exists before scanning.
		if ( ! $filesystem->is_dir( $directory ) ) {
			return [];
		}
		$paths_to_ignore = self::paths_to_ignore();
		foreach ( $paths_to_ignore as $path ) {
			if ( strpos( $directory, $path ) !== false ) {
				return [];
			}
		}

		$files = $filesystem->dirlist( $directory );
		foreach ( $files as $file ) {
			$file_path = trailingslashit( $directory ) . $file['name'];

			if ( $filesystem->is_dir( $file_path ) ) {
				$subdirectories = self::scan_directory_list( $file_path );
				$directories    = array_merge( $directories, $subdirectories );
			} else {
				// Extract the directory path from the file path.
				$dir_path = dirname( $file_path );
				// Add the directory to the list if it doesn't exist.
				if ( ! in_array( $dir_path, $directories, true ) ) {
					$directories[ $dir_path ] = [
						'total_items' => 0,
						'counted'     => 0,
						'status'      => 'available',
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
	public static function set_thumbnail_parent_id( $attachment_id ) {
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		if ( get_post_field( 'post_parent', $attachment_id ) ) {
			return false;
		}

		global $wpdb;
		$query     = $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %d", '_thumbnail_id', $attachment_id );
		$parent_id = $wpdb->get_var( $query );
		$post_ids  = [];
		if ( ! $parent_id ) {
			$orig_image_url = wp_get_attachment_url( $attachment_id );
			$post_ids       = self::search_image_at_content( $orig_image_url );
		}
		if ( empty( $post_ids ) ) {
			$post_ids = self::search_elementor_metadata( $orig_image_url );
		}
		if ( empty( $post_ids ) ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size => $fileinfo ) {
					$url      = wp_get_attachment_image_url( $attachment_id, $size );
					$post_ids = self::search_image_at_content( $url );
					if ( ! empty( $post_ids ) ) {
						break;
					}
					$post_ids = self::search_elementor_metadata( $url );
					if ( ! empty( $post_ids ) ) {
						break;
					}
				}
			}
		}
		if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
			$parent_id = reset( $post_ids );
		}
		// Update the attachment's parent ID.
		$attachment_data = [
			'ID'          => $attachment_id,
			'post_parent' => $parent_id,
		];
		// Update the attachment using wp_update_post.
		wp_update_post( $attachment_data );
		return $parent_id;
	}

	/**
	 * @param string $type string mime type.
	 *
	 * @return bool
	 */
	public static function is_support_mime_type( $type ) {
		$options = self::get_options();
		if ( empty( $options['others_file_support'] ) || ! is_array( $options['others_file_support'] ) ) {
			return false;
		}
		if ( in_array( $type, $options['others_file_support'], true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Image attachment details
	 *
	 * @param init $attachment_id image id.
	 *
	 * @return int
	 */
	public static function maximum_media_per_page() {
		return absint( apply_filters( 'tsmlt_maximum_media_per_page', 1000 ) );
	}

	/**
	 * Function to scan the upload directory and search for files
	 */
	public static function scan_rubbish_file_cron_job( $skip = [] ) {

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
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			$directory = $key;
		}

		if ( ! empty( $directory ) ) {
			self::update_rubbish_file_to_database( $directory );
		}
	}

	/**
	 * @return array|void
	 */
	public static function paths_to_ignore() {
		return apply_filters(
			'tsmlt_get_directory_list_paths_to_ignore',
			[
				'wp-content/uploads/elementor',
				'wp-content/uploads/rtcl',
			]
		);
	}

	/**
	 * @return string[]
	 */
	public static function default_file_extensions() {
		return apply_filters( 'tsmlt_default_file_extensions', [ 'pdf', 'zip', 'mp4', 'jpeg', 'jpg', 'php', 'log', 'png', 'svg', 'gif', 'DS_Store', 'bmp', 'tiff', 'webp', 'heif', 'raw', 'psd', 'eps', 'ico', 'cur', 'jp2' ] );
	}

	/**
	 * @return string[]
	 */
	public static function skip_meta_keys() {
		// List of meta keys to remove.
		$skip_key = apply_filters(
			'tsmlt_skip_meta_keys',
			[
				'file',
				'sizes',
				'width',
				'height',
				'filesize',
				'image_meta',
				'_wp_attached_file',
				'_elementor_source_image_hash',
				'_wc_attachment_source',
				'_wp_attachment_image_alt',
				'_wp_attachment_metadata',
				'_wp_old_slug',
				'_edit_lock',
				'_edit_last',
				'_original_file_url',
			]
		);
		if ( empty( $skip_key ) || ! is_array( $skip_key ) ) {
			return [];
		}
		return $skip_key;
	}
	/**
	 * @return array
	 */
	public static function get_all_necessary_meta_keys() {
		$keys_attachment = 'get_all_attachment_necessary_meta_keys';
		if ( isset( self::$cache[ $keys_attachment ] ) ) {
			return self::$cache[ $keys_attachment ];
		}
		global $wpdb;
		$meta_keys = $wpdb->get_col(
			$wpdb->prepare(
				"  SELECT DISTINCT pm.meta_key  FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s",
				'attachment'
			)
		);
		// List of meta keys to remove.
		$remove_keys = self::skip_meta_keys();
		// Remove by value.
		$meta_keys = array_values( array_diff( $meta_keys, $remove_keys ) );

		self::$cache[ $keys_attachment ] = $meta_keys;
		return $meta_keys;
	}
}
