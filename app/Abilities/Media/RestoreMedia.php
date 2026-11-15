<?php
/**
 * Ability: tsmlt/restore-media.
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
use TinySolutions\mlt\Modules\Trash\MediaTrash;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Restore attachments from the WordPress trash with wp_untrash_post(), one
 * by one. Each needs `delete_post` (as on WordPress's own screens). Each is
 * read back and reported as it ends up; nothing is rolled back.
 */
class RestoreMedia extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Result names.
	 *
	 * @var string[]
	 */
	const RESULTS = [ 'restored', 'not_trashed', 'refused', 'blocked', 'failed' ];

	/**
	 * Reason codes.
	 *
	 * @var string[]
	 */
	const REASONS = [ '', MediaTrash::REASON_NOT_TRASHED, MediaTrash::REASON_NOT_AVAILABLE, MediaTrash::REASON_BLOCKED, MediaTrash::REASON_UNEXPECTED_STATUS, MediaTrash::REASON_DELETED ];

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
		return 'tsmlt/restore-media';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Restore trashed media', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Restore media from the WordPress trash (find IDs with tsmlt/list-trashed-media). Up to 20 IDs; each is handled on its own and reported: restored, not_trashed (it was not in the trash; nothing changed), refused (not_available: missing, not media, or you may not restore it), blocked (another plugin stopped it) or failed. Restored media is back in the library and shown on the site again. Nothing is rolled back if some fail.', 'media-library-tools' ) . ' ' . MediaTrash::permanent_delete_guidance();
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
			'default'              => [],
			'required'             => [ 'ids' ],
			'properties'           => [
				'ids' => MediaTrash::ids_schema(),
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
			'required'             => [ 'items', 'summary' ],
			'properties'           => [
				'items'   => [
					'type'  => 'array',
					'items' => MediaTrash::result_schema( self::RESULTS, self::REASONS ),
				],
				'summary' => MediaTrash::summary_schema( self::RESULTS ),
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
			'idempotent'  => true,
		];
	}

	/**
	 * Restore the attachments.
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

		if ( ! is_array( $input ) || array_diff( array_keys( $input ), [ 'ids' ] ) ) {
			return $this->invalid( __( 'Only ids is accepted.', 'media-library-tools' ) );
		}
		$ids = MediaTrash::validate_ids( $input['ids'] ?? null );
		if ( null === $ids ) {
			return $this->invalid( __( 'ids must be a list of 1 to 20 distinct attachment IDs (positive integers).', 'media-library-tools' ) );
		}

		$items = [];
		foreach ( $ids as $id ) {
			$post = MediaTrash::deletable( [ $id ] )[ $id ] ?? null;
			if ( null === $post ) {
				$outcome = [
					'result' => 'refused',
					'reason' => MediaTrash::REASON_NOT_AVAILABLE,
				];
			} elseif ( 'trash' !== $post->post_status ) {
				$outcome = [
					'result' => 'not_trashed',
					'reason' => MediaTrash::REASON_NOT_TRASHED,
				];
			} else {
				$outcome = MediaTrash::restore( $id );
			}
			$items[] = [ 'id' => $id ] + $outcome;
		}

		return [
			'items'   => $items,
			'summary' => MediaTrash::summary( $items, self::RESULTS ),
		];
	}

	/**
	 * Error for invalid input.
	 *
	 * @param string $message Message.
	 *
	 * @return WP_Error
	 */
	private function invalid( string $message ): WP_Error {
		return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, $message, 400 );
	}
}
