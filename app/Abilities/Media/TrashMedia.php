<?php
/**
 * Ability: tsmlt/trash-media.
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
 * Move previewed attachments to the WordPress trash with wp_trash_post(),
 * one by one.
 *
 * Before anything changes: trash must be enabled, the confirmation token
 * from tsmlt/preview-trash-media must match the user and the exact IDs and
 * is used up, and the Used Where scan must be the one the preview used.
 * Then every eligibility rule is checked again per attachment, right
 * before its own trash call, and each attachment is read back and reported
 * as it ends up. Nothing is rolled back.
 */
class TrashMedia extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Result names.
	 *
	 * @var string[]
	 */
	const RESULTS = [ 'trashed', 'already_trashed', 'refused', 'blocked', 'failed' ];

	/**
	 * Reason codes ('' when trashed).
	 *
	 * @var string[]
	 */
	const REASONS = [ '', MediaTrash::REASON_TRASH_DISABLED, MediaTrash::REASON_NOT_AVAILABLE, MediaTrash::REASON_ALREADY_TRASHED, MediaTrash::REASON_INVALID_STATUS, MediaTrash::REASON_PROTECTED, MediaTrash::REASON_USED, MediaTrash::REASON_USAGE_UNKNOWN, MediaTrash::REASON_SCAN_CHANGED, MediaTrash::REASON_BLOCKED, MediaTrash::REASON_UNEXPECTED_STATUS, MediaTrash::REASON_DELETED ];

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
		return 'tsmlt/trash-media';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Move media to the trash', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Move media to the WordPress trash. Requires the confirmation_token from tsmlt/preview-trash-media for exactly the same IDs (any order), by the same user, within 10 minutes; the token works once. Only ask for this after the user confirmed the preview. Every rule of the preview is checked again; any item that no longer qualifies is refused, and if the Used Where scan changed since the preview nothing is trashed. Each item is reported: trashed, already_trashed, refused (with a reason), blocked (another plugin stopped it) or failed; nothing is rolled back if some fail. Trashed media stops showing on the site at once, can be restored with tsmlt/restore-media, and is deleted permanently by WordPress after EMPTY_TRASH_DAYS days. This tool never deletes media permanently.', 'media-library-tools' ) . ' ' . MediaTrash::permanent_delete_guidance();
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
			'required'             => [ 'ids', 'confirmation_token' ],
			'properties'           => [
				'ids'                => MediaTrash::ids_schema(),
				'confirmation_token' => [
					'type'        => 'string',
					'pattern'     => '^[a-f0-9]{64}$',
					'description' => 'Token from tsmlt/preview-trash-media for exactly these IDs.',
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
			'required'             => [ 'items', 'summary', 'trash' ],
			'properties'           => [
				'items'   => [
					'type'  => 'array',
					'items' => MediaTrash::result_schema( self::RESULTS, self::REASONS ),
				],
				'summary' => MediaTrash::summary_schema( self::RESULTS ),
				'trash'   => MediaTrash::settings_schema(),
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
			'destructive' => true,
			'idempotent'  => false,
		];
	}

	/**
	 * Trash the attachments.
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

		if ( ! is_array( $input ) || array_diff( array_keys( $input ), [ 'ids', 'confirmation_token' ] ) ) {
			return $this->invalid( __( 'Only ids and confirmation_token are accepted.', 'media-library-tools' ) );
		}
		$ids = MediaTrash::validate_ids( $input['ids'] ?? null );
		if ( null === $ids ) {
			return $this->invalid( __( 'ids must be a list of 1 to 20 distinct attachment IDs (positive integers).', 'media-library-tools' ) );
		}

		// When trash is off, wp_trash_post() deletes permanently: stop before
		// the token or anything else is touched.
		if ( ! MediaTrash::trash_enabled() ) {
			return AbilityGuard::error( MediaTrash::ERROR_TRASH_DISABLED, __( 'Trash is disabled on this site (EMPTY_TRASH_DAYS is 0 or less), so media cannot be moved to the trash. Nothing was changed.', 'media-library-tools' ), 409 );
		}

		$payload = MediaTrash::consume_token( $input['confirmation_token'] ?? null, $ids );
		if ( ! is_array( $payload ) ) {
			return AbilityGuard::error( MediaTrash::ERROR_TOKEN_INVALID, $payload . ' ' . __( 'Nothing was changed.', 'media-library-tools' ), 403 );
		}
		$scan_id = (string) ( $payload['scan_id'] ?? '' );
		if ( '' === $scan_id || MediaTrash::scan_status()['scan_id'] !== $scan_id ) {
			return $this->scan_changed();
		}

		$posts = MediaTrash::deletable( $ids );
		$usage = MediaTrash::usage( array_values( $posts ) );
		if ( null === $usage ) {
			return AbilityGuard::error( MediaTrash::ERROR_READ_FAILED, __( 'The usage records could not be read. Nothing was changed; preview again.', 'media-library-tools' ), 500 );
		}
		if ( $usage['status']['scan_id'] !== $scan_id ) {
			return $this->scan_changed();
		}

		$items = [];
		foreach ( $ids as $id ) {
			$items[] = [ 'id' => $id ] + $this->trash_one( $id, $usage['states'], $scan_id );
		}

		return [
			'items'   => $items,
			'summary' => MediaTrash::summary( $items, self::RESULTS ),
			'trash'   => MediaTrash::settings(),
		];
	}

	/**
	 * Check one attachment again, right before its trash call, and trash it.
	 *
	 * @param int                   $id      Attachment ID.
	 * @param array<int, bool|null> $states  Usage states read under the scan.
	 * @param string                $scan_id Scan the preview used.
	 *
	 * @return array{result: string, reason: string}
	 */
	private function trash_one( int $id, array $states, string $scan_id ): array {
		$status = MediaTrash::scan_status();
		if ( $status['scan_id'] !== $scan_id || ! $status['complete'] || ! $status['coverage']['verified'] ) {
			return [
				'result' => 'refused',
				'reason' => MediaTrash::REASON_SCAN_CHANGED,
			];
		}
		$post   = MediaTrash::deletable( [ $id ] )[ $id ] ?? null;
		$reason = MediaTrash::trash_refusal( $post, null === $post ? null : ( $states[ $id ] ?? null ), MediaTrash::protected_ids() );
		if ( MediaTrash::REASON_ALREADY_TRASHED === $reason ) {
			return [
				'result' => 'already_trashed',
				'reason' => $reason,
			];
		}
		if ( '' !== $reason ) {
			return [
				'result' => 'refused',
				'reason' => $reason,
			];
		}
		return MediaTrash::trash( $id );
	}

	/**
	 * Error: the Used Where scan changed since the preview.
	 *
	 * @return WP_Error
	 */
	private function scan_changed(): WP_Error {
		return AbilityGuard::error( MediaTrash::ERROR_SCAN_CHANGED, __( 'The Used Where scan changed since the preview, so its results may differ. Nothing was changed; run tsmlt/preview-trash-media again.', 'media-library-tools' ), 409 );
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
