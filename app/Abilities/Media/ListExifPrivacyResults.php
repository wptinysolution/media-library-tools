<?php
/**
 * Ability: tsmlt/list-exif-privacy-results.
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
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\ExifData\ExifDataReader;
use TinySolutions\mlt\Modules\ExifData\ExifScanner;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Pages of media by EXIF privacy result, from the per-image results the
 * completed EXIF scan run recorded (ExifScanner::PRIVACY_META_KEY), filtered
 * and paged in SQL. Results of earlier scan runs are never listed; media the
 * run did not reach is `not_scanned`, never safe. Each item reports which
 * categories are present (gps, camera, date_taken, author, copyright), never
 * their values: no coordinates, names or dates. No paths.
 *
 * Read-only: never starts a scan or reads files. Registered with
 * `meta.mcp.public` false: offered only on the dedicated Media Library Tools
 * server.
 */
class ListExifPrivacyResults extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Largest page.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Default page size.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 20;

	/**
	 * Highest page number.
	 *
	 * @var int
	 */
	const MAX_PAGE = 10000;

	/**
	 * Listing for media the scan run has no result for.
	 *
	 * @var string
	 */
	const NOT_SCANNED = 'not_scanned';

	/**
	 * Error code: no completed scan run with privacy results.
	 *
	 * @var string
	 */
	const ERROR_SCAN_REQUIRED = 'tsmlt_exif_scan_required';

	/**
	 * Error code: the scan changed while the page was read.
	 *
	 * @var string
	 */
	const ERROR_SCAN_CHANGED = 'tsmlt_exif_scan_changed';

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * Results that can be listed.
	 *
	 * @return string[]
	 */
	public static function results(): array {
		return array_merge( ExifScanner::PRIVACY_RESULTS, [ self::NOT_SCANNED ] );
	}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/list-exif-privacy-results';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'List EXIF privacy results', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'List media by the result of the completed EXIF privacy scan, one page at a time. result sensitive (default) lists media whose EXIF holds location (GPS), camera make or model, date taken, author or copyright data, with the categories found (never their values or coordinates); category narrows it to one category, for example gps. Other results: exif_no_sensitive, no_exif, unsupported (a format whose EXIF is not read: anything but JPEG and TIFF, WebP included), missing_file, unreadable, no_extension (the server cannot read EXIF), not_scanned (not reached by the scan, for example uploaded after it started). Media not listed as sensitive is not necessarily safe: check the other results. Requires a completed scan: start it with tsmlt/start-exif-privacy-scan and poll tsmlt/get-exif-privacy-scan-status. Read-only.', 'media-library-tools' );
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
			'default'              => [],
			'properties'           => [
				'result'   => [
					'type'        => 'string',
					'enum'        => self::results(),
					'default'     => 'sensitive',
					'description' => 'Which result to list.',
				],
				'category' => [
					'type'        => 'string',
					'enum'        => ExifDataReader::PRIVACY_CATEGORIES,
					'description' => 'Only media with this category (result sensitive only).',
				],
				'page'     => [
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => self::MAX_PAGE,
					'default' => 1,
				],
				'per_page' => [
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => self::MAX_PER_PAGE,
					'default' => self::DEFAULT_PER_PAGE,
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
			'required'             => [ 'result', 'category', 'items', 'page', 'per_page', 'total', 'total_pages', 'hidden', 'excluded', 'scan' ],
			'properties'           => [
				'result'      => [
					'type' => 'string',
					'enum' => self::results(),
				],
				'category'    => [
					'type'        => 'string',
					'description' => 'The category filter, or empty.',
				],
				'items'       => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'title', 'filename', 'mime_type', 'result', 'categories' ],
						'properties'           => [
							'id'         => [ 'type' => 'integer' ],
							'title'      => [ 'type' => 'string' ],
							'filename'   => [
								'type'        => 'string',
								'description' => 'File name only, no folders.',
							],
							'mime_type'  => [ 'type' => 'string' ],
							'result'     => [
								'type' => 'string',
								'enum' => self::results(),
							],
							'categories' => [
								'type'        => 'array',
								'items'       => [
									'type' => 'string',
									'enum' => ExifDataReader::PRIVACY_CATEGORIES,
								],
								'description' => 'Sensitive categories present in the EXIF (presence only).',
							],
						],
					],
				],
				'page'        => [ 'type' => 'integer' ],
				'per_page'    => [ 'type' => 'integer' ],
				'total'       => [
					'type'        => 'integer',
					'description' => 'Media with this result, across all pages.',
				],
				'total_pages' => [ 'type' => 'integer' ],
				'hidden'      => [
					'type'        => 'integer',
					'description' => 'Media on this page you cannot read; left out.',
				],
				'excluded'    => [
					'type'        => 'integer',
					'description' => 'Media on this page whose stored result changed while it was read; left out.',
				],
				'scan'        => GetExifPrivacyScanStatus::schema(),
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

		$scanner = ExifScanner::instance();
		$before  = $scanner->get_progress();
		$run     = GetExifPrivacyScanStatus::run( $before );
		if ( 'done' !== $before['status'] || '' === $run ) {
			return AbilityGuard::error(
				self::ERROR_SCAN_REQUIRED,
				__( 'No completed EXIF privacy scan, so there are no results to list yet. Start one with tsmlt/start-exif-privacy-scan, poll tsmlt/get-exif-privacy-scan-status until it is complete, then list again.', 'media-library-tools' ),
				409
			);
		}

		$query = new WP_Query(
			[
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'fields'                 => 'ids',
				'posts_per_page'         => $criteria['per_page'],
				'paged'                  => $criteria['page'],
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'meta_query'             => self::meta_query( $run, $criteria['result'], $criteria['category'] ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filtered and paged in SQL.
				'no_found_rows'          => false,
				'update_post_term_cache' => false,
			]
		);
		$ids   = array_map( 'intval', $query->posts );
		$total = (int) $query->found_posts;

		$items    = [];
		$hidden   = 0;
		$excluded = 0;
		foreach ( $ids as $id ) {
			$attachment = get_post( $id );
			if ( ! $attachment instanceof WP_Post || ! AbilityGuard::can_access_attachment( $attachment, 'read_post' ) ) {
				++$hidden;
				continue;
			}
			$parsed = ExifScanner::parse_privacy( get_post_meta( $id, ExifScanner::PRIVACY_META_KEY, true ), $run );
			if ( ! self::matches( $parsed, $criteria['result'], $criteria['category'] ) ) {
				++$excluded;
				continue;
			}
			$items[] = self::present( $attachment, $criteria['result'], $parsed ? $parsed['categories'] : [] );
		}

		$after = $scanner->get_progress();
		if ( 'done' !== $after['status'] || GetExifPrivacyScanStatus::run( $after ) !== $run ) {
			return AbilityGuard::error(
				self::ERROR_SCAN_CHANGED,
				__( 'The EXIF scan changed while this page was read. Check tsmlt/get-exif-privacy-scan-status, then list again.', 'media-library-tools' ),
				409
			);
		}

		return [
			'result'      => $criteria['result'],
			'category'    => $criteria['category'],
			'items'       => $items,
			'page'        => $criteria['page'],
			'per_page'    => $criteria['per_page'],
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $criteria['per_page'] ),
			'hidden'      => $hidden,
			'excluded'    => $excluded,
			'scan'        => GetExifPrivacyScanStatus::present( $after ),
		];
	}

	/**
	 * The meta query of a listing. Stored values are
	 * "{run}:{result}|{category}|..." (ExifScanner::privacy_value()); the
	 * run ID is hex and results and categories are fixed words, so the
	 * patterns hold no user text.
	 *
	 * @param string $run      Scan run ID.
	 * @param string $result   Result.
	 * @param string $category Category, or ''.
	 *
	 * @return array
	 */
	private static function meta_query( string $run, string $result, string $category ): array {
		if ( self::NOT_SCANNED === $result ) {
			return [
				'relation' => 'OR',
				[
					'key'     => ExifScanner::PRIVACY_META_KEY,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => ExifScanner::PRIVACY_META_KEY,
					'value'   => '^' . $run . ':',
					'compare' => 'NOT REGEXP',
				],
			];
		}
		$pattern = '^' . $run . ':' . $result . '[|]';
		if ( '' !== $category ) {
			$pattern .= '(.*[|])?' . $category . '[|]';
		}
		return [
			[
				'key'     => ExifScanner::PRIVACY_META_KEY,
				'value'   => $pattern,
				'compare' => 'REGEXP',
			],
		];
	}

	/**
	 * Whether an item's stored result (read after the query) still matches
	 * the listing.
	 *
	 * @param array|null $parsed   ExifScanner::parse_privacy() result.
	 * @param string     $result   Result listed.
	 * @param string     $category Category, or ''.
	 *
	 * @return bool
	 */
	private static function matches( ?array $parsed, string $result, string $category ): bool {
		if ( self::NOT_SCANNED === $result ) {
			return null === $parsed;
		}
		return null !== $parsed && $parsed['result'] === $result
			&& ( '' === $category || in_array( $category, $parsed['categories'], true ) );
	}

	/**
	 * Validate and normalise the input.
	 *
	 * @param mixed $input Raw input.
	 *
	 * @return array{result: string, category: string, page: int, per_page: int}|WP_Error
	 */
	private static function sanitize_input( $input ) {
		$input    = null === $input ? [] : $input;
		$result   = is_array( $input ) ? ( $input['result'] ?? 'sensitive' ) : null;
		$category = is_array( $input ) ? ( $input['category'] ?? '' ) : null;
		$page     = is_array( $input ) ? ( $input['page'] ?? 1 ) : null;
		$size     = is_array( $input ) ? ( $input['per_page'] ?? self::DEFAULT_PER_PAGE ) : null;
		if ( ! is_array( $input ) || array_diff( array_keys( $input ), [ 'result', 'category', 'page', 'per_page' ] )
			|| ! in_array( $result, self::results(), true )
			|| ( '' !== $category && ( 'sensitive' !== $result || ! in_array( $category, ExifDataReader::PRIVACY_CATEGORIES, true ) ) )
			|| ! is_int( $page ) || $page < 1 || $page > self::MAX_PAGE
			|| ! is_int( $size ) || $size < 1 || $size > self::MAX_PER_PAGE ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				__( 'result must be one of the listed results; category (gps, camera, date_taken, author or copyright) only with result sensitive; page 1 to 10000; per_page 1 to 100.', 'media-library-tools' ),
				400
			);
		}
		return [
			'result'   => $result,
			'category' => $category,
			'page'     => $page,
			'per_page' => $size,
		];
	}

	/**
	 * One item as the list shows it: safe fields only (no file path, EXIF
	 * value or error text).
	 *
	 * @param WP_Post  $attachment Attachment.
	 * @param string   $result     Result.
	 * @param string[] $categories Categories found.
	 *
	 * @return array
	 */
	private static function present( WP_Post $attachment, string $result, array $categories ): array {
		$file = get_post_meta( $attachment->ID, '_wp_attached_file', true );
		return [
			'id'         => (int) $attachment->ID,
			'title'      => Fns::prepare_text_for_json( $attachment->post_title ),
			'filename'   => is_string( $file ) && '' !== $file ? wp_basename( $file ) : '',
			'mime_type'  => (string) $attachment->post_mime_type,
			'result'     => $result,
			'categories' => $categories,
		];
	}
}
