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
		$this->version = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? time() : TSMLT_VERSION;
		/**
		 * Admin scripts.
		 */
		add_action( 'admin_enqueue_scripts', [ $this, 'backend_assets' ], 1 );
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
			]
		];

		// Register public scripts.
		foreach ( $scripts as $script ) {
			wp_register_script( $script['handle'], $script['src'], $script['deps'], $this->version, $script['footer'] );
		}

		global $pagenow;

		if ( 'upload.php' === $pagenow && ! empty( $_GET['page'] ) && 'tsmlt-media-tools' === $_GET['page'] ) {

			wp_enqueue_style( 'tsmlt-settings' );
			wp_enqueue_script( 'tsmlt-settings' );
			$upload_dir = wp_upload_dir(); // Get the upload directory path

			wp_localize_script(
				'tsmlt-settings',
				'tsmltParams',
				[
					'ajaxUrl'        => esc_url( admin_url( 'admin-ajax.php' ) ),
					'adminUrl'       => esc_url( admin_url() ),
					'hasExtended'    => tsmlt()->has_pro(),
					'proLink'        => 'https://checkout.freemius.com/mode/dialog/plugin/13159/plan/22377/',
					'includesUrl'    => esc_url( includes_url() ),
					'uploadUrl'      => esc_url( $upload_dir['baseurl'] ?? '#' ),
					'uploadBasedir'  => $upload_dir['basedir'] ?? '',
					'restApiUrl'     => esc_url_raw( rest_url() ), // site_url(rest_get_url_prefix()),
					'rest_nonce'     => wp_create_nonce( 'wp_rest' ),
					tsmlt()->nonceId => wp_create_nonce( tsmlt()->nonceId ),
				]
			);

		}

	}


}
