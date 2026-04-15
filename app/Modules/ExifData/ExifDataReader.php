<?php
/**
 * EXIF Data Reader module — reads and displays EXIF metadata.
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
 * ExifDataReader — reads EXIF metadata from attachments and renders HTML for modal display.
 */
class ExifDataReader {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * MIME types that support EXIF reading.
	 *
	 * @var array
	 */
	private $supported_mimes = [ 'image/jpeg', 'image/jpg', 'image/tiff', 'image/webp' ];

	/**
	 * Static cache for EXIF data (within a single page load).
	 *
	 * @var array
	 */
	private static $exif_cache = [];

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Clear the static EXIF cache (used when scan is reset).
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$exif_cache = [];
	}

	/**
	 * Read EXIF data from an attachment (with caching).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array Array with 'supported', 'has_exif', and data fields.
	 */
	public function get_exif_data( int $attachment_id ): array {
		// Check cache first.
		if ( isset( self::$exif_cache[ $attachment_id ] ) ) {
			return self::$exif_cache[ $attachment_id ];
		}

		$mime = get_post_mime_type( $attachment_id );

		// Check if MIME type is supported.
		if ( ! in_array( $mime, $this->supported_mimes, true ) ) {
			$result = [
				'supported' => false,
				'has_exif'  => false,
				'error'     => esc_html__( 'EXIF data is only available for JPEG, TIFF, and WebP images.', 'media-library-tools' ),
			];
			self::$exif_cache[ $attachment_id ] = $result;
			return $result;
		}

		// Get file path.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			$result = [
				'supported' => true,
				'has_exif'  => false,
				'error'     => esc_html__( 'Image file not found on server.', 'media-library-tools' ),
			];
			self::$exif_cache[ $attachment_id ] = $result;
			return $result;
		}

		// Check if PHP EXIF extension is available.
		if ( ! function_exists( 'exif_read_data' ) ) {
			$result = [
				'supported' => true,
				'has_exif'  => false,
				'error'     => esc_html__( 'PHP EXIF extension is not enabled on this server.', 'media-library-tools' ),
			];
			self::$exif_cache[ $attachment_id ] = $result;
			return $result;
		}

		// Read EXIF data.
		$raw = @exif_read_data( $file_path, null, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! is_array( $raw ) || empty( $raw ) ) {
			$result = [
				'supported' => true,
				'has_exif'  => false,
				'error'     => esc_html__( 'No EXIF data found for this image.', 'media-library-tools' ),
			];
			self::$exif_cache[ $attachment_id ] = $result;
			return $result;
		}

		// Format and return EXIF data.
		$result = [
			'supported' => true,
			'has_exif'  => true,
			'data'      => $this->format_exif_for_display( $raw ),
			'raw'       => $raw, // Keep raw for column display convenience.
		];
		self::$exif_cache[ $attachment_id ] = $result;
		return $result;
	}

	/**
	 * Format raw EXIF data into human-readable key-value pairs grouped by category.
	 *
	 * @param array $raw Raw EXIF data from exif_read_data().
	 *
	 * @return array Grouped EXIF data with categories and fields.
	 */
	public function format_exif_for_display( array $raw ): array {
		$groups = [
			'camera'    => [
				'Make'     => esc_html__( 'Make', 'media-library-tools' ),
				'Model'    => esc_html__( 'Model', 'media-library-tools' ),
				'Software' => esc_html__( 'Software', 'media-library-tools' ),
			],
			'image'     => [
				'ImageWidth'     => esc_html__( 'Image Width', 'media-library-tools' ),
				'ImageLength'    => esc_html__( 'Image Height', 'media-library-tools' ),
				'ExifImageWidth' => esc_html__( 'EXIF Width', 'media-library-tools' ),
				'ExifImageLength' => esc_html__( 'EXIF Height', 'media-library-tools' ),
				'Orientation'    => esc_html__( 'Orientation', 'media-library-tools' ),
				'ColorSpace'     => esc_html__( 'Color Space', 'media-library-tools' ),
				'BitsPerSample'  => esc_html__( 'Bits Per Sample', 'media-library-tools' ),
			],
			'exposure'  => [
				'ExposureTime'        => esc_html__( 'Exposure Time', 'media-library-tools' ),
				'FNumber'             => esc_html__( 'Aperture', 'media-library-tools' ),
				'ISOSpeedRatings'     => esc_html__( 'ISO', 'media-library-tools' ),
				'ExposureProgram'     => esc_html__( 'Exposure Program', 'media-library-tools' ),
				'ExposureBiasValue'   => esc_html__( 'Exposure Bias', 'media-library-tools' ),
				'MeteringMode'        => esc_html__( 'Metering Mode', 'media-library-tools' ),
				'Flash'               => esc_html__( 'Flash', 'media-library-tools' ),
				'FocalLength'         => esc_html__( 'Focal Length', 'media-library-tools' ),
				'FocalLengthIn35mmFilm' => esc_html__( '35mm Focal Length', 'media-library-tools' ),
			],
			'gps'       => [
				'GPSLatitude'  => esc_html__( 'Latitude', 'media-library-tools' ),
				'GPSLongitude' => esc_html__( 'Longitude', 'media-library-tools' ),
				'GPSAltitude'  => esc_html__( 'Altitude', 'media-library-tools' ),
			],
			'date'      => [
				'DateTime'           => esc_html__( 'Date Modified', 'media-library-tools' ),
				'DateTimeOriginal'   => esc_html__( 'Date Taken', 'media-library-tools' ),
				'DateTimeDigitized'  => esc_html__( 'Date Digitized', 'media-library-tools' ),
			],
			'other'     => [
				'Artist'       => esc_html__( 'Artist', 'media-library-tools' ),
				'Copyright'    => esc_html__( 'Copyright', 'media-library-tools' ),
				'UserComment'  => esc_html__( 'User Comment', 'media-library-tools' ),
				'ImageDescription' => esc_html__( 'Description', 'media-library-tools' ),
			],
		];

		$result = [];

		foreach ( $groups as $category => $fields ) {
			$group_data = [];

			foreach ( $fields as $exif_key => $label ) {
				$value = null;

				// Special handling for GPS coordinates.
				if ( 'gps' === $category && in_array( $exif_key, [ 'GPSLatitude', 'GPSLongitude' ], true ) ) {
					$value = $this->get_gps_coordinate( $raw, $exif_key );
				} else {
					// Look in IFD0, EXIF, GPS, or COMPUTED sections.
					foreach ( [ 'IFD0', 'EXIF', 'GPS', 'COMPUTED' ] as $section ) {
						if ( isset( $raw[ $section ][ $exif_key ] ) ) {
							$value = $raw[ $section ][ $exif_key ];
							break;
						}
					}
				}

				// Format special fields.
				if ( $value !== null && '' !== $value ) {
					if ( 'FNumber' === $exif_key ) {
						$value = $this->format_aperture( $value );
					} elseif ( 'ExposureTime' === $exif_key ) {
						$value = $this->format_exposure_time( $value );
					} elseif ( 'FocalLength' === $exif_key || 'FocalLengthIn35mmFilm' === $exif_key ) {
						$value = $this->format_focal_length( $value );
					} elseif ( 'ISOSpeedRatings' === $exif_key ) {
						$value = is_array( $value ) ? $value[0] : $value;
					} elseif ( 'GPSAltitude' === $exif_key ) {
						$value = $this->format_altitude( $value );
					}

					$group_data[ $label ] = $value;
				}
			}

			if ( ! empty( $group_data ) ) {
				$result[ $category ] = $group_data;
			}
		}

		return $result;
	}

	/**
	 * Get GPS coordinates (latitude or longitude) from EXIF data.
	 *
	 * @param array  $raw The raw EXIF data.
	 * @param string $exif_key Either 'GPSLatitude' or 'GPSLongitude'.
	 *
	 * @return string|null Decimal coordinate or null.
	 */
	private function get_gps_coordinate( array $raw, string $exif_key ): ?string {
		if ( ! isset( $raw['GPS'][ $exif_key ] ) ) {
			return null;
		}

		$dms = $raw['GPS'][ $exif_key ];
		$ref_key = 'GPSLatitude' === $exif_key ? 'GPSLatitudeRef' : 'GPSLongitudeRef';
		$ref = $raw['GPS'][ $ref_key ] ?? '';

		if ( ! is_array( $dms ) || count( $dms ) < 3 ) {
			return null;
		}

		$decimal = $this->dms_to_decimal( $dms );
		$negative = in_array( $ref, [ 'S', 'W' ], true );

		return ( $negative ? '-' : '' ) . number_format( (float) $decimal, 6 );
	}

	/**
	 * Convert DMS (degrees/minutes/seconds) to decimal degrees.
	 *
	 * @param array $dms Array with three elements: [degrees, minutes, seconds].
	 *
	 * @return float Decimal degrees.
	 */
	private function dms_to_decimal( array $dms ): float {
		$degrees = $this->rational_to_float( $dms[0] );
		$minutes = $this->rational_to_float( $dms[1] );
		$seconds = $this->rational_to_float( $dms[2] );

		return $degrees + ( $minutes / 60 ) + ( $seconds / 3600 );
	}

	/**
	 * Convert a rational string (e.g., "3/2") to float.
	 *
	 * @param string|int|float $value Rational string or number.
	 *
	 * @return float
	 */
	private function rational_to_float( $value ): float {
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}

		if ( is_string( $value ) && strpos( $value, '/' ) !== false ) {
			$parts = explode( '/', $value );
			if ( count( $parts ) === 2 && is_numeric( $parts[0] ) && is_numeric( $parts[1] ) ) {
				return (float) $parts[0] / (float) $parts[1];
			}
		}

		return 0.0;
	}

	/**
	 * Format aperture (F-number) value.
	 *
	 * @param string|float $value The F-number value (e.g., "18/10").
	 *
	 * @return string Formatted as "f/X.X".
	 */
	private function format_aperture( $value ): string {
		$f_number = $this->rational_to_float( $value );
		return 'f/' . number_format( (float) $f_number, 1 );
	}

	/**
	 * Format exposure time value.
	 *
	 * @param string|float $value The exposure time (e.g., "1/250").
	 *
	 * @return string Formatted exposure time.
	 */
	private function format_exposure_time( $value ): string {
		$float_val = $this->rational_to_float( $value );

		if ( $float_val >= 1 ) {
			return number_format( (float) $float_val, 2 ) . ' s';
		}

		if ( $float_val > 0 ) {
			$fraction = 1 / $float_val;
			return '1/' . number_format( (float) $fraction, 0 );
		}

		return (string) $value;
	}

	/**
	 * Format focal length value.
	 *
	 * @param string|float $value The focal length (e.g., "50/1").
	 *
	 * @return string Formatted as "XX mm".
	 */
	private function format_focal_length( $value ): string {
		$length = $this->rational_to_float( $value );
		return number_format( (float) $length, 1 ) . ' mm';
	}

	/**
	 * Format altitude value.
	 *
	 * @param string|float $value The altitude.
	 *
	 * @return string Formatted as "X.X m".
	 */
	private function format_altitude( $value ): string {
		$alt = $this->rational_to_float( $value );
		return number_format( (float) $alt, 1 ) . ' m';
	}

	/**
	 * Get camera make and model for list column display.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string Camera info or fallback text.
	 */
	public function get_camera_display( int $attachment_id ): string {
		$exif = $this->get_exif_data( $attachment_id );

		if ( ! $exif['has_exif'] || empty( $exif['data'] ) ) {
			return '—';
		}

		$camera_data = $exif['data']['camera'] ?? [];
		if ( empty( $camera_data ) ) {
			return '—';
		}

		$parts = [];
		if ( ! empty( $camera_data['Make'] ) ) {
			$parts[] = $camera_data['Make'];
		}
		if ( ! empty( $camera_data['Model'] ) ) {
			$parts[] = $camera_data['Model'];
		}

		return ! empty( $parts ) ? implode( ' ', $parts ) : '—';
	}

	/**
	 * Get date taken from EXIF for list column display.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string Date taken or fallback text.
	 */
	public function get_date_taken_display( int $attachment_id ): string {
		$exif = $this->get_exif_data( $attachment_id );

		if ( ! $exif['has_exif'] || empty( $exif['data'] ) ) {
			return '—';
		}

		$date_data = $exif['data']['date'] ?? [];

		// Prefer DateTimeOriginal, fall back to DateTime.
		$date_str = $date_data['Date Taken'] ?? $date_data['Date Modified'] ?? '';

		if ( empty( $date_str ) ) {
			return '—';
		}

		// Convert EXIF date format (YYYY:MM:DD HH:MM:SS) to readable format.
		$date_obj = \DateTime::createFromFormat( 'Y:m:d H:i:s', $date_str );
		if ( $date_obj ) {
			return $date_obj->format( 'Y-m-d H:i' );
		}

		return '—';
	}

	/**
	 * Get image dimensions from EXIF for list column display.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string Dimensions or fallback text.
	 */
	public function get_dimensions_display( int $attachment_id ): string {
		$exif = $this->get_exif_data( $attachment_id );

		if ( ! $exif['has_exif'] || empty( $exif['data'] ) ) {
			return '—';
		}

		$image_data = $exif['data']['image'] ?? [];

		// Try to get dimensions from EXIF.
		$width  = $image_data['Image Width'] ?? $image_data['EXIF Width'] ?? '';
		$height = $image_data['Image Height'] ?? $image_data['EXIF Height'] ?? '';

		// Extract numeric values if they're still string.
		$width  = is_string( $width ) ? intval( $width ) : intval( $width );
		$height = is_string( $height ) ? intval( $height ) : intval( $height );

		if ( $width > 0 && $height > 0 ) {
			return sprintf( '%d × %d', $width, $height );
		}

		return '—';
	}

	/**
	 * Get raw EXIF data for date-based sorting.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string Date in sortable format or empty string.
	 */
	public function get_date_for_sorting( int $attachment_id ): string {
		$exif = $this->get_exif_data( $attachment_id );

		if ( ! $exif['has_exif'] || empty( $exif['raw'] ) ) {
			return '';
		}

		$raw = $exif['raw'];

		// Try to find DateTimeOriginal first, then DateTime.
		$date_str = $raw['EXIF']['DateTimeOriginal'] ?? $raw['IFD0']['DateTime'] ?? '';

		if ( empty( $date_str ) ) {
			return '';
		}

		// Convert to ISO format (YYYY-MM-DD HH:MM:SS) for sorting.
		$date_obj = \DateTime::createFromFormat( 'Y:m:d H:i:s', $date_str );
		if ( $date_obj ) {
			return $date_obj->format( 'Y-m-d H:i:s' );
		}

		return '';
	}

	/**
	 * Render HTML for the EXIF Data panel in the media modal.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string HTML string.
	 */
	public function render_modal_html( int $attachment_id ): string {
		$exif_result = $this->get_exif_data( $attachment_id );

		// If EXIF is not supported.
		if ( ! $exif_result['supported'] ) {
			$error_html = sprintf(
				'<p style="margin:0;padding:8px 10px;background:#f9f9f9;border-left:3px solid #dcdcde;color:#a7aaad;font-size:12px;">%s</p>',
				esc_html( $exif_result['error'] )
			);
			return $error_html;
		}

		// If EXIF is supported but no data found.
		if ( ! $exif_result['has_exif'] ) {
			$error_html = sprintf(
				'<p style="margin:0;padding:8px 10px;background:#f9f9f9;border-left:3px solid #dcdcde;color:#a7aaad;font-size:12px;">%s</p>',
				esc_html( $exif_result['error'] )
			);
			return $error_html;
		}

		// Render EXIF data.
		$data = $exif_result['data'];

		// Category labels for grouping.
		$category_labels = [
			'camera'   => esc_html__( 'Camera', 'media-library-tools' ),
			'image'    => esc_html__( 'Image', 'media-library-tools' ),
			'exposure' => esc_html__( 'Exposure', 'media-library-tools' ),
			'gps'      => esc_html__( 'GPS', 'media-library-tools' ),
			'date'     => esc_html__( 'Date', 'media-library-tools' ),
			'other'    => esc_html__( 'Other', 'media-library-tools' ),
		];

		ob_start();
		?>
		<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;padding:10px 12px;font-size:12px;">
			<?php
			$first_group = true;
			foreach ( $data as $category => $fields ) {
				if ( empty( $fields ) ) {
					continue;
				}

				// Add separator between groups.
				if ( ! $first_group ) {
					?>
					<div style="margin:10px 0;border-top:1px solid #e5e5e5;"></div>
					<?php
				}

				// Category label.
				?>
				<div style="font-size:11px;color:#646970;text-transform:uppercase;letter-spacing:.5px;font-weight:600;margin-bottom:8px;">
					<?php echo esc_html( $category_labels[ $category ] ?? $category ); ?>
				</div>

				<?php
				// Render fields.
				foreach ( $fields as $label => $value ) {
					?>
					<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:6px;line-height:1.4;">
						<span style="color:#8c8f94;font-weight:500;"><?php echo esc_html( $label ); ?></span>
						<span style="color:#2c3338;word-break:break-word;"><?php echo esc_html( $value ); ?></span>
					</div>
					<?php
				}

				$first_group = false;
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}
}
