<?php
/**
 * Abilities API integration — registers the plugin's ability category and abilities.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abs\Ability;
use TinySolutions\mlt\Abilities\Media\GetMediaDetails;
use TinySolutions\mlt\Abilities\Media\SearchMedia;
use TinySolutions\mlt\Abilities\Media\UpdateMediaMetadata;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Registers Media Library Tools abilities with the WordPress Abilities API.
 *
 * The Abilities API ships with WordPress 6.9, while this plugin still supports
 * older releases, so every hook is skipped when the API is not loaded. MCP
 * clients reach these abilities through the separate MCP Adapter plugin; no
 * endpoint, transport or authentication is implemented here.
 */
class AbilitiesInit {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Ability category slug shared by every ability this plugin registers.
	 *
	 * @var string
	 */
	const CATEGORY = 'media-library-tools';

	/**
	 * Class Constructor
	 */
	private function __construct() {
		if ( ! self::is_supported() ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
		McpServerRegistration::instance();
	}

	/**
	 * Whether the running WordPress provides the Abilities API.
	 *
	 * @return bool
	 */
	public static function is_supported(): bool {
		return function_exists( 'wp_register_ability' ) && function_exists( 'wp_register_ability_category' );
	}

	/**
	 * Register the plugin's ability category.
	 *
	 * Runs on `wp_abilities_api_categories_init`, the only point at which
	 * core accepts category registrations.
	 *
	 * @return void
	 */
	public function register_category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'Media Library Tools', 'media-library-tools' ),
				'description' => __( 'Search, inspect and update media library attachments.', 'media-library-tools' ),
			]
		);
	}

	/**
	 * Register every ability provided by the free plugin.
	 *
	 * Runs on `wp_abilities_api_init`, the only point at which core accepts
	 * ability registrations.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		foreach ( $this->get_abilities() as $ability ) {
			$ability->register();
		}
	}

	/**
	 * Names of the abilities provided by the free plugin.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array_map(
			static function ( Ability $ability ): string {
				return $ability->get_name();
			},
			$this->get_abilities()
		);
	}

	/**
	 * Abilities provided by the free plugin.
	 *
	 * @return Ability[]
	 */
	private function get_abilities(): array {
		return [
			SearchMedia::instance(),
			GetMediaDetails::instance(),
			UpdateMediaMetadata::instance(),
		];
	}
}
