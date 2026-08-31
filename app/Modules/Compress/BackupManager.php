<?php
/**
 * Original-image backup storage.
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
 * Stores and locates pristine copies of images before compression replaces them.
 *
 * Backups mirror the uploads tree inside a single dedicated directory:
 *
 *     uploads/2026/09/photo.jpg
 *     uploads/tsmlt-compression-backups/2026/09/photo.jpg
 *
 * Only the uploads-relative path is ever stored in metadata, so moving or
 * renaming the WordPress root does not orphan a backup.
 *
 * Backups are never removed automatically — deletion is exposed as explicit
 * operations for a future UI to call.
 */
class BackupManager {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Directory name, relative to the uploads base directory.
	 */
	const BACKUP_DIRNAME = 'tsmlt-compression-backups';

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Absolute path to the backup root, creating and protecting it on demand.
	 *
	 * @return string|WP_Error
	 */
	public function get_backup_root() {
		$uploads = wp_get_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return new WP_Error(
				'tsmlt_compression_backup_failed',
				esc_html__( 'The WordPress uploads directory is not available.', 'media-library-tools' )
			);
		}

		$root = trailingslashit( $uploads['basedir'] ) . self::BACKUP_DIRNAME;

		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
			return new WP_Error(
				'tsmlt_compression_backup_failed',
				esc_html__( 'The backup directory could not be created.', 'media-library-tools' )
			);
		}

		$this->protect_directory( $root );

		return $root;
	}

	/**
	 * Drop an index.php and a deny rule into the backup root.
	 *
	 * Backups are byte-identical originals; without this they would be publicly
	 * fetchable at a guessable URL even after the visible copy was replaced.
	 *
	 * @param string $root Absolute path to the backup root.
	 *
	 * @return void
	 */
	private function protect_directory( string $root ): void {
		$index = trailingslashit( $root ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- Best-effort hardening; WP_Filesystem needs credentials unavailable during AJAX.
		}

		$htaccess = trailingslashit( $root ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "Require all denied\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- Best-effort hardening; see above.
		}
	}

	/**
	 * Convert an absolute uploads path to its uploads-relative form.
	 *
	 * @param string $absolute_path Absolute path inside the uploads directory.
	 *
	 * @return string|WP_Error Relative path such as `2026/09/photo.jpg`.
	 */
	public function to_relative_path( string $absolute_path ) {
		$uploads = wp_get_upload_dir();

		if ( empty( $uploads['basedir'] ) ) {
			return new WP_Error(
				'tsmlt_compression_backup_failed',
				esc_html__( 'The WordPress uploads directory is not available.', 'media-library-tools' )
			);
		}

		$basedir  = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
		$absolute = wp_normalize_path( $absolute_path );

		if ( 0 !== strpos( $absolute, $basedir ) ) {
			return new WP_Error(
				'tsmlt_compression_backup_failed',
				esc_html__( 'This image is stored outside the uploads directory and cannot be backed up.', 'media-library-tools' )
			);
		}

		return ltrim( substr( $absolute, strlen( $basedir ) ), '/' );
	}

	/**
	 * Absolute backup path for an uploads-relative file path.
	 *
	 * @param string $relative_path Uploads-relative path.
	 *
	 * @return string|WP_Error
	 */
	public function get_backup_path( string $relative_path ) {
		$root = $this->get_backup_root();

		if ( is_wp_error( $root ) ) {
			return $root;
		}

		// Reject traversal before it can escape the backup root.
		$relative_path = ltrim( wp_normalize_path( $relative_path ), '/' );
		if ( '' === $relative_path || false !== strpos( $relative_path, '../' ) ) {
			return new WP_Error(
				'tsmlt_compression_backup_failed',
				esc_html__( 'Invalid file path.', 'media-library-tools' )
			);
		}

		return trailingslashit( $root ) . $relative_path;
	}

	/**
	 * Copy a file into the backup tree, preserving its uploads-relative path.
	 *
	 * An existing backup is never overwritten: the first backup is the true
	 * original, and replacing it with an already-compressed file would make a
	 * later restore return degraded data.
	 *
	 * @param string $source_path Absolute path to the file to back up.
	 *
	 * @return string|WP_Error Uploads-relative path of the backed-up file.
	 */
	public function backup_file( string $source_path ) {
		if ( ! file_exists( $source_path ) ) {
			return new WP_Error(
				'tsmlt_compression_backup_failed',
				esc_html__( 'The file to back up no longer exists.', 'media-library-tools' )
			);
		}

		$relative_path = $this->to_relative_path( $source_path );
		if ( is_wp_error( $relative_path ) ) {
			return $relative_path;
		}

		$backup_path = $this->get_backup_path( $relative_path );
		if ( is_wp_error( $backup_path ) ) {
			return $backup_path;
		}

		if ( file_exists( $backup_path ) ) {
			return $relative_path;
		}

		$backup_dir = dirname( $backup_path );
		if ( ! is_dir( $backup_dir ) && ! wp_mkdir_p( $backup_dir ) ) {
			return new WP_Error(
				'tsmlt_compression_backup_failed',
				esc_html__( 'The backup directory could not be created.', 'media-library-tools' )
			);
		}

		if ( ! copy( $source_path, $backup_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Direct copy; WP_Filesystem requires credentials unavailable during AJAX.
			return new WP_Error(
				'tsmlt_compression_backup_failed',
				esc_html__( 'The original image could not be backed up.', 'media-library-tools' )
			);
		}

		// A truncated backup is worse than none — verify before trusting it.
		clearstatcache( true, $backup_path );
		if ( (int) filesize( $backup_path ) !== (int) filesize( $source_path ) ) {
			wp_delete_file( $backup_path );

			return new WP_Error(
				'tsmlt_compression_backup_failed',
				esc_html__( 'The backup copy was incomplete and has been discarded.', 'media-library-tools' )
			);
		}

		return $relative_path;
	}

	/**
	 * Whether a backup exists for a given uploads-relative path.
	 *
	 * @param string $relative_path Uploads-relative path.
	 *
	 * @return bool
	 */
	public function backup_exists( string $relative_path ): bool {
		$backup_path = $this->get_backup_path( $relative_path );

		return ! is_wp_error( $backup_path ) && file_exists( $backup_path );
	}

	/**
	 * Whether the full-size original of an attachment has a backup on disk.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return bool
	 */
	public function has_backup( int $attachment_id ): bool {
		$file = get_attached_file( $attachment_id );

		if ( ! $file ) {
			return false;
		}

		$relative_path = $this->to_relative_path( $file );

		return ! is_wp_error( $relative_path ) && $this->backup_exists( $relative_path );
	}

	/**
	 * Delete one backed-up file.
	 *
	 * Exposed for explicit, user-initiated cleanup only. Nothing in the
	 * compression flow calls this.
	 *
	 * @param string $relative_path Uploads-relative path.
	 *
	 * @return bool
	 */
	public function delete_backup( string $relative_path ): bool {
		$backup_path = $this->get_backup_path( $relative_path );

		if ( is_wp_error( $backup_path ) || ! file_exists( $backup_path ) ) {
			return false;
		}

		wp_delete_file( $backup_path );

		return true;
	}

	/**
	 * Delete every backup belonging to one attachment, including its sizes.
	 *
	 * Exposed for explicit, user-initiated cleanup only.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return int Number of files deleted.
	 */
	public function delete_attachment_backups( int $attachment_id ): int {
		$deleted = 0;

		foreach ( $this->get_attachment_relative_paths( $attachment_id ) as $relative_path ) {
			if ( $this->delete_backup( $relative_path ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Total size on disk of the backup directory.
	 *
	 * @return array{bytes: int, readable: string, files: int}
	 */
	public function get_backup_stats(): array {
		$root = $this->get_backup_root();

		if ( is_wp_error( $root ) || ! is_dir( $root ) ) {
			return [
				'bytes'    => 0,
				'readable' => size_format( 0, 1 ),
				'files'    => 0,
			];
		}

		$bytes = 0;
		$files = 0;

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() ) {
					$bytes += (int) $file->getSize();
					++$files;
				}
			}
		} catch ( \Exception $e ) {
			// An unreadable directory reports as empty rather than fataling.
			return [
				'bytes'    => 0,
				'readable' => size_format( 0, 1 ),
				'files'    => 0,
			];
		}

		return [
			'bytes'    => $bytes,
			'readable' => size_format( $bytes, 1 ),
			'files'    => $files,
		];
	}

	/**
	 * All uploads-relative paths belonging to an attachment.
	 *
	 * Covers the full-size file plus every generated size present in the
	 * attachment metadata.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return string[]
	 */
	public function get_attachment_relative_paths( int $attachment_id ): array {
		$file = get_attached_file( $attachment_id );

		if ( ! $file ) {
			return [];
		}

		$relative_full = $this->to_relative_path( $file );
		if ( is_wp_error( $relative_full ) ) {
			return [];
		}

		$paths     = [ $relative_full ];
		$subdir    = ltrim( dirname( $relative_full ), '.' );
		$subdir    = '' === $subdir ? '' : trailingslashit( $subdir );
		$meta_data = wp_get_attachment_metadata( $attachment_id );

		if ( is_array( $meta_data ) && ! empty( $meta_data['sizes'] ) && is_array( $meta_data['sizes'] ) ) {
			foreach ( $meta_data['sizes'] as $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}
				$paths[] = $subdir . $size['file'];
			}
		}

		return array_values( array_unique( $paths ) );
	}
}
