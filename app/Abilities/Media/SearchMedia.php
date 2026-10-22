<?php
/**
 * Ability: tsmlt/search-media.
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
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Read-only, paginated search over media library attachments.
 */
class SearchMedia extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Largest page size a client may request.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 50;

	/**
	 * Page size when the client sends none.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 20;

	/**
	 * Longest accepted search phrase.
	 *
	 * @var int
	 */
	const MAX_SEARCH_LENGTH = 200;

	/**
	 * Top-level MIME types a client may filter by on their own.
	 *
	 * @var string[]
	 */
	const MIME_TOP_LEVEL_TYPES = [ 'image', 'video', 'audio', 'application', 'text' ];

	/**
	 * Client-facing orderby values mapped to WP_Query orderby values.
	 *
	 * @var array<string, string>
	 */
	const ORDERBY_MAP = [
		'id'    => 'ID',
		'title' => 'title',
		'date'  => 'date',
	];

	/**
	 * How many matching IDs are permission-checked per batch.
	 *
	 * @var int
	 */
	const READ_CHECK_CHUNK = 500;

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/search-media';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Search media', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Search the media library and return one page of attachments with their ID, title, alt text, caption, MIME type, URL and upload date. Filter by search phrase, MIME type, missing alt text (combine missing_alt with mime_type "image" to find images that need alt text), or group_id (media assigned directly to that group; find IDs with tsmlt/list-media-groups). Read-only.', 'media-library-tools' );
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
				'search'      => [
					'type'        => 'string',
					'maxLength'   => self::MAX_SEARCH_LENGTH,
					'description' => 'Phrase matched against title, caption and description.',
				],
				'mime_type'   => [
					'type'        => 'string',
					'maxLength'   => 100,
					'pattern'     => '^(image|video|audio|application|text)(/[a-z0-9.+-]+)?$',
					'description' => 'A top-level type such as "image", or a full MIME type such as "image/jpeg".',
				],
				'missing_alt' => [
					'type'        => 'boolean',
					'description' => 'When true, only return attachments without alt text.',
				],
				'group_id'    => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Only return media assigned directly to this media group (not its subgroups). Must be an existing group ID; find IDs by name with tsmlt/list-media-groups.',
				],
				'page'        => [
					'type'        => 'integer',
					'minimum'     => 1,
					'default'     => 1,
					'description' => 'Page number, starting at 1.',
				],
				'per_page'    => [
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_PER_PAGE,
					'default'     => self::DEFAULT_PER_PAGE,
					'description' => 'Results per page.',
				],
				'orderby'     => [
					'type'        => 'string',
					'enum'        => array_keys( self::ORDERBY_MAP ),
					'default'     => 'date',
					'description' => 'Sort field.',
				],
				'order'       => [
					'type'        => 'string',
					'enum'        => [ 'ASC', 'DESC' ],
					'default'     => 'DESC',
					'description' => 'Sort direction.',
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
			'required'             => [ 'items', 'total', 'page', 'total_pages' ],
			'properties'           => [
				'items'       => [
					'type'  => 'array',
					'items' => MediaPresenter::summary_schema(),
				],
				'total'       => [
					'type'        => 'integer',
					'description' => 'Number of attachments matching the filters.',
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
	 * Run the search.
	 *
	 * @param mixed $input Ability input, already validated against the schema.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		$criteria = $this->sanitize_input( is_array( $input ) ? $input : [] );
		if ( is_wp_error( $criteria ) ) {
			return $criteria;
		}

		// Count and page only what the user may read. read_post can be changed
		// by any plugin through map_meta_cap, so it cannot be expressed in SQL:
		// every match is checked with the real capability, then paged. This
		// keeps unreadable attachments out of the totals as well as the items,
		// and keeps every page full.
		$readable = $this->readable_ids( $this->build_query_args( $criteria ) );
		$total    = count( $readable );
		$page_ids = array_slice( $readable, ( $criteria['page'] - 1 ) * $criteria['per_page'], $criteria['per_page'] );

		$items = [];
		foreach ( $page_ids as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( $attachment instanceof WP_Post ) {
				$items[] = MediaPresenter::summary( $attachment );
			}
		}

		return [
			'items'       => $items,
			'total'       => $total,
			'page'        => $criteria['page'],
			'total_pages' => (int) ceil( $total / $criteria['per_page'] ),
		];
	}

	/**
	 * IDs of every matching attachment the current user may read, in result
	 * order.
	 *
	 * Runs the search query for IDs only, without paging, then checks
	 * read_post in batches with the post cache primed for each batch.
	 *
	 * @param array $args WP_Query arguments from build_query_args().
	 *
	 * @return int[]
	 */
	private function readable_ids( array $args ): array {
		$args['fields']                 = 'ids';
		$args['posts_per_page']         = -1;
		$args['no_found_rows']          = true;
		$args['update_post_meta_cache'] = false;
		$args['update_post_term_cache'] = false;
		unset( $args['paged'] );

		$readable = [];
		foreach ( array_chunk( array_map( 'intval', ( new WP_Query( $args ) )->posts ), self::READ_CHECK_CHUNK ) as $chunk ) {
			_prime_post_caches( $chunk, false, false );
			foreach ( $chunk as $attachment_id ) {
				$attachment = get_post( $attachment_id );
				if ( $attachment instanceof WP_Post && AbilityGuard::can_access_attachment( $attachment, 'read_post' ) ) {
					$readable[] = $attachment_id;
				}
			}
		}

		return $readable;
	}

	/**
	 * Sanitize and normalise the input.
	 *
	 * The schema has already rejected wrong types and unknown keys; this still
	 * sanitizes every value and applies defaults, since schema validation does
	 * neither.
	 *
	 * @param array $input Raw input.
	 *
	 * @return array|WP_Error
	 */
	private function sanitize_input( array $input ) {
		$search = sanitize_text_field( (string) ( $input['search'] ?? '' ) );
		$search = mb_substr( $search, 0, self::MAX_SEARCH_LENGTH );

		$mime_type = '';
		if ( isset( $input['mime_type'] ) && '' !== $input['mime_type'] ) {
			$mime_type = $this->sanitize_mime_type( (string) $input['mime_type'] );
			if ( '' === $mime_type ) {
				return AbilityGuard::error(
					AbilityGuard::ERROR_INVALID_INPUT,
					__( 'mime_type must be a top-level type such as "image" or a MIME type known to WordPress.', 'media-library-tools' ),
					400
				);
			}
		}

		$group_id = 0;
		if ( isset( $input['group_id'] ) ) {
			$groups = AbilityGuard::resolve_groups( [ $input['group_id'] ] );
			if ( is_wp_error( $groups ) ) {
				return $groups;
			}
			$group_id = (int) $groups[0]->term_id;
		}

		$orderby = sanitize_key( (string) ( $input['orderby'] ?? 'date' ) );
		$order   = strtoupper( sanitize_key( (string) ( $input['order'] ?? 'DESC' ) ) );

		return [
			'search'      => $search,
			'mime_type'   => $mime_type,
			'missing_alt' => true === ( $input['missing_alt'] ?? false ),
			'group_id'    => $group_id,
			'page'        => max( 1, absint( $input['page'] ?? 1 ) ),
			'per_page'    => min( self::MAX_PER_PAGE, max( 1, absint( $input['per_page'] ?? self::DEFAULT_PER_PAGE ) ) ),
			'orderby'     => isset( self::ORDERBY_MAP[ $orderby ] ) ? $orderby : 'date',
			'order'       => 'ASC' === $order ? 'ASC' : 'DESC',
		];
	}

	/**
	 * Accept a top-level MIME type, or a full MIME type WordPress knows about.
	 *
	 * @param string $mime_type Raw MIME filter.
	 *
	 * @return string The accepted MIME filter, or '' when it is not allowed.
	 */
	private function sanitize_mime_type( string $mime_type ): string {
		$mime_type = strtolower( trim( $mime_type ) );

		if ( in_array( $mime_type, self::MIME_TOP_LEVEL_TYPES, true ) ) {
			return $mime_type;
		}

		$known = array_merge( array_values( wp_get_mime_types() ), array_values( get_allowed_mime_types() ) );

		return in_array( $mime_type, $known, true ) ? $mime_type : '';
	}

	/**
	 * Build the WP_Query arguments.
	 *
	 * Starts from the media table's query builder so paging and search behave
	 * the same as in the admin UI, but passes it only the parameters this
	 * ability accepts. Status and ordering are then set explicitly: the builder
	 * forwards `status` as given and has no date ordering.
	 *
	 * @param array $criteria Sanitized criteria from sanitize_input().
	 *
	 * @return array
	 */
	private function build_query_args( array $criteria ): array {
		$args = Api::instance()->build_media_query_args(
			[
				'searchKeyWords' => $criteria['search'],
				'media_per_page' => $criteria['per_page'],
				'paged'          => $criteria['page'],
				'order'          => $criteria['order'],
				'categories'     => $criteria['group_id'] ? [ $criteria['group_id'] ] : [],
			]
		);

		// The media table's group filter, limited to direct assignment:
		// hierarchical taxonomies otherwise include child groups' media.
		if ( $criteria['group_id'] && isset( $args['tax_query'][0] ) ) {
			$args['tax_query'][0]['include_children'] = false;
		}

		$args['post_type']   = 'attachment';
		$args['post_status'] = 'inherit';
		$args['orderby']     = self::ORDERBY_MAP[ $criteria['orderby'] ];
		$args['order']       = $criteria['order'];

		if ( '' !== $criteria['mime_type'] ) {
			$args['post_mime_type'] = $criteria['mime_type'];
		}

		if ( $criteria['missing_alt'] ) {
			$args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Alt text lives in post meta; there is no other way to filter by it.
				'relation' => 'OR',
				[
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '=',
				],
			];
		}

		return $args;
	}
}
