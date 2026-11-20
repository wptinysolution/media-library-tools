<?php
/**
 * Ability: tsmlt/get-media-image-preview.
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
use TinySolutions\mlt\Abilities\ImagePreview;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Return a downscaled, metadata-free preview of one JPEG, PNG or WebP
 * library image as an MCP image content block, so a client's own
 * vision-capable model can see the image (for example to write alt text,
 * then saved with tsmlt/update-media-metadata).
 *
 * The result has the MCP Adapter's image shape (`type` image, raw bytes in
 * `results`, `mimeType`), which the adapter turns into an image content
 * block. The output schema is therefore empty: an image block carries no
 * structured content to describe. No URL, path, file name or metadata is
 * returned. Read-only.
 */
class GetMediaImagePreview extends Ability {

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
		return 'tsmlt/get-media-image-preview';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get media image preview', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Return one media-library image as an image you can look at: a JPEG, PNG or WebP preview at most 1024 pixels on its longest side (smaller if needed to stay under about 1 MB), turned upright, re-encoded and stripped of all metadata (no EXIF, GPS, IPTC, XMP or comments). Use it to see an image before writing its title, alt text, caption or description, then save them with tsmlt/update-media-metadata. One image per call. SVG, GIF, other types, images stored off this server and very large originals have no preview. No URL, path or file name is returned. Read-only; the image itself is never changed.', 'media-library-tools' );
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
			'required'             => [ 'id' ],
			'properties'           => [
				'id' => [
					'type'        => 'integer',
					'description' => 'Attachment ID.',
					'minimum'     => 1,
				],
			],
		];
	}

	/**
	 * Output schema: none. The result is an MCP image content block, which
	 * has no structured content for a schema to describe.
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {
		return [];
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
	 * Build the preview.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error The MCP Adapter's image result shape.
	 */
	public function execute( $input = null ) {
		// The callback can be reached without the permission callback.
		$base = AbilityGuard::check_base_capability();
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		if ( ! is_array( $input ) || [ 'id' ] !== array_keys( $input ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Pass exactly one attachment id.', 'media-library-tools' ), 400 );
		}
		$preview = ImagePreview::build( $input['id'] );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		return [
			'type'     => 'image',
			'results'  => $preview['bytes'],
			'mimeType' => $preview['mime_type'],
		];
	}
}
