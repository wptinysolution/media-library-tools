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
		return __( 'Search the media library and return one page of attachments with their ID, title, alt text, caption, MIME type, URL and upload date. Filter by search phrase, MIME type, or missing alt text (combine missing_alt with mime_type "image" to find images that need alt text). Read-only.', 'media-library-tools' );
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

		$query = new WP_Query( $this->build_query_args( $criteria ) );
		$items = [];
		foreach ( $query->posts as $attachment ) {
			// Skip anything the user may not read, rather than failing the page.
			if ( $attachment instanceof WP_Post && AbilityGuard::can_access_attachment( $attachment, 'read_post' ) ) {
				$items[] = MediaPresenter::summary( $attachment );
			}
		}

		return [
			'items'       => $items,
			'total'       => (int) $query->found_posts,
			'page'        => $criteria['page'],
			'total_pages' => (int) $query->max_num_pages,
		];
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

		$orderby = sanitize_key( (string) ( $input['orderby'] ?? 'date' ) );
		$order   = strtoupper( sanitize_key( (string) ( $input['order'] ?? 'DESC' ) ) );

		return [
			'search'      => $search,
			'mime_type'   => $mime_type,
			'missing_alt' => true === ( $input['missing_alt'] ?? false ),
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
			]
		);

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
