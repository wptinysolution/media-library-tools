<?php
/**
 * Ability: tsmlt/get-image-size-details.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities\Media;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abs\Ability;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Modules\ImageSize\ImageSizeModule;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * One registered image size by name (ImageSizeModule::describe()).
 * Read-only.
 */
class GetImageSizeDetails extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * No registered size has that name.
	 *
	 * @var string
	 */
	const ERROR_NOT_FOUND = 'tsmlt_image_size_not_found';

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/get-image-size-details';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get image size details', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Return one registered image size by its exact name (as tsmlt/list-image-sizes lists it): width and height in pixels (0 means not limited), crop and crop position, source (core, media_library_tools or other) and whether it is disabled on the Image Sizes page. An unknown name is refused with tsmlt_image_size_not_found. Read-only.', 'media-library-tools' );
	}

	/**
	 * Listed by name only; not run through the default gateway.
	 *
	 * @return bool
	 */
	protected function is_mcp_public(): bool {
		return false;
	}

	/**
	 * Input schema.
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'name' ],
			'properties'           => [
				'name' => [
					'type'      => 'string',
					'minLength' => 1,
					'maxLength' => 100,
				],
			],
		];
	}

	/**
	 * Output schema.
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {
		return ListImageSizes::size_schema();
	}

	/**
	 * Annotations.
	 *
	 * @return array<string, bool>
	 */
	protected function get_annotations(): array {
		return [
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		];
	}

	/**
	 * Describe one size.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		// The callback can be reached without the permission callback.
		$base = AbilityGuard::check_base_capability();
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$name = is_array( $input ) && [ 'name' ] === array_keys( $input ) ? $input['name'] : null;
		if ( ! is_string( $name ) || '' === $name || strlen( $name ) > 100 ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Pass name: the exact name of a registered image size.', 'media-library-tools' ), 400 );
		}
		$size = ImageSizeModule::instance()->describe( $name );
		if ( null === $size ) {
			return AbilityGuard::error( self::ERROR_NOT_FOUND, __( 'No image size is registered with that name. List them with tsmlt/list-image-sizes.', 'media-library-tools' ), 404 );
		}
		return $size;
	}
}
