<?php
/**
 * Rename module — handles single rename, bulk-edit, and single-field update operations.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Rename;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * RenameModule
 */
class RenameModule {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Dispatch a single-media update request to the appropriate handler.
	 *
	 * @param array $parameters Sanitised request parameters.
	 *
	 * @return array
	 */
	public function update_single_media( array $parameters ): array {
		$result = [
			'updated' => false,
			'message' => esc_html__( 'Update failed. Please try to fix', 'media-library-tools' ),
		];

		if ( empty( $parameters['ID'] ) ) {
			return $result;
		}

		// Handle Rename.
		if ( isset( $parameters['newname'] ) ) {
			return $this->handle_rename( $parameters );
		}

		// Handle Bulk Edit by post title.
		if ( ! empty( $parameters['bulkEditPostTitle'] ) ) {
			return $this->handle_bulk_edit( $parameters );
		}

		// Handle Single Field Update.
		return $this->handle_single_updates( $parameters, $result );
	}

	/**
	 * Rename an attachment file.
	 *
	 * @param array $parameters Request parameters.
	 *
	 * @return array
	 */
	private function handle_rename( array $parameters ): array {
		$result = [
			'updated' => false,
			'message' => esc_html__( 'Rename failed. Please try again.', 'media-library-tools' ),
		];

		$attachment = get_post( (int) $parameters['ID'] );
		if ( ! $attachment || empty( $parameters['newname'] ) ) {
			return $result;
		}

		$new_name  = sanitize_text_field( $parameters['newname'] );
		$rename_to = $new_name;
		$post_id   = $attachment->post_parent ?: Fns::set_thumbnail_parent_id( $attachment->ID );

		/**
		 * Filter rename target filename.
		 *
		 * @param string   $rename_to  Final filename to rename to.
		 * @param string   $new_name   Rename action or raw filename.
		 * @param int      $post_id    Parent post ID (if exists).
		 * @param \WP_Post $attachment Attachment object.
		 */
		$rename_to = apply_filters( 'tsmlt_attachment_rename_to', $rename_to, $new_name, $post_id, $attachment );

		if ( ! empty( $rename_to ) && Fns::wp_rename_attachment( $attachment->ID, $rename_to ) ) {
			$result['updated'] = true;
			$result['message'] = esc_html__( 'Renamed.', 'media-library-tools' );
		} else {
			$result['message'] = esc_html__(
				'Rename failed. The file may not exist or file permissions may be incorrect.',
				'media-library-tools'
			);
		}

		return $result;
	}

	/**
	 * Bulk-edit attachment fields using the parent post title.
	 *
	 * @param array $parameters Request parameters.
	 *
	 * @return array
	 */
	private function handle_bulk_edit( array $parameters ): array {
		$result = [
			'updated' => false,
			'message' => esc_html__( 'Update failed.', 'media-library-tools' ),
		];

		$attachment = get_post( $parameters['ID'] );
		$new_text   = '';
		if ( $attachment && $attachment->post_parent ) {
			$new_text = get_the_title( $attachment->post_parent );
		}

		if ( empty( $new_text ) ) {
			return $result;
		}

		$submit = [];
		if ( in_array( 'post_title', $parameters['bulkEditPostTitle'], true ) ) {
			$submit['post_title'] = $new_text;
		}
		if ( in_array( 'alt_text', $parameters['bulkEditPostTitle'], true ) ) {
			$result['updated'] = update_post_meta( $parameters['ID'], '_wp_attachment_image_alt', trim( $new_text ) );
			$result['message'] = esc_html__( 'Saved.', 'media-library-tools' );
		}
		if ( in_array( 'caption', $parameters['bulkEditPostTitle'], true ) ) {
			$submit['post_excerpt'] = $new_text;
		}
		if ( in_array( 'post_description', $parameters['bulkEditPostTitle'], true ) ) {
			$submit['post_content'] = $new_text;
		}

		if ( ! empty( $submit ) ) {
			$submit['ID']      = $parameters['ID'];
			$result['updated'] = wp_update_post( $submit );
			$result['message'] = $result['updated']
				? esc_html__( 'Saved.', 'media-library-tools' )
				: esc_html__( 'Update failed. Please try to fix', 'media-library-tools' );
		}

		return $result;
	}

	/**
	 * Update a single post field (title, caption, description, alt text).
	 *
	 * @param array $parameters Request parameters.
	 * @param array $result     Default result array.
	 *
	 * @return array
	 */
	private function handle_single_updates( array $parameters, array $result ): array {
		$post_fields = [
			'post_title'   => esc_html__( 'The Title has been saved.', 'media-library-tools' ),
			'post_excerpt' => esc_html__( 'The Caption has been saved.', 'media-library-tools' ),
			'post_content' => esc_html__( 'Content has been saved.', 'media-library-tools' ),
			'alt_text'     => esc_html__( 'Saved.', 'media-library-tools' ),
		];

		if ( isset( $parameters['title'] ) ) {
			$parameters['post_title'] = sanitize_text_field( $parameters['title'] );
			unset( $parameters['title'] );
		}
		if ( isset( $parameters['caption'] ) ) {
			$parameters['post_excerpt'] = sanitize_text_field( $parameters['caption'] );
			unset( $parameters['caption'] );
		}
		if ( isset( $parameters['description'] ) ) {
			$parameters['post_content'] = sanitize_text_field( $parameters['description'] );
			unset( $parameters['description'] );
		}

		$submit = [];
		foreach ( $post_fields as $field => $message ) {
			if ( isset( $parameters[ $field ] ) ) {
				if ( 'alt_text' === $field ) {
					$result['updated'] = update_post_meta( $parameters['ID'], '_wp_attachment_image_alt', trim( $parameters[ $field ] ) );
				} else {
					$submit[ $field ] = trim( $parameters[ $field ] );
				}
				$result['message'] = $message;
			}
		}

		if ( ! empty( $submit ) ) {
			$submit['ID']      = $parameters['ID'];
			$result['updated'] = wp_update_post( $submit );
			$result['message'] = $result['updated']
				? $result['message']
				: esc_html__( 'Update failed. Please try to fix.', 'media-library-tools' );
		}

		return $result;
	}
}
