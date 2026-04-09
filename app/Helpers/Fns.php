<?php
/**
 * Fns Helpers class
 *
 * @package  TinySolutions\WM
 */

namespace TinySolutions\mlt\Helpers;

use TinySolutions\mlt\Vendor\CodesVault\Howdyqb\DB;
use WP_Filesystem;
use WP_Filesystem_Direct;
use WP_Filesystem_Base;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Fns Helpers class
 *
 * Provides utility helpers for media operations, attachment renaming,
 * scanning directories, Elementor cleanup, WPML sync, and scheduled tasks.
 *
 * @package TinySolutions\WM
 */
class Fns {

	/**
	 * Nonce ID used across admin and AJAX requests.
	 */
	const NONCE_ID = 'tsmlt_wpnonce';

	/**
	 * Taxonomy slug for media categories.
	 */
	const CATEGORY = 'tsmlt_category';

	/**
	 * @return DB
	 */
	public static function DB() {
		return new DB( 'wpdb' );
	}
	/**
	 * @var array
	 */
	public static $cache = [];
	/**
	 * @var string
	 */
	private static $useless_types_conditions = "
		post_status NOT IN ('inherit', 'trash', 'auto-draft')
		AND post_type NOT IN ('attachment', 'shop_order', 'shop_order_refund', 'nav_menu_item', 'revision', 'auto-draft', 'wphb_minify_group', 'customize_changeset', 'oembed_cache', 'nf_sub', 'jp_img_sitemap')
		AND post_type NOT LIKE 'dlssus_%'
		AND post_type NOT LIKE 'ml-slide%'
		AND post_type NOT LIKE '%acf-%'
		AND post_type NOT LIKE '%edd_%'
	";

	/**
	 * @param string $plugin_file_path string.
	 *
	 * @return bool
	 */
	public static function is_plugins_installed( $plugin_file_path = null ) {
		$installed_plugins_list = get_plugins();

		return isset( $installed_plugins_list[ $plugin_file_path ] );
	}
	/**
	 * Action.
	 *
	 * @param string $action action.
	 * @return void
	 */
	public static function add_to_scheduled_hook_list( $action ) {
		if ( empty( $action ) ) {
			return;
		}
		$schedule   = get_option( 'tsmlt_cron_schedule', [] );
		$schedule[] = $action;
		update_option( 'tsmlt_cron_schedule', array_unique( $schedule ) );
	}
	/**
	 * Clear Scheduled Events
	 *
	 * @return void
	 */
	public static function clear_scheduled_events() {
		$schedule = get_option( 'tsmlt_cron_schedule', [] );
		if ( empty( $schedule ) ) {
			return;
		}
		foreach ( $schedule as $v ) {
			wp_clear_scheduled_hook( $v );
		}
	}
	/**
	 * Image attachment details
	 *
	 * @param int $attachment_id image id.
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
		$info = apply_filters( 'wpml_element_language_details', null, $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		if ( ! empty( $info->trid ) ) {
			$translations = apply_filters( 'wpml_get_element_translations', null, $info->trid, 'post_attachment' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
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
	 * Replace all occurrences of an image URL in post content, excerpt, and Elementor data.
	 *
	 * @param string $old_url Original image URL.
	 * @param string $new_url Replacement image URL.
	 *
	 * @return int Total number of affected rows.
	 */
	public static function replace_image_url_everywhere( string $old_url, string $new_url ): int {
		$affected  = self::replace_image_at_content( 'post_content', $old_url, $new_url );
		$affected += self::replace_image_at_content( 'post_excerpt', $old_url, $new_url );

		// Elementor metadata.
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$post_ids = self::search_elementor_metadata( $old_url );
			foreach ( $post_ids as $post_id ) {
				$elementor_data = get_post_meta( (int) $post_id, '_elementor_data', true );
				if ( ! empty( $elementor_data ) ) {
					$escaped_old    = str_replace( '/', '\\/', $old_url );
					$escaped_new    = str_replace( '/', '\\/', $new_url );
					$elementor_data = str_replace( $escaped_old, $escaped_new, $elementor_data );
					update_post_meta( (int) $post_id, '_elementor_data', wp_slash( $elementor_data ) );
					++$affected;
				}
			}
		}

		return $affected;
	}

	/**
	 * Reassign featured images from one attachment to another.
	 *
	 * @param int $old_attachment_id Attachment ID to replace.
	 * @param int $new_attachment_id Attachment ID to use instead.
	 *
	 * @return int Number of updated posts.
	 */
	public static function reassign_featured_image( int $old_attachment_id, int $new_attachment_id ): int {
		self::DB()->update(
			'postmeta',
			[ 'meta_value' => $new_attachment_id ] // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)->where( 'meta_key', '=', '_thumbnail_id' )
			->andWhere( 'meta_value', '=', $old_attachment_id )
			->execute();

		// Return count of affected posts.
		$result = self::DB()->select()
			->count( '*', 'total' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', '_thumbnail_id' )
			->andWhere( 'meta_value', '=', $new_attachment_id )
			->get();

		return (int) ( $result[0]['total'] ?? 0 );
	}

	/**
	 * Search post IDs where an image URL exists in content or excerpt.
	 *
	 * @param string $orig_image_url Original image URL.
	 *
	 * @return array<int> List of post IDs.
	 */
	public static function search_image_at_content( $orig_image_url ) {
		global $wpdb;
		$like                     = '%' . $wpdb->esc_like( $orig_image_url ) . '%';
		$useless_types_conditions = self::$useless_types_conditions;

		$result_content = self::DB()->select( 'ID' )
			->from( 'posts' )
			->where( 'post_content', 'LIKE', $like )
			->raw( "AND {$useless_types_conditions}" )
			->get();

		$result_excerpt = self::DB()->select( 'ID' )
			->from( 'posts' )
			->where( 'post_excerpt', 'LIKE', $like )
			->raw( "AND {$useless_types_conditions}" )
			->get();

		$ids = array_values(
			array_unique(
				array_merge(
					array_column( $result_content ?: [], 'ID' ),
					array_column( $result_excerpt ?: [], 'ID' )
				)
			)
		);

		return empty( $ids ) ? [] : $ids;
	}

	/**
	 * Replace an image URL inside post content or excerpt.
	 *
	 * @param string $field          Database field name (post_content or post_excerpt).
	 * @param string $orig_image_url Original image URL.
	 * @param string $new_image_url  New image URL.
	 *
	 * @return int Number of affected rows.
	 */
	private static function replace_image_at_content( $field, $orig_image_url, $new_image_url ) {
		global $wpdb;
		// Strict whitelist to prevent SQL injection.
		$allowed_fields = [
			'post_content',
			'post_excerpt',
		];
		if ( ! in_array( $field, $allowed_fields, true ) ) {
			return 0;
		}
		/**
		 * Static, internal SQL fragment.
		 * Contains no user input.
		 */
		$useless_types_conditions = self::$useless_types_conditions;
		$sql                      = " UPDATE {$wpdb->posts} SET {$field} = REPLACE( {$field}, %s, %s ) WHERE {$field} LIKE %s AND {$useless_types_conditions} ";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column name is strictly whitelisted.
		return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Prepared below.
			$wpdb->prepare(
				$sql, // phpcs:ignore WordPress.DB.PreparedSQL -- Prepared below.
				$orig_image_url,
				$new_image_url,
				'%' . $wpdb->esc_like( $orig_image_url ) . '%'
			)
		);
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
		$escaped_url              = str_replace( '/', '\/', $orig_image_url );
		$searchValue              = '%' . str_replace( '\/', '\\\/', $escaped_url ) . '%';
		$useless_types_conditions = self::$useless_types_conditions;
		$result                   = self::DB()->select( 'm.post_id' )
			->from( 'postmeta m' )
			->join( 'posts p', 'p.ID', 'm.post_id' )
			->where( 'm.meta_key', '=', '_elementor_data' )
			->andWhere( 'm.meta_value', 'LIKE', $searchValue )
			->raw( "AND {$useless_types_conditions}" )
			->get();
		return array_column( $result ?: [], 'post_id' );
	}

	/**
	 * Search WooCommerce product gallery and variation thumbnail for an attachment ID.
	 *
	 * Covers:
	 *  - Product gallery images stored in `_product_image_gallery` (comma-separated IDs).
	 *  - Variation thumbnails stored in `_thumbnail_id` on variation posts.
	 *
	 * @param int $attachment_id Attachment ID to search for.
	 *
	 * @return array<int> List of post IDs that use this attachment.
	 */
	private static function search_woocommerce_gallery( $attachment_id ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return [];
		}
		$id = absint( $attachment_id );

		// Gallery: _product_image_gallery stores comma-separated IDs like "12,34,56".
		// REGEXP matches exact ID at start, end, or between commas.
		// $id is always an integer from absint(), safe to interpolate.
		$gallery_result = self::DB()->select( 'post_id' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', '_product_image_gallery' )
			->raw( "AND meta_value REGEXP '(^|,)" . $id . "(,|$)'" )
			->get();

		// Variation thumbnail: _thumbnail_id on product_variation posts.
		$variation_result = self::DB()->select( 'pm.post_id' )
			->from( 'postmeta pm' )
			->join( 'posts p', 'p.ID', 'pm.post_id' )
			->where( 'pm.meta_key', '=', '_thumbnail_id' )
			->andWhere( 'pm.meta_value', '=', $id )
			->andWhere( 'p.post_type', '=', 'product_variation' )
			->get();

		$ids = array_values(
			array_unique(
				array_merge(
					array_column( $gallery_result ?: [], 'post_id' ),
					array_column( $variation_result ?: [], 'post_id' )
				)
			)
		);

		// For variation posts, resolve to the parent product ID.
		$resolved = [];
		foreach ( $ids as $post_id ) {
			if ( 'product_variation' === get_post_type( $post_id ) ) {
				$parent     = wp_get_post_parent_id( $post_id );
				$resolved[] = $parent ?: $post_id;
			} else {
				$resolved[] = absint( $post_id );
			}
		}

		return array_values( array_unique( array_filter( $resolved ) ) );
	}

	/**
	 * Update Elementor post meta data by replacing image URLs
	 * and force Elementor to regenerate CSS and cache.
	 *
	 * @param string $orig_image_url Original image URL.
	 * @param string $new_image_url  New image URL.
	 *
	 * @return void
	 */
	private static function update_elementor_metadata( $orig_image_url, $new_image_url ) {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}
		global $wpdb;
		/**
		 * Trusted core table name.
		 * Provided by $wpdb, contains no user input.
		 */
		$table_meta   = $wpdb->postmeta;
		$search_value = '%' . $wpdb->esc_like( $orig_image_url ) . '%';
		$sql          = "UPDATE {$table_meta} SET meta_value = REPLACE( meta_value, %s, %s ) WHERE meta_key = '_elementor_data' AND meta_value LIKE %s";
		$query        = $wpdb->prepare( $sql, $orig_image_url, $new_image_url, $search_value ); // phpcs:ignore WordPress.DB.PreparedSQL -- Prepared below.
		$wpdb->query( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		// Force Elementor to regenerate CSS and cache.
		self::DB()->delete( 'postmeta' )->where( 'meta_key', '=', '_elementor_css' )->execute();
		self::DB()->delete( 'postmeta' )->where( 'meta_key', '=', '_elementor_element_cache' )->execute();
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

		$renamed          = rename( $file_path, $unique_filename ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Using rename function to rename the file.
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
					wp_trigger_error(
						__METHOD__,
						'Error reading data: ' . $e->getMessage(),
						E_USER_WARNING
					);
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
	 * @return void
	 */
	public static function permalink_to_post_guid( $post_id ) {
		$guid = wp_get_attachment_url( $post_id );
		self::DB()->update( 'posts', [ 'guid' => $guid ] )
			->where( 'ID', '=', $post_id )
			->execute();
		clean_post_cache( $post_id );
	}

	/**
	 * @return false|string
	 */
	public static function get_options() {
		$defaults = [
			'media_per_page'         => 20,
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

		$options['ai_max_suggestion_count'] = max( 1, (int) apply_filters( 'tsmlt_ai_max_suggestion_count', 1 ) );

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
	 * @return \WP_Filesystem|WP_Filesystem_Direct The WP_Filesystem instance
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
				$wp_filesystem = new WP_Filesystem_Direct( null ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- Overriding global variable intentionally.
			}
		}

		return $wp_filesystem;
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

		$result         = self::DB()->select( 'post_id' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', '_thumbnail_id' )
			->andWhere( 'meta_value', '=', $attachment_id )
			->get();
		$parent_id      = ! empty( $result ) ? $result[0]['post_id'] : null;
		$post_ids       = [];
		$orig_image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $parent_id ) {
			$post_ids = self::search_image_at_content( $orig_image_url );
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
		if ( empty( $post_ids ) ) {
			$post_ids = self::search_woocommerce_gallery( $attachment_id );
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
				'_tsmlt_image_usages',
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
		$result    = self::DB()->select( 'pm.meta_key' )
			->distinct()
			->from( 'postmeta pm' )
			->innerJoin( 'posts p', 'p.ID', 'pm.post_id' )
			->where( 'p.post_type', '=', 'attachment' )
			->get();
		$meta_keys = array_column( $result ?: [], 'meta_key' );
		// List of meta keys to remove.
		$remove_keys = self::skip_meta_keys();
		// Remove by value.
		$meta_keys = array_values( array_diff( $meta_keys, $remove_keys ) );

		self::$cache[ $keys_attachment ] = $meta_keys;
		return $meta_keys;
	}
}
