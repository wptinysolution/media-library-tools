<?php
/**
 * Per-attachment compression metadata.
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
 * Reads and writes the single structured compression meta row.
 *
 * Everything the feature knows about an attachment lives under one versioned
 * meta key, so a future format change is a single migration rather than a hunt
 * for scattered keys.
 */
class CompressionMetadata {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * The one meta key holding all compression data for an attachment.
	 */
	const META_KEY = '_tsmlt_compression_data';

	/**
	 * Current schema version of the stored structure.
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Read the stored compression data for an attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return array Empty array when the attachment has never been compressed.
	 */
	public function get( int $attachment_id ): array {
		$data = get_post_meta( $attachment_id, self::META_KEY, true );

		if ( ! is_array( $data ) || empty( $data ) ) {
			return [];
		}

		return $this->migrate( $data );
	}

	/**
	 * Persist compression data for an attachment.
	 *
	 * @param int   $attachment_id Attachment post ID.
	 * @param array $data          Structure to store.
	 *
	 * @return void
	 */
	public function save( int $attachment_id, array $data ): void {
		$data['version'] = self::SCHEMA_VERSION;

		update_post_meta( $attachment_id, self::META_KEY, $data );
	}

	/**
	 * Remove all compression data for an attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return void
	 */
	public function delete( int $attachment_id ): void {
		delete_post_meta( $attachment_id, self::META_KEY );
	}

	/**
	 * Bring an older stored structure up to the current schema.
	 *
	 * Version 1 is the initial format, so this is currently a pass-through that
	 * only stamps missing versions. It exists so later schema changes have an
	 * obvious home.
	 *
	 * @param array $data Stored structure.
	 *
	 * @return array
	 */
	private function migrate( array $data ): array {
		$version = isset( $data['version'] ) ? absint( $data['version'] ) : 0;

		if ( self::SCHEMA_VERSION === $version ) {
			return $data;
		}

		// Rows written before versioning are structurally identical to v1.
		$data['version'] = self::SCHEMA_VERSION;

		return $data;
	}

	/**
	 * Build the stored structure from a completed processing result.
	 *
	 * @param int   $attachment_id Attachment post ID.
	 * @param array $result        Result produced by `AttachmentProcessor`.
	 * @param array $run_settings  Effective settings used for the run.
	 *
	 * @return array The structure that was saved.
	 */
	public function record_result( int $attachment_id, array $result, array $run_settings ): array {
		$existing = $this->get( $attachment_id );

		$sizes            = isset( $result['sizes'] ) && is_array( $result['sizes'] ) ? $result['sizes'] : [];
		$original_total   = 0;
		$compressed_total = 0;

		foreach ( $sizes as $size ) {
			$original_total   += (int) ( $size['before'] ?? 0 );
			$compressed_total += (int) ( $size['after'] ?? 0 );
		}

		// The very first successful compression establishes the "original" size.
		// Re-compressing later must not overwrite it, or the saved percentage
		// would silently reset to near zero.
		$baseline = isset( $existing['original']['size'] ) ? (int) $existing['original']['size'] : 0;
		if ( $baseline <= 0 ) {
			$baseline = $original_total;
		}

		$saved_bytes   = max( 0, $baseline - $compressed_total );
		$saved_percent = $baseline > 0 ? round( ( $saved_bytes / $baseline ) * 100, 2 ) : 0.0;

		$data = [
			'version'       => self::SCHEMA_VERSION,
			'status'        => (string) ( $result['status'] ?? 'completed' ),
			'original'      => [
				'size'      => $baseline,
				'mime_type' => (string) ( $result['mime_type'] ?? '' ),
				'file'      => (string) ( $result['relative_file'] ?? '' ),
			],
			'compressed'    => [
				'size'          => $compressed_total,
				'saved_bytes'   => $saved_bytes,
				'saved_percent' => $saved_percent,
			],
			'settings'      => [
				'quality'         => (int) ( $result['quality'] ?? 0 ),
				'mode'            => (string) ( $run_settings['mode'] ?? '' ),
				'engine'          => (string) ( $result['engine'] ?? '' ),
				'generated_sizes' => ! empty( $run_settings['compress_generated_sizes'] ),
			],
			'backup'        => [
				'enabled'   => ! empty( $run_settings['backup_originals'] ),
				'available' => ! empty( $result['backup_available'] ),
				'directory' => BackupManager::BACKUP_DIRNAME,
			],
			'sizes'         => $sizes,
			'compressed_at' => current_time( 'mysql', true ),
			'last_error'    => (string) ( $result['last_error'] ?? '' ),
		];

		$this->save( $attachment_id, $data );

		return $data;
	}

	/**
	 * Shape the stored data for the frontend.
	 *
	 * Only presentational values cross the wire — no server paths — and byte
	 * counts are accompanied by preformatted, translated strings so every view
	 * renders sizes identically.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return array<string, mixed>
	 */
	public function get_for_display( int $attachment_id ): array {
		$data = $this->get( $attachment_id );

		if ( empty( $data ) ) {
			return [
				'has_data'          => false,
				'status'            => '',
				'restore_available' => false,
			];
		}

		$original_size  = (int) ( $data['original']['size'] ?? 0 );
		$current_size   = (int) ( $data['compressed']['size'] ?? 0 );
		$backup_present = ! empty( $data['backup']['available'] )
			&& BackupManager::instance()->has_backup( $attachment_id );

		$sizes = [];
		foreach ( (array) ( $data['sizes'] ?? [] ) as $name => $size ) {
			$sizes[] = [
				'name'            => (string) $name,
				'before'          => (int) ( $size['before'] ?? 0 ),
				'after'           => (int) ( $size['after'] ?? 0 ),
				'before_readable' => size_format( (int) ( $size['before'] ?? 0 ), 1 ),
				'after_readable'  => size_format( (int) ( $size['after'] ?? 0 ), 1 ),
				'status'          => (string) ( $size['status'] ?? '' ),
				'reason'          => (string) ( $size['reason'] ?? '' ),
			];
		}

		return [
			'has_data'               => true,
			'status'                 => (string) ( $data['status'] ?? '' ),
			'original_size'          => $original_size,
			'current_size'           => $current_size,
			'original_size_readable' => size_format( $original_size, 1 ),
			'current_size_readable'  => size_format( $current_size, 1 ),
			'saved_bytes'            => (int) ( $data['compressed']['saved_bytes'] ?? 0 ),
			'saved_bytes_readable'   => size_format( (int) ( $data['compressed']['saved_bytes'] ?? 0 ), 1 ),
			'saved_percent'          => (float) ( $data['compressed']['saved_percent'] ?? 0 ),
			'mode'                   => (string) ( $data['settings']['mode'] ?? '' ),
			'quality'                => (int) ( $data['settings']['quality'] ?? 0 ),
			'engine'                 => (string) ( $data['settings']['engine'] ?? '' ),
			'generated_sizes'        => ! empty( $data['settings']['generated_sizes'] ),
			'compressed_at'          => (string) ( $data['compressed_at'] ?? '' ),
			'backup_enabled'         => ! empty( $data['backup']['enabled'] ),
			'restore_available'      => $backup_present && CompressionAccess::instance()->can_restore_originals(),
			'has_backup'             => $backup_present,
			'last_error'             => (string) ( $data['last_error'] ?? '' ),
			'sizes'                  => $sizes,
		];
	}

	/**
	 * Fetch display data for many attachments without an N+1 query.
	 *
	 * Priming the meta cache in one query keeps the media table fast: every
	 * later `get_post_meta()` for these IDs is served from cache.
	 *
	 * @param int[] $attachment_ids Attachment post IDs.
	 *
	 * @return array<int, array<string, mixed>> Keyed by attachment ID.
	 */
	public function get_many_for_display( array $attachment_ids ): array {
		$attachment_ids = array_values( array_filter( array_map( 'absint', $attachment_ids ) ) );

		if ( empty( $attachment_ids ) ) {
			return [];
		}

		update_meta_cache( 'post', $attachment_ids );

		$out = [];
		foreach ( $attachment_ids as $attachment_id ) {
			$out[ $attachment_id ] = $this->get_for_display( $attachment_id );
		}

		return $out;
	}
}
