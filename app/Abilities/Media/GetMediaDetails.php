<?php
/**
 * Ability: tsmlt/get-media-details.
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

/**
 * Read-only details for a single media library attachment.
 */
class GetMediaDetails extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Attachment statuses this ability reports. Matches tsmlt/search-media, so
	 * a client cannot reach attachments it would never find by searching.
	 *
	 * @var string[]
	 */
	const VISIBLE_STATUSES = [ 'inherit' ];

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/get-media-details';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get media details', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Return the details of one media library attachment by ID: title, alt text, caption, description, URL, MIME type, file name, dimensions, file size, generated image sizes, the post it is attached to, and upload date. Use tsmlt/search-media to find IDs. Read-only.', 'media-library-tools' );
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
		return MediaPresenter::details_schema();
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
	 * Return the attachment details.
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
			// A refused attachment is reported exactly like a missing one, so
			// the response never confirms that an inaccessible item exists.
			return AbilityGuard::ERROR_FORBIDDEN === $attachment->get_error_code()
				? $this->not_found()
				: $attachment;
		}

		if ( ! in_array( $attachment->post_status, self::VISIBLE_STATUSES, true ) ) {
			return $this->not_found();
		}

		return MediaPresenter::details( $attachment );
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
