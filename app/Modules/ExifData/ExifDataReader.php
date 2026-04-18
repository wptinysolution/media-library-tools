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
			$result                             = [
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
			$result                             = [
				'supported' => true,
				'has_exif'  => false,
				'error'     => esc_html__( 'Image file not found on server.', 'media-library-tools' ),
			];
			self::$exif_cache[ $attachment_id ] = $result;
			return $result;
		}

		// Check if PHP EXIF extension is available.
		if ( ! function_exists( 'exif_read_data' ) ) {
			$result                             = [
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
			$result                             = [
				'supported' => true,
				'has_exif'  => false,
				'error'     => esc_html__( 'No EXIF data found for this image.', 'media-library-tools' ),
			];
			self::$exif_cache[ $attachment_id ] = $result;
			return $result;
		}

		// Format and return EXIF data.
		$result                             = [
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
			'camera'   => [
				'Make'     => esc_html__( 'Make', 'media-library-tools' ),
				'Model'    => esc_html__( 'Model', 'media-library-tools' ),
				'Software' => esc_html__( 'Software', 'media-library-tools' ),
			],
			'image'    => [
				'ImageWidth'      => esc_html__( 'Image Width', 'media-library-tools' ),
				'ImageLength'     => esc_html__( 'Image Height', 'media-library-tools' ),
				'ExifImageWidth'  => esc_html__( 'EXIF Width', 'media-library-tools' ),
				'ExifImageLength' => esc_html__( 'EXIF Height', 'media-library-tools' ),
				'Orientation'     => esc_html__( 'Orientation', 'media-library-tools' ),
				'ColorSpace'      => esc_html__( 'Color Space', 'media-library-tools' ),
				'BitsPerSample'   => esc_html__( 'Bits Per Sample', 'media-library-tools' ),
			],
			'exposure' => [
				'ExposureTime'          => esc_html__( 'Exposure Time', 'media-library-tools' ),
				'FNumber'               => esc_html__( 'Aperture', 'media-library-tools' ),
				'ISOSpeedRatings'       => esc_html__( 'ISO', 'media-library-tools' ),
				'ExposureProgram'       => esc_html__( 'Exposure Program', 'media-library-tools' ),
				'ExposureBiasValue'     => esc_html__( 'Exposure Bias', 'media-library-tools' ),
				'MeteringMode'          => esc_html__( 'Metering Mode', 'media-library-tools' ),
				'Flash'                 => esc_html__( 'Flash', 'media-library-tools' ),
				'FocalLength'           => esc_html__( 'Focal Length', 'media-library-tools' ),
				'FocalLengthIn35mmFilm' => esc_html__( '35mm Focal Length', 'media-library-tools' ),
			],
			'gps'      => [
				'GPSLatitude'  => esc_html__( 'Latitude', 'media-library-tools' ),
				'GPSLongitude' => esc_html__( 'Longitude', 'media-library-tools' ),
				'GPSAltitude'  => esc_html__( 'Altitude', 'media-library-tools' ),
			],
			'date'     => [
				'DateTime'          => esc_html__( 'Date Modified', 'media-library-tools' ),
				'DateTimeOriginal'  => esc_html__( 'Date Taken', 'media-library-tools' ),
				'DateTimeDigitized' => esc_html__( 'Date Digitized', 'media-library-tools' ),
			],
			'other'    => [
				'Artist'           => esc_html__( 'Artist', 'media-library-tools' ),
				'Copyright'        => esc_html__( 'Copyright', 'media-library-tools' ),
				'UserComment'      => esc_html__( 'User Comment', 'media-library-tools' ),
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
				if ( null !== $value && '' !== $value ) {
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

		$dms     = $raw['GPS'][ $exif_key ];
		$ref_key = 'GPSLatitude' === $exif_key ? 'GPSLatitudeRef' : 'GPSLongitudeRef';
		$ref     = $raw['GPS'][ $ref_key ] ?? '';

		if ( ! is_array( $dms ) || count( $dms ) < 3 ) {
			return null;
		}

		$decimal  = $this->dms_to_decimal( $dms );
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

	/**
	 * Get images with EXIF data for the EXIF Data page (free version - view only).
	 *
	 * @param int    $limit  Limit per page.
	 * @param int    $offset Offset.
	 * @param string $sort   Sort field.
	 * @param string $order  Sort order (ASC/DESC).
	 * @param string $filter Filter: 'all', 'with_exif', 'without_exif'.
	 * @param string $search Optional search term (matches image title).
	 *
	 * @return array
	 */
	public function get_images_with_exif( int $limit = 50, int $offset = 0, string $sort = 'default', string $order = 'DESC', string $filter = 'all', string $search = '' ): array {
		// EXIF-based sorting or filtering requires fetching all and processing in PHP.
		$is_exif_sort   = in_array( $sort, [ 'exif_date', 'camera' ], true );
		$is_exif_filter = in_array( $filter, [ 'with_exif', 'without_exif' ], true );
		$needs_php_pass = $is_exif_sort || $is_exif_filter;
		$fetch_limit    = $needs_php_pass ? -1 : $limit;
		$fetch_offset   = $needs_php_pass ? 0 : $offset;

		$query_args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $fetch_limit,
			'offset'         => $fetch_offset,
			'post_mime_type' => 'image',
			'orderby'        => 'ID',
			'order'          => 'DESC',
		];

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		// WP-level sorting for date and title.
		if ( 'date' === $sort ) {
			$query_args['orderby'] = 'date';
			$query_args['order'] = $order;
		} elseif ( 'title' === $sort ) {
			$query_args['orderby'] = 'title';
			$query_args['order'] = $order;
		}

		$attachments = get_posts( $query_args );

		$images = [];
		foreach ( $attachments as $attachment ) {
			$file_path    = get_attached_file( $attachment->ID );
			$file_exists  = $file_path && file_exists( $file_path );
			$exif_summary = $this->get_exif_summary_for_attachment( $attachment->ID, $file_exists ? $file_path : null );
			$has_exif     = ! empty( $exif_summary['has_exif'] );

			// Get thumbnail URL.
			$thumb_url = wp_get_attachment_thumb_url( $attachment->ID );
			if ( ! $thumb_url ) {
				$thumb_url = wp_get_attachment_url( $attachment->ID );
			}

			$images[] = [
				'attachment_id' => $attachment->ID,
				'title'         => $attachment->post_title,
				'url'           => $thumb_url,
				'has_exif'      => $has_exif,
				'exif_summary'  => $exif_summary,
				'stripped'      => false,
			];
		}

		// PHP-level search filter (only needed when $needs_php_pass, since WP_Query 's' handled the WP-level path).
		if ( $needs_php_pass && '' !== $search && ! empty( $images ) ) {
			$images = array_values(
				array_filter(
					$images,
					function ( $img ) use ( $search ) {
						return false !== stripos( $img['title'], $search );
					}
				)
			);
		}

		// PHP-level filtering by EXIF presence.
		if ( $is_exif_filter && ! empty( $images ) ) {
			$images = array_values(
				array_filter(
					$images,
					function ( $img ) use ( $filter ) {
						if ( 'with_exif' === $filter ) {
							return ! empty( $img['has_exif'] );
						}
						return empty( $img['has_exif'] );
					}
				)
			);
		}

		// PHP-level sorting for EXIF fields.
		if ( $is_exif_sort && ! empty( $images ) ) {
			usort(
				$images,
				function ( $a, $b ) use ( $sort, $order ) {
					$val_a = '';
					$val_b = '';

					if ( 'exif_date' === $sort ) {
						$val_a = $a['exif_summary']['other']['date_time_original'] ?? '';
						$val_b = $b['exif_summary']['other']['date_time_original'] ?? '';
					} elseif ( 'camera' === $sort ) {
						$make_a  = $a['exif_summary']['camera']['make'] ?? '';
						$model_a = $a['exif_summary']['camera']['model'] ?? '';
						$val_a   = trim( $make_a . ' ' . $model_a );

						$make_b  = $b['exif_summary']['camera']['make'] ?? '';
						$model_b = $b['exif_summary']['camera']['model'] ?? '';
						$val_b   = trim( $make_b . ' ' . $model_b );
					}

					// Empty values sort last regardless of direction.
					if ( '' === $val_a && '' !== $val_b ) {
						return 1;
					}
					if ( '' !== $val_a && '' === $val_b ) {
						return -1;
					}

					$cmp = strcmp( $val_a, $val_b );
					return 'DESC' === $order ? -$cmp : $cmp;
				}
			);
		}

		// When PHP handled sorting/filtering, we know the true filtered total.
		$filtered_total = $needs_php_pass ? count( $images ) : null;

		// Apply pagination after PHP-level sorting/filtering.
		if ( $needs_php_pass ) {
			$images = array_slice( $images, $offset, $limit );
		}

		return [
			'images'         => $images,
			'filtered_total' => $filtered_total,
		];
	}

	/**
	 * Get total count of image attachments.
	 *
	 * @return int
	 */
	public function get_attachment_count(): int {
		$query = new \WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			]
		);
		return (int) $query->found_posts;
	}

	/**
	 * Get EXIF summary for an attachment, falling back to post meta for non-JPEG types.
	 *
	 * @param int         $attachment_id The attachment ID.
	 * @param string|null $file_path     Path to the image file (null if not available).
	 *
	 * @return array
	 */
	public function get_exif_summary_for_attachment( int $attachment_id, ?string $file_path ): array {
		// Start with file-based EXIF (works for JPEG/TIFF/WebP; getimagesize fields for all types).
		$summary = $file_path ? $this->get_exif_summary( $file_path ) : [];

		// Always overlay post meta on top — meta holds user-edited values for any image type.
		$meta = get_post_meta( $attachment_id, '_tsmlt_exif_meta', true );
		if ( ! is_array( $meta ) || empty( $meta ) ) {
			return $summary;
		}

		// Merge camera fields from meta.
		if ( ! empty( $meta['make'] ) || ! empty( $meta['model'] ) ) {
			$camera = $summary['camera'] ?? [];
			if ( ! empty( $meta['make'] ) ) {
				$camera['make'] = $meta['make'];
			}
			if ( ! empty( $meta['model'] ) ) {
				$camera['model'] = $meta['model'];
			}
			$summary['camera'] = $camera;
		}

		// Merge GPS fields from meta.
		if ( isset( $meta['gps_lat'] ) || isset( $meta['gps_lng'] ) ) {
			$summary['gps'] = [
				'latitude'     => $meta['gps_lat'] ?? ( $summary['gps']['latitude'] ?? null ),
				'longitude'    => $meta['gps_lng'] ?? ( $summary['gps']['longitude'] ?? null ),
				'has_location' => true,
			];
		}

		// Merge all other editable fields from meta, overwriting file-based values.
		$other = $summary['other'] ?? [];
		$meta_other_map = [
			'date_time_original' => 'date_time_original',
			'iso'                => 'iso',
			'aperture'           => 'f_number',
			'shutter_speed'      => 'exposure_time',
			'copyright'          => 'copyright',
			'artist'             => 'artist',
			'color_space'        => 'color_space',
		];
		foreach ( $meta_other_map as $meta_key => $other_key ) {
			if ( isset( $meta[ $meta_key ] ) && '' !== (string) $meta[ $meta_key ] ) {
				$other[ $other_key ] = (string) $meta[ $meta_key ];
			}
		}
		if ( ! empty( $other ) ) {
			$summary['other'] = $other;
		}

		$summary['has_exif'] = ! empty( $summary['camera'] ) || ! empty( $summary['gps'] ) || ! empty( $summary['other'] );

		return $summary;
	}

	/**
	 * Get EXIF summary for display in the list (camera, gps, other).
	 *
	 * @param string $file_path Path to the image file.
	 *
	 * @return array
	 */
	public function get_exif_summary( string $file_path ): array {
		if ( ! function_exists( 'exif_read_data' ) ) {
			return [];
		}

		$exif = @exif_read_data( $file_path, null, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $exif ) || empty( $exif ) ) {
			return [];
		}

		$summary = [];

		// Camera info (IFD0).
		if ( isset( $exif['IFD0'] ) ) {
			$camera = [];
			if ( ! empty( $exif['IFD0']['Make'] ) ) {
				$camera['make'] = $exif['IFD0']['Make'];
			}
			if ( ! empty( $exif['IFD0']['Model'] ) ) {
				$camera['model'] = $exif['IFD0']['Model'];
			}
			if ( ! empty( $exif['IFD0']['Software'] ) ) {
				$camera['software'] = $exif['IFD0']['Software'];
			}
			if ( ! empty( $camera ) ) {
				$summary['camera'] = $camera;
			}
		}

		// GPS info.
		$gps          = [];
		$has_location = false;

		if ( isset( $exif['GPS'] ) ) {
			if ( ! empty( $exif['GPS']['GPSLatitude'] ) && ! empty( $exif['GPS']['GPSLatitudeRef'] ) ) {
				$gps['latitude'] = $this->convert_gps_coordinate( $exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef'] );
				$has_location    = true;
			}
			if ( ! empty( $exif['GPS']['GPSLongitude'] ) && ! empty( $exif['GPS']['GPSLongitudeRef'] ) ) {
				$gps['longitude'] = $this->convert_gps_coordinate( $exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef'] );
			}
			if ( ! empty( $exif['GPS']['GPSAltitude'] ) ) {
				$gps['altitude'] = $exif['GPS']['GPSAltitude'];
			}
		}

		if ( ! empty( $gps ) ) {
			$gps['has_location'] = $has_location;
			$summary['gps']      = $gps;
		}

		// Gather data from all sections into $other.
		$other        = [];
		$color_space_map = [ 1 => 'sRGB', 65535 => 'Uncalibrated' ];

		// EXIF SubIFD — exposure / camera settings.
		if ( isset( $exif['EXIF'] ) ) {
			if ( ! empty( $exif['EXIF']['DateTimeOriginal'] ) ) {
				$other['date_time_original'] = $exif['EXIF']['DateTimeOriginal'];
			}
			if ( ! empty( $exif['EXIF']['ISOSpeedRatings'] ) ) {
				$other['iso'] = is_array( $exif['EXIF']['ISOSpeedRatings'] ) ? implode( ', ', $exif['EXIF']['ISOSpeedRatings'] ) : (string) $exif['EXIF']['ISOSpeedRatings'];
			}
			if ( ! empty( $exif['EXIF']['FocalLength'] ) ) {
				$other['focal_length'] = $this->format_rational( $exif['EXIF']['FocalLength'] ) . ' mm';
			}
			if ( ! empty( $exif['EXIF']['ExposureTime'] ) ) {
				$other['exposure_time'] = $this->format_rational( $exif['EXIF']['ExposureTime'] ) . 's';
			}
			if ( ! empty( $exif['EXIF']['FNumber'] ) ) {
				$other['f_number'] = 'f/' . $this->format_rational( $exif['EXIF']['FNumber'] );
			}
			if ( isset( $exif['EXIF']['Flash'] ) ) {
				$other['flash'] = ( 0 !== ( (int) $exif['EXIF']['Flash'] & 1 ) ) ? 'Fired' : 'Did not fire';
			}
			if ( isset( $exif['EXIF']['WhiteBalance'] ) ) {
				$wb_map               = [ 0 => 'Auto', 1 => 'Manual' ];
				$other['white_balance'] = $wb_map[ (int) $exif['EXIF']['WhiteBalance'] ] ?? (string) $exif['EXIF']['WhiteBalance'];
			}
			if ( isset( $exif['EXIF']['ExposureMode'] ) ) {
				$em_map                 = [ 0 => 'Auto', 1 => 'Manual', 2 => 'Auto bracket' ];
				$other['exposure_mode'] = $em_map[ (int) $exif['EXIF']['ExposureMode'] ] ?? (string) $exif['EXIF']['ExposureMode'];
			}
			if ( isset( $exif['EXIF']['MeteringMode'] ) ) {
				$mm_map                = [ 0 => 'Unknown', 1 => 'Average', 2 => 'Center', 3 => 'Spot', 4 => 'Multi-spot', 5 => 'Pattern', 6 => 'Partial' ];
				$other['metering_mode'] = $mm_map[ (int) $exif['EXIF']['MeteringMode'] ] ?? (string) $exif['EXIF']['MeteringMode'];
			}
			if ( ! empty( $exif['EXIF']['ColorSpace'] ) ) {
				$other['color_space'] = $color_space_map[ (int) $exif['EXIF']['ColorSpace'] ] ?? (string) $exif['EXIF']['ColorSpace'];
			}
		}

		// IFD0 — image-level metadata.
		if ( isset( $exif['IFD0'] ) ) {
			// Orientation lives in IFD0, not EXIF SubIFD.
			if ( ! empty( $exif['IFD0']['Orientation'] ) ) {
				$orient_map = [
					1 => 'Normal',
					2 => 'Flipped horizontal',
					3 => 'Rotated 180°',
					4 => 'Flipped vertical',
					5 => 'Rotated 90° CW + flip',
					6 => 'Rotated 90° CW',
					7 => 'Rotated 90° CCW + flip',
					8 => 'Rotated 90° CCW',
				];
				$other['orientation'] = $orient_map[ (int) $exif['IFD0']['Orientation'] ] ?? (string) $exif['IFD0']['Orientation'];
			}
			// Dimensions from IFD0 (more reliable than EXIF SubIFD).
			if ( ! empty( $exif['IFD0']['ImageWidth'] ) ) {
				$other['image_width'] = (string) $exif['IFD0']['ImageWidth'];
			}
			if ( ! empty( $exif['IFD0']['ImageLength'] ) ) {
				$other['image_height'] = (string) $exif['IFD0']['ImageLength'];
			}
			if ( ! empty( $exif['IFD0']['XResolution'] ) ) {
				$other['x_resolution'] = (string) (int) $this->convert_rational( $exif['IFD0']['XResolution'] ) . ' dpi';
			}
			if ( ! empty( $exif['IFD0']['YResolution'] ) ) {
				$other['y_resolution'] = (string) (int) $this->convert_rational( $exif['IFD0']['YResolution'] ) . ' dpi';
			}
			if ( ! empty( $exif['IFD0']['BitsPerSample'] ) ) {
				$bps                      = $exif['IFD0']['BitsPerSample'];
				$other['bits_per_sample'] = is_array( $bps ) ? implode( ', ', $bps ) : (string) $bps;
			}
			if ( ! empty( $exif['IFD0']['Copyright'] ) ) {
				$other['copyright'] = $exif['IFD0']['Copyright'];
			}
			if ( ! empty( $exif['IFD0']['Artist'] ) ) {
				$other['artist'] = $exif['IFD0']['Artist'];
			}
			// Color space from IFD0 if not already set from EXIF SubIFD.
			if ( empty( $other['color_space'] ) && ! empty( $exif['IFD0']['ColorSpace'] ) ) {
				$other['color_space'] = $color_space_map[ (int) $exif['IFD0']['ColorSpace'] ] ?? (string) $exif['IFD0']['ColorSpace'];
			}
			// ICC colour profile — binary blob, extract description string.
			if ( ! empty( $exif['IFD0']['InterColorProfile'] ) ) {
				$profile_raw = $exif['IFD0']['InterColorProfile'];
				if ( is_string( $profile_raw ) && strlen( $profile_raw ) > 128 ) {
					$desc = substr( $profile_raw, 128, 64 );
					$desc = preg_replace( '/[^\x20-\x7E]/', '', $desc );
					if ( ! empty( trim( $desc ) ) ) {
						$other['color_profile'] = trim( $desc );
					}
				}
			}
		}

		// getimagesize() — reliable for dimensions, bit depth, alpha, mime, channels.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$img_info = @getimagesize( $file_path );
		if ( is_array( $img_info ) ) {
			// Dimensions — most reliable source.
			if ( empty( $other['image_width'] ) && ! empty( $img_info[0] ) ) {
				$other['image_width'] = (string) $img_info[0];
			}
			if ( empty( $other['image_height'] ) && ! empty( $img_info[1] ) ) {
				$other['image_height'] = (string) $img_info[1];
			}
			if ( isset( $img_info['bits'] ) ) {
				$other['bit_depth'] = (string) $img_info['bits'];
			}
			if ( isset( $img_info['channels'] ) ) {
				// channels: 3 = RGB (no alpha), 4 = RGBA/CMYK (has alpha or CMYK).
				$other['alpha_channel'] = 4 === (int) $img_info['channels'] ? 'Yes' : 'No';
			} else {
				// For PNG, GIF, WebP — PHP doesn't set channels, use image type.
				$type = $img_info[2] ?? 0;
				if ( in_array( $type, [ IMAGETYPE_PNG, IMAGETYPE_WEBP ], true ) ) {
					// Check via GD if alpha channel is present.
					if ( function_exists( 'imagecreatefrompng' ) && IMAGETYPE_PNG === $type ) {
						// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
						$im = @imagecreatefrompng( $file_path );
						if ( $im ) {
							$other['alpha_channel'] = imageistruecolor( $im ) ? 'Yes' : 'No';
							imagedestroy( $im );
						}
					}
				}
			}
			if ( isset( $img_info['mime'] ) ) {
				$other['mime_type'] = $img_info['mime'];
			}
		}

		if ( ! empty( $other ) ) {
			$summary['other'] = $other;
		}

		// Only mark as having EXIF when meaningful data exists.
		$summary['has_exif'] = ! empty( $summary['camera'] ) || ! empty( $summary['gps'] ) || ! empty( $summary['other'] );

		return $summary;
	}

	/**
	 * Convert GPS coordinate to decimal degrees.
	 *
	 * @param array  $coord    GPS coordinate array.
	 * @param string $ref      Direction reference (N, S, E, W).
	 *
	 * @return string
	 */
	private function convert_gps_coordinate( array $coord, string $ref ): string {
		if ( empty( $coord ) || count( $coord ) < 3 ) {
			return '';
		}

		$degrees = isset( $coord[0] ) ? $this->convert_rational( $coord[0] ) : 0;
		$minutes = isset( $coord[1] ) ? $this->convert_rational( $coord[1] ) : 0;
		$seconds = isset( $coord[2] ) ? $this->convert_rational( $coord[2] ) : 0;

		$decimal = $degrees + ( $minutes / 60 ) + ( $seconds / 3600 );

		if ( 'S' === $ref || 'W' === $ref ) {
			$decimal = -$decimal;
		}

		// Format with up to 6 decimals, trim trailing zeros.
		$formatted = rtrim( number_format( $decimal, 6 ), '0' );
		return rtrim( $formatted, '.' );
	}

	/**
	 * Format a rational EXIF value as a human-readable string.
	 * Keeps fractions like "1/250" intact; simplifies whole numbers like "50/1" → "50".
	 *
	 * @param mixed $value Rational value (string "n/d", numeric, or array).
	 *
	 * @return string
	 */
	private function format_rational( $value ): string {
		if ( is_string( $value ) && strpos( $value, '/' ) !== false ) {
			[ $n, $d ] = array_pad( explode( '/', $value, 2 ), 2, 1 );
			$n = (float) $n;
			$d = (float) $d;
			if ( 0.0 === $d ) {
				return (string) $n;
			}
			// Whole number — simplify.
			if ( 1.0 === $d || 0.0 === fmod( $n, $d ) ) {
				return (string) (int) ( $n / $d );
			}
			// Small fraction (e.g. exposure time) — keep as fraction.
			if ( $n < $d ) {
				return (int) $n . '/' . (int) $d;
			}
			// Decimal.
			return rtrim( rtrim( number_format( $n / $d, 2 ), '0' ), '.' );
		}
		if ( is_numeric( $value ) ) {
			return rtrim( rtrim( number_format( (float) $value, 2 ), '0' ), '.' );
		}
		return (string) $value;
	}

	/**
	 * Convert rational number to float.
	 *
	 * @param mixed $value Rational value.
	 *
	 * @return float
	 */
	private function convert_rational( $value ): float {
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}
		// Handle rational strings like "23/1", "26/1", "4284/100".
		if ( is_string( $value ) && strpos( $value, '/' ) !== false ) {
			$parts = explode( '/', $value, 2 );
			if ( count( $parts ) === 2 && is_numeric( $parts[0] ) && is_numeric( $parts[1] ) && (float) $parts[1] !== 0.0 ) {
				return (float) $parts[0] / (float) $parts[1];
			}
		}
		if ( is_array( $value ) && isset( $value[0] ) && isset( $value[1] ) && $value[1] > 0 ) {
			return $value[0] / $value[1];
		}
		return 0;
	}

	/**
	 * Get editable EXIF fields for an attachment in a flat structure.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array Editable EXIF fields.
	 */
	public function get_editable_exif( int $attachment_id ): array {
		$mime     = get_post_mime_type( $attachment_id );
		$is_jpeg  = in_array( $mime, [ 'image/jpeg', 'image/jpg' ], true );

		// For non-JPEG images, return fields stored in post meta (if any).
		if ( ! $is_jpeg ) {
			$meta = get_post_meta( $attachment_id, '_tsmlt_exif_meta', true );
			$meta = is_array( $meta ) ? $meta : [];
			return array_merge(
				[
					'supported'          => true,
					'make'               => '',
					'model'              => '',
					'date_time_original' => '',
					'iso'                => null,
					'aperture'           => null,
					'shutter_speed'      => null,
					'gps_lat'            => null,
					'gps_lng'            => null,
					'copyright'          => '',
					'artist'             => '',
					'color_space'        => '',
				],
				$meta
			);
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return [
				'supported' => false,
				'message'   => esc_html__( 'Image file not found on server.', 'media-library-tools' ),
			];
		}

		if ( ! function_exists( 'exif_read_data' ) ) {
			return [
				'supported' => false,
				'message'   => esc_html__( 'PHP EXIF extension is not enabled on this server.', 'media-library-tools' ),
			];
		}

		$raw = @exif_read_data( $file_path, null, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return [
				'supported'          => true,
				'make'               => '',
				'model'              => '',
				'date_time_original' => '',
				'iso'                => null,
				'aperture'           => null,
				'shutter_speed'      => null,
				'gps_lat'            => null,
				'gps_lng'            => null,
				'copyright'          => '',
				'artist'             => '',
				'color_space'        => '',
			];
		}

		$color_space_raw = $this->find_exif_field( $raw, 'ColorSpace' );
		$color_space_map = [ 1 => 'sRGB', 65535 => 'Uncalibrated' ];
		$color_space     = isset( $color_space_map[ $color_space_raw ] ) ? $color_space_map[ $color_space_raw ] : ( null !== $color_space_raw ? (string) $color_space_raw : '' );

		return [
			'supported'          => true,
			'make'               => $this->find_exif_field( $raw, 'Make', '' ),
			'model'              => $this->find_exif_field( $raw, 'Model', '' ),
			'date_time_original' => $this->find_exif_field( $raw, 'DateTimeOriginal', '' ),
			'iso'                => $this->parse_iso( $this->find_exif_field( $raw, 'ISOSpeedRatings' ) ),
			'aperture'           => $this->parse_aperture( $this->find_exif_field( $raw, 'FNumber' ) ),
			'shutter_speed'      => $this->parse_shutter_speed( $this->find_exif_field( $raw, 'ExposureTime' ) ),
			'gps_lat'            => $this->parse_gps_decimal( $raw, 'GPSLatitude', 'GPSLatitudeRef', 'S' ),
			'gps_lng'            => $this->parse_gps_decimal( $raw, 'GPSLongitude', 'GPSLongitudeRef', 'W' ),
			'copyright'          => $this->find_exif_field( $raw, 'Copyright', '' ),
			'artist'             => $this->find_exif_field( $raw, 'Artist', '' ),
			'color_space'        => $color_space,
		];
	}

	/**
	 * Find an EXIF field across IFD0, EXIF, and GPS sections.
	 *
	 * @param array  $raw     Raw EXIF data.
	 * @param string $field   Field name.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 */
	private function find_exif_field( array $raw, string $field, $default = null ) {
		foreach ( [ 'IFD0', 'EXIF', 'GPS' ] as $section ) {
			if ( isset( $raw[ $section ][ $field ] ) ) {
				return $raw[ $section ][ $field ];
			}
		}
		return $default;
	}

	/**
	 * Parse ISO value from EXIF.
	 *
	 * @param mixed $value Raw ISO value.
	 *
	 * @return int|null
	 */
	private function parse_iso( $value ) {
		if ( ! $value ) {
			return null;
		}
		if ( is_array( $value ) ) {
			$value = $value[0];
		}
		return (int) $value > 0 ? (int) $value : null;
	}

	/**
	 * Parse aperture from EXIF FNumber field.
	 *
	 * @param mixed $value Raw FNumber value.
	 *
	 * @return float|null
	 */
	private function parse_aperture( $value ) {
		if ( ! $value ) {
			return null;
		}
		$float_val = $this->rational_to_float( $value );
		return $float_val > 0 ? round( $float_val, 1 ) : null;
	}

	/**
	 * Parse shutter speed from EXIF ExposureTime field.
	 *
	 * @param mixed $value Raw ExposureTime value.
	 *
	 * @return string|null
	 */
	private function parse_shutter_speed( $value ) {
		if ( ! $value ) {
			return null;
		}
		if ( is_string( $value ) && strpos( $value, '/' ) !== false ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			$float = (float) $value;
			if ( $float > 0 && $float < 1 ) {
				$denom = (int) round( 1 / $float );
				return '1/' . $denom;
			}
		}
		return null;
	}

	/**
	 * Parse GPS coordinate from EXIF to decimal degrees.
	 *
	 * @param array  $raw         Raw EXIF data.
	 * @param string $coord_key   GPS coordinate key (GPSLatitude or GPSLongitude).
	 * @param string $ref_key     GPS reference key (GPSLatitudeRef or GPSLongitudeRef).
	 * @param string $negative_ref Reference value that makes the result negative (S or W).
	 *
	 * @return float|null
	 */
	private function parse_gps_decimal( array $raw, string $coord_key, string $ref_key, string $negative_ref ) {
		if ( ! isset( $raw['GPS'][ $coord_key ] ) ) {
			return null;
		}

		$dms = $raw['GPS'][ $coord_key ];
		if ( ! is_array( $dms ) || count( $dms ) < 3 ) {
			return null;
		}

		$decimal = $this->dms_to_decimal( $dms );
		$ref     = $raw['GPS'][ $ref_key ] ?? '';

		if ( $negative_ref === $ref ) {
			$decimal = -$decimal;
		}

		return round( (float) $decimal, 6 );
	}
}
