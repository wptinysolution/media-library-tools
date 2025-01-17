<?php
/**
 * Main ActionHooks class.
 *
 * @package TinySolutions\boilerplate
 */

namespace TinySolutions\mlt\Controllers\Hooks;

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit();

/**
 * Main ActionHooks class.
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
		add_action( 'wp_ajax_immediately_search_rubbish_file', [ $this, 'search_rubbish_file' ] );
	}

	/**
	 * Set Default Template.
	 *
	 * @return void
	 */
	public function search_rubbish_file() {
		check_ajax_referer(tsmlt()->nonceId, 'nonce'); // Security check
		$directory = isset($_POST['directory']) ? sanitize_text_field($_POST['directory']) : null;
		if (!$directory) {
			wp_send_json_error([
				'message' => 'Error: Directory parameter is required.',
			]);
		}
		$updated = Fns::update_rubbish_file_to_database( $directory );
		$dirlist = get_option( 'tsmlt_get_directory_list', [] );
		if ( ! empty( $dirlist[ $directory ] ) ) {
			if ( isset( $dirlist[ $directory ]['total_items'] ) && isset( $dirlist[ $directory ]['counted'] ) ) {
				$directory = absint( $dirlist[ $directory ]['total_items'] ) > absint( $dirlist[ $directory ]['counted'] ) ? $directory : 'nextDir';
			}
		}
		wp_send_json_success( [
			'dirlist' => $dirlist,
			'nextDir' => $directory
		] );
	}
}
