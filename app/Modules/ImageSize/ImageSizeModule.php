<?php
/**
 * ImageSize module — registered image size queries.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\ImageSize;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * ImageSizeModule
 */
class ImageSizeModule {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Return all registered WordPress image sizes as a key => label map.
	 *
	 * @return array<string,string>
	 */
	public function get_registered_image_size(): array {
		$image_sizes = wp_get_registered_image_subsizes();
		$size        = [];
		foreach ( $image_sizes as $key => $val ) {
			$size[ $key ] = $key . ' (' . $val['width'] . 'x' . $val['height'] . ')';
		}
		return $size;
	}
}
