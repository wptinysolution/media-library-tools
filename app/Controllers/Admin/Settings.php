<?php

namespace TinySolutions\mlt\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Settings
 */
class Settings {

	use SingletonTrait;

	/**
	 * Public function store.
	 * store data for post
	 *
	 * @since 1.0.0
	 */
	public function get_sections() {
		$sections = [
			'general'  => GeneralList::raw_list()
        ];
		return apply_filters( 'mlt/core/settings/sections', $sections );
	}

	/**
	 * Public function store.
	 * store data for post
	 *
	 * @since 1.0.0
	 */
	public function get_data() {
        $options = get_option( 'tsmlt_settings' );
        return wp_json_encode($options);
	}
}
