<?php

namespace TheTinyTools\ME\Controllers;


use TheTinyTools\ME\Helpers\Fns;
use TheTinyTools\ME\Traits\SingletonTrait;

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
		$this->version = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? time() : TTTME_VERSION;

		/**
		 * Admin scripts.
		 */
		add_action( 'admin_enqueue_scripts', [ $this, 'register_backend_assets' ], 1 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_backend_scripts' ] );

	}


	/**
	 * Registers Admin scripts.
	 *
	 * @return void
	 */
	public function register_backend_assets() {
        $this->styles[] = [
            'handle' => 'rtsb-fonts',
            'src'    => tttme()->get_assets_uri( 'fonts/rtsbfont.css' ),
        ];

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
	 * Enqueues admin scripts.
	 *
	 * @param string $hook Hooks.
	 * @return void
	 */
	public function enqueue_backend_scripts( $hook ) {
        if ( 'edit.php' != $hook ) {
            return;
        }
	}


}
