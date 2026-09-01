<?php
/**
 * Per-attachment image format conversion.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Conversion;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Modules\Compress\CompressionAccess;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Converts one attachment into WebP and/or AVIF companions.
 *
 * The original attachment is never modified or replaced: generated files sit
 * beside it (`image.jpg` → `image.webp`) and are recorded in attachment meta,
 * so WordPress keeps a single library entry per image.
 *
 * Owns the safety flow — validate source, convert to a temporary file, validate
 * the output, then move it into place — while delegating encoding to the
 * engines, so a new engine never requires changes here.
 */
class AttachmentConverter {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Meta key marking an attachment as currently being converted.
	 *
	 * Stops automatic upload conversion re-entering while a manual run is
	 * mid-flight on the same attachment.
	 */
	const LOCK_META_KEY = '_tsmlt_conversion_lock';

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Validate an attachment, including the current user's rights over it.
	 *
	 * Call from request context only. Background workers must use
	 * `validate_attachment_file()`, since WP-Cron ticks have no current user.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return true|WP_Error
	 */
	public function validate_attachment( int $attachment_id ) {
		if ( ! CompressionAccess::instance()->can_edit_attachment( $attachment_id ) ) {
			return new WP_Error(
				'tsmlt_conversion_forbidden',
				esc_html__( 'You do not have permission to edit this image.', 'media-library-tools' )
			);
		}

		return $this->validate_attachment_file( $attachment_id );
	}

	/**
	 * Validate everything about an attachment except the current user's rights.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return true|WP_Error
	 */
	public function validate_attachment_file( int $attachment_id ) {
		if ( $attachment_id <= 0 ) {
			return new WP_Error(
				'tsmlt_conversion_not_found',
				esc_html__( 'Image not found.', 'media-library-tools' )
			);
		}

		$post = get_post( $attachment_id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error(
				'tsmlt_conversion_not_found',
				esc_html__( 'Image not found.', 'media-library-tools' )
			);
		}

		$mime_type = (string) get_post_mime_type( $attachment_id );

		if ( ! ConversionCapabilities::instance()->is_supported_source( $mime_type ) ) {
			return new WP_Error(
				'tsmlt_conversion_unsupported_source',
				esc_html__( 'This file type cannot be converted. Supported types are JPEG, PNG and WebP.', 'media-library-tools' )
			);
		}

		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			return new WP_Error(
				'tsmlt_conversion_missing_source',
				esc_html__( 'The image file is missing on the server.', 'media-library-tools' )
			);
		}

		return true;
	}

	/**
	 * Convert one attachment into every requested format.
	 *
	 * A format that fails does not fail the attachment: each is recorded with
	 * its own status, and the run is `completed` when at least one format was
	 * produced, `failed` only when none were.
	 *
	 * @param int   $attachment_id Attachment post ID.
	 * @param array $run_settings  Settings from `ConversionSettings::resolve_run_settings()`.
	 *
	 * @return array|WP_Error
	 */
	public function convert( int $attachment_id, array $run_settings ) {
		$valid = $this->validate_attachment_file( $attachment_id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$formats = isset( $run_settings['formats'] ) ? (array) $run_settings['formats'] : [];

		if ( empty( $formats ) ) {
			return new WP_Error(
				'tsmlt_conversion_no_formats',
				esc_html__( 'Select at least one output format.', 'media-library-tools' )
			);
		}

		if ( $this->is_locked( $attachment_id ) ) {
			return new WP_Error(
				'tsmlt_conversion_locked',
				esc_html__( 'This image is already being converted.', 'media-library-tools' )
			);
		}

		$this->lock( $attachment_id );

		try {
			return $this->convert_unlocked( $attachment_id, $formats, $run_settings );
		} finally {
			$this->unlock( $attachment_id );
		}
	}

	/**
	 * Conversion body, executed with the attachment lock held.
	 *
	 * @param int      $attachment_id Attachment post ID.
	 * @param string[] $formats       Target format keys.
	 * @param array    $run_settings  Effective run settings.
	 *
	 * @return array|WP_Error
	 */
	private function convert_unlocked( int $attachment_id, array $formats, array $run_settings ) {
		$metadata    = ConversionMetadata::instance();
		$source_file = get_attached_file( $attachment_id );
		$source_mime = (string) get_post_mime_type( $attachment_id );

		clearstatcache( true, $source_file );

		$existing     = $metadata->get( $attachment_id );
		$format_data  = isset( $existing['formats'] ) && is_array( $existing['formats'] ) ? $existing['formats'] : [];
		$succeeded    = 0;
		$failed       = 0;
		$skipped      = 0;
		$last_error   = '';
		$total_output = 0;

		foreach ( $formats as $format ) {
			$quality = (int) ( $run_settings['quality'][ $format ] ?? 82 );
			$result  = $this->convert_one_format( $attachment_id, $source_file, $source_mime, $format, $quality, $run_settings );

			if ( is_wp_error( $result ) ) {
				++$failed;
				$last_error = $result->get_error_message();

				$format_data[ $format ] = [
					'status' => 'failed',
					'reason' => $result->get_error_code(),
				];
				continue;
			}

			if ( 'skipped' === $result['status'] ) {
				++$skipped;
				$format_data[ $format ] = $result;
				continue;
			}

			++$succeeded;
			// Track the smallest output, not the sum. A browser downloads one
			// format, so the meaningful saving is the source against the best
			// candidate — summing WebP and AVIF would report a bogus negative.
			$size = (int) $result['size'];
			if ( 0 === $total_output || $size < $total_output ) {
				$total_output = $size;
			}
			$format_data[ $format ] = $result;
		}

		$status = $succeeded > 0
			? ( $failed > 0 ? 'partial' : 'completed' )
			: ( $skipped > 0 && 0 === $failed ? 'skipped' : 'failed' );

		$data = [
			'version'    => ConversionMetadata::SCHEMA_VERSION,
			'status'     => $status,
			'source'     => [
				'file'      => (string) _wp_relative_upload_path( $source_file ),
				'mime_type' => $source_mime,
				'size'      => (int) filesize( $source_file ),
				'modified'  => (int) filemtime( $source_file ),
			],
			'formats'    => $format_data,
			'last_error' => $last_error,
		];

		$metadata->save( $attachment_id, $data );

		if ( 'failed' === $status ) {
			return new WP_Error(
				'tsmlt_conversion_failed',
				'' !== $last_error ? $last_error : esc_html__( 'The image could not be converted.', 'media-library-tools' )
			);
		}

		return [
			'attachment_id' => $attachment_id,
			'status'        => $status,
			'succeeded'     => $succeeded,
			'failed'        => $failed,
			'skipped'       => $skipped,
			'source_size'   => (int) filesize( $source_file ),
			'output_size'   => $total_output,
			'formats'       => array_keys( $format_data ),
		];
	}

	/**
	 * Produce one output format for an attachment, including its sizes.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $source_file   Absolute path to the full-size source.
	 * @param string $source_mime   Source MIME type.
	 * @param string $format        Target format key.
	 * @param int    $quality       Quality value, 1–100.
	 * @param array  $run_settings  Effective run settings.
	 *
	 * @return array|WP_Error
	 */
	private function convert_one_format( int $attachment_id, string $source_file, string $source_mime, string $format, int $quality, array $run_settings ) {
		$capabilities = ConversionCapabilities::instance();
		$engine       = $capabilities->get_engine_for( $source_mime, $format );

		if ( null === $engine ) {
			return new WP_Error(
				'tsmlt_conversion_engine_unavailable',
				sprintf(
					/* translators: %s: image format name, e.g. WebP */
					esc_html__( 'This server cannot produce %s images.', 'media-library-tools' ),
					strtoupper( $format )
				)
			);
		}

		// A JPEG source converted to WebP is useful; a WebP source converted to
		// WebP is not, and would overwrite the original with itself.
		if ( $this->source_matches_format( $source_mime, $format ) ) {
			return [
				'status' => 'skipped',
				'reason' => 'same_format',
			];
		}

		$destination = $this->build_output_path( $source_file, $format );
		$converted   = $this->convert_file( $engine, $source_file, $destination, $format, $quality );

		if ( is_wp_error( $converted ) ) {
			return $converted;
		}

		$entry = [
			'status'       => 'completed',
			'file'         => (string) _wp_relative_upload_path( $destination ),
			'size'         => (int) filesize( $destination ),
			'mime_type'    => 'image/' . $format,
			'quality'      => $quality,
			'generated_at' => current_time( 'mysql', true ),
			'sizes'        => [],
		];

		// Generated sizes (Pro). A failure on one thumbnail must not fail the
		// format — missing and deleted intermediates are normal on real sites.
		if ( ! empty( $run_settings['generated_sizes'] ) ) {
			$entry['sizes'] = $this->convert_generated_sizes( $attachment_id, $source_file, $format, $quality );
		}

		return $entry;
	}

	/**
	 * Convert every WordPress-generated size for one output format.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $source_file   Absolute path to the full-size source.
	 * @param string $format        Target format key.
	 * @param int    $quality       Quality value.
	 *
	 * @return array<string, array> Keyed by size name.
	 */
	private function convert_generated_sizes( int $attachment_id, string $source_file, string $format, int $quality ): array {
		$meta_data = wp_get_attachment_metadata( $attachment_id );

		if ( ! is_array( $meta_data ) || empty( $meta_data['sizes'] ) || ! is_array( $meta_data['sizes'] ) ) {
			return [];
		}

		$capabilities = ConversionCapabilities::instance();
		$base_dir     = trailingslashit( dirname( $source_file ) );
		$results      = [];
		$seen         = [];

		foreach ( $meta_data['sizes'] as $size_name => $size ) {
			if ( empty( $size['file'] ) ) {
				continue;
			}

			$filename = basename( (string) $size['file'] );

			// The full-size file is handled separately; several registered sizes
			// can also resolve to one file, which must only be converted once.
			if ( basename( $source_file ) === $filename || isset( $seen[ $filename ] ) ) {
				continue;
			}
			$seen[ $filename ] = true;

			$size_key  = sanitize_key( $size_name );
			$size_path = $base_dir . $filename;

			if ( ! file_exists( $size_path ) ) {
				$results[ $size_key ] = [
					'status' => 'skipped',
					'reason' => 'missing_file',
				];
				continue;
			}

			$size_mime = wp_check_filetype( $size_path );
			$size_mime = ! empty( $size_mime['type'] ) ? (string) $size_mime['type'] : '';
			$engine    = '' !== $size_mime ? $capabilities->get_engine_for( $size_mime, $format ) : null;

			if ( null === $engine ) {
				$results[ $size_key ] = [
					'status' => 'skipped',
					'reason' => 'unsupported_source',
				];
				continue;
			}

			$destination = $this->build_output_path( $size_path, $format );
			$converted   = $this->convert_file( $engine, $size_path, $destination, $format, $quality );

			if ( is_wp_error( $converted ) ) {
				$results[ $size_key ] = [
					'status' => 'failed',
					'reason' => $converted->get_error_code(),
				];
				continue;
			}

			$results[ $size_key ] = [
				'status' => 'completed',
				'file'   => (string) _wp_relative_upload_path( $destination ),
				'size'   => (int) filesize( $destination ),
			];
		}

		return $results;
	}

	/**
	 * Run the safety flow for one physical file.
	 *
	 * Output goes to a temporary file that is validated — real image, expected
	 * MIME, same dimensions as the source — before being moved into place, so a
	 * truncated or mistyped result never lands in the uploads directory.
	 *
	 * @param object $engine      Converter engine.
	 * @param string $source      Absolute source path.
	 * @param string $destination Absolute destination path.
	 * @param string $format      Target format key.
	 * @param int    $quality     Quality value.
	 *
	 * @return true|WP_Error
	 */
	private function convert_file( $engine, string $source, string $destination, string $format, int $quality ) {
		if ( ! file_exists( $source ) || ! is_readable( $source ) ) {
			return new WP_Error(
				'tsmlt_conversion_missing_source',
				esc_html__( 'The image file is missing or unreadable on the server.', 'media-library-tools' )
			);
		}

		$directory = dirname( $destination );

		if ( ! wp_is_writable( $directory ) ) {
			return new WP_Error(
				'tsmlt_conversion_not_writable',
				esc_html__( 'The upload directory is not writable.', 'media-library-tools' )
			);
		}

		$source_size = @getimagesize( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid images handled below.

		if ( false === $source_size ) {
			return new WP_Error(
				'tsmlt_conversion_invalid_source',
				esc_html__( 'The source file is not a valid image.', 'media-library-tools' )
			);
		}

		// Same directory as the destination, so the final move is an atomic
		// same-filesystem rename rather than a cross-device copy.
		$temp_file = wp_tempnam( basename( $destination ), $directory );

		if ( ! $temp_file ) {
			return new WP_Error(
				'tsmlt_conversion_temp_failed',
				esc_html__( 'A temporary file could not be created.', 'media-library-tools' )
			);
		}

		$converted = $engine->convert( $source, $temp_file, $format, $quality );

		if ( is_wp_error( $converted ) ) {
			$this->delete_temp_file( $temp_file );

			return $converted;
		}

		$validated = $this->validate_output( $temp_file, $format, (int) $source_size[0], (int) $source_size[1] );

		if ( is_wp_error( $validated ) ) {
			$this->delete_temp_file( $temp_file );

			return $validated;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions -- Atomic same-directory move; WP_Filesystem has no atomic equivalent.
		if ( ! @rename( $temp_file, $destination ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure reported as WP_Error.
			$this->delete_temp_file( $temp_file );

			return new WP_Error(
				'tsmlt_conversion_move_failed',
				esc_html__( 'The converted image could not be saved.', 'media-library-tools' )
			);
		}

		@chmod( $destination, defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- Best-effort permissions.

		return true;
	}

	/**
	 * Confirm a converted file is a valid image of the expected format and size.
	 *
	 * @param string $file            Absolute path to the file to validate.
	 * @param string $format          Expected format key.
	 * @param int    $expected_width  Source width in pixels.
	 * @param int    $expected_height Source height in pixels.
	 *
	 * @return true|WP_Error
	 */
	private function validate_output( string $file, string $format, int $expected_width, int $expected_height ) {
		if ( ! file_exists( $file ) ) {
			return new WP_Error(
				'tsmlt_conversion_validation_failed',
				esc_html__( 'The converted file was not created.', 'media-library-tools' )
			);
		}

		clearstatcache( true, $file );

		if ( (int) filesize( $file ) <= 0 ) {
			return new WP_Error(
				'tsmlt_conversion_validation_failed',
				esc_html__( 'The converted file is empty.', 'media-library-tools' )
			);
		}

		$size = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid images handled below.

		// Some PHP builds cannot introspect AVIF even though they can write it.
		// A readable header is preferred, but its absence is not proof of a bad
		// file — the size and extension checks above still apply.
		if ( false === $size ) {
			return 'avif' === $format
				? true
				: new WP_Error(
					'tsmlt_conversion_validation_failed',
					esc_html__( 'The converted file is not a valid image.', 'media-library-tools' )
				);
		}

		$actual = strtolower( (string) ( $size['mime'] ?? '' ) );

		if ( 'image/' . $format !== $actual ) {
			return new WP_Error(
				'tsmlt_conversion_validation_failed',
				esc_html__( 'The converted file is not in the expected format.', 'media-library-tools' )
			);
		}

		if ( (int) $size[0] !== $expected_width || (int) $size[1] !== $expected_height ) {
			return new WP_Error(
				'tsmlt_conversion_validation_failed',
				esc_html__( 'The converted image does not match the original dimensions.', 'media-library-tools' )
			);
		}

		return true;
	}

	/**
	 * Absolute output path for a source file in the target format.
	 *
	 * Keeps the source basename and swaps the extension, so `image.jpg` becomes
	 * `image.webp` beside it — matching what web servers and CDNs expect when
	 * serving modern formats by content negotiation.
	 *
	 * @param string $source_file Absolute source path.
	 * @param string $format      Target format key.
	 *
	 * @return string
	 */
	public function build_output_path( string $source_file, string $format ): string {
		$directory = trailingslashit( dirname( $source_file ) );
		$basename  = pathinfo( $source_file, PATHINFO_FILENAME );

		return $directory . $basename . '.' . $format;
	}

	/**
	 * Whether converting this source to this format would be a no-op.
	 *
	 * @param string $source_mime Source MIME type.
	 * @param string $format      Target format key.
	 *
	 * @return bool
	 */
	private function source_matches_format( string $source_mime, string $format ): bool {
		return strtolower( $source_mime ) === 'image/' . $format;
	}

	/**
	 * Delete a temporary file, tolerating an already-missing path.
	 *
	 * @param string $temp_file Absolute temp file path.
	 *
	 * @return void
	 */
	private function delete_temp_file( string $temp_file ): void {
		if ( '' !== $temp_file && file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}
	}

	/**
	 * Whether an attachment is currently being converted.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return bool
	 */
	public function is_locked( int $attachment_id ): bool {
		$lock = get_post_meta( $attachment_id, self::LOCK_META_KEY, true );

		if ( empty( $lock ) ) {
			return false;
		}

		// A stale lock from a fatal error must not block the image forever.
		return ( time() - (int) $lock ) < 10 * MINUTE_IN_SECONDS;
	}

	/**
	 * Mark an attachment as being converted.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return void
	 */
	private function lock( int $attachment_id ): void {
		update_post_meta( $attachment_id, self::LOCK_META_KEY, time() );
	}

	/**
	 * Clear the processing marker.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return void
	 */
	private function unlock( int $attachment_id ): void {
		delete_post_meta( $attachment_id, self::LOCK_META_KEY );
	}
}
