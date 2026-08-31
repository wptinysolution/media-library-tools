<?php
/**
 * Compression engine registry and single-file compression entry point.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Modules\Compress\Compressors\CompressorInterface;
use TinySolutions\mlt\Modules\Compress\Compressors\GDCompressor;
use TinySolutions\mlt\Modules\Compress\Compressors\ImagickCompressor;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Selects a compression engine and compresses individual physical files.
 *
 * This class owns the "compress one file safely" flow: temp output, validation
 * and size comparison. It deliberately knows nothing about attachments, backups
 * or metadata so new engines can be registered without touching the callers.
 */
class CompressionManager {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * MIME types the feature accepts, regardless of engine.
	 *
	 * @var string[]
	 */
	const SUPPORTED_MIME_TYPES = [
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/webp',
	];

	/**
	 * Registered engines, highest priority first.
	 *
	 * @var CompressorInterface[]|null
	 */
	private $engines = null;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Build the engine list in priority order: Imagick, then GD.
	 *
	 * Third parties can register additional engines — for example an API-backed
	 * compressor — via the `tsmlt_compression_engines` filter. Anything that is
	 * not a CompressorInterface is discarded.
	 *
	 * @return CompressorInterface[]
	 */
	public function get_engines(): array {
		if ( null !== $this->engines ) {
			return $this->engines;
		}

		$engines = [
			new ImagickCompressor(),
			new GDCompressor(),
		];

		/**
		 * Filter the registered compression engines.
		 *
		 * @param CompressorInterface[] $engines Engines in priority order.
		 */
		$engines = apply_filters( 'tsmlt_compression_engines', $engines );

		$this->engines = array_values(
			array_filter(
				(array) $engines,
				static function ( $engine ) {
					return $engine instanceof CompressorInterface;
				}
			)
		);

		return $this->engines;
	}

	/**
	 * Return the highest-priority engine able to handle a MIME type.
	 *
	 * @param string $mime_type MIME type to compress.
	 *
	 * @return CompressorInterface|null Null when no engine can handle the type.
	 */
	public function get_engine_for( string $mime_type ) {
		foreach ( $this->get_engines() as $engine ) {
			if ( $engine->is_available() && $engine->supports_mime_type( $mime_type ) ) {
				return $engine;
			}
		}

		return null;
	}

	/**
	 * Whether at least one engine can compress at least one supported type.
	 *
	 * @return bool
	 */
	public function has_available_engine(): bool {
		foreach ( self::SUPPORTED_MIME_TYPES as $mime_type ) {
			if ( null !== $this->get_engine_for( $mime_type ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the feature accepts this MIME type at all.
	 *
	 * @param string $mime_type MIME type to test.
	 *
	 * @return bool
	 */
	public function is_supported_mime_type( string $mime_type ): bool {
		return in_array( strtolower( $mime_type ), self::SUPPORTED_MIME_TYPES, true );
	}

	/**
	 * Human-readable list of engines available on this server.
	 *
	 * @return array<int, array{id: string, label: string}>
	 */
	public function get_available_engine_info(): array {
		$labels = [
			'imagick' => esc_html__( 'ImageMagick', 'media-library-tools' ),
			'gd'      => esc_html__( 'GD', 'media-library-tools' ),
		];

		$info = [];
		foreach ( $this->get_engines() as $engine ) {
			if ( ! $engine->is_available() ) {
				continue;
			}
			$id     = $engine->get_id();
			$info[] = [
				'id'    => $id,
				'label' => $labels[ $id ] ?? $id,
			];
		}

		return $info;
	}

	/**
	 * Compress one physical file into a validated temporary file.
	 *
	 * Implements the safety contract: the source is never written to, output
	 * goes to a temporary file, and that file is validated and size-compared
	 * before the caller is allowed to use it. On any failure — including "the
	 * result was not smaller" — the temporary file is removed before returning,
	 * so callers never have to clean up after an error.
	 *
	 * A `no_improvement` result is not a failure: the caller should mark the
	 * file as skipped and keep the original.
	 *
	 * @param string $source    Absolute path to an existing readable image file.
	 * @param string $mime_type MIME type of the source file.
	 * @param int    $quality   Quality value, 1–100.
	 *
	 * @return array{
	 *     status: string,
	 *     temp_file: string,
	 *     original_size: int,
	 *     compressed_size: int,
	 *     engine: string
	 * }|WP_Error Status is `compressed` or `skipped`.
	 */
	public function compress_file( string $source, string $mime_type, int $quality ) {
		if ( ! $this->is_supported_mime_type( $mime_type ) ) {
			return new WP_Error(
				'tsmlt_compression_unsupported_mime',
				esc_html__( 'This file type cannot be compressed.', 'media-library-tools' )
			);
		}

		if ( ! file_exists( $source ) || ! is_readable( $source ) ) {
			return new WP_Error(
				'tsmlt_compression_missing_source',
				esc_html__( 'The image file is missing or unreadable on the server.', 'media-library-tools' )
			);
		}

		if ( ! wp_is_writable( $source ) ) {
			return new WP_Error(
				'tsmlt_compression_source_not_writable',
				esc_html__( 'The image file is not writable. Check file permissions.', 'media-library-tools' )
			);
		}

		$original_size = (int) filesize( $source );
		if ( $original_size <= 0 ) {
			return new WP_Error(
				'tsmlt_compression_invalid_source',
				esc_html__( 'The image file is empty.', 'media-library-tools' )
			);
		}

		$engine = $this->get_engine_for( $mime_type );
		if ( null === $engine ) {
			return new WP_Error(
				'tsmlt_compression_engine_unavailable',
				esc_html__( 'No image compression library is available on this server.', 'media-library-tools' )
			);
		}

		$temp_file = $this->create_temp_file( $source );
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		$compressed = $engine->compress( $source, $temp_file, $mime_type, $quality );

		if ( is_wp_error( $compressed ) ) {
			$this->delete_temp_file( $temp_file );

			return $compressed;
		}

		// Validate the output before it is allowed anywhere near the original.
		$validated = $this->validate_image_file( $temp_file, $mime_type );
		if ( is_wp_error( $validated ) ) {
			$this->delete_temp_file( $temp_file );

			return $validated;
		}

		clearstatcache( true, $temp_file );
		$compressed_size = (int) filesize( $temp_file );

		// Compression must never grow a file. Not an error — just no gain.
		if ( $compressed_size >= $original_size ) {
			$this->delete_temp_file( $temp_file );

			return [
				'status'          => 'skipped',
				'temp_file'       => '',
				'original_size'   => $original_size,
				'compressed_size' => $original_size,
				'engine'          => $engine->get_id(),
			];
		}

		return [
			'status'          => 'compressed',
			'temp_file'       => $temp_file,
			'original_size'   => $original_size,
			'compressed_size' => $compressed_size,
			'engine'          => $engine->get_id(),
		];
	}

	/**
	 * Create an empty temporary file alongside the source.
	 *
	 * Kept in the same directory so the later replacement is a same-filesystem
	 * rename, which is atomic. The system temp directory is often a different
	 * mount, where rename() silently degrades to a non-atomic copy.
	 *
	 * @param string $source Absolute path to the source file.
	 *
	 * @return string|WP_Error Absolute temp file path.
	 */
	private function create_temp_file( string $source ) {
		$directory = dirname( $source );

		if ( ! wp_is_writable( $directory ) ) {
			return new WP_Error(
				'tsmlt_compression_temp_failed',
				esc_html__( 'The upload directory is not writable, so a temporary file could not be created.', 'media-library-tools' )
			);
		}

		$temp_file = wp_tempnam( basename( $source ), $directory );

		if ( ! $temp_file ) {
			return new WP_Error(
				'tsmlt_compression_temp_failed',
				esc_html__( 'A temporary file could not be created.', 'media-library-tools' )
			);
		}

		return $temp_file;
	}

	/**
	 * Delete a temporary file, tolerating an already-missing path.
	 *
	 * @param string $temp_file Absolute temp file path.
	 *
	 * @return void
	 */
	public function delete_temp_file( string $temp_file ): void {
		if ( '' !== $temp_file && file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}
	}

	/**
	 * Confirm a written file is a non-empty, readable image of the expected type.
	 *
	 * Guards against engines that report success while emitting a truncated or
	 * zero-byte file — replacing an original with one of those would be data loss.
	 *
	 * @param string $file      Absolute path to the file to validate.
	 * @param string $mime_type Expected MIME type.
	 *
	 * @return true|WP_Error
	 */
	public function validate_image_file( string $file, string $mime_type ) {
		if ( ! file_exists( $file ) ) {
			return new WP_Error(
				'tsmlt_compression_validation_failed',
				esc_html__( 'The compressed file was not created.', 'media-library-tools' )
			);
		}

		clearstatcache( true, $file );
		if ( (int) filesize( $file ) <= 0 ) {
			return new WP_Error(
				'tsmlt_compression_validation_failed',
				esc_html__( 'The compressed file is empty.', 'media-library-tools' )
			);
		}

		$size = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid images are handled below.

		if ( false === $size || empty( $size[0] ) || empty( $size[1] ) ) {
			return new WP_Error(
				'tsmlt_compression_validation_failed',
				esc_html__( 'The compressed file is not a valid image.', 'media-library-tools' )
			);
		}

		// Normalise jpg/jpeg before comparing so a JPEG never looks mismatched.
		$actual   = $this->normalise_mime( (string) ( $size['mime'] ?? '' ) );
		$expected = $this->normalise_mime( $mime_type );

		if ( $actual !== $expected ) {
			return new WP_Error(
				'tsmlt_compression_validation_failed',
				esc_html__( 'The compressed file does not match the original image format.', 'media-library-tools' )
			);
		}

		return true;
	}

	/**
	 * Collapse equivalent MIME spellings to one canonical value.
	 *
	 * @param string $mime_type MIME type to normalise.
	 *
	 * @return string
	 */
	private function normalise_mime( string $mime_type ): string {
		$mime_type = strtolower( $mime_type );

		return 'image/jpg' === $mime_type ? 'image/jpeg' : $mime_type;
	}
}
