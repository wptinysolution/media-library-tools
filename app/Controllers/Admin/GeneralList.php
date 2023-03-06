<?php

namespace TinySolutions\mlt\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

/**
 * General Settings.
 */
class GeneralList{
	/**
	 * Package
	 *
	 * @return array
	 */
	public static function raw_list() {
		// TODO:: Need FIx the issue. Settings filed is not save before change 
		$list = [
            'billing_first_name'             => [
                'id'          => 'billing_first_name',
                'label'       => esc_html__( 'First name', 'shopbuilder' ),
                'type'        => 'checkbox',
                'orientation' => 'vertical',
                'value'       => [ 'show', 'required' ],
                'options'     => [
                    [
                        'value' => 'show',
                        'label' => 'Show First name?',
                    ],
                    [
                        'value' => 'required',
                        'label' => 'Required Field',
                    ],
                ],
            ],
            'billing_first_name_label'       => [
                'id'         => 'billing_first_name_label',
                'label'      => esc_html__( 'First name Label', 'shopbuilder' ),
                'type'       => 'text',
                'help'       => esc_html__( 'Leave empty to set default.', 'shopbuilder' ),
            ],
            'billing_first_name_placeholder' => [
                'id'         => 'billing_first_name_placeholder',
                'label'      => esc_html__( 'First name Placeholder', 'shopbuilder' ),
                'type'       => 'text',
                'help'       => esc_html__( 'Leave empty to set default.', 'shopbuilder' ),
            ],
		];
		return apply_filters( 'mlt/core/general_settings/raw_list', $list );
	}
}
