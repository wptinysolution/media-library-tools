<?php
/**
 * Ability: tsmlt/get-media-usage.
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

/**
 * Where up to 50 attachments are used, from the records the "Used Where"
 * scanner keeps (UsedWhereScanner::get_usage_stats()). Nothing is scanned
 * here and nothing is written: the scan status comes from UsageScanStatus,
 * because UsedWhereScanner::get_scan_status() can re-schedule a stalled
 * scan. Scans are started with tsmlt/start-usage-scan.
 *
 * `used` is true when a usage is recorded, false only when no usage is
 * recorded and the same scan was complete before and after the records
 * were read and its coverage is verified (it could check every usage
 * source) and the attachment was uploaded before that scan started, and
 * null otherwise (unknown). Same rules as the Used Where page
 * (UsageClassifier). `scan.complete` is true only in
 * that verified case, so a client can tell final from provisional results.
 *
 * Each usage gives the referring post's ID, type, current status and title.
 * A post the user cannot read, or whose type is no longer registered, is
 * counted in `hidden` instead; a post that no longer exists is skipped.
 * Site-wide usages (logo, icon, header or background image, menus, widgets,
 * term and site options) have post ID 0 and no title or status. Order
 * records from WooCommerce's order tables (no WordPress post) are counted as
 * hidden. Usage types outside the scanner's known list are reported as
 * "other". No permalinks, file paths, excerpts or raw records are returned.
 *
 * Registered with `meta.mcp.public` false: post titles, including drafts
 * and private posts, are site-internal, so the ability is offered only on
 * the dedicated Media Library Tools server.
 */
class GetMediaUsage extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Most attachments per request.
	 *
	 * @var int
	 */
	const MAX_IDS = 50;

	/**
	 * Most usages listed per attachment.
	 *
	 * @var int
	 */
	const MAX_USAGES = 100;

	/**
	 * Usages listed per attachment when the client sends no limit.
	 *
	 * @var int
	 */
	const DEFAULT_USAGES = 50;

	/**
	 * Attachment statuses this ability reports on (trashed media is not found).
	 *
	 * @var string[]
	 */
	const VISIBLE_STATUSES = [ 'inherit' ];

	/**
	 * Option holding the Used Where scan status.
	 *
	 * @var string
	 */
	const SCAN_STATUS_OPTION = UsageScanStatus::OPTION;

	/**
	 * Scan states the scanner records.
	 *
	 * @var string[]
	 */
	const SCAN_STATES = UsageScanStatus::STATES;

	/**
	 * Usage type reported for a type the scanner does not define.
	 *
	 * @var string
	 */
	const OTHER_USAGE_TYPE = 'other';

	/**
	 * Post type of site-wide usage records (post ID 0).
	 *
	 * @var string
	 */
	const SITEWIDE_POST_TYPE = 'site_settings';

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/get-media-usage';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get media usage', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Report where up to 50 attachments (by ID) are used on the site: as featured images, in post content or excerpts, in page builders, galleries and custom fields, and site-wide (logo, site icon, header or background image, menus, widgets, term and site options). Reads the records of the plugin\'s Used Where scan; it does not scan. used is true when a usage is recorded, false only when none is recorded and the last scan completed, and null when that is unknown. scan.complete is true only when these results come from one completed scan whose coverage is verified (see tsmlt/get-usage-scan-status coverage; for example, a scan made while WooCommerce was inactive is not); otherwise treat them as provisional (start a scan with tsmlt/start-usage-scan for complete data). Each usage gives the post ID (0 for site-wide), post type, status, current title and usage type; posts you cannot read are only counted in hidden. Read-only.', 'media-library-tools' );
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
			'required'             => [ 'ids' ],
			'properties'           => [
				'ids'        => [
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => self::MAX_IDS,
					'uniqueItems' => true,
					'items'       => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'description' => 'Attachment IDs, 1 to 50, each once.',
				],
				'max_usages' => [
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_USAGES,
					'default'     => self::DEFAULT_USAGES,
					'description' => 'Most usages listed per attachment (1-100, default 50). Counts are always complete.',
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
			'required'             => [ 'scan', 'items' ],
			'properties'           => [
				'scan'  => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => [ 'state', 'complete', 'processed', 'total', 'last_update' ],
					'properties'           => [
						'state'       => [
							'type'        => 'string',
							'enum'        => self::SCAN_STATES,
							'description' => 'State of the last Used Where scan.',
						],
						'complete'    => [
							'type'        => 'boolean',
							'description' => 'Whether the last scan finished.',
						],
						'processed'   => [
							'type'        => 'integer',
							'description' => 'Items the last scan processed.',
						],
						'total'       => [
							'type'        => 'integer',
							'description' => 'Items the last scan had to process.',
						],
						'last_update' => [
							'type'        => 'string',
							'description' => 'Site-local time the scan status last changed (Y-m-d H:i:s), or empty.',
						],
					],
				],
				'items' => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'used', 'total_usage', 'hidden', 'truncated', 'usages' ],
						'properties'           => [
							'id'          => [
								'type'        => 'integer',
								'description' => 'Attachment ID.',
							],
							'used'        => [
								'type'        => [ 'boolean', 'null' ],
								'description' => 'true: used; false: not used (scan complete); null: unknown.',
							],
							'total_usage' => [
								'type'        => 'integer',
								'description' => 'Recorded usages, listed and hidden.',
							],
							'hidden'      => [
								'type'        => 'integer',
								'description' => 'Usages not listed because you cannot read the post.',
							],
							'truncated'   => [
								'type'        => 'boolean',
								'description' => 'Whether more usages exist than max_usages.',
							],
							'usages'      => [
								'type'  => 'array',
								'items' => [
									'type'                 => 'object',
									'additionalProperties' => false,
									'required'             => [ 'post_id', 'post_type', 'post_status', 'title', 'usage_type', 'sitewide' ],
									'properties'           => [
										'post_id'     => [
											'type'        => 'integer',
											'description' => 'Post ID, or 0 for a site-wide usage.',
										],
										'post_type'   => [
											'type'        => 'string',
											'description' => 'Post type, or "site_settings" for a site-wide usage.',
										],
										'post_status' => [
											'type'        => [ 'string', 'null' ],
											'description' => 'Current post status; null for a site-wide usage.',
										],
										'title'       => [
											'type'        => [ 'string', 'null' ],
											'description' => 'Current post title; null for a site-wide usage.',
										],
										'usage_type'  => [
											'type'        => 'string',
											'description' => 'How the media is used, for example featured, content, elementor, meta, site_logo, widget; "other" when unknown.',
										],
										'sitewide'    => [
											'type'        => 'boolean',
											'description' => 'Whether the usage belongs to the site rather than one post.',
										],
									],
								],
							],
						],
					],
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
	 * Report where the attachments are used.
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

		$input = is_array( $input ) ? $input : [];

		// The schema is closed; refuse unexpected keys here too.
		if ( array_diff( array_keys( $input ), [ 'ids', 'max_usages' ] ) ) {
			return $this->invalid( __( 'Only ids and max_usages are accepted.', 'media-library-tools' ) );
		}

		$ids = $input['ids'] ?? null;
		if ( ! is_array( $ids ) || ! $ids || count( $ids ) > self::MAX_IDS || array_keys( $ids ) !== range( 0, count( $ids ) - 1 ) ) {
			return $this->invalid( __( 'ids must be a list of 1 to 50 attachment IDs.', 'media-library-tools' ) );
		}
		foreach ( $ids as $id ) {
			if ( ! is_int( $id ) || $id < 1 ) {
				return AbilityGuard::error(
					AbilityGuard::ERROR_INVALID_ATTACHMENT_ID,
					__( 'The attachment ID must be a positive integer.', 'media-library-tools' ),
					400
				);
			}
		}
		if ( count( array_unique( $ids ) ) !== count( $ids ) ) {
			return $this->invalid( __( 'ids must not repeat an ID.', 'media-library-tools' ) );
		}

		$max_usages = $input['max_usages'] ?? self::DEFAULT_USAGES;
		if ( ! is_int( $max_usages ) || $max_usages < 1 || $max_usages > self::MAX_USAGES ) {
			return $this->invalid( __( 'max_usages must be an integer from 1 to 100.', 'media-library-tools' ) );
		}

		// Every attachment first, so a partly valid list returns nothing.
		$attachments = [];
		foreach ( $ids as $id ) {
			$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );
			if ( is_wp_error( $attachment ) && AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND !== $attachment->get_error_code() && AbilityGuard::ERROR_FORBIDDEN !== $attachment->get_error_code() ) {
				return $attachment;
			}
			// A refused or trashed attachment is reported exactly like a missing one.
			if ( is_wp_error( $attachment ) || ! in_array( $attachment->post_status, self::VISIBLE_STATUSES, true ) ) {
				return $this->not_found( $id );
			}
			$attachments[] = $attachment;
		}

		$before = UsageScanStatus::read();
		$items  = [];
		foreach ( $attachments as $attachment ) {
			$items[] = $this->item( $attachment, $max_usages );
		}
		// Read again: a scan that started (or finished) while the records
		// were read makes "no usage" unreliable. Not used is reported only
		// when the same scan was complete before and after.
		$after = UsageScanStatus::read();
		// Also unknown when the scan could not check every usage source (for
		// example WooCommerce was inactive): see UsageScanStatus coverage.
		$verified = $before['complete'] && $after['complete'] && $before['scan_id'] === $after['scan_id'] && $before['started_at'] === $after['started_at']
			&& $before['coverage']['verified'] && $after['coverage']['verified'];
		foreach ( $items as $index => $item ) {
			if ( 0 === $item['total_usage'] ) {
				// An attachment uploaded after the scan started was not covered by it.
				$items[ $index ]['used'] = $verified && UsageClassifier::covered( $attachments[ $index ], $after ) ? false : null;
			}
		}

		return [
			'scan'  => $this->scan_block( $verified ? $after : array_merge( $after, [ 'complete' => false ] ) ),
			'items' => $items,
		];
	}

	/**
	 * A status from UsageScanStatus::read() in this ability's output shape.
	 *
	 * @param array $status Status.
	 *
	 * @return array
	 */
	private function scan_block( array $status ): array {
		return [
			'state'       => $status['state'],
			'complete'    => $status['complete'],
			'processed'   => $status['processed'],
			'total'       => $status['total'],
			'last_update' => $status['last_update'],
		];
	}

	/**
	 * Usage of one attachment.
	 *
	 * `used` is true when a usage is recorded and null otherwise; execute()
	 * turns null into false when the scan is verified complete.
	 *
	 * @param WP_Post $attachment Attachment.
	 * @param int     $max_usages Most usages listed.
	 *
	 * @return array
	 */
	private function item( WP_Post $attachment, int $max_usages ): array {
		$stats  = UsedWhereScanner::instance()->get_usage_stats( (int) $attachment->ID );
		$usages = [];
		$hidden = 0;

		foreach ( (array) ( $stats['by_post'] ?? [] ) as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}
			$usage = $this->usage( $record );
			if ( null === $usage ) {
				continue;
			}
			if ( false === $usage ) {
				++$hidden;
				continue;
			}
			$usages[] = $usage;
		}

		$total = count( $usages ) + $hidden;

		return [
			'id'          => (int) $attachment->ID,
			'used'        => $total > 0 ? true : null,
			'total_usage' => $total,
			'hidden'      => $hidden,
			'truncated'   => count( $usages ) > $max_usages,
			'usages'      => array_slice( $usages, 0, $max_usages ),
		];
	}

	/**
	 * One usage record as returned: an array, false when the user may not
	 * see the post (counted as hidden), or null when it no longer exists.
	 *
	 * @param array $record Record from get_usage_stats()['by_post'].
	 *
	 * @return array|false|null
	 */
	private function usage( array $record ) {
		$post_id    = isset( $record['post_id'] ) && is_numeric( $record['post_id'] ) ? (int) $record['post_id'] : -1;
		$usage_type = $this->usage_type( $record['usage_type'] ?? '' );

		if ( 0 === $post_id ) {
			return [
				'post_id'     => 0,
				'post_type'   => self::SITEWIDE_POST_TYPE,
				'post_status' => null,
				'title'       => null,
				'usage_type'  => $usage_type,
				'sitewide'    => true,
			];
		}
		if ( $post_id < 0 ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			// An order kept in WooCommerce's own tables has no post to check
			// read access against: count it, never list it.
			$type = (string) ( $record['post_type'] ?? '' );
			if ( 0 === strpos( $type, 'shop_order' ) && function_exists( 'wc_get_order' ) && wc_get_order( $post_id ) ) {
				return false;
			}
			return null;
		}

		// read_post needs a registered post type; anything else stays hidden.
		if ( ! post_type_exists( $post->post_type ) || ! current_user_can( 'read_post', $post_id ) ) {
			return false;
		}

		return [
			'post_id'     => $post_id,
			'post_type'   => (string) $post->post_type,
			'post_status' => (string) $post->post_status,
			'title'       => Fns::prepare_text_for_json( $post->post_title ),
			'usage_type'  => $usage_type,
			'sitewide'    => in_array( $usage_type, UsedWhereScanner::SITEWIDE_USAGE_TYPES, true ),
		];
	}

	/**
	 * A usage type the scanner defines, or "other".
	 *
	 * @param mixed $type Recorded type.
	 *
	 * @return string
	 */
	private function usage_type( $type ): string {
		$known = array_merge( UsedWhereScanner::OWNING_USAGE_TYPES, UsedWhereScanner::SITEWIDE_USAGE_TYPES, [ 'permalink', 'rendered' ] );
		return is_string( $type ) && in_array( $type, $known, true ) ? $type : self::OTHER_USAGE_TYPE;
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

	/**
	 * Error for an attachment that is missing, trashed or not readable.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return WP_Error
	 */
	private function not_found( int $id ): WP_Error {
		return AbilityGuard::error(
			AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND,
			/* translators: %d: attachment ID. */
			sprintf( __( 'No attachment exists with ID %d. Nothing was returned for the other IDs.', 'media-library-tools' ), $id ),
			404
		);
	}
}
