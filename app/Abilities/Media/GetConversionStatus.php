<?php
/**
 * Ability: tsmlt/get-conversion-status.
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
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionCapabilities;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionMetadata;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;

/**
 * WebP/AVIF conversion status of up to 50 attachments, from the data the
 * Convert feature stores per attachment (ConversionMetadata, meta key
 * `_tsmlt_image_conversion_data`).
 *
 * Status: the stored status (completed, partial, skipped, failed; anything
 * else is reported as failed); not_converted when nothing is stored and the
 * MIME type is a supported source (ConversionCapabilities::SUPPORTED_SOURCES);
 * unsupported otherwise. `stale` comes from ConversionMetadata::is_stale()
 * and `converting` from AttachmentConverter::is_locked().
 *
 * Read-only: reads through ConversionMetadata::get_many_for_display(), which
 * only reads post meta and file sizes and times. Never calls the library
 * status read (which can rewrite the shared job state). Stored error texts
 * (`last_error`) and file paths are never returned; statuses and reasons are
 * returned only when they are plain codes.
 *
 * Registered with `meta.mcp.public` false: listed by name on the dedicated
 * server and the default server, not run through the default gateway.
 */
class GetConversionStatus extends Ability {

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
	 * Attachment statuses stored by the feature.
	 *
	 * @var string[]
	 */
	const STORED_STATUSES = [ 'completed', 'partial', 'skipped', 'failed' ];

	/**
	 * Per-item statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'completed', 'partial', 'skipped', 'failed', 'not_converted', 'unsupported', 'not_found', 'forbidden' ];

	/**
	 * Per-format statuses stored by the feature.
	 *
	 * @var string[]
	 */
	const FORMAT_STATUSES = [ 'completed', 'skipped', 'failed' ];

	/**
	 * Shape of a reason code that is returned.
	 *
	 * @var string
	 */
	const CODE_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/i';

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/get-conversion-status';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get conversion status', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Return the WebP/AVIF conversion status of 1 to 50 attachments, from what the Convert feature recorded. status: completed (every requested format was made), partial (some formats made, some failed), skipped (nothing to make, for example WebP from a WebP image), failed, not_converted (never converted; a JPEG, PNG or WebP image), unsupported (a type that cannot be converted), not_found or forbidden. With the source size in bytes, stale (the image changed or is missing since it was converted, so the copies are out of date), converting (a conversion is running now), and per format (webp, avif) its status, size, percent saved, quality, when it was made (UTC), how many generated sizes were converted, and a reason code. Error messages and file paths are not returned. Read-only; never converts or changes anything.', 'media-library-tools' );
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
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'status', 'source_size', 'stale', 'converting', 'formats' ],
						'properties'           => [
							'id'          => [ 'type' => 'integer' ],
							'status'      => [
								'type' => 'string',
								'enum' => self::STATUSES,
							],
							'source_size' => [
								'type'        => 'integer',
								'description' => 'Bytes of the source image when it was last converted; 0 when unknown.',
							],
							'stale'       => [
								'type'        => 'boolean',
								'description' => 'The source changed or is missing since it was converted.',
							],
							'converting'  => [
								'type'        => 'boolean',
								'description' => 'A conversion of this image is running now.',
							],
							'formats'     => [
								'type'  => 'array',
								'items' => [
									'type'                 => 'object',
									'additionalProperties' => false,
									'required'             => [ 'format', 'status', 'size', 'percent_saved', 'quality', 'generated_at', 'generated_sizes_count', 'reason' ],
									'properties'           => [
										'format'        => [
											'type' => 'string',
											'enum' => ConversionCapabilities::FORMATS,
										],
										'status'        => [
											'type' => 'string',
											'enum' => self::FORMAT_STATUSES,
										],
										'size'          => [ 'type' => 'integer' ],
										'percent_saved' => [ 'type' => 'number' ],
										'quality'       => [ 'type' => 'integer' ],
										'generated_at'  => [
											'type'        => 'string',
											'description' => 'UTC (Y-m-d H:i:s), or empty.',
										],
										'generated_sizes_count' => [ 'type' => 'integer' ],
										'reason'        => [
											'type'    => 'string',
											'pattern' => '^([a-zA-Z0-9][a-zA-Z0-9_-]{0,63})?$',
										],
									],
								],
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

		_prime_post_caches( $ids, false, true );
		$access  = [];
		$allowed = [];
		foreach ( $ids as $id ) {
			$access[ $id ] = self::access( $id );
			if ( $access[ $id ] instanceof WP_Post ) {
				$allowed[] = $id;
			}
		}
		$display = ConversionMetadata::instance()->get_many_for_display( $allowed );

		$items   = [];
		$summary = array_fill_keys( self::STATUSES, 0 );
		foreach ( $ids as $id ) {
			$item = self::item( $id, $access[ $id ], $display[ $id ] ?? [] );
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
	 * The attachment when the user may read and edit it; otherwise not_found
	 * (missing, not media, trashed or unreadable) or forbidden (readable, not
	 * editable).
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return WP_Post|string
	 */
	private static function access( int $id ) {
		$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );
		if ( is_wp_error( $attachment ) || ! $attachment instanceof WP_Post || 'inherit' !== $attachment->post_status ) {
			return 'not_found';
		}
		return AbilityGuard::can_access_attachment( $attachment, 'edit_post' ) ? $attachment : 'forbidden';
	}

	/**
	 * Status of one attachment.
	 *
	 * @param int            $id      Attachment ID.
	 * @param WP_Post|string $access  Attachment, or why it is refused.
	 * @param array          $display ConversionMetadata::get_for_display() result.
	 *
	 * @return array
	 */
	private static function item( int $id, $access, array $display ): array {
		$item = [
			'id'          => $id,
			'status'      => is_string( $access ) ? $access : 'not_converted',
			'source_size' => 0,
			'stale'       => false,
			'converting'  => false,
			'formats'     => [],
		];
		if ( is_string( $access ) ) {
			return $item;
		}

		$item['converting'] = AttachmentConverter::instance()->is_locked( $id );
		if ( empty( $display['has_data'] ) ) {
			if ( ! ConversionCapabilities::instance()->is_supported_source( (string) $access->post_mime_type ) ) {
				$item['status'] = 'unsupported';
			}
			return $item;
		}

		$status = (string) ( $display['status'] ?? '' );
		// Anything else is not a recorded success; never report it as one.
		$item['status']      = in_array( $status, self::STORED_STATUSES, true ) ? $status : 'failed';
		$item['source_size'] = max( 0, (int) ( $display['source_size'] ?? 0 ) );
		$item['stale']       = ! empty( $display['is_stale'] );
		$item['formats']     = self::formats( $display['formats'] ?? [] );
		return $item;
	}

	/**
	 * Per-format results for the known formats, codes only.
	 *
	 * @param mixed $formats Display formats.
	 *
	 * @return array
	 */
	private static function formats( $formats ): array {
		$out = [];
		foreach ( is_array( $formats ) ? $formats : [] as $format ) {
			if ( ! is_array( $format ) || ! in_array( $format['format'] ?? '', ConversionCapabilities::FORMATS, true ) ) {
				continue;
			}
			$status = (string) ( $format['status'] ?? '' );
			$reason = $format['reason'] ?? '';
			$out[]  = [
				'format'                => (string) $format['format'],
				'status'                => in_array( $status, self::FORMAT_STATUSES, true ) ? $status : 'failed',
				'size'                  => max( 0, (int) ( $format['size'] ?? 0 ) ),
				'percent_saved'         => (float) ( $format['saved_percent'] ?? 0 ),
				'quality'               => max( 0, (int) ( $format['quality'] ?? 0 ) ),
				'generated_at'          => self::time_value( $format['generated_at'] ?? '' ),
				'generated_sizes_count' => max( 0, (int) ( $format['sizes_count'] ?? 0 ) ),
				'reason'                => is_scalar( $reason ) && preg_match( self::CODE_PATTERN, (string) $reason ) ? (string) $reason : '',
			];
		}
		return $out;
	}

	/**
	 * A stored "Y-m-d H:i:s" time, or '' when it is not one.
	 *
	 * @param mixed $value Stored value.
	 *
	 * @return string
	 */
	private static function time_value( $value ): string {
		return is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ? $value : '';
	}
}
