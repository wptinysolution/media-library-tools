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
		Fns::scan_rubbish_file_cron_job();
		$dirlist = get_option( 'tsmlt_get_directory_list', [] );
		$dir = [];
		if ( ! empty( $dirlist ) ) {
			foreach ( $dirlist as $key => $item ) {
				if ( absint( $item['total_items'] ) && ( absint( $item['total_items'] ) <= absint( $item['counted'] ) ) ) {
					continue;
				}
				if ( 'available' !== ( $item['status'] ?? 'available' ) ) {
					continue;
				}
				$dir[$key] = $item;
			}
		}
		//error_log( print_r( $dir , true) . "\n\n", 3, __DIR__ . '/log.txt' );
		wp_send_json_success( [
			'dirList' => $dir,
			'dirStatusList' => $dirlist
		] );
	}
}
