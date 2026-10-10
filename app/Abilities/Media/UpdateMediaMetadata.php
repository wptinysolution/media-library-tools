<?php
/**
 * Ability: tsmlt/update-media-metadata.
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
use TinySolutions\mlt\Abilities\MediaPresenter;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;
use WP_Post;

/**
 * Update the title, alt text, caption and/or description of one attachment.
 *
 * Writes only the fields the caller supplies, through the WordPress post and
 * metadata APIs. It deliberately does not go through
 * RenameModule::update_single_media(): that dispatcher switches to renaming or
 * bulk editing depending on which keys are present.
 */
class UpdateMediaMetadata extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Error code: WordPress refused to save a field.
	 *
	 * @var string
	 */
	const ERROR_UPDATE_FAILED = 'tsmlt_update_failed';

	/**
	 * Attachment statuses this ability writes to. Matches the read abilities.
	 *
	 * @var string[]
	 */
	const VISIBLE_STATUSES = [ 'inherit' ];

	/**
	 * Editable fields mapped to their attachment post field, in output order.
	 * `alt_text` is stored in post meta instead (see ALT_META_KEY).
	 *
	 * @var array<string, string|null>
	 */
	const FIELDS = [
		'title'       => 'post_title',
		'alt_text'    => null,
		'caption'     => 'post_excerpt',
		'description' => 'post_content',
	];

	/**
	 * Post meta key WordPress uses for image alt text.
	 *
	 * @var string
	 */
	const ALT_META_KEY = '_wp_attachment_image_alt';

	/**
	 * Maximum accepted length per field, in characters.
	 *
	 * @var array<string, int>
	 */
	const MAX_LENGTHS = [
		'title'       => 255,
		'alt_text'    => 500,
		'caption'     => 2000,
		'description' => 10000,
	];

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/update-media-metadata';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Update media metadata', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Update the title, alt text, caption and/or description of one media library attachment by ID. Only the fields you supply are changed; omitted fields are left as they are, and an empty string clears a field. Does not rename or move the file. Returns which fields changed and the updated media summary.', 'media-library-tools' );
	}

	/**
	 * @return array
	 */
	protected function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'id' ],
			// `id` plus at least one field to update.
			'minProperties'        => 2,
			'properties'           => [
				'id'          => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Attachment ID.',
				],
				'title'       => [
					'type'        => 'string',
					'maxLength'   => self::MAX_LENGTHS['title'],
					'description' => 'New title. Plain text; HTML is removed.',
				],
				'alt_text'    => [
					'type'        => 'string',
					'maxLength'   => self::MAX_LENGTHS['alt_text'],
					'description' => 'New image alt text. Plain text; HTML is removed. An empty string removes the alt text.',
				],
				'caption'     => [
					'type'        => 'string',
					'maxLength'   => self::MAX_LENGTHS['caption'],
					'description' => 'New caption. Plain text; HTML is removed.',
				],
				'description' => [
					'type'        => 'string',
					'maxLength'   => self::MAX_LENGTHS['description'],
					'description' => 'New description. Basic HTML allowed in post content is kept; anything else is removed.',
				],
			],
		];
	}

	/**
	 * @return array
	 */
	protected function get_output_schema(): array {
		$field_list = [
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => [
				'type' => 'string',
				'enum' => array_keys( self::FIELDS ),
			],
		];

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'id', 'updated_fields', 'unchanged_fields', 'media' ],
			'properties'           => [
				'id'               => [
					'type'        => 'integer',
					'description' => 'Attachment ID.',
				],
				'updated_fields'   => array_merge(
					$field_list,
					[ 'description' => 'Requested fields whose stored value changed.' ]
				),
				'unchanged_fields' => array_merge(
					$field_list,
					[ 'description' => 'Requested fields that already held the value after sanitization, so nothing was written.' ]
				),
				'media'            => MediaPresenter::summary_schema(),
			],
		];
	}

	/**
	 * Overwrites existing values, which cannot be undone; repeating the same
	 * request leaves the same state.
	 *
	 * @return array<string, bool>
	 */
	protected function get_annotations(): array {
		return [
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => true,
		];
	}

	/**
	 * Apply the update.
	 *
	 * @param mixed $input Ability input, already validated against the schema.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		$input = is_array( $input ) ? $input : [];

		$attachment = $this->resolve_editable_attachment( $input['id'] ?? null );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}

		$values = $this->sanitize_fields( $input );
		if ( is_wp_error( $values ) ) {
			return $values;
		}

		$before = $this->current_values( $attachment->ID );

		$post_update = [];
		foreach ( $values as $field => $value ) {
			if ( null !== self::FIELDS[ $field ] && $value !== $before[ $field ] ) {
				$post_update[ self::FIELDS[ $field ] ] = $value;
			}
		}

		if ( ! empty( $post_update ) ) {
			$post_update['ID'] = $attachment->ID;
			// wp_update_post() unslashes its input, so slash it to keep backslashes.
			$saved = wp_update_post( wp_slash( $post_update ), true );
			if ( is_wp_error( $saved ) || ! $saved ) {
				return $this->update_failed();
			}
		}

		if ( array_key_exists( 'alt_text', $values ) && $values['alt_text'] !== $before['alt_text'] ) {
			// update_post_meta() unslashes its input too.
			if ( ! update_post_meta( $attachment->ID, self::ALT_META_KEY, wp_slash( $values['alt_text'] ) ) ) {
				return $this->update_failed();
			}
		}

		clean_post_cache( $attachment->ID );
		$after = $this->current_values( $attachment->ID );

		// Decided from the stored values, not the input, so a value WordPress
		// filtered back to what was already there is reported as unchanged.
		$updated   = [];
		$unchanged = [];
		foreach ( array_keys( self::FIELDS ) as $field ) {
			if ( ! array_key_exists( $field, $values ) ) {
				continue;
			}
			if ( $after[ $field ] !== $before[ $field ] ) {
				$updated[] = $field;
			} else {
				$unchanged[] = $field;
			}
		}

		return [
			'id'               => (int) $attachment->ID,
			'updated_fields'   => $updated,
			'unchanged_fields' => $unchanged,
			'media'            => MediaPresenter::summary( get_post( $attachment->ID ) ),
		];
	}

	/**
	 * Resolve the attachment and check that the user may edit it.
	 *
	 * Attachments the user may not read, and those outside the visible
	 * statuses, are reported as not found so their existence is not revealed.
	 * A readable attachment the user may not edit is reported as forbidden.
	 *
	 * @param mixed $id Raw attachment ID.
	 *
	 * @return WP_Post|WP_Error
	 */
	private function resolve_editable_attachment( $id ) {
		$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );

		if ( is_wp_error( $attachment ) ) {
			return AbilityGuard::ERROR_FORBIDDEN === $attachment->get_error_code()
				? $this->not_found()
				: $attachment;
		}

		if ( ! in_array( $attachment->post_status, self::VISIBLE_STATUSES, true ) ) {
			return $this->not_found();
		}

		if ( ! AbilityGuard::can_access_attachment( $attachment, 'edit_post' ) ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_FORBIDDEN,
				__( 'You are not allowed to edit this attachment.', 'media-library-tools' ),
				403
			);
		}

		return $attachment;
	}

	/**
	 * Sanitize the supplied fields. Omitted fields are absent from the result.
	 *
	 * @param array $input Raw input.
	 *
	 * @return array<string, string>|WP_Error
	 */
	private function sanitize_fields( array $input ) {
		$values = [];
		foreach ( array_keys( self::FIELDS ) as $field ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}
			if ( ! is_string( $input[ $field ] ) || mb_strlen( $input[ $field ] ) > self::MAX_LENGTHS[ $field ] ) {
				return AbilityGuard::error(
					AbilityGuard::ERROR_INVALID_INPUT,
					/* translators: %s: field name. */
					sprintf( __( '%s must be a string within the allowed length.', 'media-library-tools' ), $field ),
					400
				);
			}
			$values[ $field ] = 'description' === $field
				? trim( wp_kses_post( $input[ $field ] ) )
				: sanitize_text_field( $input[ $field ] );
		}

		if ( empty( $values ) ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				__( 'Supply at least one of title, alt_text, caption or description.', 'media-library-tools' ),
				400
			);
		}

		return $values;
	}

	/**
	 * Stored values of the editable fields, read fresh from the database.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array<string, string>
	 */
	private function current_values( int $attachment_id ): array {
		$post = get_post( $attachment_id );
		$alt  = get_post_meta( $attachment_id, self::ALT_META_KEY, true );

		return [
			'title'       => (string) $post->post_title,
			'alt_text'    => is_scalar( $alt ) ? (string) $alt : '',
			'caption'     => (string) $post->post_excerpt,
			'description' => (string) $post->post_content,
		];
	}

	/**
	 * The same error the shared guard returns for a missing attachment.
	 *
	 * @return WP_Error
	 */
	private function not_found(): WP_Error {
		return AbilityGuard::error(
			AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND,
			__( 'No attachment exists with that ID.', 'media-library-tools' ),
			404
		);
	}

	/**
	 * Generic save failure. WordPress's own message is not passed on, since it
	 * can describe internals.
	 *
	 * @return WP_Error
	 */
	private function update_failed(): WP_Error {
		return AbilityGuard::error(
			self::ERROR_UPDATE_FAILED,
			__( 'The media could not be updated. Fields processed before the failure may already have been saved; fetch the media to check.', 'media-library-tools' ),
			500
		);
	}
}
