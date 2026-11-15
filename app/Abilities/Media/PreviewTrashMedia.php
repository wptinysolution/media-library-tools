<?php
/**
 * Ability: tsmlt/preview-trash-media.
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
use TinySolutions\mlt\Abilities\MediaPresenter;
use TinySolutions\mlt\Modules\Trash\MediaTrash;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Check whether attachments can be moved to the trash and, when every one
 * can, issue the confirmation token tsmlt/trash-media needs. Changes no
 * attachment: the only write is the token itself (and pruning expired
 * ones).
 */
class PreviewTrashMedia extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Refusal reasons ('' when eligible).
	 *
	 * @var string[]
	 */
	const REASONS = [ '', MediaTrash::REASON_TRASH_DISABLED, MediaTrash::REASON_NOT_AVAILABLE, MediaTrash::REASON_ALREADY_TRASHED, MediaTrash::REASON_INVALID_STATUS, MediaTrash::REASON_PROTECTED, MediaTrash::REASON_USED, MediaTrash::REASON_USAGE_UNKNOWN ];

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
		return 'tsmlt/preview-trash-media';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Preview moving media to the trash', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Check whether media can be moved to the WordPress trash, and get the confirmation_token tsmlt/trash-media requires. Up to 20 IDs. An item is eligible only when: trash is enabled (EMPTY_TRASH_DAYS above 0), you may delete it, it is in the media library (not already trashed), it is not the site icon or logo, and the latest Used Where scan is complete, verified and found it unused (usage "unused"; used or unknown media is refused: run tsmlt/start-usage-scan and wait for it to finish if usage is unknown). A token is issued only when every ID is eligible; preview again with only the eligible IDs otherwise. The token works once, for the same user and exactly the same IDs, within 10 minutes, and only while the same usage scan is current. Show the user the items and ask them to confirm before trashing. Trashed media stops showing on the site and WordPress deletes it permanently after EMPTY_TRASH_DAYS days unless restored (tsmlt/restore-media). Changes no media.', 'media-library-tools' ) . ' ' . MediaTrash::permanent_delete_guidance();
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
		$media         = MediaPresenter::summary_schema();
		$media['type'] = [ 'object', 'null' ];

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'items', 'eligible_count', 'confirmation_token', 'expires_at', 'message', 'trash' ],
			'properties'           => [
				'items'              => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'eligible', 'reason', 'usage', 'usage_count', 'scan_verified', 'frontend_impact', 'media' ],
						'properties'           => [
							'id'              => [ 'type' => 'integer' ],
							'eligible'        => [ 'type' => 'boolean' ],
							'reason'          => [
								'type'        => 'string',
								'enum'        => self::REASONS,
								'description' => 'Why it cannot be trashed, or empty when it can. not_available: missing, not media, or you may not delete it.',
							],
							'usage'           => [
								'type'        => 'string',
								'enum'        => [ 'used', 'unused', 'unknown' ],
								'description' => 'From the Used Where scan, as tsmlt/get-media-usage reports it.',
							],
							'usage_count'     => [
								'type'        => 'integer',
								'description' => 'Usages the scan recorded.',
							],
							'scan_verified'   => [
								'type'        => 'boolean',
								'description' => 'Whether a complete, verified usage scan covered it.',
							],
							'frontend_impact' => [
								'type'        => 'string',
								'enum'        => [ 'none_recorded', 'breaks_recorded_usage', 'unknown' ],
								'description' => 'If trashed: none_recorded (no usage recorded by a verified scan), breaks_recorded_usage (recorded usages would stop showing it on the site), or unknown.',
							],
							'media'           => $media,
						],
					],
				],
				'eligible_count'     => [ 'type' => 'integer' ],
				'confirmation_token' => [
					'type'        => [ 'string', 'null' ],
					'description' => 'Pass to tsmlt/trash-media with exactly these IDs; null unless every ID is eligible.',
				],
				'expires_at'         => [
					'type'        => [ 'string', 'null' ],
					'description' => 'When the token expires, ISO 8601 (UTC).',
				],
				'message'            => [ 'type' => 'string' ],
				'trash'              => MediaTrash::settings_schema(),
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
			'idempotent'  => false,
		];
	}

	/**
	 * Preview the attachments.
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
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Only ids is accepted.', 'media-library-tools' ), 400 );
		}
		$ids = MediaTrash::validate_ids( $input['ids'] ?? null );
		if ( null === $ids ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'ids must be a list of 1 to 20 distinct attachment IDs (positive integers).', 'media-library-tools' ), 400 );
		}

		$posts = MediaTrash::deletable( $ids );
		$usage = MediaTrash::usage( array_values( $posts ) );
		if ( null === $usage ) {
			return AbilityGuard::error( MediaTrash::ERROR_READ_FAILED, __( 'The usage records could not be read. Try again.', 'media-library-tools' ), 500 );
		}
		$protected = MediaTrash::protected_ids();

		$items    = [];
		$eligible = 0;
		foreach ( $ids as $id ) {
			$post   = $posts[ $id ] ?? null;
			$state  = null === $post ? null : $usage['states'][ $id ];
			$reason = MediaTrash::trash_refusal( $post, $state, $protected );
			if ( '' === $reason ) {
				++$eligible;
			}
			$items[] = [
				'id'              => $id,
				'eligible'        => '' === $reason,
				'reason'          => $reason,
				'usage'           => true === $state ? 'used' : ( false === $state ? 'unused' : 'unknown' ),
				'usage_count'     => null === $post ? 0 : $usage['counts'][ $id ],
				'scan_verified'   => null !== $post && $usage['covered'][ $id ],
				'frontend_impact' => true === $state ? 'breaks_recorded_usage' : ( false === $state ? 'none_recorded' : 'unknown' ),
				'media'           => null === $post ? null : MediaPresenter::summary( $post ),
			];
		}

		$token = null;
		if ( count( $ids ) === $eligible ) {
			$token = MediaTrash::issue_token( $ids, $usage['status']['scan_id'] );
			if ( null === $token ) {
				return AbilityGuard::error( MediaTrash::ERROR_READ_FAILED, __( 'The confirmation token could not be stored. Try again.', 'media-library-tools' ), 500 );
			}
		}

		return [
			'items'              => $items,
			'eligible_count'     => $eligible,
			'confirmation_token' => $token ? $token['token'] : null,
			'expires_at'         => $token ? gmdate( DATE_ATOM, $token['expires'] ) : null,
			'message'            => $token
				? __( 'Every item is eligible. Ask the user to confirm, then call tsmlt/trash-media with exactly these ids and the confirmation_token.', 'media-library-tools' )
				: __( 'No token: not every item is eligible. Nothing was changed. Preview again with only the eligible IDs to trash them.', 'media-library-tools' ),
			'trash'              => MediaTrash::settings(),
		];
	}
}
