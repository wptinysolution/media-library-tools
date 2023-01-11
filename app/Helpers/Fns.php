<?php
/**
 * Fns Helpers class
 *
 * @package  RadiusTheme\SB
 */

namespace RadiusTheme\SB\Helpers;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use Elementor\Icons_Manager;
use RadiusTheme\SB\Models\ReSizer;
use RadiusTheme\SB\Models\DataModel;
use WC_Product;

/**
 * Fns class
 */
class Fns {
	/**
	 *  Verify nonce.
	 *
	 * @return bool
	 */
	public static function verify_nonce() {
		$nonce     = isset( $_REQUEST[ rtsb()->nonceId ] ) ? $_REQUEST[ rtsb()->nonceId ] : null;
		$nonceText = rtsb()->nonceText;
		if ( wp_verify_nonce( $nonce, $nonceText ) ) {
			return true;
		}
		return false;
	}
	/**
	 * @param string $template_name Template name.
	 * @param string $template_path Template path. (default: '').
	 * @param string $plugin_path   Plugin path. (default: ''). fallback file from plugin
	 *
	 * @return mixed|void
	 */
	public static function locate_template( $template_name, $template_path = '', $plugin_path = '' ) {
		$template_name = $template_name . '.php';
		if ( ! $template_path ) {
			$template_path = rtsb()->get_template_path();
		}
		if ( ! $plugin_path ) {
			$plugin_path = RTSB_ABSPATH . 'templates/';
		}

		$template_rtsb_path = trailingslashit( $template_path ) . $template_name;
		$template_path      = '/' . $template_name;
		$plugin_path        = $plugin_path . $template_name;

		$located = locate_template(
			apply_filters(
				'rtsb/core/locate_template_files',
				[
					$template_rtsb_path, // Search in <theme>/shopbuilder/.
					$template_path,             // Search in <theme>/.
				]
			)
		);

		if ( ! $located && file_exists( $plugin_path ) ) {
			return apply_filters( 'rtsb/core/locate_template', $plugin_path, $template_name );
		}

		/**
		 * APPLY_FILTERS: rtsb/core/locate_template
		 *
		 * Filter the location of the templates.
		 *
		 * @param string $located Template found
		 * @param string $path    Template path
		 *
		 * @return string
		 */
		return apply_filters( 'rtsb/core/locate_template', $located, $template_name );
	}

	/**
	 * Template Content
	 *
	 * @param string $template_name Template name.
	 * @param array  $args          Arguments. (default: array).
	 * @param bool   $return        Whether to return or print the template.
	 * @param string $template_path Template path. (default: '').
	 * @param string $plugin_path   Fallback path from where file will load if fail to load from template. (default: '').
	 *
	 * @return false|string|void
	 */
	public static function load_template( $template_name, array $args = null, $return = false, $template_path = '', $plugin_path = '' ) {
		$located = self::locate_template( $template_name, $template_path, $plugin_path );

		if ( ! file_exists( $located ) ) {
			// translators: %s template.
			self::doing_it_wrong( __FUNCTION__, sprintf( __( '%s does not exist.', 'shopbuilder' ), '<code>' . $located . '</code>' ), '1.0' );

			return;
		}

		if ( ! empty( $args ) && is_array( $args ) ) {
			$atts = $args;
			extract( $args ); // @codingStandardsIgnoreLine
		}

		// Allow 3rd party plugin filter template file from their plugin.
		$located = apply_filters( 'rtsb/core/get_template', $located, $template_name, $args );

		if ( $return ) {
			ob_start();
		}

		do_action( 'rtsb/core/before_template_part', $template_name, $located, $args );
		include $located;
		do_action( 'rtsb/core/after_template_part', $template_name, $located, $args );

		if ( $return ) {
			return ob_get_clean();
		}
	}

	/**
	 *  Verify nonce.
	 *
	 * @return string
	 */
	public static function is_woocommerce() {
		return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
	}

	/**
	 * Page builder
	 *
	 * @param int $post_id post id.
	 *
	 * @return string
	 */
	public static function page_edit_with( $post_id ) {
		if ( ! $post_id ) {
			return '';
		}

		$edit_with = get_post_meta( $post_id, '_elementor_edit_mode', true );

		if ( 'builder' === $edit_with ) {
			$edit_by = 'elementor';
		} else {
			$edit_by = 'gutenberg';
		}

		return $edit_by;
	}

	/**
	 * Returns default expiration for wishlist cookie
	 *
	 * @return int Number of seconds the cookie should last.
	 */
	public static function get_cookie_expiration() {
		return intval( apply_filters( 'rtsb/cookie_expiration', 60 * 60 * 24 * 30 ) );
	}

	/**
	 * Create a cookie.
	 *
	 * @param string $name     Cookie name.
	 * @param mixed  $value    Cookie value.
	 * @param int    $time     Cookie expiration time.
	 * @param bool   $secure   Whether cookie should be available to secured connection only.
	 * @param bool   $httponly Whether cookie should be available to HTTP request only (no js handling).
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function setcookie( $name, $value = [], $time = null, $secure = false, $httponly = false ) {

		if ( ! apply_filters( 'rtsb/set_cookie', true ) || empty( $name ) ) {
			return false;
		}

		$time = ! empty( $time ) ? $time : time() + self::get_cookie_expiration();

		$value      = wp_json_encode( stripslashes_deep( $value ) );
		$expiration = apply_filters( 'rtsb/cookie_expiration_time', $time ); // Default 30 days.

		$_COOKIE[ $name ] = $value;
		wc_setcookie( $name, $value, $expiration, $secure, $httponly );

		return true;
	}

	/**
	 * Retrieve the value of a cookie.
	 *
	 * @param string $name Cookie name.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function getcookie( $name ) {
		if ( isset( $_COOKIE[ $name ] ) ) {
			return json_decode( sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ), true );
		}

		return [];
	}

	/**
	 * Woocommerce Last product id return
	 */
	public static function get_prepared_product_id() {
		// TODO:: Get Product List for select in Create Template Modal .
		if ( is_singular( 'product' ) ) {
			return get_the_ID();
		}
		global $wpdb;
		$cache_key = 'rtsb_prepared_product_id';
		$_post_id  = get_transient( $cache_key );
		if ( false === $_post_id || 'publish' !== get_post_status( $_post_id ) ) {
			delete_transient( $cache_key );
			$_post_id = $wpdb->get_var(
				$wpdb->prepare( "SELECT MAX(ID) FROM {$wpdb->prefix}posts WHERE post_type =  %s AND post_status = %s", 'product', 'publish' )
			);
			set_transient( $cache_key, $_post_id, 12 * HOUR_IN_SECONDS );
		}

		return $_post_id;
	}

	/**
	 * Get the product function.
	 *
	 * @return object
	 */
	public static function get_product() {
		global $post, $product;
		if ( is_singular( 'product' ) && $product instanceof WC_Product ) {
			return $product;
		}
		return wc_get_product( self::get_prepared_product_id() );
	}

	/**
	 * Error function
	 *
	 * @param [type] $function function name.
	 * @param [type] $message message.
	 * @param [type] $version version.
	 *
	 * @return void
	 */
	public static function doing_it_wrong( $function, $message, $version ) {
		$message .= ' Backtrace: ' . wp_debug_backtrace_summary();
		_doing_it_wrong( $function, $message, $version );
	}

	/**
	 * @return array
	 */
	public static function get_pages() {
		$pages    = [];
		$rawPages = get_pages();
		if ( ! empty( $rawPages ) ) {
			foreach ( $rawPages as $page ) {
				$pages[ $page->ID ] = $page->post_title;
			}
		}

		return $pages;
	}

	public static function get_section_items( $section_id ) {
		if ( ! $section_id ) {
			return [];
		}

		return DataModel::source()->get_option( $section_id, [] );
	}

	public static function get_options( $section_id, $item_id ) {
		if ( ! $section_id || ! $item_id ) {
			return [];
		}
		$sections = self::get_section_items( $section_id );

		return isset( $sections[ $item_id ] ) ? $sections[ $item_id ] : [];
	}

	/**
	 * @param string $section_id
	 * @param string $item_id
	 * @param string $option_id
	 * @param null   $default EXCEPT multi_checkbox you can provide default value if given option does not set any value
	 * @param null   $type    checkbox, multi_checkbox, number
	 *
	 * @return bool|int|mixed|null
	 */
	public static function get_option( $section_id, $item_id, $option_id, $default = null, $type = null ) {
		$options = self::get_options( $section_id, $item_id );

		if ( $type === 'checkbox' ) {
			if ( isset( $options[ $option_id ] ) ) {
				return $options[ $option_id ] === 'on';
			}

			return $default;
		} elseif ( $type === 'multi_checkbox' ) {
			return isset( $options[ $option_id ] ) && is_array( $options[ $option_id ] ) && in_array( $default, $options[ $option_id ] );
		} elseif ( $type === 'number' ) {
			return isset( $options[ $option_id ] ) ? absint( $options[ $option_id ] ) : absint( $default );
		}

		return isset( $options[ $option_id ] ) && ! empty( $options[ $option_id ] ) ? $options[ $option_id ] : $default;
	}


	/**
	 * Create a page and store the ID in an option.
	 *
	 * @param mixed  $slug         Slug for the new page.
	 * @param array  $options      ['section_id', 'item_id', 'option_id']Option name to store the page's ID.
	 * @param string $page_title   (default: '') Title for the new page.
	 * @param string $page_content (default: '') Content for the new page.
	 * @param int    $post_parent  (default: 0) Parent for the new page.
	 * @param string $post_status  (default: publish) The post status of the new page.
	 *
	 * @return int page ID.
	 */
	public static function create_page( $slug, $options = '', $page_title = '', $page_content = '', $post_parent = 0, $post_status = 'publish' ) {
		global $wpdb;

		$option_value = 0;
		if ( ! empty( $options ) ) {
			if ( is_array( $options ) ) {
				$options      = wp_parse_args(
					$options,
					[
						'section_id' => '',
						'item_id'    => '',
						'option_id'  => '',
					]
				);
				$option_value = self::get_option( $options['section_id'], $options['item_id'], $options['option_id'], 0, 'number' );
			} else {
				$option_value = absint( get_option( $options ) );
			}
		}

		if ( $option_value > 0 ) {
			$page_object = get_post( $option_value );

			if ( $page_object && 'page' === $page_object->post_type && ! in_array(
				$page_object->post_status,
				[
					'pending',
					'trash',
					'future',
					'auto-draft',
				],
				true
			) ) {
				// Valid page is already in place.
				return $page_object->ID;
			}
		}

		if ( strlen( $page_content ) > 0 ) {
			// Search for an existing page with the specified page content (typically a shortcode).
			$shortcode        = str_replace( [ '<!-- wp:shortcode -->', '<!-- /wp:shortcode -->' ], '', $page_content );
			$valid_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status NOT IN ( 'pending', 'trash', 'future', 'auto-draft' ) AND post_content LIKE %s LIMIT 1;", "%{$shortcode}%" ) );
		} else {
			// Search for an existing page with the specified page slug.
			$valid_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status NOT IN ( 'pending', 'trash', 'future', 'auto-draft' )  AND post_name = %s LIMIT 1;", $slug ) );
		}

		$valid_page_found = apply_filters( 'rtsb/core/create_page_id', $valid_page_found, $slug, $page_content, $options );

		if ( $valid_page_found ) {
			if ( is_array( $options ) ) {
				if ( ! empty( $options['section_id'] ) && $options['item_id'] && $options['option_id'] ) {
					$section_items = DataModel::source()->get_option( $options['section_id'], [] );
					$section_items[ $options['item_id'] ][ $options['option_id'] ] = $valid_page_found;
					DataModel::source()->set_option( $options['section_id'], $section_items );
				}
			} else {
				if ( $options ) {
					update_option( $options, $valid_page_found );
				}
			}

			return $valid_page_found;
		}

		// Search for a matching valid trashed page.
		if ( strlen( $page_content ) > 0 ) {
			// Search for an existing page with the specified page content (typically a shortcode).
			$trashed_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_content LIKE %s LIMIT 1;", "%{$page_content}%" ) );
		} else {
			// Search for an existing page with the specified page slug.
			$trashed_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_name = %s LIMIT 1;", $slug ) );
		}

		if ( $trashed_page_found ) {
			$page_id   = $trashed_page_found;
			$page_data = [
				'ID'          => $page_id,
				'post_status' => $post_status,
			];
			wp_update_post( $page_data );
		} else {
			$page_data = [
				'post_status'    => $post_status,
				'post_type'      => 'page',
				'post_author'    => 1,
				'post_name'      => $slug,
				'post_title'     => $page_title,
				'post_content'   => $page_content,
				'post_parent'    => $post_parent,
				'comment_status' => 'closed',
			];
			$page_id   = wp_insert_post( $page_data );

			do_action( 'rtsb/core/page_created', $page_id, $page_data );
		}

		if ( is_array( $options ) ) {
			if ( ! empty( $options['section_id'] ) && $options['item_id'] && $options['option_id'] ) {
				$section_items = DataModel::source()->get_option( $options['section_id'], [] );
				$section_items[ $options['item_id'] ][ $options['option_id'] ] = $page_id;
				DataModel::source()->set_option( $options['section_id'], $section_items );
			}
		} else {
			if ( $options ) {
				update_option( $options, $page_id );
			}
		}

		return $page_id;
	}

	// TODO:: Need to remove if not used.
	public static function get_kses_array() {
		return [
			'a'                             => [
				'class' => [],
				'href'  => [],
				'rel'   => [],
				'title' => [],
			],
			'abbr'                          => [
				'title' => [],
			],
			'b'                             => [],
			'blockquote'                    => [
				'cite' => [],
			],
			'cite'                          => [
				'title' => [],
			],
			'code'                          => [],
			'del'                           => [
				'datetime' => [],
				'title'    => [],
			],
			'dd'                            => [],
			'div'                           => [
				'class' => [],
				'title' => [],
				'style' => [],
			],
			'dl'                            => [],
			'dt'                            => [],
			'em'                            => [],
			'h1'                            => [
				'class' => [],
			],
			'h2'                            => [
				'class' => [],
			],
			'h3'                            => [
				'class' => [],
			],
			'h4'                            => [
				'class' => [],
			],
			'h5'                            => [
				'class' => [],
			],
			'h6'                            => [
				'class' => [],
			],
			'i'                             => [
				'class' => [],
			],
			'img'                           => [
				'alt'    => [],
				'class'  => [],
				'height' => [],
				'src'    => [],
				'width'  => [],
			],
			'li'                            => [
				'class' => [],
			],
			'ol'                            => [
				'class' => [],
			],
			'p'                             => [
				'class' => [],
			],
			'q'                             => [
				'cite'  => [],
				'title' => [],
			],
			'span'                          => [
				'class' => [],
				'title' => [],
				'style' => [],
			],
			'iframe'                        => [
				'width'       => [],
				'height'      => [],
				'scrolling'   => [],
				'frameborder' => [],
				'allow'       => [],
				'src'         => [],
			],
			'strike'                        => [],
			'br'                            => [],
			'strong'                        => [],
			'data-wow-duration'             => [],
			'data-wow-delay'                => [],
			'data-wallpaper-options'        => [],
			'data-stellar-background-ratio' => [],
			'ul'                            => [
				'class' => [],
			],
		];
	}

	// TODO:: Need to remove if not used.
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
	 * Prints HTMl.
	 *
	 * @param string $html HTML.
	 * @param bool   $allHtml All HTML.
	 *
	 * @return void
	 */
	public static function print_html( $html, $allHtml = false ) {
		if ( $allHtml ) {
			echo stripslashes_deep( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo wp_kses_post( stripslashes_deep( $html ) );
		}
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
		// TODO:: Need Optimize.
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

			case 'advanced':
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
					'a'      => [
						'href'   => [],
						'title'  => [],
						'class'  => [],
						'id'     => [],
						'target' => [],
					],
					'input'  => [
						'type'  => [],
						'name'  => [],
						'class' => [],
						'value' => [],
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
	 * Definition for wp_kses.
	 *
	 * @param string $string String to check.
	 * @param string $level  Tag level.
	 *
	 * @return string|void
	 */
	// TODO:: Need to remove if not used.
	public static function htmlKses( $string, $level ) {
		if ( empty( $string ) ) {
			return;
		}

		return wp_kses( $string, self::allowedHtml( $level ) );
	}

	/**
	 * Insert Some array element
	 *
	 * @param [type]  $key The elements will insert nearby this key.
	 * @param [type]  $main_array Original array.
	 * @param [type]  $insert_array some element will insert in original array.
	 * @param boolean $is_after array insert position base on the key.
	 *
	 * @return array
	 */
	public static function insert_controls( $key, $main_array, $insert_array, $is_after = false ) {
		$index = array_search( $key, array_keys( $main_array ), true );
		if ( 'integer' === gettype( $index ) ) {
			if ( $is_after ) {
				$index ++;
			}
			$main_array = array_merge(
				array_slice( $main_array, 0, $index ),
				$insert_array,
				array_slice( $main_array, $index )
			);
		}

		return $main_array;
	}

	/**
	 * Image Sizes
	 *
	 * @return array
	 */
	public static function get_image_sizes() {
		global $_wp_additional_image_sizes;

		$sizes = [];

		foreach ( get_intermediate_image_sizes() as $_size ) {
			if ( in_array( $_size, [ 'thumbnail', 'medium', 'large' ], true ) ) {
				$sizes[ $_size ]['width']  = get_option( "{$_size}_size_w" );
				$sizes[ $_size ]['height'] = get_option( "{$_size}_size_h" );
				$sizes[ $_size ]['crop']   = (bool) get_option( "{$_size}_crop" );
			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
				$sizes[ $_size ] = [
					'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
					'height' => $_wp_additional_image_sizes[ $_size ]['height'],
					'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
				];
			}
		}

		$imgSize = [];

		foreach ( $sizes as $key => $img ) {
			$imgSize[ $key ] = ucfirst( $key ) . " ({$img['width']}*{$img['height']})";
		}

		$imgSize['full']        = esc_html__( 'Full size', 'shopbuilder' );
		$imgSize['rtsb_custom'] = esc_html__( 'Custom image size', 'shopbuilder' );

		return $imgSize;
	}

	/**
	 * Free Layouts
	 *
	 * @return array
	 */
	public static function free_layouts() {
		$layouts = [
			'grid-layout1'            => esc_html__( 'Grid Layout 1', 'shopbuilder' ),
			'grid-layout2'            => esc_html__( 'Grid Layout 2', 'shopbuilder' ),
			'list-layout1'            => esc_html__( 'List Layout 1', 'shopbuilder' ),
			'list-layout2'            => esc_html__( 'List Layout 2', 'shopbuilder' ),
			'category-single-layout1' => esc_html__( 'Category Layout 1', 'shopbuilder' ),
			'category-layout1'        => esc_html__( 'Category Layout 1', 'shopbuilder' ),
			'category-layout2'        => esc_html__( 'Category Layout 2', 'shopbuilder' ),
			'category-layout3'        => esc_html__( 'Category Layout 2', 'shopbuilder' ),
		];

		return apply_filters( 'rtsb/elements/elementor/free_layouts', $layouts );
	}

	/**
	 * Get all terms by taxonomy
	 *
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return array
	 */
	public static function get_all_terms( $taxonomy ) {
		$terms = [];

		if ( empty( $taxonomy ) ) {
			return $terms;
		}

		$termList = get_terms( [ $taxonomy ], [ 'hide_empty' => 0 ] );

		if ( is_array( $termList ) && ! empty( $termList ) && empty( $termList['errors'] ) ) {
			foreach ( $termList as $term ) {
				$terms[ $term->term_id ] = esc_html( $term->name );
			}
		}

		return $terms;
	}

	/**
	 * Get all terms by attributes
	 *
	 * @return array
	 */
	public static function get_all_attributes() {
		$terms    = [];
		$termList = [];

		$attributes = wc_get_attribute_taxonomies();

		if ( $attributes ) {
			foreach ( $attributes as $tax ) {
				if ( taxonomy_exists( wc_attribute_taxonomy_name( $tax->attribute_name ) ) ) {
					$termList[ $tax->attribute_name ] = get_terms( wc_attribute_taxonomy_name( $tax->attribute_name ), 'orderby=name&hide_empty=0' );
				}
			}
		}

		if ( ! empty( $termList ) && empty( $termList['errors'] ) && is_array( $termList ) ) {
			foreach ( $termList as $name => $atts ) {
				foreach ( $atts as $term ) {
					$terms[ $term->term_id ] = esc_html( ucwords( str_replace( '-', ' ', $name ) ) . ' - ' . $term->name );
				}
			}
		}

		return $terms;
	}

	/**
	 * Get User list
	 *
	 * @return array
	 */
	public static function get_users() {
		$users = [];
		$u     = get_users();

		if ( ! empty( $u ) ) {
			foreach ( $u as $user ) {
				$users[ $user->ID ] = $user->display_name;
			}
		}

		return $users;
	}

	/**
	 * Get products list.
	 *
	 * @return array
	 */
	public static function get_products_list() {
		$products = [];
		// TODO:: Need To replace with $wpdb.
		$query = get_posts(
			[
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => - 1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		if ( ! empty( $query ) && is_array( $query ) ) {
			foreach ( $query as $product ) {
				$products[ $product->ID ] = $product->post_title;
			}
		}

		return $products;
	}

	/**
	 * Get Taxonomy List.
	 *
	 * @return array
	 */
	public static function get_tax_list() {
		return [
			'product_cat' => esc_html__( 'Product Category', 'shopbuilder' ),
			// 'product_tag' => esc_html__( 'Product Tag', 'shopbuilder' ), // Pro.
			// 'product_attr' => esc_html__( 'Product Attr', 'shopbuilder' ), // Pro.
		];
	}

	/**
	 * Get Category Query List.
	 *
	 * @return array
	 */
	public static function get_cat_list() {
		return [
			'all'             => esc_html__( 'All Categories', 'shopbuilder' ),
			'specific_parent' => esc_html__( 'Sub-Categories by Parent', 'shopbuilder' ),
			'cat_ids'         => esc_html__( 'Select by ID', 'shopbuilder' ),
			'selection'       => esc_html__( 'Manual Selection', 'shopbuilder' ),
		];
	}

	/**
	 * Get Term List.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param bool   $first_term First term.
	 * @param bool   $only_parents Only parent terms.
	 * @param bool   $return_slug Return with slug.
	 *
	 * @return int|array
	 */
	public static function get_terms( $taxonomy, $first_term = false, $only_parents = false, $return_slug = false ) {
		// TODO:: Return Slug always true for all settings.
		$term_list = [];
		$args      = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		];

		if ( $only_parents ) {
			$args['parent'] = 0;
		}

		$terms = get_terms( $args );

		if ( empty( $terms ) ) {
			return [ esc_html__( 'Nothing found', 'shopbuilder' ) ];
		}

		foreach ( $terms as $term ) {
			if ( $return_slug ) {
				$term_list[ $term->slug ] = $term->name;
			} else {
				$term_list[ $term->term_id ] = $term->name;
			}
		}

		if ( $first_term ) {
			return array_keys( $term_list )[0];
		} else {
			return $term_list;
		}
	}

	/**
	 * Get product rating.
	 *
	 * @return string|void
	 */
	public static function get_product_rating_html() {
		global $product;

		$html         = '';
		$rating_count = $product->get_rating_count();
		$average      = $product->get_average_rating();

		if ( empty( wc_get_rating_html( $average, $rating_count ) ) ) {
			return $html;
		}

		$html .= '<div class="product-rating">';
		$html .= wc_get_rating_html( $average, $rating_count );
		// TODO:: Disable Rating Count option need to apply.
		$html .= ! empty( $html ) ? '<span class="count">(' . $average . ')</span>' : '';
		$html .= '</div>';

		self::print_html( $html, true );
	}

	/**
	 * Text truncation.
	 *
	 * @param string $text_to_truncate Text.
	 * @param int    $limit Limit.
	 * @param string $after After text.
	 * @return string
	 */
	public static function text_truncation( $text_to_truncate, $limit, $after = '&#8230;' ) {
		if ( empty( $limit ) ) {
			return $text_to_truncate;
		}

		$limit ++;

		$text = '';

		if ( mb_strlen( $text_to_truncate ) > $limit ) {
			$subex   = mb_substr( wp_strip_all_tags( $text_to_truncate ), 0, $limit );
			$exwords = explode( ' ', $subex );
			$excut   = - ( mb_strlen( $exwords[ count( $exwords ) - 1 ] ) );

			if ( $excut < 0 ) {
				$text .= mb_substr( $subex, 0, $excut ) . $after;
			} else {
				$text .= $subex . $after;
			}
		} else {
			$text .= $text_to_truncate;
		}

		return $text;
	}

	/**
	 * Promo Badge HTML
	 *
	 * @param string $text Text.
	 * @param string $class Class.
	 * @return void
	 */
	public static function get_badge_html( $text, $class = 'fill' ) {
		ob_start();
		?>

		<ul class="rtsb-promotion-list">
			<li class="rtsb-promotion-list-item">
				<span class="rtsb-tag-<?php echo ! empty( $class ) ? esc_attr( $class ) : ''; ?>"><?php echo esc_html( $text ); ?></span>
			</li>
		</ul>

		<?php
		self::print_html( ob_get_clean() );
	}

	/**
	 * Categories HTML
	 *
	 * @param int    $id Product ID.
	 * @param string $class Custom class.
	 * @return void|string
	 */
	public static function get_categories_list( $id, $class = 'rtsb-category-outline' ) {
		if ( empty( $id ) ) {
			return '';
		}

		ob_start();
		?>

		<ul class="rtsb-category-list <?php echo esc_attr( $class ); ?>">
			<?php
			self::print_html(
				wc_get_product_category_list(
					$id,
					'</li><li class="rtsb-category-list-item">',
					'<li class="rtsb-category-list-item">',
					'</li>'
				)
			);
			?>
		</ul>

		<?php
		self::print_html( ob_get_clean() );
	}


	/**
	 * Get Featured Image HTML.
	 *
	 * @param string $type Image type.
	 * @param int    $post_id Post ID.
	 * @param string $f_img_size Image size.
	 * @param null   $default_img_id Default image ID.
	 * @param array  $custom_img_size Custom image size.
	 * @param bool   $lazy Lazy load check.
	 * @param bool   $hover Hover image.
	 *
	 * @return string|null
	 */
	public static function get_product_image_html( $type = 'product', $post_id = null, $f_img_size = 'medium', $default_img_id = null, $custom_img_size = [], $lazy = false, $hover = false ) {
		$img_html      = null;
		$attachment_id = null;
		$c_size        = false;
		$hover_class   = '';
		$post_title    = '';

		if ( 'rtsb_custom' === $f_img_size ) {
			$f_img_size = 'full';
			$c_size     = true;
		}

		if ( $hover ) {
			$a_id        = $post_id;
			$hover_class = ' rtsb-img-hover';
		} else {
			if ( 'product' === $type ) {
				$a_id       = get_post_thumbnail_id( $post_id );
				$post_title = get_the_title( $post_id );
			} elseif ( 'category' === $type ) {
				$a_id       = get_term_meta( $post_id, 'thumbnail_id', true );
				$post_title = get_term( $post_id )->name;
			} else {
				$a_id = $default_img_id;
			}
		}

		$img_alt    = trim( wp_strip_all_tags( get_post_meta( $a_id, '_wp_attachment_image_alt', true ) ) );
		$alt_tag    = ! empty( $img_alt ) ? $img_alt : wp_strip_all_tags( $post_title );
		$lazy_class = $lazy ? ' swiper-lazy' : '';
		$attr       = [
			'class' => 'img-responsive rtsb-product-image' . $lazy_class . $hover_class,
			'alt'   => $alt_tag,
		];

		if ( $a_id ) {
			$img_html      = wp_get_attachment_image( $a_id, $f_img_size, false, $attr );
			$attachment_id = $a_id;
		}

		if ( ! $img_html && $default_img_id ) {
			$img_html      = wp_get_attachment_image( $default_img_id, $f_img_size, false, $attr );
			$attachment_id = $default_img_id;
		}

		if ( $img_html && $c_size ) {
			preg_match( '@src="([^"]+)"@', $img_html, $match );
			$img_src = array_pop( $match );
			$w       = ! empty( $custom_img_size['width'] ) ? absint( $custom_img_size['width'] ) : null;
			$h       = ! empty( $custom_img_size['height'] ) ? absint( $custom_img_size['height'] ) : null;
			$c       = ! empty( $custom_img_size['crop'] ) && 'soft' === $custom_img_size['crop'] ? false : true;

			if ( $w && $h ) {
				$image = self::image_resize( $img_src, $w, $h, $c, false );

				if ( ! empty( $image ) ) {
					list( $src, $width, $height ) = $image;

					$hwstring   = image_hwstring( $width, $height );
					$attachment = get_post( $attachment_id );
					$attr       = apply_filters( 'wp_get_attachment_image_attributes', $attr, $attachment, $f_img_size );

					if ( $lazy ) {
						$attr['data-src'] = $src;
					} else {
						$attr['src'] = $src;
					}

					$attr     = array_map( 'esc_attr', $attr );
					$img_html = rtrim( "<img $hwstring" );

					foreach ( $attr as $name => $value ) {
						$img_html .= " $name=" . '"' . $value . '"';
					}

					$img_html .= ' />';
				}
			}
		}

		if ( ! $img_html ) {
			$hwstring      = image_hwstring( 160, 160 );
			$attr          = isset( $attr['src'] ) ? apply_filters( 'wp_get_attachment_image_attributes', $attr, false, $f_img_size ) : [];
			$attr['class'] = 'default-img';
			$attr['src']   = esc_url( rtsb()->get_assets_uri( 'images/demo.png' ) );
			$attr['alt']   = esc_html__( 'Default Image', 'shopbuilder' );
			$img_html      = rtrim( "<img $hwstring" );

			foreach ( $attr as $name => $value ) {
				$img_html .= " $name=" . '"' . $value . '"';
			}

			$img_html .= ' />';
		}

		if ( $lazy ) {
			$img_html = $img_html . '<div class="swiper-lazy-preloader swiper-lazy-preloader"></div>';
		}

		return $img_html;
	}

	/**
	 * Call the Image resize model for resize function
	 *
	 * @param string     $url     URL.
	 * @param int        $width   Width.
	 * @param int        $height  Height.
	 * @param string     $crop    Crop.
	 * @param bool|true  $single  Single.
	 * @param bool|false $upscale Upscale.
	 *
	 * @return array|bool|string
	 */
	public static function image_resize( $url, $width = null, $height = null, $crop = null, $single = true, $upscale = false ) {
		$rtResize = new ReSizer();

		return $rtResize->process( $url, $width, $height, $crop, $single, $upscale );
	}

	/**
	 * Get Product Image
	 *
	 * @param string $f_image Featured Image.
	 * @param string $h_image Hover Image.
	 * @return void
	 */
	public static function get_product_image( $f_image, $h_image = null ) {
		echo wp_kses( $f_image, self::allowedHtml( 'image' ) );

		if ( ! empty( $h_image ) ) {
			echo wp_kses( $h_image, self::allowedHtml( 'image' ) );
		}
	}
	/**
	 * Post Custom Field value.
	 *
	 * @param int    $post_id Post id.
	 * @param string $field_key Custom Field Key.
	 * @param string $field_fallback FallBack text.
	 * @return string|void
	 */
	public static function get_post_custom_field_value( $post_id, $field_key = '', $field_fallback = '' ) {
		if ( ! $post_id || ! $field_key ) {
			return;
		}
		$field_value = get_post_meta( $post_id, $field_key, true );
		if ( empty( $field_value ) ) {
			$field_value = ! empty( $field_fallback ) ? $field_fallback : $field_value;
		}
		$field_value = apply_filters( 'rtsb/get_post_custom_field_value/' . $field_key, $field_value, $field_key );
		return sprintf( '<span class="rtsb-woo-custom-field">%s</span>', $field_value );
	}

	/**
	 * Elementor Icon
	 *
	 * @param array  $control Elementor Control array.
	 * @param string $class Custom class.
	 * @param string $builder Builder name.
	 *
	 * @return string
	 */
	public static function icons_manager( $control, $class = '', $builder = 'elementor' ): string {
		$attributes = [
			'aria-hidden' => 'true',
		];

		if ( ! empty( $class ) ) {
			$attributes['class'] = esc_attr( $class );
		}

		ob_start();

		if ( defined( 'ELEMENTOR_VERSION' ) && ! empty( $control ) && 'elementor' === $builder ) {
			Icons_Manager::render_icon( $control, $attributes );
		}

		return ob_get_clean();
	}

	/**
	 * Get sale badge
	 *
	 * @param string $type Badge type.
	 * @param string $text Badge text.
	 * @param string $out_of_stock_text Out of stock text.
	 * @return string
	 */
	public static function get_sale_badge( $type, $text, $out_of_stock_text ) {
		global $product;

		if ( ! $product->is_in_stock() ) {
			return $out_of_stock_text;
		}

		if ( ! $product->is_on_sale() ) {
			return '';
		}

		$badge_text     = '';
		$percentage     = null;
		$max_percentage = '';

		if ( $product->is_type( 'simple' ) ) {
			$max_percentage = ( ( $product->get_regular_price() - $product->get_sale_price() ) / $product->get_regular_price() ) * 100;
		} elseif ( $product->is_type( 'variable' ) ) {
			$max_percentage = 0;

			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );
				$price     = $variation->get_regular_price();
				$sale      = $variation->get_sale_price();

				if ( 0 !== $price && ! empty( $sale ) ) {
					$percentage = ( $price - $sale ) / $price * 100;
				}

				if ( $percentage > $max_percentage ) {
					$max_percentage = $percentage;
				}
			}
		}

		if ( $max_percentage > 0 ) {
			$badge_text = '-' . round( $max_percentage ) . '%';
		}

		if ( 'text' === $type ) {
			$badge_text = $text;
		}

		return $badge_text;
	}

	/**
	 * Pagination
	 *
	 * @param string  $pages Pages.
	 * @param integer $range Range.
	 * @param boolean $ajax Ajax.
	 * @return string
	 */
	public static function custom_pagination( $pages = '', $range = 4, $ajax = false ) {
		$html      = null;
		$showitems = ( $range * 2 ) + 1;

		global $paged;

		if ( is_front_page() ) {
			$paged = ( get_query_var( 'page' ) ) ? get_query_var( 'page' ) : 1;
		} else {
			$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
		}

		if ( empty( $paged ) ) {
			$paged = 1;
		}

		if ( $pages == '' ) {
			global $wp_query;
			$pages = $wp_query->max_num_pages;

			if ( ! $pages ) {
				$pages = 1;
			}
		}

		$ajaxClass = null;
		$dataAttr  = null;

		if ( $ajax ) {
			$ajaxClass = ' rtsb-ajax';
			$dataAttr  = "data-paged='1'";
		}

		if ( 1 != $pages ) {
			$html .= '<div class="rtsb-pagination' . $ajaxClass . '" ' . $dataAttr . '>';
			$html .= '<ul class="pagination-list">';

			if ( $paged > 2 && $paged > $range + 1 && $showitems < $pages ) {
				$html .= "<li><a data-paged='1' href='" . get_pagenum_link( 1 ) . "' aria-label='First'>&laquo;</a></li>";
			}

			if ( $paged > 1 && $showitems < $pages ) {
				$p     = $paged - 1;
				$html .= "<li><a data-paged='{$p}' href='" . get_pagenum_link( $p ) . "' aria-label='Previous'>&lsaquo;</a></li>";
			}

			for ( $i = 1; $i <= $pages; $i ++ ) {
				if ( 1 != $pages && ( ! ( $i >= $paged + $range + 1 || $i <= $paged - $range - 1 ) || $pages <= $showitems ) ) {
					$html .= ( $paged == $i ) ? '<li class="active"><span>' . $i . '</span></li>' : "<li><a data-paged='{$i}' href='" . get_pagenum_link( $i ) . "'>" . $i . '</a></li>';
				}
			}

			if ( $paged < $pages && $showitems < $pages ) {
				$p     = $paged + 1;
				$html .= "<li><a data-paged='{$p}' href=\"" . get_pagenum_link( $paged + 1 ) . "\"  aria-label='Next'>&rsaquo;</a></li>";
			}

			if ( $paged < $pages - 1 && $paged + $range - 1 < $pages && $showitems < $pages ) {
				$html .= "<li><a data-paged='{$pages}' href='" . get_pagenum_link( $pages ) . "' aria-label='Last'>&raquo;</a></li>";
			}

			$html .= '</ul>';
			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Action Buttons HTMl
	 *
	 * @param array  $items Items.
	 * @param array  $ajax_cart Ajax cart HTML.
	 * @param string $preset Button preset.
	 * @param array  $position Position.
	 *
	 * @return void|string
	 */
	public static function get_formatted_action_buttons( $items, $ajax_cart = '', $preset = 'preset1', $position = 'above' ) {
		if ( empty( $items ) ) {
			return;
		}

		$html  = '';
		$class = '';

		if ( 'after' === $position ) {
			$class .= 'after-content';
		}

		if ( 'preset2' === $preset ) {
			$class .= ' rtsb-action-buttons-vertical vertical-delay-effect';
		} elseif ( 'preset4' === $preset ) {
			$class .= ' action-buttons-outline horizontal-floating-btn';
		} else {
			$class .= ' rtsb-action-buttons-cart-box-width-auto horizontal-floating-btn';
		}

		if ( 'preset3' === $preset ) {
			$html .= '<div class="rtsb-action-buttons top-part">';
			$html .= '<ul class="rtsb-action-button-list">';
			$html .= self::get_action_button_by_type( $items, 'wishlist' );
			$html .= self::get_action_button_by_type( $items, 'compare' );
			$html .= self::get_action_button_by_type( $items, 'quick_view' );
			$html .= '</ul>';
			$html .= '</div>';

			$html .= '<div class="rtsb-action-buttons bottom-part">';
			$html .= '<ul class="rtsb-action-button-list">';
			$html .= self::get_action_button_by_type( $items, 'add_to_cart', $ajax_cart );
		} else {
			$html .= '<div class="rtsb-action-buttons ' . esc_attr( $class ) . '">';
			$html .= '<ul class="rtsb-action-button-list">';
			$html .= self::get_action_button_by_type( $items, 'add_to_cart', $ajax_cart );
			$html .= self::get_action_button_by_type( $items, 'wishlist' );
			$html .= self::get_action_button_by_type( $items, 'compare' );
			$html .= self::get_action_button_by_type( $items, 'quick_view' );
		}

		$html .= '</ul>';
		$html .= '</div>';

		return apply_filters( 'rtsb/elements/elementor/get_formatted_action_buttons', $html );
	}

	/**
	 * Get Action Button HTML
	 *
	 * @param array  $items Items.
	 * @param string $type Button type.
	 * @param string $cart_html Ajax cart HTML.
	 * @param string $wrapper Wrapper tag.
	 *
	 * @return void|string
	 */
	public static function get_action_button_by_type( $items, $type, $cart_html = '', $wrapper = 'li' ) {
		if ( ! in_array( $type, $items, true ) ) {
			return;
		}

		$html  = '';
		$class = 'rtsb-action-button-item';

		if ( 'add_to_cart' === $type ) {
			$class .= ' rtsb-cart' . ( empty( $cart_html ) ? esc_attr( ' no-cart-button' ) : '' );
		} else {
			$class .= ' rtsb-' . esc_attr( str_replace( '_', '-', $type ) );
		}

		$html .= '<' . esc_attr( $wrapper ) . ' class="' . esc_attr( $class ) . '">';

		if ( ( 'add_to_cart' === $type ) && ( ! empty( $cart_html ) ) ) {
			$html .= $cart_html;
		} else {
			$html .= do_shortcode( '[rtsb_' . $type . '_button]' );
		}

		$html .= '</' . esc_attr( $wrapper ) . '>';

		return apply_filters( 'rtsb/elements/elementor/get_action_button_by_type', $html );
	}

	/**
	 * Social Share Platforms.
	 *
	 * @return mixed|null
	 */
	public static function social_share_platforms_list() {
		return apply_filters(
			'rtsb/settings/social_share/platforms',
			[
				[
					'value' => 'facebook',
					'label' => esc_html__( 'Facebook', 'shopbuilder' ),
				],
				[
					'value' => 'twitter',
					'label' => esc_html__( 'Twitter', 'shopbuilder' ),
				],
				[
					'value' => 'linkedin',
					'label' => esc_html__( 'Linkedin', 'shopbuilder' ),
				],
				[
					'value' => 'pinterest',
					'label' => esc_html__( 'Pinterest', 'shopbuilder' ),
				],
				[
					'value' => 'skype',
					'label' => esc_html__( 'Skype', 'shopbuilder' ),
				],
				[
					'value' => 'whatsapp',
					'label' => esc_html__( 'Whatsapp', 'shopbuilder' ),
				],
				[
					'value' => 'reddit',
					'label' => esc_html__( 'Reddit', 'shopbuilder' ),
				],
				[
					'value' => 'telegram',
					'label' => esc_html__( 'Telegram', 'shopbuilder' ),
				],
			]
		);
	}

	/**
	 * Get Social Share link HTML.
	 *
	 * @param int    $id Post ID.
	 * @param array  $types Preset type.
	 * @param string $preset Style type.
	 * @param string $show_icon Show icon.
	 *
	 * @return string
	 */
	public static function get_social_share_html( int $id, array $types, string $preset = 'default', $show_icon = 'yes' ) {
		$attr   = [ 'postid' => $id ];
		$output = '';

		if ( empty( $types ) ) {
			return $output;
		}

		foreach ( $types as $type ) {
			$link          = [];
			$link['type']  = $type['share_items'];
			$link['class'] = '';
			$link['img']   = apply_filters( 'rtsb/elements/share/default_img', '', $id, $link );

			if ( 'site' === $id ) {
				$link['url']   = esc_url( home_url() );
				$link['title'] = wp_strip_all_tags( get_bloginfo( 'name' ) );
			} elseif ( 0 === strpos( $id, 'http' ) ) {
				$link['url']   = esc_url( $id );
				$link['title'] = '';
			} else {
				$link['url']   = esc_url( get_permalink( $id ) );
				$link['title'] = wp_strip_all_tags( get_the_title( $id ) );

				if ( has_post_thumbnail( $id ) ) {
					$link['img'] = wp_get_attachment_image_url( get_post_thumbnail_id( $id ), 'full' );
				}

				$link['img'] = apply_filters( 'rtsb/elements/share/single_img', $link['img'], $id, $link );
			}

			$link['url'] = apply_filters( 'rtsb/elements/share/url', $link['url'], $link );

			switch ( $type['share_items'] ) {
				case 'facebook':
					$link['link']           = 'https://www.facebook.com/sharer/sharer.php?u=' . $link['url'] . '&display=popup&ref=plugin&src=share_button';
					$link['icon']           = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="18.8125" height="32" viewBox="0 0 602 1024"><path d="M548 6.857v150.857h-89.714q-49.143 0-66.286 20.571t-17.143 61.714v108h167.429l-22.286 169.143h-145.143v433.714h-174.857v-433.714h-145.714v-169.143h145.714v-124.571q0-106.286 59.429-164.857t158.286-58.571q84 0 130.286 6.857z"></path></svg>';
					$link['attr_title']     = esc_html__( 'Share on Facebook', 'shopbuilder' );
					$link['social_network'] = 'Facebook';
					$link['social_action']  = 'Share';
					break;
				case 'twitter':
					$link['link']           = 'https://twitter.com/intent/tweet?text=' . htmlspecialchars( rawurlencode( html_entity_decode( $link['title'], ENT_COMPAT, 'UTF-8' ) ), ENT_COMPAT, 'UTF-8' ) . '&url=' . $link['url'];
					$link['icon']           = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="29.71875" height="32" viewBox="0 0 951 1024"><path d="M925.714 233.143q-38.286 56-92.571 95.429 0.571 8 0.571 24 0 74.286-21.714 148.286t-66 142-105.429 120.286-147.429 83.429-184.571 31.143q-154.857 0-283.429-82.857 20 2.286 44.571 2.286 128.571 0 229.143-78.857-60-1.143-107.429-36.857t-65.143-91.143q18.857 2.857 34.857 2.857 24.571 0 48.571-6.286-64-13.143-106-63.714t-42-117.429v-2.286q38.857 21.714 83.429 23.429-37.714-25.143-60-65.714t-22.286-88q0-50.286 25.143-93.143 69.143 85.143 168.286 136.286t212.286 56.857q-4.571-21.714-4.571-42.286 0-76.571 54-130.571t130.571-54q80 0 134.857 58.286 62.286-12 117.143-44.571-21.143 65.714-81.143 101.714 53.143-5.714 106.286-28.571z"></path></svg>';
					$link['attr_title']     = esc_html__( 'Share on Twitter', 'shopbuilder' );
					$link['social_network'] = 'Twitter';
					$link['social_action']  = 'Tweet';
					break;
				case 'pinterest':
					$link['link']           = 'https://pinterest.com/pin/create/button/?url=' . $link['url'] . '&media=' . $link['img'] . '&description=' . $link['title'];
					$link['icon']           = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="22.84375" height="32" viewBox="0 0 731 1024"><path d="M0 341.143q0-61.714 21.429-116.286t59.143-95.143 86.857-70.286 105.714-44.571 115.429-14.857q90.286 0 168 38t126.286 110.571 48.571 164q0 54.857-10.857 107.429t-34.286 101.143-57.143 85.429-82.857 58.857-108 22q-38.857 0-77.143-18.286t-54.857-50.286q-5.714 22.286-16 64.286t-13.429 54.286-11.714 40.571-14.857 40.571-18.286 35.714-26.286 44.286-35.429 49.429l-8 2.857-5.143-5.714q-8.571-89.714-8.571-107.429 0-52.571 12.286-118t38-164.286 29.714-116q-18.286-37.143-18.286-96.571 0-47.429 29.714-89.143t75.429-41.714q34.857 0 54.286 23.143t19.429 58.571q0 37.714-25.143 109.143t-25.143 106.857q0 36 25.714 59.714t62.286 23.714q31.429 0 58.286-14.286t44.857-38.857 32-54.286 21.714-63.143 11.429-63.429 3.714-56.857q0-98.857-62.571-154t-163.143-55.143q-114.286 0-190.857 74t-76.571 187.714q0 25.143 7.143 48.571t15.429 37.143 15.429 26 7.143 17.429q0 16-8.571 41.714t-21.143 25.714q-1.143 0-9.714-1.714-29.143-8.571-51.714-32t-34.857-54-18.571-61.714-6.286-60.857z"></path></svg>';
					$link['attr_title']     = esc_html__( 'Share on Pinterest', 'shopbuilder' );
					$link['social_network'] = 'Pinterest';
					$link['social_action']  = 'Pin';
					break;
				case 'linkedin':
					$link['link']           = 'https://www.linkedin.com/shareArticle?url=' . $link['url'] . '&title=' . $link['title'];
					$link['icon']           = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="27.4375" height="32" viewBox="0 0 878 1024"><path d="M199.429 357.143v566.286h-188.571v-566.286h188.571zM211.429 182.286q0.571 41.714-28.857 69.714t-77.429 28h-1.143q-46.857 0-75.429-28t-28.571-69.714q0-42.286 29.429-70t76.857-27.714 76 27.714 29.143 70zM877.714 598.857v324.571h-188v-302.857q0-60-23.143-94t-72.286-34q-36 0-60.286 19.714t-36.286 48.857q-6.286 17.143-6.286 46.286v316h-188q1.143-228 1.143-369.714t-0.571-169.143l-0.571-27.429h188v82.286h-1.143q11.429-18.286 23.429-32t32.286-29.714 49.714-24.857 65.429-8.857q97.714 0 157.143 64.857t59.429 190z"></path></svg>';
					$link['attr_title']     = esc_html__( 'Share on LinkedIn', 'shopbuilder' );
					$link['social_network'] = 'LinkedIn';
					$link['social_action']  = 'Share';
					break;
				case 'skype':
					$link['link']           = 'https://web.skype.com/share?url=' . $link['url'];
					$link['icon']           = '<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="24px" height="24px" viewBox="0 0 24 24" enable-background="new 0 0 24 24" xml:space="preserve" class="eapps-social-share-buttons-item-icon"> <path d="M23.016,13.971c0.111-0.638,0.173-1.293,0.173-1.963 c0-6.213-5.014-11.249-11.199-11.249c-0.704,0-1.393,0.068-2.061,0.193C8.939,0.348,7.779,0,6.536,0C2.926,0,0,2.939,0,6.565 c0,1.264,0.357,2.443,0.973,3.445c-0.116,0.649-0.18,1.316-0.18,1.999c0,6.212,5.014,11.25,11.198,11.25 c0.719,0,1.419-0.071,2.099-0.201C15.075,23.656,16.229,24,17.465,24C21.074,24,24,21.061,24,17.435 C24,16.163,23.639,14.976,23.016,13.971z M12.386,19.88c-3.19,0-6.395-1.453-6.378-3.953c0.005-0.754,0.565-1.446,1.312-1.446 c1.877,0,1.86,2.803,4.85,2.803c2.098,0,2.814-1.15,2.814-1.95c0-2.894-9.068-1.12-9.068-6.563c0-2.945,2.409-4.977,6.196-4.753 c3.61,0.213,5.727,1.808,5.932,3.299c0.102,0.973-0.543,1.731-1.662,1.731c-1.633,0-1.8-2.188-4.613-2.188 c-1.269,0-2.341,0.53-2.341,1.679c0,2.402,9.014,1.008,9.014,6.295C18.441,17.882,16.012,19.88,12.386,19.88z"></path> </svg>';
					$link['attr_title']     = esc_html__( 'Share on Skype', 'shopbuilder' );
					$link['social_network'] = 'Skype';
					$link['social_action']  = 'Skype';
					break;
				case 'whatsapp':
					$link['link']           = 'https://wa.me/?text=' . $link['title'] . ' ' . $link['url'];
					$link['icon']           = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-whatsapp" viewBox="0 0 16 16"> <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/> </svg>';
					$link['attr_title']     = esc_html__( 'Share on Whatsapp', 'shopbuilder' );
					$link['social_network'] = 'Whatsapp';
					$link['social_action']  = 'Share';
					break;
				case 'reddit':
					$link['link']           = 'https://reddit.com/submit?url=' . $link['url'] . '&title=' . $link['title'];
					$link['icon']           = '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><title>ionicons-v5_logos</title><path d="M324,256a36,36,0,1,0,36,36A36,36,0,0,0,324,256Z"/><circle cx="188" cy="292" r="36" transform="translate(-97.43 94.17) rotate(-22.5)"/><path d="M496,253.77c0-31.19-25.14-56.56-56-56.56a55.72,55.72,0,0,0-35.61,12.86c-35-23.77-80.78-38.32-129.65-41.27l22-79L363.15,103c1.9,26.48,24,47.49,50.65,47.49,28,0,50.78-23,50.78-51.21S441,48,413,48c-19.53,0-36.31,11.19-44.85,28.77l-90-17.89L247.05,168.4l-4.63.13c-50.63,2.21-98.34,16.93-134.77,41.53A55.38,55.38,0,0,0,72,197.21c-30.89,0-56,25.37-56,56.56a56.43,56.43,0,0,0,28.11,49.06,98.65,98.65,0,0,0-.89,13.34c.11,39.74,22.49,77,63,105C146.36,448.77,199.51,464,256,464s109.76-15.23,149.83-42.89c40.53-28,62.85-65.27,62.85-105.06a109.32,109.32,0,0,0-.84-13.3A56.32,56.32,0,0,0,496,253.77ZM414,75a24,24,0,1,1-24,24A24,24,0,0,1,414,75ZM42.72,253.77a29.6,29.6,0,0,1,29.42-29.71,29,29,0,0,1,13.62,3.43c-15.5,14.41-26.93,30.41-34.07,47.68A30.23,30.23,0,0,1,42.72,253.77ZM390.82,399c-35.74,24.59-83.6,38.14-134.77,38.14S157,423.61,121.29,399c-33-22.79-51.24-52.26-51.24-83A78.5,78.5,0,0,1,75,288.72c5.68-15.74,16.16-30.48,31.15-43.79a155.17,155.17,0,0,1,14.76-11.53l.3-.21,0,0,.24-.17c35.72-24.52,83.52-38,134.61-38s98.9,13.51,134.62,38l.23.17.34.25A156.57,156.57,0,0,1,406,244.92c15,13.32,25.48,28.05,31.16,43.81a85.44,85.44,0,0,1,4.31,17.67,77.29,77.29,0,0,1,.6,9.65C442.06,346.77,423.86,376.24,390.82,399Zm69.6-123.92c-7.13-17.28-18.56-33.29-34.07-47.72A29.09,29.09,0,0,1,440,224a29.59,29.59,0,0,1,29.41,29.71A30.07,30.07,0,0,1,460.42,275.1Z"/><path d="M323.23,362.22c-.25.25-25.56,26.07-67.15,26.27-42-.2-66.28-25.23-67.31-26.27h0a4.14,4.14,0,0,0-5.83,0l-13.7,13.47a4.15,4.15,0,0,0,0,5.89h0c3.4,3.4,34.7,34.23,86.78,34.45,51.94-.22,83.38-31.05,86.78-34.45h0a4.16,4.16,0,0,0,0-5.9l-13.71-13.47a4.13,4.13,0,0,0-5.81,0Z"/></svg>';
					$link['attr_title']     = esc_html__( 'Share on Reddit', 'shopbuilder' );
					$link['social_network'] = 'Reddit';
					$link['social_action']  = 'Share';
					break;
				case 'telegram':
					$link['link']           = 'https://telegram.me/share/url?text=' . $link['title'] . '&url=' .$link['url'];
					$link['icon']           = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telegram" viewBox="0 0 16 16"> <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.666 2.01-.378.15-.577.298-.595.442-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294.26.006.549-.1.868-.32 2.179-1.471 3.304-2.214 3.374-2.23.05-.012.12-.026.166.016.047.041.042.12.037.141-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8.154 8.154 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629.093.06.183.125.27.187.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.426 1.426 0 0 0-.013-.315.337.337 0 0 0-.114-.217.526.526 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09z"/> </svg>';
					$link['attr_title']     = esc_html__( 'Share on Telegram', 'shopbuilder' );
					$link['social_network'] = 'Telegram';
					$link['social_action']  = 'Share';
					break;
			}

			$link['label']  = $type['share_text'];
			$link['target'] = '_blank';
			$link['rel']    = 'nofollow noopener noreferrer';

			$data       = '';
			$link       = apply_filters( 'rtsb/elements/share/share_link', $link, $id, $preset );
			$icon       = apply_filters( 'rtsb/elements/share/share_icon', $link['icon'], $preset );
			$target     = ! empty( $link['target'] ) ? ' target="' . esc_attr( $link['target'] ) . '" ' : '';
			$rel        = ! empty( $link['rel'] ) ? ' rel="' . esc_attr( $link['rel'] ) . '" ' : '';
			$attr_title = ! empty( $link['attr_title'] ) ? ' title="' . esc_attr( $link['attr_title'] ) . '" ' : '';
			$elements   = [];

			// Add classes.
			$css_classes = [
				'rtsb-share-btn',
				sanitize_html_class( $link['type'] ),
			];
			$css_classes = array_merge( $css_classes, explode( ' ', $link['class'] ) );
			$css_classes = array_map( 'sanitize_html_class', $css_classes );
			$css_classes = implode( ' ', array_filter( $css_classes ) );

            unset( $attr['pin-do'], $attr['action'] );

			if ( 'pinterest' === $type['share_items'] ) {
				$attr['pin-do'] = 'none';
			}

			if ( 'whatsapp' === $type['share_items'] ) {
				$attr['action'] = 'share/whatsapp/share';
			}

			$attr = apply_filters( 'rtsb/elements/share/link_data', $attr, $link, $id );

			if ( ! empty( $attr ) ) {
				foreach ( $attr as $key => $val ) {
					$data .= ' data-' . sanitize_html_class( $key ) . '="' . esc_attr( $val ) . '"';
				}
			}

			$additional_attr = apply_filters( 'rtsb/elements/share/additional_attr', [], $link, $id, $preset );

			if ( ! empty( $additional_attr ) ) {
				$attr_output = join( ' ', $additional_attr );

				if ( ! empty( $data ) ) {
					$attr_output = ' ' . $attr_output;
				}

				$data .= $attr_output;
			}

			$elements['wrapper_start'] = sprintf(
				'<li class="rtsb-share-item"><a href="%s"%s%s%s class="%s"%s>',
				! empty( $link['link'] ) ? esc_attr( $link['link'] ) : '',
				$attr_title,
				$target,
				$rel,
				$css_classes,
				$data
			);
			$elements['wrapper_end']   = '</a></li>';

			$elements['icon']       = ! empty( $show_icon ) ? '<span class="rtsb-share-icon">' . ( ! empty( $icon ) ? $icon : null ) . '</span>' : null;
			$elements['label']      = ! empty( $link['label'] ) ? '<span class="rtsb-share-label">' . $link['label'] . '</span>' : null;
			$elements['icon_label'] = '<span class="rtsb-share-icon-label">' . $elements['icon'] . $elements['label'] . '</span>';
			$elements               = apply_filters( 'rtsb/elements/share/output_elements', $elements, $link, $id );

			$output .= $elements['wrapper_start'] . $elements['icon_label'] . $elements['wrapper_end'];
		}

		return apply_filters( 'rtsb/elements/share/list_output', $output );
	}
}
