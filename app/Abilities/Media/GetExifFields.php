<?php
/**
 * Ability: tsmlt/get-exif-fields.
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
use TinySolutions\mlt\Modules\ExifData\ExifDataReader;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Read-only camera and exposure EXIF fields for one image attachment.
 *
 * Reads through ExifDataReader::get_editable_exif(): EXIF from the file for
 * JPEG images, plus the fields saved by the EXIF editor in `_tsmlt_exif_meta`,
 * which take precedence and are the only source for other image types.
 *
 * Only an allowlist is returned — make, model, date taken, ISO, aperture and
 * shutter speed — each re-validated. GPS position (or whether one exists),
 * copyright, artist, serial numbers, raw EXIF and file paths are never
 * returned. Registered with `meta.mcp.public` false, so it is offered only on
 * the dedicated Media Library Tools MCP server.
 */
class GetExifFields extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Attachment statuses this ability reports, matching tsmlt/get-media-details.
	 *
	 * @var string[]
	 */
	const VISIBLE_STATUSES = [ 'inherit' ];

	/**
	 * Post meta key where the EXIF editor saves edited fields.
	 *
	 * @var string
	 */
	const STORED_META_KEY = '_tsmlt_exif_meta';

	/**
	 * Maximum length of a returned text field.
	 *
	 * @var int
	 */
	const MAX_TEXT_LENGTH = 255;

	/**
	 * Returned field names, in output order.
	 *
	 * @var string[]
	 */
	const FIELDS = [ 'make', 'model', 'date_time_original', 'iso', 'aperture', 'shutter_speed' ];

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/get-exif-fields';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get EXIF fields', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Return the camera and exposure EXIF fields of one image attachment by ID: camera make and model, date taken, ISO, aperture (f-number) and shutter speed (seconds, e.g. "1/250"). Reads the image file for JPEG, and the values saved in the plugin\'s EXIF editor for any image type. Location, copyright and artist are never returned. Read-only.', 'media-library-tools' );
	}

	/**
	 * Not offered on the MCP Adapter's default gateway.
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
			'required'             => [ 'id' ],
			'properties'           => [
				'id' => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Attachment ID.',
				],
			],
		];
	}

	/**
	 * @return array
	 */
	protected function get_output_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'id', 'supported', 'source', 'fields' ],
			'properties'           => [
				'id'        => [
					'type'        => 'integer',
					'description' => 'Attachment ID.',
				],
				'supported' => [
					'type'        => 'boolean',
					'description' => 'Whether EXIF fields can be read for this attachment. False for non-images, or a JPEG whose file is missing or cannot be read.',
				],
				'source'    => [
					'type'        => 'string',
					'enum'        => [ 'file', 'stored', 'none' ],
					'description' => '"stored" when values saved in the EXIF editor are present (they take precedence over the file), "file" when the values come only from the image file, "none" when no field has a value.',
				],
				'fields'    => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => self::FIELDS,
					'properties'           => [
						'make'               => [
							'type'        => [ 'string', 'null' ],
							'description' => 'Camera make.',
						],
						'model'              => [
							'type'        => [ 'string', 'null' ],
							'description' => 'Camera model.',
						],
						'date_time_original' => [
							'type'        => [ 'string', 'null' ],
							'description' => 'Date taken, in EXIF format "YYYY:MM:DD HH:MM:SS".',
						],
						'iso'                => [
							'type'        => [ 'integer', 'null' ],
							'description' => 'ISO speed.',
						],
						'aperture'           => [
							'type'        => [ 'number', 'null' ],
							'description' => 'Aperture as an f-number, e.g. 1.8 for f/1.8.',
						],
						'shutter_speed'      => [
							'type'        => [ 'string', 'null' ],
							'description' => 'Shutter speed in seconds, e.g. "1/250" or "2".',
						],
					],
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
	 * Return the allowlisted EXIF fields.
	 *
	 * @param mixed $input Ability input, already validated against the schema.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		$id = is_array( $input ) && array_key_exists( 'id', $input ) ? $input['id'] : null;

		// Re-validates the ID and checks existence, type and read_post access.
		$attachment = AbilityGuard::resolve_attachment( $id, 'read_post' );

		if ( is_wp_error( $attachment ) ) {
			// A refused attachment is reported exactly like a missing one.
			return AbilityGuard::ERROR_FORBIDDEN === $attachment->get_error_code()
				? $this->not_found()
				: $attachment;
		}

		if ( ! in_array( $attachment->post_status, self::VISIBLE_STATUSES, true ) ) {
			return $this->not_found();
		}

		$attachment_id = (int) $attachment->ID;
		$result        = [
			'id'        => $attachment_id,
			'supported' => false,
			'source'    => 'none',
			'fields'    => array_fill_keys( self::FIELDS, null ),
		];

		// The reader reports any non-JPEG as supported; only images can have EXIF.
		if ( 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			return $result;
		}

		$editable = ExifDataReader::instance()->get_editable_exif( $attachment_id );
		if ( empty( $editable['supported'] ) ) {
			return $result;
		}

		$result['supported'] = true;
		$result['fields']    = $this->normalize_fields( $editable );

		if ( ! array_filter( $result['fields'], [ $this, 'has_value' ] ) ) {
			return $result;
		}

		$stored           = $this->normalize_fields( get_post_meta( $attachment_id, self::STORED_META_KEY, true ) );
		$result['source'] = array_filter( $stored, [ $this, 'has_value' ] ) ? 'stored' : 'file';

		return $result;
	}

	/**
	 * The allowlisted fields, each validated; anything else is dropped.
	 *
	 * @param mixed $values Field values keyed by name.
	 *
	 * @return array
	 */
	private function normalize_fields( $values ): array {
		$values = is_array( $values ) ? $values : [];

		return [
			'make'               => $this->text( $values['make'] ?? null ),
			'model'              => $this->text( $values['model'] ?? null ),
			'date_time_original' => $this->date_taken( $values['date_time_original'] ?? null ),
			'iso'                => $this->iso( $values['iso'] ?? null ),
			'aperture'           => $this->aperture( $values['aperture'] ?? null ),
			'shutter_speed'      => $this->shutter_speed( $values['shutter_speed'] ?? null ),
		];
	}

	/**
	 * Plain single-line text, or null when empty.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string|null
	 */
	private function text( $value ): ?string {
		if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}

		$text = trim( sanitize_text_field( Fns::prepare_text_for_json( $value ) ) );
		if ( '' === $text ) {
			return null;
		}

		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_TEXT_LENGTH ) : substr( $text, 0, self::MAX_TEXT_LENGTH );
	}

	/**
	 * An EXIF date "YYYY:MM:DD HH:MM:SS", or null when not in that form.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string|null
	 */
	private function date_taken( $value ): ?string {
		$text = $this->text( $value );

		return null !== $text && preg_match( '/^\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}$/', $text ) ? $text : null;
	}

	/**
	 * A positive integer ISO, or null.
	 *
	 * @param mixed $value Value.
	 *
	 * @return int|null
	 */
	private function iso( $value ): ?int {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && ctype_digit( trim( $value ) ) ) ) {
			return null;
		}

		$iso = (int) $value;
		return $iso > 0 ? $iso : null;
	}

	/**
	 * A positive f-number rounded to one decimal, or null. Accepts "f/1.8".
	 *
	 * @param mixed $value Value.
	 *
	 * @return float|null
	 */
	private function aperture( $value ): ?float {
		if ( is_string( $value ) ) {
			$value = preg_replace( '#^f\s*/?\s*#i', '', trim( $value ) );
		}
		if ( ! is_int( $value ) && ! is_float( $value ) && ! ( is_string( $value ) && is_numeric( $value ) ) ) {
			return null;
		}

		$aperture = round( (float) $value, 1 );
		return $aperture > 0 && is_finite( $aperture ) ? $aperture : null;
	}

	/**
	 * A shutter speed in seconds — "1/250", "2" or "0.5" — or null. A trailing
	 * unit ("1/250s", "2 sec") is removed.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string|null
	 */
	private function shutter_speed( $value ): ?string {
		$text = $this->text( $value );
		if ( null === $text ) {
			return null;
		}

		$text = trim( (string) preg_replace( '#\s*(?:s|secs?|seconds?)\.?$#i', '', $text ) );

		return preg_match( '#^\d+(?:\.\d+)?(?:/\d+(?:\.\d+)?)?$#', $text ) ? $text : null;
	}

	/**
	 * Whether a normalized field has a value.
	 *
	 * @param mixed $value Value.
	 *
	 * @return bool
	 */
	private function has_value( $value ): bool {
		return null !== $value;
	}

	/**
	 * The same error AbilityGuard returns for a missing attachment.
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
}
