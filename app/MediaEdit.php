<?php
/**
 * Main initialization class.
 *
 * @package RadiusTheme\SB
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

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
		 * Nonce Text
		 *
		 * @var string
		 */
		public $nonceText = 'rtsb_nonce';
		/**
		 * Post Type.
		 *
		 * @var string
		 */
		public $post_type;
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
			register_activation_hook( RTSB_FILE, [ $installation, 'activate' ] );

			// Register Plugin Deactivate Hook.
			register_deactivation_hook( RTSB_FILE, [ $installation, 'deactivation' ] );
		}

		/**
		 * Constants
		 *
		 * @return void
		 */
		private function define_constants() {
			if ( ! defined( 'RTSB_ABSPATH' ) ) {
				define( 'RTSB_ABSPATH', dirname( RTSB_FILE ) . '/' );
			}

			if ( ! defined( 'RTSB_URL' ) ) {
				define( 'RTSB_URL', plugins_url( '', RTSB_FILE ) );
			}
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

			return trailingslashit( RTSB_URL . '/assets' ) . $file;
		}

		/**
		 * Get the template path.
		 *
		 * @return string
		 */
		public function get_template_path() {
			return apply_filters( 'rtsb_template_path', 'media-edit/' );
		}

		/**
		 * Get the plugin path.
		 *
		 * @return string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( RTSB_FILE ) );
		}

		/**
		 * Load Text Domain
		 */
		public function language() {
			load_plugin_textdomain( 'shopbuilder', false, dirname( plugin_basename( RTSB_FILE ) ) . '/languages/' );
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

			do_action( 'rtsb/before_loaded' );
			// Plugins Setting Page.
			add_filter(
				'plugin_action_links_' . RTSB_FILE,
				[
					Installation::instance(),
					'plugins_setting_links',
				]
			);

			// Include File.
			AssetsController::instance();
			SupportController::instance();
			ModuleManager::instance();
			AddToCart::instance();

			BuilderController::instance();
			FilterHooks::init_hooks();
			ActionHooks::init_hooks();

			if ( is_admin() ) {
				AdminInit::instance();
			}

			do_action( 'rtsb/after_loaded' );
		}

		/**
		 * Checks if Pro version installed
		 *
		 * @return boolean
		 */
		public function has_pro() {
			return function_exists( 'rtsbp' );
		}

		/**
		 * PRO Version URL.
		 *
		 * @return string
		 */
		public function pro_version_link() {
			return 'https://www.radiustheme.com/downloads/shopbuilder/';
		}
	}

	/**
	 * @return ShopBuilder
	 */
	function rtsb() {
		return ShopBuilder::instance();
	}

	rtsb();
}
