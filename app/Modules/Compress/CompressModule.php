<?php
/**
 * Compress Images module entry point.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Public surface of the Compress Images feature.
 *
 * The AJAX layer talks only to this class, which keeps request handling free of
 * knowledge about engines, jobs, backups or metadata layout.
 */
class CompressModule {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Boot the module.
	 *
	 * Instantiating the collaborators here keeps their construction in one place
	 * and matches how the other modules initialise.
	 */
	private function __construct() {
		CompressionAccess::instance();
		CompressionManager::instance();
		CompressionSettings::instance();
		CompressionMetadata::instance();
		BackupManager::instance();
		AttachmentProcessor::instance();
		RestoreManager::instance();
		CompressionJob::instance();
	}

	/**
	 * Everything the settings screen and compression modal need on load.
	 *
	 * @return array
	 */
	public function get_settings_payload(): array {
		$settings = CompressionSettings::instance();
		$manager  = CompressionManager::instance();

		return [
			'settings'  => $settings->get_settings(),
			'modes'     => $settings->get_modes(),
			'access'    => CompressionAccess::instance()->to_array(),
			'engines'   => $manager->get_available_engine_info(),
			'mimeTypes' => CompressionManager::SUPPORTED_MIME_TYPES,
		];
	}

	/**
	 * Persist the Free-tier compression settings.
	 *
	 * Pro-only keys are intentionally not written here: the Pro plugin adds them
	 * on `tsmlt/settings/before/save`, which is the established seam for Pro
	 * settings in this codebase.
	 *
	 * @param array $params Sanitised request parameters.
	 *
	 * @return array
	 */
	public function save_settings( array $params ): array {
		$settings = CompressionSettings::instance();
		$stored   = get_option( 'tsmlt_settings', [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$stored = array_merge( $stored, $settings->sanitize_free_settings( $params ) );

		/**
		 * Allow the Pro plugin to persist its own compression settings.
		 *
		 * @param array $stored Settings about to be saved.
		 * @param array $params Incoming request parameters.
		 */
		$stored = apply_filters( 'tsmlt/settings/before/save', $stored, $params );

		update_option( 'tsmlt_settings', $stored );

		return [
			'updated'  => true,
			'message'  => esc_html__( 'Compression settings saved.', 'media-library-tools' ),
			'settings' => $settings->get_settings(),
		];
	}

	/**
	 * Start a bulk compression job.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array|WP_Error
	 */
	public function start_job( array $params ) {
		$ids = isset( $params['ids'] ) && is_array( $params['ids'] ) ? $params['ids'] : [];

		if ( empty( $ids ) ) {
			return new WP_Error(
				'tsmlt_compression_no_images',
				esc_html__( 'Select at least one image to compress.', 'media-library-tools' )
			);
		}

		return CompressionJob::instance()->start( $ids, $params );
	}

	/**
	 * Current job progress.
	 *
	 * @return array
	 */
	public function get_job_progress(): array {
		return CompressionJob::instance()->get_progress();
	}

	/**
	 * Cancel the running job.
	 *
	 * @return array
	 */
	public function cancel_job(): array {
		return CompressionJob::instance()->cancel();
	}

	/**
	 * Retry the images that failed in the last job.
	 *
	 * @return array|WP_Error
	 */
	public function retry_job() {
		return CompressionJob::instance()->retry();
	}

	/**
	 * Clear the stored job.
	 *
	 * @return array
	 */
	public function reset_job(): array {
		return CompressionJob::instance()->reset();
	}

	/**
	 * Compress a single attachment synchronously.
	 *
	 * Used by the per-row action, where one image is fast enough to handle
	 * inside the request rather than through the job queue.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array|WP_Error
	 */
	public function compress_single( array $params ) {
		$attachment_id = absint( $params['attachment_id'] ?? 0 );

		if ( ! CompressionAccess::instance()->is_compression_feature_available() ) {
			return new WP_Error(
				'tsmlt_compression_engine_unavailable',
				esc_html__( 'No image compression library (ImageMagick or GD) is available on this server.', 'media-library-tools' )
			);
		}

		$processor = AttachmentProcessor::instance();

		// Request context: authorise the attachment here. `process()` performs
		// structural validation only, because it also runs from WP-Cron ticks
		// where there is no current user to check against.
		$valid = $processor->validate_attachment( $attachment_id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$run_settings = CompressionSettings::instance()->resolve_run_settings( $params );
		$result       = $processor->process( $attachment_id, $run_settings );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['compression'] = CompressionMetadata::instance()->get_for_display( $attachment_id );

		return $result;
	}

	/**
	 * Restore a single attachment from its backup.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array|WP_Error
	 */
	public function restore_single( array $params ) {
		$attachment_id = absint( $params['attachment_id'] ?? 0 );
		$result        = RestoreManager::instance()->restore( $attachment_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['compression'] = CompressionMetadata::instance()->get_for_display( $attachment_id );

		return $result;
	}

	/**
	 * Compression details for one attachment.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array|WP_Error
	 */
	public function get_attachment_details( array $params ) {
		$attachment_id = absint( $params['attachment_id'] ?? 0 );
		$access        = CompressionAccess::instance();

		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error(
				'tsmlt_compression_not_found',
				esc_html__( 'Image not found.', 'media-library-tools' )
			);
		}

		if ( ! $access->can_edit_attachment( $attachment_id ) ) {
			return new WP_Error(
				'tsmlt_compression_forbidden',
				esc_html__( 'You do not have permission to view this image.', 'media-library-tools' )
			);
		}

		return [
			'attachment_id' => $attachment_id,
			'compression'   => CompressionMetadata::instance()->get_for_display( $attachment_id ),
		];
	}

	/**
	 * Compression summaries for many attachments at once.
	 *
	 * The media table calls this once per page load rather than once per row,
	 * which keeps the listing free of N+1 queries.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array
	 */
	public function get_bulk_details( array $params ): array {
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

		return [ 'items' => CompressionMetadata::instance()->get_many_for_display( $allowed ) ];
	}
}
