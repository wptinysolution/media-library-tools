<?php

namespace RadiusTheme\SB\Controllers;

use RadiusTheme\SB\Helpers\BuilderFns;
use RadiusTheme\SB\Helpers\Fns;
use RadiusTheme\SB\Models\Settings;
use RadiusTheme\SB\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class AssetsController {

	use SingletonTrait;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Ajax URL
	 *
	 * @var string
	 */
	private $ajaxurl;

	/**
	 * Styles.
	 *
	 * @var array
	 */
	private $styles = [];

	/**
	 * Scripts.
	 *
	 * @var array
	 */
	private $scripts = [];

	/**
	 * Class Constructor
	 */
	public function __construct() {
		$this->version = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? time() : RTSB_VERSION;
		if ( in_array( 'sitepress-multilingual-cms/sitepress.php', get_option( 'active_plugins' ) ) ) {
			$this->ajaxurl = admin_url( 'admin-ajax.php?lang=' . ICL_LANGUAGE_CODE );
		} else {
			$this->ajaxurl = admin_url( 'admin-ajax.php' );
		}

		/**
		 * Admin scripts.
		 */
		add_action( 'admin_enqueue_scripts', [ $this, 'register_backend_assets' ], 1 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_backend_scripts' ] );

		/**
		 * Public scripts.
		 */
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_scripts' ], 15 );
	}

	/**
	 * Get all frontend scripts.
	 *
	 * @return void
	 */
	private function get_public_assets() {
		$this->get_public_styles()->get_public_scripts();
	}

	/**
	 * Get public styles.
	 *
	 * @return object
	 */
	private function get_public_styles() {
		$this->styles[] = [
			'handle' => 'rtsb-fonts',
			'src'    => rtsb()->get_assets_uri( 'fonts/rtsbfont.css' ),
		];

		$this->styles[] = [
			'handle' => 'builder-page-frontend',
			'src'    => rtsb()->get_assets_uri( 'css/frontend/builder-frontend.css' ),
		];

		$this->styles[] = [
			'handle' => 'rtsb-frontend',
			'src'    => rtsb()->get_assets_uri( 'css/frontend/general-frontend.css' ),
		];

		// if ( BuilderFns::is_product() ) {
			// wp_deregister_style( 'woocommerce-general' );
			// $this->styles[] = [
			// 'handle' => 'woocommerce-general',
			// 'src'    => plugins_url( 'assets/css/woocommerce.css', WC_PLUGIN_FILE ),
			// ];
		// }

		$this->styles[] = [
			'handle' => 'swiper',
			'src'    => rtsb()->get_assets_uri( 'vendor/swiper/css/swiper-bundle.min.css' ),
		];

		if ( BuilderFns::is_builder_preview() && 'elementor' == Fns::page_edit_with( get_the_ID() ) ) {
			$this->styles[] = [
				'handle' => 'photoswipe',
				'src'    => plugins_url( 'assets/css/photoswipe/photoswipe.min.css', WC_PLUGIN_FILE ),
			];

			$this->styles[] = [
				'handle' => 'photoswipe-default-skin',
				'src'    => plugins_url( 'assets/css/photoswipe/default-skin/default-skin.min.css', WC_PLUGIN_FILE ),
			];

			// Load only for elementor editor page and fix some issue.
			$this->styles[] = [
				'handle' => 'elementor-editor-style-fix',
				'src'    => rtsb()->get_assets_uri( 'css/backend/elementor-editor-style-fix.css' ),
			];
		}

		$this->styles[] = [
			'handle' => 'rtsb-public',
			'src'    => rtsb()->get_assets_uri( 'css/frontend/global.css' ),
		];

		return $this;
	}

	/**
	 * Get public scripts.
	 *
	 * @return object
	 */
	private function get_public_scripts() {
		$this->scripts[] = [
			'handle' => 'builder-page-frontend',
			'src'    => rtsb()->get_assets_uri( 'js/frontend/builder-frontend.js' ),
			'deps'   => [ 'jquery' ],
			'footer' => true,
		];

		$this->scripts[] = [
			'handle' => 'swiper',
			'src'    => rtsb()->get_assets_uri( 'vendor/swiper/js/swiper-bundle.min.js' ),
			'deps'   => [ 'jquery' ],
			'footer' => true,
		];

		$this->scripts[] = [
			'handle' => 'rtsb-imagesloaded',
			'src'    => rtsb()->get_assets_uri( 'vendor/isotope/imagesloaded.pkgd.min.js' ),
			'deps'   => [ 'jquery' ],
			'footer' => true,
		];

		$this->scripts[] = [
			'handle' => 'rtsb-tipsy',
			'src'    => rtsb()->get_assets_uri( 'vendor/tipsy/tipsy.min.js' ),
			'deps'   => [ 'jquery' ],
			'footer' => true,
		];

		if ( BuilderFns::is_builder_preview() && 'elementor' == Fns::page_edit_with( get_the_ID() ) ) {

			$this->scripts[] = [
				'handle' => 'flexslider',
				'src'    => plugins_url( 'assets/js/flexslider/jquery.flexslider.js', WC_PLUGIN_FILE ),
				'deps'   => [ 'jquery' ],
				'footer' => true,
			];

			$this->scripts[] = [
				'handle' => 'photoswipe',
				'src'    => plugins_url( 'assets/js/photoswipe/photoswipe.js', WC_PLUGIN_FILE ),
				'deps'   => [ 'jquery' ],
				'footer' => true,
			];

			$this->scripts[] = [
				'handle' => 'zoom',
				'src'    => plugins_url( 'assets/js/zoom/jquery.zoom.js', WC_PLUGIN_FILE ),
				'deps'   => [ 'jquery' ],
				'footer' => true,
			];

			$this->scripts[] = [
				'handle' => 'photoswipe-ui-default',
				'src'    => plugins_url( 'assets/js/photoswipe/photoswipe-ui-default.js', WC_PLUGIN_FILE ),
				'deps'   => [ 'jquery', 'photoswipe' ],
				'footer' => true,
			];

			$this->scripts[] = [
				'handle' => 'wc-single-product',
				'src'    => plugins_url( 'assets/js/frontend/single-product.js', WC_PLUGIN_FILE ),
				'deps'   => [ 'jquery', 'flexslider', 'photoswipe', 'photoswipe-ui-default', 'zoom' ],
				'footer' => true,
			];

		}

		$this->scripts[] = [
			'handle' => 'rtsb-public',
			'src'    => rtsb()->get_assets_uri( 'js/frontend/general-frontend.js' ),
			'deps'   => [ 'jquery' ],
			'footer' => true,
		];

		return $this;
	}

	/**
	 * Register public scripts.
	 *
	 * @return void
	 */
	public function register_public_scripts() {
		$this->get_public_assets();

		// Register public styles.
		foreach ( $this->styles as $style ) {
			wp_register_style( $style['handle'], $style['src'], '', $this->version );
		}

		// Register public scripts.
		foreach ( $this->scripts as $script ) {
			wp_register_script( $script['handle'], $script['src'], $script['deps'], $this->version, $script['footer'] );
		}
	}

	/**
	 * Enqueues public scripts.
	 *
	 * @return void
	 */
	public function enqueue_public_scripts() {
		/**
		 * Register scripts.
		 */
		$this->register_public_scripts();
		/**
		 * Enqueue scripts.
		 */
		if ( BuilderFns::is_builder_preview() && 'elementor' === Fns::page_edit_with( get_the_ID() ) ) {
			/**
			 * Styles.
			 */
			wp_enqueue_style( 'swiper' );
			wp_enqueue_style( 'photoswipe' );
			wp_enqueue_style( 'photoswipe-default-skin' );
			wp_enqueue_style( 'elementor-editor-style-fix' );

            wp_enqueue_style( 'woocommerce-general' );


			/**
			 * Scripts.
			 */
			wp_enqueue_script( 'flexslider' );
			wp_enqueue_script( 'wc-single-product' );
			wp_dequeue_script( 'builder-page-frontend' );
			wp_enqueue_script( 'swiper' );
			wp_enqueue_script( 'builder-page-frontend' );
		}

		/**
		 * Styles.
		 */
		wp_enqueue_style( 'rtsb-fonts' );
		wp_enqueue_style( 'rtsb-public' );
		wp_enqueue_style( 'rtsb-frontend' );

		/**
		 * Scripts.
		 */
		wp_enqueue_script( 'rtsb-imagesloaded' );
		wp_enqueue_script( 'rtsb-tipsy' );
		wp_enqueue_script( 'rtsb-public' );

		wp_localize_script(
			'rtsb-public',
			'rtsbPublicParams',
			[
				'ajaxUrl'         => esc_url( $this->ajaxurl ),
				'wcCartUrl'       => wc_get_cart_url(),
				'addedToCartText' => esc_html__( 'Product Added', 'shopbuilder' ),
				'browseCartText'  => esc_html__( 'Browse Cart', 'shopbuilder' ),
				rtsb()->nonceId   => wp_create_nonce( rtsb()->nonceText ),
			]
		);
	}

	/**
	 * Registers Admin scripts.
	 *
	 * @return void
	 */
	public function register_backend_assets() {
		/**
		 * Styles.
		 */
		wp_register_style( 'rtsb-admin-app', rtsb()->get_assets_uri( 'css/backend/admin-settings.css' ), '', $this->version );
		wp_register_style( 'rtsb-admin-global', rtsb()->get_assets_uri( 'css/backend/admin-global.css' ), '', $this->version );
		wp_register_style( 'rtsb-templatebuilder', rtsb()->get_assets_uri( 'css/backend/template-builder.css' ), '', $this->version );

		/**
		 * Scripts.
		 */
		wp_register_script( 'rtsb-admin-app', rtsb()->get_assets_uri( 'js/backend/admin-settings.js' ), '', $this->version, true );
		wp_register_script( 'rtsb-templatebuilder', rtsb()->get_assets_uri( 'js/backend/template-builder.js' ), '', $this->version, true );
	}

	/**
	 * Enqueues admin scripts.
	 *
	 * @param string $hook Hooks.
	 * @return void
	 */
	public function enqueue_backend_scripts( $hook ) {
		
		wp_enqueue_style( 'rtsb-admin-global' );

		if ( 'shopbuilder_page_rtsb-settings' === $hook ) {
			/**
			 * Styles.
			 */
			wp_enqueue_style( 'rtsb-admin-app' );

			/**
			 * Scripts.
			 */
			wp_enqueue_script( 'rtsb-admin-app' );
			wp_localize_script(
				'rtsb-admin-app',
				'rtsbParams',
				[
					'ajaxurl'  => esc_url( $this->ajaxurl ),
					'nonce'    => wp_create_nonce( rtsb()->nonceText ),
					'sections' => Settings::instance()->get_sections(),
					'pages'    => Fns::get_pages(),
				]
			);
		} else {
			$current_screen = get_current_screen();

			if ( 'edit-rtsb_builder' === $current_screen->id ) {
				/**
				 * Styles.
				 */
				wp_enqueue_style( 'rtsb-templatebuilder' );

				/**
				 * Scripts.
				 */
				wp_enqueue_script( 'rtsb-templatebuilder' );
				wp_localize_script(
					'rtsb-templatebuilder',
					'rtsbParams',
					[
						'ajaxurl'       => esc_url( $this->ajaxurl ),
						rtsb()->nonceId => wp_create_nonce( rtsb()->nonceText ),
					]
				);
			}
		}
	}
}
