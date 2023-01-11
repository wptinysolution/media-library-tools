<?php
/**
 * Main FilterHooks class.
 *
 * @package RadiusTheme\SB
 */

namespace RadiusTheme\SB\Controllers\Hooks;

use RadiusTheme\SB\Helpers\BuilderFns;
use RadiusTheme\SB\Models\GeneralList;

defined( 'ABSPATH' ) || exit();

/**
 * Main FilterHooks class.
 */
class FilterHooks {
	/**
	 * Init Hooks.
	 *
	 * @return void
	 */
	public static function init_hooks() {
		add_filter( 'wp_kses_allowed_html', [ __CLASS__, 'custom_wpkses_post_tags' ], 10, 2 );
		add_filter( 'woocommerce_get_country_locale', [ __CLASS__, 'change_state_label_locale' ] );
		add_filter( 'woocommerce_checkout_fields', [ __CLASS__, 'woocommerce_checkout_fields' ] );
		add_filter( 'woocommerce_default_address_fields', [ __CLASS__, 'default_address_fields' ] );
		// add_filter( 'woocommerce_billing_fields', [ __CLASS__, 'checkout_billing_fields' ] );
		// add_filter( 'woocommerce_shipping_fields', [ __CLASS__, 'checkout_shipping_fields' ] );
		// /*
			// For testing
			// add_filter( 'woocommerce_checkout_fields' , function ( $fields ) {
			// $fields['billing']['billing_country']['required'] = false;
			// unset( $fields['billing']['billing_country'] );
			// return $fields;
			// }, 999 );
		// */
		/*
		add_filter( 'woocommerce_default_address_fields', function( $fields  ){
			// error_log( print_r( $fields , true ) . "\n\n" , 3, __DIR__ . '/log.txt' );
			$fields['state']['label'] = 'something';
			error_log( print_r( $fields , true ) . "\n\n" , 3, __DIR__ . '/log.txt' );
			return $fields;
		});
		*/

	}
	/**
	 * Localize Scripts
	 *
	 * @param array $locale locale variable.
	 *
	 * @return array
	 */
	public static function change_state_label_locale( $locale ) {
		// Only on checkout page.
		if ( ! BuilderFns::is_checkout() ) {
			return $locale;
		}

		$elementor_list = GeneralList::instance()->get_data();

		if ( ! array_key_exists( 'billing_form', $elementor_list ) ) {
			return $locale;
		}

		$billing_settings = $elementor_list['billing_form'];

		if ( ! $billing_settings['active'] ) {
			return $locale;
		}

		if ( ! empty( $billing_settings['billing_state_label'] ) ) {
			$locale[ WC()->countries->get_base_country() ]['state']['label'] = $billing_settings['billing_state_label'];
		}

		return $locale;
	}
	/**
	 * Add exceptions in wp_kses_post tags.
	 *
	 * @param array  $tags Allowed tags, attributes, and/or entities.
	 * @param string $context Context to judge allowed tags by. Allowed values are 'post'.
	 *
	 * @return array
	 */
	public static function custom_wpkses_post_tags( $tags, $context ) {
		if ( 'post' === $context ) {
			$tags['iframe'] = [
				'src'             => true,
				'height'          => true,
				'width'           => true,
				'frameborder'     => true,
				'allowfullscreen' => true,
			];

			$tags['svg'] = [
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
			];

			$tags['g']     = [ 'fill' => true ];
			$tags['title'] = [ 'title' => true ];
			$tags['path']  = [
				'd'               => true,
				'fill'            => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			];
		}

		return $tags;
	}

	/**
	 * Set field Keyword.
	 *
	 * @return array
	 */
	private static function checkout_field( $form_name, $billing_settings, $fields, $field_id = 'billing_field_name' ) {

		if ( ! isset( $billing_settings[ $field_id ] ) ) {
			return $fields;
		}

		$settings_value = $billing_settings[ $field_id ];
		if ( ! in_array( 'show', $settings_value, true ) ) {
			unset( $fields[ $form_name ][ $field_id ] );
			if ( $form_name . '_address_1' === $field_id ) {
				unset( $fields[ $form_name ][$form_name . '_address_2'] );
			}
			return $fields;
		}

		$fields[ $form_name ][ $field_id ]['required'] = in_array( 'required', $settings_value, true );

		$labels = [
			'label'       => $field_id . '_label',
			'placeholder' => $field_id . '_placeholder',
		];

		foreach ( $labels as $key => $value ) {
			if ( ! empty( $billing_settings[ $value ] ) ) {
				$fields[ $form_name ][ $field_id ][ $key ] = $billing_settings[ $value ];
			}
		}

		return $fields;
	}


	/**
	 * Set Widget Keyword.
	 *
	 * @return array
	 */
	public static function woocommerce_checkout_fields( $fields ) {
		// Only on checkout page.
		if ( ! BuilderFns::is_checkout() ) {
			return $fields;
		}

		$elementor_list = GeneralList::instance()->get_data();
		$fields_key     = [];

		$forms = [ 'billing_form', 'shipping_form' ];
		foreach ( $forms as $form ) {
			if ( ! array_key_exists( $form, $elementor_list ) || ! $elementor_list[ $form ]['active'] ) {
				continue;
			}
			$form_name                = str_replace( '_form', '', $form );
			$fields_key[ $form_name ] = [
				$form_name . '_first_name',
				$form_name . '_last_name',
				$form_name . '_company',
				$form_name . '_country',
				$form_name . '_postcode',
				$form_name . '_address_2',
				$form_name . '_address_1',
				$form_name . '_state',
				$form_name . '_city',
			];

			if ( 'billing_form' === $form ) {
				$fields_key[ $form_name ][] = $form_name . '_phone';
				$fields_key[ $form_name ][] = $form_name . '_email';
			}
		}
		foreach ( $fields_key as $form_name => $form_fields ) {
			foreach ( $form_fields as $field_id ) {
				$fields = self::checkout_field( $form_name, $elementor_list[ $form_name . '_form' ], $fields, $field_id );
			}
		}

		return $fields;
	}

	/**
	 * Set Widget Keyword.
	 *
	 * @return array
	 */
	public static function default_address_fields( $fields ) {
		// Only on checkout page.
		if ( ! BuilderFns::is_checkout() ) {
			return $fields;
		}

		$elementor_list = GeneralList::instance()->get_data();

		if ( ! array_key_exists( 'billing_form', $elementor_list ) ) {
			return $fields;
		}

		$billing_settings = $elementor_list['billing_form'];

		if ( ! $billing_settings['active'] ) {
			return $fields;
		}

		$fields_key = [
			'address_2',
			'address_1',
			'state',
			'city',
			'postcode',
		];
		foreach ( $fields_key as $value) {
			$fields = self::checkout_billing_address_field( $billing_settings, $fields, $value );
		}
		return $fields;
	}

	/**
	 * Set field Keyword.
	 *
	 * @return array
	 */
	private static function checkout_billing_address_field( $billing_settings, $fields, $field_id = '' ) {
		$settings_field_id = 'billing_' . $field_id;
		if ( ! isset( $billing_settings[ $settings_field_id ] ) ) {
			return $fields;
		}

		$settings_value = $billing_settings[ $settings_field_id ];

		$fields[ $field_id ]['required'] = in_array( 'required', $settings_value, true );
		
		$labels = [
			'label'       => $settings_field_id . '_label',
			'placeholder' => $settings_field_id . '_placeholder',
		];

		foreach ( $labels as $key => $value ) {
			if ( ! empty( $billing_settings[ $value ] ) ) {
				$fields[ $field_id ][ $key ] = $billing_settings[ $value ];
			}
		}

		return $fields;
	}

}

