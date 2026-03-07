<?php
/**
 * AI content generation via third-party LLM providers.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Controllers\AI;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Generates AI-powered content for media attachment fields.
 *
 * Reads settings from tsmlt_settings, loads the attachment file,
 * and dispatches to the configured AI provider (OpenAI, Gemini, or Claude).
 */
class AiApi {

	/**
	 * Valid field types and their corresponding prompts.
	 *
	 * @var array<string, string>
	 */
	private const PROMPTS = [
		'title'       => 'Generate a concise, descriptive title for this image (3–8 words). Return only the title text, no punctuation at the end.',
		'alt_text'    => 'Generate an accurate, descriptive alt text for this image for web accessibility (WCAG). Keep it under 125 characters. Return only the alt text.',
		'caption'     => 'Write a brief, engaging caption for this image (1–2 sentences). Return only the caption.',
		'description' => 'Write a detailed description of this image suitable for a media library. Return only the description.',
		'filename'    => 'Generate a clean, SEO-friendly filename for this image (lowercase letters and hyphens only, no extension, max 50 characters). Return only the filename.',
	];

	/**
	 * Generate AI content for an attachment field.
	 *
	 * @param array $params {
	 *     @type int    $attachment_id  Attachment post ID.
	 *     @type string $field_type     One of: title, alt_text, caption, description, filename.
	 * }
	 *
	 * @return array{text: string}
	 * @throws \Exception On configuration or API errors.
	 */
	public function generate( array $params ): array {
		$attachment_id = absint( $params['attachment_id'] ?? 0 );
		$field_type    = sanitize_key( $params['field_type'] ?? '' );

		if ( ! $attachment_id || ! array_key_exists( $field_type, self::PROMPTS ) ) {
			throw new \Exception( esc_html__( 'Invalid parameters.', 'media-library-tools' ) );
		}

		$settings = get_option( 'tsmlt_settings', [] );

		$provider = $settings['ai_provider'] ?? 'chatgpt';
		$prompt   = self::PROMPTS[ $field_type ];

		// Load the attachment file and detect MIME type.
		$file_path = get_attached_file( $attachment_id );
		$base64    = '';
		$mime      = '';

		if ( $file_path && file_exists( $file_path ) ) {
			$mime = mime_content_type( $file_path );
			if ( ! $mime ) {
				$mime = (string) get_post_mime_type( $attachment_id );
			}
		}

		// Only base64-encode actual image files when the user opts in.
		$send_image = ! empty( $settings['ai_send_image'] );
		$is_image   = $mime && ( 0 === strpos( $mime, 'image/' ) );

		if ( $send_image && $is_image && $file_path && file_exists( $file_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read; no HTTP involved.
			$file_data = file_get_contents( $file_path );
			if ( false !== $file_data ) {
				$base64 = base64_encode( $file_data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by AI vision APIs.
			}
		}

		// Always append text context (site title, filename, attached post) to the prompt.
		$filename        = $file_path ? basename( $file_path ) : get_the_title( $attachment_id );
		$attachment_post = get_post( $attachment_id );
		$attached_title  = ( $attachment_post && $attachment_post->post_parent )
			? get_the_title( $attachment_post->post_parent )
			: '';

		$context  = ' Context:';
		$context .= ' Site title: "' . sanitize_text_field( get_bloginfo( 'name' ) ) . '".';
		$context .= ' Site tagline: "' . sanitize_text_field( get_bloginfo( 'description' ) ) . '".';
		$context .= ' Filename: "' . sanitize_text_field( $filename ) . '".';
		if ( $attached_title ) {
			$context .= ' Attached to post: "' . sanitize_text_field( $attached_title ) . '".';
		}

		$prompt .= $context;

		// Determine suggestion count: user setting capped by the Pro-controlled max.
		$max_count = max( 1, (int) apply_filters( 'tsmlt_ai_max_suggestion_count', 1 ) );
		$count     = min( $max_count, max( 1, (int) ( $settings['ai_suggestion_count'] ?? 1 ) ) );

		if ( $count > 1 ) {
			$prompt .= sprintf(
				' Provide %d different suggestions. Number each one (e.g. "1. suggestion"). Put each suggestion on its own line. Return only the numbered list, nothing else.',
				$count
			);
		}

		switch ( $provider ) {
			case 'gemini':
				$key   = sanitize_text_field( $settings['ai_gemini_key'] ?? '' );
				$model = sanitize_text_field( $settings['ai_gemini_model'] ?? '' ) ?: 'gemini-2.0-flash';
				$text  = $this->call_gemini( $key, $base64, $mime, $prompt, $model );
				break;
			case 'claude':
				$key   = sanitize_text_field( $settings['ai_claude_key'] ?? '' );
				$model = sanitize_text_field( $settings['ai_claude_model'] ?? '' ) ?: 'claude-haiku-4-5-20251001';
				$text  = $this->call_claude( $key, $base64, $mime, $prompt, $model );
				break;
			case 'chatgpt':
			default:
				$key   = sanitize_text_field( $settings['ai_chatgpt_key'] ?? '' );
				$model = sanitize_text_field( $settings['ai_chatgpt_model'] ?? '' ) ?: 'gpt-4o-mini';
				$text  = $this->call_openai( $key, $base64, $mime, $prompt, $model );
				break;
		}

		// Parse numbered suggestions when count > 1, otherwise wrap single text.
		if ( $count > 1 ) {
			$lines       = array_filter( array_map( 'trim', explode( "\n", $text ) ) );
			$suggestions = [];
			foreach ( $lines as $line ) {
				$clean = preg_replace( '/^\d+[\.\)]\s*/', '', $line );
				if ( '' !== $clean ) {
					$suggestions[] = $clean;
				}
			}
			if ( empty( $suggestions ) ) {
				$suggestions = [ $text ];
			}
		} else {
			$suggestions = [ $text ];
		}

		return [ 'suggestions' => $suggestions ];
	}

	// -------------------------------------------------------------------------
	// Private API methods
	// -------------------------------------------------------------------------

	/**
	 * Call the OpenAI GPT-4o-mini API.
	 *
	 * @param string $key    API key.
	 * @param string $base64 Base64-encoded image data (empty string for non-images).
	 * @param string $mime   MIME type of the file.
	 * @param string $prompt Text prompt.
	 *
	 * @return string Generated text.
	 * @throws \Exception On request or API error.
	 */
	private function call_openai( string $key, string $base64, string $mime, string $prompt, string $model ): string {
		if ( empty( $key ) ) {
			throw new \Exception( esc_html__( 'OpenAI API key is not configured.', 'media-library-tools' ) );
		}
		
		$content = [];

		if ( $base64 ) {
			$content[] = [
				'type'      => 'image_url',
				'image_url' => [
					'url'    => 'data:' . $mime . ';base64,' . $base64,
					'detail' => 'low',
				],
			];
		}

		$content[] = [
			'type' => 'text',
			'text' => $prompt,
		];

		$body = wp_json_encode(
			[
				'model'      => $model,
				'messages'   => [
					[
						'role'    => 'user',
						'content' => $content,
					],
				],
				'max_tokens' => 200,
			]
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			[
				'timeout' => 30,
				'headers' => [
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				],
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$err_body = json_decode( wp_remote_retrieve_body( $response ), true );
			$err_msg  = $err_body['error']['message'] ?? '';
			throw new \Exception( sprintf( 'OpenAI HTTP %d: %s', $code, $err_msg ?: 'unknown error' ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = trim( $data['choices'][0]['message']['content'] ?? '' );

		if ( empty( $text ) ) {
			throw new \Exception( esc_html__( 'Empty response from OpenAI.', 'media-library-tools' ) );
		}

		return $text;
	}

	/**
	 * Call the Google Gemini API.
	 *
	 * @param string $key    API key.
	 * @param string $base64 Base64-encoded image data (empty string for non-images).
	 * @param string $mime   MIME type of the file.
	 * @param string $prompt Text prompt.
	 *
	 * @return string Generated text.
	 * @throws \Exception On request or API error.
	 */
	private function call_gemini( string $key, string $base64, string $mime, string $prompt, string $model ): string {
		if ( empty( $key ) ) {
			throw new \Exception( esc_html__( 'Gemini API key is not configured.', 'media-library-tools' ) );
		}

		$parts = [];

		if ( $base64 ) {
			$parts[] = [
				'inline_data' => [
					'mime_type' => $mime,
					'data'      => $base64,
				],
			];
		}

		$parts[] = [ 'text' => $prompt ];

		$body = wp_json_encode(
			[
				'contents' => [
					[
						'parts' => $parts,
					],
				],
			]
		);

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $key );

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 30,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$err_body = json_decode( wp_remote_retrieve_body( $response ), true );
			$err_msg  = $err_body['error']['message'] ?? '';
			throw new \Exception( sprintf( 'Gemini HTTP %d: %s', $code, $err_msg ?: 'unknown error' ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = trim( $data['candidates'][0]['content']['parts'][0]['text'] ?? '' );

		if ( empty( $text ) ) {
			throw new \Exception( esc_html__( 'Empty response from Gemini.', 'media-library-tools' ) );
		}

		return $text;
	}

	/**
	 * Call the Anthropic Claude API.
	 *
	 * @param string $key    API key.
	 * @param string $base64 Base64-encoded image data (empty string for non-images).
	 * @param string $mime   MIME type of the file.
	 * @param string $prompt Text prompt.
	 *
	 * @return string Generated text.
	 * @throws \Exception On request or API error.
	 */
	private function call_claude( string $key, string $base64, string $mime, string $prompt, string $model ): string {
		if ( empty( $key ) ) {
			throw new \Exception( esc_html__( 'Claude API key is not configured.', 'media-library-tools' ) );
		}

		$content = [];

		if ( $base64 ) {
			$content[] = [
				'type'   => 'image',
				'source' => [
					'type'       => 'base64',
					'media_type' => $mime,
					'data'       => $base64,
				],
			];
		}

		$content[] = [
			'type' => 'text',
			'text' => $prompt,
		];

		$body = wp_json_encode(
			[
				'model'      => $model,
				'max_tokens' => 200,
				'messages'   => [
					[
						'role'    => 'user',
						'content' => $content,
					],
				],
			]
		);

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			[
				'timeout' => 30,
				'headers' => [
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				],
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$err_body = json_decode( wp_remote_retrieve_body( $response ), true );
			$err_msg  = $err_body['error']['message'] ?? '';
			throw new \Exception( sprintf( 'Claude HTTP %d: %s', $code, $err_msg ?: 'unknown error' ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = trim( $data['content'][0]['text'] ?? '' );

		if ( empty( $text ) ) {
			throw new \Exception( esc_html__( 'Empty response from Claude.', 'media-library-tools' ) );
		}

		return $text;
	}
}
