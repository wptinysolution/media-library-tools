<?php
/**
 * Per-attachment compression orchestration.
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
 * Compresses a single attachment: its full-size file and, for Pro installs,
 * its generated sizes.
 *
 * Owns the ordering guarantees of the safety flow — validate, compress to a
 * temporary file, compare sizes, back up, then replace atomically — while
 * delegating the actual encoding to `CompressionManager`. Adding a new engine
 * therefore never requires changes here.
 */
class AttachmentProcessor {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Meta key marking an attachment as currently being processed.
	 *
	 * Prevents automatic upload compression from re-entering while a manual run
	 * is mid-flight on the same attachment.
	 */
	const LOCK_META_KEY = '_tsmlt_compression_lock';

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Validate that an attachment may be compressed by the current user.
	 *
	 * Combines the structural checks with the per-attachment capability check.
	 * Every attachment ID that arrives from a request passes through here before
	 * any file is touched, so a forged ID cannot reach the filesystem.
	 *
	 * Call this from request context only. Background workers must use
	 * `validate_attachment_file()` instead — see the note there.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return true|WP_Error
	 */
	public function validate_attachment( int $attachment_id ) {
		if ( ! CompressionAccess::instance()->can_edit_attachment( $attachment_id ) ) {
			return new WP_Error(
				'tsmlt_compression_forbidden',
				esc_html__( 'You do not have permission to edit this image.', 'media-library-tools' )
			);
		}

		return $this->validate_attachment_file( $attachment_id );
	}

	/**
	 * Validate everything about an attachment except the current user's rights.
	 *
	 * Used by the background job runner. WP-Cron ticks execute with no logged-in
	 * user, so `current_user_can()` would deny every item there; authorisation is
	 * instead performed once, in request context, when the job is created — the
	 * same boundary at which the plugin's other background scanners authorise.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return true|WP_Error
	 */
	public function validate_attachment_file( int $attachment_id ) {
		if ( $attachment_id <= 0 ) {
			return new WP_Error(
				'tsmlt_compression_not_found',
				esc_html__( 'Image not found.', 'media-library-tools' )
			);
		}

		$post = get_post( $attachment_id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error(
				'tsmlt_compression_not_found',
				esc_html__( 'Image not found.', 'media-library-tools' )
			);
		}

		$mime_type = (string) get_post_mime_type( $attachment_id );

		if ( ! CompressionManager::instance()->is_supported_mime_type( $mime_type ) ) {
			return new WP_Error(
				'tsmlt_compression_unsupported_mime',
				esc_html__( 'This file type cannot be compressed. Supported types are JPEG, PNG and WebP.', 'media-library-tools' )
			);
		}

		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			return new WP_Error(
				'tsmlt_compression_missing_source',
				esc_html__( 'The image file is missing on the server.', 'media-library-tools' )
			);
		}

		return true;
	}

	/**
	 * Compress one attachment and record the outcome.
	 *
	 * The returned `status` is `completed` when at least one file shrank,
	 * `skipped` when nothing could be improved, and `failed` when the full-size
	 * image could not be processed. A generated size that fails does not fail
	 * the attachment — it is recorded as a partial result.
	 *
	 * @param int   $attachment_id Attachment post ID.
	 * @param array $run_settings  Settings from `CompressionSettings::resolve_run_settings()`.
	 *
	 * @return array|WP_Error
	 */
	public function process( int $attachment_id, array $run_settings ) {
		// Structural validation only: this runs inside WP-Cron ticks where there
		// is no current user. The caller is responsible for having authorised the
		// attachment in request context first.
		$valid = $this->validate_attachment_file( $attachment_id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( $this->is_locked( $attachment_id ) ) {
			return new WP_Error(
				'tsmlt_compression_locked',
				esc_html__( 'This image is already being compressed.', 'media-library-tools' )
			);
		}

		$this->lock( $attachment_id );

		try {
			return $this->process_unlocked( $attachment_id, $run_settings );
		} finally {
			$this->unlock( $attachment_id );
		}
	}

	/**
	 * Compression body, executed with the attachment lock already held.
	 *
	 * @param int   $attachment_id Attachment post ID.
	 * @param array $run_settings  Effective run settings.
	 *
	 * @return array|WP_Error
	 */
	private function process_unlocked( int $attachment_id, array $run_settings ) {
		$file      = get_attached_file( $attachment_id );
		$mime_type = (string) get_post_mime_type( $attachment_id );
		$backup    = BackupManager::instance();
		$metadata  = CompressionMetadata::instance();

		$relative_file = $backup->to_relative_path( $file );
		$relative_file = is_wp_error( $relative_file ) ? '' : $relative_file;

		$sizes            = [];
		$backup_available = false;
		$engine_used      = '';
		$quality_used     = 0;
		$last_error       = '';

		// ── Full-size image ──────────────────────────────────────────────────
		$full = $this->process_file( $file, $mime_type, $run_settings );

		if ( is_wp_error( $full ) ) {
			$this->record_failure( $attachment_id, $run_settings, $full, $mime_type, $relative_file );

			return $full;
		}

		$sizes['full'] = [
			'before' => $full['original_size'],
			'after'  => $full['final_size'],
			'status' => $full['status'],
		];
		if ( ! empty( $full['reason'] ) ) {
			$sizes['full']['reason'] = $full['reason'];
		}

		$backup_available = $backup_available || ! empty( $full['backed_up'] );
		$engine_used      = $full['engine'] ?: $engine_used;
		$quality_used     = $full['quality'] ?: $quality_used;

		// ── Generated sizes (Pro) ────────────────────────────────────────────
		if ( ! empty( $run_settings['compress_generated_sizes'] ) ) {
			foreach ( $this->get_generated_size_files( $attachment_id ) as $size_name => $size_file ) {
				// A missing or deleted thumbnail is expected on real sites — record
				// it and continue rather than failing the whole attachment.
				if ( ! file_exists( $size_file ) ) {
					$sizes[ $size_name ] = [
						'before' => 0,
						'after'  => 0,
						'status' => 'skipped',
						'reason' => 'missing_file',
					];
					continue;
				}

				$size_mime   = $this->detect_mime_type( $size_file, $mime_type );
				$size_result = $this->process_file( $size_file, $size_mime, $run_settings );

				if ( is_wp_error( $size_result ) ) {
					$last_error          = $size_result->get_error_message();
					$sizes[ $size_name ] = [
						'before' => (int) filesize( $size_file ),
						'after'  => (int) filesize( $size_file ),
						'status' => 'failed',
						'reason' => $size_result->get_error_code(),
					];
					continue;
				}

				$sizes[ $size_name ] = [
					'before' => $size_result['original_size'],
					'after'  => $size_result['final_size'],
					'status' => $size_result['status'],
				];
				if ( ! empty( $size_result['reason'] ) ) {
					$sizes[ $size_name ]['reason'] = $size_result['reason'];
				}

				$backup_available = $backup_available || ! empty( $size_result['backed_up'] );
				$engine_used      = $size_result['engine'] ?: $engine_used;
			}
		}

		// A run counts as completed when anything actually shrank.
		$any_compressed = false;
		foreach ( $sizes as $size ) {
			if ( 'compressed' === ( $size['status'] ?? '' ) ) {
				$any_compressed = true;
				break;
			}
		}

		$status = $any_compressed ? 'completed' : 'skipped';

		// The image bytes changed but the dimensions did not, so the stored
		// attachment metadata stays valid — regenerating it here would be a
		// pointless, expensive rewrite. Only the cached filesize is refreshed.
		if ( $any_compressed ) {
			$this->refresh_metadata_filesize( $attachment_id );
			clean_post_cache( $attachment_id );
		}

		$result = [
			'status'           => $status,
			'sizes'            => $sizes,
			'engine'           => $engine_used,
			'quality'          => $quality_used,
			'mime_type'        => $mime_type,
			'relative_file'    => $relative_file,
			'backup_available' => $backup_available || $backup->has_backup( $attachment_id ),
			'last_error'       => $last_error,
		];

		$stored = $metadata->record_result( $attachment_id, $result, $run_settings );

		return [
			'attachment_id' => $attachment_id,
			'status'        => $status,
			'saved_bytes'   => (int) ( $stored['compressed']['saved_bytes'] ?? 0 ),
			'saved_percent' => (float) ( $stored['compressed']['saved_percent'] ?? 0 ),
			'before'        => (int) ( $sizes['full']['before'] ?? 0 ),
			'after'         => (int) ( $sizes['full']['after'] ?? 0 ),
			'reason'        => (string) ( $sizes['full']['reason'] ?? '' ),
			'sizes'         => $sizes,
		];
	}

	/**
	 * Run the safety flow for one physical file.
	 *
	 * Order is deliberate and must not be rearranged: the original is only
	 * backed up once a validated, genuinely smaller replacement exists, and it
	 * is only replaced once that backup has succeeded.
	 *
	 * @param string $path         Absolute path to the file.
	 * @param string $mime_type    MIME type of the file.
	 * @param array  $run_settings Effective run settings.
	 *
	 * @return array|WP_Error
	 */
	private function process_file( string $path, string $mime_type, array $run_settings ) {
		$manager  = CompressionManager::instance();
		$settings = CompressionSettings::instance();
		$quality  = $settings->get_quality_for( $run_settings, $mime_type );

		$compressed = $manager->compress_file( $path, $mime_type, $quality );

		if ( is_wp_error( $compressed ) ) {
			return $compressed;
		}

		// Nothing to gain — keep the original untouched.
		if ( 'skipped' === $compressed['status'] ) {
			return [
				'status'        => 'skipped',
				'reason'        => 'no_improvement',
				'original_size' => $compressed['original_size'],
				'final_size'    => $compressed['original_size'],
				'engine'        => $compressed['engine'],
				'quality'       => $quality,
				'backed_up'     => false,
			];
		}

		$temp_file = $compressed['temp_file'];
		$backed_up = false;

		// Back up before the original is touched. A failed backup aborts the
		// replacement — the user asked for a safety net, so proceeding without
		// one would violate the setting.
		if ( ! empty( $run_settings['backup_originals'] ) ) {
			$backup_result = BackupManager::instance()->backup_file( $path );

			if ( is_wp_error( $backup_result ) ) {
				$manager->delete_temp_file( $temp_file );

				return $backup_result;
			}

			$backed_up = true;
		}

		$replaced = $this->replace_file( $path, $temp_file );

		if ( is_wp_error( $replaced ) ) {
			$manager->delete_temp_file( $temp_file );

			return $replaced;
		}

		// Confirm what actually landed on disk before reporting success.
		$validated = $manager->validate_image_file( $path, $mime_type );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		clearstatcache( true, $path );

		return [
			'status'        => 'compressed',
			'reason'        => '',
			'original_size' => $compressed['original_size'],
			'final_size'    => (int) filesize( $path ),
			'engine'        => $compressed['engine'],
			'quality'       => $quality,
			'backed_up'     => $backed_up,
		];
	}

	/**
	 * Move a validated temporary file over the original.
	 *
	 * `rename()` within one directory is atomic on POSIX filesystems, so a
	 * crash mid-write can never leave a half-written image in place. File
	 * permissions are re-applied because the temp file is created with
	 * restrictive defaults.
	 *
	 * @param string $destination Absolute path to the file being replaced.
	 * @param string $temp_file   Absolute path to the validated replacement.
	 *
	 * @return true|WP_Error
	 */
	private function replace_file( string $destination, string $temp_file ) {
		$perms = @fileperms( $destination ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Falls back to the WP default below.
		$perms = false !== $perms ? $perms & 0777 : ( defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 );

		if ( ! @rename( $temp_file, $destination ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- Atomic same-directory replacement; WP_Filesystem has no atomic equivalent. Failure is reported as WP_Error.
			return new WP_Error(
				'tsmlt_compression_replace_failed',
				esc_html__( 'The compressed image could not replace the original.', 'media-library-tools' )
			);
		}

		@chmod( $destination, $perms ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- Best-effort permission restore.

		return true;
	}

	/**
	 * Absolute paths of every generated size file for an attachment.
	 *
	 * Uses `wp_get_attachment_metadata()` so custom registered sizes are
	 * included. Duplicate filenames — common when several sizes resolve to the
	 * same crop — are collapsed so a file is never compressed twice in one run.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return array<string, string> Size name => absolute path.
	 */
	private function get_generated_size_files( int $attachment_id ): array {
		$meta_data = wp_get_attachment_metadata( $attachment_id );

		if ( ! is_array( $meta_data ) || empty( $meta_data['sizes'] ) || ! is_array( $meta_data['sizes'] ) ) {
			return [];
		}

		$file = get_attached_file( $attachment_id );

		if ( ! $file ) {
			return [];
		}

		$base_dir = trailingslashit( dirname( $file ) );
		$files    = [];
		$seen     = [];

		foreach ( $meta_data['sizes'] as $size_name => $size ) {
			if ( empty( $size['file'] ) ) {
				continue;
			}

			$filename = basename( (string) $size['file'] );

			// The full-size file is handled separately; never process it twice.
			if ( basename( $file ) === $filename || isset( $seen[ $filename ] ) ) {
				continue;
			}

			$seen[ $filename ]                   = true;
			$files[ sanitize_key( $size_name ) ] = $base_dir . $filename;
		}

		return $files;
	}

	/**
	 * Determine a generated size's MIME type.
	 *
	 * WordPress can emit intermediate sizes in a different format from the
	 * original, so the file's own type wins when it can be read.
	 *
	 * @param string $path     Absolute path to the size file.
	 * @param string $fallback MIME type to use when detection fails.
	 *
	 * @return string
	 */
	private function detect_mime_type( string $path, string $fallback ): string {
		$type = wp_check_filetype( $path );

		if ( ! empty( $type['type'] ) ) {
			return (string) $type['type'];
		}

		return $fallback;
	}

	/**
	 * Refresh only the cached filesize in attachment metadata.
	 *
	 * WordPress 6.0+ stores `filesize` in the metadata array. Compression
	 * changes it while leaving dimensions untouched, so this updates that one
	 * value instead of regenerating the whole metadata structure.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return void
	 */
	private function refresh_metadata_filesize( int $attachment_id ): void {
		$meta_data = wp_get_attachment_metadata( $attachment_id );

		if ( ! is_array( $meta_data ) ) {
			return;
		}

		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			return;
		}

		clearstatcache( true, $file );
		$meta_data['filesize'] = (int) filesize( $file );

		wp_update_attachment_metadata( $attachment_id, $meta_data );
	}

	/**
	 * Store a failure against the attachment so the UI can explain it.
	 *
	 * @param int      $attachment_id Attachment post ID.
	 * @param array    $run_settings  Effective run settings.
	 * @param WP_Error $error         The failure.
	 * @param string   $mime_type     Attachment MIME type.
	 * @param string   $relative_file Uploads-relative path.
	 *
	 * @return void
	 */
	private function record_failure( int $attachment_id, array $run_settings, WP_Error $error, string $mime_type, string $relative_file ): void {
		$metadata = CompressionMetadata::instance();
		$existing = $metadata->get( $attachment_id );

		// Preserve any earlier successful result; only the error is updated.
		if ( ! empty( $existing ) ) {
			$existing['last_error'] = $error->get_error_message();
			$metadata->save( $attachment_id, $existing );

			return;
		}

		$metadata->save(
			$attachment_id,
			[
				'status'        => 'failed',
				'original'      => [
					'size'      => 0,
					'mime_type' => $mime_type,
					'file'      => $relative_file,
				],
				'compressed'    => [
					'size'          => 0,
					'saved_bytes'   => 0,
					'saved_percent' => 0,
				],
				'settings'      => [
					'quality'         => 0,
					'mode'            => (string) ( $run_settings['mode'] ?? '' ),
					'engine'          => '',
					'generated_sizes' => ! empty( $run_settings['compress_generated_sizes'] ),
				],
				'backup'        => [
					'enabled'   => ! empty( $run_settings['backup_originals'] ),
					'available' => false,
					'directory' => BackupManager::BACKUP_DIRNAME,
				],
				'sizes'         => [],
				'compressed_at' => current_time( 'mysql', true ),
				'last_error'    => $error->get_error_message(),
			]
		);
	}

	/**
	 * Whether an attachment is currently being processed.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return bool
	 */
	public function is_locked( int $attachment_id ): bool {
		$lock = get_post_meta( $attachment_id, self::LOCK_META_KEY, true );

		if ( empty( $lock ) ) {
			return false;
		}

		// A stale lock from a fatal error must not block the image forever.
		return ( time() - (int) $lock ) < 10 * MINUTE_IN_SECONDS;
	}

	/**
	 * Mark an attachment as being processed.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return void
	 */
	private function lock( int $attachment_id ): void {
		update_post_meta( $attachment_id, self::LOCK_META_KEY, time() );
	}

	/**
	 * Clear the processing marker.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return void
	 */
	private function unlock( int $attachment_id ): void {
		delete_post_meta( $attachment_id, self::LOCK_META_KEY );
	}
}
