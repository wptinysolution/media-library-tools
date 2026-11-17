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
use TinySolutions\mlt\Helpers\Fns;
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
	 * Longest accepted file name fragment.
	 *
	 * @var int
	 */
	const MAX_FILENAME_LENGTH = 200;

	/**
	 * Post meta key holding an attachment's file, relative to the uploads folder.
	 *
	 * @var string
	 */
	const ATTACHED_FILE_META_KEY = '_wp_attached_file';

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
	 * Most attachment IDs one search accepts in `ids`.
	 *
	 * @var int
	 */
	const MAX_IDS = 100;

	/**
	 * Content types whose associated media can be searched (product only when
	 * WooCommerce registers it).
	 *
	 * @var string[]
	 */
	const CONTENT_TYPES = [ 'post', 'page', 'product' ];

	/**
	 * Most content items listed when a title matches several.
	 *
	 * @var int
	 */
	const MAX_CONTENT_CANDIDATES = 10;

	/**
	 * WooCommerce's product gallery meta key (comma-separated attachment IDs).
	 *
	 * @var string
	 */
	const PRODUCT_GALLERY_META_KEY = '_product_image_gallery';

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
		return __( 'Search the media library and return one page of attachments with their ID, title, alt text, caption, MIME type, URL and upload date. Filter by search phrase (title, caption, description), filename (part of the file name, case-insensitive, without folders), MIME type, missing alt text (combine missing_alt with mime_type "image" to find images that need alt text), group_id (media assigned directly to that group; find IDs with tsmlt/list-media-groups), or attached (true: attached to a post, i.e. uploaded to or set as attached to one; false: unattached). Attached does not mean the media is used in content: a file can be used without being attached, and attached without being used. Also by ids (1 to 100 media IDs the user gave; IDs not returned are listed in missing_ids), or by a post, page or WooCommerce product, given by content_id or content_title: media attached to it, its featured image, its product gallery, and media inserted in its content with WordPress image, gallery, cover or media-and-text blocks, wp-image classes or [gallery] shortcodes. Media used only by URL, in page builders, widgets, theme settings or from other sites is not found this way. When content_title matches several items, no media is returned and content_candidates lists them: ask the user which one. Each result has its file name, to tell similar images apart. Read-only.', 'media-library-tools' );
	}

	/**
	 * Offered on the MCP Adapter's default gateway too (read-only).
	 *
	 * @return bool
	 */
	protected function is_mcp_public(): bool {
		return true;
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
				'search'        => [
					'type'        => 'string',
					'maxLength'   => self::MAX_SEARCH_LENGTH,
					'description' => 'Phrase matched against title, caption and description.',
				],
				'filename'      => [
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => self::MAX_FILENAME_LENGTH,
					'pattern'     => '^[^/\\\\]+$',
					'description' => 'Part of the file name, matched case-insensitively against the name of the attachment\'s file (for large images WordPress scaled down, the "-scaled" file). Folders are not matched, so "/" and "\\" are not allowed. Matched literally: % and _ are not wildcards.',
				],
				'mime_type'     => [
					'type'        => 'string',
					'maxLength'   => 100,
					'pattern'     => '^(image|video|audio|application|text)(/[a-z0-9.+-]+)?$',
					'description' => 'A top-level type such as "image", or a full MIME type such as "image/jpeg".',
				],
				'missing_alt'   => [
					'type'        => 'boolean',
					'description' => 'When true, only return attachments without alt text.',
				],
				'group_id'      => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Only return media assigned directly to this media group (not its subgroups). Must be an existing group ID; find IDs by name with tsmlt/list-media-groups.',
				],
				'attached'      => [
					'type'        => 'boolean',
					'description' => 'true: only media attached to a post (WordPress\'s "Uploaded to" parent, which may be a draft, private or trashed post); false: only unattached media. Omit for both. This is not whether the media is used in content.',
				],
				'ids'           => [
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => self::MAX_IDS,
					'uniqueItems' => true,
					'items'       => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'description' => 'Only these media IDs, as the user gave them (1 to 100). Results keep this order unless orderby is given.',
				],
				'content_id'    => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Only media associated with this post, page or WooCommerce product (see the description). Not with content_title.',
				],
				'content_title' => [
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => self::MAX_SEARCH_LENGTH,
					'description' => 'Like content_id, by the title of a post, page or WooCommerce product. An exact title is used first, then titles containing it.',
				],
				'page'          => [
					'type'        => 'integer',
					'minimum'     => 1,
					'default'     => 1,
					'description' => 'Page number, starting at 1.',
				],
				'per_page'      => [
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_PER_PAGE,
					'default'     => self::DEFAULT_PER_PAGE,
					'description' => 'Results per page.',
				],
				'orderby'       => [
					'type'        => 'string',
					'enum'        => array_keys( self::ORDERBY_MAP ),
					'default'     => 'date',
					'description' => 'Sort field.',
				],
				'order'         => [
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
				'items'              => [
					'type'  => 'array',
					'items' => self::item_schema(),
				],
				'missing_ids'        => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'With ids: the IDs not returned (missing, not media, trashed, not readable by you, or not matching the other filters), reported alike.',
				],
				'content'            => [
					'type'        => [ 'object', 'null' ],
					'description' => 'With content_id or content_title: the post, page or product searched.',
					'properties'  => self::content_schema()['properties'],
				],
				'content_candidates' => [
					'type'        => 'array',
					'items'       => self::content_schema(),
					'description' => 'When content_title matches several items: up to 10 of them; no media is returned. Ask the user which one, then search with its content_id.',
				],
				'total'              => [
					'type'        => 'integer',
					'description' => 'Number of attachments matching the filters.',
				],
				'page'               => [
					'type'        => 'integer',
					'description' => 'Page returned.',
				],
				'total_pages'        => [
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
		// The callback can be reached without the permission callback.
		$base = AbilityGuard::check_base_capability();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$criteria = $this->sanitize_input( is_array( $input ) ? $input : [] );
		if ( is_wp_error( $criteria ) ) {
			return $criteria;
		}

		// Content by title: one match is searched; several are listed for the
		// user to choose from, with no media.
		$content = null;
		if ( '' !== $criteria['content_title'] ) {
			$matches = $this->contents_by_title( $criteria['content_title'] );
			if ( ! $matches ) {
				return self::content_not_found();
			}
			if ( count( $matches ) > 1 ) {
				return [
					'items'              => [],
					'total'              => 0,
					'page'               => $criteria['page'],
					'total_pages'        => 0,
					'content'            => null,
					'content_candidates' => array_map( [ self::class, 'present_content' ], array_slice( $matches, 0, self::MAX_CONTENT_CANDIDATES ) ),
				];
			}
			$content = $matches[0];
		} elseif ( $criteria['content_id'] ) {
			$content = $this->readable_content( $criteria['content_id'] );
			if ( null === $content ) {
				return self::content_not_found();
			}
		}
		$associations = null === $content ? null : $this->associated_media( $content );

		// Count and page only what the user may read. read_post can be changed
		// by any plugin through map_meta_cap, so it cannot be expressed in SQL:
		// every match is checked with the real capability, then paged. This
		// keeps unreadable attachments out of the totals as well as the items,
		// and keeps every page full.
		$readable = $this->readable_ids( $this->build_query_args( $criteria, null === $associations ? null : array_keys( $associations ) ), $criteria['filename'] );
		$total    = count( $readable );
		$page_ids = array_slice( $readable, ( $criteria['page'] - 1 ) * $criteria['per_page'], $criteria['per_page'] );

		$items = [];
		foreach ( $page_ids as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( $attachment instanceof WP_Post ) {
				$item = MediaPresenter::summary( $attachment ) + [ 'filename' => self::file_name( $attachment_id ) ];
				if ( null !== $associations ) {
					$item['associations'] = $associations[ $attachment_id ] ?? [];
				}
				$items[] = $item;
			}
		}

		$result = [
			'items'       => $items,
			'total'       => $total,
			'page'        => $criteria['page'],
			'total_pages' => (int) ceil( $total / $criteria['per_page'] ),
		];
		if ( $criteria['ids'] ) {
			$result['missing_ids'] = array_values( array_diff( $criteria['ids'], $readable ) );
		}
		if ( null !== $content ) {
			$result['content'] = self::present_content( $content );
		}
		return $result;
	}

	/**
	 * Schema of one result: the media summary, its file name and, with a
	 * content filter, how it is associated with the content.
	 *
	 * @return array
	 */
	private static function item_schema(): array {
		$schema                               = MediaPresenter::summary_schema();
		$schema['required'][]                 = 'filename';
		$schema['properties']['filename']     = [
			'type'        => 'string',
			'description' => 'File name, without folders.',
		];
		$schema['properties']['associations'] = [
			'type'        => 'array',
			'items'       => [
				'type' => 'string',
				'enum' => [ 'attached', 'featured', 'gallery', 'content' ],
			],
			'description' => 'With a content filter: attached (uploaded to it), featured (its featured image), gallery (its product gallery), content (inserted in its content).',
		];
		return $schema;
	}

	/**
	 * Schema of a post, page or product in the output.
	 *
	 * @return array
	 */
	private static function content_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'id', 'title', 'type', 'date' ],
			'properties'           => [
				'id'    => [ 'type' => 'integer' ],
				'title' => [ 'type' => 'string' ],
				'type'  => [
					'type' => 'string',
					'enum' => self::CONTENT_TYPES,
				],
				'date'  => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * A post, page or product as the output shows it.
	 *
	 * @param WP_Post $post Content.
	 *
	 * @return array
	 */
	private static function present_content( WP_Post $post ): array {
		return [
			'id'    => (int) $post->ID,
			'title' => Fns::prepare_text_for_json( get_the_title( $post ) ),
			'type'  => (string) $post->post_type,
			'date'  => (string) get_post_time( 'c', true, $post ),
		];
	}

	/**
	 * The error for content that does not exist or that the user may not read
	 * (reported alike).
	 *
	 * @return WP_Error
	 */
	private static function content_not_found(): WP_Error {
		return AbilityGuard::error( 'tsmlt_content_not_found', __( 'No post, page or product with that ID or title was found that you can read. Check the title or ID, or search media by name instead.', 'media-library-tools' ), 404 );
	}

	/**
	 * The file name of an attachment, without folders ('' when unknown).
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string
	 */
	private static function file_name( int $attachment_id ): string {
		$file = get_post_meta( $attachment_id, self::ATTACHED_FILE_META_KEY, true );
		return is_string( $file ) && '' !== $file ? wp_basename( $file ) : '';
	}

	/**
	 * Content types that can be searched on this site.
	 *
	 * @return string[]
	 */
	private static function content_types(): array {
		return array_values( array_filter( self::CONTENT_TYPES, 'post_type_exists' ) );
	}

	/**
	 * A post, page or product the user may read, or null (missing, another
	 * type, trashed, an auto-draft or not readable, alike).
	 *
	 * @param int $content_id Content ID.
	 *
	 * @return WP_Post|null
	 */
	private function readable_content( int $content_id ): ?WP_Post {
		$post = get_post( $content_id );
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::content_types(), true ) || in_array( $post->post_status, [ 'trash', 'auto-draft' ], true ) || ! current_user_can( 'read_post', $post->ID ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * Readable posts, pages and products with this title: exact titles when
	 * there are any, otherwise titles containing it; at most
	 * MAX_CONTENT_CANDIDATES + 1, so several are known to be several.
	 *
	 * @param string $title Title.
	 *
	 * @return WP_Post[]
	 */
	private function contents_by_title( string $title ): array {
		$types = self::content_types();
		$base  = [
			'post_type'              => $types,
			'post_status'            => 'any',
			'posts_per_page'         => self::MAX_CONTENT_CANDIDATES + 1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];
		foreach ( [
			[ 'title' => $title ],
			[
				's'              => $title,
				'search_columns' => [ 'post_title' ],
			],
		] as $match ) {
			$posts = array_values(
				array_filter(
					( new WP_Query( $base + $match ) )->posts,
					[ $this, 'is_readable_content' ]
				)
			);
			if ( $posts ) {
				return $posts;
			}
		}
		return [];
	}

	/**
	 * Whether a found post is readable content (see readable_content()).
	 *
	 * @param mixed $post Post.
	 *
	 * @return bool
	 */
	public function is_readable_content( $post ): bool {
		return $post instanceof WP_Post && null !== $this->readable_content( (int) $post->ID );
	}

	/**
	 * Media associated with a post, page or product, by how: attached
	 * ("Uploaded to"), featured image, WooCommerce product gallery, and
	 * media inserted in the content with WordPress image, gallery, cover or
	 * media-and-text blocks, wp-image-N classes or [gallery ids] shortcodes.
	 * IDs only; whether they are readable media is checked by the search.
	 *
	 * @param WP_Post $post Content.
	 *
	 * @return array<int, string[]> Attachment ID => associations.
	 */
	private function associated_media( WP_Post $post ): array {
		$found = [];

		$attached = new WP_Query(
			[
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_parent'            => $post->ID,
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);
		self::add_found( $found, $attached->posts, 'attached' );
		self::add_found( $found, get_post_thumbnail_id( $post ), 'featured' );
		if ( 'product' === $post->post_type ) {
			self::add_found( $found, explode( ',', (string) get_post_meta( $post->ID, self::PRODUCT_GALLERY_META_KEY, true ) ), 'gallery' );
		}

		$content = (string) $post->post_content;
		self::add_found( $found, self::block_media_ids( parse_blocks( $content ) ), 'content' );
		if ( preg_match_all( '/\bwp-image-(\d+)\b/', $content, $classes ) ) {
			self::add_found( $found, $classes[1], 'content' );
		}
		if ( preg_match_all( '/\[gallery\b[^\]]*\bids\s*=\s*["\']?([\d,\s]+)/i', $content, $galleries ) ) {
			foreach ( $galleries[1] as $list ) {
				self::add_found( $found, explode( ',', $list ), 'content' );
			}
		}

		return $found;
	}

	/**
	 * Record attachment IDs as associated in one way (each way once per ID).
	 *
	 * @param array  $found Attachment ID => associations, updated.
	 * @param mixed  $ids   An ID or IDs.
	 * @param string $how   Association.
	 *
	 * @return void
	 */
	private static function add_found( array &$found, $ids, string $how ): void {
		foreach ( (array) $ids as $id ) {
			$id = absint( $id );
			if ( $id && ! in_array( $how, $found[ $id ] ?? [], true ) ) {
				$found[ $id ][] = $how;
			}
		}
	}

	/**
	 * Media IDs in core image, gallery, cover and media-and-text blocks,
	 * nested blocks included.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param int   $depth  Nesting depth.
	 *
	 * @return int[]
	 */
	private static function block_media_ids( array $blocks, int $depth = 0 ): array {
		$ids = [];
		if ( $depth > 20 ) {
			return $ids;
		}
		foreach ( $blocks as $block ) {
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
			switch ( $block['blockName'] ?? '' ) {
				case 'core/image':
				case 'core/cover':
					$ids[] = $attrs['id'] ?? 0;
					break;
				case 'core/media-text':
					$ids[] = $attrs['mediaId'] ?? 0;
					break;
				case 'core/gallery':
					$ids = array_merge( $ids, isset( $attrs['ids'] ) && is_array( $attrs['ids'] ) ? $attrs['ids'] : [] );
					break;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$ids = array_merge( $ids, self::block_media_ids( $block['innerBlocks'], $depth + 1 ) );
			}
		}
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	/**
	 * IDs of every matching attachment the current user may read, in result
	 * order.
	 *
	 * Runs the search query for IDs only, without paging, then checks
	 * read_post in batches with the post cache primed for each batch.
	 *
	 * @param array  $args     WP_Query arguments from build_query_args().
	 * @param string $filename File name text to require (see file_name_matches()), or ''.
	 *
	 * @return int[]
	 */
	private function readable_ids( array $args, string $filename = '' ): array {
		$args['fields']                 = 'ids';
		$args['posts_per_page']         = -1;
		$args['no_found_rows']          = true;
		$args['update_post_meta_cache'] = false;
		$args['update_post_term_cache'] = false;
		unset( $args['paged'] );

		$readable = [];
		foreach ( array_chunk( array_map( 'intval', ( new WP_Query( $args ) )->posts ), self::READ_CHECK_CHUNK ) as $chunk ) {
			_prime_post_caches( $chunk, false, '' !== $filename );
			foreach ( $chunk as $attachment_id ) {
				$attachment = get_post( $attachment_id );
				if ( $attachment instanceof WP_Post && AbilityGuard::can_access_attachment( $attachment, 'read_post' )
					&& ( '' === $filename || $this->file_name_matches( $attachment_id, $filename ) ) ) {
					$readable[] = $attachment_id;
				}
			}
		}

		return $readable;
	}

	/**
	 * Whether the attachment's file name (without folders) contains the text,
	 * ignoring case.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $filename      Text to find.
	 *
	 * @return bool
	 */
	private function file_name_matches( int $attachment_id, string $filename ): bool {
		$file = get_post_meta( $attachment_id, self::ATTACHED_FILE_META_KEY, true );
		if ( ! is_string( $file ) || '' === $file ) {
			return false;
		}
		$name = wp_basename( $file );
		return function_exists( 'mb_stripos' ) ? false !== mb_stripos( $name, $filename ) : false !== stripos( $name, $filename );
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

		$filename = '';
		if ( array_key_exists( 'filename', $input ) ) {
			$filename = is_string( $input['filename'] ) ? trim( $input['filename'] ) : '';
			if ( '' === $filename || mb_strlen( $filename ) > self::MAX_FILENAME_LENGTH || 0 !== preg_match( '#[/\\\\\x00-\x1F\x7F]#', $filename ) ) {
				return AbilityGuard::error(
					AbilityGuard::ERROR_INVALID_INPUT,
					__( 'filename must be part of a file name: 1 to 200 characters, without folders ("/" or "\\") or control characters.', 'media-library-tools' ),
					400
				);
			}
		}

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

		// Core's schema check also accepts "true", "false", "1", "0", 1 and 0
		// for a boolean; only a real boolean is accepted, so a string "false"
		// can never be read as true.
		$attached = null;
		if ( array_key_exists( 'attached', $input ) ) {
			if ( ! is_bool( $input['attached'] ) ) {
				return AbilityGuard::error(
					AbilityGuard::ERROR_INVALID_INPUT,
					__( 'attached must be true or false.', 'media-library-tools' ),
					400
				);
			}
			$attached = $input['attached'];
		}

		$ids = [];
		if ( array_key_exists( 'ids', $input ) ) {
			$ids = is_array( $input['ids'] ) ? array_values( $input['ids'] ) : null;
			if ( null === $ids || ! $ids || count( $ids ) > self::MAX_IDS || count( $ids ) !== count( array_unique( $ids, SORT_REGULAR ) ) || array_filter( $ids, [ self::class, 'is_not_an_id' ] ) ) {
				return AbilityGuard::error(
					AbilityGuard::ERROR_INVALID_INPUT,
					__( 'ids must be 1 to 100 different media IDs.', 'media-library-tools' ),
					400
				);
			}
			$ids = array_map( 'intval', $ids );
		}

		if ( isset( $input['content_id'] ) && isset( $input['content_title'] ) ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				__( 'Give content_id or content_title, not both.', 'media-library-tools' ),
				400
			);
		}
		$content_title = sanitize_text_field( (string) ( $input['content_title'] ?? '' ) );
		if ( array_key_exists( 'content_title', $input ) && '' === $content_title ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				__( 'content_title must be a title.', 'media-library-tools' ),
				400
			);
		}

		$orderby = sanitize_key( (string) ( $input['orderby'] ?? 'date' ) );
		$order   = strtoupper( sanitize_key( (string) ( $input['order'] ?? 'DESC' ) ) );

		return [
			'search'        => $search,
			'filename'      => $filename,
			'mime_type'     => $mime_type,
			'missing_alt'   => true === ( $input['missing_alt'] ?? false ),
			'group_id'      => $group_id,
			'attached'      => $attached,
			'ids'           => $ids,
			'content_id'    => absint( $input['content_id'] ?? 0 ),
			'content_title' => mb_substr( $content_title, 0, self::MAX_SEARCH_LENGTH ),
			// Given IDs keep the user's order unless an order is asked for.
			'keep_id_order' => $ids && ! array_key_exists( 'orderby', $input ),
			'page'          => max( 1, absint( $input['page'] ?? 1 ) ),
			'per_page'      => min( self::MAX_PER_PAGE, max( 1, absint( $input['per_page'] ?? self::DEFAULT_PER_PAGE ) ) ),
			'orderby'       => isset( self::ORDERBY_MAP[ $orderby ] ) ? $orderby : 'date',
			'order'         => 'ASC' === $order ? 'ASC' : 'DESC',
		];
	}

	/**
	 * Whether a value is not a positive integer ID.
	 *
	 * @param mixed $value Value.
	 *
	 * @return bool
	 */
	public static function is_not_an_id( $value ): bool {
		return ! is_int( $value ) || $value < 1;
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
	 * @param array      $criteria   Sanitized criteria from sanitize_input().
	 * @param int[]|null $associated Media associated with the searched content, or null.
	 *
	 * @return array
	 */
	private function build_query_args( array $criteria, ?array $associated = null ): array {
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

		// Given IDs and the content's media narrow the search; nothing matches
		// when the two do not overlap.
		$in = $criteria['ids'] ? $criteria['ids'] : null;
		if ( null !== $associated ) {
			$in = null === $in ? array_map( 'intval', $associated ) : array_values( array_intersect( $in, $associated ) );
		}
		if ( null !== $in ) {
			$args['post__in'] = $in ? $in : [ 0 ];
			if ( $criteria['keep_id_order'] ) {
				$args['orderby'] = 'post__in';
			}
		}

		// Attached means the attachment has a parent post (post_parent); it
		// says nothing about where the media is actually used.
		if ( true === $criteria['attached'] ) {
			$args['post_parent__not_in'] = [ 0 ];
		} elseif ( false === $criteria['attached'] ) {
			$args['post_parent'] = 0;
		}

		$meta_query = [];
		if ( $criteria['missing_alt'] ) {
			$meta_query[] = [
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
		if ( '' !== $criteria['filename'] ) {
			// Narrows the query to files whose stored path contains the text
			// (WP_Meta_Query escapes LIKE wildcards and prepares the value);
			// file_name_matches() then drops matches found only in folder names.
			$meta_query[] = [
				'key'     => self::ATTACHED_FILE_META_KEY,
				'value'   => $criteria['filename'],
				'compare' => 'LIKE',
			];
		}
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = array_merge( [ 'relation' => 'AND' ], $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Alt text and the file name live in post meta; there is no other way to filter by them.
		}

		return $args;
	}
}
