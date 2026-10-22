<?php
/**
 * Ability: tsmlt/set-media-groups.
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
use TinySolutions\mlt\Controllers\Admin\Api;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;
use WP_Term;

/**
 * Add, remove or replace the media groups (tsmlt_category terms) of one
 * attachment.
 *
 * Every group ID must already exist in the media groups taxonomy; groups are
 * never created. Everything is validated before anything is written, the
 * write goes through Api::assign_groups() (the media table's bulk edit uses
 * the same method), and the stored relationships are read back and compared
 * with the expected result before success is reported. Only this taxonomy's
 * relationships change: no attachment fields, metadata or files.
 *
 * Registered with `meta.mcp.public` false, so it is offered only on the
 * dedicated Media Library Tools server.
 */
class SetMediaGroups extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Error code: the relationships could not be saved as requested.
	 *
	 * @var string
	 */
	const ERROR_UPDATE_FAILED = 'tsmlt_update_failed';

	/**
	 * Assignment modes.
	 *
	 * @var string[]
	 */
	const MODES = [ 'add', 'remove', 'replace' ];

	/**
	 * Most group IDs accepted in one request.
	 *
	 * @var int
	 */
	const MAX_GROUPS = 100;

	/**
	 * Attachment statuses this ability changes, matching the other abilities.
	 *
	 * @var string[]
	 */
	const VISIBLE_STATUSES = [ 'inherit' ];

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/set-media-groups';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Set media groups', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Change which media groups one attachment belongs to. mode "add" keeps its current groups and adds group_ids; "remove" removes only group_ids; "replace" makes group_ids its only groups (an empty list removes all of them). With "add" or "remove" an empty group_ids changes nothing. Every ID must be an existing group (find IDs with tsmlt/list-media-groups); groups are never created, and nothing changes if any ID is invalid. Returns the groups before and after, and whether anything changed. Does not change the file or any other attachment data.', 'media-library-tools' );
	}

	/**
	 * Not offered on the MCP Adapter's default gateway.
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
			'required'             => [ 'id', 'group_ids', 'mode' ],
			'properties'           => [
				'id'        => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Attachment ID.',
				],
				'group_ids' => [
					'type'        => 'array',
					'maxItems'    => self::MAX_GROUPS,
					'uniqueItems' => true,
					'items'       => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'description' => 'Existing group IDs.',
				],
				'mode'      => [
					'type'        => 'string',
					'enum'        => self::MODES,
					'description' => 'add: keep current groups and add these; remove: remove only these; replace: make these the only groups.',
				],
			],
		];
	}

	/**
	 * @return array
	 */
	protected function get_output_schema(): array {
		$groups = [
			'type'  => 'array',
			'items' => MediaPresenter::group_schema(),
		];

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'id', 'mode', 'groups_before', 'groups_after', 'changed' ],
			'properties'           => [
				'id'            => [
					'type'        => 'integer',
					'description' => 'Attachment ID.',
				],
				'mode'          => [
					'type' => 'string',
					'enum' => self::MODES,
				],
				'groups_before' => array_merge( $groups, [ 'description' => 'Groups before the change, by name.' ] ),
				'groups_after'  => array_merge( $groups, [ 'description' => 'Groups after the change, as stored, by name.' ] ),
				'changed'       => [
					'type'        => 'boolean',
					'description' => 'Whether the attachment\'s groups changed.',
				],
			],
		];
	}

	/**
	 * @return array<string, bool>
	 */
	protected function get_annotations(): array {
		return [
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => true,
		];
	}

	/**
	 * Apply the change.
	 *
	 * @param mixed $input Ability input, already validated against the schema.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		$input = is_array( $input ) ? $input : [];

		$attachment = $this->resolve_editable_attachment( $input['id'] ?? null );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}

		$mode = is_string( $input['mode'] ?? null ) ? $input['mode'] : '';
		if ( ! in_array( $mode, self::MODES, true ) ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				__( 'mode must be "add", "remove" or "replace".', 'media-library-tools' ),
				400
			);
		}

		if ( ! array_key_exists( 'group_ids', $input ) || ( is_array( $input['group_ids'] ) && count( $input['group_ids'] ) > self::MAX_GROUPS ) ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				/* translators: %d: maximum number of group IDs. */
				sprintf( __( 'group_ids must be a list of at most %d group IDs.', 'media-library-tools' ), self::MAX_GROUPS ),
				400
			);
		}
		$terms = AbilityGuard::resolve_groups( $input['group_ids'] );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$taxonomy = get_taxonomy( Fns::CATEGORY );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_FORBIDDEN,
				__( 'You are not allowed to assign media groups.', 'media-library-tools' ),
				403
			);
		}

		$id        = (int) $attachment->ID;
		$requested = array_map(
			static function ( WP_Term $term ): int {
				return (int) $term->term_id;
			},
			$terms
		);
		$before    = $this->current_group_ids( $id );
		$expected  = $this->expected_group_ids( $before, $requested, $mode );

		if ( $expected !== $before ) {
			$saved = Api::instance()->assign_groups( $id, $requested, $mode );
			$after = $this->current_group_ids( $id );
			if ( is_wp_error( $saved ) || $after !== $expected ) {
				// Put back what was there, so a failure leaves no partial change.
				Api::instance()->assign_groups( $id, $before, 'replace' );
				return AbilityGuard::error(
					self::ERROR_UPDATE_FAILED,
					__( 'The media groups could not be saved; the attachment keeps its previous groups.', 'media-library-tools' ),
					500
				);
			}
		} else {
			$after = $before;
		}

		return [
			'id'            => $id,
			'mode'          => $mode,
			'groups_before' => $this->present( $before ),
			'groups_after'  => $this->present( $after ),
			'changed'       => $after !== $before,
		];
	}

	/**
	 * The groups the attachment should have after the change.
	 *
	 * @param int[]  $before    Current group IDs, sorted.
	 * @param int[]  $requested Requested group IDs.
	 * @param string $mode      Mode.
	 *
	 * @return int[] Sorted group IDs.
	 */
	private function expected_group_ids( array $before, array $requested, string $mode ): array {
		if ( 'add' === $mode ) {
			$expected = array_merge( $before, $requested );
		} elseif ( 'remove' === $mode ) {
			$expected = array_diff( $before, $requested );
		} else {
			$expected = $requested;
		}
		$expected = array_values( array_unique( array_map( 'intval', $expected ) ) );
		sort( $expected );
		return $expected;
	}

	/**
	 * The attachment's current group IDs, read from the database, sorted.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return int[]
	 */
	private function current_group_ids( int $attachment_id ): array {
		clean_object_term_cache( $attachment_id, 'attachment' );
		$ids = wp_get_object_terms( $attachment_id, Fns::CATEGORY, [ 'fields' => 'ids' ] );
		$ids = is_wp_error( $ids ) ? [] : array_map( 'intval', $ids );
		sort( $ids );
		return $ids;
	}

	/**
	 * Groups for the output, by name then ID.
	 *
	 * @param int[] $group_ids Group IDs.
	 *
	 * @return array
	 */
	private function present( array $group_ids ): array {
		$groups = [];
		foreach ( $group_ids as $group_id ) {
			$term = get_term( $group_id, Fns::CATEGORY );
			if ( $term instanceof WP_Term ) {
				$groups[] = MediaPresenter::group( $term );
			}
		}
		usort(
			$groups,
			static function ( array $a, array $b ): int {
				$by_name = strnatcasecmp( $a['name'], $b['name'] );
				return 0 !== $by_name ? $by_name : $a['id'] <=> $b['id'];
			}
		);
		return $groups;
	}

	/**
	 * Resolve the attachment and check that the user may edit it.
	 *
	 * Attachments the user may not read, and those outside the visible
	 * statuses, are reported as not found so their existence is not revealed;
	 * a readable attachment the user may not edit is reported as forbidden —
	 * as tsmlt/update-media-metadata does.
	 *
	 * @param mixed $id Raw attachment ID.
	 *
	 * @return WP_Post|WP_Error
	 */
	private function resolve_editable_attachment( $id ) {
		$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );

		if ( is_wp_error( $attachment ) ) {
			return AbilityGuard::ERROR_FORBIDDEN === $attachment->get_error_code()
				? $this->not_found()
				: $attachment;
		}

		if ( ! in_array( $attachment->post_status, self::VISIBLE_STATUSES, true ) ) {
			return $this->not_found();
		}

		if ( ! AbilityGuard::can_access_attachment( $attachment, 'edit_post' ) ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_FORBIDDEN,
				__( 'You are not allowed to edit this attachment.', 'media-library-tools' ),
				403
			);
		}

		return $attachment;
	}

	/**
	 * The same error the other abilities return for a missing attachment.
	 *
	 * @return WP_Error
	 */
	private function not_found(): WP_Error {
		return AbilityGuard::error(
			AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND,
			__( 'No attachment exists with that ID.', 'media-library-tools' ),
			404
		);
	}
}
