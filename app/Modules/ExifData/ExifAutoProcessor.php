<?php
/**
 * EXIF Auto-Processor — automatically processes EXIF data on media upload.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\ExifData;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Helpers\ExifFilter;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * ExifAutoProcessor — processes EXIF on upload based on settings.
 */
class ExifAutoProcessor {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Process EXIF for newly uploaded attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function process_on_upload( int $attachment_id ): void {
		// Get settings.
		$options = Fns::get_options();
		if ( empty( $options['auto_process_exif'] ) ) {
			return; // Feature disabled.
		}

		// Check MIME type.
		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, [ 'image/jpeg', 'image/jpg', 'image/tiff', 'image/webp' ], true ) ) {
			return; // Not an image with EXIF support.
		}

		// Get file path.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return; // File not found.
		}

		// Read EXIF.
		$exif_data = $this->read_exif( $file_path );
		if ( empty( $exif_data ) ) {
			return; // No EXIF data.
		}

		// Apply auto-processing rules.
		$this->apply_rules( $attachment_id, $file_path, $exif_data, $options );
	}

	/**
	 * Read EXIF data from file.
	 *
	 * @param string $file_path Path to image file.
	 *
	 * @return array Raw EXIF data.
	 */
	private function read_exif( string $file_path ): array {
		if ( ! function_exists( 'exif_read_data' ) ) {
			return [];
		}

		$exif = @exif_read_data( $file_path, null, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return is_array( $exif ) ? $exif : [];
	}

	/**
	 * Apply auto-processing rules based on settings.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $file_path     Path to the uploaded file.
	 * @param array  $exif_data     Raw EXIF data.
	 * @param array  $options       Plugin options.
	 *
	 * @return void
	 */
	private function apply_rules( int $attachment_id, string $file_path, array $exif_data, array $options ): void {
		// EXIF stored for filtering/sorting: read again after a GPS removal, so the
		// stored location reflects the file whether or not the removal succeeded.
		$index_data = $exif_data;

		// Rule 1: Remove GPS if enabled (Pro).
		if ( ! empty( $options['auto_remove_gps_on_upload'] ) && $this->remove_gps_metadata( $attachment_id ) ) {
			clearstatcache( true, $file_path );
			ExifDataReader::clear_cache();
			$index_data = $this->read_exif( $file_path );
		}

		ExifFilter::store_exif_meta( $attachment_id, $index_data );

		// Rule 2: Fill missing metadata from EXIF.
		if ( ! empty( $options['auto_fill_metadata_from_exif'] ) ) {
			$this->fill_missing_metadata( $attachment_id, $exif_data );
		}

		// Rule 3: Set featured image date from EXIF (if available).
		if ( ! empty( $options['auto_set_date_from_exif'] ) ) {
			$this->set_date_from_exif( $attachment_id, $exif_data );
		}

		/**
		 * Allow plugins/themes to apply custom EXIF processing rules.
		 *
		 * @param int   $attachment_id The attachment ID.
		 * @param array $exif_data     Raw EXIF data.
		 * @param array $options       Plugin options.
		 */
		do_action( 'tsmlt_auto_process_exif', $attachment_id, $exif_data, $options );
	}

	/**
	 * Remove GPS metadata from attachment by requesting Pro's stripper.
	 *
	 * GPS removal is a Pro feature: the free plugin only fires the hook, and
	 * only while the Pro licence is active.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool Whether the hook was fired.
	 */
	private function remove_gps_metadata( int $attachment_id ): bool {
		if ( ! tsmlt()->has_pro() ) {
			return false;
		}

		/**
		 * Fires when an uploaded image should have its GPS data removed.
		 *
		 * The Pro plugin hooks here to do the removal.
		 *
		 * @param int $attachment_id The attachment ID.
		 */
		do_action( 'tsmlt_auto_remove_gps_on_upload', $attachment_id );

		return true;
	}

	/**
	 * Fill missing attachment metadata from EXIF.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $exif_data     Raw EXIF data.
	 *
	 * @return void
	 */
	private function fill_missing_metadata( int $attachment_id, array $exif_data ): void {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment ) {
			return;
		}

		$updates = [];

		// Fill title from camera make/model if not set.
		if ( empty( $attachment->post_title ) ) {
			$make  = $this->get_exif_field( $exif_data, 'Make' );
			$model = $this->get_exif_field( $exif_data, 'Model' );
			if ( $make || $model ) {
				$updates['post_title'] = trim( "$make $model" );
			}
		}

		// Fill alt text from image filename if not set.
		if ( ! get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) {
			$filename = basename( get_attached_file( $attachment_id ) );
			$filename = pathinfo( $filename, PATHINFO_FILENAME );
			$filename = sanitize_file_name( $filename );
			if ( $filename ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $filename );
			}
		}

		// Fill caption from image description (EXIF) if not set.
		if ( empty( $attachment->post_excerpt ) ) {
			$description = $this->get_exif_field( $exif_data, 'ImageDescription', [ 'IFD0' ] );
			if ( $description ) {
				$updates['post_excerpt'] = sanitize_text_field( $description );
			}
		}

		// Apply updates.
		if ( ! empty( $updates ) ) {
			$updates['ID'] = $attachment_id;
			wp_update_post( $updates );
		}
	}

	/**
	 * Set attachment date from EXIF date taken.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $exif_data     Raw EXIF data.
	 *
	 * @return void
	 */
	private function set_date_from_exif( int $attachment_id, array $exif_data ): void {
		// Try to get date from DateTimeOriginal, then DateTime.
		$date_str = $this->get_exif_field( $exif_data, 'DateTimeOriginal', [ 'EXIF' ] );
		if ( ! $date_str ) {
			$date_str = $this->get_exif_field( $exif_data, 'DateTime', [ 'IFD0' ] );
		}

		if ( ! $date_str ) {
			return; // No date found.
		}

		// Convert EXIF date format (YYYY:MM:DD HH:MM:SS) to MySQL format (YYYY-MM-DD HH:MM:SS).
		// Only replace the first two colons (in the date part, not the time part).
		$date_str = preg_replace( '/:/', '-', $date_str, 2 );

		// Validate date format.
		$date_obj = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date_str );
		if ( ! $date_obj ) {
			return;
		}

		// Update attachment post date.
		wp_update_post( [
			'ID'                => $attachment_id,
			'post_date'         => $date_str,
			'post_date_gmt'     => get_gmt_from_date( $date_str ),
		] );

	}

	/**
	 * Get EXIF field value from raw data.
	 *
	 * @param array      $exif_data Raw EXIF data.
	 * @param string     $field     Field name.
	 * @param array|null $sections  Sections to search (default: all).
	 *
	 * @return string|null
	 */
	private function get_exif_field( array $exif_data, string $field, ?array $sections = null ): ?string {
		if ( null === $sections ) {
			$sections = [ 'IFD0', 'EXIF', 'GPS' ];
		}

		foreach ( $sections as $section ) {
			if ( isset( $exif_data[ $section ][ $field ] ) ) {
				$value = $exif_data[ $section ][ $field ];
				// Handle array values (e.g., ISOSpeedRatings).
				if ( is_array( $value ) ) {
					$value = $value[0];
				}
				return (string) $value;
			}
		}

		return null;
	}

}
