<?php
/**
 * Ability: tsmlt/export-media-csv.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities\Media;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abs\Ability;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Modules\Csv\MediaCsv;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;

/**
 * Export selected attachments as one CSV text, in the order of the IDs
 * given, with the standard columns (MediaCsv) the CSV importers read.
 * Read-only.
 *
 * Each ID must be an attachment in the media library (`inherit`) the user
 * may read; any other ID — missing, not media, trashed, private or not
 * readable — is skipped and reported alike, so the response reveals
 * nothing about it. Never exports file paths, attachment metadata, EXIF
 * data or custom fields.
 */
class ExportMediaCsv extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Most IDs per call.
	 *
	 * @var int
	 */
	const MAX_IDS = 100;

	/**
	 * Skip reason: missing, not media, not in the library, or not readable.
	 *
	 * @var string
	 */
	const REASON_NOT_AVAILABLE = 'not_available';

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/export-media-csv';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Export selected media as CSV', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Export specific media attachments, given by their WordPress attachment (media) IDs, as one CSV text: 1 to 100 distinct IDs per export, rows in the order of the IDs given. Columns: ID, slug, url, title, alt_text, caption, description, post_parent (0 when there is none or you cannot read it), menu_order and groups (Group names, comma-separated), or the columns you choose. These are the columns the CSV import reads (remove url to re-import); import-only columns such as rename_to are never exported, and neither are custom fields, file paths or metadata. Text cells that a spreadsheet would run as a formula start with an extra apostrophe. IDs that are missing, not media, trashed, private or not readable by you are listed in skipped. Read-only. This tool always needs the IDs; when the user gives them, export them. When the user asks to export the whole Media Library, or a bulk export without IDs: use tsmlt/export-media-csv-bulk if it is available (Pro); otherwise say briefly that exporting without media IDs, or the whole library, is a Pro feature, and ask for the media IDs to export with this tool. Never imitate a whole-library export with this tool: do not collect IDs yourself (for example with tsmlt/search-media) to export them batch after batch, and never say the free version can export the whole library. Split IDs the user gave into several exports only when they ask for it.', 'media-library-tools' );
	}

	/**
	 * Input schema.
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'default'              => [],
			'required'             => [ 'ids' ],
			'properties'           => [
				'ids'     => [
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => self::MAX_IDS,
					'uniqueItems' => true,
					'items'       => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'description' => 'WordPress media (attachment) IDs given by the user, 1 to 100 distinct IDs. Rows follow this order.',
				],
				'columns' => self::columns_schema(),
			],
		];
	}

	/**
	 * Input schema of a column list.
	 *
	 * @return array
	 */
	private static function columns_schema(): array {
		return [
			'type'        => 'array',
			'minItems'    => 1,
			'maxItems'    => count( MediaCsv::COLUMNS ),
			'uniqueItems' => true,
			'items'       => [
				'type' => 'string',
				'enum' => MediaCsv::COLUMNS,
			],
			'description' => 'Columns to export, in this order. Defaults to all columns.',
		];
	}

	/**
	 * Output schema.
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'csv', 'columns', 'rows', 'exported_ids', 'skipped' ],
			'properties'           => [
				'csv'          => [
					'type'        => 'string',
					'description' => 'CSV text, UTF-8: a header row, then one row per exported attachment.',
				],
				'columns'      => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'rows'         => [
					'type'        => 'integer',
					'description' => 'Data rows in csv.',
				],
				'exported_ids' => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
				],
				'skipped'      => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'reason' ],
						'properties'           => [
							'id'     => [ 'type' => 'integer' ],
							'reason' => [
								'type'        => 'string',
								'enum'        => [ self::REASON_NOT_AVAILABLE ],
								'description' => 'Missing, not media, trashed, private, or not readable by you.',
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Annotations.
	 *
	 * @return array<string, bool>
	 */
	protected function get_annotations(): array {
		return [
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		];
	}

	/**
	 * Export the attachments.
	 *
	 * @param mixed $input Ability input, already validated against the schema.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		// The callback can be reached without the permission callback.
		$base = AbilityGuard::check_base_capability();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		if ( ! is_array( $input ) || array_diff( array_keys( $input ), [ 'ids', 'columns' ] ) ) {
			return $this->invalid( __( 'Only ids and columns are accepted.', 'media-library-tools' ) );
		}
		$ids = self::validate_ids( $input['ids'] ?? null );
		if ( null === $ids ) {
			return $this->invalid( __( 'ids must be a list of 1 to 100 distinct attachment IDs (positive integers).', 'media-library-tools' ) );
		}
		$columns = array_key_exists( 'columns', $input ) ? MediaCsv::validate_columns( $input['columns'] ) : MediaCsv::COLUMNS;
		if ( null === $columns ) {
			return $this->invalid(
				sprintf(
					/* translators: %s: column names. */
					__( 'columns must be a list of different column names from: %s.', 'media-library-tools' ),
					implode( ', ', MediaCsv::COLUMNS )
				)
			);
		}

		$groups   = MediaCsv::prime( $ids );
		$lines    = [ $columns ];
		$exported = [];
		$skipped  = [];
		foreach ( $ids as $id ) {
			$attachment = get_post( $id );
			if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || 'inherit' !== $attachment->post_status || ! current_user_can( 'read_post', $id ) ) {
				$skipped[] = [
					'id'     => $id,
					'reason' => self::REASON_NOT_AVAILABLE,
				];
				continue;
			}
			$lines[]    = MediaCsv::row( $attachment, $columns, $groups[ $id ] ?? [] );
			$exported[] = $id;
		}

		return [
			'csv'          => MediaCsv::to_csv( $lines ),
			'columns'      => $columns,
			'rows'         => count( $exported ),
			'exported_ids' => $exported,
			'skipped'      => $skipped,
		];
	}

	/**
	 * A valid ID list: 1 to MAX_IDS distinct positive integers.
	 *
	 * @param mixed $ids Raw IDs.
	 *
	 * @return int[]|null The IDs in the order given; null when invalid.
	 */
	private static function validate_ids( $ids ): ?array {
		if ( ! is_array( $ids ) || ! $ids || count( $ids ) > self::MAX_IDS || array_values( $ids ) !== $ids ) {
			return null;
		}
		foreach ( $ids as $id ) {
			if ( ! is_int( $id ) || $id < 1 ) {
				return null;
			}
		}
		return count( array_unique( $ids ) ) === count( $ids ) ? $ids : null;
	}

	/**
	 * Error for invalid input.
	 *
	 * @param string $message Message.
	 *
	 * @return WP_Error
	 */
	private function invalid( string $message ): WP_Error {
		return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, $message, 400 );
	}
}
