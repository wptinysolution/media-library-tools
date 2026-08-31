<?php
/**
 * Compression settings: presets, quality resolution and sanitisation.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Resolves the effective compression settings for a run.
 *
 * All quality values used by the processor come from here. Values supplied by
 * the browser are treated as requests only: they are clamped, validated against
 * the preset table, and downgraded when the install is not entitled to them.
 */
class CompressionSettings {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Default preset when nothing has been configured.
	 */
	const DEFAULT_MODE = 'balanced';

	/**
	 * Quality applied to each preset, per MIME family.
	 *
	 * PNG is lossless, so its numbers steer palette reduction and zlib effort
	 * rather than visual fidelity.
	 *
	 * @var array<string, array<string, int>>
	 */
	const PRESETS = [
		'high_quality' => [
			'jpeg' => 90,
			'png'  => 95,
			'webp' => 90,
		],
		'balanced'     => [
			'jpeg' => 82,
			'png'  => 85,
			'webp' => 80,
		],
		'maximum'      => [
			'jpeg' => 65,
			'png'  => 70,
			'webp' => 65,
		],
	];

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Preset list for the settings UI.
	 *
	 * @return array<int, array{value: string, label: string, description: string}>
	 */
	public function get_modes(): array {
		return [
			[
				'value'       => 'high_quality',
				'label'       => esc_html__( 'High Quality', 'media-library-tools' ),
				'description' => esc_html__( 'Smallest visual change. Modest file size savings.', 'media-library-tools' ),
			],
			[
				'value'       => 'balanced',
				'label'       => esc_html__( 'Balanced', 'media-library-tools' ),
				'description' => esc_html__( 'Recommended. Good savings with no visible quality loss on most images.', 'media-library-tools' ),
			],
			[
				'value'       => 'maximum',
				'label'       => esc_html__( 'Maximum Compression', 'media-library-tools' ),
				'description' => esc_html__( 'Largest savings. Quality loss may be visible on detailed images.', 'media-library-tools' ),
			],
		];
	}

	/**
	 * Whether a value names a known preset.
	 *
	 * @param string $mode Preset identifier.
	 *
	 * @return bool
	 */
	public function is_valid_mode( string $mode ): bool {
		return isset( self::PRESETS[ $mode ] );
	}

	/**
	 * Read the stored compression settings, merged over defaults.
	 *
	 * Pro-only values are forced off when the install is not entitled, so a
	 * lapsed licence cannot leave backup or auto-compression silently active.
	 *
	 * @return array{
	 *     mode: string,
	 *     quality: int,
	 *     use_custom_quality: bool,
	 *     backup_originals: bool,
	 *     compress_generated_sizes: bool,
	 *     auto_compress_on_upload: bool
	 * }
	 */
	public function get_settings(): array {
		$stored = get_option( 'tsmlt_settings', [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$access = CompressionAccess::instance();

		$mode = isset( $stored['compression_mode'] ) ? (string) $stored['compression_mode'] : self::DEFAULT_MODE;
		if ( ! $this->is_valid_mode( $mode ) ) {
			$mode = self::DEFAULT_MODE;
		}

		$use_custom = ! empty( $stored['compression_use_custom_quality'] ) && $access->can_use_custom_quality();

		$quality = isset( $stored['compression_quality'] ) ? absint( $stored['compression_quality'] ) : 0;
		$quality = $this->clamp_quality( $quality );

		return [
			'mode'                     => $mode,
			'quality'                  => $quality,
			'use_custom_quality'       => $use_custom,
			'backup_originals'         => ! empty( $stored['compression_backup_originals'] ) && $access->can_backup_originals(),
			'compress_generated_sizes' => ! empty( $stored['compression_generated_sizes'] ) && $access->can_compress_generated_sizes(),
			'auto_compress_on_upload'  => ! empty( $stored['compression_auto_on_upload'] ) && $access->can_auto_compress_on_upload(),
		];
	}

	/**
	 * Build the effective settings for one run from a request payload.
	 *
	 * The result is what the processor actually uses. Anything the install is
	 * not entitled to is stripped here rather than at the call sites, so a
	 * crafted request cannot enable a Pro capability.
	 *
	 * @param array $params Raw request parameters.
	 *
	 * @return array{
	 *     mode: string,
	 *     quality: int,
	 *     use_custom_quality: bool,
	 *     backup_originals: bool,
	 *     compress_generated_sizes: bool
	 * }
	 */
	public function resolve_run_settings( array $params ): array {
		$access   = CompressionAccess::instance();
		$defaults = $this->get_settings();

		$mode = isset( $params['mode'] ) ? sanitize_key( (string) $params['mode'] ) : $defaults['mode'];
		if ( ! $this->is_valid_mode( $mode ) ) {
			$mode = $defaults['mode'];
		}

		// A custom quality is only honoured for Pro installs; everyone else
		// falls back to the preset table.
		$use_custom = false;
		$quality    = 0;

		if ( $access->can_use_custom_quality() ) {
			$requested_custom = array_key_exists( 'use_custom_quality', $params )
				? ! empty( $params['use_custom_quality'] )
				: $defaults['use_custom_quality'];

			if ( $requested_custom ) {
				$raw_quality = array_key_exists( 'quality', $params )
					? absint( $params['quality'] )
					: $defaults['quality'];

				$quality = $this->clamp_quality( $raw_quality );
				if ( $quality > 0 ) {
					$use_custom = true;
				}
			}
		}

		$backup = $access->can_backup_originals()
			&& ( array_key_exists( 'backup_originals', $params )
				? ! empty( $params['backup_originals'] )
				: $defaults['backup_originals'] );

		$generated = $access->can_compress_generated_sizes()
			&& ( array_key_exists( 'compress_generated_sizes', $params )
				? ! empty( $params['compress_generated_sizes'] )
				: $defaults['compress_generated_sizes'] );

		return [
			'mode'                     => $mode,
			'quality'                  => $quality,
			'use_custom_quality'       => $use_custom,
			'backup_originals'         => $backup,
			'compress_generated_sizes' => $generated,
		];
	}

	/**
	 * Resolve the quality number to hand the engine for one file.
	 *
	 * @param array  $run_settings Settings from `resolve_run_settings()`.
	 * @param string $mime_type    MIME type being compressed.
	 *
	 * @return int Quality value, 1–100.
	 */
	public function get_quality_for( array $run_settings, string $mime_type ): int {
		if ( ! empty( $run_settings['use_custom_quality'] ) && ! empty( $run_settings['quality'] ) ) {
			return $this->clamp_quality( (int) $run_settings['quality'] );
		}

		$mode = isset( $run_settings['mode'] ) ? (string) $run_settings['mode'] : self::DEFAULT_MODE;
		if ( ! $this->is_valid_mode( $mode ) ) {
			$mode = self::DEFAULT_MODE;
		}

		$family = $this->mime_family( $mime_type );

		return (int) ( self::PRESETS[ $mode ][ $family ] ?? self::PRESETS[ self::DEFAULT_MODE ]['jpeg'] );
	}

	/**
	 * Sanitise the compression keys of a settings-save payload.
	 *
	 * Returns only the Free-tier keys. Pro keys are written by the Pro plugin on
	 * `tsmlt/settings/before/save`, which keeps the Free/Pro boundary in one
	 * place per plugin rather than spread across both.
	 *
	 * @param array $params Raw request parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_free_settings( array $params ): array {
		$mode = isset( $params['compression_mode'] ) ? sanitize_key( (string) $params['compression_mode'] ) : self::DEFAULT_MODE;
		if ( ! $this->is_valid_mode( $mode ) ) {
			$mode = self::DEFAULT_MODE;
		}

		return [
			'compression_mode' => $mode,
		];
	}

	/**
	 * Constrain a quality value to the engine-safe 1–100 range.
	 *
	 * Zero is preserved to mean "not set", which makes the preset apply.
	 *
	 * @param int $quality Raw quality value.
	 *
	 * @return int
	 */
	public function clamp_quality( int $quality ): int {
		if ( $quality <= 0 ) {
			return 0;
		}

		return max( 1, min( 100, $quality ) );
	}

	/**
	 * Map a MIME type onto its preset family key.
	 *
	 * @param string $mime_type MIME type.
	 *
	 * @return string One of `jpeg`, `png`, `webp`.
	 */
	private function mime_family( string $mime_type ): string {
		switch ( strtolower( $mime_type ) ) {
			case 'image/png':
				return 'png';
			case 'image/webp':
				return 'webp';
			default:
				return 'jpeg';
		}
	}
}
