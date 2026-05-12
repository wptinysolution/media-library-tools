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
	 * Single-event hook name for self-chaining background scan ticks.
	 */
	const SCAN_TICK_HOOK = 'tsmlt_rubbish_scan_tick';

	/**
	 * Seconds between scan ticks. Short enough to feel responsive on an
	 * active session, long enough to let WP-Cron release the lock so the
	 * next request can pick it up.
	 */
	const SCAN_TICK_INTERVAL = 15;

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Start a user-initiated rubbish scan.
	 *
	 * Synchronously builds the directory list, then schedules the first
	 * background file-scan tick. The tick handler re-schedules itself
	 * until every directory is fully scanned, then stops — no recurring
	 * cron runs in the background otherwise.
	 *
	 * @return array
	 */
	public function start_scan(): array {
		// Drop any in-flight tick so we don't double-fire when a user restarts.
		wp_clear_scheduled_hook( self::SCAN_TICK_HOOK );

		// Fresh directory list — clears `tsmlt_get_directory_list` and walks uploads/.
		self::clear_scan_transients();
		self::get_directory_list_cron_job( true );

		$dirlist = get_option( 'tsmlt_get_directory_list', [] );

		// Only schedule a tick if there's actually work to do.
		if ( ! empty( $dirlist ) ) {
			wp_schedule_single_event( time() + self::SCAN_TICK_INTERVAL, self::SCAN_TICK_HOOK );
		}

		return [
			'updated' => true,
			'dirlist' => $dirlist,
			'message' => esc_html__( 'Scan started. Directories will be processed in the background.', 'media-library-tools' ),
		];
	}

	/**
	 * Background tick: scan one directory, then re-schedule for the next one
	 * if any unscanned directories remain. Self-terminates when complete.
	 *
	 * @return void
	 */
	public static function run_scan_tick(): void {
		self::scan_rubbish_file_cron_job();

		// Decide whether more work remains.
		$dirlist = get_option( 'tsmlt_get_directory_list', [] );
		if ( empty( $dirlist ) ) {
			return;
		}

		foreach ( $dirlist as $item ) {
			$fully_scanned = ( absint( $item['total_items'] ) && absint( $item['total_items'] ) <= absint( $item['counted'] ) )
				|| ( absint( $item['total_items'] ) === 0 && ! empty( $item['scanned'] ) );
			$blocked       = 'available' !== ( $item['status'] ?? 'available' );
			if ( ! $fully_scanned && ! $blocked ) {
				wp_schedule_single_event( time() + self::SCAN_TICK_INTERVAL, self::SCAN_TICK_HOOK );
				return;
			}
		}
		// No unscanned directories left — chain terminates here.
	}

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
		$directory_list = get_option( 'tsmlt_get_directory_list', [] );

		// Surface whether a background tick is in flight so the UI can show progress state.
		$next_tick_ts = wp_next_scheduled( self::SCAN_TICK_HOOK );
		$next_tick    = '';
		if ( $next_tick_ts ) {
			$wordpress_timezone = get_option( 'timezone_string' );
			$timezone           = $wordpress_timezone ? new \DateTimeZone( $wordpress_timezone ) : new \DateTimeZone( 'UTC' );
			$next_tick_dt       = new \DateTime( "@$next_tick_ts" );
			$next_tick_dt->setTimezone( $timezone );
			$next_tick = $next_tick_dt->format( 'Y-m-d h:i:sa' );
		}

		$data = [
			'dirList'      => $directory_list,
			'nextSchedule' => $next_tick,
			'scanning'     => (bool) $next_tick_ts,
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
			// Clear all directory scan transients so fresh filesystem reads happen.
			self::clear_scan_transients();
			self::get_directory_list_cron_job( true );
			$message = esc_html__( 'Schedule Will Execute Soon For Directory List.', 'media-library-tools' );
		} elseif ( empty( $directory_list[ $dir ] ) ) {
			// Clear the transient for this specific directory.
			delete_transient( 'tsmlt_dir_scan_' . md5( $dir ) );
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
		wp_clear_scheduled_hook( self::SCAN_TICK_HOOK );
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
		wp_clear_scheduled_hook( self::SCAN_TICK_HOOK );
		return [
			'updated' => true,
			'dirlist' => get_option( 'tsmlt_get_directory_list', [] ),
			'message' => esc_html__( 'Background scan cancelled.', 'media-library-tools' ),
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
		$filter_ext = sanitize_text_field( $parameters['filterExtension'] ?? '' );
		if ( 'all' === $filter_ext ) {
			$extensions = null;
		} elseif ( ! empty( $filter_ext ) ) {
			$extensions = [ $filter_ext ];
		} else {
			$extensions = self::default_file_extensions();
		}

		$cache_key    = 'tsmlt_unlisted_file_' . md5( serialize( [ $statuses, $extensions, $page ] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Safe use.
		$existing_row = wp_cache_get( $cache_key );

		if ( false === $existing_row ) {
			$query = Fns::DB()->select( '*' )
				->from( 'tsmlt_unlisted_file' )
				->whereIn( 'status', ...$statuses );
			if ( null !== $extensions ) {
				$query = $query->andIn( 'file_type', ...$extensions );
			}
			$existing_row = $query
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
			$count_query = Fns::DB()->select()
				->count( '*', 'total' )
				->from( 'tsmlt_unlisted_file' )
				->whereIn( 'status', ...$statuses );
			if ( null !== $extensions ) {
				$count_query = $count_query->andIn( 'file_type', ...$extensions );
			}
			$count_result = $count_query->get();
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
		self::clear_scan_transients();
		return true;
	}

	/**
	 * Delete all tsmlt_dir_scan_* transients so the next scan reads fresh from disk.
	 *
	 * WordPress stores transients in the options table as `_transient_<key>`, so
	 * we can bulk-delete them with a LIKE query via the query builder.
	 *
	 * @return void
	 */
	private static function clear_scan_transients(): void {
		// Also clear the registered-file lookup cache.
		wp_cache_delete( 'tsmlt_registered_file_lookup' );

		// Bulk-delete all directory scan transients from the options table.
		Fns::DB()->delete( 'options' )
			->where( 'option_name', 'LIKE', '_transient_tsmlt_dir_scan_%' )
			->execute();
		Fns::DB()->delete( 'options' )
			->where( 'option_name', 'LIKE', '_transient_timeout_tsmlt_dir_scan_%' )
			->execute();
	}

	// -------------------------------------------------------------------------
	// Filesystem scanning (moved from Fns)
	// -------------------------------------------------------------------------

	/**
	 * Function to scan the upload directory and search for files.
	 *
	 * Results are cached in a transient (10 minutes) so that the repeated AJAX
	 * batch calls for large directories (e.g. 30,000+ files) do not re-read the
	 * filesystem on every request.
	 *
	 * @param string $directory The directory to scan.
	 *
	 * @return array The list of found files.
	 */
	public static function scan_file_in_directory( $directory ) {
		if ( ! $directory || ! is_dir( $directory ) ) {
			return [];
		}

		// Transient cache — survives across AJAX requests (unlike Fns::$cache).
		$transient_key = 'tsmlt_dir_scan_' . md5( $directory );
		$cached        = get_transient( $transient_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// FilesystemIterator uses the dirent type from `readdir`, so `isFile()`
		// answers from kernel cache without an extra `stat` per entry — what
		// the previous WP_Filesystem path was paying on every file.
		try {
			$iterator = new \FilesystemIterator(
				$directory,
				\FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS
			);
		} catch ( \UnexpectedValueException $e ) {
			return [];
		}

		$scanned_files = [];
		foreach ( $iterator as $entry ) {
			if ( $entry->isFile() ) {
				$scanned_files[] = $entry->getPathname();
			}
		}

		// Cache for 10 minutes — long enough to survive the full batch run.
		set_transient( $transient_key, $scanned_files, 10 * MINUTE_IN_SECONDS );

		return $scanned_files;
	}

	/**
	 * Pre-load all registered attachment paths and thumbnail basenames from the
	 * database into a fast in-memory lookup set so the per-file loop needs zero
	 * DB queries to determine whether a physical file belongs to an attachment.
	 *
	 * Returns an array with two keys:
	 *   'paths'     => [ 'relative/path.jpg' => true, ... ]   — exact relative paths
	 *   'basenames' => [ 'thumb-150x150.jpg' => post_id, ... ] — thumbnail basenames
	 *
	 * @return array{paths: array<string,true>, basenames: array<string,int>}
	 */
	private static function build_registered_file_lookup(): array {
		$cache_key = 'tsmlt_registered_file_lookup';
		$cached    = wp_cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$paths          = [];
		$basenames      = [];
		// Map of post_id → relative dir, built from _wp_attached_file, used to
		// resolve thumbnail paths without a second inner loop.
		$post_id_to_dir = [];
		// Map of post_id → relative file path, used to resolve attachment IDs
		// stored in term meta / option meta back to disk paths.
		$post_id_to_path = [];

		// 1. Fetch every _wp_attached_file value (1 query via query builder).
		$rows = Fns::DB()->select( 'post_id', 'meta_value' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', '_wp_attached_file' )
			->get();

		foreach ( (array) $rows as $row ) {
			$rel_path = $row['meta_value'] ?? '';
			if ( ! $rel_path ) {
				continue;
			}
			$post_id                                 = (int) $row['post_id'];
			$paths[ $rel_path ]                      = true;
			$post_id_to_dir[ $post_id ]              = dirname( $rel_path );
			$post_id_to_path[ $post_id ]             = $rel_path;
		}

		// 2. Fetch _wp_attachment_metadata to capture generated thumbnail filenames (1 query).
		$meta_rows = Fns::DB()->select( 'post_id', 'meta_value' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', '_wp_attachment_metadata' )
			->get();

		foreach ( (array) $meta_rows as $row ) {
			$post_id = (int) $row['post_id'];
			$meta    = maybe_unserialize( $row['meta_value'] );
			if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
				continue;
			}

			// O(1) dir lookup — no inner loop needed.
			$dir = $post_id_to_dir[ $post_id ] ?? '.';

			foreach ( $meta['sizes'] as $size_data ) {
				if ( empty( $size_data['file'] ) ) {
					continue;
				}
				$rel           = ( '.' === $dir ) ? $size_data['file'] : $dir . '/' . $size_data['file'];
				$paths[ $rel ] = true;
				$basenames[ basename( $size_data['file'] ) ] = $post_id;
			}
		}

		// 3. Term meta — WooCommerce category/brand thumbnails and similar.
		// Pulls every termmeta row whose key looks like an attachment-id field.
		// We don't try to be exhaustive; we just resolve any value that maps to a
		// known attachment ID. If the value isn't a real attachment ID we ignore it.
		if ( function_exists( 'get_terms' ) ) {
			$term_meta_keys = apply_filters(
				'tsmlt_registered_term_meta_attachment_keys',
				[
					'thumbnail_id',         // WooCommerce product_cat thumbnail.
					'brand_thumbnail_id',   // WooCommerce Brands.
					'product_brand_thumb',  // Other brand plugins.
					'category_image_id',    // Generic.
				]
			);
			if ( ! empty( $term_meta_keys ) && is_array( $term_meta_keys ) ) {
				$tm_rows = Fns::DB()->select( 'meta_value' )
					->from( 'termmeta' )
					->whereIn( 'meta_key', ...array_values( $term_meta_keys ) )
					->get();
				foreach ( (array) $tm_rows as $row ) {
					$att_id = (int) ( $row['meta_value'] ?? 0 );
					if ( $att_id && isset( $post_id_to_path[ $att_id ] ) ) {
						$paths[ $post_id_to_path[ $att_id ] ] = true;
					}
				}
			}
		}

		// 4. site_logo / custom_logo option — site identity images.
		$logo_id = (int) get_option( 'site_logo' );
		if ( ! $logo_id ) {
			$logo_id = (int) get_theme_mod( 'custom_logo' );
		}
		if ( $logo_id && isset( $post_id_to_path[ $logo_id ] ) ) {
			$paths[ $post_id_to_path[ $logo_id ] ] = true;
		}

		// 5. Filter — let Pro/third parties append paths (e.g. Elementor library,
		// ACF image fields that store paths instead of IDs, Customizer images, etc.).
		// Filter receives the partial lookup so it can also append basenames.
		$lookup = apply_filters(
			'tsmlt_registered_file_lookup',
			[ 'paths' => $paths, 'basenames' => $basenames ],
			$post_id_to_path
		);

		// Defend against filters returning a wrong shape.
		if ( ! is_array( $lookup ) || ! isset( $lookup['paths'], $lookup['basenames'] ) ) {
			$lookup = [ 'paths' => $paths, 'basenames' => $basenames ];
		}

		wp_cache_set( $cache_key, $lookup, '', 300 ); // Cache for 5 minutes.
		return $lookup;
	}

	/**
	 * @param $directory
	 *
	 * @return bool|void
	 */
	public static function update_rubbish_file_to_database( $directory ) {

		// scan_file_in_directory() caches in a transient, so subsequent batch
		// AJAX calls for the same directory don't hit the filesystem again.
		$found_files = self::scan_file_in_directory( $directory );

		$dis_list = get_option( 'tsmlt_get_directory_list', [] );

		$dis_list[ $directory ]['total_items'] = count( $found_files );

		$last_processed_offset = absint( $dis_list[ $directory ]['counted'] );

		// Process files in batches of 500 — large enough to finish big directories
		// (30k+ files) in ~60 round trips instead of 600, while staying well within
		// typical PHP time limits since the per-file work is now O(1) hash lookups.
		$batch_size = (int) apply_filters( 'tsmlt_rubbish_scan_batch_size', 500 );
		$files      = array_slice( $found_files, $last_processed_offset, $batch_size );

		$found_files_count = count( $files );

		$dis_list[ $directory ]['counted'] = $last_processed_offset + $found_files_count;

		global $wpdb;
		$upload_dir      = wp_upload_dir();
		$uploaddir       = $upload_dir['basedir'] ?? 'wp-content/uploads/';
		$instantDeletion = 'instant' === sanitize_text_field( wp_unslash( $_REQUEST['instantDeletion'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$table_name      = $wpdb->prefix . 'tsmlt_unlisted_file'; // Used by tsmlt_do_ajax_instant_action hook.

		// ── Pre-load all known registered paths in ONE DB round-trip ──────────
		$lookup = self::build_registered_file_lookup();

		// ── Pre-load only rubbish rows for this specific directory (1 query) ──
		// Scoping by directory prefix avoids loading the entire rubbish table
		// when there are thousands of rows across many directories.
		$upload_rel      = ltrim( str_replace( $uploaddir, '', $directory ), '/\\' );
		$existing_rows   = Fns::DB()->select( 'file_path' )
			->from( 'tsmlt_unlisted_file' )
			->where( 'file_path', 'LIKE', $upload_rel . '/%' )
			->get();
		$existing_set    = array_flip( array_column( (array) $existing_rows, 'file_path' ) );

		// ── Collect rows to bulk-insert ───────────────────────────────────────
		$rows_to_insert  = [];
		$uploaddir_token = $uploaddir . '/';
		$token_len       = strlen( $uploaddir_token );
		// Flip the extension list once so per-file membership check is O(1)
		// instead of `in_array` over a 20-item array per iteration.
		$ext_lookup = array_flip( self::default_file_extensions() );

		// Extensions that should never be auto-deleted during scan even when
		// the user opts into instant deletion. These file types are commonly
		// referenced from places this scanner can't see (term meta, theme
		// settings, page builders), so the safer default is to flag them as
		// rubbish for review rather than delete them outright.
		$instant_delete_excluded = array_flip(
			(array) apply_filters(
				'tsmlt_instant_delete_excluded_extensions',
				[ 'svg', 'pdf' ]
			)
		);

		foreach ( $files as $file_path ) {
			// `scan_file_in_directory()` already filtered to real files; the transient
			// keeps the list fresh for 10 minutes, so a per-file `file_exists()` here
			// would just re-stat thousands of paths we just enumerated.

			// Cheap prefix strip — avoids `explode()` allocating a 2-element array per file.
			$pos = strpos( $file_path, $uploaddir_token );
			if ( false === $pos ) {
				continue;
			}
			$search_string = substr( $file_path, $pos + $token_len );
			if ( '' === $search_string ) {
				continue;
			}

			// O(1) lookups — no DB query needed per file.
			// Check exact relative path first (covers attachments and their thumbnails,
			// since build_registered_file_lookup() stores both in 'paths').
			if ( isset( $lookup['paths'][ $search_string ] ) ) {
				continue; // Registered file — skip.
			}
			// Fallback basename check: a file in an unexpected path but same name
			// as a known thumbnail. Rare edge case.
			$bn = basename( $search_string );
			if ( isset( $lookup['basenames'][ $bn ] ) ) {
				continue; // Matches a known thumbnail basename — skip.
			}

			// Derive extension from the basename we already computed instead of a
			// separate `pathinfo()` call. Fall back to '' for extension-less files.
			$dot           = strrpos( $bn, '.' );
			$fileextension = ( false === $dot ) ? '' : substr( $bn, $dot + 1 );

			if (
				$instantDeletion
				&& wp_doing_ajax()
				&& isset( $ext_lookup[ $fileextension ] )
				&& ! isset( $instant_delete_excluded[ $fileextension ] )
			) {
				do_action( 'tsmlt_do_ajax_instant_action', $file_path, $table_name );
				continue;
			}

			// Skip if already in our rubbish table.
			if ( isset( $existing_set[ $search_string ] ) ) {
				continue;
			}

			$rows_to_insert[] = [
				'file_path'     => $search_string,
				'attachment_id' => 0,
				'file_type'     => $fileextension,
				'meta_data'     => serialize( [] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			];
		}

		// ── Bulk insert all new rubbish rows in a single query ────────────────
		if ( ! empty( $rows_to_insert ) ) {
			Fns::DB()->insert( 'tsmlt_unlisted_file', $rows_to_insert )->execute();
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
		if ( ! $directory || ! is_string( $directory ) || ! is_dir( $directory ) ) {
			return [];
		}

		$paths_to_ignore = self::paths_to_ignore();
		foreach ( $paths_to_ignore as $path ) {
			if ( strpos( $directory, $path ) !== false ) {
				return [];
			}
		}

		// Native recursive iterator: walks the tree in C, no PHP recursion frames,
		// and `dirname()` keys give us O(1) dedupe via `isset` instead of `in_array`.
		try {
			$flags    = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $directory, $flags ),
				\RecursiveIteratorIterator::LEAVES_ONLY,
				\RecursiveIteratorIterator::CATCH_GET_CHILD
			);
		} catch ( \UnexpectedValueException $e ) {
			return [];
		}

		$directories  = [];
		$default_meta = [
			'total_items' => 0,
			'counted'     => 0,
			'status'      => 'available',
		];

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$file_path = $file->getPathname();

			// Honor the ignore list — substring match on the full path matches the legacy behavior.
			foreach ( $paths_to_ignore as $ignore ) {
				if ( strpos( $file_path, $ignore ) !== false ) {
					continue 2;
				}
			}

			$dir_path = $file->getPath();
			if ( ! isset( $directories[ $dir_path ] ) ) {
				$directories[ $dir_path ] = $default_meta;
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
