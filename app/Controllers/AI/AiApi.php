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
		'title'       => 'Generate a concise, descriptive media title for this image. Use title case. Focus on the main subject or scene. The title should be relevant to the site and its content. Between 3 and 8 words. No trailing punctuation. Return only the title text, nothing else.',
		'alt_text'    => 'Write accurate, SEO-friendly alt text for this image. Describe the image content and context naturally while incorporating relevant keywords that reflect both the subject and the site context. Follow WCAG 2.1 accessibility guidelines so it is meaningful for screen readers. Do not start with "image of", "picture of", or similar phrases. Maximum 125 characters. Return only the alt text, nothing else.',
		'caption'     => 'Write a short, engaging caption for this image. Describe what is happening or shown, and add context relevant to the site and its content. 1 to 2 sentences maximum. No hashtags. Return only the caption text, nothing else.',
		'description' => 'Write a detailed, SEO-friendly description of this image for a WordPress media library. Describe the main subject, visual elements, mood, and any relevant context. Incorporate keywords relevant to the site and the content the image is associated with. Use natural language. 2 to 4 sentences. Return only the description text, nothing else.',
		'filename'    => 'Generate an SEO-friendly filename for this image. Use descriptive keywords that reflect the main subject and are relevant to the site context. Use only lowercase letters and hyphens — no spaces, underscores, numbers, or special characters. Maximum 50 characters. No file extension. Return only the filename, nothing else.',
	];

	/**
	 * Language names keyed by WordPress locale prefix, used to turn the site
	 * locale into wording the model reliably understands.
	 *
	 * @var array<string, string>
	 */
	private const LOCALE_LANGUAGES = [
		'de' => 'German',
		'fr' => 'French',
		'es' => 'Spanish',
		'it' => 'Italian',
		'nl' => 'Dutch',
		'pt' => 'Portuguese',
		'pl' => 'Polish',
		'sv' => 'Swedish',
		'da' => 'Danish',
		'nb' => 'Norwegian',
		'nn' => 'Norwegian',
		'fi' => 'Finnish',
		'cs' => 'Czech',
		'sk' => 'Slovak',
		'hu' => 'Hungarian',
		'ro' => 'Romanian',
		'el' => 'Greek',
		'tr' => 'Turkish',
		'ru' => 'Russian',
		'uk' => 'Ukrainian',
		'ar' => 'Arabic',
		'he' => 'Hebrew',
		'hi' => 'Hindi',
		'bn' => 'Bengali',
		'id' => 'Indonesian',
		'vi' => 'Vietnamese',
		'th' => 'Thai',
		'ja' => 'Japanese',
		'ko' => 'Korean',
		'zh' => 'Chinese',
		'en' => 'English',
	];

	/**
	 * Base64-encoded image data set by the Pro plugin via set_image_data().
	 * Empty string when Pro is not active or image sending is disabled.
	 *
	 * @var string
	 */
	private string $image_base64 = '';

	/**
	 * MIME type of the attachment image (set alongside $image_base64).
	 *
	 * @var string
	 */
	private string $image_mime = '';

	/**
	 * Called by the Pro plugin to supply image data for vision API calls.
	 *
	 * @param string $base64 Base64-encoded image data.
	 * @param string $mime   MIME type of the image.
	 *
	 * @return void
	 */
	public function set_image_data( string $base64, string $mime ): void {
		$this->image_base64 = $base64;
		$this->image_mime   = $mime;
	}

	/**
	 * Resolve the configured language setting into a language name for the prompt.
	 *
	 * An empty setting means "site default", which maps the WordPress locale to a
	 * language name. English resolves to an empty string because the built-in
	 * prompts are already English and need no extra instruction.
	 *
	 * @param string $setting Stored ai_language value ('' for site default, or a language name).
	 *
	 * @return string Language name, or empty string when no instruction is needed.
	 */
	private static function resolve_language( string $setting ): string {
		$setting = trim( $setting );

		if ( '' !== $setting ) {
			return 'English' === $setting ? '' : $setting;
		}

		// Site default: derive from the locale, e.g. de_DE / de_DE_formal -> German.
		$locale = (string) apply_filters( 'tsmlt_ai_locale', get_locale() );
		$prefix = strtolower( substr( $locale, 0, 2 ) );

		if ( ! isset( self::LOCALE_LANGUAGES[ $prefix ] ) || 'en' === $prefix ) {
			return '';
		}

		return self::LOCALE_LANGUAGES[ $prefix ];
	}

	/**
	 * Generate AI content for an attachment field.
	 *
	 * @param array $params {
	 *     @type int    $attachment_id  Attachment post ID.
	 *     @type string $field_type     One of: title, alt_text, caption, description, filename.
	 * }
	 *
	 * @return array{suggestions: string[]}
	 * @throws \Exception On configuration or API errors.
	 */
	public function generate( array $params ): array {
		$attachment_id = absint( $params['attachment_id'] ?? 0 );
		$field_type    = sanitize_key( $params['field_type'] ?? '' );

		if ( ! $attachment_id || ! array_key_exists( $field_type, self::PROMPTS ) ) {
			throw new \Exception( esc_html__( 'Invalid parameters.', 'media-library-tools' ) );
		}

		$settings = get_option( 'tsmlt_settings', [] );

		$provider = $settings['ai_provider'] ?? 'gemini';
		$prompt   = self::PROMPTS[ $field_type ];

		// Filename is used in the text context (WP metadata lookup, no file I/O).
		$file_path = get_attached_file( $attachment_id );

		// Pro plugin loads the file, detects MIME, and calls set_image_data() on this instance.
		do_action( 'tsmlt_ai_prepare_image', $this, $attachment_id, $settings, $file_path );

		// Always append text context to the prompt.
		$filename        = $file_path ? basename( $file_path ) : '';
		$attachment_post = get_post( $attachment_id );

		// Existing attachment metadata.
		$current_title   = $attachment_post ? $attachment_post->post_title : '';
		$current_alt     = get_post_meta( $attachment_id, '_wp_attachment_alt', true );
		$current_caption = $attachment_post ? $attachment_post->post_excerpt : '';

		// Parent post context.
		$parent_title   = '';
		$parent_type    = '';
		$parent_excerpt = '';
		if ( $attachment_post && $attachment_post->post_parent ) {
			$parent        = get_post( $attachment_post->post_parent );
			$parent_title  = $parent ? get_the_title( $parent ) : '';
			$parent_type   = $parent ? $parent->post_type : '';
			$parent_excerpt = $parent ? wp_trim_words( wp_strip_all_tags( $parent->post_content ), 30, '...' ) : '';
		}

		$context  = ' Context:';
		$context .= ' Site: "' . sanitize_text_field( get_bloginfo( 'name' ) ) . '".';
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- local variable inside namespaced class.
		$site_desc = sanitize_text_field( get_bloginfo( 'description' ) );
		if ( $site_desc ) {
			$context .= ' Tagline: "' . $site_desc . '".';
		}
		if ( $filename ) {
			$context .= ' Filename: "' . sanitize_text_field( $filename ) . '".';
		}
		if ( $current_title ) {
			$context .= ' Current title: "' . sanitize_text_field( $current_title ) . '".';
		}
		if ( $current_alt ) {
			$context .= ' Current alt text: "' . sanitize_text_field( $current_alt ) . '".';
		}
		if ( $current_caption ) {
			$context .= ' Current caption: "' . sanitize_text_field( $current_caption ) . '".';
		}
		if ( $parent_title ) {
			$context .= ' Attached to ' . sanitize_text_field( $parent_type ) . ': "' . sanitize_text_field( $parent_title ) . '".';
		}
		if ( $parent_excerpt ) {
			$context .= ' Parent content summary: "' . sanitize_text_field( $parent_excerpt ) . '".';
		}

		$prompt .= $context;

		// Free: 1 real suggestion (frontend pads with placeholders). Pro: user setting (5–max).
		$max_count = max( 1, (int) apply_filters( 'tsmlt_ai_max_suggestion_count', 1 ) );
		$count     = $max_count > 1
			? min( $max_count, max( 5, (int) ( $settings['ai_suggestion_count'] ?? 5 ) ) )
			: 1;

		$prompt .= sprintf(
			' Provide %d different suggestions. Number each one (e.g. "1. suggestion"). Put each suggestion on its own line. Return only the numbered list, nothing else.',
			$count
		);

		// Output language. Empty means "site default", resolved from the WordPress
		// locale so a German site produces German output without configuration.
		$language = self::resolve_language( $settings['ai_language'] ?? '' );
		if ( '' !== $language ) {
			if ( 'filename' === $field_type ) {
				// Filenames stay ASCII-safe: non-ASCII characters break on some
				// servers, so ask for transliteration rather than native script.
				$prompt .= sprintf(
					' Base the filename on %1$s wording, but transliterate it to plain ASCII (for example, ä becomes ae, ß becomes ss). Use only lowercase a-z and hyphens.',
					$language
				);
			} else {
				$prompt .= sprintf( ' Write every suggestion in %s.', $language );
			}
		}

		// User instruction is appended last so it takes precedence over the
		// built-in wording above.
		$custom_instruction = trim( (string) ( $settings['ai_custom_instruction'] ?? '' ) );
		if ( '' !== $custom_instruction ) {
			$prompt .= ' ' . $custom_instruction;
		}

		/**
		 * Filter the final prompt sent to the AI provider.
		 *
		 * @param string $prompt        Complete prompt text.
		 * @param string $field_type    Field being generated (title, alt_text, caption, description, filename).
		 * @param int    $attachment_id Attachment post ID.
		 * @param array  $settings      Plugin settings array.
		 */
		$prompt = (string) apply_filters( 'tsmlt_ai_prompt', $prompt, $field_type, $attachment_id, $settings );

		switch ( $provider ) {
			case 'gemini':
				$key   = sanitize_text_field( $settings['ai_gemini_key'] ?? '' );
				$model = sanitize_text_field( $settings['ai_gemini_model'] ?? '' ) ?: 'gemini-2.5-flash';
				// Retired Gemini models return HTTP 404 on the generateContent endpoint;
				// transparently map saved settings to a currently supported equivalent.
				$retired_gemini_models = [
					'gemini-2.0-flash'      => 'gemini-2.5-flash',
					'gemini-2.0-flash-lite' => 'gemini-2.5-flash-lite',
					'gemini-1.5-flash'      => 'gemini-2.5-flash',
					'gemini-1.5-pro'        => 'gemini-2.5-pro',
					'gemini-1.0-pro'        => 'gemini-2.5-pro',
					'gemini-pro'            => 'gemini-2.5-pro',
				];
				if ( isset( $retired_gemini_models[ $model ] ) ) {
					$model = $retired_gemini_models[ $model ];
				}
				$text = $this->call_gemini( $key, $prompt, $model );
				break;
			case 'claude':
				$key   = sanitize_text_field( $settings['ai_claude_key'] ?? '' );
				$model = sanitize_text_field( $settings['ai_claude_model'] ?? '' ) ?: 'claude-haiku-4-5-20251001';
				$text  = $this->call_claude( $key, $prompt, $model );
				break;
			case 'chatgpt':
			default:
				$key   = sanitize_text_field( $settings['ai_chatgpt_key'] ?? '' );
				$model = sanitize_text_field( $settings['ai_chatgpt_model'] ?? '' ) ?: 'gpt-4o-mini';
				$text  = $this->call_openai( $key, $prompt, $model );
				break;
		}

		// Parse numbered suggestions.
		$lines       = array_filter( array_map( 'trim', explode( "\n", $text ) ) );
		$suggestions = [];
		foreach ( $lines as $line ) {
			$clean = preg_replace( '/^\d+[.)]\s*/', '', $line );
			if ( '' !== $clean ) {
				$suggestions[] = $clean;
			}
		}
		if ( empty( $suggestions ) ) {
			$suggestions = [ $text ];
		}

		return [ 'suggestions' => $suggestions ];
	}

	// -------------------------------------------------------------------------
	// Private API methods
	// -------------------------------------------------------------------------

	/**
	 * Call the OpenAI API.
	 *
	 * @param string $key    API key.
	 * @param string $prompt Text prompt.
	 * @param string $model  Model ID.
	 *
	 * @return string Generated text.
	 * @throws \Exception On request or API error.
	 */
	private function call_openai( string $key, string $prompt, string $model ): string {
		if ( empty( $key ) ) {
			throw new \Exception( esc_html__( 'OpenAI API key is not configured.', 'media-library-tools' ) );
		}

		// Pro plugin injects the image block via filter; free returns empty array.
		$content   = (array) apply_filters( 'tsmlt_ai_openai_image_content', [], $this->image_base64, $this->image_mime );
		$content[] = [ 'type' => 'text', 'text' => $prompt ];

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
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$err_body = json_decode( wp_remote_retrieve_body( $response ), true );
			$err_msg  = $err_body['error']['message'] ?? '';
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped via esc_html().
			throw new \Exception( esc_html( sprintf( 'OpenAI HTTP %d: %s', $code, $err_msg ?: 'unknown error' ) ) );
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
	 * @param string $prompt Text prompt.
	 * @param string $model  Model ID.
	 *
	 * @return string Generated text.
	 * @throws \Exception On request or API error.
	 */
	private function call_gemini( string $key, string $prompt, string $model ): string {
		if ( empty( $key ) ) {
			throw new \Exception( esc_html__( 'Gemini API key is not configured.', 'media-library-tools' ) );
		}

		// Pro plugin injects the image part via filter; free returns empty array.
		$parts   = (array) apply_filters( 'tsmlt_ai_gemini_image_parts', [], $this->image_base64, $this->image_mime );
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
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$err_body = json_decode( wp_remote_retrieve_body( $response ), true );
			$err_msg  = $err_body['error']['message'] ?? '';
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped via esc_html().
			throw new \Exception( esc_html( sprintf( 'Gemini HTTP %d: %s', $code, $err_msg ?: 'unknown error' ) ) );
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
	 * @param string $prompt Text prompt.
	 * @param string $model  Model ID.
	 *
	 * @return string Generated text.
	 * @throws \Exception On request or API error.
	 */
	private function call_claude( string $key, string $prompt, string $model ): string {
		if ( empty( $key ) ) {
			throw new \Exception( esc_html__( 'Claude API key is not configured.', 'media-library-tools' ) );
		}

		// Pro plugin injects the image block via filter; free returns empty array.
		$content   = (array) apply_filters( 'tsmlt_ai_claude_image_content', [], $this->image_base64, $this->image_mime );
		$content[] = [ 'type' => 'text', 'text' => $prompt ];

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
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$err_body = json_decode( wp_remote_retrieve_body( $response ), true );
			$err_msg  = $err_body['error']['message'] ?? '';
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped via esc_html().
			throw new \Exception( esc_html( sprintf( 'Claude HTTP %d: %s', $code, $err_msg ?: 'unknown error' ) ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = trim( $data['content'][0]['text'] ?? '' );

		if ( empty( $text ) ) {
			throw new \Exception( esc_html__( 'Empty response from Claude.', 'media-library-tools' ) );
		}

		return $text;
	}
}
