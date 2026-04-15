<?php
/**
 * EXIF Filtering helpers — filters media by EXIF data.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Helpers;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * ExifFilter — static helpers for EXIF-based filtering.
 */
class ExifFilter {

	/**
	 * Apply EXIF-based filters to media query.
	 *
	 * @param array $args WP_Query arguments.
	 * @param array $params Filter parameters from request.
	 *
	 * @return array Modified query arguments.
	 */
	public static function apply_filters( array $args, array $params ): array {
		global $wpdb;

		// Camera model filter.
		if ( ! empty( $params['exif_camera'] ) ) {
			// This requires scanning EXIF on demand or joining to a meta table.
			// For performance, we'll do post-query filtering.
			$args['meta_query'] = $args['meta_query'] ?? [];
			$args['meta_query'][] = [
				'key'     => '_tsmlt_exif_camera',
				'value'   => sanitize_text_field( $params['exif_camera'] ),
				'compare' => 'LIKE',
			];
		}

		// Date range filter (DateTimeOriginal from EXIF).
		if ( ! empty( $params['exif_date_from'] ) || ! empty( $params['exif_date_to'] ) ) {
			if ( ! isset( $args['meta_query'] ) ) {
				$args['meta_query'] = [];
			}

			$date_query = [
				'key'     => '_tsmlt_exif_date',
				'type'    => 'DATETIME',
				'compare' => 'BETWEEN',
			];

			$from = sanitize_text_field( $params['exif_date_from'] ?? '' );
			$to = sanitize_text_field( $params['exif_date_to'] ?? '' );

			if ( $from ) {
				$date_query['value'] = [ $from . ' 00:00:00', $to ? $to . ' 23:59:59' : wp_date( 'Y-m-d H:i:s' ) ];
			} elseif ( $to ) {
				$date_query['compare'] = '<=';
				$date_query['value'] = $to . ' 23:59:59';
			}

			if ( ! empty( $date_query['value'] ) ) {
				$args['meta_query'][] = $date_query;
			}
		}

		// GPS filter (has GPS / no GPS).
		if ( ! empty( $params['exif_has_gps'] ) ) {
			if ( ! isset( $args['meta_query'] ) ) {
				$args['meta_query'] = [];
			}

			if ( 'yes' === $params['exif_has_gps'] ) {
				// Has GPS.
				$args['meta_query'][] = [
					'key'     => '_tsmlt_exif_gps_lat',
					'compare' => 'EXISTS',
				];
			} elseif ( 'no' === $params['exif_has_gps'] ) {
				// No GPS.
				$args['meta_query'][] = [
					'key'     => '_tsmlt_exif_gps_lat',
					'compare' => 'NOT EXISTS',
				];
			}
		}

		return $args;
	}

	/**
	 * Get available camera models from existing attachments.
	 *
	 * @return array Camera model options.
	 */
	public static function get_camera_models(): array {
		global $wpdb;

		$models = $wpdb->get_col(
			"SELECT DISTINCT meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_tsmlt_exif_camera'
			AND meta_value != ''
			ORDER BY meta_value"
		);

		return array_filter( $models );
	}

	/**
	 * Store EXIF metadata as post meta for filtering/sorting.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $exif_data     Raw EXIF data.
	 *
	 * @return void
	 */
	public static function store_exif_meta( int $attachment_id, array $exif_data ): void {
		// Extract camera info.
		$make = self::get_exif_field( $exif_data, 'Make' );
		$model = self::get_exif_field( $exif_data, 'Model' );
		if ( $make || $model ) {
			$camera = trim( "$make $model" );
			update_post_meta( $attachment_id, '_tsmlt_exif_camera', $camera );
		}

		// Extract date taken.
		$date = self::get_exif_field( $exif_data, 'DateTimeOriginal', [ 'EXIF' ] );
		if ( ! $date ) {
			$date = self::get_exif_field( $exif_data, 'DateTime', [ 'IFD0' ] );
		}
		if ( $date ) {
			// Convert to MySQL format.
			$date = str_replace( ':', '-', $date, 2 );
			update_post_meta( $attachment_id, '_tsmlt_exif_date', $date );
		}

		// Extract GPS.
		$gps_lat = self::get_exif_field( $exif_data, 'GPSLatitude', [ 'GPS' ] );
		$gps_lng = self::get_exif_field( $exif_data, 'GPSLongitude', [ 'GPS' ] );
		if ( $gps_lat || $gps_lng ) {
			if ( $gps_lat ) {
				update_post_meta( $attachment_id, '_tsmlt_exif_gps_lat', $gps_lat );
			}
			if ( $gps_lng ) {
				update_post_meta( $attachment_id, '_tsmlt_exif_gps_lng', $gps_lng );
			}
		}
	}

	/**
	 * Get EXIF field value from raw data.
	 *
	 * @param array      $exif_data Raw EXIF data.
	 * @param string     $field     Field name.
	 * @param array|null $sections  Sections to search.
	 *
	 * @return string|null
	 */
	private static function get_exif_field( array $exif_data, string $field, ?array $sections = null ): ?string {
		if ( null === $sections ) {
			$sections = [ 'IFD0', 'EXIF', 'GPS' ];
		}

		foreach ( $sections as $section ) {
			if ( isset( $exif_data[ $section ][ $field ] ) ) {
				$value = $exif_data[ $section ][ $field ];
				if ( is_array( $value ) ) {
					$value = $value[0];
				}
				return (string) $value;
			}
		}

		return null;
	}
}
