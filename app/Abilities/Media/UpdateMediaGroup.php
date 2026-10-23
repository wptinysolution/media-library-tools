<?php
/**
 * Ability: tsmlt/update-media-group.
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
 * Rename one media group (a tsmlt_category term). Only the name changes:
 * the ID, slug (and so the group's URL), parent and media assignments stay
 * as they are.
 *
 * WordPress's wp_update_term() does not refuse a name that a sibling
 * already has (only a duplicate slug), so siblings are checked here, the way
 * wp_insert_term() checks them when a group is created: same parent,
 * ignoring case. The term is read back after the update; if it does not
 * match, the previous name is put back and an error is returned.
 *
 * Requires the base capability, the taxonomy's `edit_terms` capability and
 * `edit_term` on this group (what WordPress checks before updating a term).
 * Registered with `meta.mcp.public` false, so it is offered only on the
 * dedicated Media Library Tools server. Groups are never deleted or moved
 * here.
 */
class UpdateMediaGroup extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Error code: a sibling group already has this name.
	 *
	 * @var string
	 */
	const ERROR_GROUP_EXISTS = 'tsmlt_group_exists';

	/**
	 * Error code: WordPress could not rename the group as requested.
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
		return 'tsmlt/update-media-group';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Rename media group', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Rename one media library group by ID (find IDs with tsmlt/list-media-groups). Only the name changes: the group keeps its ID, slug and URL, parent group and media. name is plain text (HTML is removed). A name another group already has under the same parent (ignoring case) is refused and the error names that group\'s ID. Returns the group after the change, its previous name, and whether anything changed. Groups cannot be deleted or moved with these tools.', 'media-library-tools' );
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
			'required'             => [ 'id', 'name' ],
			'properties'           => [
				'id'   => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Group ID from tsmlt/list-media-groups.',
				],
				'name' => [
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => self::MAX_NAME_LENGTH,
					'description' => 'New group name, plain text (HTML is removed). At most 200 characters.',
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
			'required'             => [ 'group', 'previous_name', 'changed' ],
			'properties'           => [
				'group'         => MediaPresenter::group_schema(),
				'previous_name' => [
					'type'        => 'string',
					'description' => 'The group\'s name before this request.',
				],
				'changed'       => [
					'type'        => 'boolean',
					'description' => 'Whether the name changed. False when the group already had this name.',
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
	 * Rename the group.
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
			return $this->forbidden();
		}

		$input = is_array( $input ) ? $input : [];

		// The schema is closed; refuse unexpected keys (such as a slug or a
		// parent) here too, since this callback can be reached without it.
		if ( array_diff( array_keys( $input ), [ 'id', 'name' ] ) ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				__( 'Only id and name are accepted.', 'media-library-tools' ),
				400
			);
		}

		$id = $input['id'] ?? null;
		if ( ! is_int( $id ) || $id < 1 ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				__( 'id must be a group ID.', 'media-library-tools' ),
				400
			);
		}

		// Refuses unknown IDs and terms of any other taxonomy.
		$groups = AbilityGuard::resolve_groups( [ $id ] );
		if ( is_wp_error( $groups ) ) {
			return $groups;
		}
		$term = $groups[0];

		if ( ! current_user_can( 'edit_term', (int) $term->term_id ) ) {
			return $this->forbidden();
		}

		$name = $this->sanitize_name( $input['name'] ?? null );
		if ( is_wp_error( $name ) ) {
			return $name;
		}

		// The name exactly as WordPress will store it.
		$expected = wp_unslash( sanitize_term_field( 'name', wp_slash( $name ), (int) $term->term_id, Fns::CATEGORY, 'db' ) );
		if ( '' === trim( $expected ) || mb_strlen( $expected ) > self::MAX_NAME_LENGTH ) {
			return $this->invalid_name();
		}

		$previous_name = MediaPresenter::group( $term )['name'];

		if ( $expected === $term->name ) {
			return [
				'group'         => MediaPresenter::group( $term ),
				'previous_name' => $previous_name,
				'changed'       => false,
			];
		}

		$sibling = $this->sibling_with_name( $term, $expected );
		if ( $sibling ) {
			return new WP_Error(
				self::ERROR_GROUP_EXISTS,
				/* translators: %d: existing group ID. */
				sprintf( __( 'Another group with this name already exists under the same parent (ID %d).', 'media-library-tools' ), $sibling ),
				[
					'status'   => 409,
					'group_id' => $sibling,
				]
			);
		}

		$updated = wp_update_term( (int) $term->term_id, Fns::CATEGORY, [ 'name' => wp_slash( $name ) ] );
		if ( is_wp_error( $updated ) ) {
			return 'empty_term_name' === $updated->get_error_code() ? $this->invalid_name() : $this->update_failed();
		}

		// Normally the same ID; a legacy term shared with another taxonomy
		// is split and gets a new one.
		$term_id = isset( $updated['term_id'] ) ? (int) $updated['term_id'] : 0;
		$after   = $term_id ? get_term( $term_id, Fns::CATEGORY ) : null;
		if ( ! $after instanceof WP_Term || Fns::CATEGORY !== $after->taxonomy || (int) $term->parent !== (int) $after->parent
			|| $term->slug !== $after->slug || $expected !== $after->name ) {
			// Put the previous name back; the group and its media are not touched otherwise.
			if ( $term_id ) {
				wp_update_term( $term_id, Fns::CATEGORY, [ 'name' => wp_slash( $term->name ) ] );
			}
			return $this->update_failed();
		}

		return [
			'group'         => MediaPresenter::group( $after ),
			'previous_name' => $previous_name,
			'changed'       => true,
		];
	}

	/**
	 * ID of another group under the same parent with this name (compared as
	 * WordPress compares names when creating a term: ignoring case), or 0.
	 *
	 * @param WP_Term $term     Group being renamed.
	 * @param string  $expected New name, as stored.
	 *
	 * @return int
	 */
	private function sibling_with_name( WP_Term $term, string $expected ): int {
		$matches = get_terms(
			[
				'taxonomy'               => Fns::CATEGORY,
				'name'                   => $expected,
				'parent'                 => (int) $term->parent,
				'hide_empty'             => false,
				'exclude'                => [ (int) $term->term_id ],
				'update_term_meta_cache' => false,
			]
		);
		if ( ! is_array( $matches ) ) {
			return 0;
		}
		foreach ( $matches as $match ) {
			if ( $match instanceof WP_Term && strtolower( $expected ) === strtolower( $match->name ) ) {
				return (int) $match->term_id;
			}
		}
		return 0;
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
	 * Error for a user who may not rename this group.
	 *
	 * @return WP_Error
	 */
	private function forbidden(): WP_Error {
		return AbilityGuard::error(
			AbilityGuard::ERROR_FORBIDDEN,
			__( 'You are not allowed to rename media groups.', 'media-library-tools' ),
			403
		);
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
	 * Error for a group WordPress could not rename as requested.
	 *
	 * @return WP_Error
	 */
	private function update_failed(): WP_Error {
		return AbilityGuard::error(
			self::ERROR_UPDATE_FAILED,
			__( 'The media group could not be renamed; it keeps its previous name.', 'media-library-tools' ),
			500
		);
	}
}
