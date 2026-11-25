<?php
/**
 * Ability: tsmlt/get-settings.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities\Media;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abs\Ability;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Modules\Settings\SettingsCatalog;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * The plugin's non-sensitive settings (SettingsCatalog), with the status of
 * each AI provider's API key. Read-only: no setting can be changed here.
 */
class GetSettings extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Most keys per call.
	 *
	 * @var int
	 */
	const MAX_KEYS = 50;

	/**
	 * Error code for keys that are not reported (unknown, excluded, or Pro
	 * settings without an active licence) — the same for all of them.
	 *
	 * @var string
	 */
	const ERROR_NOT_SUPPORTED = 'tsmlt_setting_not_supported';

	/**
	 * Pseudo-key for the API key statuses, which are always returned.
	 *
	 * @var string
	 */
	const AI_KEY_STATUS = 'ai_key_status';

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/get-settings';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Get Media Library Tools settings', 'media-library-tools' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Read Media Library Tools settings: per-page limits, what fills alt text, caption and description on upload, EXIF processing on upload, compression and WebP conversion, the AI provider, language and models, and front-end usage tracking; with Media Library Tools Pro active, also its rename, title-based text, front-end alt text, AI and compression settings. Each setting has its key, group, label, current value, default, type and allowed values or range. ai_key_status says for each AI provider whether an API key is saved, empty or unreadable — never the key. Optional: keys (up to 50 setting keys) or group (media, defaults, exif, compression, conversion, ai, rename, frontend), not both; without them every setting is returned. Read-only: settings are changed only in WP Admin. API keys, credentials, uninstall behaviour, allowed file types and internal state are never reported.', 'media-library-tools' );
	}

	/**
	 * Listed by name only; not run through the default gateway.
	 *
	 * @return bool
	 */
	protected function is_mcp_public(): bool {
		return false;
	}

	/**
	 * Input schema.
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			// Lets clients call the ability with no arguments at all.
			'default'              => [],
			'properties'           => [
				'keys'  => [
					'type'        => 'array',
					'description' => 'Setting keys to return (up to 50). Without keys or group, every setting.',
					'minItems'    => 1,
					'maxItems'    => self::MAX_KEYS,
					'uniqueItems' => true,
					'items'       => [
						'type'    => 'string',
						'pattern' => '^[a-z0-9_]{1,64}$',
					],
				],
				'group' => [
					'type'        => 'string',
					'description' => 'Only the settings of this group.',
					'enum'        => SettingsCatalog::GROUPS,
				],
			],
		];
	}

	/**
	 * Output schema.
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'settings', 'total', 'ai_key_status', 'pro_active' ],
			'properties'           => [
				'settings'      => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'key', 'group', 'label', 'type', 'value', 'default', 'allowed', 'min', 'max', 'owner' ],
						'properties'           => [
							'key'     => [ 'type' => 'string' ],
							'group'   => [
								'type' => 'string',
								'enum' => SettingsCatalog::GROUPS,
							],
							'label'   => [ 'type' => 'string' ],
							'type'    => [
								'type' => 'string',
								'enum' => [ 'integer', 'boolean', 'string', 'array' ],
							],
							'value'   => [ 'type' => [ 'integer', 'boolean', 'string', 'array', 'null' ] ],
							'default' => [ 'type' => [ 'integer', 'boolean', 'string', 'array', 'null' ] ],
							'allowed' => [
								'type'  => [ 'array', 'null' ],
								'items' => [ 'type' => 'string' ],
							],
							'min'     => [ 'type' => [ 'integer', 'null' ] ],
							'max'     => [ 'type' => [ 'integer', 'null' ] ],
							'owner'   => [
								'type' => 'string',
								'enum' => [ 'free', 'pro' ],
							],
						],
					],
				],
				'total'         => [ 'type' => 'integer' ],
				'ai_key_status' => [
					'type'                 => 'object',
					'description'          => 'Per AI provider: saved, empty or unreadable. Never the key.',
					'additionalProperties' => [
						'type' => 'string',
						'enum' => [ 'saved', 'empty', 'unreadable' ],
					],
				],
				'pro_active'    => [ 'type' => 'boolean' ],
			],
		];
	}

	/**
	 * Annotations.
	 *
	 * @return array<string, bool>
	 */
	protected function get_annotations(): array {
		return [
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		];
	}

	/**
	 * Report the settings.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		// The callback can be reached without the permission callback.
		$base = AbilityGuard::check_base_capability();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$input = null === $input ? [] : $input;
		if ( ! is_array( $input ) || array_diff( array_keys( $input ), [ 'keys', 'group' ] ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Only keys and group are accepted.', 'media-library-tools' ), 400 );
		}
		if ( isset( $input['keys'], $input['group'] ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Use keys or group, not both.', 'media-library-tools' ), 400 );
		}

		$group = '';
		if ( isset( $input['group'] ) ) {
			if ( ! is_string( $input['group'] ) || ! in_array( $input['group'], SettingsCatalog::GROUPS, true ) ) {
				return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Unknown group.', 'media-library-tools' ), 400 );
			}
			$group = $input['group'];
		}

		$keys = null;
		if ( isset( $input['keys'] ) ) {
			$keys = $this->checked_keys( $input['keys'] );
			if ( is_wp_error( $keys ) ) {
				return $keys;
			}
		}

		$catalog = SettingsCatalog::instance();
		$result  = $catalog->describe( $keys, $group );
		if ( ! empty( $result['unknown'] ) ) {
			// One answer for unknown, excluded and unlicensed keys alike.
			return AbilityGuard::error(
				self::ERROR_NOT_SUPPORTED,
				sprintf(
					/* translators: %s: comma-separated setting keys. */
					__( 'These settings are not available through MCP: %s. Call tsmlt/get-settings without keys to see the available ones.', 'media-library-tools' ),
					implode( ', ', $result['unknown'] )
				),
				400
			);
		}

		return [
			'settings'      => $result['settings'],
			'total'         => count( $result['settings'] ),
			'ai_key_status' => $catalog->ai_key_status(),
			'pro_active'    => (bool) tsmlt()->has_pro(),
		];
	}

	/**
	 * The requested keys, checked: a non-empty list of up to MAX_KEYS plain
	 * keys, without duplicates. AI_KEY_STATUS is accepted and dropped (the
	 * statuses are always returned).
	 *
	 * @param mixed $keys Requested keys.
	 *
	 * @return string[]|WP_Error
	 */
	private function checked_keys( $keys ) {
		if ( ! is_array( $keys ) || empty( $keys ) || count( $keys ) > self::MAX_KEYS ) {
			/* translators: %d: maximum number of keys. */
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, sprintf( __( 'keys must list 1 to %d setting keys.', 'media-library-tools' ), self::MAX_KEYS ), 400 );
		}
		$clean = [];
		foreach ( $keys as $key ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^[a-z0-9_]{1,64}$/', $key ) ) {
				return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Setting keys use lower-case letters, digits and underscores only.', 'media-library-tools' ), 400 );
			}
			if ( self::AI_KEY_STATUS !== $key ) {
				$clean[] = $key;
			}
		}
		if ( count( $clean ) !== count( array_unique( $clean ) ) ) {
			return AbilityGuard::error( AbilityGuard::ERROR_INVALID_INPUT, __( 'Each key may be listed once.', 'media-library-tools' ), 400 );
		}

		return $clean;
	}
}
