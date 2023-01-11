<?php

namespace RadiusTheme\SB\Controllers;

use RadiusTheme\SB\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}


class Dependencies {

	const MINIMUM_PHP_VERSION = '7.4';

	const PLUGIN_NAME = 'ShopBuilder - Woocommerce Builder for Elementor & Gutenberg';

	use SingletonTrait;

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
		// Check WooCommerce
		$woocommerce = 'woocommerce/woocommerce.php';

		if ( ! is_plugin_active( $woocommerce ) ) {
			if ( $this->is_plugins_installed( $woocommerce ) ) {
				$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $woocommerce . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $woocommerce );
				$message        = sprintf(
					'<strong>%s</strong> %s <strong>%s</strong> %s',
					esc_html( self::PLUGIN_NAME ),
					esc_html__( 'requires', 'shopbuilder' ),
					esc_html__( 'WooCommerce', 'shopbuilder' ),
					esc_html__( 'plugin to be active. Please activate WooCommerce to continue.', 'shopbuilder' )
				);
				$button_text    = esc_html__( 'Activate WooCommerce', 'shopbuilder' );

			} else {
				$activation_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ), 'install-plugin_woocommerce' );
				$message        = sprintf(
					'<strong>%s</strong> %s <strong>%s</strong> %s',
					esc_html( self::PLUGIN_NAME ),
					esc_html__( 'requires', 'shopbuilder' ),
					esc_html__( 'WooCommerce', 'shopbuilder' ),
					esc_html__( 'plugin to be installed and activated. Please install WooCommerce to continue.', 'shopbuilder' )
				);
				$button_text    = esc_html__( 'Install WooCommerce', 'shopbuilder' );
			}
			$this->missing['woocommerce'] = [
				'name'       => 'WooCommerce',
				'slug'       => 'woocommerce',
				'file_name'  => $woocommerce,
				'url'        => $activation_url,
				'message'    => $message,
				'button_txt' => $button_text,
			];
		}

		$elementor = 'elementor/elementor.php';

		if ( ! is_plugin_active( $elementor ) ) {

			if ( $this->is_plugins_installed( $elementor ) ) {
				$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $elementor . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $elementor );
				$message        = sprintf(
					'<strong>%s</strong> %s <strong>%s</strong> %s',
					esc_html( self::PLUGIN_NAME ),
					esc_html__( 'requires', 'shopbuilder' ),
					esc_html__( 'Elementor', 'shopbuilder' ),
					esc_html__( 'plugin to be active. Please activate Elementor to continue.', 'shopbuilder' )
				);
				$button_text    = esc_html__( 'Activate Elementor', 'shopbuilder' );

			} else {
				$activation_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 
				'install-plugin_elementor' ); 
				$message        = sprintf(
					'<strong>%s</strong> %s <strong>%s</strong> %s',
					esc_html( self::PLUGIN_NAME ),
					esc_html__( 'requires', 'shopbuilder' ),
					esc_html__( 'Elementor', 'shopbuilder' ),
					esc_html__( 'plugin to be installed and activated. Please install Elementor to continue.', 'shopbuilder' )
				);
				$button_text    = esc_html__( 'Install Elementor', 'shopbuilder' );
			}

			$this->missing['elementor'] = [
				'name'       => 'Elementor',
				'slug'       => 'elementor',
				'file_name'  => $elementor,
				'url'        => $activation_url,
				'message'    => $message,
				'button_txt' => $button_text,
			];
		}
		if ( ! empty( $this->missing ) ) {
			add_action( 'admin_notices', [ $this, '_missing_plugins_warning' ] );

			$this->allOk = false;
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
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'shopbuilder' ),
			'<strong>' . esc_html__( 'ShopBuilder', 'shopbuilder' ) . '</strong>',
			'<strong>' . esc_html__( 'PHP', 'shopbuilder' ) . '</strong>',
			self::MINIMUM_PHP_VERSION
		);
		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
	}

	/**
	 * Adds admin notice.
	 */
	public function _missing_plugins_warning() {
		$missingPlugins = '';
		$counter        = 0;
		foreach ( $this->missing as $plugin ) {
			$counter ++;
			if ( $counter == sizeof( $this->missing ) ) {
				$sep = '';
			} elseif ( $counter == sizeof( $this->missing ) - 1 ) {
				$sep = ' ' . esc_html__( 'and', 'shopbuilder' ) . ' ';
			} else {
				$sep = ', ';
			}
			if ( current_user_can( 'activate_plugins' ) ) {
				$button = '<p><a href="' . esc_url( $plugin['url'] ) . '" class="button-primary">' . esc_html( $plugin['button_txt'] ) . '</a></p>';
				// $plugin['message'] Already used escaping function
				printf( '<div class="error"><p>%1$s</p>%2$s</div>', $plugin['message'], $button ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				$missingPlugins .= '<strong>' . esc_html( $plugin['name'] ) . '</strong>' . $sep;
			}
		}
	}

	/**
	 * @param $plugin_file_path
	 *
	 * @return bool
	 */
	public function is_plugins_installed( $plugin_file_path = null ) {
		$installed_plugins_list = get_plugins();

		return isset( $installed_plugins_list[ $plugin_file_path ] );
	}
}
