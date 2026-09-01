<?php
/**
 * Server format-capability detection for image conversion.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Conversion;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Modules\Compress\Conversion\Converters\GDConverter;
use TinySolutions\mlt\Modules\Compress\Conversion\Converters\ImageConverterInterface;
use TinySolutions\mlt\Modules\Compress\Conversion\Converters\ImagickConverter;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Reports which output formats this server can actually produce.
 *
 * WebP and especially AVIF depend on delegates compiled into Imagick or GD, so
 * support is probed rather than assumed. The result drives both the UI (which
 * disables formats that cannot work) and the server-side guards (which refuse
 * jobs for them regardless of what the browser sends).
 */
class ConversionCapabilities {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Output formats the feature knows about.
	 *
	 * @var string[]
	 */
	const FORMATS = [ 'webp', 'avif' ];

	/**
	 * Source MIME types that may be converted.
	 *
	 * @var string[]
	 */
	const SUPPORTED_SOURCES = [
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/webp',
	];

	/**
	 * Registered engines in priority order.
	 *
	 * @var ImageConverterInterface[]|null
	 */
	private $engines = null;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Build the engine list: Imagick first, then GD.
	 *
	 * Third parties can register additional engines — for example a binary or
	 * API-backed converter — through the `tsmlt_conversion_engines` filter.
	 *
	 * @return ImageConverterInterface[]
	 */
	public function get_engines(): array {
		if ( null !== $this->engines ) {
			return $this->engines;
		}

		$engines = [
			new ImagickConverter(),
			new GDConverter(),
		];

		/**
		 * Filter the registered conversion engines.
		 *
		 * @param ImageConverterInterface[] $engines Engines in priority order.
		 */
		$engines = apply_filters( 'tsmlt_conversion_engines', $engines );

		$this->engines = array_values(
			array_filter(
				(array) $engines,
				static function ( $engine ) {
					return $engine instanceof ImageConverterInterface;
				}
			)
		);

		return $this->engines;
	}

	/**
	 * Return the best engine able to read the source and write the format.
	 *
	 * Selection is per format, not global: a server may have Imagick with WebP
	 * only and GD with AVIF, in which case each format uses a different engine.
	 *
	 * @param string $source_mime Source MIME type.
	 * @param string $format      Target format key.
	 *
	 * @return ImageConverterInterface|null Null when nothing can do the job.
	 */
	public function get_engine_for( string $source_mime, string $format ) {
		foreach ( $this->get_engines() as $engine ) {
			if ( $engine->is_available()
				&& $engine->supports_source( $source_mime )
				&& $engine->supports_format( $format ) ) {
				return $engine;
			}
		}

		return null;
	}

	/**
	 * Whether any engine can produce the given format.
	 *
	 * @param string $format Target format key.
	 *
	 * @return bool
	 */
	public function supports_format( string $format ): bool {
		if ( ! in_array( $format, self::FORMATS, true ) ) {
			return false;
		}

		foreach ( $this->get_engines() as $engine ) {
			if ( $engine->is_available() && $engine->supports_format( $format ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a source MIME type may be converted at all.
	 *
	 * @param string $mime_type Source MIME type.
	 *
	 * @return bool
	 */
	public function is_supported_source( string $mime_type ): bool {
		return in_array( strtolower( $mime_type ), self::SUPPORTED_SOURCES, true );
	}

	/**
	 * Whether conversion is usable at all on this server.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		foreach ( self::FORMATS as $format ) {
			if ( $this->supports_format( $format ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Full capability report for the UI.
	 *
	 * Reports raw server support only. Entitlement is layered on separately by
	 * `ConversionSettings::get_payload()` so the two concerns stay distinct —
	 * "the server cannot do AVIF" and "your licence does not include AVIF" are
	 * different messages.
	 *
	 * @return array{formats: array<string, bool>, engines: array<string, array>}
	 */
	public function to_array(): array {
		$formats = [];
		foreach ( self::FORMATS as $format ) {
			$formats[ $format ] = $this->supports_format( $format );
		}

		$engines = [];
		foreach ( $this->get_engines() as $engine ) {
			$available = $engine->is_available();

			$engines[ $engine->get_id() ] = [
				'available' => $available,
				'webp'      => $available && $engine->supports_format( 'webp' ),
				'avif'      => $available && $engine->supports_format( 'avif' ),
			];
		}

		return [
			'formats' => $formats,
			'engines' => $engines,
		];
	}
}
