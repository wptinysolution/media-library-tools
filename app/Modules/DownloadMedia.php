<?php

namespace TinySolutions\mlt\Modules;

use TinySolutions\mlt\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Class DownloadMedia
 *
 * Provides a shortcode to render a styled download button for media files.
 * Accepts either a WordPress attachment ID or a direct file URL.
 *
 * Usage:
 *   [tsmlt_download_button id="123" text="Download Image"]
 *   [tsmlt_download_button url="https://example.com/file.pdf" text="Download PDF"]
 */
class DownloadMedia {
	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Class Constructor
	 *
	 * Hooks into WordPress init and wp_head to register shortcode
	 * and inject internal CSS styles.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_shortcode' ] );
		add_action( 'wp_head', [ $this, 'add_styles' ] );
	}

	/**
	 * Register shortcode
	 *
	 * Registers the shortcode [tsmlt_download_button] with WordPress.
	 *
	 * @return void
	 */
	public function register_shortcode() {
		add_shortcode( 'tsmlt_download_button', [ $this, 'render_button' ] );
	}

	/**
	 * Add internal CSS styles
	 *
	 * Outputs inline styles for the download button
	 * to ensure consistent design without external CSS files.
	 *
	 * @return void
	 */
	public function add_styles() {
		?>
		<style>
			.tsmlt-download-btn {
				display: inline-block;
				padding: 10px 20px;
				background: #0073aa;
				color: #fff !important;
				border-radius: 5px;
				text-decoration: none;
				font-weight: bold;
				transition: background 0.3s ease;
			}
			.tsmlt-download-btn:hover {
				background: #005177;
			}
		</style>
		<?php
	}

	/**
	 * Render shortcode callback
	 *
	 * Generates the HTML for the download button.
	 *
	 * @param array $atts Shortcode attributes.
	 *   - id   (int)    Attachment ID (optional).
	 *   - url  (string) Direct file or image URL (optional).
	 *   - text (string) Button label text.
	 *   - class(string) Extra CSS classes.
	 *
	 * @return string HTML for the download button or error message.
	 */
	public function render_button( $atts ) {
		$atts     = shortcode_atts(
			[
				'id'    => '',   // Attachment ID.
				'url'   => '',   // File or image URL.
				'text'  => 'Download File', // Button text.
				'class' => '', // Default CSS class.
			],
			$atts,
			'tsmlt_download_button'
		);
		$file_url = '';
		// Get file URL by ID if provided.
		if ( ! empty( $atts['id'] ) ) {
			$file_url = wp_get_attachment_url( intval( $atts['id'] ) );
		}
		// URL will override ID if both are given.
		if ( ! empty( $atts['url'] ) ) {
			$file_url = esc_url( $atts['url'] );
		}
		// No valid file or image.
		if ( empty( $file_url ) ) {
			return '<p style="color:red;">No valid file or image found.</p>';
		}
		// Return download button.
		return sprintf(
			'<a href="%s" download class="tsmlt-download-btn %s">%s</a>',
			esc_url( $file_url ),
			esc_attr( $atts['class'] ),
			esc_html( $atts['text'] )
		);
	}
}