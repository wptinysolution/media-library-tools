<?php
/**
 * Which converted WebP/AVIF files belong to which attachment.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Conversion;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;

/**
 * File ownership rules for converted copies (`{name}.webp`, `{name}.avif`
 * next to the source file or a generated size).
 *
 * A file is this attachment's own copy only when all of these hold:
 * - it is in the uploads folder, named exactly as this attachment's attached
 *   file or one of its recorded size files converts to (build_output_path());
 * - this attachment's conversion data records that file for that format;
 * - no attachment (this one included) uses it as its attached file, a size,
 *   its full-size original or a pre-edit backup;
 * - no other attachment's conversion data records it;
 * - it is an image of that format.
 *
 * Anything else at a target name is someone else's file: conversion refuses
 * to write over it (`name_conflict`) and deletion keeps it.
 */
class ConversionOwnership {

	/**
	 * Path of a file relative to the uploads folder, or '' when outside it.
	 *
	 * @param string $absolute Absolute path.
	 *
	 * @return string
	 */
	public static function relative( string $absolute ): string {
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return '';
		}
		$base = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
		$path = wp_normalize_path( $absolute );
		if ( 0 !== strpos( $path, $base ) || false !== strpos( $path, '../' ) ) {
			return '';
		}
		return substr( $path, strlen( $base ) );
	}

	/**
	 * The output files this attachment may own for a format: the converted
	 * name of its attached file and of each recorded size file, relative to
	 * uploads.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $format        Format key.
	 *
	 * @return string[]
	 */
	public static function expected_outputs( int $attachment_id, string $format ): array {
		$file = (string) get_attached_file( $attachment_id );
		if ( '' === $file ) {
			return [];
		}
		$converter = AttachmentConverter::instance();
		$outputs   = [ self::relative( $converter->build_output_path( $file, $format ) ) ];
		$meta      = wp_get_attachment_metadata( $attachment_id );
		foreach ( is_array( $meta['sizes'] ?? null ) ? $meta['sizes'] : [] as $size ) {
			if ( ! empty( $size['file'] ) && wp_basename( (string) $size['file'] ) === (string) $size['file'] ) {
				$outputs[] = self::relative( $converter->build_output_path( dirname( $file ) . '/' . $size['file'], $format ) );
			}
		}
		return array_values( array_filter( array_unique( $outputs ) ) );
	}

	/**
	 * Files this attachment's conversion data records for a format.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $format        Format key.
	 *
	 * @return string[] Relative paths.
	 */
	public static function recorded_outputs( int $attachment_id, string $format ): array {
		$data  = ConversionMetadata::instance()->get( $attachment_id );
		$entry = $data['formats'][ $format ] ?? [];
		$files = [];
		if ( ! empty( $entry['file'] ) ) {
			$files[] = (string) $entry['file'];
		}
		foreach ( (array) ( $entry['sizes'] ?? [] ) as $size ) {
			if ( ! empty( $size['file'] ) ) {
				$files[] = (string) $size['file'];
			}
		}
		return $files;
	}

	/**
	 * Whether a file is this attachment's own converted copy (see the class
	 * description). A file that does not exist is never owned.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $absolute      Absolute path.
	 * @param string $format        Format key.
	 *
	 * @return bool
	 */
	public static function is_owned_copy( int $attachment_id, string $absolute, string $format ): bool {
		$relative = self::relative( $absolute );
		if ( '' === $relative || ! is_file( $absolute ) ) {
			return false;
		}
		if ( ! in_array( $relative, self::expected_outputs( $attachment_id, $format ), true )
			|| ! in_array( $relative, self::recorded_outputs( $attachment_id, $format ), true ) ) {
			return false;
		}
		if ( self::used_by_media( $relative ) || self::recorded_by_other( $attachment_id, $relative ) ) {
			return false;
		}
		return self::is_format( $absolute, $format );
	}

	/**
	 * State of a target path before writing: `free` (nothing there), `owned`
	 * (this attachment's own copy, may be replaced) or `conflict`.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $absolute      Absolute target path.
	 * @param string $format        Format key.
	 *
	 * @return string
	 */
	public static function target_state( int $attachment_id, string $absolute, string $format ): string {
		clearstatcache( true, $absolute );
		$relative = self::relative( $absolute );
		if ( '' === $relative ) {
			return 'conflict';
		}
		if ( ! file_exists( $absolute ) ) {
			// Free on disk, but another attachment may still claim the name.
			return self::used_by_media( $relative ) || self::recorded_by_other( $attachment_id, $relative ) ? 'conflict' : 'free';
		}
		return self::is_owned_copy( $attachment_id, $absolute, $format ) ? 'owned' : 'conflict';
	}

	/**
	 * Whether any attachment uses a file as its attached file, a size, its
	 * full-size original or a pre-edit backup.
	 *
	 * @param string $relative Path relative to uploads.
	 *
	 * @return bool
	 */
	public static function used_by_media( string $relative ): bool {
		$main = Fns::DB()->select( 'post_id' )->from( 'postmeta' )
			->where( 'meta_key', '=', '_wp_attached_file' )
			->andWhere( 'meta_value', '=', $relative )
			->limit( 1 )->get();
		if ( ! empty( $main ) ) {
			return true;
		}
		$name = wp_basename( $relative );
		$dir  = dirname( $relative );
		foreach ( [ '_wp_attachment_metadata', '_wp_attachment_backup_sizes' ] as $key ) {
			foreach ( self::meta_mentioning( $key, '"' . $name . '"' ) as $post_id ) {
				$attached = (string) get_post_meta( $post_id, '_wp_attached_file', true );
				if ( '' !== $attached && dirname( $attached ) === $dir && in_array( $name, self::media_file_names( $post_id ), true ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether another attachment's conversion data records a file.
	 *
	 * @param int    $attachment_id This attachment.
	 * @param string $relative      Path relative to uploads.
	 *
	 * @return bool
	 */
	public static function recorded_by_other( int $attachment_id, string $relative ): bool {
		foreach ( self::meta_mentioning( ConversionMetadata::META_KEY, '"' . $relative . '"' ) as $post_id ) {
			if ( $post_id === $attachment_id ) {
				continue;
			}
			foreach ( ConversionCapabilities::FORMATS as $format ) {
				if ( in_array( $relative, self::recorded_outputs( $post_id, $format ), true ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * File names an attachment uses besides converted copies: sizes, the
	 * full-size original and pre-edit backups.
	 *
	 * @param int $post_id Attachment ID.
	 *
	 * @return string[]
	 */
	private static function media_file_names( int $post_id ): array {
		$names = [];
		$meta  = wp_get_attachment_metadata( $post_id );
		foreach ( is_array( $meta['sizes'] ?? null ) ? $meta['sizes'] : [] as $size ) {
			$names[] = (string) ( $size['file'] ?? '' );
		}
		$names[] = (string) ( $meta['original_image'] ?? '' );
		$backups = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );
		foreach ( is_array( $backups ) ? $backups : [] as $backup ) {
			$names[] = (string) ( $backup['file'] ?? '' );
		}
		return array_filter( $names );
	}

	/**
	 * IDs of posts whose meta value under a key contains a text.
	 *
	 * @param string $key  Meta key.
	 * @param string $text Text to find.
	 *
	 * @return int[]
	 */
	private static function meta_mentioning( string $key, string $text ): array {
		global $wpdb;
		$rows = Fns::DB()->select( 'post_id' )->from( 'postmeta' )
			->where( 'meta_key', '=', $key )
			->andWhere( 'meta_value', 'LIKE', '%' . $wpdb->esc_like( $text ) . '%' )
			->get();
		return array_values( array_unique( array_map( 'intval', array_column( is_array( $rows ) ? $rows : [], 'post_id' ) ) ) );
	}

	/**
	 * Whether a file is an image of the format. AVIF is accepted by its size
	 * alone where PHP cannot read AVIF headers, as on conversion.
	 *
	 * @param string $absolute Absolute path.
	 * @param string $format   Format key.
	 *
	 * @return bool
	 */
	private static function is_format( string $absolute, string $format ): bool {
		$size = @getimagesize( $absolute ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Unreadable files are simply not owned.
		if ( false === $size ) {
			return 'avif' === $format && (int) filesize( $absolute ) > 0;
		}
		return strtolower( (string) ( $size['mime'] ?? '' ) ) === 'image/' . $format;
	}
}
