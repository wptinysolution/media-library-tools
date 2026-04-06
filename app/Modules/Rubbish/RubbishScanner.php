<?php
/**
 * Rubbish (unlisted) file detection — scans upload directories for files
 * not registered in the WordPress media library.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Rubbish;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Handles scanning, querying, and managing rubbish (unlisted) files.
 */
class RubbishScanner {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	// -------------------------------------------------------------------------
	// Parameter helper
	// -------------------------------------------------------------------------

	/**
	 * Accept a plain parameter array (all callers pass arrays via AJAX).
	 *
	 * @param array $request_data Plain parameter array.
	 *
	 * @return array
	 */
	private function parse_params( array $request_data ): array {
		return $request_data;
	}

	// -------------------------------------------------------------------------
	// Directory listing & scheduling
	// -------------------------------------------------------------------------

	/**
	 * @return false|string
	 */
	public function get_dir_list() {

		wp_clear_scheduled_hook( 'tsmlt_upload_inner_file_scan' );

		$directory_list = get_option( 'tsmlt_get_directory_list', [] );

		// Get the timestamp of the next scheduled event.
		$next_scheduled_timestamp = wp_next_scheduled( 'tsmlt_upload_dir_scan' );

		// Get WordPress timezone.
		$wordpress_timezone = get_option( 'timezone_string' );

		// Set a default timezone in case the WordPress timezone is not set or invalid.
		$timezone = $wordpress_timezone ? new \DateTimeZone( $wordpress_timezone ) : new \DateTimeZone( 'UTC' );

		// Create a DateTime object with the scheduled timestamp and set the timezone.
		$next_scheduled_datetime = new \DateTime( "@$next_scheduled_timestamp" );
		$next_scheduled_datetime->setTimezone( $timezone );

		$data = [
			'dirList'      => $directory_list,
			'nextSchedule' => $next_scheduled_datetime->format( 'Y-m-d h:i:sa' ),
		];
		return json_encode( $data );
	}

	/**
	 * @return array
	 */
	public function rescan_dir( array $request_data ) {
		$parameters     = $this->parse_params( $request_data );
		$dir            = $parameters['dir'] ?? 'all';
		$directory_list = [];
		$message        = esc_html__( 'Schedule Will Execute Soon.', 'media-library-tools' );
		if ( 'all' === $dir ) {
			self::get_directory_list_cron_job( true );
			$message = esc_html__( 'Schedule Will Execute Soon For Directory List.', 'media-library-tools' );
		} elseif ( empty( $directory_list[ $dir ] ) ) {
			$directory_list = get_option( 'tsmlt_get_directory_list', [] );
			if ( ! empty( $directory_list[ $dir ] ) ) {
				$directory_list[ $dir ] = [
					'total_items' => 0,
					'counted'     => 0,
					'status'      => 'available',
				];
				update_option( 'tsmlt_get_directory_list', $directory_list );
			}
		}
		wp_clear_scheduled_hook( 'tsmlt_upload_inner_file_scan' );
		wp_clear_scheduled_hook( 'tsmlt_upload_dir_scan' );
		return [
			'updated'    => true,
			'thedirlist' => get_option( 'tsmlt_get_directory_list', [] ),
			'message'    => $message,
		];
	}

	/**
	 * @return array
	 */
	public function immediately_search_rubbish_file( array $request_data ) {
		$parameters = $this->parse_params( $request_data );
		$result     = [
			'updated' => false,
			'data'    => [],
			'message' => esc_html__( 'Update failed. Please try to fix', 'media-library-tools' ),
		];

		$directory = $parameters['directory'] ?? '';

		if ( empty( $directory ) ) {
			return $result;
		}
		$updated = self::update_rubbish_file_to_database( $directory );
		$dirlist = get_option( 'tsmlt_get_directory_list', [] );

		if ( ! empty( $dirlist[ $directory ] ) ) {
			if ( isset( $dirlist[ $directory ]['total_items'] ) && isset( $dirlist[ $directory ]['counted'] ) ) {
				$directory = absint( $dirlist[ $directory ]['total_items'] ) > absint( $dirlist[ $directory ]['counted'] ) ? $directory : 'nextDir';
			}
		}
		$result['updated'] = (bool) $updated;
		$result['nextDir'] = $directory;
		$result['dirlist'] = $dirlist;
		$result['message'] = $result['updated'] ? esc_html__( 'Done, Be happy.', 'media-library-tools' ) : esc_html__( 'Update failed. Please try to fix', 'media-library-tools' );
		return $result;
	}

	/**
	 * @return array
	 */
	public function clear_schedule() {
		wp_clear_scheduled_hook( 'tsmlt_upload_inner_file_scan' );
		wp_clear_scheduled_hook( 'tsmlt_upload_dir_scan' );
		return [
			'updated' => true,
			'dirlist' => get_option( 'tsmlt_get_directory_list', [] ),
			'message' => esc_html__( 'Schedule Cleared. Will Execute Soon.', 'media-library-tools' ),
		];
	}

	// -------------------------------------------------------------------------
	// Rubbish file queries
	// -------------------------------------------------------------------------

	/**
	 * @return false|string
	 */
	public function get_rubbish_filetype() {
		$cache_key = 'tsmlt_unlisted_filetypes';
		$types     = wp_cache_get( $cache_key );
		if ( false === $types ) {
			$result = Fns::DB()->select( 'file_type' )->distinct()->from( 'tsmlt_unlisted_file' )->get();
			$types  = array_column( $result ?: [], 'file_type' );
			wp_cache_set( $cache_key, $types );
		}
		$rubbish_data = [
			'fileTypes' => is_array( $types ) ? $types : [],
		];
		return wp_json_encode( $rubbish_data );
	}

	/**
	 * Retrieve rubbish files with pagination and filtering.
	 *
	 * @param array $request_data Parameter array.
	 *
	 * @return false|string JSON-encoded response.
	 */
	public function get_rubbish_file( array $request_data ) {
		$parameters = $this->parse_params( $request_data );
		$options    = get_option( 'tsmlt_settings' );
		$limit      = absint( $parameters['postsPerPage'] ?? $options['rubbish_per_page'] ?? 20 );
		$page       = max( 1, absint( $parameters['paged'] ?? 1 ) );
		$offset     = ( $page - 1 ) * $limit;
		$status     = sanitize_text_field( $parameters['fileStatus'] ?? 'show' );
		$statuses   = [ $status ];
		$extensions = ! empty( $parameters['filterExtension'] )
			? [ sanitize_text_field( $parameters['filterExtension'] ) ]
			: self::default_file_extensions();

		$cache_key    = 'tsmlt_unlisted_file_' . md5( serialize( [ $statuses, $extensions, $page ] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Safe use.
		$existing_row = wp_cache_get( $cache_key );

		if ( false === $existing_row ) {
			$existing_row = Fns::DB()->select( '*' )
				->from( 'tsmlt_unlisted_file' )
				->whereIn( 'status', ...$statuses )
				->andIn( 'file_type', ...$extensions )
				->limit( $limit )
				->offset( $offset )
				->get();
			$existing_row = $existing_row ?: [];
			wp_cache_set( $cache_key, $existing_row );
		}

		/* ---------- COUNT QUERY ---------- */

		$total_cache_key = $cache_key . '_total';
		$total_file      = wp_cache_get( $total_cache_key );

		if ( false === $total_file ) {
			$count_result = Fns::DB()->select()
				->count( '*', 'total' )
				->from( 'tsmlt_unlisted_file' )
				->whereIn( 'status', ...$statuses )
				->andIn( 'file_type', ...$extensions )
				->get();
			$total_file   = (int) ( $count_result[0]['total'] ?? 0 );
			wp_cache_set( $total_cache_key, $total_file );
		}

		return wp_json_encode(
			[
				'mediaFile'    => is_array( $existing_row ) ? $existing_row : [],
				'paged'        => $page,
				'totalPost'    => $total_file,
				'postsPerPage' => $limit,
			]
		);
	}

	/**
	 * Truncate the 'tsmlt_unlisted_file' table.
	 *
	 * @return bool True if the query succeeds, false otherwise.
	 */
	public function delete_all_rows_in_unlisted_file() {
		Fns::DB()->truncate( 'tsmlt_unlisted_file' );
		// MODIFY COLUMN resets the AUTO_INCREMENT counter once all rows are deleted.
		Fns::DB()->alter( 'tsmlt_unlisted_file' )->modify( 'id' )->int()->autoIncrement()->execute();
		update_option( 'tsmlt_get_directory_list', [] );
		return true;
	}

	// -------------------------------------------------------------------------
	// Filesystem scanning (moved from Fns)
	// -------------------------------------------------------------------------

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
		$filesystem = Fns::get_wp_filesystem_instance();
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
		if ( isset( Fns::$cache[ $dir_cache_key ] ) ) {
			$found_files = Fns::$cache[ $dir_cache_key ];
		} else {
			$found_files                   = self::scan_file_in_directory( $directory );
			Fns::$cache[ $dir_cache_key ] = $found_files;
		}

		$dis_list = get_option( 'tsmlt_get_directory_list', [] );

		$dis_list[ $directory ]['total_items'] = count( $found_files );

		$last_processed_offset = absint( $dis_list[ $directory ]['counted'] );

		// Process files in batches of 50 to avoid timeouts on large directories.
		$files = array_slice( $found_files, $last_processed_offset, 50 );

		$found_files_count = count( $files );

		$dis_list[ $directory ]['counted'] = $last_processed_offset + $found_files_count;
		global $wpdb;

		$upload_dir      = wp_upload_dir();
		$uploaddir       = $upload_dir['basedir'] ?? 'wp-content/uploads/';
		$instantDeletion = 'instant' === sanitize_text_field( wp_unslash( $_REQUEST['instantDeletion'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$table_name      = $wpdb->prefix . 'tsmlt_unlisted_file';
		foreach ( $files as $file_path ) {
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
			if ( ! $attachment_id && $search_string ) {
				// Search by basename so WordPress-generated thumbnails are also matched.
				// Then verify the matched attachment lives in the same directory to avoid
				// false positives from custom directories like "ribbish/".
				$search_basename = basename( $search_string );
				$search_dir      = dirname( $search_string );
				$result          = Fns::DB()->select( 'post_id' )
					->from( 'postmeta' )
					->where( 'meta_key', '=', '_wp_attachment_metadata' )
					->andWhere( 'meta_value', 'LIKE', '%' . $wpdb->esc_like( $search_basename ) . '%' )
					->get();
				if ( ! empty( $result ) ) {
					foreach ( $result as $row ) {
						$attached_file = get_post_meta( (int) $row['post_id'], '_wp_attached_file', true );
						if ( $attached_file && dirname( $attached_file ) === $search_dir ) {
							$attachment_id = (int) $row['post_id'];
							break;
						}
					}
				}
			}

			if ( absint( $attachment_id ) && get_post_type( $attachment_id ) ) {
				continue;
			}

			$metadata_file = basename( $file_path );
			$fileextension = pathinfo( $metadata_file, PATHINFO_EXTENSION );

			$matchFileExtension = in_array( $fileextension, self::default_file_extensions(), true );
			if ( $instantDeletion && wp_doing_ajax() && $matchFileExtension ) {
				do_action( 'tsmlt_do_ajax_instant_action', $file_path, $table_name );
				continue;
			}
			$cache_key  = 'tsmlt_existing_row_' . sanitize_title( $file_path );
			// Check if the file_path already exists in the table using cached data.
			$existing_row = wp_cache_get( $cache_key );
			if ( ! $existing_row ) {
				$result       = Fns::DB()->select( 'id' )
					->from( 'tsmlt_unlisted_file' )
					->where( 'file_path', '=', $search_string )
					->get();
				$existing_row = ! empty( $result ) ? $result[0] : null;
				// Cache the query result.
				if ( $existing_row ) {
					continue;
				}
				$save_data = [
					'file_path'     => $search_string,
					'attachment_id' => 0,
					'file_type'     => pathinfo( $search_string, PATHINFO_EXTENSION ),
					'meta_data'     => serialize( [] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Using serialize to store array data.
				];
				Fns::DB()->insert( 'tsmlt_unlisted_file', [ $save_data ] )->execute();

				wp_cache_set( $cache_key, $existing_row );
			}
		}
		$dis_list[ $directory ]['scanned'] = true;
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
			$upload_dir     = wp_upload_dir();
			$directory      = $upload_dir['basedir'];
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
		$filesystem  = Fns::get_wp_filesystem_instance();
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
	 * Function to scan the upload directory and search for files.
	 */
	public static function scan_rubbish_file_cron_job( $skip = [] ) {

		$dis_list = get_option( 'tsmlt_get_directory_list', [] );
		if ( ! count( $dis_list ) ) {
			return;
		}
		$directory = '';
		foreach ( $dis_list as $key => $item ) {
			$fully_scanned = ( absint( $item['total_items'] ) && absint( $item['total_items'] ) <= absint( $item['counted'] ) )
				|| ( absint( $item['total_items'] ) === 0 && ! empty( $item['scanned'] ) );
			if ( $fully_scanned ) {
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

	// -------------------------------------------------------------------------
	// Empty directory detection & deletion
	// -------------------------------------------------------------------------

	/**
	 * Return all upload subdirectories that contain no files (recursively).
	 *
	 * Only directories that are inside the WordPress uploads basedir are considered.
	 * Directories on the ignore list are skipped.
	 *
	 * @return array  List of absolute paths to empty directories.
	 */
	public static function get_empty_directories(): array {
		$upload_dir  = wp_upload_dir();
		$basedir     = trailingslashit( $upload_dir['basedir'] );
		$filesystem  = Fns::get_wp_filesystem_instance();
		$ignore_list = self::paths_to_ignore();

		return self::collect_empty_directories( $basedir, $filesystem, $ignore_list );
	}

	/**
	 * Recursively collect directories that contain no files at any depth.
	 *
	 * @param string $directory  Absolute path to scan.
	 * @param object $filesystem WP_Filesystem instance.
	 * @param array  $ignore     Paths to skip.
	 *
	 * @return array
	 */
	private static function collect_empty_directories( string $directory, $filesystem, array $ignore ): array {
		if ( ! $filesystem->is_dir( $directory ) ) {
			return [];
		}
		foreach ( $ignore as $path ) {
			if ( false !== strpos( $directory, $path ) ) {
				return [];
			}
		}

		$files   = $filesystem->dirlist( $directory );
		$empty   = [];

		if ( empty( $files ) ) {
			$empty[] = untrailingslashit( $directory );
			return $empty;
		}

		$has_file   = false;
		$child_dirs = [];

		foreach ( $files as $file ) {
			$file_path = trailingslashit( $directory ) . $file['name'];
			if ( $filesystem->is_dir( $file_path ) ) {
				$child_dirs[] = $file_path;
			} else {
				$has_file = true;
			}
		}

		// Directory contains files — not empty itself.
		if ( $has_file ) {
			// Still recurse into subdirectories to find nested empty ones.
			foreach ( $child_dirs as $child ) {
				$empty = array_merge( $empty, self::collect_empty_directories( $child, $filesystem, $ignore ) );
			}
			return $empty;
		}

		// No files in this directory — check children.
		$all_child_empty = true;
		foreach ( $child_dirs as $child ) {
			$child_empty = self::collect_empty_directories( $child, $filesystem, $ignore );
			if ( ! empty( $child_empty ) ) {
				$empty = array_merge( $empty, $child_empty );
			} else {
				// Child was not reported empty (has files inside).
				$all_child_empty = false;
			}
		}

		// If this directory has subdirs and all of them (and their descendants)
		// are empty, report this directory itself as empty instead.
		if ( ! empty( $child_dirs ) && $all_child_empty && count( $empty ) === count( $child_dirs ) ) {
			$empty[] = untrailingslashit( $directory );
		}

		return $empty;
	}

	/**
	 * Delete a single empty directory.
	 *
	 * Validates that the path is inside the uploads basedir, is a real directory,
	 * and is genuinely empty before removing it.
	 *
	 * @param array $request_data Must contain 'directory' key with absolute path.
	 *
	 * @return array Result with 'updated' bool and 'message' string.
	 */
	public function delete_empty_directory( array $request_data ): array {
		$result = [
			'updated' => false,
			'message' => esc_html__( 'Delete failed.', 'media-library-tools' ),
		];

		// File paths must not be run through sanitize_text_field() — it can mangle
		// valid directory names (e.g. folders containing spaces or special chars).
		$directory = isset( $request_data['directory'] ) ? (string) $request_data['directory'] : '';

		if ( empty( $directory ) ) {
			$result['message'] = esc_html__( 'No directory specified.', 'media-library-tools' );
			return $result;
		}

		$upload_dir = wp_upload_dir();
		$basedir    = realpath( $upload_dir['basedir'] );
		$real_dir   = realpath( $directory );

		// Security: must be inside uploads basedir (trailing separator prevents
		// partial-prefix bypasses like /uploads-extra/).
		if ( ! $real_dir || ! $basedir || 0 !== strpos( $real_dir, trailingslashit( $basedir ) ) ) {
			$result['message'] = esc_html__( 'Invalid directory path.', 'media-library-tools' );
			return $result;
		}

		// Must be a directory.
		if ( ! is_dir( $real_dir ) ) {
			$result['message'] = esc_html__( 'Path is not a directory.', 'media-library-tools' );
			return $result;
		}

		// Must be empty (no files or subdirs).
		$filesystem = Fns::get_wp_filesystem_instance();
		$files      = $filesystem->dirlist( $real_dir );
		if ( ! empty( $files ) ) {
			$result['message'] = esc_html__( 'Directory is not empty.', 'media-library-tools' );
			return $result;
		}

		if ( $filesystem->rmdir( $real_dir ) ) {
			// Remove from directory list option if present.
			$dir_list = get_option( 'tsmlt_get_directory_list', [] );
			unset( $dir_list[ $directory ], $dir_list[ $real_dir ] );
			update_option( 'tsmlt_get_directory_list', $dir_list );

			$result['updated'] = true;
			$result['message'] = esc_html__( 'Empty directory deleted successfully.', 'media-library-tools' );
		} else {
			$result['message'] = esc_html__( 'Could not delete directory. Check file permissions.', 'media-library-tools' );
		}

		return $result;
	}

	/**
	 * @return string[]
	 */
	public static function default_file_extensions() {
		return apply_filters( 'tsmlt_default_file_extensions', [ 'pdf', 'zip', 'mp4', 'jpeg', 'jpg', 'php', 'log', 'png', 'svg', 'gif', 'DS_Store', 'bmp', 'tiff', 'webp', 'heif', 'raw', 'psd', 'eps', 'ico', 'cur', 'jp2' ] );
	}
}
