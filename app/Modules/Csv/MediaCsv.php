<?php
/**
 * Standard media CSV columns and encoding, shared by the CSV export tools.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Csv;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use WP_Post;

/**
 * The standard media CSV: a fixed allowlist of columns, the values of one
 * attachment as cells, spreadsheet formula protection and RFC 4180
 * encoding. Used by the Free tsmlt/export-media-csv ability and reusable by
 * other CSV exports (the Pro library export), so every export writes the
 * same columns the CSV importers read.
 *
 * Only the allowlisted columns exist: never file paths, the attachment
 * metadata array, EXIF data or custom fields. Import-only columns such as
 * rename_to are never written. Nothing here writes or hooks anything.
 */
class MediaCsv {

	/**
	 * Exported columns, in their default order (names the CSV importers
	 * read).
	 *
	 * @var string[]
	 */
	const COLUMNS = [ 'ID', 'slug', 'url', 'title', 'alt_text', 'caption', 'description', 'post_parent', 'menu_order', 'groups' ];

	/**
	 * Columns holding numbers the plugin generates; never formula-protected.
	 *
	 * @var string[]
	 */
	const NUMERIC_COLUMNS = [ 'ID', 'post_parent', 'menu_order' ];

	/**
	 * A cell a spreadsheet may run as a formula: optional apostrophes, then
	 * =, +, -, @, a tab or a carriage return (the rule of the CSV importers
	 * and of the admin CSV export).
	 *
	 * @var string
	 */
	const FORMULA_START = "/^'*[=+\\-@\t\r]/";

	/**
	 * A valid column list: one or more different names from COLUMNS.
	 *
	 * @param mixed $columns Raw columns.
	 *
	 * @return string[]|null The columns, in the order given; null when invalid.
	 */
	public static function validate_columns( $columns ): ?array {
		if ( ! is_array( $columns ) || ! $columns || array_values( $columns ) !== $columns ) {
			return null;
		}
		foreach ( $columns as $column ) {
			if ( ! is_string( $column ) || ! in_array( $column, self::COLUMNS, true ) ) {
				return null;
			}
		}
		return count( array_unique( $columns ) ) === count( $columns ) ? $columns : null;
	}

	/**
	 * Load what the rows of these attachments need in a few queries: the
	 * posts with their meta, their parents, and their Group names.
	 *
	 * @param int[] $ids Attachment IDs.
	 *
	 * @return array<int, string[]> Group names by attachment ID.
	 */
	public static function prime( array $ids ): array {
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return [];
		}
		_prime_post_caches( $ids, false, true );
		$parents = [];
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post instanceof WP_Post && $post->post_parent > 0 ) {
				$parents[] = (int) $post->post_parent;
			}
		}
		if ( $parents ) {
			_prime_post_caches( array_values( array_unique( $parents ) ), false, false );
		}
		return self::group_names( $ids );
	}

	/**
	 * One attachment's cells, in the order of $columns.
	 *
	 * @param WP_Post       $attachment Attachment.
	 * @param string[]      $columns    Columns (validate_columns()).
	 * @param string[]|null $groups     Its Group names (from prime()); null to look them up.
	 *
	 * @return string[]
	 */
	public static function row( WP_Post $attachment, array $columns, ?array $groups = null ): array {
		if ( null === $groups ) {
			$groups = self::group_names( [ (int) $attachment->ID ] )[ (int) $attachment->ID ] ?? [];
		}
		$url    = wp_get_attachment_url( $attachment->ID );
		$values = [
			'ID'          => (int) $attachment->ID,
			'slug'        => (string) $attachment->post_name,
			'url'         => $url ? (string) $url : '',
			'title'       => Fns::prepare_text_for_json( $attachment->post_title ),
			'alt_text'    => Fns::prepare_text_for_json( get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) ),
			'caption'     => Fns::prepare_text_for_json( $attachment->post_excerpt ),
			'description' => Fns::prepare_text_for_json( $attachment->post_content ),
			'post_parent' => self::readable_parent( (int) $attachment->post_parent ),
			'menu_order'  => (int) $attachment->menu_order,
			'groups'      => implode( ', ', array_map( [ Fns::class, 'prepare_text_for_json' ], $groups ) ),
		];

		$row = [];
		foreach ( $columns as $column ) {
			$value = (string) $values[ $column ];
			$row[] = in_array( $column, self::NUMERIC_COLUMNS, true ) ? $value : self::protect( $value );
		}
		return $row;
	}

	/**
	 * Protect a text cell for spreadsheets: one apostrophe in front when it
	 * would run as a formula. The CSV importers remove exactly that
	 * apostrophe again.
	 *
	 * @param string $value Cell text.
	 *
	 * @return string
	 */
	public static function protect( string $value ): string {
		return 1 === preg_match( self::FORMULA_START, $value ) ? "'" . $value : $value;
	}

	/**
	 * Rows as CSV text: RFC 4180 quoting, no escape character, invalid UTF-8
	 * removed.
	 *
	 * @param array[] $lines Rows of cell strings (the first is usually the header).
	 *
	 * @return string
	 */
	public static function to_csv( array $lines ): string {
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream.
		foreach ( $lines as $line ) {
			fputcsv( $handle, $line, ',', '"', '' );
		}
		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.

		return wp_check_invalid_utf8( $csv, true );
	}

	/**
	 * The parent post ID when it is a valid attachment parent the user may
	 * read, else 0 (the importers accept the former and detach on 0).
	 *
	 * @param int $parent_id Stored post_parent.
	 *
	 * @return int
	 */
	private static function readable_parent( int $parent_id ): int {
		return $parent_id > 0 && Fns::is_valid_attachment_parent( $parent_id ) && current_user_can( 'read_post', $parent_id ) ? $parent_id : 0;
	}

	/**
	 * Group names of attachments, in one query, each list sorted by name.
	 *
	 * @param int[] $ids Attachment IDs.
	 *
	 * @return array<int, string[]>
	 */
	private static function group_names( array $ids ): array {
		$names = array_fill_keys( $ids, [] );
		$terms = wp_get_object_terms(
			$ids,
			Fns::CATEGORY,
			[
				'fields'                 => 'all_with_object_id',
				'orderby'                => 'name',
				'update_term_meta_cache' => false,
			]
		);
		foreach ( is_array( $terms ) ? $terms : [] as $term ) {
			$names[ (int) $term->object_id ][] = (string) $term->name;
		}
		return $names;
	}
}
