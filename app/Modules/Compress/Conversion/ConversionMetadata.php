<?php
/**
 * Per-attachment image conversion metadata.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Conversion;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Reads and writes the single structured conversion meta row.
 *
 * Generated WebP/AVIF files are recorded against the original attachment rather
 * than becoming attachments of their own, so the media library keeps one entry
 * per image. Everything lives under one versioned key, making a future format
 * change a single migration.
 */
class ConversionMetadata {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * The one meta key holding all conversion data for an attachment.
	 */
	const META_KEY = '_tsmlt_image_conversion_data';

	/**
	 * Current schema version of the stored structure.
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Read the stored conversion data for an attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return array Empty array when the attachment has never been converted.
	 */
	public function get( int $attachment_id ): array {
		$data = get_post_meta( $attachment_id, self::META_KEY, true );

		if ( ! is_array( $data ) || empty( $data ) ) {
			return [];
		}

		return $this->migrate( $data );
	}

	/**
	 * Persist conversion data for an attachment.
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
	 * Remove all conversion data for an attachment.
	 *
	 * Does not touch the generated files — deleting those is `ConversionManager`'s
	 * job, and it calls this afterwards.
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
	 * Version 1 is the initial format, so this only stamps missing versions. It
	 * exists so later schema changes have an obvious home.
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

		$data['version'] = self::SCHEMA_VERSION;

		return $data;
	}

	/**
	 * Whether the source image has changed since it was last converted.
	 *
	 * Compares the recorded size and modification time against the file on disk.
	 * A regenerated thumbnail set or a re-uploaded original leaves the generated
	 * WebP/AVIF describing an image that no longer exists, and this is how the
	 * UI knows to offer a regenerate.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return bool False when there is no conversion data to compare against.
	 */
	public function is_stale( int $attachment_id ): bool {
		$data = $this->get( $attachment_id );

		if ( empty( $data['source'] ) ) {
			return false;
		}

		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			return true;
		}

		clearstatcache( true, $file );

		$recorded_size = (int) ( $data['source']['size'] ?? 0 );
		$recorded_time = (int) ( $data['source']['modified'] ?? 0 );

		if ( $recorded_size > 0 && (int) filesize( $file ) !== $recorded_size ) {
			return true;
		}

		return $recorded_time > 0 && (int) filemtime( $file ) !== $recorded_time;
	}

	/**
	 * Shape the stored data for the frontend.
	 *
	 * Only presentational values cross the wire — uploads-relative paths, never
	 * absolute server paths — and byte counts come with preformatted strings so
	 * every view renders sizes identically.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return array<string, mixed>
	 */
	public function get_for_display( int $attachment_id ): array {
		$data = $this->get( $attachment_id );

		if ( empty( $data ) ) {
			return [
				'has_data' => false,
				'status'   => '',
				'formats'  => [],
			];
		}

		$source_size = (int) ( $data['source']['size'] ?? 0 );
		$formats     = [];

		foreach ( (array) ( $data['formats'] ?? [] ) as $format => $info ) {
			$size    = (int) ( $info['size'] ?? 0 );
			$saved   = $source_size > 0 ? max( 0, $source_size - $size ) : 0;
			$percent = $source_size > 0 && $size > 0
				? round( ( $saved / $source_size ) * 100, 2 )
				: 0.0;

			$formats[] = [
				'format'        => (string) $format,
				'status'        => (string) ( $info['status'] ?? '' ),
				'size'          => $size,
				'size_readable' => size_format( $size, 1 ),
				'saved_percent' => $percent,
				'quality'       => (int) ( $info['quality'] ?? 0 ),
				'generated_at'  => (string) ( $info['generated_at'] ?? '' ),
				'sizes_count'   => count( (array) ( $info['sizes'] ?? [] ) ),
				'reason'        => (string) ( $info['reason'] ?? '' ),
			];
		}

		return [
			'has_data'             => true,
			'status'               => (string) ( $data['status'] ?? '' ),
			'source_size'          => $source_size,
			'source_size_readable' => size_format( $source_size, 1 ),
			'formats'              => $formats,
			'is_stale'             => $this->is_stale( $attachment_id ),
			'last_error'           => (string) ( $data['last_error'] ?? '' ),
		];
	}

	/**
	 * Fetch display data for many attachments without an N+1 query.
	 *
	 * Priming the meta cache in one query keeps listings fast: every later
	 * `get_post_meta()` for these IDs is served from cache.
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
