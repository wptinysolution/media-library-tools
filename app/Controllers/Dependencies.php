<?php

namespace TheTinyTools\ME\Controllers;

use TheTinyTools\ME\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}


class Dependencies {

    use SingletonTrait;

	const MINIMUM_PHP_VERSION = '7.4';

	const PLUGIN_NAME = 'Media Edit';

	private $missing = [];
	/**
	 * @var bool
	 */
	private $allOk = true;

	/**
	 * @return bool
	 */
	public function check() {

		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'minimum_php_version' ] );
			$this->allOk = false;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'wp_create_nonce' ) ) {
			require_once ABSPATH . 'wp-includes/pluggable.php';
		}

		return $this->allOk;
	}

	/**
	 * Admin Notice For Required PHP Version
	 */
	public function minimum_php_version() {
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
		$message = sprintf(
		/* translators: 1: Plugin name 2: PHP 3: Required PHP version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'wp-media' ),
			'<strong>' . esc_html__( 'Media Edit', 'wp-media' ) . '</strong>',
			'<strong>' . esc_html__( 'PHP', 'wp-media' ) . '</strong>',
			self::MINIMUM_PHP_VERSION
		);
		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
	}


}
