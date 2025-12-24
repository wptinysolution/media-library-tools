<?php

namespace TinySolutions\mlt\Controllers;

use TinySolutions\mlt\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Dependencies
 */

class Dependencies {
    /**
     * Singleton
     */
    use SingletonTrait;

	const MINIMUM_PHP_VERSION = '7.4';

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
		return $this->allOk;
	}

	/**
	 * Admin Notice For Required PHP Version
	 */
	public function minimum_php_version() {
		$message = sprintf(
		/* translators: 1: Plugin name 2: PHP 3: Required PHP version */
			__( '"%1$s" requires "%2$s" version %3$s or greater.', 'media-library-tools' ),
			'<strong>' . esc_html__( 'Media Library Tools', 'media-library-tools' ) . '</strong>',
			'<strong>' . esc_html__( 'PHP', 'media-library-tools' ) . '</strong>',
			esc_html( self::MINIMUM_PHP_VERSION )
		);
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			wp_kses_post( $message )
		);
	}


}
