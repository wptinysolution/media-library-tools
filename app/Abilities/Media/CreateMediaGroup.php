<?php
/**
 * Ability: tsmlt/create-media-group.
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
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Term;

/**
 * Create one media group (a tsmlt_category term), at the top level or under
 * an existing group.
 *
 * Uses wp_insert_term(), so WordPress generates the slug and refuses a name
 * that already exists under the same parent (ignoring case); the same name
 * under a different parent is allowed, as in the WordPress term screen. The
 * parent must be a media group — wp_insert_term() alone would accept a term
 * of any taxonomy. The new term is read back and checked before it is
 * returned; if it does not match, it is removed again.
 *
 * Requires the base capability and the taxonomy's `edit_terms` capability
 * (what WordPress checks before creating a term in a hierarchical taxonomy).
 * Registered with `meta.mcp.public` false, so it is offered only on the
 * dedicated Media Library Tools server. Groups are never renamed or deleted
 * here (tsmlt/update-media-group renames them).
 */
class CreateMediaGroup extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Error code: a group with this name already exists under the parent.
	 *
	 * @var string
	 */
	const ERROR_GROUP_EXISTS = 'tsmlt_group_exists';

	/**
	 * Error code: WordPress could not create the group as requested.
	 *
	 * @var string
	 */
	const ERROR_UPDATE_FAILED = 'tsmlt_update_failed';

	/**
	 * Longest accepted group name (the database column's length).
	 *
	 * @var int
	 */
	const MAX_NAME_LENGTH = 200;

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/create-media-group';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Create media group', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Create one media library group (the Groups used to organise media) and return its ID, name, parent and count. name is plain text (HTML is removed); parent is an existing group ID from tsmlt/list-media-groups, or 0 for a top-level group. A name that already exists under the same parent (ignoring case) is refused and the error names the existing group\'s ID; the same name under a different parent is allowed. To rename a group, use tsmlt/update-media-group; groups cannot be deleted with these tools. To put media in the new group, use tsmlt/set-media-groups.', 'media-library-tools' );
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
			'required'             => [ 'name' ],
			'properties'           => [
				'name'   => [
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => self::MAX_NAME_LENGTH,
					'description' => 'New group name, plain text (HTML is removed). At most 200 characters.',
				],
				'parent' => [
					'type'        => 'integer',
					'minimum'     => 0,
					'default'     => 0,
					'description' => 'Parent group ID from tsmlt/list-media-groups; 0 or omitted for a top-level group.',
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
			'required'             => [ 'group', 'created' ],
			'properties'           => [
				'group'   => MediaPresenter::group_schema(),
				'created' => [
					'type'        => 'boolean',
					'description' => 'True: the group was created.',
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
			'destructive' => false,
			'idempotent'  => false,
		];
	}

	/**
	 * Create the group.
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

		$taxonomy = get_taxonomy( Fns::CATEGORY );
		if ( ! $taxonomy || empty( $taxonomy->cap->edit_terms ) || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_FORBIDDEN,
				__( 'You are not allowed to create media groups.', 'media-library-tools' ),
				403
			);
		}

		$input = is_array( $input ) ? $input : [];

		// The schema is closed; refuse unexpected keys (such as a slug) here
		// too, since this callback can be reached without schema validation.
		if ( array_diff( array_keys( $input ), [ 'name', 'parent' ] ) ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				__( 'Only name and parent are accepted.', 'media-library-tools' ),
				400
			);
		}

		$name = $this->sanitize_name( $input['name'] ?? null );
		if ( is_wp_error( $name ) ) {
			return $name;
		}

		$parent = $this->resolve_parent( $input['parent'] ?? 0 );
		if ( is_wp_error( $parent ) ) {
			return $parent;
		}

		// The name exactly as WordPress will store it, to verify the result.
		$expected = wp_unslash( sanitize_term_field( 'name', wp_slash( $name ), 0, Fns::CATEGORY, 'db' ) );
		if ( '' === trim( $expected ) || mb_strlen( $expected ) > self::MAX_NAME_LENGTH ) {
			return $this->invalid_name();
		}

		// wp_insert_term() expects slashed input; unslashed, backslashes are lost.
		$created = wp_insert_term( wp_slash( $name ), Fns::CATEGORY, [ 'parent' => $parent ] );
		if ( is_wp_error( $created ) ) {
			return $this->map_insert_error( $created );
		}

		$term_id = isset( $created['term_id'] ) ? (int) $created['term_id'] : 0;
		$term    = $term_id ? get_term( $term_id, Fns::CATEGORY ) : null;
		if ( ! $term instanceof WP_Term || Fns::CATEGORY !== $term->taxonomy || $parent !== (int) $term->parent || $expected !== $term->name ) {
			// Remove what was created, so a mismatch leaves nothing behind.
			if ( $term_id ) {
				wp_delete_term( $term_id, Fns::CATEGORY );
			}
			return $this->update_failed();
		}

		return [
			'group'   => MediaPresenter::group( $term ),
			'created' => true,
		];
	}

	/**
	 * The name as plain, trimmed text, or an error when nothing usable is left.
	 *
	 * @param mixed $name Raw name.
	 *
	 * @return string|WP_Error
	 */
	private function sanitize_name( $name ) {
		if ( ! is_string( $name ) ) {
			return $this->invalid_name();
		}

		$name = trim( sanitize_text_field( $name ) );
		if ( '' === $name || mb_strlen( $name ) > self::MAX_NAME_LENGTH ) {
			return $this->invalid_name();
		}

		return $name;
	}

	/**
	 * The parent group ID: 0, or an existing media group.
	 *
	 * @param mixed $parent_id Raw parent ID.
	 *
	 * @return int|WP_Error
	 */
	private function resolve_parent( $parent_id ) {
		if ( ! is_int( $parent_id ) || $parent_id < 0 ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				__( 'parent must be 0 or an existing group ID.', 'media-library-tools' ),
				400
			);
		}
		if ( 0 === $parent_id ) {
			return 0;
		}

		// Refuses unknown IDs and terms of any other taxonomy.
		$groups = AbilityGuard::resolve_groups( [ $parent_id ] );
		return is_wp_error( $groups ) ? $groups : (int) $groups[0]->term_id;
	}

	/**
	 * Map a wp_insert_term() error.
	 *
	 * @param WP_Error $error Error.
	 *
	 * @return WP_Error
	 */
	private function map_insert_error( WP_Error $error ): WP_Error {
		$code = $error->get_error_code();

		if ( 'term_exists' === $code ) {
			$existing = absint( $error->get_error_data( 'term_exists' ) );
			return new WP_Error(
				self::ERROR_GROUP_EXISTS,
				$existing
					/* translators: %d: existing group ID. */
					? sprintf( __( 'A group with this name already exists under this parent (ID %d).', 'media-library-tools' ), $existing )
					: __( 'A group with this name already exists under this parent.', 'media-library-tools' ),
				[
					'status'   => 409,
					'group_id' => $existing,
				]
			);
		}

		if ( in_array( $code, [ 'empty_term_name', 'invalid_term_name' ], true ) ) {
			return $this->invalid_name();
		}

		return $this->update_failed();
	}

	/**
	 * Error for a name that is empty, too long, or only markup.
	 *
	 * @return WP_Error
	 */
	private function invalid_name(): WP_Error {
		return AbilityGuard::error(
			AbilityGuard::ERROR_INVALID_INPUT,
			__( 'name must be 1 to 200 characters of plain text.', 'media-library-tools' ),
			400
		);
	}

	/**
	 * Error for a group WordPress could not create as requested.
	 *
	 * @return WP_Error
	 */
	private function update_failed(): WP_Error {
		return AbilityGuard::error(
			self::ERROR_UPDATE_FAILED,
			__( 'The media group could not be created.', 'media-library-tools' ),
			500
		);
	}
}
