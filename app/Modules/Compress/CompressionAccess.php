<?php
/**
 * Compression feature access — the single entitlement layer for Compress Images.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Centralised Free/Pro entitlement checks for the compression feature.
 *
 * Every Pro gate in the compression module routes through this class so the
 * restriction lives in exactly one place. Callers must never test
 * `tsmlt()->has_pro()` directly — ask the relevant method here instead.
 */
class CompressionAccess {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Maximum number of images a Free user may queue in a single job.
	 *
	 * Mirrors the plugin's default `media_per_page` so a Free user can compress
	 * one full page of the media table at a time.
	 */
	const FREE_JOB_LIMIT = 20;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Whether the current install has an active Pro licence.
	 *
	 * Wraps the plugin's existing Pro detection so the compression module has a
	 * single seam to stub or filter.
	 *
	 * @return bool
	 */
	public function is_pro(): bool {
		return (bool) tsmlt()->has_pro();
	}

	/**
	 * Whether the compression feature is usable at all on this install.
	 *
	 * False when no image library (Imagick or GD) is present, in which case the
	 * UI should explain the server requirement rather than offer compression.
	 *
	 * @return bool
	 */
	public function is_compression_feature_available(): bool {
		return CompressionManager::instance()->has_available_engine();
	}

	/**
	 * Maximum number of attachments allowed in one compression job.
	 *
	 * Returns 0 for unlimited (Pro). Free installs get a bounded number that is
	 * enforced server-side when the job is created — the frontend limit is
	 * presentational only.
	 *
	 * @return int Zero means unlimited.
	 */
	public function get_compression_limit(): int {
		if ( $this->is_pro() ) {
			return 0;
		}

		$limit = (int) apply_filters( 'tsmlt_compression_free_job_limit', self::FREE_JOB_LIMIT );

		return max( 1, $limit );
	}

	/**
	 * Whether original files may be backed up before replacement.
	 *
	 * @return bool
	 */
	public function can_backup_originals(): bool {
		return $this->is_pro();
	}

	/**
	 * Whether previously backed-up originals may be restored.
	 *
	 * @return bool
	 */
	public function can_restore_originals(): bool {
		return $this->is_pro();
	}

	/**
	 * Whether WordPress-generated intermediate sizes may be compressed.
	 *
	 * @return bool
	 */
	public function can_compress_generated_sizes(): bool {
		return $this->is_pro();
	}

	/**
	 * Whether an explicit 1–100 quality value may override the preset.
	 *
	 * @return bool
	 */
	public function can_use_custom_quality(): bool {
		return $this->is_pro();
	}

	/**
	 * Whether newly uploaded images may be compressed automatically.
	 *
	 * @return bool
	 */
	public function can_auto_compress_on_upload(): bool {
		return $this->is_pro();
	}

	/**
	 * Whether AVIF output is permitted.
	 *
	 * AVIF is a Pro format. Server support is a separate question, answered by
	 * `ConversionCapabilities` — both must hold before AVIF can be produced.
	 *
	 * @return bool
	 */
	public function can_convert_avif(): bool {
		return $this->is_pro();
	}

	/**
	 * Whether WordPress-generated sizes may also be converted.
	 *
	 * @return bool
	 */
	public function can_convert_generated_sizes(): bool {
		return $this->is_pro();
	}

	/**
	 * Whether an explicit per-format quality may override the default.
	 *
	 * @return bool
	 */
	public function can_use_custom_conversion_quality(): bool {
		return $this->is_pro();
	}

	/**
	 * Whether newly uploaded images may be converted automatically.
	 *
	 * @return bool
	 */
	public function can_auto_convert_on_upload(): bool {
		return $this->is_pro();
	}

	/**
	 * Maximum number of attachments allowed in one conversion job.
	 *
	 * Shares the compression limit so the Free tier presents one consistent
	 * number rather than two competing ones. Zero means unlimited.
	 *
	 * @return int
	 */
	public function get_conversion_limit(): int {
		return $this->get_compression_limit();
	}

	/**
	 * Export the conversion entitlement matrix for the React layer.
	 *
	 * Purely for shaping the UI; every value is re-checked server-side before
	 * any work is performed.
	 *
	 * @return array<string, bool|int>
	 */
	public function conversion_to_array(): array {
		return [
			'is_pro'              => $this->is_pro(),
			'job_limit'           => $this->get_conversion_limit(),
			'can_avif'            => $this->can_convert_avif(),
			'can_generated_sizes' => $this->can_convert_generated_sizes(),
			'can_custom_quality'  => $this->can_use_custom_conversion_quality(),
			'can_auto_convert'    => $this->can_auto_convert_on_upload(),
		];
	}

	/**
	 * Capability + ownership check for a single attachment.
	 *
	 * Used by every attachment-scoped operation. The AJAX layer already gates on
	 * `manage_options`; this adds the per-post check so an attachment the user
	 * cannot edit is never touched, even when its ID arrives from the client.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return bool
	 */
	public function can_edit_attachment( int $attachment_id ): bool {
		if ( $attachment_id <= 0 ) {
			return false;
		}

		return current_user_can( 'edit_post', $attachment_id );
	}

	/**
	 * Export the entitlement matrix for the React layer.
	 *
	 * The frontend uses this purely to shape the UI. Every value is re-checked
	 * server-side before any work is performed.
	 *
	 * @return array<string, bool|int>
	 */
	public function to_array(): array {
		return [
			'is_pro'              => $this->is_pro(),
			'feature_available'   => $this->is_compression_feature_available(),
			'job_limit'           => $this->get_compression_limit(),
			'can_backup'          => $this->can_backup_originals(),
			'can_restore'         => $this->can_restore_originals(),
			'can_generated_sizes' => $this->can_compress_generated_sizes(),
			'can_custom_quality'  => $this->can_use_custom_quality(),
			'can_auto_compress'   => $this->can_auto_compress_on_upload(),
		];
	}
}
