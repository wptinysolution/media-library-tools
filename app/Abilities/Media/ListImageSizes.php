<?php
/**
 * Ability: tsmlt/list-image-sizes.
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
 * Every registered image size (ImageSizeModule::describe_all()). Read-only.
 */
class ListImageSizes extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

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
		return 'tsmlt/list-image-sizes';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'List image sizes', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'List every registered image size (the sizes WordPress makes from each uploaded image): name, width and height in pixels (0 means not limited), whether it is cropped (and the crop position when one is set), its source (core: WordPress; media_library_tools: a custom size made in Media Library Tools Pro, which tsmlt/update-image-size and tsmlt/unregister-image-size can change; other: registered by the theme or another plugin), and whether it is disabled on the Image Sizes page (registered but not made on upload). Read-only.', 'media-library-tools' );
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
	 * Input schema: no input.
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			// Lets clients call the ability with no arguments at all.
			'default'              => [],
			'properties'           => [],
		];
	}

	/**
	 * Output schema.
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'sizes', 'total' ],
			'properties'           => [
				'sizes' => [
					'type'  => 'array',
					'items' => self::size_schema(),
				],
				'total' => [ 'type' => 'integer' ],
			],
		];
	}

	/**
	 * Schema of one size, shared with tsmlt/get-image-size-details.
	 *
	 * @return array
	 */
	public static function size_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'name', 'width', 'height', 'crop', 'crop_position', 'source', 'managed', 'disabled' ],
			'properties'           => [
				'name'          => [ 'type' => 'string' ],
				'width'         => [ 'type' => 'integer' ],
				'height'        => [ 'type' => 'integer' ],
				'crop'          => [ 'type' => 'boolean' ],
				'crop_position' => [
					'type'  => [ 'array', 'null' ],
					'items' => [ 'type' => 'string' ],
				],
				'source'        => [
					'type' => 'string',
					'enum' => ImageSizeModule::SOURCES,
				],
				'managed'       => [
					'type'        => 'boolean',
					'description' => 'Whether Media Library Tools manages it (can be changed through MCP with Pro).',
				],
				'disabled'      => [
					'type'        => 'boolean',
					'description' => 'Registered but not made on upload (Image Sizes page).',
				],
			],
		];
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
	 * List the sizes.
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
		if ( null !== $input && ( ! is_array( $input ) || ! empty( $input ) ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'This tool takes no input.', 'media-library-tools' ), 400 );
		}
		$sizes = ImageSizeModule::instance()->describe_all();
		return [
			'sizes' => $sizes,
			'total' => count( $sizes ),
		];
	}
}
