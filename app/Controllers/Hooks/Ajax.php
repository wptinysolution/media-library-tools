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
		// Return image usage data grouped by attachment.
		$limit  = absint( $params['limit'] ?? 20 );
		$offset = absint( $params['offset'] ?? 0 );

		$results = Fns::DB()->select( 'attachment_id' )
			->from( 'tsmlt_image_usage' )
			->groupBy( 'attachment_id' )
			->limit( $limit )
			->offset( $offset )
			->get();

		if ( empty( $results ) ) {
			$this->send(
				[
					'usages' => [],
					'total'  => 0,
				]
			);
			return;
		}

		$usages = [];
		foreach ( $results as $row ) {
			$attachment_id = absint( $row['attachment_id'] );
			$stats         = UsedWhereScanner::instance()->get_usage_stats( $attachment_id );
			$post          = get_post( $attachment_id );
			if ( $post ) {
				$usages[] = [
					'attachment_id' => $attachment_id,
					'title'         => $post->post_title,
					'url'           => wp_get_attachment_url( $attachment_id ),
					'usage_count'   => $stats['total_usage'],
					'usage_by_type' => $stats['by_type'],
					'used_in_posts' => count( $stats['by_post'] ),
					'posts'         => $stats['by_post'],
				];
			}
		}

		// Get total count (unique attachments).
		$total_result = Fns::DB()->select( 'attachment_id' )
			->from( 'tsmlt_image_usage' )
			->groupBy( 'attachment_id' )
			->get();

		$total = is_array( $total_result ) ? count( $total_result ) : 0;

		$this->send(
			[
				'usages' => $usages,
				'total'  => $total,
			]
		);
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
}
