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

use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\CompressionAccess;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;
use TinySolutions\mlt\Modules\Regenerate\RegenerateLock;
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

		// Only media in the library: trashed (or otherwise unpublished)
		// attachments are not converted.
		if ( ! $post || 'attachment' !== $post->post_type || 'inherit' !== $post->post_status ) {
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
		return $this->run_locked( $attachment_id, $run_settings, false );
	}

	/**
	 * Make an attachment's converted copies again, all or nothing: every new
	 * copy is made and checked before any old one is replaced, and if one
	 * fails the old copies and records stay as they were. Old copies that are
	 * no longer made are deleted only when they are this attachment's own.
	 *
	 * @param int   $attachment_id Attachment post ID.
	 * @param array $run_settings  Settings from `ConversionSettings::resolve_run_settings()`.
	 *
	 * @return array|WP_Error
	 */
	public function regenerate_copies( int $attachment_id, array $run_settings ) {
		return $this->run_locked( $attachment_id, $run_settings, true );
	}

	/**
	 * Checks, the conversion lock and the conversion itself.
	 *
	 * @param int   $attachment_id Attachment post ID.
	 * @param array $run_settings  Effective run settings.
	 * @param bool  $regenerate    All-or-nothing replacement of existing copies.
	 *
	 * @return array|WP_Error
	 */
	private function run_locked( int $attachment_id, array $run_settings, bool $regenerate ) {
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
			// Checked with the conversion lock held, so an operation starting
			// now sees it and stays out. Nothing has been read or written yet.
			if ( $this->is_busy_elsewhere( $attachment_id ) ) {
				return new WP_Error(
					'tsmlt_conversion_operation_locked',
					esc_html__( 'This image is being compressed, having its thumbnails regenerated or its EXIF data removed. Try again when that has finished.', 'media-library-tools' )
				);
			}

			return $this->convert_unlocked( $attachment_id, $formats, $run_settings, $regenerate );
		} finally {
			$this->unlock( $attachment_id );
		}
	}

	/**
	 * Whether another operation that rewrites the image file is running on
	 * it: compression (its lock), a thumbnail regeneration (RegenerateLock)
	 * or an EXIF removal (its ScanStartLock row). Each lock's own expiry
	 * applies; no lock is changed here.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return bool
	 */
	public function is_busy_elsewhere( int $attachment_id ): bool {
		return AttachmentProcessor::instance()->is_locked( $attachment_id )
			|| RegenerateLock::is_held( $attachment_id )
			|| ScanStartLock::instance()->is_held( ExifStripper::LOCK_PREFIX . $attachment_id );
	}

	/**
	 * Conversion body, executed with the attachment lock held.
	 *
	 * Phase one makes every output into a checked temporary file next to its
	 * target; phase two moves each into place (only over this attachment's
	 * own copy, or where nothing exists). Temporary files are always removed.
	 *
	 * @param int      $attachment_id Attachment post ID.
	 * @param string[] $formats       Target format keys.
	 * @param array    $run_settings  Effective run settings.
	 * @param bool     $regenerate    All-or-nothing replacement of existing copies.
	 *
	 * @return array|WP_Error
	 */
	private function convert_unlocked( int $attachment_id, array $formats, array $run_settings, bool $regenerate ) {
		$metadata    = ConversionMetadata::instance();
		$source_file = get_attached_file( $attachment_id );
		$source_mime = (string) get_post_mime_type( $attachment_id );

		clearstatcache( true, $source_file );

		$existing    = $metadata->get( $attachment_id );
		$old_formats = isset( $existing['formats'] ) && is_array( $existing['formats'] ) ? $existing['formats'] : [];
		$format_data = $old_formats;
		$plans       = [];
		$temps       = [];

		try {
			foreach ( $formats as $format ) {
				$quality          = (int) ( $run_settings['quality'][ $format ] ?? 82 );
				$plans[ $format ] = $this->plan_format( $attachment_id, $source_file, $source_mime, $format, $quality, $run_settings );
				$temps            = array_merge( $temps, self::plan_temps( $plans[ $format ] ) );
			}

			if ( $regenerate ) {
				$refusal = self::regenerate_refusal( $plans );
				if ( null !== $refusal ) {
					return $refusal;
				}
			}

			$counts       = [
				'succeeded' => 0,
				'failed'    => 0,
				'skipped'   => 0,
			];
			$last_error   = '';
			$total_output = 0;

			foreach ( $plans as $format => $plan ) {
				$old_entry = isset( $old_formats[ $format ] ) && is_array( $old_formats[ $format ] ) ? $old_formats[ $format ] : [];
				$entry     = is_wp_error( $plan ) ? $plan : ( 'skipped' === $plan['status'] ? $plan : $this->place_format( $attachment_id, $format, $plan, $old_entry ) );

				if ( is_wp_error( $entry ) ) {
					++$counts['failed'];
					$last_error = $entry->get_error_message();
					// A copy that was not replaced keeps its record; it is still on disk.
					$format_data[ $format ] = [] !== $old_entry && ! is_wp_error( $plan )
						? $old_entry
						: [
							'status' => 'failed',
							'reason' => $entry->get_error_code(),
						];
					continue;
				}

				if ( 'skipped' === $entry['status'] ) {
					++$counts['skipped'];
					$format_data[ $format ] = $entry;
					continue;
				}

				++$counts['succeeded'];
				if ( $regenerate ) {
					$this->remove_unreplaced_copies( $attachment_id, $format, $old_entry, $entry );
				}
				// Track the smallest output, not the sum. A browser downloads one
				// format, so the meaningful saving is the source against the best
				// candidate — summing WebP and AVIF would report a bogus negative.
				$size = (int) $entry['size'];
				if ( 0 === $total_output || $size < $total_output ) {
					$total_output = $size;
				}
				$format_data[ $format ] = $entry;
			}
		} finally {
			foreach ( $temps as $temp ) {
				$this->delete_temp_file( $temp );
			}
		}

		return $this->record_result( $attachment_id, $source_file, $source_mime, $format_data, $counts, $last_error, $total_output );
	}

	/**
	 * Save the conversion data and shape the result.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $source_file   Source file.
	 * @param string $source_mime   Source MIME type.
	 * @param array  $format_data   Per-format records.
	 * @param array  $counts        succeeded, failed and skipped formats.
	 * @param string $last_error    Last error message.
	 * @param int    $total_output  Smallest output size.
	 *
	 * @return array|WP_Error
	 */
	private function record_result( int $attachment_id, string $source_file, string $source_mime, array $format_data, array $counts, string $last_error, int $total_output ) {
		$succeeded = $counts['succeeded'];
		$failed    = $counts['failed'];
		$skipped   = $counts['skipped'];
		$status    = $succeeded > 0
			? ( $failed > 0 ? 'partial' : 'completed' )
			: ( $skipped > 0 && 0 === $failed ? 'skipped' : 'failed' );

		ConversionMetadata::instance()->save(
			$attachment_id,
			[
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
			]
		);

		if ( 'failed' === $status ) {
			$codes = array_unique( array_filter( array_column( $format_data, 'reason' ) ) );
			return new WP_Error(
				[ 'tsmlt_conversion_name_conflict' ] === array_values( $codes ) ? 'tsmlt_conversion_name_conflict' : 'tsmlt_conversion_failed',
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
	 * Make one format's outputs (full size and, with generated sizes, each
	 * size) into checked temporary files. Nothing is replaced here.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $source_file   Absolute path to the full-size source.
	 * @param string $source_mime   Source MIME type.
	 * @param string $format        Target format key.
	 * @param int    $quality       Quality value, 1–100.
	 * @param array  $run_settings  Effective run settings.
	 *
	 * @return array|WP_Error A skipped result, or a plan with `full` and `sizes`.
	 */
	private function plan_format( int $attachment_id, string $source_file, string $source_mime, string $format, int $quality, array $run_settings ) {
		$engine = ConversionCapabilities::instance()->get_engine_for( $source_mime, $format );

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
		$temp        = $this->make_temp( $engine, $source_file, $destination, $format, $quality, $attachment_id );

		if ( is_wp_error( $temp ) ) {
			return $temp;
		}

		return [
			'status'  => 'planned',
			'quality' => $quality,
			'full'    => [
				'temp'   => $temp,
				'target' => $destination,
			],
			// Generated sizes (Pro). A failure on one thumbnail must not fail the
			// format — missing and deleted intermediates are normal on real sites.
			'sizes'   => ! empty( $run_settings['generated_sizes'] ) ? $this->plan_generated_sizes( $attachment_id, $source_file, $format, $quality ) : [],
		];
	}

	/**
	 * Make every WordPress-generated size of one format into checked
	 * temporary files.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $source_file   Absolute path to the full-size source.
	 * @param string $format        Target format key.
	 * @param int    $quality       Quality value.
	 *
	 * @return array<string, array> Keyed by size name: a plan (`temp`, `target`) or a skipped/failed result.
	 */
	private function plan_generated_sizes( int $attachment_id, string $source_file, string $format, int $quality ): array {
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
			$engine    = '' !== $size_mime && ! $this->source_matches_format( $size_mime, $format ) ? $capabilities->get_engine_for( $size_mime, $format ) : null;

			if ( null === $engine ) {
				$results[ $size_key ] = [
					'status' => 'skipped',
					'reason' => 'unsupported_source',
				];
				continue;
			}

			$destination = $this->build_output_path( $size_path, $format );
			$temp        = $this->make_temp( $engine, $size_path, $destination, $format, $quality, $attachment_id );

			$results[ $size_key ] = is_wp_error( $temp )
				? [
					'status' => 'failed',
					'reason' => $temp->get_error_code(),
				]
				: [
					'status' => 'planned',
					'temp'   => $temp,
					'target' => $destination,
				];
		}

		return $results;
	}

	/**
	 * Temporary files of a format plan.
	 *
	 * @param array|WP_Error $plan Plan.
	 *
	 * @return string[]
	 */
	private static function plan_temps( $plan ): array {
		if ( is_wp_error( $plan ) || 'planned' !== ( $plan['status'] ?? '' ) ) {
			return [];
		}
		$temps = [ $plan['full']['temp'] ];
		foreach ( $plan['sizes'] as $size ) {
			if ( 'planned' === $size['status'] ) {
				$temps[] = $size['temp'];
			}
		}
		return $temps;
	}

	/**
	 * For a regeneration of copies: the first failed output, as an error
	 * (nothing is replaced), or null when every output was made.
	 *
	 * @param array $plans Plans by format.
	 *
	 * @return WP_Error|null
	 */
	private static function regenerate_refusal( array $plans ): ?WP_Error {
		foreach ( $plans as $plan ) {
			if ( is_wp_error( $plan ) ) {
				return $plan;
			}
			foreach ( 'planned' === $plan['status'] ? $plan['sizes'] : [] as $size ) {
				if ( 'failed' === $size['status'] ) {
					return new WP_Error( (string) $size['reason'], esc_html__( 'A converted size could not be made. The existing copies were kept.', 'media-library-tools' ) );
				}
			}
		}
		return null;
	}

	/**
	 * Move one format's outputs into place and build its record.
	 *
	 * The full-size copy goes first: if it cannot be placed, nothing of the
	 * format is placed (the caller keeps the old record). A size that cannot
	 * be placed keeps its old record when it had one.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $format        Format key.
	 * @param array  $plan          Plan from plan_format().
	 * @param array  $old_entry     Previous record of the format.
	 *
	 * @return array|WP_Error
	 */
	private function place_format( int $attachment_id, string $format, array $plan, array $old_entry ) {
		$placed = $this->place( $plan['full']['temp'], $plan['full']['target'], $attachment_id, $format );

		if ( is_wp_error( $placed ) ) {
			return $placed;
		}

		$sizes = [];
		foreach ( $plan['sizes'] as $size_key => $size ) {
			if ( 'planned' !== $size['status'] ) {
				$sizes[ $size_key ] = $size;
				continue;
			}
			$moved = $this->place( $size['temp'], $size['target'], $attachment_id, $format );
			if ( is_wp_error( $moved ) ) {
				$sizes[ $size_key ] = isset( $old_entry['sizes'][ $size_key ]['file'] )
					? $old_entry['sizes'][ $size_key ]
					: [
						'status' => 'failed',
						'reason' => $moved->get_error_code(),
					];
				continue;
			}
			$sizes[ $size_key ] = [
				'status' => 'completed',
				'file'   => (string) _wp_relative_upload_path( $size['target'] ),
				'size'   => (int) filesize( $size['target'] ),
			];
		}

		return [
			'status'       => 'completed',
			'file'         => (string) _wp_relative_upload_path( $plan['full']['target'] ),
			'size'         => (int) filesize( $plan['full']['target'] ),
			'mime_type'    => 'image/' . $format,
			'quality'      => (int) $plan['quality'],
			'generated_at' => current_time( 'mysql', true ),
			'sizes'        => $sizes,
		];
	}

	/**
	 * After a regeneration of copies: delete old copies of the format that
	 * were not made again, when they are this attachment's own.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $format        Format key.
	 * @param array  $old_entry     Previous record.
	 * @param array  $new_entry     New record.
	 *
	 * @return void
	 */
	private function remove_unreplaced_copies( int $attachment_id, string $format, array $old_entry, array $new_entry ): void {
		$keep = [ (string) ( $new_entry['file'] ?? '' ) ];
		foreach ( (array) ( $new_entry['sizes'] ?? [] ) as $size ) {
			$keep[] = (string) ( $size['file'] ?? '' );
		}
		$old = [ (string) ( $old_entry['file'] ?? '' ) ];
		foreach ( (array) ( $old_entry['sizes'] ?? [] ) as $size ) {
			$old[] = (string) ( $size['file'] ?? '' );
		}
		$basedir = trailingslashit( (string) wp_get_upload_dir()['basedir'] );
		foreach ( array_diff( array_filter( $old ), $keep ) as $relative ) {
			if ( ConversionOwnership::is_owned_copy( $attachment_id, $basedir . $relative, $format ) ) {
				wp_delete_file( $basedir . $relative );
			}
		}
	}

	/**
	 * Make one output into a checked temporary file next to its target.
	 *
	 * Refused (`tsmlt_conversion_name_conflict`) when the target name belongs
	 * to anything but this attachment's own copy. The output goes to a
	 * temporary file that is validated — real image, expected MIME, same
	 * dimensions as the source — so a truncated or mistyped result never
	 * lands in the uploads directory.
	 *
	 * @param object $engine        Converter engine.
	 * @param string $source        Absolute source path.
	 * @param string $destination   Absolute destination path.
	 * @param string $format        Target format key.
	 * @param int    $quality       Quality value.
	 * @param int    $attachment_id Attachment post ID.
	 *
	 * @return string|WP_Error The temporary file.
	 */
	private function make_temp( $engine, string $source, string $destination, string $format, int $quality, int $attachment_id ) {
		if ( ! file_exists( $source ) || ! is_readable( $source ) ) {
			return new WP_Error(
				'tsmlt_conversion_missing_source',
				esc_html__( 'The image file is missing or unreadable on the server.', 'media-library-tools' )
			);
		}

		if ( 'conflict' === ConversionOwnership::target_state( $attachment_id, $destination, $format ) ) {
			return self::name_conflict();
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

		return $temp_file;
	}

	/**
	 * Move a checked temporary file to its target, checking the target again
	 * first. This attachment's own copy is replaced by an atomic rename; a
	 * free name is taken with a hard link, which fails instead of replacing a
	 * file that appeared meanwhile. Anything else is a name conflict.
	 *
	 * @param string $temp          Temporary file.
	 * @param string $destination   Target.
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $format        Format key.
	 *
	 * @return true|WP_Error
	 */
	private function place( string $temp, string $destination, int $attachment_id, string $format ) {
		$state = ConversionOwnership::target_state( $attachment_id, $destination, $format );

		if ( 'conflict' === $state ) {
			return self::name_conflict();
		}

		if ( 'free' === $state && ! $this->link_new( $temp, $destination ) ) {
			return file_exists( $destination ) ? self::name_conflict() : self::move_failed();
		}

		if ( 'owned' === $state && ! @rename( $temp, $destination ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Atomic same-directory replacement; WP_Filesystem has no atomic equivalent. Failure reported as WP_Error.
			return self::move_failed();
		}

		@chmod( $destination, defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- Best-effort permissions.

		return true;
	}

	/**
	 * Create a file that must not exist yet from a temporary file: a hard
	 * link (fails when the name exists), then the temporary name is removed.
	 * Where hard links are unavailable, the name is checked once more and
	 * the file renamed.
	 *
	 * @param string $temp        Temporary file.
	 * @param string $destination Target that must not exist.
	 *
	 * @return bool
	 */
	private function link_new( string $temp, string $destination ): bool {
		if ( @link( $temp, $destination ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- An existing name or no hard-link support is handled below.
			$this->delete_temp_file( $temp );
			return true;
		}
		clearstatcache( true, $destination );
		if ( file_exists( $destination ) ) {
			return false;
		}
		return @rename( $temp, $destination ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Same-directory move where hard links are not supported; failure reported by the caller.
	}

	/**
	 * Name-conflict error.
	 *
	 * @return WP_Error
	 */
	private static function name_conflict(): WP_Error {
		return new WP_Error(
			'tsmlt_conversion_name_conflict',
			esc_html__( 'A file with the converted name already exists and is not this image\'s own converted copy, so it was not replaced.', 'media-library-tools' )
		);
	}

	/**
	 * Move-failed error.
	 *
	 * @return WP_Error
	 */
	private static function move_failed(): WP_Error {
		return new WP_Error(
			'tsmlt_conversion_move_failed',
			esc_html__( 'The converted image could not be saved.', 'media-library-tools' )
		);
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
