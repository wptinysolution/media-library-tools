<?php
/**
 * Ability: tsmlt/preview-media-rename.
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
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;

/**
 * Preview renaming the files of up to 20 attachments to typed names — the
 * Rename page's single rename and Bulk Rename with your own names — without
 * renaming anything.
 *
 * The resulting name is worked out as Fns::wp_rename_attachment() does it:
 * the typed name passes sanitize_text_field() (as RenameModule does) and
 * Fns::rename_target_name() (the part before the last dot, sanitized, with
 * "-scaled" and "-rotated" removed), keeps the current extension, and gets a
 * "-1"-style suffix from wp_unique_filename() when the name is taken.
 *
 * Read-only: never calls RenameModule or wp_rename_attachment(), never
 * touches files, metadata, content or parents. Pro rename strategies
 * (attached post title, SKU, alt text, AI) are refused, not taken as names.
 * Only file names are returned, never folders or paths.
 *
 * Registered with `meta.mcp.public` false: listed by name on the dedicated
 * server and the default server, not run through the default gateway.
 */
class PreviewMediaRename extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Most items per request.
	 *
	 * @var int
	 */
	const MAX_ITEMS = 20;

	/**
	 * Longest typed name accepted.
	 *
	 * @var int
	 */
	const MAX_NAME_LENGTH = 200;

	/**
	 * Keywords the Rename page sends for Pro strategies; refused here.
	 *
	 * @var string[]
	 */
	const PRO_KEYWORDS = [ 'bulkrenamebyposttitle', 'bulkrenamebysku', 'bulkrenamebyalttext', 'bulkaifilename' ];

	/**
	 * Per-item statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'ready', 'unchanged', 'invalid_filename', 'pro_strategy_not_supported', 'invalid_id', 'not_found', 'forbidden', 'file_missing' ];

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/preview-media-rename';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Preview media rename', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Preview renaming the files of 1 to 20 attachments to names you type (the Rename page\'s single rename and Bulk Rename with your own names), without renaming anything. Find attachments first with tsmlt-search-media (its filename input matches part of a file name and returns each attachment\'s id and filename). items: a list of { id, filename }. Per item: status (ready, unchanged, invalid_filename, pro_strategy_not_supported, invalid_id, not_found, forbidden, file_missing), the current and the resulting file name (the current extension is always kept; a typed extension is ignored), whether the typed name was changed by sanitizing, whether a "-1"-style suffix would be added because the name is taken, and what a rename would affect: generated sizes (deleted and made again), the full-size original WordPress keeps for scaled uploads and pre-edit backup sizes (both left with their old names), and whether links in post content would be updated. Renaming by attached post title, product SKU, alt text or AI is not available here. Read-only.', 'media-library-tools' );
	}

	/**
	 * Listed by name only; not run through the default gateway.
	 *
	 * @return bool
	 */
	protected function is_mcp_public(): bool {
		return false;
	}

	/**
	 * @return array
	 */
	protected function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'items' ],
			'properties'           => [
				'items' => [
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => self::MAX_ITEMS,
					'items'    => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'filename' ],
						'properties'           => [
							'id'       => [
								'type'    => 'integer',
								'minimum' => 1,
							],
							'filename' => [
								'type'        => 'string',
								'minLength'   => 1,
								'maxLength'   => self::MAX_NAME_LENGTH,
								'description' => 'New file name, without folders; the current extension is kept.',
							],
						],
					],
				],
			],
		];
	}

	/**
	 * @return array
	 */
	protected function get_output_schema(): array {
		$name = [ 'type' => 'string' ];
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'items', 'summary' ],
			'properties'           => [
				'items'   => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'id', 'status', 'requested_filename', 'current_filename', 'proposed_filename', 'sanitized', 'extension_ignored', 'unchanged', 'needs_unique_suffix', 'same_name_in_request', 'affects' ],
						'properties'           => [
							'id'                   => [ 'type' => 'integer' ],
							'status'               => [
								'type' => 'string',
								'enum' => self::STATUSES,
							],
							'requested_filename'   => $name,
							'current_filename'     => $name,
							'proposed_filename'    => [
								'type'        => 'string',
								'description' => 'The file name a rename would give the file, with its extension; empty when the item cannot be renamed.',
							],
							'sanitized'            => [
								'type'        => 'boolean',
								'description' => 'Whether sanitizing changed the typed name.',
							],
							'extension_ignored'    => [
								'type'        => 'boolean',
								'description' => 'Whether the typed name ended in an extension (or other ".text") that is dropped.',
							],
							'unchanged'            => [ 'type' => 'boolean' ],
							'needs_unique_suffix'  => [
								'type'        => 'boolean',
								'description' => 'Whether the name is taken in the file\'s folder, so a "-1"-style suffix would be added.',
							],
							'same_name_in_request' => [
								'type'        => 'boolean',
								'description' => 'Another item of this request would get the same name in the same folder; the second rename would get a suffix.',
							],
							'affects'              => [
								'type'                 => 'object',
								'additionalProperties' => false,
								'required'             => [ 'generated_sizes', 'original_image_kept', 'backup_sizes', 'content_links_updated' ],
								'properties'           => [
									'generated_sizes'     => [
										'type'        => 'integer',
										'description' => 'Generated image sizes a rename makes again under the new name (old size files are removed only once replaced).',
									],
									'original_image_kept' => [
										'type'        => 'boolean',
										'description' => 'A full-size original of a scaled or rotated upload exists; a rename renames it too and keeps the link (the field name is kept for compatibility).',
									],
									'backup_sizes'        => [
										'type'        => 'integer',
										'description' => 'Pre-edit backup files a rename leaves with their old names.',
									],
									'content_links_updated' => [
										'type'        => 'boolean',
										'description' => 'Whether a rename replaces links to the file in post content and excerpts (images only).',
									],
								],
							],
						],
					],
				],
				'summary' => [
					'type'                 => 'object',
					'additionalProperties' => [ 'type' => 'integer' ],
				],
			],
		];
	}

	/**
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
	 * Preview each item.
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
		$items = self::sanitize_input( $input );
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$results = [];
		foreach ( $items as $item ) {
			$results[] = self::preview( $item['id'], $item['filename'] );
		}
		$results = self::mark_same_names( $results );

		$summary = array_fill_keys( self::STATUSES, 0 );
		foreach ( $results as $index => $result ) {
			++$summary[ $result['status'] ];
			unset( $results[ $index ]['folder'] );
		}

		return [
			'items'   => $results,
			'summary' => $summary,
		];
	}

	/**
	 * Validate the input: 1 to 20 items, each a positive integer ID and a
	 * typed name (a non-integer ID is reported per item as invalid_id).
	 *
	 * @param mixed $input Raw input.
	 *
	 * @return array<int, array{id: mixed, filename: string}>|WP_Error
	 */
	private static function sanitize_input( $input ) {
		$items = is_array( $input ) && [] === array_diff( array_keys( $input ), [ 'items' ] ) ? ( $input['items'] ?? null ) : null;
		if ( ! is_array( $items ) || [] === $items || count( $items ) > self::MAX_ITEMS || array_keys( $items ) !== range( 0, count( $items ) - 1 ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'items must be a list of 1 to 20 objects with id and filename.', 'media-library-tools' ), 400 );
		}
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || array_diff( array_keys( $item ), [ 'id', 'filename' ] ) || ! array_key_exists( 'id', $item )
				|| ! is_string( $item['filename'] ?? null ) || '' === $item['filename'] || mb_strlen( $item['filename'] ) > self::MAX_NAME_LENGTH ) {
				return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Each item needs an id and a filename of 1 to 200 characters, and nothing else.', 'media-library-tools' ), 400 );
			}
		}
		return $items;
	}

	/**
	 * Preview one item.
	 *
	 * @param mixed  $id        Attachment ID.
	 * @param string $requested Typed name.
	 *
	 * @return array
	 */
	private static function preview( $id, string $requested ): array {
		$result = [
			'id'                   => is_int( $id ) ? $id : 0,
			'status'               => '',
			'requested_filename'   => $requested,
			'current_filename'     => '',
			'proposed_filename'    => '',
			'sanitized'            => false,
			'extension_ignored'    => false,
			'unchanged'            => false,
			'needs_unique_suffix'  => false,
			'same_name_in_request' => false,
			'affects'              => [
				'generated_sizes'       => 0,
				'original_image_kept'   => false,
				'backup_sizes'          => 0,
				'content_links_updated' => false,
			],
			'folder'               => '',
		];

		if ( ! is_int( $id ) || $id < 1 ) {
			$result['status'] = 'invalid_id';
			return $result;
		}
		$attachment = self::editable( $id );
		if ( is_string( $attachment ) ) {
			$result['status'] = $attachment;
			return $result;
		}

		$file = get_attached_file( $id );
		if ( is_string( $file ) && '' !== $file ) {
			$result['current_filename'] = wp_basename( $file );
		}

		$name_status = self::name_status( $requested );
		if ( '' !== $name_status ) {
			$result['status'] = $name_status;
			return $result;
		}
		if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
			$result['status'] = 'file_missing';
			return $result;
		}

		// The same steps as the Rename page's rename handler and file rename.
		$typed     = sanitize_text_field( $requested );
		$target    = Fns::rename_target_name( $typed );
		$extension = pathinfo( $file, PATHINFO_EXTENSION );
		$current   = basename( $file, '.' . $extension );

		$result['extension_ignored'] = '' !== pathinfo( $typed, PATHINFO_EXTENSION );
		$result['sanitized']         = pathinfo( $requested, PATHINFO_FILENAME ) !== $target;
		if ( '' === $target ) {
			$result['status'] = 'invalid_filename';
			return $result;
		}

		$proposed = $target . '.' . $extension;
		if ( $target === $current ) {
			$result['status']            = 'unchanged';
			$result['unchanged']         = true;
			$result['proposed_filename'] = wp_basename( $file );
			return $result;
		}

		$folder                        = dirname( $file );
		$unique                        = wp_unique_filename( $folder, $proposed );
		$result['status']              = 'ready';
		$result['proposed_filename']   = $unique;
		$result['needs_unique_suffix'] = $unique !== $proposed;
		$result['affects']             = self::affects( $id, $file );
		$result['folder']              = $folder;
		return $result;
	}

	/**
	 * Why a typed name is refused ('' when it is usable): folders, control
	 * characters, or a Pro strategy keyword.
	 *
	 * @param string $requested Typed name.
	 *
	 * @return string
	 */
	private static function name_status( string $requested ): string {
		if ( preg_match( '#[/\\\\\x00-\x1F\x7F]#', $requested ) ) {
			return 'invalid_filename';
		}
		$plain = strtolower( (string) preg_replace( '/[^a-z]/i', '', pathinfo( $requested, PATHINFO_FILENAME ) ) );
		return in_array( $plain, self::PRO_KEYWORDS, true ) ? 'pro_strategy_not_supported' : '';
	}

	/**
	 * What a rename of this file would affect, as Fns::wp_rename_attachment()
	 * handles it.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $file Attached file.
	 *
	 * @return array
	 */
	private static function affects( int $id, string $file ): array {
		$type     = wp_check_filetype( $file );
		$is_image = false !== strpos( (string) $type['type'], 'image' );
		$sizes    = 0;
		if ( $is_image && 'image/svg+xml' !== $type['type'] ) {
			$metadata = wp_get_attachment_metadata( $id );
			$sizes    = is_array( $metadata ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ? count( $metadata['sizes'] ) : 0;
		}
		$original = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $id ) : false;
		$backups  = get_post_meta( $id, '_wp_attachment_backup_sizes', true );

		return [
			'generated_sizes'       => $sizes,
			'original_image_kept'   => is_string( $original ) && $original !== $file && file_exists( $original ),
			'backup_sizes'          => is_array( $backups ) ? count( $backups ) : 0,
			'content_links_updated' => $is_image,
		];
	}

	/**
	 * Flag items of the request that would get the same name in the same
	 * folder.
	 *
	 * @param array $results Previews.
	 *
	 * @return array
	 */
	private static function mark_same_names( array $results ): array {
		$seen = [];
		foreach ( $results as $index => $result ) {
			if ( 'ready' !== $result['status'] ) {
				continue;
			}
			$key            = strtolower( $result['folder'] . '/' . $result['proposed_filename'] );
			$seen[ $key ][] = $index;
		}
		foreach ( $seen as $indexes ) {
			if ( count( $indexes ) > 1 ) {
				foreach ( $indexes as $index ) {
					$results[ $index ]['same_name_in_request'] = true;
				}
			}
		}
		return $results;
	}

	/**
	 * The attachment if the user may edit it; otherwise not_found (missing,
	 * not readable, or not in the library) or forbidden (readable, not
	 * editable).
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return WP_Post|string
	 */
	private static function editable( int $id ) {
		$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );
		if ( is_wp_error( $attachment ) || 'inherit' !== $attachment->post_status ) {
			return 'not_found';
		}
		return AbilityGuard::can_access_attachment( $attachment, 'edit_post' ) ? $attachment : 'forbidden';
	}
}
