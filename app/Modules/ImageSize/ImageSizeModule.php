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

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Registered image sizes: the admin page's key => label list, and a
 * description of each size for the MCP image-size tools (dimensions, crop,
 * where it comes from, and whether Media Library Tools disables it).
 */
class ImageSizeModule {

	/**
	 * Sizes WordPress itself registers.
	 *
	 * @var string[]
	 */
	const CORE_SIZES = [ 'thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048' ];

	/**
	 * Filter: names of registered sizes another part of the plugin manages
	 * (Pro's custom sizes); they are reported with source
	 * media_library_tools.
	 *
	 * @var string
	 */
	const MANAGED_FILTER = 'tsmlt_managed_image_sizes';

	/**
	 * Sources a size may have.
	 *
	 * @var string[]
	 */
	const SOURCES = [ 'core', 'media_library_tools', 'other' ];

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

	/**
	 * Every registered size, described (registration order).
	 *
	 * @return array<int, array>
	 */
	public function describe_all(): array {
		$out = [];
		foreach ( array_keys( wp_get_registered_image_subsizes() ) as $name ) {
			$size = $this->describe( (string) $name );
			if ( null !== $size ) {
				$out[] = $size;
			}
		}
		return $out;
	}

	/**
	 * One registered size, described, or null when no size has that name.
	 *
	 * Source is core for WordPress's own sizes, media_library_tools for sizes
	 * the plugin manages (MANAGED_FILTER), and other for sizes a theme or
	 * another plugin registered (which of the two is not known). Disabled
	 * sizes are registered but not made on upload (Image Sizes page).
	 *
	 * @param string $name Size name.
	 *
	 * @return array{name: string, width: int, height: int, crop: bool, crop_position: ?string[], source: string, managed: bool, disabled: bool}|null
	 */
	public function describe( string $name ): ?array {
		$registered = wp_get_registered_image_subsizes();
		if ( '' === $name || ! isset( $registered[ $name ] ) || ! is_array( $registered[ $name ] ) ) {
			return null;
		}
		$size     = $registered[ $name ];
		$crop     = $size['crop'] ?? false;
		$position = null;
		if ( is_array( $crop ) ) {
			$position = array_values( array_map( 'strval', array_slice( $crop, 0, 2 ) ) );
		}
		$managed = in_array( $name, $this->managed_sizes(), true );
		$source  = 'other';
		if ( $managed ) {
			$source = 'media_library_tools';
		} elseif ( in_array( $name, self::CORE_SIZES, true ) ) {
			$source = 'core';
		}
		return [
			'name'          => $name,
			'width'         => max( 0, (int) ( $size['width'] ?? 0 ) ),
			'height'        => max( 0, (int) ( $size['height'] ?? 0 ) ),
			'crop'          => (bool) $crop,
			'crop_position' => $position,
			'source'        => $source,
			'managed'       => $managed,
			'disabled'      => in_array( $name, $this->disabled_sizes(), true ),
		];
	}

	/**
	 * Names of sizes the plugin manages (MANAGED_FILTER; none without Pro).
	 *
	 * @return string[]
	 */
	public function managed_sizes(): array {
		$names = apply_filters( self::MANAGED_FILTER, [] );
		return is_array( $names ) ? array_values( array_filter( $names, 'is_string' ) ) : [];
	}

	/**
	 * Size names disabled on the Image Sizes page (deregistered_image_sizes).
	 *
	 * @return string[]
	 */
	public function disabled_sizes(): array {
		$options = Fns::get_options();
		$sizes   = $options['deregistered_image_sizes'] ?? [];
		return is_array( $sizes ) ? array_values( array_filter( $sizes, 'is_string' ) ) : [];
	}
}
