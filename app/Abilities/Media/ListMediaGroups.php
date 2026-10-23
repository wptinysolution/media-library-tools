<?php
/**
 * Ability: tsmlt/list-media-groups.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities\Media;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abs\Ability;
use TinySolutions\mlt\Abilities\MediaPresenter;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Term;

/**
 * Read-only, paginated list of media groups (the tsmlt_category taxonomy
 * behind the media table's Groups).
 *
 * Filters: `search` (case-insensitive part of the name) and `id` (one exact
 * group). When both are given a group must match both. Results are ordered by
 * name, then ID, so groups that share a name keep a stable order across
 * pages. Groups are never created, renamed or deleted here (see
 * tsmlt/create-media-group and tsmlt/update-media-group).
 *
 * Registered with `meta.mcp.public` false: group names are site-internal, so
 * the ability is offered only on the dedicated Media Library Tools server.
 */
class ListMediaGroups extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Largest page size a client may request.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Page size when the client sends none.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 50;

	/**
	 * Longest accepted search phrase.
	 *
	 * @var int
	 */
	const MAX_SEARCH_LENGTH = 200;

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/list-media-groups';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'List media groups', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'List the media library groups (the Groups used to organise media), with each group\'s ID, name, parent group ID and number of media items. Filter by search (part of the name, case-insensitive) and/or id (one exact group); when both are given a group must match both. Several groups can share a name under different parents, so check the parent before using an ID. Use the returned ID as group_id in tsmlt/search-media or in tsmlt/set-media-groups. Read-only.', 'media-library-tools' );
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
			// Lets clients call the ability with no arguments at all.
			'default'              => [],
			'properties'           => [
				'search'   => [
					'type'        => 'string',
					'maxLength'   => self::MAX_SEARCH_LENGTH,
					'description' => 'Part of the group name, matched case-insensitively.',
				],
				'id'       => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Return only the group with this ID (combined with search when both are given).',
				],
				'page'     => [
					'type'        => 'integer',
					'minimum'     => 1,
					'default'     => 1,
					'description' => 'Page number, starting at 1.',
				],
				'per_page' => [
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_PER_PAGE,
					'default'     => self::DEFAULT_PER_PAGE,
					'description' => 'Groups per page.',
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
			'required'             => [ 'groups', 'total', 'page', 'total_pages' ],
			'properties'           => [
				'groups'      => [
					'type'  => 'array',
					'items' => MediaPresenter::group_schema(),
				],
				'total'       => [
					'type'        => 'integer',
					'description' => 'Number of groups matching the filters.',
				],
				'page'        => [
					'type'        => 'integer',
					'description' => 'Page returned.',
				],
				'total_pages' => [
					'type'        => 'integer',
					'description' => 'Number of pages at the requested page size.',
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
	 * List the groups.
	 *
	 * @param mixed $input Ability input, already validated against the schema.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		$input    = is_array( $input ) ? $input : [];
		$search   = mb_substr( sanitize_text_field( (string) ( $input['search'] ?? '' ) ), 0, self::MAX_SEARCH_LENGTH );
		$id       = absint( $input['id'] ?? 0 );
		$page     = max( 1, absint( $input['page'] ?? 1 ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, absint( $input['per_page'] ?? self::DEFAULT_PER_PAGE ) ) );

		$args = [
			'taxonomy'   => Fns::CATEGORY,
			'hide_empty' => false,
			'fields'     => 'id=>name',
		];
		if ( '' !== $search ) {
			// Term names are stored as WordPress sanitizes them on save (for
			// example "&" as "&amp;"), so the phrase is matched in that form.
			$args['name__like'] = wp_unslash( sanitize_term_field( 'name', wp_slash( $search ), 0, Fns::CATEGORY, 'db' ) );
		}
		if ( $id ) {
			$args['include'] = [ $id ];
		}

		// IDs and names only, for every match; full terms only for the page.
		$names = get_terms( $args );
		if ( is_wp_error( $names ) ) {
			$names = [];
		}
		$ids = array_map( 'intval', array_keys( $names ) );
		usort(
			$ids,
			static function ( int $a, int $b ) use ( $names ): int {
				$by_name = strnatcasecmp( (string) $names[ $a ], (string) $names[ $b ] );
				return 0 !== $by_name ? $by_name : $a <=> $b;
			}
		);

		$total  = count( $ids );
		$groups = [];
		foreach ( array_slice( $ids, ( $page - 1 ) * $per_page, $per_page ) as $term_id ) {
			$term = get_term( $term_id, Fns::CATEGORY );
			if ( $term instanceof WP_Term ) {
				$groups[] = MediaPresenter::group( $term );
			}
		}

		return [
			'groups'      => $groups,
			'total'       => $total,
			'page'        => $page,
			'total_pages' => (int) ceil( $total / $per_page ),
		];
	}
}
