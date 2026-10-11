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
	 * OpenAI models that are shut down or scheduled to be, keyed by model ID.
	 * Values are [ shutdown date (Y-m-d), recommended replacement ], taken from
	 * https://developers.openai.com/api/docs/deprecations.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	private const RETIRED_OPENAI_MODELS = [
		'gpt-4o-search-preview'      => [ '2026-07-23', 'gpt-5.6-terra' ],
		'gpt-4o-mini-search-preview' => [ '2026-07-23', 'gpt-5.6-terra' ],
		'gpt-3.5-turbo'              => [ '2026-10-23', 'gpt-5.6-terra' ],
		'gpt-4'                      => [ '2026-10-23', 'gpt-5.6-sol' ],
		'gpt-4-turbo'                => [ '2026-10-23', 'gpt-5.6-sol' ],
		'gpt-4.1-nano'               => [ '2026-10-23', 'gpt-5.6-luna' ],
		'o1'                         => [ '2026-10-23', 'gpt-5.6-sol' ],
		'o1-mini'                    => [ '2025-10-27', 'gpt-5.6-terra' ],
		'o3-mini'                    => [ '2026-10-23', 'gpt-5.6-sol' ],
		'o4-mini'                    => [ '2026-10-23', 'gpt-5.6-terra' ],
		'o3'                         => [ '2026-12-11', 'gpt-5.6-sol' ],
		'gpt-5'                      => [ '2026-12-11', 'gpt-5.6-sol' ],
		'gpt-5-mini'                 => [ '2026-12-11', 'gpt-5.6-terra' ],
		'gpt-5-nano'                 => [ '2026-12-11', 'gpt-5.6-luna' ],
		'gpt-5.1'                    => [ '2027-04-01', 'gpt-6-sol' ],
	];

	/**
	 * Lowest reasoning effort each OpenAI reasoning model accepts. Metadata
	 * generation needs no deliberation, and every token spent reasoning counts
	 * against max_completion_tokens and is billed as output. GPT-6 Astra and
	 * GPT-6.1 Sol reject "none". An empty value marks a reasoning model whose
	 * accepted efforts OpenAI does not document: no effort is sent (a wrong
	 * value fails every request), but its reasoning still gets token headroom.
	 * Models not listed (GPT-4o, GPT-4.1, or unknown IDs) are treated as
	 * non-reasoning, which do not accept reasoning_effort.
	 *
	 * @var array<string, string>
	 */
	private const OPENAI_REASONING_EFFORT = [
		'gpt-6-astra'   => 'low',
		'gpt-6.1-sol'   => 'low',
		'gpt-6-sol'     => 'none',
		'gpt-6-luna'    => 'none',
		'gpt-5.6-sol'   => 'none',
		'gpt-5.6-terra' => 'none',
		'gpt-5.6-luna'  => 'none',
		'gpt-5.5'       => 'none',
		// Legacy models still served until their shutdown dates.
		'gpt-5.1'       => 'none',
		'gpt-5'         => 'minimal',
		'gpt-5-mini'    => '',
		'gpt-5-nano'    => '',
		'o1'            => '',
		'o3'            => '',
		'o3-mini'       => '',
		'o4-mini'       => '',
	];

	/**
	 * Claude models that are retired or scheduled to be, keyed by model ID.
	 * Values are [ retirement date (Y-m-d), replacement ], from
	 * https://platform.claude.com/docs/en/about-claude/model-deprecations.
	 * Sonnet 4.5 maps to Sonnet 4.6 rather than Anthropic's suggested Sonnet
	 * 5.5: same price, already offered here, and it does not think by default,
	 * so the request format and max_tokens budget stay valid.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	private const RETIRED_CLAUDE_MODELS = [
		'claude-sonnet-4-5'          => [ '2026-11-30', 'claude-sonnet-4-6' ],
		'claude-sonnet-4-5-20250929' => [ '2026-11-30', 'claude-sonnet-4-6' ],
	];

	/**
	 * Gemini finish reasons that still yield a complete, usable reply.
	 *
	 * @var string[]
	 */
	private const GEMINI_COMPLETE_FINISH_REASONS = [ 'STOP', 'FINISH_REASON_UNSPECIFIED' ];

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
	 * One-off instruction supplied with the current request, typed by the user in
	 * the AI suggestions popup. Applies to a single generation for a single image
	 * and is deliberately never persisted — per-image wording is usually a
	 * throwaway nudge ("emphasise the grey fabric"), so storing it would add a
	 * meta row per attachment, a CSV column, and uninstall cleanup for no gain.
	 *
	 * @var string
	 */
	private string $request_instruction = '';

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
	 * Build the shared "Context:" prompt fragment for an attachment.
	 *
	 * Extracted so single-field and bulk generation describe an image to the model
	 * identically — the context is what grounds the output in the actual site, so
	 * the two paths must not drift apart.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $file_path     Absolute path to the attached file, if known.
	 *
	 * @return string Leading-space prefixed context fragment.
	 */
	private function build_context( int $attachment_id, $file_path ): string {
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
			$parent         = get_post( $attachment_post->post_parent );
			$parent_title   = $parent ? get_the_title( $parent ) : '';
			$parent_type    = $parent ? $parent->post_type : '';
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

		return $context;
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

		// Reset per-request state: this class is a singleton, so a value left over
		// from an earlier generation would otherwise leak into the next one.
		$this->request_instruction = trim( sanitize_textarea_field( (string) ( $params['instruction'] ?? '' ) ) );

		$settings = get_option( 'tsmlt_settings', [] );

		$provider = $settings['ai_provider'] ?? 'gemini';
		$prompt   = self::PROMPTS[ $field_type ];

		// Filename is used in the text context (WP metadata lookup, no file I/O).
		$file_path = get_attached_file( $attachment_id );

		// Pro plugin loads the file, detects MIME, and calls set_image_data() on this instance.
		do_action( 'tsmlt_ai_prepare_image', $this, $attachment_id, $settings, $file_path );

		// Always append text context to the prompt.
		$prompt .= $this->build_context( $attachment_id, $file_path );

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

		// User instructions, appended in order of increasing specificity so the
		// narrowest one lands last and carries the most weight:
		//   global  -> legacy: applies to every field, no longer has a settings
		//              field but still honoured where one was saved previously
		//   field   -> applies to this field on every image
		//   request -> one-off, typed in the AI popup for this generation only
		// Each is optional; blank layers are skipped entirely.
		// Custom instructions are a Pro feature. Gated here rather than only in the
		// UI so a crafted request cannot use them without a licence.
		$instructions = [];
		if ( tsmlt()->has_pro() ) {
			$instructions = [
				trim( (string) ( $settings['ai_custom_instruction'] ?? '' ) ),
				trim( (string) ( $settings[ 'ai_instruction_' . $field_type ] ?? '' ) ),
				// Not persisted: sanitised from the request in generate() and never stored.
				$this->request_instruction,
			];
		}

		foreach ( $instructions as $instruction ) {
			if ( '' !== $instruction ) {
				$prompt .= ' ' . $instruction;
			}
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

		$text = $this->dispatch_to_provider( $provider, $prompt, $settings );

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

	/**
	 * Read a provider's API key from encrypted storage.
	 *
	 * An unreadable saved key stops generation here: nothing is sent to the
	 * provider, and the error says how to recover.
	 *
	 * @param string $provider Provider key (chatgpt, gemini, claude).
	 *
	 * @return string API key, or '' when none is configured.
	 * @throws \Exception When a saved key cannot be decrypted.
	 */
	private static function provider_key( string $provider ): string {
		$resolved = Credentials::resolve( $provider );

		if ( '' === $resolved['key'] && Credentials::STATUS_UNREADABLE === $resolved['status'] ) {
			throw new \Exception(
				esc_html(
					sprintf(
						/* translators: %s: provider name, e.g. "Gemini". */
						__( 'Your saved %s API key can\'t be read, usually because this site\'s WordPress security keys changed. Re-enter it in Media Library Tools → Settings → AI.', 'media-library-tools' ),
						CredentialHooks::provider_label( $provider )
					)
				)
			);
		}

		return $resolved['key'];
	}

	/**
	 * Swap a saved model for its replacement once the provider has retired it.
	 *
	 * The swap happens from the start of the retirement date in UTC, never
	 * earlier, so a model that still works (and its pricing) is left alone.
	 * Providers publish dates without a time of day; switching at 00:00 UTC
	 * means a request may move a few hours early, but never fails late.
	 *
	 * @param string                                     $model   Saved model ID.
	 * @param array<string, array{0: string, 1: string}> $retired Model ID => [ date, replacement ].
	 *
	 * @return string Model ID to send.
	 */
	private static function resolve_retired_model( string $model, array $retired ): string {
		if ( ! isset( $retired[ $model ] ) ) {
			return $model;
		}

		list( $retirement_date, $replacement ) = $retired[ $model ];

		return gmdate( 'Y-m-d' ) >= $retirement_date ? $replacement : $model;
	}

	/**
	 * Send a prompt to the configured provider and return the raw text reply.
	 *
	 * @param string $provider Provider key (gemini, claude, chatgpt).
	 * @param string $prompt   Complete prompt text.
	 * @param array  $settings Plugin settings array.
	 *
	 * @return string Raw model output.
	 * @throws \Exception On configuration or API errors.
	 */
	private function dispatch_to_provider( string $provider, string $prompt, array $settings ): string {
		switch ( $provider ) {
			case 'gemini':
				$key   = self::provider_key( 'gemini' );
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
				return $this->call_gemini( $key, $prompt, $model );

			case 'claude':
				$key   = self::provider_key( 'claude' );
				$model = sanitize_text_field( $settings['ai_claude_model'] ?? '' ) ?: 'claude-haiku-4-5-20251001';
				$model = self::resolve_retired_model( $model, self::RETIRED_CLAUDE_MODELS );
				return $this->call_claude( $key, $prompt, $model );

			case 'chatgpt':
			default:
				$key   = self::provider_key( 'chatgpt' );
				$model = sanitize_text_field( $settings['ai_chatgpt_model'] ?? '' ) ?: 'gpt-6-luna';
				$model = self::resolve_retired_model( $model, self::RETIRED_OPENAI_MODELS );
				return $this->call_openai( $key, $prompt, $model );
		}
	}

	/**
	 * Generate several metadata fields for one attachment in a single API call.
	 *
	 * Bulk runs would otherwise issue one request per field — four calls per image,
	 * so 4,000 for a 1,000-image library. Asking for a JSON object instead cuts
	 * that to one call per image while reusing the same context, language and
	 * instruction layers as `generate()`.
	 *
	 * Returns only the fields the model actually supplied, so a partial reply
	 * yields partial results rather than failing the whole item.
	 *
	 * @param int      $attachment_id Attachment post ID.
	 * @param string[] $fields        Requested field keys (title, alt_text, caption, description, filename).
	 *
	 * @return array<string, string> Field key => generated value.
	 * @throws \Exception On configuration, API, or unparseable-response errors.
	 */
	public function generate_bulk( int $attachment_id, array $fields ): array {
		// Bulk generation is a Pro capability. Checked here as well as in the job
		// that drives it, so this public method cannot spend a site's API budget
		// without a licence however it is reached.
		if ( ! tsmlt()->has_pro() ) {
			throw new \Exception( esc_html__( 'Bulk AI generation requires Pro.', 'media-library-tools' ) );
		}

		$fields = array_values( array_intersect( array_keys( self::PROMPTS ), $fields ) );

		if ( ! $attachment_id || empty( $fields ) ) {
			throw new \Exception( esc_html__( 'Invalid parameters.', 'media-library-tools' ) );
		}

		// Bulk generation never carries a one-off instruction; reset so a value
		// left by an earlier single-image call cannot leak into the job.
		$this->request_instruction = '';

		$settings  = get_option( 'tsmlt_settings', [] );
		$provider  = $settings['ai_provider'] ?? 'gemini';
		$file_path = get_attached_file( $attachment_id );

		// Pro plugin loads the file, detects MIME, and calls set_image_data().
		do_action( 'tsmlt_ai_prepare_image', $this, $attachment_id, $settings, $file_path );

		// One instruction line per requested field, reusing the single-field
		// wording so bulk output matches what the same field produces alone.
		$prompt = 'Generate metadata for this image. Follow the requirement for each field:';
		foreach ( $fields as $field ) {
			$prompt .= "\n- \"" . $field . '": ' . self::PROMPTS[ $field ];
		}

		$prompt .= $this->build_context( $attachment_id, $file_path );

		$language = self::resolve_language( $settings['ai_language'] ?? '' );
		if ( '' !== $language ) {
			$non_filename = array_diff( $fields, [ 'filename' ] );
			if ( ! empty( $non_filename ) ) {
				$prompt .= sprintf(
					' Write the %s values in %s.',
					implode( ', ', $non_filename ),
					$language
				);
			}
			if ( in_array( 'filename', $fields, true ) ) {
				// Filenames stay ASCII-safe regardless of the content language.
				$prompt .= sprintf(
					' Base the filename on %1$s wording, but transliterate it to plain ASCII (for example, ä becomes ae, ß becomes ss). Use only lowercase a-z and hyphens.',
					$language
				);
			}
		}

		// Custom instructions are Pro-only, matching generate(). Per-field wording
		// is labelled so one combined prompt keeps each field's guidance distinct.
		if ( tsmlt()->has_pro() ) {
			$global = trim( (string) ( $settings['ai_custom_instruction'] ?? '' ) );
			if ( '' !== $global ) {
				$prompt .= ' ' . $global;
			}
			foreach ( $fields as $field ) {
				$field_instruction = trim( (string) ( $settings[ 'ai_instruction_' . $field ] ?? '' ) );
				if ( '' !== $field_instruction ) {
					$prompt .= ' For "' . $field . '": ' . $field_instruction;
				}
			}
		}

		// Strict output contract — the reply is machine-read, so no prose.
		$prompt .= ' Respond with a single minified JSON object and nothing else.'
			. ' Use exactly these keys: "' . implode( '", "', $fields ) . '".'
			. ' Every value must be a plain string. Do not wrap the JSON in code fences.';

		/**
		 * Filter the final bulk prompt sent to the AI provider.
		 *
		 * @param string   $prompt        Complete prompt text.
		 * @param string[] $fields        Requested field keys.
		 * @param int      $attachment_id Attachment post ID.
		 * @param array    $settings      Plugin settings array.
		 */
		$prompt = (string) apply_filters( 'tsmlt_ai_bulk_prompt', $prompt, $fields, $attachment_id, $settings );

		$text = $this->dispatch_to_provider( $provider, $prompt, $settings );

		return $this->parse_bulk_response( $text, $fields );
	}

	/**
	 * Decode a bulk JSON reply into sanitised field values.
	 *
	 * Models occasionally wrap JSON in code fences or add a sentence around it,
	 * so the outermost brace pair is extracted before decoding rather than
	 * trusting the response to be clean.
	 *
	 * @param string   $text   Raw model output.
	 * @param string[] $fields Requested field keys.
	 *
	 * @return array<string, string> Field key => generated value.
	 * @throws \Exception When no usable JSON object is present.
	 */
	private function parse_bulk_response( string $text, array $fields ): array {
		$json  = trim( $text );
		$start = strpos( $json, '{' );
		$end   = strrpos( $json, '}' );

		if ( false === $start || false === $end || $end <= $start ) {
			throw new \Exception( esc_html__( 'The AI response could not be read.', 'media-library-tools' ) );
		}

		$decoded = json_decode( substr( $json, $start, $end - $start + 1 ), true );

		if ( ! is_array( $decoded ) ) {
			throw new \Exception( esc_html__( 'The AI response could not be read.', 'media-library-tools' ) );
		}

		$results = [];
		foreach ( $fields as $field ) {
			if ( ! isset( $decoded[ $field ] ) || ! is_scalar( $decoded[ $field ] ) ) {
				continue;
			}

			$value = trim( (string) $decoded[ $field ] );
			if ( '' === $value ) {
				continue;
			}

			$results[ $field ] = 'description' === $field
				? wp_kses_post( $value )
				: sanitize_text_field( $value );
		}

		if ( empty( $results ) ) {
			throw new \Exception( esc_html__( 'The AI response contained no usable values.', 'media-library-tools' ) );
		}

		return $results;
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

		$request = [
			'model'                 => $model,
			'messages'              => [
				[
					'role'    => 'user',
					'content' => $content,
				],
			],
			// max_tokens is deprecated and rejected by reasoning models;
			// max_completion_tokens is accepted by every chat model. It is a
			// ceiling, not a charge: only generated tokens are billed. 2,000
			// covers Pro's ten suggestions of a 2-4 sentence description, or a
			// five-field bulk JSON object, including non-Latin scripts.
			'max_completion_tokens' => 2000,
		];

		// Snapshot IDs (e.g. gpt-5.5-2026-04-23) share their alias's settings.
		$base_model = preg_replace( '/-\d{4}-\d{2}-\d{2}$/', '', $model );
		if ( isset( self::OPENAI_REASONING_EFFORT[ $base_model ] ) ) {
			$effort = self::OPENAI_REASONING_EFFORT[ $base_model ];
			if ( '' !== $effort ) {
				$request['reasoning_effort'] = $effort;
			}
			if ( 'none' !== $effort ) {
				// Reasoning tokens share max_completion_tokens with the answer;
				// leave room so reasoning cannot crowd out the visible reply.
				$request['max_completion_tokens'] += 6000;
			}
		}

		$body = wp_json_encode( $request );

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

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$choice  = $data['choices'][0] ?? [];
		$refusal = trim( (string) ( $choice['message']['refusal'] ?? '' ) );
		$text    = trim( (string) ( $choice['message']['content'] ?? '' ) );
		$finish  = $choice['finish_reason'] ?? '';

		if ( '' !== $refusal ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped via esc_html().
			throw new \Exception( esc_html( sprintf( 'OpenAI declined the request: %s', $refusal ) ) );
		}

		if ( 'content_filter' === $finish ) {
			throw new \Exception( esc_html__( 'OpenAI withheld the response because of its content filter.', 'media-library-tools' ) );
		}

		// A reply cut off at the token limit is incomplete: the last suggestion
		// or the bulk JSON would be truncated, so fail rather than save it.
		if ( 'length' === $finish ) {
			throw new \Exception( esc_html__( 'OpenAI stopped before finishing the response (token limit reached). Try fewer suggestions or a different model.', 'media-library-tools' ) );
		}

		if ( '' === $text ) {
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

		// No maxOutputTokens: Gemini then allows the model's full output limit
		// (65,536 on current models), which also covers thinking tokens, so a
		// metadata reply is never cut short by a cap set here.
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent';

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 30,
				// The key goes in a header, not the query string, so it never
				// appears in a URL that proxies or HTTP-logging plugins record.
				'headers' => [
					'x-goog-api-key' => $key,
					'Content-Type'   => 'application/json',
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
			throw new \Exception( esc_html( sprintf( 'Gemini HTTP %d: %s', $code, $err_msg ?: 'unknown error' ) ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		// A blocked prompt returns no candidates, only promptFeedback.
		$block_reason = (string) ( $data['promptFeedback']['blockReason'] ?? '' );
		if ( '' !== $block_reason ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped via esc_html().
			throw new \Exception( esc_html( sprintf( 'Gemini blocked the request (%s).', $block_reason ) ) );
		}

		$candidate = $data['candidates'][0] ?? [];
		$finish    = (string) ( $candidate['finishReason'] ?? '' );

		// MAX_TOKENS means a truncated reply; SAFETY, RECITATION and the other
		// reasons mean content was withheld. Neither may be saved as a result.
		if ( '' !== $finish && ! in_array( $finish, self::GEMINI_COMPLETE_FINISH_REASONS, true ) ) {
			$message = 'MAX_TOKENS' === $finish
				? __( 'Gemini stopped before finishing the response (token limit reached). Try fewer suggestions or a different model.', 'media-library-tools' )
				/* translators: %s: Gemini finish reason code, e.g. SAFETY. */
				: sprintf( __( 'Gemini did not complete the response (%s).', 'media-library-tools' ), $finish );
			throw new \Exception( esc_html( $message ) );
		}

		$text = self::join_gemini_text( (array) ( $candidate['content']['parts'] ?? [] ) );

		if ( '' === $text ) {
			throw new \Exception( esc_html__( 'Empty response from Gemini.', 'media-library-tools' ) );
		}

		return $text;
	}

	/**
	 * Join the answer text from a Gemini candidate's parts.
	 *
	 * A reply can span several text parts, and thinking models may add thought
	 * parts (flagged `thought: true`) or signature-only parts; only answer text
	 * is kept.
	 *
	 * @param array $parts Candidate content parts.
	 *
	 * @return string Trimmed answer text.
	 */
	private static function join_gemini_text( array $parts ): string {
		$text = '';
		foreach ( $parts as $part ) {
			if ( ! is_array( $part ) || ! empty( $part['thought'] ) || ! isset( $part['text'] ) ) {
				continue;
			}
			$text .= (string) $part['text'];
		}

		return trim( $text );
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
				// A ceiling, not a charge: only generated tokens are billed. 2,000
				// covers Pro's ten suggestions of a 2-4 sentence description, or a
				// five-field bulk JSON object, including non-Latin scripts. The
				// offered models do not think unless asked, so it is all answer.
				'max_tokens' => 2000,
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

		$data        = json_decode( wp_remote_retrieve_body( $response ), true );
		$stop_reason = (string) ( $data['stop_reason'] ?? '' );

		if ( 'refusal' === $stop_reason ) {
			throw new \Exception( esc_html__( 'Claude declined the request.', 'media-library-tools' ) );
		}

		// A reply cut off at max_tokens is incomplete: the last suggestion or the
		// bulk JSON would be truncated, so fail rather than save it.
		if ( 'max_tokens' === $stop_reason ) {
			throw new \Exception( esc_html__( 'Claude stopped before finishing the response (token limit reached). Try fewer suggestions or a different model.', 'media-library-tools' ) );
		}

		// Content is a list of typed blocks; only text blocks carry the answer,
		// and a reply may be split across several of them.
		$text = '';
		foreach ( (array) ( $data['content'] ?? [] ) as $block ) {
			if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) ) {
				$text .= (string) ( $block['text'] ?? '' );
			}
		}
		$text = trim( $text );

		if ( '' === $text ) {
			throw new \Exception( esc_html__( 'Empty response from Claude.', 'media-library-tools' ) );
		}

		return $text;
	}
}
