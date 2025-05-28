<?php

namespace TinySolutions\mlt\Controllers;

use TinySolutions\mlt\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * AssetsController
 */
class AssetsController {
	/**
	 * Singleton
	 */
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
	 * Class Constructor
	 */
	public function __construct() {
		$this->version = time(); // ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? time() : TSMLT_VERSION;
		/**
		 * Admin scripts.
		 */
		add_action( 'admin_enqueue_scripts', [ $this, 'backend_assets' ], 99 );
	}


	/**
	 * Registers Admin scripts.
	 *
	 * @return void
	 */
	public function backend_assets( $hook ) {

		$scripts = [
			[
				'handle' => 'tsmlt-settings',
				'src'    => tsmlt()->get_assets_uri( 'js/backend/admin-settings.js' ),
				'deps'   => [],
				'footer' => true,
			],
		];

		// Register public scripts.
		foreach ( $scripts as $script ) {
			wp_register_script( $script['handle'], $script['src'], $script['deps'], $this->version, $script['footer'] );
		}

		$styles = [
			[
				'handle' => 'tsmlt-settings-style',
				'src'    => tsmlt()->get_assets_uri( 'css/backend/admin-settings.css' ),
			],
		];

		// Register public styles.
		foreach ( $styles as $style ) {
			wp_register_style( $style['handle'], $style['src'], [], $this->version );
		}

		global $pagenow;

		if ( 'upload.php' === $pagenow ) {

			if ( ! empty( $_GET['page'] ) && 'media-library-tools' === $_GET['page'] ) {
				// Enqueue ThickBox scripts and styles.
				wp_enqueue_script( 'thickbox' );
				wp_enqueue_style( 'thickbox' );
				wp_enqueue_script( 'tsmlt-settings' );

				// WPml Create Issue.
				wp_dequeue_style( 'wpml-tm-styles' );
				wp_dequeue_script( 'wpml-tm-scripts' );

				$upload_dir = wp_upload_dir(); // Get the upload directory path.
				wp_localize_script(
					'tsmlt-settings',
					'tsmltParams',
					[
						'ajaxUrl'        => esc_url( admin_url( 'admin-ajax.php' ) ),
						'adminUrl'       => esc_url( admin_url() ),
						'hasExtended'    => tsmlt()->has_pro(),
						'proVersion'     => defined( 'TSMLTPRO_VERSION' ) ? TSMLTPRO_VERSION : false,
						'proLink'        => tsmlt()->pro_version_link(),
						'includesUrl'    => esc_url( includes_url() ),
						'uploadUrl'      => esc_url( set_url_scheme( $upload_dir['baseurl'] ?? '#' ) ),
						'uploadBasedir'  => $upload_dir['basedir'] ?? '',
						'hasWoo'         => function_exists( 'WC' ),
						'restApiUrl'     => esc_url_raw( rest_url() ),
						'rest_nonce'     => wp_create_nonce( 'wp_rest' ),
						tsmlt()->nonceId => wp_create_nonce( tsmlt()->nonceId ),
					]
				);
			}
		}
	}
}
