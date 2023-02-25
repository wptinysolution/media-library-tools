<?php
/**
 * Main initialization class.
 *
 * @package TheTinyTools\WM
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
require_once __DIR__ . './../vendor/autoload.php';

use TheTinyTools\WM\Traits\SingletonTrait;
use TheTinyTools\WM\Controllers\Installation;
use TheTinyTools\WM\Controllers\Dependencies;
use TheTinyTools\WM\Controllers\AssetsController;
use TheTinyTools\WM\Controllers\Hooks\FilterHooks;
use TheTinyTools\WM\Controllers\Hooks\ActionHooks;
use TheTinyTools\WM\Controllers\Admin\SubMenu;
use TheTinyTools\WM\Controllers\Admin\Api;
use TheTinyTools\WM\Controllers\Admin\RegisterPostAndTax;
use TheTinyTools\WM\Controllers\Admin\Review;

if ( ! class_exists( TTTWpMedia::class ) ) {
	/**
	 * Main initialization class.
	 */
	final class TTTWpMedia {

		/**
		 * Nonce id
		 *
		 * @var string
		 */
		public $nonceId = 'tttwp_wpnonce';

		/**
		 * Post Type.
		 *
		 * @var string
		 */
		public $current_theme;
        /**
         * Post Type.
         *
         * @var string
         */
        public $category = 'tttwp_category';
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
			// Register Plugin Active Hook.
			register_activation_hook( TTTWM_FILE, [ Installation::class, 'activate' ] );
			// Register Plugin Deactivate Hook.
			register_deactivation_hook( TTTWM_FILE, [ Installation::class, 'deactivation' ] );



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
			return trailingslashit( TTTWM_URL . '/assets' ) . $file;
		}

		/**
		 * Get the template path.
		 *
		 * @return string
		 */
		public function get_template_path() {
			return apply_filters( 'tttwp_template_path', 'templates/' );
		}

		/**
		 * Get the plugin path.
		 *
		 * @return string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( TTTWM_FILE ) );
		}

		/**
		 * Load Text Domain
		 */
		public function language() {
			load_plugin_textdomain( 'ttt-wp-media', false, TTTWM_ABSPATH . '/languages/' );
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

			do_action( 'tttwp/before_loaded' );

            Review::instance();
			// Include File.
            AssetsController::instance();
            SubMenu::instance();
            RegisterPostAndTax::instance();
            FilterHooks::init_hooks();
			ActionHooks::init_hooks();
            Api::instance();

			do_action( 'tttwp/after_loaded' );
		}

		/**
		 * Checks if Pro version installed
		 *
		 * @return boolean
		 */
		public function has_pro() {
			return function_exists( 'tttwpp' );
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
	 * @return WpMedia
	 */
	function tttwm() {
		return TTTWpMedia::instance();
	}

	tttwm();
}
