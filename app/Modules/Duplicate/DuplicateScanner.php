<?php
/**
 * Duplicate media file detection via MD5 file hashing.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Duplicate;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Scans media library attachments for duplicate files using MD5 hashing.
 */
class DuplicateScanner {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Scan a batch of attachments and store their file hashes.
	 *
	 * Optimized for large libraries: pre-loads all already-scanned IDs in one
	 * query, then bulk-inserts new rows and bulk-updates existing rows after the
	 * loop — reducing DB queries from O(2n) to O(4) per batch.
	 *
	 * @param int $offset     Offset to start scanning from.
	 * @param int $batch_size Number of attachments to scan per batch.
	 *
	 * @return array{processed: int, total: int, complete: bool}
	 */
	public function scan_batch( int $offset = 0, int $batch_size = 50 ): array {
		// Get total attachment count.
		$total_result = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->get();
		$total        = (int) ( $total_result[0]['total'] ?? 0 );

		// Get batch of attachment IDs.
		$batch = Fns::DB()->select( 'ID' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->orderBy( 'ID', 'ASC' )
			->limit( $batch_size )
			->offset( $offset )
			->get();

		if ( empty( $batch ) ) {
			return [
				'processed' => $offset,
				'total'     => $total,
				'complete'  => true,
			];
		}

		// Collect the IDs in this batch.
		$batch_ids = array_map( fn( $row ) => (int) $row['ID'], $batch );

		// Pre-load which IDs are already in the duplicate table (1 query).
		$existing_rows = Fns::DB()->select( 'attachment_id' )
			->from( 'tsmlt_duplicate_file' )
			->whereIn( 'attachment_id', ...$batch_ids )
			->get();

		$already_scanned = [];
		foreach ( ( $existing_rows ?: [] ) as $r ) {
			$already_scanned[ (int) $r['attachment_id'] ] = true;
		}

		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] );

		// Rows to bulk-insert (new) and update payloads (existing).
		$rows_to_insert  = [];
		$rows_to_update  = [];
		$processed       = 0;

		foreach ( $batch as $row ) {
			$attachment_id = (int) $row['ID'];
			$file_path     = get_attached_file( $attachment_id );

			if ( ! $file_path || ! file_exists( $file_path ) ) {
				++$processed;
				continue;
			}

			$file_hash = md5_file( $file_path );
			if ( false === $file_hash ) {
				++$processed;
				continue;
			}

			$file_size = (int) filesize( $file_path );
			$rel_path  = str_replace( $base_dir, '', $file_path );

			if ( isset( $already_scanned[ $attachment_id ] ) ) {
				// Queue for update.
				$rows_to_update[] = [
					'attachment_id' => $attachment_id,
					'file_hash'     => $file_hash,
					'file_size'     => $file_size,
					'file_path'     => $rel_path,
				];
			} else {
				// Queue for insert.
				$rows_to_insert[] = [
					'attachment_id' => $attachment_id,
					'file_hash'     => $file_hash,
					'file_size'     => $file_size,
					'file_path'     => $rel_path,
				];
			}

			++$processed;
		}

		// Bulk insert all new rows (1 query).
		if ( ! empty( $rows_to_insert ) ) {
			Fns::DB()->insert( 'tsmlt_duplicate_file', $rows_to_insert )->execute();
		}

		// Update existing rows individually — query builder has no bulk UPDATE,
		// but these are rare (re-scanning already-scanned IDs).
		foreach ( $rows_to_update as $update ) {
			$attachment_id = $update['attachment_id'];
			unset( $update['attachment_id'] );
			Fns::DB()->update( 'tsmlt_duplicate_file', $update )
				->where( 'attachment_id', '=', $attachment_id )
				->execute();
		}

		$new_offset = $offset + $processed;
		$complete   = $new_offset >= $total;

		return [
			'processed' => $new_offset,
			'total'     => $total,
			'complete'  => $complete,
		];
	}

	/**
	 * Remove rows from tsmlt_duplicate_file for attachments that no longer exist.
	 */
	private function purge_stale_rows(): void {
		$all_ids_result = Fns::DB()->select( 'attachment_id' )
			->from( 'tsmlt_duplicate_file' )
			->get();
		if ( empty( $all_ids_result ) ) {
			return;
		}
		foreach ( $all_ids_result as $row ) {
			$att_id = (int) $row['attachment_id'];
			if ( ! get_post( $att_id ) ) {
				Fns::DB()->delete( 'tsmlt_duplicate_file' )
					->where( 'attachment_id', '=', $att_id )
					->execute();
			}
		}
	}

	/**
	 * Get duplicate groups with pagination.
	 *
	 * @param array $query {
	 *     @type int $paged       Current page number.
	 *     @type int $postsPerPage Items per page.
	 * }
	 *
	 * @return string JSON-encoded result.
	 */
	public function get_duplicates( array $query ): string {
		$page   = max( 1, absint( $query['paged'] ?? 1 ) );
		$limit  = max( 1, absint( $query['postsPerPage'] ?? 20 ) );
		$offset = ( $page - 1 ) * $limit;

		// Get duplicate hashes with pagination.
		$hashes = Fns::DB()->select( 'file_hash', 'file_size' )
			->count( '*', 'cnt' )
			->from( 'tsmlt_duplicate_file' )
			->groupBy( 'file_hash' )
			->raw( 'HAVING cnt > 1' )
			->orderBy( 'cnt', 'DESC' )
			->limit( $limit )
			->offset( $offset )
			->get();

		// Remove stale rows before counting so total_groups is accurate.
		$this->purge_stale_rows();

		// Get total count of duplicate groups.
		$total_result = Fns::DB()->select( 'file_hash' )
			->count( '*', 'cnt' )
			->from( 'tsmlt_duplicate_file' )
			->groupBy( 'file_hash' )
			->raw( 'HAVING cnt > 1' )
			->get();
		$total_groups = is_array( $total_result ) ? count( $total_result ) : 0;

		$groups = [];
		foreach ( ( $hashes ?: [] ) as $hash_row ) {
			$file_hash = $hash_row['file_hash'];

			// Get all items in this duplicate group.
			$items = Fns::DB()->select( 'attachment_id', 'file_size', 'file_path' )
				->from( 'tsmlt_duplicate_file' )
				->where( 'file_hash', '=', $file_hash )
				->orderBy( 'attachment_id', 'ASC' )
				->get();

			$group_items = [];
			foreach ( ( $items ?: [] ) as $item ) {
				$att_id = (int) $item['attachment_id'];
				$post   = get_post( $att_id );
				if ( ! $post ) {
					continue;
				}

				$thumbnail = wp_get_attachment_image_url( $att_id, 'thumbnail' );
				$url       = wp_get_attachment_url( $att_id );

				// Find all posts/pages where this image is used.
				$used_in    = [];
				$found_ids  = [];
				$post_ids   = $url ? Fns::search_image_at_content( $url ) : [];
				foreach ( $post_ids as $post_id ) {
					$used_post = get_post( (int) $post_id );
					if ( $used_post && ! in_array( $used_post->ID, $found_ids, true ) ) {
						$found_ids[] = $used_post->ID;
						$used_in[]   = [
							'title'     => get_the_title( $used_post ),
							'permalink' => get_the_permalink( $used_post ),
						];
					}
				}

				// Include parent post if not already found.
				if ( $post->post_parent && ! in_array( $post->post_parent, $found_ids, true ) ) {
					$parent = get_post( $post->post_parent );
					if ( $parent ) {
						$used_in[] = [
							'title'     => get_the_title( $parent ),
							'permalink' => get_the_permalink( $parent ),
						];
					}
				}

				$group_items[] = [
					'attachment_id' => $att_id,
					'title'         => $post->post_title,
					'url'           => $url ?: '',
					'thumbnail'     => $thumbnail ?: '',
					'file_path'     => $item['file_path'],
					'file_size'     => (int) $item['file_size'],
					'used_in'       => $used_in,
					'upload_date'   => $post->post_date,
				];
			}

			if ( count( $group_items ) > 1 ) {
				$groups[] = [
					'file_hash'  => $file_hash,
					'file_size'  => (int) $hash_row['file_size'],
					'item_count' => count( $group_items ),
					'items'      => $group_items,
				];
			}
		}

		return wp_json_encode(
			[
				'groups'       => $groups,
				'totalGroups'  => $total_groups,
				'paged'        => $page,
				'postsPerPage' => $limit,
			]
		);
	}

	/**
	 * Get scan status and summary statistics.
	 *
	 * @return array{total_attachments: int, scanned: int, duplicate_groups: int, potential_savings: int}
	 */
	public function get_scan_status(): array {
		$this->purge_stale_rows();

		// Total attachments.
		$total_result = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'posts' )
			->where( 'post_type', '=', 'attachment' )
			->andWhere( 'post_status', '=', 'inherit' )
			->get();
		$total_attachments = (int) ( $total_result[0]['total'] ?? 0 );

		// Scanned count.
		$scanned_result = Fns::DB()->select()
			->count( '*', 'total' )
			->from( 'tsmlt_duplicate_file' )
			->get();
		$scanned = (int) ( $scanned_result[0]['total'] ?? 0 );

		// Duplicate groups — count rows from GROUP BY HAVING query.
		$dup_rows = Fns::DB()->select( 'file_hash', 'file_size' )
			->count( '*', 'cnt' )
			->from( 'tsmlt_duplicate_file' )
			->groupBy( 'file_hash' )
			->raw( 'HAVING cnt > 1' )
			->get();
		$duplicate_groups = is_array( $dup_rows ) ? count( $dup_rows ) : 0;

		// Potential savings: for each duplicate group, file_size * (count - 1).
		$savings = 0;
		if ( ! empty( $dup_rows ) ) {
			foreach ( $dup_rows as $row ) {
				$file_size = isset( $row['file_size'] ) ? (int) $row['file_size'] : 0;
				$savings += $file_size * ( (int) $row['cnt'] - 1 );
			}
		}

		return [
			'total_attachments' => $total_attachments,
			'scanned'           => $scanned,
			'duplicate_groups'  => $duplicate_groups,
			'potential_savings' => $savings,
		];
	}

	/**
	 * Clear all scan results.
	 *
	 * @return array{updated: bool, message: string}
	 */
	public function clear_scan(): array {
		Fns::DB()->truncate( 'tsmlt_duplicate_file' );
		Fns::DB()->alter( 'tsmlt_duplicate_file' )->modify( 'id' )->int()->autoIncrement()->execute();

		return [
			'updated' => true,
			'message' => esc_html__( 'Duplicate scan results cleared.', 'media-library-tools' ),
		];
	}
}
