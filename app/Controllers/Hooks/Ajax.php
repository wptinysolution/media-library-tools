<?php
/**
 * Ajax action handlers.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Controllers\Hooks;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Rubbish\RubbishScanner;
use TinySolutions\mlt\Modules\Duplicate\DuplicateScanner;
use TinySolutions\mlt\Modules\Rename\RenameModule;
use TinySolutions\mlt\Modules\ImageSize\ImageSizeModule;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;
use TinySolutions\mlt\Traits\SingletonTrait;
use TinySolutions\mlt\Controllers\Admin\Api;
use TinySolutions\mlt\Controllers\AI\AiApi;

defined( 'ABSPATH' ) || exit();

/**
 * WordPress AJAX action handlers.
 */
class Ajax {
	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Class Constructor
	 */
	private function __construct() {
		// Directory scan — used by DirectoryModal (legacy action name kept for compatibility).
		add_action( 'wp_ajax_immediately_search_rubbish_file', [ $this, 'search_rubbish_file' ] );

		// Media list / counts.
		add_action( 'wp_ajax_tsmlt_get_media', [ $this, 'get_media' ] );
		add_action( 'wp_ajax_tsmlt_media_count', [ $this, 'media_count' ] );
		add_action( 'wp_ajax_tsmlt_update_single_media', [ $this, 'update_single_media' ] );
		add_action( 'wp_ajax_tsmlt_bulk_submit', [ $this, 'media_submit_bulk_action' ] );

		// Filters / options.
		add_action( 'wp_ajax_tsmlt_get_dates', [ $this, 'get_dates' ] );
		add_action( 'wp_ajax_tsmlt_get_terms', [ $this, 'get_terms' ] );
		add_action( 'wp_ajax_tsmlt_get_options', [ $this, 'get_options' ] );
		add_action( 'wp_ajax_tsmlt_update_option', [ $this, 'update_option' ] );

		// Rubbish / unlisted files.
		add_action( 'wp_ajax_tsmlt_get_rubbish_filetype', [ $this, 'get_rubbish_filetype' ] );
		add_action( 'wp_ajax_tsmlt_get_rubbish_file', [ $this, 'get_rubbish_file' ] );
		add_action( 'wp_ajax_tsmlt_get_dir_list', [ $this, 'get_dir_list' ] );
		add_action( 'wp_ajax_tsmlt_rescan_dir', [ $this, 'rescan_dir' ] );
		add_action( 'wp_ajax_tsmlt_search_file_by_dir', [ $this, 'search_file_by_dir' ] );
		add_action( 'wp_ajax_tsmlt_truncate_unlisted_file', [ $this, 'truncate_unlisted_file' ] );
		add_action( 'wp_ajax_tsmlt_get_empty_directories', [ $this, 'get_empty_directories' ] );
		add_action( 'wp_ajax_tsmlt_delete_empty_directory', [ $this, 'delete_empty_directory' ] );

		// Schedule / image sizes / plugins.
		add_action( 'wp_ajax_tsmlt_clear_schedule', [ $this, 'clear_schedule' ] );
		add_action( 'wp_ajax_tsmlt_get_registered_image_sizes', [ $this, 'get_registered_image_sizes' ] );
		add_action( 'wp_ajax_tsmlt_get_plugin_list', [ $this, 'get_plugin_list' ] );

		// AI content generation.
		add_action( 'wp_ajax_tsmlt_ai_generate', [ $this, 'ai_generate' ] );

		// Duplicate detection.
		add_action( 'wp_ajax_tsmlt_duplicate_scan_batch', [ $this, 'duplicate_scan_batch' ] );
		add_action( 'wp_ajax_tsmlt_duplicate_get_results', [ $this, 'duplicate_get_results' ] );
		add_action( 'wp_ajax_tsmlt_duplicate_get_status', [ $this, 'duplicate_get_status' ] );
		add_action( 'wp_ajax_tsmlt_duplicate_clear', [ $this, 'duplicate_clear' ] );

		// Used-Where image usage tracking.
		add_action( 'wp_ajax_tsmlt_used_where_scan_batch', [ $this, 'used_where_scan_batch' ] );
		add_action( 'wp_ajax_tsmlt_used_where_get_results', [ $this, 'used_where_get_results' ] );
		add_action( 'wp_ajax_tsmlt_used_where_get_status', [ $this, 'used_where_get_status' ] );
		add_action( 'wp_ajax_tsmlt_used_where_clear', [ $this, 'used_where_clear' ] );
		add_action( 'wp_ajax_tsmlt_used_where_bulk_delete', [ $this, 'used_where_bulk_delete' ] );
		add_action( 'wp_ajax_tsmlt_used_where_trash', [ $this, 'used_where_trash' ] );
		add_action( 'wp_ajax_tsmlt_used_where_untrash', [ $this, 'used_where_untrash' ] );
		add_action( 'wp_ajax_tsmlt_used_where_get_trashed', [ $this, 'used_where_get_trashed' ] );

		// Regenerate thumbnails.
		add_action( 'wp_ajax_tsmlt_regenerate_batch', [ $this, 'regenerate_batch' ] );
		add_action( 'wp_ajax_tsmlt_regenerate_get_status', [ $this, 'regenerate_get_status' ] );
	}

	// -------------------------------------------------------------------------
	// Security helpers
	// -------------------------------------------------------------------------

	/**
	 * Enforce that this is a genuine, POST-only, admin AJAX request made by a
	 * logged-in user with the manage_options capability.
	 *
	 * Returns the decoded params array on success.
	 * Terminates with wp_die() on any failure — no code path continues after a
	 * failed check.
	 *
	 * @return array
	 */
	private function verify_and_get_params(): array {
		// Must be an actual AJAX request routed through admin-ajax.php.
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'Invalid request.', 'media-library-tools' ), 400 );
		}

		// Must be a POST request — reject GET/HEAD/etc.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_die( esc_html__( 'Method not allowed.', 'media-library-tools' ), 405 );
		}

		// Verify the nonce — dies with 403 on failure.
		check_ajax_referer( Fns::NONCE_ID, 'nonce' );

		// Verify the user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Unauthorized.', 'media-library-tools' ) ], 403 );
		}

		// Decode the JSON params payload.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON blob; each field sanitized inside handler methods.
		$raw    = isset( $_POST['params'] ) ? wp_unslash( $_POST['params'] ) : '{}';
		$params = json_decode( $raw, true );

		if ( ! is_array( $params ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Malformed request payload.', 'media-library-tools' ) ], 400 );
		}

		return $params;
	}

	/**
	 * Wrap the result from an Api method and send as AJAX success response.
	 *
	 * @param mixed $result Data returned by an Api method.
	 *
	 * @return void
	 */
	private function send( $result ): void {
		wp_send_json_success( $result );
	}

	// -------------------------------------------------------------------------
	// Legacy handler — DirectoryModal directory scan
	// -------------------------------------------------------------------------

	/**
	 * Scan rubbish file cron job — called by DirectoryModal.
	 *
	 * @return void
	 */
	public function search_rubbish_file(): void {
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'Invalid request.', 'media-library-tools' ), 400 );
		}

		check_ajax_referer( Fns::NONCE_ID, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Unauthorized.', 'media-library-tools' ) ], 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via array_map below.
		$raw_skip = isset( $_POST['skip'] ) ? wp_unslash( $_POST['skip'] ) : [];
		$skip     = is_array( $raw_skip ) ? array_map( 'sanitize_text_field', $raw_skip ) : [];

		RubbishScanner::scan_rubbish_file_cron_job( $skip );

		$dirlist = get_option( 'tsmlt_get_directory_list', [] );
		$dir     = [];
		if ( ! empty( $dirlist ) ) {
			foreach ( $dirlist as $key => $item ) {
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
				$dir[ $key ] = $item;
			}
		}
		wp_send_json_success(
			[
				'dirList'       => $dir,
				'dirStatusList' => $dirlist,
			]
		);
	}

	// -------------------------------------------------------------------------
	// Media
	// -------------------------------------------------------------------------

	/** @return void */
	public function get_media(): void {
		$params = $this->verify_and_get_params();
		$this->send( Api::instance()->get_media( $params ) );
	}

	/** @return void */
	public function media_count(): void {
		$this->verify_and_get_params();
		$this->send( Api::instance()->media_count() );
	}

	/** @return void */
	public function update_single_media(): void {
		$params = $this->verify_and_get_params();
		$this->send( RenameModule::instance()->update_single_media( $params ) );
	}

	/** @return void */
	public function media_submit_bulk_action(): void {
		$params = $this->verify_and_get_params();
		$this->send( Api::instance()->media_submit_bulk_action( $params ) );
	}

	// -------------------------------------------------------------------------
	// Filters / Options
	// -------------------------------------------------------------------------

	/** @return void */
	public function get_dates(): void {
		$this->verify_and_get_params();
		$this->send( Api::instance()->get_dates() );
	}

	/** @return void */
	public function get_terms(): void {
		$this->verify_and_get_params();
		$this->send( Api::instance()->get_terms() );
	}

	/** @return void */
	public function get_options(): void {
		$this->verify_and_get_params();
		$this->send( Api::instance()->get_options() );
	}

	/** @return void */
	public function update_option(): void {
		$params = $this->verify_and_get_params();
		$this->send( Api::instance()->update_option( $params ) );
	}

	// -------------------------------------------------------------------------
	// Rubbish / Unlisted files
	// -------------------------------------------------------------------------

	/** @return void */
	public function get_rubbish_filetype(): void {
		$this->verify_and_get_params();
		$this->send( RubbishScanner::instance()->get_rubbish_filetype() );
	}

	/** @return void */
	public function get_rubbish_file(): void {
		$params = $this->verify_and_get_params();
		$this->send( RubbishScanner::instance()->get_rubbish_file( $params ) );
	}

	/** @return void */
	public function get_dir_list(): void {
		$this->verify_and_get_params();
		$this->send( RubbishScanner::instance()->get_dir_list() );
	}

	/** @return void */
	public function rescan_dir(): void {
		$params = $this->verify_and_get_params();
		$this->send( RubbishScanner::instance()->rescan_dir( $params ) );
	}

	/** @return void */
	public function search_file_by_dir(): void {
		$params = $this->verify_and_get_params();
		$this->send( RubbishScanner::instance()->immediately_search_rubbish_file( $params ) );
	}

	/** @return void */
	public function truncate_unlisted_file(): void {
		$this->verify_and_get_params();
		$this->send( RubbishScanner::instance()->delete_all_rows_in_unlisted_file() );
	}

	/** @return void */
	public function get_empty_directories(): void {
		$this->verify_and_get_params();
		$dirs = RubbishScanner::get_empty_directories();
		$this->send( [ 'updated' => true, 'directories' => $dirs ] );
	}

	/** @return void */
	public function delete_empty_directory(): void {
		$params = $this->verify_and_get_params();
		$this->send( RubbishScanner::instance()->delete_empty_directory( $params ) );
	}

	// -------------------------------------------------------------------------
	// Schedule / Image sizes / Plugins
	// -------------------------------------------------------------------------

	/** @return void */
	public function clear_schedule(): void {
		$this->verify_and_get_params();
		$this->send( RubbishScanner::instance()->clear_schedule() );
	}

	/** @return void */
	public function get_registered_image_sizes(): void {
		$this->verify_and_get_params();
		$this->send( ImageSizeModule::instance()->get_registered_image_size() );
	}

	/** @return void */
	public function get_plugin_list(): void {
		$this->verify_and_get_params();
		$this->send( Api::instance()->get_plugin_list() );
	}

	// -------------------------------------------------------------------------
	// AI content generation
	// -------------------------------------------------------------------------

	/** @return void */
	public function ai_generate(): void {
		$params        = $this->verify_and_get_params();
		$attachment_id = absint( $params['attachment_id'] ?? 0 );
		$field_type    = sanitize_key( $params['field_type'] ?? '' );
		try {
			$result = ( new AiApi() )->generate(
				[
					'attachment_id' => $attachment_id,
					'field_type'    => $field_type,
				]
			);
			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	// -------------------------------------------------------------------------
	// Duplicate detection
	// -------------------------------------------------------------------------

	/** @return void */
	public function duplicate_scan_batch(): void {
		$params = $this->verify_and_get_params();
		$offset = absint( $params['offset'] ?? 0 );
		$batch  = absint( $params['batch_size'] ?? 50 );
		$this->send( DuplicateScanner::instance()->scan_batch( $offset, $batch ) );
	}

	/** @return void */
	public function duplicate_get_results(): void {
		$params = $this->verify_and_get_params();
		$result = DuplicateScanner::instance()->get_duplicates( $params );
		wp_send_json_success( json_decode( $result, true ) );
	}

	/** @return void */
	public function duplicate_get_status(): void {
		$this->verify_and_get_params();
		$this->send( DuplicateScanner::instance()->get_scan_status() );
	}

	/** @return void */
	public function duplicate_clear(): void {
		$this->verify_and_get_params();
		$this->send( DuplicateScanner::instance()->clear_scan() );
	}

	// -------------------------------------------------------------------------
	// Used-Where image usage tracking
	// -------------------------------------------------------------------------

	/** @return void */
	public function used_where_scan_batch(): void {
		$params = $this->verify_and_get_params();
		$offset = absint( $params['offset'] ?? 0 );
		$batch  = absint( $params['batch_size'] ?? 20 );
		$result = UsedWhereScanner::instance()->scan_batch( $offset, $batch );
		// Update scan status in options.
		update_option( 'tsmlt_used_where_scan_status', array_merge( $result, [ 'timestamp' => current_time( 'mysql' ) ] ) );
		$this->send( $result );
	}

	/** @return void */
	public function used_where_get_results(): void {
		$params = $this->verify_and_get_params();
		$limit  = absint( $params['limit'] ?? 20 );
		$paged  = absint( $params['offset'] ?? 0 );
		$page   = $paged > 0 ? ( $paged / $limit ) + 1 : 1;
		$filter = sanitize_text_field( $params['filter'] ?? 'used' );
		$search = sanitize_text_field( $params['search'] ?? '' );

		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'paged'          => $page,
		];

		if ( $search ) {
			$args['s'] = $search;
		}

		if ( 'unused' === $filter ) {
			// Attachments that were scanned but have no recorded usages.
			$args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => UsedWhereScanner::META_KEY,
					'compare' => 'NOT EXISTS',
				],
			];
		} else {
			// Default: attachments that have usage meta (used images).
			$args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => UsedWhereScanner::META_KEY,
					'compare' => 'EXISTS',
				],
			];
		}

		$query = new \WP_Query( $args );

		$usages = [];
		foreach ( $query->posts as $post ) {
			$stats    = UsedWhereScanner::instance()->get_usage_stats( $post->ID );
			$usages[] = [
				'attachment_id' => $post->ID,
				'title'         => $post->post_title,
				'url'           => wp_get_attachment_url( $post->ID ),
				'usage_count'   => $stats['total_usage'],
				'usage_by_type' => $stats['by_type'],
				'used_in_posts' => count( $stats['by_post'] ),
				'posts'         => $stats['by_post'],
			];
		}

		$this->send( [
			'usages' => $usages,
			'total'  => $query->found_posts,
		] );
	}

	/** @return void */
	public function used_where_get_status(): void {
		$this->verify_and_get_params();
		$this->send( UsedWhereScanner::instance()->get_scan_status() );
	}

	/** @return void */
	public function used_where_clear(): void {
		$this->verify_and_get_params();
		$this->send( UsedWhereScanner::instance()->clear_scan() );
	}

	/** @return void */
	public function used_where_bulk_delete(): void {
		$params = $this->verify_and_get_params();
		$ids    = isset( $params['ids'] ) && is_array( $params['ids'] ) ? array_map( 'absint', $params['ids'] ) : [];

		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'No IDs provided.', 'media-library-tools' ) ], 400 );
		}

		$deleted = 0;
		foreach ( $ids as $attachment_id ) {
			if ( $attachment_id > 0 && wp_delete_attachment( $attachment_id, true ) ) {
				$deleted++;
			}
		}

		$this->send( [ 'deleted' => $deleted ] );
	}

	/** @return void */
	public function used_where_trash(): void {
		$params = $this->verify_and_get_params();
		$ids    = isset( $params['ids'] ) && is_array( $params['ids'] ) ? array_map( 'absint', $params['ids'] ) : [];

		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'No IDs provided.', 'media-library-tools' ) ], 400 );
		}

		$trashed = [];
		foreach ( $ids as $attachment_id ) {
			if ( $attachment_id > 0 && wp_trash_post( $attachment_id ) ) {
				$trashed[] = $attachment_id;
			}
		}

		$this->send( [ 'trashed' => $trashed ] );
	}

	/** @return void */
	public function used_where_untrash(): void {
		$params = $this->verify_and_get_params();
		$ids    = isset( $params['ids'] ) && is_array( $params['ids'] ) ? array_map( 'absint', $params['ids'] ) : [];

		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'No IDs provided.', 'media-library-tools' ) ], 400 );
		}

		$untrashed = [];
		foreach ( $ids as $attachment_id ) {
			if ( $attachment_id > 0 && wp_untrash_post( $attachment_id ) ) {
				$untrashed[] = $attachment_id;
			}
		}

		$this->send( [ 'untrashed' => $untrashed ] );
	}

	/** @return void */
	public function used_where_get_trashed(): void {
		$params = $this->verify_and_get_params();
		$limit  = absint( $params['limit'] ?? 10 );
		$paged  = absint( $params['offset'] ?? 0 );
		$page   = $paged > 0 ? ( $paged / $limit ) + 1 : 1;
		$search = sanitize_text_field( $params['search'] ?? '' );

		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'trash',
			'posts_per_page' => $limit,
			'paged'          => $page,
		];

		// Add search parameter if provided.
		if ( $search ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );
		$items = [];

		foreach ( $query->posts as $post ) {
			$attachment_id = $post->ID;
			// For trashed items, get URL from attached file path (wp_get_attachment_url returns empty for trashed).
			$url = '';
			$attached_file = get_attached_file( $attachment_id );
			if ( $attached_file ) {
				$upload_dir = wp_upload_dir();
				$url = $upload_dir['baseurl'] . '/' . str_replace( $upload_dir['basedir'] . '/', '', $attached_file );
			}

			$items[] = [
				'attachment_id' => $attachment_id,
				'title'         => $post->post_title,
				'url'           => $url,
			];
		}

		$this->send( [ 'items' => $items, 'total' => $query->found_posts ] );
	}

	// -------------------------------------------------------------------------
	// Regenerate Thumbnails
	// -------------------------------------------------------------------------

	/** @return void */
	public function regenerate_batch(): void {
		$params     = $this->verify_and_get_params();
		$offset     = absint( $params['offset'] ?? 0 );
		$batch_size = min( absint( $params['batch_size'] ?? 10 ), 50 );
		$this->send( RegenerateThumbnails::instance()->regenerate_batch( $offset, $batch_size ) );
	}

	/** @return void */
	public function regenerate_get_status(): void {
		$this->verify_and_get_params();

		$sizes      = [];
		$registered = wp_get_registered_image_subsizes();
		foreach ( $registered as $name => $size ) {
			$sizes[] = [
				'name'   => $name,
				'width'  => (int) ( $size['width'] ?? 0 ),
				'height' => (int) ( $size['height'] ?? 0 ),
				'crop'   => ! empty( $size['crop'] ),
			];
		}

		$this->send( [
			'total'       => RegenerateThumbnails::instance()->get_total(),
			'image_sizes' => $sizes,
		] );
	}

}
