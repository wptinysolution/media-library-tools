<?php
/**
 * Ability: tsmlt/get-compression-status.
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
use TinySolutions\mlt\Modules\Compress\BackupManager;
use TinySolutions\mlt\Modules\Compress\CompressionManager;
use TinySolutions\mlt\Modules\Compress\CompressionMetadata;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;

/**
 * Compression status of up to 50 attachments, from the data the Compress
 * Images feature stores per attachment (CompressionMetadata, meta key
 * `_tsmlt_compression_data`).
 *
 * Status: the stored status (completed, skipped, failed); not_processed when
 * nothing is stored and the MIME type is one the feature compresses
 * (CompressionManager::SUPPORTED_MIME_TYPES); unsupported otherwise.
 *
 * Strictly read-only: reads post meta through CompressionMetadata::get()
 * and checks for a backup with BackupManager::has_backup_read_only(). It
 * never calls the job's progress or library status reads, nor
 * get_for_display(), whose backup check can create the backup directory.
 * Stored error texts (`last_error`) and file paths are never returned;
 * per-size reasons are returned only when they are plain codes.
 *
 * Registered with `meta.mcp.public` false: listed by name on the dedicated
 * server and the default server, not run through the default gateway.
 */
class GetCompressionStatus extends Ability {

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
	 * Compression statuses stored by the feature.
	 *
	 * @var string[]
	 */
	const STORED_STATUSES = [ 'completed', 'skipped', 'failed' ];

	/**
	 * Per-item statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'completed', 'skipped', 'failed', 'not_processed', 'unsupported', 'not_found', 'forbidden' ];

	/**
	 * Per-size statuses stored by the feature.
	 *
	 * @var string[]
	 */
	const SIZE_STATUSES = [ 'compressed', 'skipped', 'failed' ];

	/**
	 * Shape of a code (reason, mode, engine, size name) that is returned.
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
		return 'tsmlt/get-compression-status';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get compression status', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Return the image compression status of 1 to 50 attachments, from what the Compress Images feature recorded. status: completed (at least one size got smaller), skipped (processed, nothing got smaller), failed, not_processed (never compressed; a JPEG, PNG or WebP image), unsupported (a type the feature does not compress), not_found or forbidden. With the original and current size in bytes, bytes and percent saved, the mode, quality and engine used, when it was compressed (UTC), whether a backup of the original exists, and per size (full and generated sizes) the size before and after, status and reason code. Error messages are not returned. Read-only; never compresses or changes anything.', 'media-library-tools' );
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
		$code = [
			'type'    => 'string',
			'pattern' => '^([a-zA-Z0-9][a-zA-Z0-9_-]{0,63})?$',
		];
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
						'required'             => [ 'id', 'status', 'original_size', 'current_size', 'bytes_saved', 'percent_saved', 'mode', 'quality', 'engine', 'compressed_at', 'backup_exists', 'generated_sizes' ],
						'properties'           => [
							'id'              => [ 'type' => 'integer' ],
							'status'          => [
								'type' => 'string',
								'enum' => self::STATUSES,
							],
							'original_size'   => [
								'type'        => 'integer',
								'description' => 'Bytes before the first compression (all compressed sizes together); 0 when unknown.',
							],
							'current_size'    => [
								'type'        => 'integer',
								'description' => 'Bytes after the last compression; 0 when unknown.',
							],
							'bytes_saved'     => [ 'type' => 'integer' ],
							'percent_saved'   => [ 'type' => 'number' ],
							'mode'            => $code,
							'quality'         => [ 'type' => 'integer' ],
							'engine'          => $code,
							'compressed_at'   => [
								'type'        => 'string',
								'description' => 'When it was last processed, UTC (Y-m-d H:i:s), or empty.',
							],
							'backup_exists'   => [ 'type' => 'boolean' ],
							'generated_sizes' => [
								'type'  => 'array',
								'items' => [
									'type'                 => 'object',
									'additionalProperties' => false,
									'required'             => [ 'name', 'before', 'after', 'status', 'reason' ],
									'properties'           => [
										'name'   => $code,
										'before' => [ 'type' => 'integer' ],
										'after'  => [ 'type' => 'integer' ],
										'status' => [
											'type' => 'string',
											'enum' => array_merge( self::SIZE_STATUSES, [ '' ] ),
										],
										'reason' => $code,
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
		$items   = [];
		$summary = array_fill_keys( self::STATUSES, 0 );
		foreach ( $ids as $id ) {
			$item = self::item( $id );
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
	 * Status of one attachment.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array
	 */
	private static function item( int $id ): array {
		$item = [
			'id'              => $id,
			'status'          => 'not_found',
			'original_size'   => 0,
			'current_size'    => 0,
			'bytes_saved'     => 0,
			'percent_saved'   => 0,
			'mode'            => '',
			'quality'         => 0,
			'engine'          => '',
			'compressed_at'   => '',
			'backup_exists'   => false,
			'generated_sizes' => [],
		];

		$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );
		if ( is_wp_error( $attachment ) || ! $attachment instanceof WP_Post || 'inherit' !== $attachment->post_status ) {
			return $item;
		}
		if ( ! AbilityGuard::can_access_attachment( $attachment, 'edit_post' ) ) {
			$item['status'] = 'forbidden';
			return $item;
		}

		$data = CompressionMetadata::instance()->get( $id );
		if ( [] === $data ) {
			$item['status'] = in_array( (string) $attachment->post_mime_type, CompressionManager::SUPPORTED_MIME_TYPES, true ) ? 'not_processed' : 'unsupported';
			return $item;
		}

		$status = (string) ( $data['status'] ?? '' );
		// Anything else is not a recorded success; never report it as one.
		$item['status']          = in_array( $status, self::STORED_STATUSES, true ) ? $status : 'failed';
		$item['original_size']   = max( 0, (int) ( $data['original']['size'] ?? 0 ) );
		$item['current_size']    = max( 0, (int) ( $data['compressed']['size'] ?? 0 ) );
		$item['bytes_saved']     = max( 0, (int) ( $data['compressed']['saved_bytes'] ?? 0 ) );
		$item['percent_saved']   = (float) ( $data['compressed']['saved_percent'] ?? 0 );
		$item['mode']            = self::code( $data['settings']['mode'] ?? '' );
		$item['quality']         = max( 0, (int) ( $data['settings']['quality'] ?? 0 ) );
		$item['engine']          = self::code( $data['settings']['engine'] ?? '' );
		$item['compressed_at']   = self::time_value( $data['compressed_at'] ?? '' );
		$item['backup_exists']   = ! empty( $data['backup']['available'] ) && BackupManager::instance()->has_backup_read_only( $id );
		$item['generated_sizes'] = self::sizes( $data['sizes'] ?? [] );
		return $item;
	}

	/**
	 * Per-size results: name, bytes before and after, status and reason as
	 * codes only.
	 *
	 * @param mixed $sizes Stored sizes.
	 *
	 * @return array
	 */
	private static function sizes( $sizes ): array {
		$out = [];
		foreach ( is_array( $sizes ) ? $sizes : [] as $name => $size ) {
			if ( ! is_array( $size ) ) {
				continue;
			}
			$status = (string) ( $size['status'] ?? '' );
			$out[]  = [
				'name'   => self::code( $name ),
				'before' => max( 0, (int) ( $size['before'] ?? 0 ) ),
				'after'  => max( 0, (int) ( $size['after'] ?? 0 ) ),
				'status' => in_array( $status, self::SIZE_STATUSES, true ) ? $status : '',
				'reason' => self::code( $size['reason'] ?? '' ),
			];
		}
		return $out;
	}

	/**
	 * A stored value when it is a plain code, otherwise ''.
	 *
	 * @param mixed $value Stored value.
	 *
	 * @return string
	 */
	private static function code( $value ): string {
		return is_scalar( $value ) && preg_match( self::CODE_PATTERN, (string) $value ) ? (string) $value : '';
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
