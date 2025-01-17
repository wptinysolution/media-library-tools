<?php
/**
 * Main ActionHooks class.
 *
 * @package TinySolutions\boilerplate
 */

namespace TinySolutions\mlt\Controllers\Hooks;

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
		error_log( print_r( 'Hello', true) . "\n\n", 3, __DIR__ . '/log.txt' );
		wp_send_json_success( true );
	}
}
