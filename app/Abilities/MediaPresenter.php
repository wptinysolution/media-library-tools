<?php
/**
 * Allowlisted attachment serializer for ability output.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use WP_Post;

/**
 * MediaPresenter — turns attachments into the fields abilities may disclose.
 *
 * Deliberately separate from Api::get_media(), whose response is shaped for the
 * admin UI and carries absolute file paths, the raw attachment metadata array
 * and every custom meta key. Nothing here is copied from that response: each
 * field is named explicitly, so a new field can only appear by being added.
 */
class MediaPresenter {

	/**
	 * Summary fields for one attachment.
	 *
	 * @param WP_Post $attachment Attachment post.
	 *
	 * @return array{id: int, title: string, alt_text: string, caption: string, mime_type: string, url: string, date: string}
	 */
	public static function summary( WP_Post $attachment ): array {
		$url  = wp_get_attachment_url( $attachment->ID );
		$date = get_post_time( DATE_ATOM, true, $attachment );

		return [
			'id'        => (int) $attachment->ID,
			'title'     => Fns::prepare_text_for_json( $attachment->post_title ),
			'alt_text'  => Fns::prepare_text_for_json( get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) ),
			'caption'   => Fns::prepare_text_for_json( $attachment->post_excerpt ),
			'mime_type' => (string) $attachment->post_mime_type,
			'url'       => $url ? (string) $url : '',
			'date'      => $date ? (string) $date : '',
		];
	}

	/**
	 * Full details for one attachment.
	 *
	 * Reads only the post, a few known meta keys and the attachment metadata
	 * array, from which individual values are picked; the array itself is never
	 * returned, so EXIF data (`image_meta`, including any GPS position) and file
	 * paths cannot leak. No filesystem calls are made: file size comes from the
	 * metadata WordPress stored at upload time.
	 *
	 * Text fields are always strings (empty when unset). Values that may be
	 * genuinely unavailable — URL, filename, dimensions, size, parent, date —
	 * are null when missing rather than guessed.
	 *
	 * @param WP_Post $attachment Attachment post.
	 *
	 * @return array
	 */
	public static function details( WP_Post $attachment ): array {
		$text     = Fns::wp_get_attachment( $attachment->ID );
		$metadata = wp_get_attachment_metadata( $attachment->ID );
		$metadata = is_array( $metadata ) ? $metadata : [];
		$url      = wp_get_attachment_url( $attachment->ID );
		$file     = get_post_meta( $attachment->ID, '_wp_attached_file', true );
		$date     = get_post_time( DATE_ATOM, true, $attachment );

		return [
			'id'          => (int) $attachment->ID,
			'title'       => Fns::prepare_text_for_json( $text['title'] ),
			'alt_text'    => Fns::prepare_text_for_json( $text['alt'] ),
			'caption'     => Fns::prepare_text_for_json( $text['caption'] ),
			'description' => Fns::prepare_text_for_json( $text['description'] ),
			'url'         => $url ? (string) $url : null,
			'mime_type'   => (string) $attachment->post_mime_type,
			// Basename of the stored relative path only — never the directory.
			'filename'    => is_string( $file ) && '' !== $file ? wp_basename( $file ) : null,
			'width'       => self::positive_int_or_null( $metadata['width'] ?? null ),
			'height'      => self::positive_int_or_null( $metadata['height'] ?? null ),
			'filesize'    => self::positive_int_or_null( $metadata['filesize'] ?? null ),
			'sizes'       => self::image_sizes( $metadata['sizes'] ?? null ),
			'parent'      => self::parent( (int) $attachment->post_parent ),
			'date'        => $date ? (string) $date : null,
		];
	}

	/**
	 * Generated image sizes as name and dimensions only.
	 *
	 * The metadata entries also hold a filename; it is deliberately dropped.
	 * Malformed entries are skipped rather than reported with guessed values.
	 *
	 * @param mixed $sizes The `sizes` value from attachment metadata.
	 *
	 * @return array<int, array{name: string, width: int, height: int}>
	 */
	private static function image_sizes( $sizes ): array {
		if ( ! is_array( $sizes ) ) {
			return [];
		}

		$result = [];
		foreach ( $sizes as $name => $size ) {
			$width  = is_array( $size ) ? self::positive_int_or_null( $size['width'] ?? null ) : null;
			$height = is_array( $size ) ? self::positive_int_or_null( $size['height'] ?? null ) : null;
			if ( ! is_string( $name ) || '' === $name || null === $width || null === $height ) {
				continue;
			}
			$result[] = [
				'name'   => sanitize_text_field( $name ),
				'width'  => $width,
				'height' => $height,
			];
		}

		return $result;
	}

	/**
	 * The attachment's parent post, when it is a real post the user may read.
	 *
	 * @param int $parent_id Stored post_parent.
	 *
	 * @return array{id: int, title: string}|null
	 */
	private static function parent( int $parent_id ): ?array {
		// Same validity rule as the media table, so plumbing post types and
		// deleted or trashed parents are never reported.
		if ( ! Fns::is_valid_attachment_parent( $parent_id ) || ! current_user_can( 'read_post', $parent_id ) ) {
			return null;
		}

		return [
			'id'    => $parent_id,
			'title' => Fns::prepare_text_for_json( get_the_title( $parent_id ) ),
		];
	}

	/**
	 * Cast a stored numeric value to a positive integer, or null.
	 *
	 * @param mixed $value Stored value.
	 *
	 * @return int|null
	 */
	private static function positive_int_or_null( $value ): ?int {
		if ( ! is_numeric( $value ) || (int) $value <= 0 ) {
			return null;
		}
		return (int) $value;
	}

	/**
	 * JSON Schema matching details().
	 *
	 * @return array
	 */
	public static function details_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'id', 'title', 'alt_text', 'caption', 'description', 'url', 'mime_type', 'filename', 'width', 'height', 'filesize', 'sizes', 'parent', 'date' ],
			'properties'           => [
				'id'          => [
					'type'        => 'integer',
					'description' => 'Attachment ID.',
				],
				'title'       => [
					'type'        => 'string',
					'description' => 'Attachment title.',
				],
				'alt_text'    => [
					'type'        => 'string',
					'description' => 'Image alt text. Empty when none is set.',
				],
				'caption'     => [
					'type'        => 'string',
					'description' => 'Attachment caption.',
				],
				'description' => [
					'type'        => 'string',
					'description' => 'Attachment description. May contain HTML.',
				],
				'url'         => [
					'type'        => [ 'string', 'null' ],
					'description' => 'Public URL of the file, or null when unavailable.',
				],
				'mime_type'   => [
					'type'        => 'string',
					'description' => 'MIME type, e.g. image/jpeg.',
				],
				'filename'    => [
					'type'        => [ 'string', 'null' ],
					'description' => 'File name without any directory, or null when unknown.',
				],
				'width'       => [
					'type'        => [ 'integer', 'null' ],
					'description' => 'Width in pixels, or null when unknown.',
				],
				'height'      => [
					'type'        => [ 'integer', 'null' ],
					'description' => 'Height in pixels, or null when unknown.',
				],
				'filesize'    => [
					'type'        => [ 'integer', 'null' ],
					'description' => 'File size in bytes as recorded at upload, or null when not recorded.',
				],
				'sizes'       => [
					'type'        => 'array',
					'description' => 'Generated image sizes. Empty for non-images.',
					'items'       => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'name', 'width', 'height' ],
						'properties'           => [
							'name'   => [ 'type' => 'string' ],
							'width'  => [ 'type' => 'integer' ],
							'height' => [ 'type' => 'integer' ],
						],
					],
				],
				'parent'      => [
					'type'                 => [ 'object', 'null' ],
					'description'          => 'Post the attachment is attached to, or null.',
					'additionalProperties' => false,
					'required'             => [ 'id', 'title' ],
					'properties'           => [
						'id'    => [ 'type' => 'integer' ],
						'title' => [ 'type' => 'string' ],
					],
				],
				'date'        => [
					'type'        => [ 'string', 'null' ],
					'description' => 'Upload date in ISO 8601 (UTC), or null when unknown.',
				],
			],
		];
	}

	/**
	 * JSON Schema matching summary().
	 *
	 * @return array
	 */
	public static function summary_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'id', 'title', 'alt_text', 'caption', 'mime_type', 'url', 'date' ],
			'properties'           => [
				'id'        => [
					'type'        => 'integer',
					'description' => 'Attachment ID.',
				],
				'title'     => [
					'type'        => 'string',
					'description' => 'Attachment title.',
				],
				'alt_text'  => [
					'type'        => 'string',
					'description' => 'Image alt text. Empty when none is set.',
				],
				'caption'   => [
					'type'        => 'string',
					'description' => 'Attachment caption.',
				],
				'mime_type' => [
					'type'        => 'string',
					'description' => 'MIME type, e.g. image/jpeg.',
				],
				'url'       => [
					'type'        => 'string',
					'description' => 'Public URL of the original file.',
				],
				'date'      => [
					'type'        => 'string',
					'description' => 'Upload date in ISO 8601 (UTC).',
				],
			],
		];
	}
}
