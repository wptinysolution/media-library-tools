<?php
/**
 * Ability: tsmlt/preview-thumbnail-regeneration.
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
use TinySolutions\mlt\Modules\Regenerate\McpRegenerate;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Check which of 1 to 20 images can have their thumbnails regenerated, with
 * what effect, and issue the one-time confirmation token
 * tsmlt/regenerate-thumbnails needs. Changes no image, metadata or job: the
 * only write is the token itself (and pruning expired ones), so it is not
 * annotated read-only.
 */
class PreviewThumbnailRegeneration extends Ability {

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
		return 'tsmlt/preview-thumbnail-regeneration';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Preview thumbnail regeneration', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Check whether the thumbnails (image sizes) of 1 to 20 images can be regenerated, and get the confirmation_token tsmlt/regenerate-thumbnails requires. The whole library cannot be regenerated through MCP. Per image: ready, not_found (missing, deleted, trashed or not readable for you), forbidden (you may not edit it), not_image, file_missing (the file is missing or unreadable), remote_storage (stored off this server), original_missing (a scaled or rotated upload whose full-size original is missing or unreadable) or locked (being compressed, converted, regenerated or having its EXIF removed). Also per image: upload_copy (scaled or rotated when WordPress keeps the full-size original; sizes are then made from that original, which is kept), and whether it was compressed or converted (those sizes are replaced, and WebP/AVIF copies of sizes may become outdated). Returns the sizes that would be made and the sizes disabled in Media Library Tools (not made; their files are kept). Regeneration is not reversible: the existing size files are replaced. A token is issued only when at least one image is ready; it works once, for you, within 10 minutes. Show the user the result and ask them to confirm before calling tsmlt/regenerate-thumbnails. Changes no image; it only stores the short-lived token.', 'media-library-tools' );
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
			'required'             => [ 'ids' ],
			'properties'           => [
				'ids' => [
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => McpRegenerate::MAX_IDS,
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
	 * Output schema.
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {
		$names = [
			'type'  => 'array',
			'items' => [ 'type' => 'string' ],
		];
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'items', 'summary', 'sizes', 'disabled_sizes', 'reversible', 'job_running', 'ready_count', 'confirmation_token', 'expires_at', 'message' ],
			'properties'           => [
				'items'              => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'status', 'upload_copy', 'original', 'compressed', 'converted' ],
						'properties'           => [
							'id'          => [ 'type' => 'integer' ],
							'status'      => [
								'type' => 'string',
								'enum' => McpRegenerate::STATUSES,
							],
							'upload_copy' => [
								'type' => 'string',
								'enum' => [ '', 'scaled', 'rotated', 'other' ],
							],
							'original'    => [
								'type' => 'string',
								'enum' => [ 'none', 'available', 'missing' ],
							],
							'compressed'  => [ 'type' => 'boolean' ],
							'converted'   => [ 'type' => 'boolean' ],
						],
					],
				],
				'summary'            => [
					'type'                 => 'object',
					'additionalProperties' => [ 'type' => 'integer' ],
				],
				'sizes'              => $names,
				'disabled_sizes'     => $names,
				'reversible'         => [
					'type'        => 'boolean',
					'description' => 'Always false: replaced size files are not kept.',
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
		$ids = McpRegenerate::sanitize_ids( $input );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}

		_prime_post_caches( $ids, false, true );
		$items   = [];
		$summary = array_fill_keys( McpRegenerate::STATUSES, 0 );
		$ready   = [];
		foreach ( $ids as $id ) {
			$item = McpRegenerate::item( $id );
			++$summary[ $item['status'] ];
			$items[] = $item;
			if ( 'ready' === $item['status'] ) {
				$ready[] = $id;
			}
		}
		$sizes = McpRegenerate::sizes();
		$token = null;
		if ( $ready ) {
			$token = McpRegenerate::issue_token( $ready, $sizes['fingerprint'] );
			if ( null === $token ) {
				return new WP_Error( McpRegenerate::ERROR_START_FAILED, __( 'The confirmation token could not be stored. Try again.', 'media-library-tools' ), [ 'status' => 500 ] );
			}
		}
		$running = RegenerateThumbnails::instance()->has_active_job();

		return [
			'items'              => $items,
			'summary'            => $summary,
			'sizes'              => $sizes['sizes'],
			'disabled_sizes'     => $sizes['disabled_sizes'],
			'reversible'         => false,
			'job_running'        => $running,
			'ready_count'        => count( $ready ),
			'confirmation_token' => $token ? $token['token'] : null,
			'expires_at'         => $token ? gmdate( 'Y-m-d H:i:s', $token['expires'] ) : '',
			'message'            => self::message( count( $ready ), $running ),
		];
	}

	/**
	 * Guidance for the client.
	 *
	 * @param int  $ready   Ready images.
	 * @param bool $running Whether a regeneration job is running.
	 *
	 * @return string
	 */
	private static function message( int $ready, bool $running ): string {
		if ( $ready < 1 ) {
			return __( 'No image is ready to regenerate, so no token was issued.', 'media-library-tools' );
		}
		$message = __( 'Regeneration is not reversible: the existing size files of these images are replaced. Ask the user to confirm, then call tsmlt/regenerate-thumbnails with the confirmation_token and confirm: true. It runs in the background; follow it with tsmlt/get-thumbnail-regeneration-status.', 'media-library-tools' );
		if ( $running ) {
			$message .= ' ' . __( 'A regeneration job is running now; wait for it to finish (the token stays valid for 10 minutes).', 'media-library-tools' );
		}
		return $message;
	}
}
