<?php
/**
 * Main initialization class.
 *
 * @package TheTinyTools\ME
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TheTinyTools\ME\Traits\SingletonTrait;
use TheTinyTools\ME\Controllers\Admin\Installation;
use TheTinyTools\ME\Controllers\Dependencies;
use TheTinyTools\ME\Controllers\AssetsController;
use TheTinyTools\ME\Controllers\Hooks\FilterHooks;
use TheTinyTools\ME\Controllers\Hooks\ActionHooks;

require_once __DIR__ . './../vendor/autoload.php';

if ( ! class_exists( MediaEdit::class ) ) {
	/**
	 * Main initialization class.
	 */
	final class MediaEdit {

		/**
		 * Nonce id
		 *
		 * @var string
		 */
		public $nonceId = '__tttme_wpnonce';

		/**
		 * Post Type.
		 *
		 * @var string
		 */
		public $current_theme;
		/**
		 * Singleton
		 */
		use SingletonTrait;

		/**
		 * Class Constructor
		 */
		private function __construct() {

			$this->current_theme = wp_get_theme()->get( 'TextDomain' );

			add_action( 'init', [ $this, 'language' ] );
			add_action( 'plugins_loaded', [ $this, 'init' ], 100 );

			$installation = Installation::instance();

			// Register Plugin Active Hook.

			// Register Plugin Deactivate Hook.
			register_deactivation_hook( TTTME_FILE, [ $installation, 'deactivation' ] );

            // echo $this->plugin_path();
            // echo $this->get_assets_uri('style.css');
             echo $this->get_template_path();

            // echo '</br>';
            // echo TTTME_ABSPATH . '/languages/';
		}

		/**
		 * Assets url generate with given assets file
		 *
		 * @param string $file File.
		 *
		 * @return string
		 */
		public function get_assets_uri( $file ) {
			$file = ltrim( $file, '/' );

			return trailingslashit( TTTME_URL . '/assets' ) . $file;
		}

		/**
		 * Get the template path.
		 *
		 * @return string
		 */
		public function get_template_path() {
			return apply_filters( 'tttme_template_path', 'media-edit/' );
		}

		/**
		 * Get the plugin path.
		 *
		 * @return string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( TTTME_FILE ) );
		}

		/**
		 * Load Text Domain
		 */
		public function language() {
			load_plugin_textdomain( 'media-edit', false, TTTME_ABSPATH . '/languages/' );
		}

		/**
		 * Init
		 *
		 * @return void
		 */
		public function init() {
			if ( ! Dependencies::instance()->check() ) {
				return;
			}

			do_action( 'tttme/before_loaded' );
			// Plugins Setting Page.
			add_filter(
				'plugin_action_links_' . TTTME_BASENAME,
				[
					Installation::instance(),
					'plugins_setting_links',
				]
			);

			// Include File.
			AssetsController::instance();
			FilterHooks::init_hooks();
			ActionHooks::init_hooks();


			do_action( 'tttme/after_loaded' );
		}

		/**
		 * Checks if Pro version installed
		 *
		 * @return boolean
		 */
		public function has_pro() {
			return function_exists( 'tttmep' );
		}

		/**
		 * PRO Version URL.
		 *
		 * @return string
		 */
		public function pro_version_link() {
			return '#';
		}
	}

	/**
	 * @return MediaEdit
	 */
	function tttme() {
		return MediaEdit::instance();
	}

	tttme();
}
