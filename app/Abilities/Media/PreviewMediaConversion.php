<?php
/**
 * Ability: tsmlt/preview-media-conversion.
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
use TinySolutions\mlt\Modules\Compress\CompressionAccess;
use TinySolutions\mlt\Modules\Compress\CompressionJob;
use TinySolutions\mlt\Modules\Compress\Conversion\McpConversion;
use TinySolutions\mlt\Modules\Compress\McpCompression;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Check a WebP/AVIF conversion of 1 to 20 images and issue the one-time
 * confirmation token tsmlt/convert-media needs. Changes no image, copy,
 * metadata or job: the only write is the token itself (and pruning expired
 * ones), so it is not annotated read-only.
 */
class PreviewMediaConversion extends Ability {

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
		return 'tsmlt/preview-media-conversion';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Preview image conversion', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Check converting 1 to 20 images to WebP and/or AVIF copies before starting it, and get the confirmation_token tsmlt/convert-media requires. The whole library cannot be converted through MCP. formats: webp (default) and/or avif. AVIF, quality (per format, 1-100) and generated_sizes: true need Media Library Tools Pro and are refused without it (never replaced by WebP or the preset quality); a format this server cannot produce is refused too. Per image: ready, already_converted (left out unless include_converted is true), same_format (already in every requested format), unsupported (JPEG, PNG and WebP only), file_missing, name_conflict (a file with the copy\'s name exists that is not this image\'s own copy; it is never replaced), locked (being converted, compressed, regenerated or having its EXIF removed), not_found (missing, deleted, trashed or not readable for you) or forbidden. Copies are extra files next to the image; the image itself is not changed. A token is issued only when at least one image is ready; it works once, for you, within 10 minutes. Ask the user to confirm before calling tsmlt/convert-media. Changes no image; it only stores the short-lived token.', 'media-library-tools' );
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
		$quality = [
			'type'    => 'integer',
			'minimum' => 1,
			'maximum' => 100,
		];
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'ids' ],
			'properties'           => [
				'ids'               => [
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => McpConversion::MAX_IDS,
					'uniqueItems' => true,
					'items'       => [
						'type'    => 'integer',
						'minimum' => 1,
					],
				],
				'formats'           => [
					'type'        => 'array',
					'description' => 'webp (default) and/or avif (Pro).',
					'minItems'    => 1,
					'maxItems'    => 2,
					'uniqueItems' => true,
					'items'       => [
						'type' => 'string',
						'enum' => [ 'webp', 'avif' ],
					],
				],
				'quality'           => [
					'type'                 => 'object',
					'description'          => 'Custom quality per requested format (Pro).',
					'additionalProperties' => false,
					'properties'           => [
						'webp' => $quality,
						'avif' => $quality,
					],
				],
				'generated_sizes'   => [
					'type'        => 'boolean',
					'description' => 'Also convert generated sizes (Pro).',
				],
				'include_converted' => [
					'type'        => 'boolean',
					'description' => 'Convert images that already have copies again.',
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
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'items', 'summary', 'settings', 'pro_active', 'pro_only_options', 'job_limit', 'job_running', 'ready_count', 'confirmation_token', 'expires_at', 'message' ],
			'properties'           => [
				'items'              => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'status' ],
						'properties'           => [
							'id'     => [ 'type' => 'integer' ],
							'status' => [
								'type' => 'string',
								'enum' => McpConversion::STATUSES,
							],
						],
					],
				],
				'summary'            => [
					'type'                 => 'object',
					'additionalProperties' => [ 'type' => 'integer' ],
				],
				'settings'           => self::settings_schema(),
				'pro_active'         => [ 'type' => 'boolean' ],
				'pro_only_options'   => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'job_limit'          => [
					'type'        => 'integer',
					'description' => 'Most images per job; 0 means no limit.',
				],
				'job_running'        => [ 'type' => 'boolean' ],
				'ready_count'        => [ 'type' => 'integer' ],
				'confirmation_token' => [ 'type' => [ 'string', 'null' ] ],
				'expires_at'         => [ 'type' => 'string' ],
				'message'            => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * Effective settings schema, shared with tsmlt/convert-media.
	 *
	 * @return array
	 */
	public static function settings_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'formats', 'quality', 'generated_sizes' ],
			'properties'           => [
				'formats'         => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'quality'         => [
					'type'                 => 'object',
					'additionalProperties' => [ 'type' => 'integer' ],
				],
				'generated_sizes' => [ 'type' => 'boolean' ],
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
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		];
	}

	/**
	 * Check the images and issue the token.
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
		$run = McpConversion::sanitize_preview( $input );
		if ( is_wp_error( $run ) ) {
			return $run;
		}
		$refusal = McpConversion::refusal( $run['request'] );
		if ( null !== $refusal ) {
			return $refusal;
		}
		$settings = McpConversion::run_settings( $run['request'] );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		_prime_post_caches( $run['ids'], false, true );
		$items   = [];
		$summary = array_fill_keys( McpConversion::STATUSES, 0 );
		$ready   = [];
		foreach ( $run['ids'] as $id ) {
			$status = McpConversion::item_status( $id, $settings['formats'], $run['include_converted'] );
			++$summary[ $status ];
			$items[] = [
				'id'     => $id,
				'status' => $status,
			];
			if ( 'ready' === $status ) {
				$ready[] = $id;
			}
		}

		$token = null;
		if ( $ready ) {
			$token = McpConversion::issue_token( $ready, $run['request'] + [ 'include_converted' => $run['include_converted'] ], $settings );
			if ( null === $token ) {
				return new WP_Error( McpConversion::ERROR_START_FAILED, __( 'The confirmation token could not be stored. Try again.', 'media-library-tools' ), [ 'status' => 500 ] );
			}
		}
		$running = 'running' === McpCompression::job_status( CompressionJob::instance()->get_state() );
		$access  = CompressionAccess::instance();

		return [
			'items'              => $items,
			'summary'            => $summary,
			'settings'           => $settings,
			'pro_active'         => $access->is_pro(),
			'pro_only_options'   => [ 'avif', 'quality', 'generated_sizes' ],
			'job_limit'          => $access->get_conversion_limit(),
			'job_running'        => $running,
			'ready_count'        => count( $ready ),
			'confirmation_token' => $token ? $token['token'] : null,
			'expires_at'         => $token ? gmdate( 'Y-m-d H:i:s', $token['expires'] ) : '',
			'message'            => $ready
				? __( 'Ask the user to confirm, then call tsmlt/convert-media with the confirmation_token and confirm: true. Conversion runs in the background; follow it with tsmlt/get-compression-job-status (job_type conversion) and see each image with tsmlt/get-conversion-status.', 'media-library-tools' ) . ( $running ? ' ' . __( 'A compression or conversion job is running now; wait for it to finish (the token stays valid for 10 minutes).', 'media-library-tools' ) : '' )
				: __( 'No image is ready to convert, so no token was issued.', 'media-library-tools' ),
		];
	}
}
