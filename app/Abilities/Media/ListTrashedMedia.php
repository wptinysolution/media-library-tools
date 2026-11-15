<?php
/**
 * Ability: tsmlt/list-trashed-media.
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
use WP_Post;
use WP_Query;

/**
 * List attachments in the WordPress trash, most recently changed first, one
 * page at a time. Read-only. Each listed attachment needs `read_post`;
 * others on the page are only counted in `hidden`.
 */
class ListTrashedMedia extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Default attachments per page.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 10;

	/**
	 * Most attachments per page.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 20;

	/**
	 * Highest page number accepted.
	 *
	 * @var int
	 */
	const MAX_PAGE = 10000;

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
		return 'tsmlt/list-trashed-media';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'List trashed media', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'List media in the WordPress trash, most recently changed first, one page at a time (use page and total_pages). Each item gives its ID, title, alt text, caption, MIME type, URL (empty while the plugin hides trashed media), upload date, when it was trashed (trashed_at) and the earliest time WordPress\'s daily trash cleanup may delete it permanently (purge_at, EMPTY_TRASH_DAYS after trashed_at). Trashed media you cannot read is only counted in hidden. Use tsmlt/restore-media to restore. Read-only.', 'media-library-tools' ) . ' ' . MediaTrash::permanent_delete_guidance();
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
			'properties'           => [
				'page'     => [
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_PAGE,
					'default'     => 1,
					'description' => 'Page number, starting at 1 (at most 10000).',
				],
				'per_page' => [
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_PER_PAGE,
					'default'     => self::DEFAULT_PER_PAGE,
					'description' => 'Attachments per page (1-20, default 10).',
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
		$item                             = MediaPresenter::summary_schema();
		$item['required']                 = array_merge( $item['required'], [ 'trashed_at', 'purge_at' ] );
		$item['properties']['trashed_at'] = [
			'type'        => 'string',
			'description' => 'When it was trashed, ISO 8601 (UTC), or empty when WordPress did not record it.',
		];
		$item['properties']['purge_at']   = [
			'type'        => 'string',
			'description' => 'Earliest time WordPress\'s daily trash cleanup may delete it permanently, ISO 8601 (UTC), or empty when unknown.',
		];

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'page', 'per_page', 'total', 'total_pages', 'hidden', 'items', 'trash' ],
			'properties'           => [
				'page'        => [ 'type' => 'integer' ],
				'per_page'    => [ 'type' => 'integer' ],
				'total'       => [
					'type'        => 'integer',
					'description' => 'Trashed attachments in all, including ones you cannot read.',
				],
				'total_pages' => [ 'type' => 'integer' ],
				'hidden'      => [
					'type'        => 'integer',
					'description' => 'Trashed attachments on this page you cannot read (not listed).',
				],
				'items'       => [
					'type'  => 'array',
					'items' => $item,
				],
				'trash'       => MediaTrash::settings_schema(),
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
			'idempotent'  => true,
		];
	}

	/**
	 * List one page of trashed attachments.
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

		$input = null === $input ? [] : $input;
		if ( ! is_array( $input ) || array_diff( array_keys( $input ), [ 'page', 'per_page' ] ) ) {
			return $this->invalid( __( 'Only page and per_page are accepted.', 'media-library-tools' ) );
		}
		$page     = $input['page'] ?? 1;
		$per_page = $input['per_page'] ?? self::DEFAULT_PER_PAGE;
		if ( ! is_int( $page ) || $page < 1 || $page > self::MAX_PAGE ) {
			return $this->invalid( __( 'page must be an integer from 1 to 10000.', 'media-library-tools' ) );
		}
		if ( ! is_int( $per_page ) || $per_page < 1 || $per_page > self::MAX_PER_PAGE ) {
			return $this->invalid( __( 'per_page must be an integer from 1 to 20.', 'media-library-tools' ) );
		}

		$query = new WP_Query(
			[
				'post_type'              => 'attachment',
				'post_status'            => 'trash',
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'orderby'                => [
					'modified' => 'DESC',
					'ID'       => 'DESC',
				],
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
			]
		);

		$items  = [];
		$hidden = 0;
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post || ! current_user_can( 'read_post', $post->ID ) ) {
				++$hidden;
				continue;
			}
			$times   = MediaTrash::trash_times( (int) $post->ID );
			$items[] = MediaPresenter::summary( $post ) + [
				'trashed_at' => $times['trashed'] > 0 ? gmdate( DATE_ATOM, $times['trashed'] ) : '',
				'purge_at'   => $times['purge'] > 0 ? gmdate( DATE_ATOM, $times['purge'] ) : '',
			];
		}

		return [
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) ceil( (int) $query->found_posts / $per_page ),
			'hidden'      => $hidden,
			'items'       => $items,
			'trash'       => MediaTrash::settings(),
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
