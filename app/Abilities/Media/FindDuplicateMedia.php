<?php
/**
 * Ability: tsmlt/find-duplicate-media.
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
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Duplicate\DuplicateScanner;
use TinySolutions\mlt\Modules\UsedWhere\UsageClassifier;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;

/**
 * List groups of identical media files found by the duplicate scan, one page
 * at a time. Read-only: it reads the duplicate scan's results and state and
 * never scans, purges stale rows, schedules anything, writes, or touches the
 * filesystem.
 *
 * A group is a file hash shared by at least two distinct attachments still
 * in the media library. Each listed attachment needs `read_post`; others are
 * only counted in `hidden`. Usage comes from the Used Where scan, by the
 * rules of tsmlt/get-media-usage (UsageClassifier). Posts, usage records and
 * referenced posts are read in batches per page, so a page costs a fixed
 * number of queries.
 */
class FindDuplicateMedia extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Default groups per page.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 10;

	/**
	 * Most groups per page.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 20;

	/**
	 * Highest page number accepted. With at most MAX_PER_PAGE groups per
	 * page, the offset stays far below any integer limit.
	 *
	 * @var int
	 */
	const MAX_PAGE = 10000;

	/**
	 * Most attachments listed per group.
	 *
	 * @var int
	 */
	const MAX_ITEMS = 20;

	/**
	 * Duplicate scan states.
	 *
	 * @var string[]
	 */
	const STATES = [ 'idle', 'running', 'done', 'cancelled' ];

	/**
	 * Error code: the duplicate scan's results could not be read.
	 *
	 * @var string
	 */
	const ERROR_READ_FAILED = 'tsmlt_duplicate_read_failed';

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
		return 'tsmlt/find-duplicate-media';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Find duplicate media', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'List groups of identical media files, largest groups first, one page at a time (use page and has_more). Results come from the plugin\'s duplicate scan (Media Library Tools > Duplicates) and reflect its last run: scan.complete is false while it runs or after it was cancelled, so treat those results as provisional; this tool does not start a scan, and attachments added or changed since the scan are not covered. Two files are duplicates when their bytes are identical (for large images, the scaled file WordPress keeps); re-encoded or resized copies are not detected. Each group gives the file size and, per attachment you can read, its ID, title, alt text, caption, MIME type, URL, upload date, parent post (parent_id, when you can read it) and usage from the Used Where scan: used, unused (only after a complete, verified usage scan), or unknown (see tsmlt/get-media-usage). Attachments you cannot read are only counted in hidden; at most 20 are listed per group (truncated). Read-only: nothing is merged, deleted or changed.', 'media-library-tools' );
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
					'description' => 'Groups per page (1-20, default 10).',
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
		$item                            = MediaPresenter::summary_schema();
		$item['required']                = array_merge( $item['required'], [ 'parent_id', 'usage' ] );
		$item['properties']['parent_id'] = [
			'type'        => [ 'integer', 'null' ],
			'description' => 'Parent post ("Uploaded to"), or null when there is none or you cannot read it.',
		];
		$item['properties']['usage']     = [
			'type'        => 'string',
			'enum'        => [ 'used', 'unused', 'unknown' ],
			'description' => 'From the Used Where scan: used (a usage is recorded), unused (none recorded, and a complete, verified usage scan covered it) or unknown.',
		];

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'scan', 'page', 'per_page', 'has_more', 'groups' ],
			'properties'           => [
				'scan'     => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => [ 'state', 'processed', 'total', 'started_at', 'updated_at', 'complete' ],
					'properties'           => [
						'state'      => [
							'type' => 'string',
							'enum' => self::STATES,
						],
						'processed'  => [
							'type'        => 'integer',
							'description' => 'Attachments the duplicate scan has gone through.',
						],
						'total'      => [
							'type'        => 'integer',
							'description' => 'Attachments the duplicate scan covers.',
						],
						'started_at' => [
							'type'        => 'string',
							'description' => 'When the duplicate scan started, ISO 8601 (UTC), or empty.',
						],
						'updated_at' => [
							'type'        => 'string',
							'description' => 'Last progress of the duplicate scan, ISO 8601 (UTC), or empty.',
						],
						'complete'   => [
							'type'        => 'boolean',
							'description' => 'True only when the last duplicate scan finished.',
						],
					],
				],
				'page'     => [ 'type' => 'integer' ],
				'per_page' => [ 'type' => 'integer' ],
				'has_more' => [
					'type'        => 'boolean',
					'description' => 'Whether there is a next page.',
				],
				'groups'   => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'file_size', 'item_count', 'hidden', 'truncated', 'items' ],
						'properties'           => [
							'file_size'  => [
								'type'        => 'integer',
								'description' => 'Size of the file in bytes, as scanned.',
							],
							'item_count' => [
								'type'        => 'integer',
								'description' => 'Attachments in the group: the ones you can read (listed, up to 20) plus hidden.',
							],
							'hidden'     => [
								'type'        => 'integer',
								'description' => 'Attachments in the group you cannot read (never listed).',
							],
							'truncated'  => [
								'type'        => 'boolean',
								'description' => 'True when you can read more than 20 of them.',
							],
							'items'      => [
								'type'  => 'array',
								'items' => $item,
							],
						],
					],
				],
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
	 * List one page of duplicate groups.
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

		$scanner = DuplicateScanner::instance();
		$groups  = $scanner->find_duplicate_groups( $page, $per_page );
		$members = null === $groups ? null : $scanner->find_duplicate_members( array_column( array_slice( $groups, 0, $per_page ), 'file_hash' ) );
		if ( null === $members ) {
			return AbilityGuard::error( self::ERROR_READ_FAILED, __( 'The duplicate scan results could not be read. Try again.', 'media-library-tools' ), 500 );
		}

		$listed = $this->groups( array_slice( $groups, 0, $per_page ), $members );
		if ( null === $listed ) {
			return AbilityGuard::error( self::ERROR_READ_FAILED, __( 'The duplicate scan results could not be read. Try again.', 'media-library-tools' ), 500 );
		}

		return [
			'scan'     => $this->scan_block( $scanner->get_progress() ),
			'page'     => $page,
			'per_page' => $per_page,
			'has_more' => count( $groups ) > $per_page,
			'groups'   => $listed,
		];
	}

	/**
	 * The duplicate scan's state in this ability's output shape.
	 *
	 * @param array $progress DuplicateScanner::get_progress().
	 *
	 * @return array
	 */
	private function scan_block( array $progress ): array {
		$state = in_array( $progress['status'] ?? '', self::STATES, true ) ? $progress['status'] : 'idle';
		return [
			'state'      => $state,
			'processed'  => max( 0, (int) ( $progress['offset'] ?? 0 ) ),
			'total'      => max( 0, (int) ( $progress['total'] ?? 0 ) ),
			'started_at' => self::time_value( $progress['started_at'] ?? 0 ),
			'updated_at' => self::time_value( $progress['updated_at'] ?? 0 ),
			'complete'   => 'done' === $state,
		];
	}

	/**
	 * A Unix time as ISO 8601 (UTC), or '' when unset.
	 *
	 * @param mixed $time Unix time.
	 *
	 * @return string
	 */
	private static function time_value( $time ): string {
		$time = (int) $time;
		return $time > 0 ? gmdate( DATE_ATOM, $time ) : '';
	}

	/**
	 * Groups with their readable attachments listed and the rest counted.
	 *
	 * @param array[] $groups  Groups (`file_hash`, `file_size`).
	 * @param array   $members Attachment IDs by hash.
	 *
	 * @return array[]|null Null when usage could not be read.
	 */
	private function groups( array $groups, array $members ): ?array {
		// Every member of the page in one query, then their parents in one
		// more: read_post on an attachment reads its parent post.
		$ids = array_merge( [], ...array_values( $members ) );
		_prime_post_caches( $ids, false, false );
		$parents = [];
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post instanceof WP_Post && $post->post_parent > 0 ) {
				$parents[] = (int) $post->post_parent;
			}
		}
		_prime_post_caches( array_values( array_unique( $parents ) ), false, false );

		$listed = [];
		$out    = [];
		foreach ( $groups as $group ) {
			$readable = [];
			$hidden   = 0;
			foreach ( $members[ (string) $group['file_hash'] ] ?? [] as $id ) {
				$post = get_post( $id );
				if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
					continue;
				}
				if ( current_user_can( 'read_post', $id ) ) {
					$readable[] = $post;
				} else {
					++$hidden;
				}
			}
			$shown  = array_slice( $readable, 0, self::MAX_ITEMS );
			$listed = array_merge( $listed, $shown );
			$out[]  = [
				'file_size'  => max( 0, (int) $group['file_size'] ),
				'item_count' => count( $readable ) + $hidden,
				'hidden'     => $hidden,
				'truncated'  => count( $readable ) > self::MAX_ITEMS,
				'items'      => $shown,
			];
		}

		$items = $this->items( $listed );
		if ( null === $items ) {
			return null;
		}
		foreach ( $out as $index => $group ) {
			$out[ $index ]['items'] = array_map(
				static function ( WP_Post $post ) use ( $items ) {
					return $items[ $post->ID ];
				},
				$group['items']
			);
		}
		return $out;
	}

	/**
	 * Listed attachments as output items, read in batches: their usage
	 * records in one query, the posts their usages point to in one more
	 * (their parents are already cached by groups()). The usage scan status
	 * is read before and after the records; a scan that changed meanwhile
	 * makes "no usage" unknown. A failed read of the records fails the
	 * whole call: an empty record must never be mistaken for no usage.
	 *
	 * @param WP_Post[] $attachments Listed attachments.
	 *
	 * @return array<int, array>|null Items by attachment ID; null when the
	 *                               usage records or referenced posts could
	 *                               not be read (a usage must never look
	 *                               missing).
	 */
	private function items( array $attachments ): ?array {
		global $wpdb;
		if ( empty( $attachments ) ) {
			return [];
		}
		$ids = array_map( 'intval', wp_list_pluck( $attachments, 'ID' ) );

		$before  = UsageScanStatus::read();
		$queries = (int) $wpdb->num_queries;
		update_meta_cache( 'post', $ids );
		if ( (int) $wpdb->num_queries > $queries && '' !== (string) $wpdb->last_error ) {
			// The read failed, and WordPress cached the records as empty:
			// drop that, so nothing (here or later) takes it as no usage.
			foreach ( $ids as $id ) {
				wp_cache_delete( $id, 'post_meta' );
			}
			return null;
		}
		$usages = [];
		$posts  = [];
		foreach ( $ids as $id ) {
			$usages[ $id ] = get_post_meta( $id, UsedWhereScanner::META_KEY, true );
			foreach ( is_array( $usages[ $id ] ) ? $usages[ $id ] : [] as $usage ) {
				if ( is_array( $usage ) && isset( $usage['post_id'] ) && is_numeric( $usage['post_id'] ) && (int) $usage['post_id'] > 0 ) {
					$posts[] = (int) $usage['post_id'];
				}
			}
		}
		$existing = self::existing_posts( $posts );
		if ( null === $existing ) {
			return null;
		}
		$after    = UsageScanStatus::read();
		$verified = UsageClassifier::verified_between( $before, $after );

		$items = [];
		foreach ( $attachments as $attachment ) {
			$state                    = UsageClassifier::usage_state( $attachment, UsageClassifier::has_recorded_usage( $usages[ $attachment->ID ], $existing ), $verified, $after );
			$items[ $attachment->ID ] = MediaPresenter::summary( $attachment ) + [
				'parent_id' => self::readable_parent( $attachment ),
				'usage'     => true === $state ? 'used' : ( false === $state ? 'unused' : 'unknown' ),
			];
		}
		return $items;
	}

	/**
	 * Which of these posts exist, in one query per 1,000 IDs.
	 *
	 * @param int[] $ids Post IDs.
	 *
	 * @return array<int, true>|null Existing IDs (keys); null on a database
	 *                               error.
	 */
	private static function existing_posts( array $ids ): ?array {
		global $wpdb;
		$existing = [];
		foreach ( array_chunk( array_values( array_unique( $ids ) ), 1000 ) as $chunk ) {
			try {
				$rows = Fns::DB()->select( 'ID' )->from( 'posts' )->whereIn( 'ID', ...$chunk )->get();
			} catch ( \Throwable $e ) {
				return null;
			}
			if ( '' !== (string) $wpdb->last_error || ! is_array( $rows ) ) {
				return null;
			}
			foreach ( $rows as $row ) {
				$existing[ (int) $row['ID'] ] = true;
			}
		}
		return $existing;
	}

	/**
	 * An attachment's parent post ID, when the parent exists and the user can
	 * read it; otherwise null.
	 *
	 * @param WP_Post $attachment Attachment.
	 *
	 * @return int|null
	 */
	private static function readable_parent( WP_Post $attachment ): ?int {
		$parent_id = (int) $attachment->post_parent;
		if ( $parent_id < 1 || ! get_post( $parent_id ) instanceof WP_Post || ! current_user_can( 'read_post', $parent_id ) ) {
			return null;
		}
		return $parent_id;
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
