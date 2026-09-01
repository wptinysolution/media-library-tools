<?php
/**
 * Conversion settings: formats, quality resolution and sanitisation.
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

/**
 * Resolves the effective conversion settings for a run.
 *
 * Every value the converter uses comes from here. Anything supplied by the
 * browser is treated as a request only: clamped, checked against server
 * capability, and dropped when the install is not entitled to it — so a crafted
 * payload cannot turn on AVIF or custom quality.
 *
 * Stored under `conversion_*` keys in the shared `tsmlt_settings` option,
 * keeping them namespaced away from the `compression_*` keys.
 */
class ConversionSettings {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Default quality per format when nothing has been configured.
	 *
	 * AVIF is set lower than WebP because it holds quality far better at the
	 * same number — 70 AVIF is visually comparable to about 82 WebP.
	 *
	 * @var array<string, int>
	 */
	const DEFAULT_QUALITY = [
		'webp' => 82,
		'avif' => 70,
	];

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Read the stored conversion settings, merged over defaults.
	 *
	 * Pro-only values are forced off when the install is not entitled, so a
	 * lapsed licence cannot leave AVIF or auto-conversion silently active.
	 *
	 * @return array{
	 *     webp_enabled: bool,
	 *     avif_enabled: bool,
	 *     webp_quality: int,
	 *     avif_quality: int,
	 *     use_custom_quality: bool,
	 *     generated_sizes: bool,
	 *     auto_on_upload: bool
	 * }
	 */
	public function get_settings(): array {
		$stored = get_option( 'tsmlt_settings', [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$access       = CompressionAccess::instance();
		$capabilities = ConversionCapabilities::instance();

		$use_custom = ! empty( $stored['conversion_use_custom_quality'] )
			&& $access->can_use_custom_conversion_quality();

		return [
			// WebP defaults on; AVIF requires both Pro and a capable server.
			'webp_enabled'       => ! isset( $stored['conversion_webp_enabled'] )
				? $capabilities->supports_format( 'webp' )
				: ( ! empty( $stored['conversion_webp_enabled'] ) && $capabilities->supports_format( 'webp' ) ),
			'avif_enabled'       => ! empty( $stored['conversion_avif_enabled'] )
				&& $access->can_convert_avif()
				&& $capabilities->supports_format( 'avif' ),
			'webp_quality'       => $this->clamp_quality(
				isset( $stored['conversion_webp_quality'] ) ? absint( $stored['conversion_webp_quality'] ) : 0,
				'webp'
			),
			'avif_quality'       => $this->clamp_quality(
				isset( $stored['conversion_avif_quality'] ) ? absint( $stored['conversion_avif_quality'] ) : 0,
				'avif'
			),
			'use_custom_quality' => $use_custom,
			'generated_sizes'    => ! empty( $stored['conversion_generated_sizes'] )
				&& $access->can_convert_generated_sizes(),
			'auto_on_upload'     => ! empty( $stored['conversion_auto_on_upload'] )
				&& $access->can_auto_convert_on_upload(),
		];
	}

	/**
	 * Build the effective settings for one run from a request payload.
	 *
	 * The result is what the converter actually uses. Formats the server cannot
	 * produce, or the licence does not cover, are stripped here rather than at
	 * the call sites.
	 *
	 * @param array $params Raw request parameters.
	 *
	 * @return array{
	 *     formats: string[],
	 *     quality: array<string, int>,
	 *     generated_sizes: bool
	 * }
	 */
	public function resolve_run_settings( array $params ): array {
		$access       = CompressionAccess::instance();
		$capabilities = ConversionCapabilities::instance();
		$defaults     = $this->get_settings();

		// Requested formats, falling back to whatever the settings enable.
		$requested = isset( $params['formats'] ) && is_array( $params['formats'] )
			? array_map( 'sanitize_key', $params['formats'] )
			: array_keys( array_filter( [
				'webp' => $defaults['webp_enabled'],
				'avif' => $defaults['avif_enabled'],
			] ) );

		$formats = [];
		foreach ( $requested as $format ) {
			if ( ! in_array( $format, ConversionCapabilities::FORMATS, true ) ) {
				continue;
			}
			// The server must be able to produce it...
			if ( ! $capabilities->supports_format( $format ) ) {
				continue;
			}
			// ...and AVIF additionally requires Pro.
			if ( 'avif' === $format && ! $access->can_convert_avif() ) {
				continue;
			}
			$formats[] = $format;
		}

		$formats = array_values( array_unique( $formats ) );

		// Quality: a custom value is only honoured for Pro installs.
		$quality = [
			'webp' => $defaults['webp_quality'],
			'avif' => $defaults['avif_quality'],
		];

		if ( $access->can_use_custom_conversion_quality() && ! empty( $params['quality'] ) && is_array( $params['quality'] ) ) {
			foreach ( [ 'webp', 'avif' ] as $format ) {
				if ( isset( $params['quality'][ $format ] ) ) {
					$quality[ $format ] = $this->clamp_quality( absint( $params['quality'][ $format ] ), $format );
				}
			}
		}

		$generated = $access->can_convert_generated_sizes()
			&& ( array_key_exists( 'generated_sizes', $params )
				? ! empty( $params['generated_sizes'] )
				: $defaults['generated_sizes'] );

		return [
			'formats'         => $formats,
			'quality'         => $quality,
			'generated_sizes' => $generated,
		];
	}

	/**
	 * Sanitise the conversion keys of a settings-save payload.
	 *
	 * Returns only the Free-tier keys. Pro keys are written by the Pro plugin on
	 * `tsmlt/settings/before/save`, matching how compression settings are split.
	 *
	 * @param array $params Raw request parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_free_settings( array $params ): array {
		$out = [];

		if ( array_key_exists( 'conversion_webp_enabled', $params ) ) {
			$out['conversion_webp_enabled'] = ! empty( $params['conversion_webp_enabled'] ) ? 1 : 0;
		}

		if ( array_key_exists( 'conversion_serve_modern', $params ) ) {
			$out['conversion_serve_modern'] = ! empty( $params['conversion_serve_modern'] ) ? 1 : 0;
		}

		return $out;
	}

	/**
	 * Constrain a quality value to the engine-safe 1–100 range.
	 *
	 * Zero or out-of-range falls back to the format's default rather than
	 * failing, so a malformed value degrades gracefully.
	 *
	 * @param int    $quality Raw quality value.
	 * @param string $format  Format key, used for the fallback.
	 *
	 * @return int
	 */
	public function clamp_quality( int $quality, string $format ): int {
		if ( $quality < 1 || $quality > 100 ) {
			return self::DEFAULT_QUALITY[ $format ] ?? 82;
		}

		return $quality;
	}

	/**
	 * Everything the Convert tab needs on load.
	 *
	 * Combines raw server capability with entitlement so the UI can distinguish
	 * "this server cannot do AVIF" from "AVIF needs Pro".
	 *
	 * @return array
	 */
	public function get_payload(): array {
		$capabilities = ConversionCapabilities::instance();

		return [
			'settings'     => $this->get_settings(),
			'capabilities' => $capabilities->to_array(),
			'access'       => CompressionAccess::instance()->conversion_to_array(),
			'sources'      => ConversionCapabilities::SUPPORTED_SOURCES,
			'available'    => $capabilities->is_available(),
		];
	}
}
