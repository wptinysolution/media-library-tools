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
		// Camera model filter.
		if ( ! empty( $params['exif_camera'] ) ) {
			$args['meta_query']   = $args['meta_query'] ?? []; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary for EXIF camera filtering.
			$args['meta_query'][] = [
				'key'     => '_tsmlt_exif_camera',
				'value'   => sanitize_text_field( $params['exif_camera'] ),
				'compare' => 'LIKE',
			];
		}

		// GPS filter (has GPS / no GPS).
		if ( ! empty( $params['exif_has_gps'] ) ) {
			if ( ! isset( $args['meta_query'] ) ) {
				$args['meta_query'] = []; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary for EXIF GPS filtering.
			}

			if ( 'yes' === $params['exif_has_gps'] ) {
				$args['meta_query'][] = [
					'key'     => '_tsmlt_exif_gps_lat',
					'compare' => 'EXISTS',
				];
			} elseif ( 'no' === $params['exif_has_gps'] ) {
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
		$results = Fns::DB()->select( 'meta_value' )
			->distinct()
			->from( 'postmeta' )
			->where( 'meta_key', '=', '_tsmlt_exif_camera' )
			->andWhere( 'meta_value', '!=', '' )
			->orderBy( 'meta_value', 'ASC' )
			->get();

		$models = [];
		foreach ( $results as $row ) {
			if ( ! empty( $row['meta_value'] ) ) {
				$models[] = $row['meta_value'];
			}
		}

		return $models;
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
		$make  = self::get_exif_field( $exif_data, 'Make' );
		$model = self::get_exif_field( $exif_data, 'Model' );
		if ( $make || $model ) {
			$camera = trim( "$make $model" );
			update_post_meta( $attachment_id, '_tsmlt_exif_camera', $camera );
		}

		// Extract GPS as decimal coordinates.
		$gps_lat = self::get_gps_decimal( $exif_data, 'GPSLatitude', 'GPSLatitudeRef' );
		$gps_lng = self::get_gps_decimal( $exif_data, 'GPSLongitude', 'GPSLongitudeRef' );
		if ( null !== $gps_lat ) {
			update_post_meta( $attachment_id, '_tsmlt_exif_gps_lat', $gps_lat );
		}
		if ( null !== $gps_lng ) {
			update_post_meta( $attachment_id, '_tsmlt_exif_gps_lng', $gps_lng );
		}
	}

	/**
	 * Remove stored GPS meta for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public static function remove_gps_meta( int $attachment_id ): void {
		delete_post_meta( $attachment_id, '_tsmlt_exif_gps_lat' );
		delete_post_meta( $attachment_id, '_tsmlt_exif_gps_lng' );
	}

	/**
	 * Convert GPS DMS array from EXIF to decimal degrees.
	 *
	 * @param array  $exif_data Raw EXIF data.
	 * @param string $coord_key GPS coordinate key (GPSLatitude or GPSLongitude).
	 * @param string $ref_key   GPS reference key (GPSLatitudeRef or GPSLongitudeRef).
	 *
	 * @return float|null Decimal coordinate or null.
	 */
	private static function get_gps_decimal( array $exif_data, string $coord_key, string $ref_key ): ?float {
		if ( ! isset( $exif_data['GPS'][ $coord_key ] ) ) {
			return null;
		}

		$dms = $exif_data['GPS'][ $coord_key ];
		if ( ! is_array( $dms ) || count( $dms ) < 3 ) {
			return null;
		}

		$degrees = self::rational_to_float( $dms[0] );
		$minutes = self::rational_to_float( $dms[1] );
		$seconds = self::rational_to_float( $dms[2] );

		$decimal = $degrees + ( $minutes / 60 ) + ( $seconds / 3600 );

		$ref = $exif_data['GPS'][ $ref_key ] ?? '';
		if ( in_array( $ref, [ 'S', 'W' ], true ) ) {
			$decimal = -$decimal;
		}

		return round( $decimal, 6 );
	}

	/**
	 * Convert rational string (e.g., "40/1") to float.
	 *
	 * @param mixed $value Rational string or number.
	 *
	 * @return float
	 */
	private static function rational_to_float( $value ): float {
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}

		if ( is_string( $value ) && strpos( $value, '/' ) !== false ) {
			$parts = explode( '/', $value, 2 );
			if ( 2 === count( $parts ) && is_numeric( $parts[0] ) && is_numeric( $parts[1] ) && (float) $parts[1] > 0 ) {
				return (float) $parts[0] / (float) $parts[1];
			}
		}

		return 0.0;
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
	public static function get_exif_field( array $exif_data, string $field, ?array $sections = null ): ?string {
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
