<?php
/**
 * Convert Images module entry point.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Conversion;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Compress\CompressionAccess;
use TinySolutions\mlt\Modules\Compress\CompressionJob;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Public surface of the WebP/AVIF conversion feature.
 *
 * The AJAX layer talks only to this class, keeping request handling free of
 * knowledge about engines, capabilities, metadata or the job queue. Job
 * infrastructure is shared with compression via `CompressionJob`; only the
 * per-attachment work differs.
 */
class ConvertModule {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Boot the module.
	 */
	private function __construct() {
		ConversionCapabilities::instance();
		ConversionSettings::instance();
		ConversionMetadata::instance();
		AttachmentConverter::instance();
		ConversionManager::instance();

		// Frontend URL rewriting. Registers nothing on admin screens, so the
		// media library keeps showing the real attachment files.
		ModernImageDelivery::instance()->register_hooks();
	}

	/**
	 * Server capabilities, settings and entitlements for the Convert tab.
	 *
	 * @return array
	 */
	public function get_capabilities(): array {
		return ConversionSettings::instance()->get_payload();
	}

	/**
	 * Persist the Free-tier conversion settings.
	 *
	 * Pro-only keys are added by the Pro plugin on `tsmlt/settings/before/save`,
	 * matching how compression settings are split between the two plugins.
	 *
	 * @param array $params Sanitised request parameters.
	 *
	 * @return array
	 */
	public function save_settings( array $params ): array {
		$settings = ConversionSettings::instance();
		$stored   = get_option( 'tsmlt_settings', [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$stored = array_merge( $stored, $settings->sanitize_free_settings( $params ) );

		/**
		 * Allow the Pro plugin to persist its own conversion settings.
		 *
		 * @param array $stored Settings about to be saved.
		 * @param array $params Incoming request parameters.
		 */
		$stored = apply_filters( 'tsmlt/settings/before/save', $stored, $params );

		update_option( 'tsmlt_settings', $stored );

		return [
			'updated'  => true,
			'message'  => esc_html__( 'Conversion settings saved.', 'media-library-tools' ),
			'settings' => $settings->get_settings(),
		];
	}

	/**
	 * Start a conversion job over the supplied attachments.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array|WP_Error
	 */
	public function start_job( array $params ) {
		$ids = isset( $params['ids'] ) && is_array( $params['ids'] ) ? $params['ids'] : [];

		if ( empty( $ids ) ) {
			return new WP_Error(
				'tsmlt_conversion_no_images',
				esc_html__( 'Select at least one image to convert.', 'media-library-tools' )
			);
		}

		return CompressionJob::instance()->start_conversion( $ids, $params );
	}

	/**
	 * Library-wide conversion figures for the Convert tab.
	 *
	 * @return array
	 */
	public function get_library_status(): array {
		$job = CompressionJob::instance();

		return array_merge(
			$this->get_library_stats(),
			[
				'progress' => $job->get_progress(),
			]
		);
	}

	/**
	 * Count convertible images and how many already have converted output.
	 *
	 * Two aggregate queries rather than loading IDs, so the figure stays cheap
	 * on large libraries.
	 *
	 * @return array{total: int, converted: int, remaining: int}
	 */
	public function get_library_stats(): array {
		$total_rows = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->andIn( 'post_mime_type', ...ConversionCapabilities::SUPPORTED_SOURCES )
			->get();

		$converted_rows = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'postmeta' )
			->where( 'meta_key', '=', ConversionMetadata::META_KEY )
			->get();

		$total     = (int) ( $total_rows[0]['total'] ?? 0 );
		$converted = min( $total, (int) ( $converted_rows[0]['total'] ?? 0 ) );

		return [
			'total'     => $total,
			'converted' => $converted,
			'remaining' => max( 0, $total - $converted ),
		];
	}

	/**
	 * Queue every image in the library that has not been converted yet.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array|WP_Error
	 */
	public function start_library_job( array $params ) {
		global $wpdb;

		$limit        = CompressionAccess::instance()->get_conversion_limit();
		$include_done = ! empty( $params['include_converted'] );

		$query = Fns::DB()->select( 'ID' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->andIn( 'post_mime_type', ...ConversionCapabilities::SUPPORTED_SOURCES );

		if ( ! $include_done ) {
			// The query builder has no LEFT JOIN, so the exclusion is a prepared
			// subquery through raw(), as CompressionJob::start_library() does; the
			// meta key is a class constant and no request data reaches the SQL.
			$query->raw(
				$wpdb->prepare(
					"AND ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s )",
					ConversionMetadata::META_KEY
				)
			);
		}

		$query->orderBy( 'ID', 'DESC' );

		if ( $limit > 0 ) {
			$query->limit( $limit );
		}

		$rows = $query->get();
		$ids  = array_map( 'absint', array_column( is_array( $rows ) ? $rows : [], 'ID' ) );

		if ( empty( $ids ) ) {
			return new WP_Error(
				'tsmlt_conversion_nothing_to_do',
				$include_done
					? esc_html__( 'There are no images to convert.', 'media-library-tools' )
					: esc_html__( 'Every supported image has already been converted.', 'media-library-tools' )
			);
		}

		return CompressionJob::instance()->start_conversion( $ids, $params );
	}

	/**
	 * Convert a single attachment synchronously.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array|WP_Error
	 */
	public function convert_single( array $params ) {
		$attachment_id = absint( $params['attachment_id'] ?? 0 );
		$converter     = AttachmentConverter::instance();

		// Request context: authorise the attachment here, since `convert()` only
		// performs structural validation for the benefit of background ticks.
		$valid = $converter->validate_attachment( $attachment_id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$run_settings = ConversionSettings::instance()->resolve_run_settings( $params );

		if ( empty( $run_settings['formats'] ) ) {
			return new WP_Error(
				'tsmlt_conversion_no_formats',
				esc_html__( 'Select at least one output format that this server and your licence support.', 'media-library-tools' )
			);
		}

		$result = $converter->convert( $attachment_id, $run_settings );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['conversion'] = ConversionMetadata::instance()->get_for_display( $attachment_id );

		return $result;
	}

	/**
	 * Conversion details for one attachment.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array|WP_Error
	 */
	public function get_attachment_conversion( array $params ) {
		$attachment_id = absint( $params['attachment_id'] ?? 0 );

		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error(
				'tsmlt_conversion_not_found',
				esc_html__( 'Image not found.', 'media-library-tools' )
			);
		}

		if ( ! CompressionAccess::instance()->can_edit_attachment( $attachment_id ) ) {
			return new WP_Error(
				'tsmlt_conversion_forbidden',
				esc_html__( 'You do not have permission to view this image.', 'media-library-tools' )
			);
		}

		return [
			'attachment_id' => $attachment_id,
			'conversion'    => ConversionMetadata::instance()->get_for_display( $attachment_id ),
		];
	}

	/**
	 * Conversion summaries for many attachments at once.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array
	 */
	public function get_bulk_conversions( array $params ): array {
		$ids = isset( $params['ids'] ) && is_array( $params['ids'] ) ? $params['ids'] : [];
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		// Bound the request so a crafted payload cannot ask for the whole library.
		$ids = array_slice( $ids, 0, 200 );

		if ( empty( $ids ) ) {
			return [ 'items' => [] ];
		}

		$access  = CompressionAccess::instance();
		$allowed = [];

		foreach ( $ids as $attachment_id ) {
			if ( $access->can_edit_attachment( $attachment_id ) ) {
				$allowed[] = $attachment_id;
			}
		}

		return [ 'items' => ConversionMetadata::instance()->get_many_for_display( $allowed ) ];
	}

	/**
	 * Delete generated files for one attachment.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array|WP_Error
	 */
	public function delete_conversion( array $params ) {
		$attachment_id = absint( $params['attachment_id'] ?? 0 );
		$format        = isset( $params['format'] ) ? sanitize_key( (string) $params['format'] ) : '';

		return ConversionManager::instance()->delete_conversions( $attachment_id, $format );
	}

	/**
	 * Regenerate converted files for one attachment.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array|WP_Error
	 */
	public function regenerate_conversion( array $params ) {
		$attachment_id = absint( $params['attachment_id'] ?? 0 );
		$result        = ConversionManager::instance()->regenerate( $attachment_id, $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['conversion'] = ConversionMetadata::instance()->get_for_display( $attachment_id );

		return $result;
	}
}
