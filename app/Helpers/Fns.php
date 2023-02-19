<?php
/**
 * Fns Helpers class
 *
 * @package  TheTinyTools\ME
 */

namespace TheTinyTools\ME\Helpers;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Fns class
 */
class Fns {

	/**
	 *  Verify nonce.
	 *
	 * @return bool
	 */

	public static function verify_nonce( $nonce_value = false ) {
        if( ! $nonce_value ) {
            $nonce_value     = isset( $_REQUEST[ tttme()->nonceId ] ) ? $_REQUEST[ tttme()->nonceId ] : null;
        }
        // error_log( print_r($nonce_value . ' == ' .  tttme()->nonceId, true) . "\n======\n", 3 , __DIR__.'/nonce-2.txt');
		if ( wp_verify_nonce( $nonce_value, tttme()->nonceId ) ) {
			return true;
		}
		return false;
	}

	public static function kses( $raw, $allowDanger = false ) {

		if ( function_exists( 'wp_kses' ) && ! $allowDanger ) { // WP is here
			return wp_kses( $raw, self::get_kses_array() );
		} else {
			return $raw;
		}
	}

	/*
	 * Escape output of wishlist icon
	 *
	 * @param string $data Data to escape.
	 * @return string Escaped data
	 */
	static function print_icon( $data ) {
		/**
		 * APPLY_FILTERS: rtsb/core/allowed_icon_html
		 *
		 * Filter the allowed HTML for the icons.
		 *
		 * @param array $allowed_icon_html Allowed HTML
		 *
		 * @return array
		 */
		$allowed_icon_html = apply_filters(
			'rtsb/core/allowed_icon_html',
			[
				'i'     => [
					'class' => true,
				],
				'img'   => [
					'src'    => true,
					'alt'    => true,
					'width'  => true,
					'height' => true,
				],
				'svg'   => [
					'class'           => true,
					'aria-hidden'     => true,
					'aria-labelledby' => true,
					'role'            => true,
					'xmlns'           => true,
					'width'           => true,
					'height'          => true,
					'viewbox'         => true,
					'stroke'          => true,
					'fill'            => true,
				],
				'g'     => [
					'fill' => true,
				],
				'title' => [
					'title' => true,
				],
				'path'  => [
					'd'               => true,
					'fill'            => true,
					'stroke-width'    => true,
					'stroke-linecap'  => true,
					'stroke-linejoin' => true,
				],
			]
		);

		echo wp_kses( $data, $allowed_icon_html );
	}

	/**
	 * Allowed HTML for wp_kses.
	 *
	 * @param string $level Tag level.
	 *
	 * @return mixed
	 */
	public static function allowedHtml( $level = 'basic' ) {
		$allowed_html = [];
		switch ( $level ) {
			case 'basic':
				$allowed_html = [
					'b'      => [
						'class' => [],
						'id'    => [],
					],
					'i'      => [
						'class' => [],
						'id'    => [],
					],
					'u'      => [
						'class' => [],
						'id'    => [],
					],
					'br'     => [
						'class' => [],
						'id'    => [],
					],
					'em'     => [
						'class' => [],
						'id'    => [],
					],
					'span'   => [
						'class' => [],
						'id'    => [],
					],
					'strong' => [
						'class' => [],
						'id'    => [],
					],
					'hr'     => [
						'class' => [],
						'id'    => [],
					],
					'div'    => [
						'class' => [],
						'id'    => [],
					],
					'a'      => [
						'href'   => [],
						'title'  => [],
						'class'  => [],
						'id'     => [],
						'target' => [],
					],
				];
				break;

			case 'image':
				$allowed_html = [
					'img' => [
						'src'      => [],
						'data-src' => [],
						'alt'      => [],
						'height'   => [],
						'width'    => [],
						'class'    => [],
						'id'       => [],
						'style'    => [],
						'srcset'   => [],
						'loading'  => [],
						'sizes'    => [],
					],
					'div' => [
						'class' => [],
					],
				];
				break;

			case 'anchor':
				$allowed_html = [
					'a' => [
						'href'  => [],
						'title' => [],
						'class' => [],
						'id'    => [],
						'style' => [],
					],
				];
				break;

			default:
				// code...
				break;
		}

		return $allowed_html;
	}
    /**
     * @param $plugin_file_path
     *
     * @return bool
     */
    public static function is_plugins_installed( $plugin_file_path = null ) {
        $installed_plugins_list = get_plugins();
        return isset( $installed_plugins_list[ $plugin_file_path ] );
    }

    /**
     * @param $post_type
     * @param $post_status
     * @param $group
     * @param $additional_query
     * @return false|mixed|string|null
     */
    public static function get_post_count( $post_type, $post_status = 'publish', $group = 'default', $additional_query = null ) {
        global $wpdb;
        $count_key = 'post_count_'.$post_type . '_' . $post_status;
        $count = wp_cache_get( $count_key, $group );
        if ( false === $count ) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(id) FROM $wpdb->posts AS p WHERE post_type = '%1\$s' AND post_status = '%2\$s'  $additional_query ",
                    $post_type,
                    $post_status
                )
            );
            wp_cache_set( $count_key, $count, $group );
        }
        return $count;
    }


}
