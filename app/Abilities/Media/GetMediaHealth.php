<?php
/**
 * Ability: tsmlt/get-media-health.
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
 * File and record integrity of up to 50 attachments: the attached file
 * (reference, existence, readability), its real type against the stored
 * MIME type, for raster images the metadata and its recorded file size and
 * dimensions, the attached post, and whether alt text is set.
 *
 * The type is detected from the file header with wp_get_image_mime(), which
 * only recognises images: a mismatch is reported only when it detects an
 * image type different from the stored one, never guessed for other files.
 * Dimensions come from wp_getimagesize() (header only). The attached post is
 * checked with Fns::is_valid_attachment_parent().
 *
 * Read-only: never relinks parents, regenerates or repairs anything. Fixed
 * issue codes only; no paths, file names, EXIF or other metadata. Thumbnails,
 * usage, duplicates, compression and conversion have their own tools.
 *
 * Registered with `meta.mcp.public` false: listed by name on the dedicated
 * server and the default server, not run through the default gateway.
 */
class GetMediaHealth extends Ability {

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
	 * Per-item statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'healthy', 'issues', 'not_found', 'forbidden' ];

	/**
	 * Issue codes, in the order they are checked.
	 *
	 * @var string[]
	 */
	const ISSUES = [ 'no_attached_file', 'file_missing', 'file_unreadable', 'mime_mismatch', 'metadata_missing', 'filesize_mismatch', 'dimensions_mismatch', 'invalid_parent' ];

	/**
	 * Shape of a MIME type that is returned.
	 *
	 * @var string
	 */
	const MIME_PATTERN = '#^[a-z0-9.+-]{1,40}/[a-z0-9.+-]{1,80}$#';

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/get-media-health';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get media health', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Check the file and record integrity of 1 to 50 attachments. status: healthy, issues, not_found or forbidden. issues lists fixed codes: no_attached_file (no file recorded), file_missing, file_unreadable, mime_mismatch (the file header shows a different image type than the stored one), metadata_missing (an image without usable WordPress image data), filesize_mismatch and dimensions_mismatch (the recorded size or dimensions differ from the file), invalid_parent (attached to a post that is missing, trashed or not a real post). details gives the compared values for those; has_alt_text whether alt text is set. For generated sizes use tsmlt-get-thumbnail-status; for unused media tsmlt-list-media-usage; for duplicates tsmlt-find-duplicate-media. Read-only; never repairs anything.', 'media-library-tools' );
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
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'items', 'summary' ],
			'properties'           => [
				'items'   => [
					'type'  => 'array',
					'items' => self::item_schema(),
				],
				'summary' => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => [ 'statuses', 'issues' ],
					'properties'           => [
						'statuses' => [
							'type'                 => 'object',
							'additionalProperties' => [ 'type' => 'integer' ],
						],
						'issues'   => [
							'type'                 => 'object',
							'additionalProperties' => [ 'type' => 'integer' ],
						],
					],
				],
			],
		];
	}

	/**
	 * Output schema of one inspect() result. Shared with
	 * tsmlt/audit-media-health.
	 *
	 * @return array
	 */
	public static function item_schema(): array {
		$pair = static function ( string $first, string $second ): array {
			return [
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ $first, $second ],
				'properties'           => [
					$first  => [ 'type' => [ 'integer', 'string' ] ],
					$second => [ 'type' => [ 'integer', 'string' ] ],
				],
			];
		};
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'id', 'status', 'issues', 'details', 'has_alt_text' ],
			'properties'           => [
				'id'           => [ 'type' => 'integer' ],
				'status'       => [
					'type' => 'string',
					'enum' => self::STATUSES,
				],
				'issues'       => [
					'type'  => 'array',
					'items' => [
						'type' => 'string',
						'enum' => self::ISSUES,
					],
				],
				'details'      => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => [
						'mime_mismatch'       => $pair( 'stored', 'detected' ),
						'filesize_mismatch'   => $pair( 'recorded', 'actual' ),
						'dimensions_mismatch' => [
							'type'                 => 'object',
							'additionalProperties' => false,
							'required'             => [ 'recorded_width', 'recorded_height', 'actual_width', 'actual_height' ],
							'properties'           => [
								'recorded_width'  => [ 'type' => 'integer' ],
								'recorded_height' => [ 'type' => 'integer' ],
								'actual_width'    => [ 'type' => 'integer' ],
								'actual_height'   => [ 'type' => 'integer' ],
							],
						],
						'invalid_parent'      => [
							'type'                 => 'object',
							'additionalProperties' => false,
							'required'             => [ 'parent_id' ],
							'properties'           => [
								'parent_id' => [ 'type' => 'integer' ],
							],
						],
					],
				],
				'has_alt_text' => [ 'type' => 'boolean' ],
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
	 * Check each attachment.
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

		_prime_post_caches( $ids, false, true );
		$items    = [];
		$statuses = array_fill_keys( self::STATUSES, 0 );
		$issues   = array_fill_keys( self::ISSUES, 0 );
		foreach ( $ids as $id ) {
			$item = self::inspect( $id );
			++$statuses[ $item['status'] ];
			foreach ( $item['issues'] as $issue ) {
				++$issues[ $issue ];
			}
			$items[] = $item;
		}

		return [
			'items'   => $items,
			'summary' => [
				'statuses' => $statuses,
				'issues'   => $issues,
			],
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
	 * Health of one attachment: status (healthy, issues, not_found or
	 * forbidden), issue codes, compared values and whether alt text is set.
	 * Read-only. Also used by tsmlt/audit-media-health.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array{id: int, status: string, issues: string[], details: object, has_alt_text: bool}
	 */
	public static function inspect( int $id ): array {
		$item = [
			'id'           => $id,
			'status'       => 'not_found',
			'issues'       => [],
			'details'      => (object) [],
			'has_alt_text' => false,
		];

		$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );
		if ( is_wp_error( $attachment ) || ! $attachment instanceof WP_Post || 'inherit' !== $attachment->post_status ) {
			return $item;
		}
		if ( ! AbilityGuard::can_access_attachment( $attachment, 'edit_post' ) ) {
			$item['status'] = 'forbidden';
			return $item;
		}

		$issues  = [];
		$details = [];
		self::check_file( $attachment, $issues, $details );
		self::check_parent( $attachment, $issues, $details );

		$alt                  = get_post_meta( $id, '_wp_attachment_image_alt', true );
		$item['has_alt_text'] = is_string( $alt ) && '' !== trim( $alt );
		$item['issues']       = array_values( array_intersect( self::ISSUES, $issues ) );
		$item['details']      = (object) $details;
		$item['status']       = [] === $item['issues'] ? 'healthy' : 'issues';
		return $item;
	}

	/**
	 * File checks: reference, existence, readability, header type, and for
	 * raster images the metadata, file size and dimensions.
	 *
	 * @param WP_Post $attachment Attachment.
	 * @param array   $issues     Issue codes, added to.
	 * @param array   $details    Compared values, added to.
	 *
	 * @return void
	 */
	private static function check_file( WP_Post $attachment, array &$issues, array &$details ): void {
		$stored = get_post_meta( $attachment->ID, '_wp_attached_file', true );
		if ( ! is_string( $stored ) || '' === trim( $stored ) ) {
			$issues[] = 'no_attached_file';
			return;
		}
		$file = get_attached_file( $attachment->ID );
		if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
			$issues[] = 'file_missing';
			return;
		}
		if ( ! is_readable( $file ) ) {
			$issues[] = 'file_unreadable';
			return;
		}

		$stored_mime = self::normalise_mime( (string) $attachment->post_mime_type );
		$detected    = wp_get_image_mime( $file );
		$detected    = is_string( $detected ) ? self::normalise_mime( $detected ) : '';
		// Only a type the header identifies counts; nothing is guessed.
		if ( '' !== $detected && $detected !== $stored_mime ) {
			$issues[]                 = 'mime_mismatch';
			$details['mime_mismatch'] = [
				'stored'   => preg_match( self::MIME_PATTERN, $stored_mime ) ? $stored_mime : '',
				'detected' => preg_match( self::MIME_PATTERN, $detected ) ? $detected : '',
			];
		}

		if ( ! self::is_raster_image( $attachment ) ) {
			return;
		}
		$metadata = wp_get_attachment_metadata( $attachment->ID );
		$width    = is_array( $metadata ) ? (int) ( $metadata['width'] ?? 0 ) : 0;
		$height   = is_array( $metadata ) ? (int) ( $metadata['height'] ?? 0 ) : 0;
		if ( $width <= 0 || $height <= 0 ) {
			$issues[] = 'metadata_missing';
			return;
		}

		// Uploads from before WordPress recorded file sizes have none; not a mismatch.
		if ( isset( $metadata['filesize'] ) && is_numeric( $metadata['filesize'] ) ) {
			$recorded = (int) $metadata['filesize'];
			$actual   = (int) filesize( $file );
			if ( $recorded !== $actual ) {
				$issues[]                     = 'filesize_mismatch';
				$details['filesize_mismatch'] = [
					'recorded' => $recorded,
					'actual'   => $actual,
				];
			}
		}

		$size = wp_getimagesize( $file );
		if ( is_array( $size ) && (int) ( $size[0] ?? 0 ) > 0 && (int) ( $size[1] ?? 0 ) > 0
			&& ( (int) $size[0] !== $width || (int) $size[1] !== $height ) ) {
			$issues[]                       = 'dimensions_mismatch';
			$details['dimensions_mismatch'] = [
				'recorded_width'  => $width,
				'recorded_height' => $height,
				'actual_width'    => (int) $size[0],
				'actual_height'   => (int) $size[1],
			];
		}
	}

	/**
	 * Attached post check: a non-zero parent must be a real post.
	 *
	 * @param WP_Post $attachment Attachment.
	 * @param array   $issues     Issue codes, added to.
	 * @param array   $details    Compared values, added to.
	 *
	 * @return void
	 */
	private static function check_parent( WP_Post $attachment, array &$issues, array &$details ): void {
		$parent = (int) $attachment->post_parent;
		if ( $parent > 0 && ! Fns::is_valid_attachment_parent( $parent ) ) {
			$issues[]                  = 'invalid_parent';
			$details['invalid_parent'] = [ 'parent_id' => $parent ];
		}
	}

	/**
	 * Whether the attachment is a raster image: an image to WordPress, and
	 * not SVG.
	 *
	 * @param WP_Post $attachment Attachment.
	 *
	 * @return bool
	 */
	private static function is_raster_image( WP_Post $attachment ): bool {
		return 'image/svg+xml' !== $attachment->post_mime_type && wp_attachment_is_image( $attachment->ID );
	}

	/**
	 * A MIME type in lower case, with image/jpg read as image/jpeg.
	 *
	 * @param string $mime MIME type.
	 *
	 * @return string
	 */
	private static function normalise_mime( string $mime ): string {
		$mime = strtolower( trim( $mime ) );
		return 'image/jpg' === $mime ? 'image/jpeg' : $mime;
	}
}
