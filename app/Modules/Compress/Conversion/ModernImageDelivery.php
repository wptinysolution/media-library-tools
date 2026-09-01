<?php
/**
 * Serves generated WebP/AVIF files to browsers that support them.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Compress\Conversion;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Rewrites frontend image URLs to their converted WebP/AVIF counterparts.
 *
 * Converting produces the files; this is what actually makes visitors download
 * them. URLs are swapped only when three things hold: a conversion exists for
 * that attachment, the generated file is present on disk, and the requesting
 * browser advertises support for the format. Anything else falls through to the
 * original, so an unsupported browser or a missing file always still renders.
 *
 * The attachment itself is never modified — only the URL emitted at render time
 * — so disabling this setting instantly restores the original behaviour.
 */
class ModernImageDelivery {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Formats in preference order. AVIF is smaller, so it wins when the browser
	 * accepts both.
	 *
	 * @var string[]
	 */
	const FORMAT_PRIORITY = [ 'avif', 'webp' ];

	/**
	 * Per-request cache of attachment ID => chosen format data.
	 *
	 * A single page can render the same image many times; this keeps that to one
	 * metadata read and one filesystem check per attachment.
	 *
	 * @var array<int, array|false>
	 */
	private $cache = [];

	/**
	 * Formats the current request's browser accepts, resolved once.
	 *
	 * @var string[]|null
	 */
	private $accepted = null;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Register the frontend filters.
	 *
	 * Admin screens are deliberately excluded: the media library must keep
	 * showing the real attachment so editing, renaming and deleting operate on
	 * the file the user actually selected.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			return;
		}

		// Covers wp_get_attachment_image() and everything built on it.
		add_filter( 'wp_get_attachment_image_src', [ $this, 'filter_image_src' ], 20, 4 );
		// Responsive srcset entries, which bypass the filter above.
		add_filter( 'wp_calculate_image_srcset', [ $this, 'filter_srcset' ], 20, 5 );
		// Images embedded directly in post content (WP 6.0+).
		add_filter( 'wp_content_img_tag', [ $this, 'filter_content_img_tag' ], 20, 3 );

		// Tell caches the response varies by Accept, so a WebP-capable browser's
		// copy is never served to one that cannot display it.
		add_filter( 'wp_headers', [ $this, 'filter_headers' ] );
	}

	/**
	 * Whether modern image delivery is switched on.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$settings = get_option( 'tsmlt_settings', [] );

		return is_array( $settings ) && ! empty( $settings['conversion_serve_modern'] );
	}

	/**
	 * Formats the requesting browser accepts.
	 *
	 * Read from the `Accept` header rather than user-agent sniffing, which is
	 * what the header exists for and stays correct as browsers change.
	 *
	 * @return string[]
	 */
	private function get_accepted_formats(): array {
		if ( null !== $this->accepted ) {
			return $this->accepted;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared against fixed literals below, never output.
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';

		$formats = [];
		foreach ( self::FORMAT_PRIORITY as $format ) {
			if ( false !== strpos( $accept, 'image/' . $format ) ) {
				$formats[] = $format;
			}
		}

		$this->accepted = $formats;

		return $this->accepted;
	}

	/**
	 * Resolve the best converted format available for an attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return array|false Conversion entry for the chosen format, or false.
	 */
	private function get_best_format( int $attachment_id ) {
		if ( isset( $this->cache[ $attachment_id ] ) ) {
			return $this->cache[ $attachment_id ];
		}

		$this->cache[ $attachment_id ] = false;

		$accepted = $this->get_accepted_formats();

		if ( empty( $accepted ) ) {
			return false;
		}

		$data = ConversionMetadata::instance()->get( $attachment_id );

		if ( empty( $data['formats'] ) ) {
			return false;
		}

		foreach ( $accepted as $format ) {
			$entry = $data['formats'][ $format ] ?? null;

			if ( ! is_array( $entry ) || 'completed' !== ( $entry['status'] ?? '' ) || empty( $entry['file'] ) ) {
				continue;
			}

			$this->cache[ $attachment_id ] = [
				'format' => $format,
				'file'   => (string) $entry['file'],
				'sizes'  => (array) ( $entry['sizes'] ?? [] ),
			];

			return $this->cache[ $attachment_id ];
		}

		return false;
	}

	/**
	 * Swap a URL's extension for its converted counterpart, if that file exists.
	 *
	 * The existence check matters: a converted file deleted from disk without the
	 * metadata being cleared would otherwise produce a broken image on every page
	 * it appears.
	 *
	 * @param string $url    Original image URL.
	 * @param string $format Target format key.
	 *
	 * @return string The converted URL, or the original when unavailable.
	 */
	private function swap_url( string $url, string $format ): string {
		$uploads = wp_get_upload_dir();

		if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
			return $url;
		}

		// Only touch URLs inside this site's uploads directory.
		$baseurl = $uploads['baseurl'];
		if ( 0 !== strpos( $url, $baseurl ) ) {
			return $url;
		}

		$relative  = ltrim( substr( $url, strlen( $baseurl ) ), '/' );
		$directory = ltrim( dirname( $relative ), '.' );
		$directory = '' === $directory ? '' : trailingslashit( $directory );
		$converted = $directory . pathinfo( $relative, PATHINFO_FILENAME ) . '.' . $format;

		$path = trailingslashit( $uploads['basedir'] ) . $converted;

		if ( ! file_exists( $path ) ) {
			return $url;
		}

		return trailingslashit( $baseurl ) . $converted;
	}

	/**
	 * Point `wp_get_attachment_image_src()` at the converted file.
	 *
	 * @param array|false  $image         Array of image data, or false.
	 * @param int          $attachment_id Attachment post ID.
	 * @param string|int[] $size          Requested size.
	 * @param bool         $icon          Whether the result is an icon.
	 *
	 * @return array|false
	 */
	public function filter_image_src( $image, $attachment_id, $size, $icon ) {
		unset( $size, $icon );

		if ( ! is_array( $image ) || empty( $image[0] ) ) {
			return $image;
		}

		$best = $this->get_best_format( (int) $attachment_id );

		if ( false === $best ) {
			return $image;
		}

		$image[0] = $this->swap_url( (string) $image[0], $best['format'] );

		return $image;
	}

	/**
	 * Point each responsive srcset candidate at its converted file.
	 *
	 * @param array  $sources       Srcset candidates keyed by width.
	 * @param array  $size_array    Requested width and height.
	 * @param string $image_src     Original image URL.
	 * @param array  $image_meta    Attachment metadata.
	 * @param int    $attachment_id Attachment post ID.
	 *
	 * @return array
	 */
	public function filter_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		unset( $size_array, $image_src, $image_meta );

		if ( ! is_array( $sources ) ) {
			return $sources;
		}

		$best = $this->get_best_format( (int) $attachment_id );

		if ( false === $best ) {
			return $sources;
		}

		foreach ( $sources as $width => $source ) {
			if ( empty( $source['url'] ) ) {
				continue;
			}
			$sources[ $width ]['url'] = $this->swap_url( (string) $source['url'], $best['format'] );
		}

		return $sources;
	}

	/**
	 * Rewrite images embedded directly in post content.
	 *
	 * @param string $filtered_image The img tag markup.
	 * @param string $context        Where the tag is being rendered.
	 * @param int    $attachment_id  Attachment post ID.
	 *
	 * @return string
	 */
	public function filter_content_img_tag( $filtered_image, $context, $attachment_id ) {
		unset( $context );

		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return $filtered_image;
		}

		$best = $this->get_best_format( $attachment_id );

		if ( false === $best ) {
			return $filtered_image;
		}

		// Rewrite every uploads URL in src and srcset in one pass, leaving any
		// other attribute untouched.
		return (string) preg_replace_callback(
			'#(src|srcset)=(["\'])(.*?)\2#i',
			function ( $matches ) use ( $best ) {
				$value = (string) $matches[3];
				$parts = explode( ',', $value );

				foreach ( $parts as $index => $part ) {
					$part      = trim( $part );
					$segments  = preg_split( '#\s+#', $part, 2 );
					$candidate = $segments[0] ?? '';

					if ( '' === $candidate ) {
						continue;
					}

					$swapped         = $this->swap_url( $candidate, $best['format'] );
					$parts[ $index ] = isset( $segments[1] ) ? $swapped . ' ' . $segments[1] : $swapped;
				}

				return $matches[1] . '=' . $matches[2] . implode( ', ', $parts ) . $matches[2];
			},
			$filtered_image
		);
	}

	/**
	 * Advertise that responses vary by the Accept header.
	 *
	 * Without this a shared cache or CDN could hand a WebP-substituted page to a
	 * browser that cannot display WebP.
	 *
	 * @param array $headers Response headers.
	 *
	 * @return array
	 */
	public function filter_headers( $headers ) {
		if ( ! is_array( $headers ) ) {
			return $headers;
		}

		$headers['Vary'] = empty( $headers['Vary'] ) ? 'Accept' : $headers['Vary'] . ', Accept';

		return $headers;
	}
}
