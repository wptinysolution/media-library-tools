<?php
/**
 * Ability: tsmlt/get-thumbnail-status.
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
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;

/**
 * Thumbnail (generated image size) status of up to 50 attachments, from
 * WordPress's attachment metadata, the registered sizes and the files on
 * disk.
 *
 * - Recorded sizes: the metadata's `sizes`, each with whether its file
 *   exists (checked by file name only, inside the attachment's folder) and
 *   whether the size is still registered.
 * - Missing sizes: wp_get_missing_image_subsizes() — registered sizes the
 *   image is large enough for (measured on the scaled-upload original when
 *   there is one) and that are not recorded — minus the sizes disabled in
 *   Media Library Tools (`deregistered_image_sizes`), which the core helper
 *   does not know about.
 * - SVG and non-image attachments are not_applicable.
 *
 * Read-only: reads metadata, options, registered sizes, file existence and
 * image headers. Never regenerates, deletes or updates anything. No paths,
 * file names or EXIF (`image_meta`) are returned.
 *
 * Registered with `meta.mcp.public` false: listed by name on the dedicated
 * server and the default server, not run through the default gateway.
 */
class GetThumbnailStatus extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Most IDs per request.
	 *
	 * @var int
	 */
	const MAX_IDS = 50;

	/**
	 * Per-item statuses, in order of precedence.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'not_found', 'forbidden', 'not_applicable', 'file_missing', 'no_metadata', 'missing_files', 'missing_sizes', 'complete' ];

	/**
	 * Shape of a size name that is returned.
	 *
	 * @var string
	 */
	const NAME_PATTERN = '/^[a-zA-Z0-9_-]{1,100}$/';

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/get-thumbnail-status';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get thumbnail status', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Return the thumbnail (generated image size) status of 1 to 50 attachments. status: complete, missing_sizes (registered sizes the image is large enough for are not generated), missing_files (a generated size is recorded but its file is gone), no_metadata (WordPress has no image data), not_applicable (SVG or not an image), file_missing (the image file itself is gone), not_found or forbidden. With the image\'s width and height, the original\'s dimensions for scaled uploads, each recorded size (name, dimensions, MIME type, file size, whether its file exists and whether the size is still registered), the missing sizes with their registered dimensions, and the sizes left out because they are disabled in Media Library Tools. Missing sizes are computed from the current registered sizes. Read-only; never regenerates or deletes anything.', 'media-library-tools' );
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
	 * @return array
	 */
	protected function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'ids' ],
			'properties'           => [
				'ids' => [
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => self::MAX_IDS,
					'uniqueItems' => true,
					'items'       => [
						'type'    => 'integer',
						'minimum' => 1,
					],
				],
			],
		];
	}

	/**
	 * @return array
	 */
	protected function get_output_schema(): array {
		$dimension = [ 'type' => [ 'integer', 'null' ] ];
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'items', 'summary' ],
			'properties'           => [
				'items'   => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'status', 'width', 'height', 'original_width', 'original_height', 'has_original', 'sizes', 'missing', 'disabled_sizes_excluded' ],
						'properties'           => [
							'id'                      => [ 'type' => 'integer' ],
							'status'                  => [
								'type' => 'string',
								'enum' => self::STATUSES,
							],
							'width'                   => $dimension,
							'height'                  => $dimension,
							'original_width'          => $dimension,
							'original_height'         => $dimension,
							'has_original'            => [
								'type'        => 'boolean',
								'description' => 'A full-size original of a scaled or rotated upload exists.',
							],
							'sizes'                   => [
								'type'  => 'array',
								'items' => [
									'type'                 => 'object',
									'additionalProperties' => false,
									'required'             => [ 'name', 'width', 'height', 'mime_type', 'filesize', 'file_exists', 'registered' ],
									'properties'           => [
										'name'        => [ 'type' => 'string' ],
										'width'       => $dimension,
										'height'      => $dimension,
										'mime_type'   => [ 'type' => 'string' ],
										'filesize'    => $dimension,
										'file_exists' => [ 'type' => 'boolean' ],
										'registered'  => [ 'type' => 'boolean' ],
									],
								],
							],
							'missing'                 => [
								'type'  => 'array',
								'items' => [
									'type'                 => 'object',
									'additionalProperties' => false,
									'required'             => [ 'name', 'width', 'height', 'crop' ],
									'properties'           => [
										'name'   => [ 'type' => 'string' ],
										'width'  => [ 'type' => 'integer' ],
										'height' => [ 'type' => 'integer' ],
										'crop'   => [ 'type' => 'boolean' ],
									],
								],
							],
							'disabled_sizes_excluded' => [
								'type'  => 'array',
								'items' => [ 'type' => 'string' ],
							],
						],
					],
				],
				'summary' => [
					'type'                 => 'object',
					'additionalProperties' => [ 'type' => 'integer' ],
				],
			],
		];
	}

	/**
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
	 * Report each attachment's status.
	 *
	 * @param mixed $input Ability input, already validated against the schema.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		// The callback can be reached without the permission callback.
		$base = AbilityGuard::check_base_capability();
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$ids = self::sanitize_input( $input );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}
		if ( ! function_exists( 'wp_get_missing_image_subsizes' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		_prime_post_caches( $ids, true, true );
		$registered = wp_get_registered_image_subsizes();
		$disabled   = self::disabled_sizes();
		$items      = [];
		$summary    = array_fill_keys( self::STATUSES, 0 );
		foreach ( $ids as $id ) {
			$item = self::item( $id, $registered, $disabled );
			++$summary[ $item['status'] ];
			$items[] = $item;
		}

		return [
			'items'   => $items,
			'summary' => $summary,
		];
	}

	/**
	 * Validate the input: 1 to 50 distinct positive integer IDs.
	 *
	 * @param mixed $input Raw input.
	 *
	 * @return int[]|WP_Error
	 */
	private static function sanitize_input( $input ) {
		$ids = is_array( $input ) && [] === array_diff( array_keys( $input ), [ 'ids' ] ) ? ( $input['ids'] ?? null ) : null;
		$ok  = is_array( $ids ) && [] !== $ids && count( $ids ) <= self::MAX_IDS && array_keys( $ids ) === range( 0, count( $ids ) - 1 );
		foreach ( $ok ? $ids : [] as $id ) {
			$ok = $ok && is_int( $id ) && $id > 0;
		}
		if ( ! $ok || count( array_unique( $ids ) ) !== count( $ids ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'ids must be a list of 1 to 50 distinct attachment IDs (positive integers).', 'media-library-tools' ), 400 );
		}
		return $ids;
	}

	/**
	 * Size names disabled in Media Library Tools (deregistered_image_sizes).
	 *
	 * @return string[]
	 */
	private static function disabled_sizes(): array {
		$options = Fns::get_options();
		$sizes   = $options['deregistered_image_sizes'] ?? [];
		return is_array( $sizes ) ? array_values( array_filter( $sizes, 'is_string' ) ) : [];
	}

	/**
	 * Status of one attachment.
	 *
	 * @param int      $id         Attachment ID.
	 * @param array    $registered wp_get_registered_image_subsizes().
	 * @param string[] $disabled   Disabled size names.
	 *
	 * @return array
	 */
	private static function item( int $id, array $registered, array $disabled ): array {
		$item = [
			'id'                      => $id,
			'status'                  => 'not_found',
			'width'                   => null,
			'height'                  => null,
			'original_width'          => null,
			'original_height'         => null,
			'has_original'            => false,
			'sizes'                   => [],
			'missing'                 => [],
			'disabled_sizes_excluded' => [],
		];

		$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );
		if ( is_wp_error( $attachment ) || ! $attachment instanceof WP_Post || 'inherit' !== $attachment->post_status ) {
			return $item;
		}
		if ( ! AbilityGuard::can_access_attachment( $attachment, 'edit_post' ) ) {
			$item['status'] = 'forbidden';
			return $item;
		}
		// WordPress counts SVG as an image (image/ MIME type), but it has no
		// generated sizes; its metadata lists sizes without files.
		if ( 'image/svg+xml' === $attachment->post_mime_type || ! wp_attachment_is_image( $id ) ) {
			$item['status'] = 'not_applicable';
			return $item;
		}
		$file = get_attached_file( $id );
		if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
			$item['status'] = 'file_missing';
			return $item;
		}
		$metadata = wp_get_attachment_metadata( $id );
		if ( ! is_array( $metadata ) || [] === $metadata ) {
			$item['status'] = 'no_metadata';
			return $item;
		}

		$folder                  = trailingslashit( dirname( $file ) );
		$item['width']           = self::dimension( $metadata['width'] ?? null );
		$item['height']          = self::dimension( $metadata['height'] ?? null );
		$original                = self::original( $metadata, $folder );
		$item['has_original']    = null !== $original;
		$item['original_width']  = $original[0] ?? null;
		$item['original_height'] = $original[1] ?? null;
		$item['sizes']           = self::sizes( $metadata['sizes'] ?? [], $folder, $registered );

		foreach ( (array) wp_get_missing_image_subsizes( $id ) as $name => $size ) {
			if ( ! is_string( $name ) || ! preg_match( self::NAME_PATTERN, $name ) ) {
				continue;
			}
			if ( in_array( $name, $disabled, true ) ) {
				$item['disabled_sizes_excluded'][] = $name;
				continue;
			}
			$item['missing'][] = [
				'name'   => $name,
				'width'  => max( 0, (int) ( $size['width'] ?? 0 ) ),
				'height' => max( 0, (int) ( $size['height'] ?? 0 ) ),
				'crop'   => ! empty( $size['crop'] ),
			];
		}

		if ( in_array( false, array_column( $item['sizes'], 'file_exists' ), true ) ) {
			$item['status'] = 'missing_files';
		} elseif ( [] !== $item['missing'] ) {
			$item['status'] = 'missing_sizes';
		} else {
			$item['status'] = 'complete';
		}
		return $item;
	}

	/**
	 * Dimensions of the full-size original of a scaled or rotated upload,
	 * read from its header; null when there is none. The stored name is
	 * reduced to a file name, so the check stays in the attachment's folder.
	 *
	 * @param array  $metadata Attachment metadata.
	 * @param string $folder   The attachment's folder, with a trailing slash.
	 *
	 * @return array{0: int|null, 1: int|null}|null
	 */
	private static function original( array $metadata, string $folder ): ?array {
		$name = $metadata['original_image'] ?? '';
		if ( ! is_string( $name ) || '' === wp_basename( $name ) ) {
			return null;
		}
		$path = $folder . wp_basename( $name );
		if ( ! file_exists( $path ) ) {
			return null;
		}
		$size = wp_getimagesize( $path );
		return [
			is_array( $size ) ? self::dimension( $size[0] ?? null ) : null,
			is_array( $size ) ? self::dimension( $size[1] ?? null ) : null,
		];
	}

	/**
	 * Recorded sizes: name, dimensions, MIME type, file size, whether the
	 * file exists (by file name, in the attachment's folder) and whether the
	 * size is registered. Never the file name.
	 *
	 * @param mixed  $sizes      Metadata sizes.
	 * @param string $folder     The attachment's folder, with a trailing slash.
	 * @param array  $registered Registered sizes.
	 *
	 * @return array
	 */
	private static function sizes( $sizes, string $folder, array $registered ): array {
		$out = [];
		foreach ( is_array( $sizes ) ? $sizes : [] as $name => $size ) {
			if ( ! is_string( $name ) || ! preg_match( self::NAME_PATTERN, $name ) || ! is_array( $size ) ) {
				continue;
			}
			$file  = isset( $size['file'] ) && is_string( $size['file'] ) ? wp_basename( $size['file'] ) : '';
			$mime  = isset( $size['mime-type'] ) && is_string( $size['mime-type'] ) && preg_match( '#^image/[a-z0-9.+-]{1,50}$#', $size['mime-type'] ) ? $size['mime-type'] : '';
			$out[] = [
				'name'        => $name,
				'width'       => self::dimension( $size['width'] ?? null ),
				'height'      => self::dimension( $size['height'] ?? null ),
				'mime_type'   => $mime,
				'filesize'    => self::dimension( $size['filesize'] ?? null ),
				'file_exists' => '' !== $file && file_exists( $folder . $file ),
				'registered'  => isset( $registered[ $name ] ),
			];
		}
		return $out;
	}

	/**
	 * A positive integer, or null.
	 *
	 * @param mixed $value Value.
	 *
	 * @return int|null
	 */
	private static function dimension( $value ): ?int {
		return is_numeric( $value ) && (int) $value > 0 ? (int) $value : null;
	}
}
