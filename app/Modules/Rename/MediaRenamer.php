<?php
/**
 * Rename an attachment's files safely.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Rename;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\BackupManager;
use TinySolutions\mlt\Modules\Compress\CompressionMetadata;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionMetadata;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionOwnership;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\Regenerate\RegenerateLock;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;
use WP_Post;

/**
 * The media rename engine behind Fns::wp_rename_attachment().
 *
 * For an image, the attached file and its full-size original (scaled and
 * rotated uploads) are renamed with no-overwrite moves, keeping the
 * `-scaled` / `-rotated` suffix and the `original_image` link; then the
 * sizes are rebuilt under the new name from the original by the hardened
 * thumbnail engine (temporary files, no upload hooks, old size files deleted
 * only once replaced and unreferenced). Compression backups of the renamed
 * files move with them; backups of rebuilt sizes are removed, and the
 * rebuilt sizes count as not compressed. The attachment's own WebP/AVIF
 * copies are renamed (or, for sizes whose dimensions changed, removed);
 * anything not positively its own is left alone. Image URLs in content
 * are then updated with the existing replacement, and failures there are
 * reported separately from the file rename.
 *
 * The whole operation holds the regeneration lock, so compression,
 * conversion and EXIF removal stay out, and it refuses an image they hold.
 */
class MediaRenamer {

	/**
	 * Most numbered alternatives tried for a taken name.
	 *
	 * @var int
	 */
	const MAX_ALTERNATIVES = 99;

	/**
	 * Rename an attachment for a user.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $requested     Requested file name (extension ignored).
	 * @param int    $user_id       User the rename is done for.
	 *
	 * @return array{id: int, status: string, code: string, file: string, sizes: string, sizes_code: string, content: array, copies: array, backups: array}
	 *               status: renamed, unchanged, refused or failed.
	 */
	public static function rename( int $attachment_id, string $requested, int $user_id ): array {
		$result = [
			'id'         => $attachment_id,
			'status'     => 'refused',
			'code'       => '',
			'file'       => '',
			'sizes'      => 'none',
			'sizes_code' => '',
			'content'    => [
				'posts'  => [],
				'errors' => 0,
			],
			'copies'     => [
				'renamed' => 0,
				'removed' => 0,
			],
			'backups'    => [
				'moved'   => 0,
				'removed' => 0,
			],
		];

		$post = get_post( $attachment_id );
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || 'inherit' !== $post->post_status ) {
			return self::code( $result, 'not_found' );
		}
		if ( ! user_can( $user_id, 'edit_post', $attachment_id ) ) {
			return self::code( $result, 'forbidden' );
		}
		$stem = Fns::rename_target_name( $requested );
		if ( '' === $stem ) {
			return self::code( $result, 'invalid_name' );
		}
		$file = (string) get_attached_file( $attachment_id );
		if ( '' === $file || false !== strpos( $file, '://' ) || wp_is_stream( $file ) ) {
			return self::code( $result, '' === $file ? 'file_missing' : 'remote_storage' );
		}
		if ( ! is_file( $file ) ) {
			return self::code( $result, 'file_missing' );
		}

		$lock = RegenerateLock::acquire( $attachment_id );
		if ( null === $lock ) {
			return self::code( $result, 'locked' );
		}
		try {
			if ( AttachmentProcessor::instance()->is_locked( $attachment_id )
				|| AttachmentConverter::instance()->is_locked( $attachment_id )
				|| ScanStartLock::instance()->is_held( ExifStripper::LOCK_PREFIX . $attachment_id ) ) {
				return self::code( $result, 'locked' );
			}
			return self::rename_locked( $result, $attachment_id, $stem, $file );
		} finally {
			RegenerateLock::release( $attachment_id, $lock );
		}
	}

	/**
	 * The rename itself, with the lock held.
	 *
	 * @param array  $result        Result so far.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $stem          Sanitized new name without extension.
	 * @param string $file          Attached file.
	 *
	 * @return array
	 */
	private static function rename_locked( array $result, int $attachment_id, string $stem, string $file ): array {
		$dir       = dirname( $file );
		$meta      = wp_get_attachment_metadata( $attachment_id );
		$meta      = is_array( $meta ) ? $meta : [];
		$mime      = (string) get_post_mime_type( $attachment_id );
		$raster    = 0 === strpos( $mime, 'image/' ) && 'image/svg+xml' !== $mime;
		$is_image  = 0 === strpos( $mime, 'image/' );
		$original  = '';
		$suffix    = '';
		$extension = pathinfo( $file, PATHINFO_EXTENSION );

		if ( $raster && ! empty( $meta['original_image'] ) ) {
			$original = $dir . '/' . wp_basename( (string) $meta['original_image'] );
			if ( ! is_file( $original ) ) {
				return self::code( $result, 'original_missing' );
			}
			if ( preg_match( '/-(scaled|rotated)$/', pathinfo( $file, PATHINFO_FILENAME ), $match ) ) {
				$suffix = '-' . $match[1];
			}
		}

		$current_stem = (string) preg_replace( '/-(scaled|rotated)$/', '', pathinfo( '' !== $original ? $original : $file, PATHINFO_FILENAME ) );
		if ( $current_stem === $stem ) {
			$result['status'] = 'unchanged';
			$result['file']   = wp_basename( $file );
			return $result;
		}

		$targets = self::targets( $dir, $stem, $suffix, $extension, $original );
		if ( null === $targets ) {
			return self::code( $result, 'name_unavailable' );
		}

		// Snapshot before anything moves: URLs, owned copies, backups.
		$old_urls = self::urls( $attachment_id, $meta );
		$copies   = self::owned_copies( $attachment_id );
		$old_rel  = (string) _wp_relative_upload_path( $file );
		$old_meta = $meta;

		// Move the original, then the attached file; undo on failure.
		if ( '' !== $original && ! self::move( $original, $targets['original'] ) ) {
			return self::failed( $result, 'rename_failed' );
		}
		if ( ! self::move( $file, $targets['attached'] ) ) {
			if ( '' !== $original ) {
				self::move( $targets['original'], $original );
			}
			return self::failed( $result, 'rename_failed' );
		}

		update_attached_file( $attachment_id, $targets['attached'] );
		if ( isset( $meta['file'] ) || '' !== $original ) {
			$meta['file'] = _wp_relative_upload_path( $targets['attached'] );
			if ( '' !== $original ) {
				$meta['original_image'] = wp_basename( $targets['original'] );
			}
			wp_update_attachment_metadata( $attachment_id, $meta );
		}
		wp_update_post(
			[
				'ID'        => $attachment_id,
				'post_name' => $targets['stem'],
			]
		);
		$result['status'] = 'renamed';
		$result['file']   = wp_basename( $targets['attached'] );

		if ( $raster ) {
			$rebuilt              = RegenerateThumbnails::instance()->rebuild_held( $attachment_id );
			$result['sizes']      = '' === $rebuilt['code'] ? 'regenerated' : 'kept';
			$result['sizes_code'] = $rebuilt['code'];
		}

		$result['backups'] = self::move_backups( $attachment_id, $old_rel, $targets, $original, 'regenerated' === $result['sizes'] ? $old_meta : [] );
		$result['copies']  = self::move_copies( $attachment_id, $copies, $targets['attached'], 'regenerated' === $result['sizes'] );

		if ( $is_image ) {
			$new_meta = wp_get_attachment_metadata( $attachment_id );
			$new_urls = self::urls( $attachment_id, is_array( $new_meta ) ? $new_meta : [] );
			foreach ( $old_urls as $key => $old_url ) {
				$new_url = $new_urls[ $key ] ?? '';
				if ( '' === $old_url || '' === $new_url || $old_url === $new_url ) {
					continue;
				}
				$replaced                     = Fns::replace_image_url_tracked( $old_url, $new_url );
				$result['content']['posts']   = array_values( array_unique( array_merge( $result['content']['posts'], $replaced['posts'] ) ) );
				$result['content']['errors'] += $replaced['errors'];
			}
			if ( empty( get_post_meta( $attachment_id, '_original_file_url', true ) ) && '' !== $old_urls['main'] ) {
				update_post_meta( $attachment_id, '_original_file_url', $old_urls['main'] );
			}
		}

		Fns::wpml_update_translations( $attachment_id );
		Fns::permalink_to_post_guid( $attachment_id );
		return $result;
	}

	/**
	 * Target paths for the new name: the attached file (with its suffix)
	 * and the original, each free on disk, not a size-style name WordPress
	 * would avoid, and not claimed by any attachment. A numbered name
	 * (`name-1`, `name-2`, ...) is used when the plain one is taken.
	 *
	 * @param string $dir       Folder.
	 * @param string $stem      New name without extension.
	 * @param string $suffix    `-scaled`, `-rotated` or ''.
	 * @param string $extension Attached file extension.
	 * @param string $original  Original file, or ''.
	 *
	 * @return array{stem: string, attached: string, original: string}|null
	 */
	private static function targets( string $dir, string $stem, string $suffix, string $extension, string $original ): ?array {
		$original_ext = '' !== $original ? pathinfo( $original, PATHINFO_EXTENSION ) : '';
		for ( $i = 0; $i <= self::MAX_ALTERNATIVES; $i++ ) {
			$candidate = 0 === $i ? $stem : $stem . '-' . $i;
			$attached  = $candidate . $suffix . ( '' !== $extension ? '.' . $extension : '' );
			$names     = [ $attached ];
			if ( '' !== $original ) {
				$names[] = $candidate . ( '' !== $original_ext ? '.' . $original_ext : '' );
			}
			// WordPress never hands out a name ending in -scaled or -rotated
			// (wp_unique_filename() would add a number), so the suffixed
			// attached name is checked on disk and against the media only;
			// the plain name is also checked the WordPress way.
			$free = self::is_free( $dir, $attached, '' === $suffix );
			if ( '' !== $original ) {
				$free = $free && self::is_free( $dir, $names[1] );
			}
			if ( $free ) {
				return [
					'stem'     => $candidate,
					'attached' => $dir . '/' . $attached,
					'original' => '' !== $original ? $dir . '/' . $names[1] : '',
				];
			}
		}
		return null;
	}

	/**
	 * Whether a name can be used: nothing on disk, WordPress would not pick
	 * another name for it, and no attachment uses or claims it.
	 *
	 * @param string $dir      Folder.
	 * @param string $name     File name.
	 * @param bool   $wp_check Also require wp_unique_filename() to accept it.
	 *
	 * @return bool
	 */
	private static function is_free( string $dir, string $name, bool $wp_check = true ): bool {
		if ( file_exists( $dir . '/' . $name ) || ( $wp_check && wp_unique_filename( $dir, $name ) !== $name ) ) {
			return false;
		}
		$relative = ConversionOwnership::relative( $dir . '/' . $name );
		return '' !== $relative && ! ConversionOwnership::used_by_media( $relative ) && ! ConversionOwnership::recorded_by_other( 0, $relative );
	}

	/**
	 * Move a file to a name that must not exist: a hard link (fails when
	 * the name exists), then the old name is removed; where hard links are
	 * unavailable, the name is checked once more and the file renamed.
	 *
	 * @param string $from Current path.
	 * @param string $to   New path.
	 *
	 * @return bool
	 */
	private static function move( string $from, string $to ): bool {
		if ( @link( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- An existing name or no hard-link support is handled below.
			wp_delete_file( $from );
			return ! file_exists( $from );
		}
		clearstatcache( true, $to );
		if ( file_exists( $to ) ) {
			return false;
		}
		return @rename( $from, $to ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Same-folder move where hard links are not supported; failure is reported.
	}

	/**
	 * Image URLs of an attachment: the attached file, the original and each
	 * recorded size, keyed so old and new can be paired.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $meta          Metadata.
	 *
	 * @return array<string, string>
	 */
	private static function urls( int $attachment_id, array $meta ): array {
		$main = (string) wp_get_attachment_url( $attachment_id );
		$urls = [ 'main' => $main ];
		$base = '' !== $main ? trailingslashit( dirname( $main ) ) : '';
		if ( '' !== $base && ! empty( $meta['original_image'] ) ) {
			$urls['original'] = $base . wp_basename( (string) $meta['original_image'] );
		}
		foreach ( is_array( $meta['sizes'] ?? null ) ? $meta['sizes'] : [] as $name => $size ) {
			if ( '' !== $base && ! empty( $size['file'] ) ) {
				$urls[ 'size:' . $name ] = $base . wp_basename( (string) $size['file'] );
			}
		}
		return $urls;
	}

	/**
	 * The attachment's own converted copies, worked out before anything is
	 * renamed (ownership depends on the current names).
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array<int, array{format: string, size: string, path: string}>
	 */
	private static function owned_copies( int $attachment_id ): array {
		$data    = ConversionMetadata::instance()->get( $attachment_id );
		$basedir = trailingslashit( (string) wp_get_upload_dir()['basedir'] );
		$owned   = [];
		foreach ( is_array( $data['formats'] ?? null ) ? $data['formats'] : [] as $format => $entry ) {
			if ( ! empty( $entry['file'] ) && ConversionOwnership::is_owned_copy( $attachment_id, $basedir . $entry['file'], (string) $format ) ) {
				$owned[] = [
					'format' => (string) $format,
					'size'   => '',
					'path'   => $basedir . $entry['file'],
				];
			}
			foreach ( (array) ( $entry['sizes'] ?? [] ) as $size => $size_entry ) {
				if ( ! empty( $size_entry['file'] ) && ConversionOwnership::is_owned_copy( $attachment_id, $basedir . $size_entry['file'], (string) $format ) ) {
					$owned[] = [
						'format' => (string) $format,
						'size'   => (string) $size,
						'path'   => $basedir . $size_entry['file'],
					];
				}
			}
		}
		return $owned;
	}

	/**
	 * Rename the attachment's own copies to the new names and update the
	 * conversion record. A size copy is renamed only when its size was
	 * rebuilt with the same dimensions; otherwise it is removed (sizes not
	 * rebuilt keep their copies and names). A copy whose new name is not
	 * free is left in place and dropped from the record.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $copies        Owned copies from owned_copies().
	 * @param string $attached      New attached file.
	 * @param bool   $rebuilt       Whether the sizes were rebuilt.
	 *
	 * @return array{renamed: int, removed: int}
	 */
	private static function move_copies( int $attachment_id, array $copies, string $attached, bool $rebuilt ): array {
		$counts = [
			'renamed' => 0,
			'removed' => 0,
		];
		$data   = ConversionMetadata::instance()->get( $attachment_id );
		if ( [] === $data ) {
			return $counts;
		}
		$converter = AttachmentConverter::instance();
		$meta      = wp_get_attachment_metadata( $attachment_id );
		$sizes     = is_array( $meta['sizes'] ?? null ) ? $meta['sizes'] : [];
		$dir       = dirname( $attached );

		foreach ( $copies as $copy ) {
			$format = $copy['format'];
			if ( '' === $copy['size'] ) {
				$target = $converter->build_output_path( $attached, $format );
				$key    = null;
			} else {
				if ( ! $rebuilt ) {
					continue;
				}
				$key  = $copy['size'];
				$size = self::size_by_key( $sizes, $key );
				if ( null === $size || ! self::same_dimensions( $copy['path'], $size ) ) {
					wp_delete_file( $copy['path'] );
					unset( $data['formats'][ $format ]['sizes'][ $key ] );
					++$counts['removed'];
					continue;
				}
				$target = $converter->build_output_path( $dir . '/' . $size['file'], $format );
			}
			if ( $target === $copy['path'] ) {
				continue;
			}
			$moved = self::is_free( dirname( $target ), wp_basename( $target ) ) && self::move( $copy['path'], $target );
			$file  = $moved ? (string) _wp_relative_upload_path( $target ) : '';
			if ( null === $key ) {
				if ( $moved ) {
					$data['formats'][ $format ]['file'] = $file;
				} else {
					unset( $data['formats'][ $format ] );
				}
			} elseif ( $moved ) {
				$data['formats'][ $format ]['sizes'][ $key ]['file'] = $file;
			} else {
				unset( $data['formats'][ $format ]['sizes'][ $key ] );
			}
			if ( $moved ) {
				++$counts['renamed'];
			}
		}

		if ( isset( $data['source'] ) && is_array( $data['source'] ) ) {
			$data['source']['file'] = (string) _wp_relative_upload_path( $attached );
		}
		if ( empty( $data['formats'] ) ) {
			ConversionMetadata::instance()->delete( $attachment_id );
		} else {
			ConversionMetadata::instance()->save( $attachment_id, $data );
		}
		return $counts;
	}

	/**
	 * The size record whose sanitized name is a conversion record's key.
	 *
	 * @param array  $sizes Size records.
	 * @param string $key   Sanitized size name.
	 *
	 * @return array|null
	 */
	private static function size_by_key( array $sizes, string $key ): ?array {
		foreach ( $sizes as $name => $size ) {
			if ( sanitize_key( (string) $name ) === $key && ! empty( $size['file'] ) ) {
				return $size;
			}
		}
		return null;
	}

	/**
	 * Whether a copy has the dimensions of a size record.
	 *
	 * @param string $path Copy.
	 * @param array  $size Size record.
	 *
	 * @return bool
	 */
	private static function same_dimensions( string $path, array $size ): bool {
		$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- An unreadable copy is treated as different.
		if ( false === $info ) {
			return false;
		}
		$width    = (int) ( $size['width'] ?? 0 );
		$height   = (int) ( $size['height'] ?? 0 );
		$actual_w = (int) $info[0];
		$actual_h = (int) $info[1];
		return $actual_w === $width && $actual_h === $height;
	}

	/**
	 * Move compression backups of the renamed files to their new relative
	 * paths; when the sizes were rebuilt, remove the backups of the old
	 * sizes and drop their entries from the compression record (the rebuilt
	 * sizes are not compressed).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $old_rel       Old relative attached path.
	 * @param array  $targets       New paths.
	 * @param string $original      Old original path, or ''.
	 * @param array  $old_meta      Metadata before the rename when the sizes
	 *                              were rebuilt, else [].
	 *
	 * @return array{moved: int, removed: int}
	 */
	private static function move_backups( int $attachment_id, string $old_rel, array $targets, string $original, array $old_meta ): array {
		$counts  = [
			'moved'   => 0,
			'removed' => 0,
		];
		$backups = BackupManager::instance();
		$pairs   = [ $old_rel => (string) _wp_relative_upload_path( $targets['attached'] ) ];
		if ( '' !== $original ) {
			$pairs[ (string) _wp_relative_upload_path( $original ) ] = (string) _wp_relative_upload_path( $targets['original'] );
		}
		foreach ( $pairs as $from => $to ) {
			if ( ! $backups->backup_exists( $from ) ) {
				continue;
			}
			$from_path = $backups->get_backup_path( $from );
			$to_path   = $backups->get_backup_path( $to );
			if ( ! is_wp_error( $from_path ) && ! is_wp_error( $to_path ) && ! file_exists( $to_path ) && wp_mkdir_p( dirname( $to_path ) ) && self::move( $from_path, $to_path ) ) {
				++$counts['moved'];
			}
		}

		$data = CompressionMetadata::instance()->get( $attachment_id );
		if ( [] === $data ) {
			return $counts;
		}
		$subdir = ltrim( dirname( $old_rel ), '.' );
		$subdir = '' === $subdir ? '' : trailingslashit( $subdir );
		if ( [] !== $old_meta ) {
			// The old sizes were replaced: their backups no longer match a file.
			foreach ( is_array( $old_meta['sizes'] ?? null ) ? $old_meta['sizes'] : [] as $size ) {
				if ( ! empty( $size['file'] ) && $backups->delete_backup( $subdir . wp_basename( (string) $size['file'] ) ) ) {
					++$counts['removed'];
				}
			}
			foreach ( array_keys( (array) ( $data['sizes'] ?? [] ) ) as $name ) {
				if ( 'full' !== $name ) {
					unset( $data['sizes'][ $name ] );
				}
			}
			$before = 0;
			$after  = 0;
			foreach ( (array) ( $data['sizes'] ?? [] ) as $size ) {
				$before += (int) ( $size['before'] ?? 0 );
				$after  += (int) ( $size['after'] ?? 0 );
			}
			if ( $before > 0 ) {
				$data['original']['size']            = $before;
				$data['compressed']['size']          = $after;
				$data['compressed']['saved_bytes']   = max( 0, $before - $after );
				$data['compressed']['saved_percent'] = round( max( 0, $before - $after ) / $before * 100, 2 );
			}
		}
		$data['original']['file'] = (string) _wp_relative_upload_path( $targets['attached'] );
		CompressionMetadata::instance()->save( $attachment_id, $data );
		return $counts;
	}

	/**
	 * Set a refusal code.
	 *
	 * @param array  $result Result.
	 * @param string $code   Code.
	 *
	 * @return array
	 */
	private static function code( array $result, string $code ): array {
		$result['code'] = $code;
		return $result;
	}

	/**
	 * Set a failure code.
	 *
	 * @param array  $result Result.
	 * @param string $code   Code.
	 *
	 * @return array
	 */
	private static function failed( array $result, string $code ): array {
		$result['status'] = 'failed';
		$result['code']   = $code;
		return $result;
	}
}
