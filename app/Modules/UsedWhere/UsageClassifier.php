<?php
/**
 * Used / unused / unknown classification for the Used Where page.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\UsedWhere;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Helpers\Fns;
use WP_Post;

/**
 * Classifies attachments the way tsmlt/get-media-usage does, and explains
 * the unknown ones.
 *
 * - Used: at least one usage is recorded (whatever the scan state).
 * - Unused: no usage is recorded, the last scan is complete with verified
 *   coverage (UsageScanStatus), and the attachment was uploaded before that
 *   scan started (so the scan covered it).
 * - Unknown: everything else without a recorded usage. No record is never
 *   treated as proof of non-use.
 *
 * Reasons for unknown come only from the stored scan status, its coverage
 * record and the attachment's own data; when the data cannot tell why, the
 * reason says so. Nothing here writes anything.
 */
class UsageClassifier {

	/**
	 * Tabs of the Used Where page this class answers for.
	 *
	 * @var string[]
	 */
	const TABS = [ 'used', 'unused', 'unknown' ];

	/**
	 * Scan context shared by every attachment of a request.
	 *
	 * @return array
	 */
	public static function context(): array {
		$status = UsageScanStatus::read();
		$raw    = get_option( UsageScanStatus::OPTION, [] );
		$raw    = is_array( $raw ) ? $raw : [];

		$scanned = null;
		if ( isset( $raw['scanned_post_types'] ) && is_array( $raw['scanned_post_types'] ) ) {
			$scanned = array_values( array_filter( $raw['scanned_post_types'], 'is_string' ) );
		}
		$not_scanned = null;
		if ( null !== $scanned ) {
			$not_scanned = array_values( array_diff( UsedWhereScanner::instance()->scannable_post_types(), $scanned ) );
		}

		$context = [
			'status'                 => $status,
			'verified'               => self::verified( $status ),
			'scanned_post_types'     => $scanned,
			'not_scanned_post_types' => $not_scanned,
			'scan_error'             => 'error' === $status['state'] ? self::safe_error( $raw['last_error'] ?? '' ) : '',
		];

		$context['missing'] = self::missing_information( $context );
		return $context;
	}

	/**
	 * Whether "no usage recorded" can be trusted: the scan is complete, its
	 * coverage verified, and its start time known (to tell which attachments
	 * it covered). Same rule as tsmlt/get-media-usage.
	 *
	 * @param array $status From UsageScanStatus::read().
	 *
	 * @return bool
	 */
	public static function verified( array $status ): bool {
		return $status['complete'] && ! $status['stalled'] && $status['coverage']['verified'] && '' !== $status['started_at'];
	}

	/**
	 * Whether the scan covered the attachment: it was uploaded before the
	 * scan started (same second included).
	 *
	 * @param WP_Post $attachment Attachment.
	 * @param array   $status     From UsageScanStatus::read().
	 *
	 * @return bool
	 */
	public static function covered( WP_Post $attachment, array $status ): bool {
		return '' !== $status['started_at'] && strcmp( (string) $attachment->post_date, $status['started_at'] ) <= 0;
	}

	/**
	 * WP_Query arguments for a tab, or null when the tab is empty by
	 * definition (no verified scan, so nothing can be unused).
	 *
	 * @param string $tab     One of TABS.
	 * @param string $search  Search text.
	 * @param array  $context From context().
	 *
	 * @return array|null
	 */
	public static function query_args( string $tab, string $search, array $context ): ?array {
		$args = [
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
		];
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$has_usage          = [
			'key'     => UsedWhereScanner::META_KEY,
			'compare' => 'used' === $tab ? 'EXISTS' : 'NOT EXISTS',
		];
		$args['meta_query'] = [ $has_usage ]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Usage records are post meta.

		if ( 'used' === $tab ) {
			return $args;
		}

		$started = $context['status']['started_at'];
		if ( 'unused' === $tab ) {
			if ( ! $context['verified'] ) {
				return null;
			}
			$args['date_query'] = [
				[
					'before'    => $started,
					'inclusive' => true,
					'column'    => 'post_date',
				],
			];
			return $args;
		}

		// Unknown: with a verified scan, only what it did not cover.
		if ( $context['verified'] ) {
			$args['date_query'] = [
				[
					'after'     => $started,
					'inclusive' => false,
					'column'    => 'post_date',
				],
			];
		}
		return $args;
	}

	/**
	 * Number of attachments in each tab (same queries as the lists).
	 *
	 * @param string $search  Search text.
	 * @param array  $context From context().
	 *
	 * @return array<string, int>
	 */
	public static function counts( string $search, array $context ): array {
		$counts = [];
		foreach ( self::TABS as $tab ) {
			$args = self::query_args( $tab, $search, $context );
			if ( null === $args ) {
				$counts[ $tab ] = 0;
				continue;
			}
			$query          = new \WP_Query(
				array_merge(
					$args,
					[
						'posts_per_page'         => 1,
						'fields'                 => 'ids',
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					]
				)
			);
			$counts[ $tab ] = (int) $query->found_posts;
		}
		return $counts;
	}

	/**
	 * Why an attachment without a recorded usage is unknown.
	 *
	 * @param WP_Post $attachment Attachment.
	 * @param array   $context    From context().
	 *
	 * @return array{reasons: array[], uploaded_at: string, uploaded_after_scan_start: bool|null}
	 */
	public static function diagnose( WP_Post $attachment, array $context ): array {
		$status   = $context['status'];
		$reasons  = [];
		$progress = sprintf(
			/* translators: 1: posts processed, 2: posts to process. */
			__( '%1$d of %2$d posts', 'media-library-tools' ),
			$status['processed'],
			$status['total']
		);

		if ( ! $status['ever_started'] ) {
			$reasons[] = self::reason(
				'no_scan',
				__( 'No usage scan has run', 'media-library-tools' ),
				__( 'There are no scan results (no scan has run, or the results were cleared), so there is nothing to show where this image is used.', 'media-library-tools' ),
				__( 'Run a scan.', 'media-library-tools' )
			);
		} elseif ( $status['stalled'] ) {
			$reasons[] = self::reason(
				'scan_stalled',
				__( 'The scan stopped making progress', 'media-library-tools' ),
				/* translators: %s: progress such as "40 of 120 posts". */
				sprintf( __( 'The scan has not processed a batch for over 3 minutes (at %s), so not every post was checked. WP-Cron may not be running.', 'media-library-tools' ), $progress ),
				__( 'Make sure WP-Cron runs on this site, then start the scan again.', 'media-library-tools' )
			);
		} elseif ( in_array( $status['state'], UsageScanStatus::ACTIVE_STATES, true ) ) {
			$reasons[] = self::reason(
				'scan_in_progress',
				__( 'The scan is still running', 'media-library-tools' ),
				/* translators: %s: progress such as "40 of 120 posts". */
				sprintf( __( 'The scan has checked %s so far; the post that uses this image may not have been reached yet.', 'media-library-tools' ), $progress ),
				__( 'Wait for the scan to finish.', 'media-library-tools' )
			);
		} elseif ( 'cancelled' === $status['state'] ) {
			$reasons[] = self::reason(
				'scan_cancelled',
				__( 'The scan was cancelled', 'media-library-tools' ),
				/* translators: %s: progress such as "40 of 120 posts". */
				sprintf( __( 'The scan was cancelled at %s, so not every post was checked.', 'media-library-tools' ), $progress ),
				__( 'Run a new scan and let it finish.', 'media-library-tools' )
			);
		} elseif ( 'error' === $status['state'] ) {
			$message = sprintf(
				/* translators: %s: progress such as "40 of 120 posts". */
				__( 'The scan stopped with an error at %s, so not every post was checked.', 'media-library-tools' ),
				$progress
			);
			$reasons[] = self::reason(
				'scan_failed',
				__( 'The scan failed', 'media-library-tools' ),
				$message,
				__( 'Fix the problem described in the error, then run a new scan.', 'media-library-tools' ),
				'' !== $context['scan_error'] ? [ $context['scan_error'] ] : [ __( 'The error message was not recorded.', 'media-library-tools' ) ]
			);
		} elseif ( ! $status['complete'] ) {
			$reasons[] = self::reason(
				'scan_incomplete',
				__( 'The scan did not finish', 'media-library-tools' ),
				__( 'The stored scan status does not show a finished scan, so the results are incomplete.', 'media-library-tools' ),
				__( 'Run a new scan and let it finish.', 'media-library-tools' )
			);
		} else {
			$gaps = $status['coverage']['gaps'];
			if ( in_array( UsageScanStatus::GAP_NOT_RECORDED, $gaps, true ) ) {
				$reasons[] = self::reason(
					'coverage_not_recorded',
					__( 'Scan coverage was not recorded', 'media-library-tools' ),
					__( 'The last scan was made by an earlier plugin version and did not record which post types it checked or what it could not check, so "no usage found" cannot be trusted.', 'media-library-tools' ),
					__( 'Run a new scan; the current version records its coverage.', 'media-library-tools' )
				);
			}
			if ( in_array( 'woocommerce_inactive', $gaps, true ) ) {
				$reasons[] = self::reason(
					'woocommerce_inactive',
					__( 'WooCommerce was inactive during the scan', 'media-library-tools' ),
					__( 'Products and variations were scanned, but product category images and rendered product pages could not be checked while WooCommerce was inactive.', 'media-library-tools' ),
					__( 'Activate WooCommerce, then run a new scan.', 'media-library-tools' ),
					self::woocommerce_evidence( $attachment )
				);
			}
			if ( in_array( 'woocommerce_types_not_scanned', $gaps, true ) ) {
				$types     = self::woocommerce_types_with_rows();
				$skipped   = null === $context['scanned_post_types'] ? $types : array_values( array_diff( $types, $context['scanned_post_types'] ) );
				$reasons[] = self::reason(
					'woocommerce_types_not_scanned',
					__( 'WooCommerce products were not scanned', 'media-library-tools' ),
					__( 'WooCommerce content exists, but the tsmlt_used_where_scannable_post_types filter left it out of the scan, so its images may not be detected.', 'media-library-tools' ),
					__( 'Remove the filter that excludes products or variations, then run a new scan.', 'media-library-tools' ),
					$skipped ? [
						/* translators: %s: post types. */
						sprintf( __( 'Not scanned: %s', 'media-library-tools' ), implode( ', ', $skipped ) ),
					] : []
				);
			}
			if ( ! $reasons && ! $status['coverage']['verified'] ) {
				$reasons[] = self::reason(
					'undetermined',
					__( 'The exact reason is not known', 'media-library-tools' ),
					__( 'The scan completed but its coverage is not verified, and the stored data does not say why.', 'media-library-tools' ),
					__( 'Run a new scan.', 'media-library-tools' )
				);
			}
		}

		$after_start = null;
		if ( '' !== $status['started_at'] ) {
			$after_start = ! self::covered( $attachment, $status );
			if ( $after_start && $status['ever_started'] ) {
				$reasons[] = self::reason(
					'not_covered',
					__( 'Uploaded after the scan started', 'media-library-tools' ),
					/* translators: 1: upload time, 2: scan start time. */
					sprintf( __( 'This image was uploaded on %1$s, after the scan started on %2$s, so the scan did not check it.', 'media-library-tools' ), $attachment->post_date, $status['started_at'] ),
					__( 'Run a new scan.', 'media-library-tools' )
				);
			}
		}

		if ( ! $reasons ) {
			$reasons[] = self::reason(
				'undetermined',
				__( 'The exact reason is not known', 'media-library-tools' ),
				__( 'The stored scan data does not show why this image cannot be confirmed as unused.', 'media-library-tools' ),
				__( 'Run a new scan.', 'media-library-tools' )
			);
		}

		return [
			'reasons'                   => $reasons,
			'uploaded_at'               => (string) $attachment->post_date,
			'uploaded_after_scan_start' => $after_start,
		];
	}

	/**
	 * Scan-level context in the shape the page shows.
	 *
	 * @param array $context From context().
	 *
	 * @return array
	 */
	public static function scan_summary( array $context ): array {
		$status = $context['status'];
		return [
			'state'                  => $status['state'],
			'complete'               => $status['complete'],
			'stalled'                => $status['stalled'],
			'processed'              => $status['processed'],
			'total'                  => $status['total'],
			'started_at'             => $status['started_at'],
			'last_update'            => $status['last_update'],
			'coverage_recorded'      => ! in_array( UsageScanStatus::GAP_NOT_RECORDED, $status['coverage']['gaps'], true ),
			'coverage_verified'      => $status['coverage']['verified'],
			'coverage_gaps'          => $status['coverage']['gaps'],
			'unused_verified'        => $context['verified'],
			'scanned_post_types'     => $context['scanned_post_types'],
			'not_scanned_post_types' => $context['not_scanned_post_types'],
			'scan_error'             => $context['scan_error'],
			'missing'                => $context['missing'],
		];
	}

	/**
	 * What the stored data does not tell, for the page's details panel.
	 *
	 * @param array $context Context (without `missing`).
	 *
	 * @return string[]
	 */
	private static function missing_information( array $context ): array {
		$status  = $context['status'];
		$missing = [];
		if ( ! $status['ever_started'] ) {
			return [ __( 'No scan results are stored.', 'media-library-tools' ) ];
		}
		if ( ! $status['complete'] ) {
			$missing[] = __( 'The scan did not finish, so not every post was checked.', 'media-library-tools' );
		}
		if ( null === $context['scanned_post_types'] ) {
			$missing[] = __( 'Which post types the scan checked was not recorded.', 'media-library-tools' );
		}
		if ( in_array( UsageScanStatus::GAP_NOT_RECORDED, $status['coverage']['gaps'], true ) ) {
			$missing[] = __( 'What the scan could not check (coverage gaps) was not recorded.', 'media-library-tools' );
		}
		if ( in_array( 'woocommerce_inactive', $status['coverage']['gaps'], true ) ) {
			$missing[] = __( 'Product category images and rendered product pages were not checked.', 'media-library-tools' );
		}
		if ( 'error' === $status['state'] && '' === $context['scan_error'] ) {
			$missing[] = __( 'The scan error message was not recorded.', 'media-library-tools' );
		}
		return $missing;
	}

	/**
	 * Direct links between an attachment and WooCommerce data, read from the
	 * database (the post types and taxonomy may be unregistered).
	 *
	 * @param WP_Post $attachment Attachment.
	 *
	 * @return string[]
	 */
	private static function woocommerce_evidence( WP_Post $attachment ): array {
		$evidence = [];
		$parent   = (int) $attachment->post_parent;
		if ( $parent ) {
			$type = (string) get_post_field( 'post_type', $parent );
			if ( in_array( $type, UsedWhereScanner::WOOCOMMERCE_POST_TYPES, true ) ) {
				/* translators: 1: post type, 2: post ID. */
				$evidence[] = sprintf( __( 'Uploaded to (attached to) %1$s #%2$d.', 'media-library-tools' ), $type, $parent );
			}
		}

		$rows = Fns::DB()->select( 'term_id' )
			->from( 'termmeta' )
			->where( 'meta_key', '=', 'thumbnail_id' )
			->andWhere( 'meta_value', '=', (string) $attachment->ID )
			->get();
		foreach ( (array) $rows as $row ) {
			$term_id  = (int) ( $row['term_id'] ?? 0 );
			$taxonomy = Fns::DB()->select( 'taxonomy' )
				->from( 'term_taxonomy' )
				->where( 'term_id', '=', $term_id )
				->get();
			$name     = (string) ( $taxonomy[0]['taxonomy'] ?? '' );
			/* translators: 1: term ID, 2: taxonomy. */
			$evidence[] = sprintf( __( 'Set as the image of term #%1$d (%2$s), which the scan could not check.', 'media-library-tools' ), $term_id, '' !== $name ? $name : __( 'unknown taxonomy', 'media-library-tools' ) );
		}

		if ( ! $evidence ) {
			$evidence[] = __( 'No direct link to a product, variation or product category was found for this image in the database; it stays unknown because those sources were not fully checked.', 'media-library-tools' );
		}
		return $evidence;
	}

	/**
	 * WooCommerce post types that have rows.
	 *
	 * @return string[]
	 */
	private static function woocommerce_types_with_rows(): array {
		$types = [];
		foreach ( UsedWhereScanner::WOOCOMMERCE_POST_TYPES as $type ) {
			$rows = Fns::DB()->select()
				->count( '*', 'total' )
				->from( 'posts' )
				->where( 'post_type', '=', $type )
				->get();
			if ( (int) ( $rows[0]['total'] ?? 0 ) > 0 ) {
				$types[] = $type;
			}
		}
		return $types;
	}

	/**
	 * One reason.
	 *
	 * @param string   $code     Machine-readable code.
	 * @param string   $title    Short title.
	 * @param string   $message  Explanation.
	 * @param string   $action   What the user can do.
	 * @param string[] $evidence Facts about this attachment.
	 *
	 * @return array
	 */
	private static function reason( string $code, string $title, string $message, string $action, array $evidence = [] ): array {
		return [
			'code'     => $code,
			'title'    => $title,
			'message'  => $message,
			'action'   => $action,
			'evidence' => $evidence,
		];
	}

	/**
	 * The scanner's error text without server paths or markup, shortened.
	 *
	 * @param mixed $error Stored error.
	 *
	 * @return string
	 */
	private static function safe_error( $error ): string {
		if ( ! is_scalar( $error ) ) {
			return '';
		}
		$error = wp_strip_all_tags( (string) $error );
		foreach ( array_filter( [ defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '', ABSPATH ] ) as $path ) {
			$error = str_replace( [ rtrim( $path, '/\\' ), wp_normalize_path( rtrim( $path, '/\\' ) ) ], '…', $error );
		}
		// Any other absolute path.
		$error = (string) preg_replace( '#(?:[A-Za-z]:)?[\\\\/](?:[^\\s\\\\/:]+[\\\\/])+[^\\s\\\\/:]*#', '…', $error );
		return mb_substr( trim( $error ), 0, 300 );
	}
}
