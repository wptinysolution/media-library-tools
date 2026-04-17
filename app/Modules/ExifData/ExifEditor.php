<?php
/**
 * EXIF Editor module — validate and save EXIF metadata.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\ExifData;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * ExifEditor — validate and write EXIF data for single images.
 */
class ExifEditor {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Meta key for tracking EXIF edits.
	 */
	const META_KEY_EDITED = '_tsmlt_exif_edited';

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Validate EXIF fields.
	 *
	 * @param array $fields Fields to validate.
	 *
	 * @return array{valid: bool, errors: array<string,string>}
	 */
	public function validate_fields( array $fields ): array {
		$errors = [];

		// Make: optional, string ≤ 64 chars.
		if ( isset( $fields['make'] ) && ! empty( $fields['make'] ) ) {
			if ( ! is_string( $fields['make'] ) || strlen( $fields['make'] ) > 64 ) {
				$errors['make'] = esc_html__( 'Make must be a string up to 64 characters.', 'media-library-tools' );
			}
		}

		// Model: optional, string ≤ 64 chars.
		if ( isset( $fields['model'] ) && ! empty( $fields['model'] ) ) {
			if ( ! is_string( $fields['model'] ) || strlen( $fields['model'] ) > 64 ) {
				$errors['model'] = esc_html__( 'Model must be a string up to 64 characters.', 'media-library-tools' );
			}
		}

		// DateTimeOriginal: optional; if set must match YYYY:MM:DD HH:MM:SS and not be in the future.
		if ( isset( $fields['date_time_original'] ) && ! empty( $fields['date_time_original'] ) ) {
			if ( ! preg_match( '/^\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}$/', $fields['date_time_original'] ) ) {
				$errors['date_time_original'] = esc_html__( 'Date must be in format YYYY:MM:DD HH:MM:SS.', 'media-library-tools' );
			} else {
				$date_str = str_replace( ':', '-', substr( $fields['date_time_original'], 0, 10 ) ) . substr( $fields['date_time_original'], 10 );
				$timestamp = strtotime( $date_str );
				if ( false !== $timestamp && $timestamp > time() ) {
					$errors['date_time_original'] = esc_html__( 'Date Taken cannot be in the future.', 'media-library-tools' );
				}
			}
		}

		// ISO: optional; if set must be integer 1–102400.
		if ( isset( $fields['iso'] ) && '' !== $fields['iso'] && null !== $fields['iso'] ) {
			$iso = (int) $fields['iso'];
			if ( ! is_numeric( $fields['iso'] ) || $iso < 1 || $iso > 102400 ) {
				$errors['iso'] = esc_html__( 'ISO must be a number between 1 and 102400.', 'media-library-tools' );
			}
		}

		// Aperture: optional; if set must be float > 0 ≤ 128.
		if ( isset( $fields['aperture'] ) && '' !== $fields['aperture'] && null !== $fields['aperture'] ) {
			$aperture = (float) $fields['aperture'];
			if ( ! is_numeric( $fields['aperture'] ) || $aperture <= 0 || $aperture > 128 ) {
				$errors['aperture'] = esc_html__( 'Aperture (f/) must be a number between 0 and 128.', 'media-library-tools' );
			}
		}

		// ShutterSpeed: optional; if set must be "1/N" or float.
		if ( isset( $fields['shutter_speed'] ) && ! empty( $fields['shutter_speed'] ) ) {
			if ( ! preg_match( '/^(1\/\d+|\d*\.?\d+)$/', $fields['shutter_speed'] ) ) {
				$errors['shutter_speed'] = esc_html__( 'Shutter speed must be like "1/250" or "0.5".', 'media-library-tools' );
			}
		}

		// GPSLatitude: optional; if set must be -90..90.
		if ( isset( $fields['gps_lat'] ) && '' !== $fields['gps_lat'] && null !== $fields['gps_lat'] ) {
			$lat = (float) $fields['gps_lat'];
			if ( ! is_numeric( $fields['gps_lat'] ) || $lat < -90 || $lat > 90 ) {
				$errors['gps_lat'] = esc_html__( 'Latitude must be between -90 and 90.', 'media-library-tools' );
			}
		}

		// GPSLongitude: optional; if set must be -180..180.
		if ( isset( $fields['gps_lng'] ) && '' !== $fields['gps_lng'] && null !== $fields['gps_lng'] ) {
			$lng = (float) $fields['gps_lng'];
			if ( ! is_numeric( $fields['gps_lng'] ) || $lng < -180 || $lng > 180 ) {
				$errors['gps_lng'] = esc_html__( 'Longitude must be between -180 and 180.', 'media-library-tools' );
			}
		}

		return [
			'valid'  => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Save EXIF data to attachment file.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $fields        EXIF fields to save.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function save_exif( int $attachment_id, array $fields ): array {
		// Validate fields.
		$validation = $this->validate_fields( $fields );
		if ( ! $validation['valid'] ) {
			$error_msg = implode( ' | ', $validation['errors'] );
			return [
				'success' => false,
				'message' => $error_msg,
			];
		}

		// Check JPEG MIME.
		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, [ 'image/jpeg', 'image/jpg' ], true ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'EXIF writing is only available for JPEG images.', 'media-library-tools' ),
			];
		}

		// Get file path.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return [
				'success' => false,
				'message' => esc_html__( 'Image file not found on server.', 'media-library-tools' ),
			];
		}

		// Build EXIF-compatible field array for writer.
		$exif_fields = [];

		if ( ! empty( $fields['make'] ) ) {
			$exif_fields['Make'] = sanitize_text_field( $fields['make'] );
		}
		if ( ! empty( $fields['model'] ) ) {
			$exif_fields['Model'] = sanitize_text_field( $fields['model'] );
		}
		if ( ! empty( $fields['date_time_original'] ) ) {
			$exif_fields['DateTimeOriginal'] = sanitize_text_field( $fields['date_time_original'] );
		}
		if ( isset( $fields['iso'] ) && '' !== $fields['iso'] && null !== $fields['iso'] ) {
			$exif_fields['ISOSpeedRatings'] = (int) $fields['iso'];
		}
		if ( isset( $fields['aperture'] ) && '' !== $fields['aperture'] && null !== $fields['aperture'] ) {
			$f_num                  = (float) $fields['aperture'];
			$exif_fields['FNumber'] = (int) round( $f_num * 10 ) . '/10';
		}
		if ( ! empty( $fields['shutter_speed'] ) ) {
			$exif_fields['ExposureTime'] = sanitize_text_field( $fields['shutter_speed'] );
		}
		if ( isset( $fields['gps_lat'] ) && '' !== $fields['gps_lat'] && null !== $fields['gps_lat'] ) {
			$lat                              = (float) $fields['gps_lat'];
			$exif_fields['GPSLatitude']       = $this->decimal_to_dms( $lat );
			$exif_fields['GPSLatitudeRef']    = $lat >= 0 ? 'N' : 'S';
		}
		if ( isset( $fields['gps_lng'] ) && '' !== $fields['gps_lng'] && null !== $fields['gps_lng'] ) {
			$lng                               = (float) $fields['gps_lng'];
			$exif_fields['GPSLongitude']       = $this->decimal_to_dms( $lng );
			$exif_fields['GPSLongitudeRef']    = $lng >= 0 ? 'E' : 'W';
		}

		// Write EXIF using JpegExifWriter.
		$result = JpegExifWriter::write( $file_path, $exif_fields );
		if ( ! $result['success'] ) {
			return $result;
		}

		// Clear EXIF cache.
		ExifDataReader::clear_cache();

		// Record edit timestamp.
		update_post_meta( $attachment_id, self::META_KEY_EDITED, current_time( 'mysql' ) );

		return [
			'success' => true,
			'message' => esc_html__( 'EXIF data saved successfully.', 'media-library-tools' ),
		];
	}

	/**
	 * Convert decimal coordinates to DMS rational format.
	 *
	 * @param float $decimal Decimal coordinate.
	 *
	 * @return string DMS format "deg/1 min/1 sec/100".
	 */
	private function decimal_to_dms( float $decimal ): string {
		$abs_decimal = abs( $decimal );
		$deg         = (int) floor( $abs_decimal );
		$min         = (int) floor( ( $abs_decimal - $deg ) * 60 );
		$sec_num     = (int) round( ( ( $abs_decimal - $deg ) * 60 - $min ) * 60 * 100 );

		return "$deg/1 $min/1 $sec_num/100";
	}
}
