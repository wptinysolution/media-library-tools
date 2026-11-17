<?php
/**
 * Ability: tsmlt/list-media-usage.
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
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\UsedWhere\UsageClassifier;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Pages of media by usage, read-only: used, unused or unknown, with the
 * Used Where page's classification (UsageClassifier::query_args()) and,
 * for each item, the rule of tsmlt/get-media-usage (usage_state()): used
 * when a usage that still counts is recorded; unused only when none is and
 * a complete, verified scan covered it; otherwise unknown. An item whose
 * stored record does not match its list (a record left for posts that no
 * longer exist) is left out and counted in `excluded`; nothing is cleaned
 * up or rescanned.
 *
 * Needs a completed usage scan; the scan status is read before and after
 * the records, and a scan that changed meanwhile is refused (retry). Pages
 * come from SQL (only one page of IDs is loaded); attachments the user may
 * not read are left out and counted in `hidden`. No paths or error texts
 * are returned. Registered with `meta.mcp.public` false: offered only on
 * the dedicated Media Library Tools server.
 */
class ListMediaUsage extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Most items per page.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Items per page by default.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 20;

	/**
	 * Highest page accepted.
	 *
	 * @var int
	 */
	const MAX_PAGE = 10000;

	/**
	 * Error code: no completed usage scan.
	 *
	 * @var string
	 */
	const ERROR_SCAN_REQUIRED = 'tsmlt_usage_scan_required';

	/**
	 * Error code: the usage scan changed while the page was read.
	 *
	 * @var string
	 */
	const ERROR_SCAN_CHANGED = 'tsmlt_usage_scan_changed';

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/list-media-usage';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'List media by usage', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'List media by usage, one page at a time: status used (a usage is recorded), unused (no usage recorded, and a complete, verified usage scan covered it) or unknown (anything that cannot be called used or unused, for example media uploaded after the scan started or a scan whose coverage is not verified; never treat unknown as unused). Each item has its ID, title, file name, MIME type and usage status; used items add the usage types and the number of posts using them (details per item with tsmlt/get-media-usage), unknown items add the reason codes. Needs a completed usage scan: first start one with tsmlt/start-usage-scan, poll tsmlt/get-usage-scan-status until it is complete, then list. Use page and total_pages to go through all results. Read-only: never starts a scan or changes results.', 'media-library-tools' );
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
			'required'             => [ 'status' ],
			'properties'           => [
				'status'   => [
					'type'        => 'string',
					'enum'        => UsageClassifier::TABS,
					'description' => 'used, unused or unknown.',
				],
				'page'     => [
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_PAGE,
					'default'     => 1,
					'description' => 'Page, from 1.',
				],
				'per_page' => [
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_PER_PAGE,
					'default'     => self::DEFAULT_PER_PAGE,
					'description' => 'Items per page, 1 to 100.',
				],
			],
		];
	}

	/**
	 * @return array
	 */
	protected function get_output_schema(): array {
		$count = [
			'type'    => 'integer',
			'minimum' => 0,
		];
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'status', 'items', 'page', 'per_page', 'total', 'total_pages', 'hidden', 'excluded', 'scan' ],
			'properties'           => [
				'status'      => [
					'type' => 'string',
					'enum' => UsageClassifier::TABS,
				],
				'items'       => [
					'type'  => 'array',
					'items' => self::item_schema(),
				],
				'page'        => $count,
				'per_page'    => $count,
				'total'       => $count + [ 'description' => 'Media in this list (as the Used Where page counts them).' ],
				'total_pages' => $count,
				'hidden'      => $count + [ 'description' => 'Media on this page you cannot read; left out.' ],
				'excluded'    => $count + [ 'description' => 'Media on this page whose stored usage record does not match this list (for example a record left for posts that no longer exist); left out.' ],
				'scan'        => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => [ 'scan_id', 'complete', 'verified', 'started_at', 'gaps' ],
					'properties'           => [
						'scan_id'    => [ 'type' => 'string' ],
						'complete'   => [ 'type' => 'boolean' ],
						'verified'   => [
							'type'        => 'boolean',
							'description' => 'Whether the scan could check every usage source; when false, no media is listed as unused.',
						],
						'started_at' => [ 'type' => 'string' ],
						'gaps'       => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
					],
				],
			],
		];
	}

	/**
	 * Schema of one item.
	 *
	 * @return array
	 */
	private static function item_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'id', 'title', 'filename', 'mime_type', 'usage_status' ],
			'properties'           => [
				'id'              => [ 'type' => 'integer' ],
				'title'           => [ 'type' => 'string' ],
				'filename'        => [
					'type'        => 'string',
					'description' => 'File name, without folders.',
				],
				'mime_type'       => [ 'type' => 'string' ],
				'usage_status'    => [
					'type' => 'string',
					'enum' => UsageClassifier::TABS,
				],
				'usage_types'     => [
					'type'        => 'object',
					'description' => 'Used items: recorded usages by type (featured, content, gallery, builder, meta, ...), with counts.',
				],
				'used_in_posts'   => [
					'type'        => 'integer',
					'description' => 'Used items: distinct posts recording a usage (site-wide usages not counted).',
				],
				'unknown_reasons' => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => 'Unknown items: reason codes (for example not_covered, coverage_not_recorded, woocommerce_inactive).',
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
	 * List one page.
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

		$criteria = self::sanitize_input( $input );
		if ( is_wp_error( $criteria ) ) {
			return $criteria;
		}

		$before = UsageScanStatus::read();
		if ( ! $before['complete'] ) {
			return AbilityGuard::error(
				self::ERROR_SCAN_REQUIRED,
				__( 'No completed usage scan, so media cannot be listed by usage yet. Start one with tsmlt/start-usage-scan, poll tsmlt/get-usage-scan-status until it is complete, then list again.', 'media-library-tools' ),
				409
			);
		}

		$context = UsageClassifier::context();
		$args    = UsageClassifier::query_args( $criteria['status'], '', $context );
		$ids     = [];
		$total   = 0;
		if ( null !== $args ) {
			$query = new WP_Query(
				array_merge(
					$args,
					[
						'fields'                 => 'ids',
						'posts_per_page'         => $criteria['per_page'],
						'paged'                  => $criteria['page'],
						'orderby'                => 'ID',
						'order'                  => 'ASC',
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					]
				)
			);
			$ids   = array_map( 'intval', $query->posts );
			$total = (int) $query->found_posts;
		}

		_prime_post_caches( $ids, false, true );
		$records = [];
		$hidden  = 0;
		foreach ( $ids as $id ) {
			$attachment = get_post( $id );
			if ( ! $attachment instanceof WP_Post || ! AbilityGuard::can_access_attachment( $attachment, 'read_post' ) ) {
				++$hidden;
				continue;
			}
			$records[ $id ] = [
				'post'     => $attachment,
				'recorded' => UsageClassifier::has_recorded_usage( get_post_meta( $id, UsedWhereScanner::META_KEY, true ) ),
			];
		}

		$after = UsageScanStatus::read();
		if ( ! $after['complete'] || $before['scan_id'] !== $after['scan_id'] || $before['started_at'] !== $after['started_at'] ) {
			return AbilityGuard::error(
				self::ERROR_SCAN_CHANGED,
				__( 'The usage scan changed while this page was read. Check tsmlt/get-usage-scan-status, then list again.', 'media-library-tools' ),
				409
			);
		}
		$verified = UsageClassifier::verified_between( $before, $after );

		$items    = [];
		$excluded = 0;
		foreach ( $records as $id => $record ) {
			$state  = UsageClassifier::usage_state( $record['post'], $record['recorded'], $verified, $after );
			$status = true === $state ? 'used' : ( false === $state ? 'unused' : 'unknown' );
			if ( $status !== $criteria['status'] ) {
				++$excluded;
				continue;
			}
			$items[] = self::present( $record['post'], $status, $context );
		}

		return [
			'status'      => $criteria['status'],
			'items'       => $items,
			'page'        => $criteria['page'],
			'per_page'    => $criteria['per_page'],
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $criteria['per_page'] ),
			'hidden'      => $hidden,
			'excluded'    => $excluded,
			'scan'        => [
				'scan_id'    => (string) $after['scan_id'],
				'complete'   => (bool) $after['complete'],
				'verified'   => $verified && ! $after['stalled'] && '' !== $after['started_at'],
				'started_at' => (string) $after['started_at'],
				'gaps'       => array_values( array_map( 'strval', (array) $after['coverage']['gaps'] ) ),
			],
		];
	}

	/**
	 * Validate and normalise the input.
	 *
	 * @param mixed $input Raw input.
	 *
	 * @return array{status: string, page: int, per_page: int}|WP_Error
	 */
	private static function sanitize_input( $input ) {
		$input = null === $input ? [] : $input;
		$page  = $input['page'] ?? 1;
		$size  = $input['per_page'] ?? self::DEFAULT_PER_PAGE;
		if ( ! is_array( $input ) || array_diff( array_keys( $input ), [ 'status', 'page', 'per_page' ] )
			|| ! in_array( $input['status'] ?? null, UsageClassifier::TABS, true )
			|| ! is_int( $page ) || $page < 1 || $page > self::MAX_PAGE
			|| ! is_int( $size ) || $size < 1 || $size > self::MAX_PER_PAGE ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				__( 'status must be used, unused or unknown; page 1 to 10000; per_page 1 to 100.', 'media-library-tools' ),
				400
			);
		}
		return [
			'status'   => $input['status'],
			'page'     => $page,
			'per_page' => $size,
		];
	}

	/**
	 * One item as the list shows it: safe fields only (no file path,
	 * metadata or error text).
	 *
	 * @param WP_Post $attachment Attachment.
	 * @param string  $status     Usage status.
	 * @param array   $context    UsageClassifier::context().
	 *
	 * @return array
	 */
	private static function present( WP_Post $attachment, string $status, array $context ): array {
		$file = get_post_meta( $attachment->ID, '_wp_attached_file', true );
		$item = [
			'id'           => (int) $attachment->ID,
			'title'        => Fns::prepare_text_for_json( $attachment->post_title ),
			'filename'     => is_string( $file ) && '' !== $file ? wp_basename( $file ) : '',
			'mime_type'    => (string) $attachment->post_mime_type,
			'usage_status' => $status,
		];
		if ( 'used' === $status ) {
			$stats                 = UsedWhereScanner::instance()->get_usage_stats( $attachment->ID );
			$item['usage_types']   = (object) array_map( 'intval', (array) $stats['by_type'] );
			$posts                 = array_filter( array_map( 'intval', array_column( (array) $stats['by_post'], 'post_id' ) ) );
			$item['used_in_posts'] = count( array_unique( $posts ) );
		} elseif ( 'unknown' === $status ) {
			$item['unknown_reasons'] = array_values( array_unique( array_map( 'strval', array_column( UsageClassifier::diagnose( $attachment, $context )['reasons'], 'code' ) ) ) );
		}
		return $item;
	}
}
