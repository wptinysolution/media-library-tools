<?php
/**
 * Main initialization class.
 *
 * @package TinySolutions\mlt
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

require_once TSMLT_PATH . 'vendor/autoload.php';

use TinySolutions\mlt\Controllers\Admin\Api;
use TinySolutions\mlt\Controllers\Admin\RegisterPostAndTax;
use TinySolutions\mlt\Controllers\Admin\SubMenu;
use TinySolutions\mlt\Controllers\AssetsController;
use TinySolutions\mlt\Controllers\Dependencies;
use TinySolutions\mlt\Controllers\Hooks\ActionHooks;
use TinySolutions\mlt\Controllers\Hooks\CronJobHooks;
use TinySolutions\mlt\Controllers\Hooks\FilterHooks;
use TinySolutions\mlt\Controllers\Hooks\Ajax;
use TinySolutions\mlt\Controllers\Installation;
use TinySolutions\mlt\Controllers\Notice\Review;
use TinySolutions\mlt\Controllers\Notice\SpecialDiscount;
use TinySolutions\mlt\Traits\SingletonTrait;

if ( ! class_exists( Tsmlt::class ) ) {
	/**
	 * Main initialization class.
	 */
	final class Tsmlt {

		/**
		 * Nonce id
		 *
		 * @var string
		 */
		public $nonceId = 'tsmlt_wpnonce';

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
		public $category = 'tsmlt_category';
		/**
		 * Singleton
		 */
		use SingletonTrait;

		/**
		 * Class Constructor
		 */
		private function __construct() {
			$this->current_theme = wp_get_theme()->get( 'TextDomain' );
			// add_action( 'init', [ $this, 'language' ] );
			add_action( 'plugins_loaded', [ $this, 'init' ], 100 );
			add_action( 'plugins_loaded', [ Installation::class, 'migration' ], 100 );
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
			return trailingslashit( TSMLT_URL . '/assets' ) . $file;
		}

		/**
		 * Get the template path.
		 *
		 * @return string
		 */
		public function get_template_path() {
			return apply_filters( 'tsmlt_template_path', 'templates/' );
		}

		/**
		 * Get the plugin path.
		 *
		 * @return string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( TSMLT_FILE ) );
		}

		/**
		 * Load Text Domain
		 */
		public function language() {
			load_plugin_textdomain( 'media-library-tools', false, TSMLT_ABSPATH . '/languages/' );
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

			do_action( 'tsmlt/before_loaded' );
			Ajax::instance();
			Api::instance();
			FilterHooks::init_hooks();
			ActionHooks::instance();
			CronJobHooks::instance();
			if ( is_admin() ) {
				SpecialDiscount::instance();
				Review::instance();
				// Include File.
				AssetsController::instance();
				SubMenu::instance();
			}
			RegisterPostAndTax::instance();
			do_action( 'tsmlt/after_loaded' );
		}

		/**
		 * Checks if Pro version installed
		 *
		 * @return boolean
		 */
		public function has_pro() {
			if ( function_exists( 'tsmltpro' ) && version_compare( TSMLTPRO_VERSION, '1.2.3', '>=' ) ) {
				// Decrypt the license value.
				$is_valid = 'e655d5f802d3d9724f02f3af4e71a0dc' === ( defined( 'TINY_DEBUG_TSMLT_PRO_1_2_3' ) ? md5( TINY_DEBUG_TSMLT_PRO_1_2_3 ) : '' );
				return tsmltpro()->user_can_use_tsmltpro() || $is_valid;
			}
			return false;
		}

		/**
		 * PRO Version URL.
		 *
		 * @return string
		 */
		public function pro_version_link() {
			return 'https://www.wptinysolutions.com/tiny-products/media-library-tools/';
		}
	}
	/**
	 * @return Tsmlt
	 */
	function tsmlt() {
		return Tsmlt::instance();
	}

	tsmlt();
}

// Register Plugin Active Hook.
register_activation_hook( TSMLT_FILE, [ Installation::class, 'activate' ] );
// Register Plugin Deactivate Hook.
register_deactivation_hook( TSMLT_FILE, [ Installation::class, 'deactivation' ] );
