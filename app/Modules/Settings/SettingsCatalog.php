<?php
/**
 * Read-only catalogue of the plugin settings MCP may report.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Settings;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Controllers\AI\Credentials;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Compress\CompressionSettings;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * An explicit allowlist of non-sensitive settings from `tsmlt_settings`, with
 * each one's group, label, type, default and accepted values, and the
 * current value normalised to that definition (the admin save stores some
 * values unchecked). Free defines its own settings; Media Library Tools Pro
 * adds its own through DEFINITIONS_FILTER, applied only while Pro is active.
 *
 * Nothing here writes anything. API keys, credentials, the MCP access switch,
 * uninstall behaviour, file-type support and internal state are never
 * defined, and EXCLUDED_KEYS / is_secret_name() refuse them from the filter.
 */
class SettingsCatalog {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Filter through which Pro adds its definitions: array key => definition.
	 *
	 * @var string
	 */
	const DEFINITIONS_FILTER = 'tsmlt_mcp_settings_definitions';

	/**
	 * Setting groups.
	 *
	 * @var string[]
	 */
	const GROUPS = [ 'media', 'defaults', 'exif', 'compression', 'conversion', 'ai', 'rename', 'frontend' ];

	/**
	 * Value types.
	 *
	 * @var string[]
	 */
	const TYPES = [ 'integer', 'boolean', 'string', 'text', 'enum', 'list' ];

	/**
	 * Keys that are never reported, whoever defines them.
	 *
	 * @var string[]
	 */
	const EXCLUDED_KEYS = [
		'ai_chatgpt_key',
		'ai_gemini_key',
		'ai_claude_key',
		'tsmlt_ai_credentials',
		'tsmlt_credentials_version',
		'tsmlt_mcp_access_disabled',
		'delete_data_on_uninstall',
		'others_file_support',
		'custom_image_sizes',
		'media_table_column',
	];

	/**
	 * Longest string reported for a one-line value.
	 *
	 * @var int
	 */
	const MAX_STRING = 1000;

	/**
	 * Longest string reported for a multi-line value.
	 *
	 * @var int
	 */
	const MAX_TEXT = 4000;

	/**
	 * Most entries reported for a list value.
	 *
	 * @var int
	 */
	const MAX_LIST = 200;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Free's settings.
	 *
	 * @return array<string, array>
	 */
	private function free_definitions(): array {
		$per_page = [
			'min' => 1,
			'max' => max( 1, Fns::maximum_media_per_page() ),
		];

		$alt     = [ 'image_name_to_alt', 'custom_text_to_alt', 'none' ];
		$caption = [ 'image_name_to_caption', 'custom_text_to_caption', 'none' ];
		$desc    = [ 'image_name_to_desc', 'custom_text_to_desc', 'none' ];

		return [
			'media_per_page'               => $this->define( 'media', __( 'Media per page', 'media-library-tools' ), 'integer', 20, $per_page ),
			'rubbish_per_page'             => $this->define( 'media', __( 'Unlisted files per page', 'media-library-tools' ), 'integer', 20, $per_page ),
			'deregistered_image_sizes'     => $this->define( 'media', __( 'Disabled image sizes', 'media-library-tools' ), 'list', [] ),
			'default_alt_text'             => $this->define( 'defaults', __( 'Alt text on upload', 'media-library-tools' ), 'enum', 'image_name_to_alt', [ 'allowed' => $alt ] ),
			'default_caption_text'         => $this->define( 'defaults', __( 'Caption on upload', 'media-library-tools' ), 'enum', 'none', [ 'allowed' => $caption ] ),
			'default_desc_text'            => $this->define( 'defaults', __( 'Description on upload', 'media-library-tools' ), 'enum', 'none', [ 'allowed' => $desc ] ),
			'media_default_alt'            => $this->define( 'defaults', __( 'Custom alt text on upload', 'media-library-tools' ), 'string', '' ),
			'media_default_caption'        => $this->define( 'defaults', __( 'Custom caption on upload', 'media-library-tools' ), 'string', '' ),
			'media_default_desc'           => $this->define( 'defaults', __( 'Custom description on upload', 'media-library-tools' ), 'string', '' ),
			'auto_process_exif'            => $this->define( 'exif', __( 'Process EXIF data on upload', 'media-library-tools' ), 'boolean', false ),
			'auto_fill_metadata_from_exif' => $this->define( 'exif', __( 'Fill empty fields from EXIF on upload', 'media-library-tools' ), 'boolean', false ),
			'auto_set_date_from_exif'      => $this->define( 'exif', __( 'Set the media date from EXIF on upload', 'media-library-tools' ), 'boolean', false ),
			'compression_mode'             => $this->define( 'compression', __( 'Compression level', 'media-library-tools' ), 'enum', CompressionSettings::DEFAULT_MODE, [ 'allowed' => array_keys( CompressionSettings::PRESETS ) ] ),
			'conversion_webp_enabled'      => $this->define( 'conversion', __( 'Convert to WebP', 'media-library-tools' ), 'boolean', true ),
			'conversion_serve_modern'      => $this->define( 'conversion', __( 'Serve WebP/AVIF to supporting browsers', 'media-library-tools' ), 'boolean', false ),
			'ai_provider'                  => $this->define( 'ai', __( 'AI provider', 'media-library-tools' ), 'enum', 'gemini', [ 'allowed' => array_keys( Credentials::PROVIDERS ) ] ),
			'ai_language'                  => $this->define( 'ai', __( 'AI output language (empty: the site language)', 'media-library-tools' ), 'string', '' ),
			'ai_chatgpt_model'             => $this->define( 'ai', __( 'ChatGPT model (empty: the default model)', 'media-library-tools' ), 'string', '' ),
			'ai_gemini_model'              => $this->define( 'ai', __( 'Gemini model (empty: the default model)', 'media-library-tools' ), 'string', '' ),
			'ai_claude_model'              => $this->define( 'ai', __( 'Claude model (empty: the default model)', 'media-library-tools' ), 'string', '' ),
			'ai_max_suggestion_count'      => $this->define( 'ai', __( 'Most AI suggestions per field', 'media-library-tools' ), 'integer', 1, [ 'min' => 1 ] ),
			'track_frontend_usage'         => $this->define( 'frontend', __( 'Track media usage on the front end', 'media-library-tools' ), 'boolean', false ),
		];
	}

	/**
	 * One definition.
	 *
	 * @param string $group   Group.
	 * @param string $label   Label.
	 * @param string $type    One of TYPES.
	 * @param mixed  $fallback Default value.
	 * @param array  $extra   `allowed`, `min`, `max`.
	 *
	 * @return array
	 */
	public function define( string $group, string $label, string $type, $fallback, array $extra = [] ): array {
		return [
			'group'   => $group,
			'label'   => $label,
			'type'    => $type,
			'default' => $fallback,
			'allowed' => isset( $extra['allowed'] ) ? array_values( array_map( 'strval', (array) $extra['allowed'] ) ) : null,
			'min'     => isset( $extra['min'] ) ? (int) $extra['min'] : null,
			'max'     => isset( $extra['max'] ) ? (int) $extra['max'] : null,
			'owner'   => 'free',
		];
	}

	/**
	 * Whether a setting name looks like a secret.
	 *
	 * @param string $key Setting key.
	 *
	 * @return bool
	 */
	public static function is_secret_name( string $key ): bool {
		return 1 === preg_match( '/(^|_)(key|keys|secret|secrets|token|tokens|password|passwords|credential|credentials|nonce|salt)(_|$)/', $key );
	}

	/**
	 * Every reportable definition: Free's, then Pro's while Pro is active.
	 * Pro's definitions are checked: unknown shapes, Free's own keys,
	 * excluded keys and secret-looking names are dropped.
	 *
	 * @return array<string, array>
	 */
	public function definitions(): array {
		$definitions = $this->free_definitions();
		if ( ! tsmlt()->has_pro() ) {
			return $definitions;
		}

		/**
		 * Media Library Tools Pro's settings for tsmlt/get-settings.
		 *
		 * @param array $pro Setting key => definition (see define()).
		 */
		$pro = apply_filters( self::DEFINITIONS_FILTER, [] );
		if ( ! is_array( $pro ) ) {
			return $definitions;
		}
		foreach ( $pro as $key => $definition ) {
			$definition = $this->checked_definition( $key, $definition );
			if ( null !== $definition && ! isset( $definitions[ $key ] ) ) {
				$definition['owner'] = 'pro';
				$definitions[ $key ] = $definition;
			}
		}

		return $definitions;
	}

	/**
	 * A filtered definition, or null when it is not acceptable.
	 *
	 * @param mixed $key        Setting key.
	 * @param mixed $definition Definition.
	 *
	 * @return array|null
	 */
	private function checked_definition( $key, $definition ): ?array {
		if ( ! is_string( $key ) || 1 !== preg_match( '/^[a-z0-9_]{1,64}$/', $key ) || in_array( $key, self::EXCLUDED_KEYS, true ) || self::is_secret_name( $key ) ) {
			return null;
		}
		if ( ! is_array( $definition ) || ! in_array( $definition['group'] ?? '', self::GROUPS, true ) || ! in_array( $definition['type'] ?? '', self::TYPES, true ) ) {
			return null;
		}
		if ( 'enum' === $definition['type'] && ( empty( $definition['allowed'] ) || ! is_array( $definition['allowed'] ) ) ) {
			return null;
		}

		return $this->define(
			(string) $definition['group'],
			sanitize_text_field( (string) ( $definition['label'] ?? $key ) ),
			(string) $definition['type'],
			$definition['default'] ?? null,
			[
				'allowed' => $definition['allowed'] ?? null,
				'min'     => $definition['min'] ?? null,
				'max'     => $definition['max'] ?? null,
			]
		);
	}

	/**
	 * The settings, normalised: all of them, those of one group, or the
	 * given keys (which must all be defined).
	 *
	 * @param string[]|null $keys  Keys, or null.
	 * @param string        $group Group, or ''.
	 *
	 * @return array{settings: array<int, array>, unknown: string[]}
	 */
	public function describe( ?array $keys = null, string $group = '' ): array {
		$definitions = $this->definitions();
		$options     = Fns::get_options();
		$unknown     = [];

		if ( null === $keys ) {
			$selected = array_keys( $definitions );
		} else {
			$selected = [];
			foreach ( $keys as $key ) {
				if ( isset( $definitions[ $key ] ) ) {
					$selected[] = $key;
				} else {
					$unknown[] = $key;
				}
			}
		}

		$settings = [];
		foreach ( $selected as $key ) {
			$definition = $definitions[ $key ];
			if ( '' !== $group && $group !== $definition['group'] ) {
				continue;
			}
			$settings[] = $this->row( $key, $definition, $options );
		}

		return [
			'settings' => $settings,
			'unknown'  => $unknown,
		];
	}

	/**
	 * Status of each AI provider's API key (saved, empty or unreadable),
	 * never the key itself.
	 *
	 * @return array<string, string>
	 */
	public function ai_key_status(): array {
		$statuses = [];
		foreach ( Credentials::statuses() as $provider => $status ) {
			$statuses[ (string) $provider ] = in_array( $status, [ Credentials::STATUS_SAVED, Credentials::STATUS_EMPTY, Credentials::STATUS_UNREADABLE ], true ) ? $status : Credentials::STATUS_UNREADABLE;
		}

		return $statuses;
	}

	/**
	 * One reported setting.
	 *
	 * @param string $key        Setting key.
	 * @param array  $definition Definition.
	 * @param array  $options    Fns::get_options().
	 *
	 * @return array
	 */
	private function row( string $key, array $definition, array $options ): array {
		// The number of suggestions is capped by the current maximum.
		if ( 'ai_suggestion_count' === $key ) {
			$definition['max'] = max( 1, (int) ( $options['ai_max_suggestion_count'] ?? 1 ) );
		}

		return [
			'key'     => $key,
			'group'   => $definition['group'],
			'label'   => $definition['label'],
			'type'    => self::output_type( $definition['type'] ),
			'value'   => $this->normalise( array_key_exists( $key, $options ) ? $options[ $key ] : $definition['default'], $definition ),
			'default' => $this->normalise( $definition['default'], $definition ),
			'allowed' => $definition['allowed'],
			'min'     => $definition['min'],
			'max'     => $definition['max'],
			'owner'   => $definition['owner'],
		];
	}

	/**
	 * Type reported to clients.
	 *
	 * @param string $type One of TYPES.
	 *
	 * @return string
	 */
	private static function output_type( string $type ): string {
		$map = [
			'enum' => 'string',
			'text' => 'string',
			'list' => 'array',
		];
		return $map[ $type ] ?? $type;
	}

	/**
	 * A value normalised the way the plugin reads it: booleans by
	 * emptiness, integers capped (not a number or below the minimum: the
	 * default), choices outside the list reported as the
	 * default (`none` for the text-on-upload choices, which is how an empty
	 * or unknown value behaves), strings cleaned and shortened, lists of
	 * plain names only.
	 *
	 * @param mixed $value      Stored value.
	 * @param array $definition Definition.
	 *
	 * @return mixed
	 */
	private function normalise( $value, array $definition ) {
		switch ( $definition['type'] ) {
			case 'boolean':
				return ! empty( $value );
			case 'integer':
				$default = is_numeric( $definition['default'] ) ? (int) $definition['default'] : 0;
				$int     = is_numeric( $value ) ? (int) $value : $default;
				// Below the minimum is not a usable value: report the default.
				if ( null !== $definition['min'] && $int < $definition['min'] ) {
					$int = $default;
				}
				if ( null !== $definition['max'] ) {
					$int = min( $definition['max'], $int );
				}
				return $int;
			case 'enum':
				$allowed = (array) $definition['allowed'];
				if ( is_string( $value ) && in_array( $value, $allowed, true ) ) {
					return $value;
				}
				if ( in_array( 'none', $allowed, true ) ) {
					return 'none';
				}
				return in_array( (string) $definition['default'], $allowed, true ) ? (string) $definition['default'] : (string) reset( $allowed );
			case 'list':
				$items = [];
				// Only entries that are plain names already; others are dropped, not rewritten.
				foreach ( (array) $value as $item ) {
					if ( is_string( $item ) && 1 === preg_match( '/^[A-Za-z0-9_.-]{1,100}$/', $item ) ) {
						$items[] = $item;
					}
				}
				return array_slice( array_values( array_unique( $items ) ), 0, self::MAX_LIST );
			case 'text':
				return is_scalar( $value ) ? mb_substr( sanitize_textarea_field( (string) $value ), 0, self::MAX_TEXT ) : '';
			default:
				return is_scalar( $value ) ? mb_substr( sanitize_text_field( (string) $value ), 0, self::MAX_STRING ) : '';
		}
	}
}
