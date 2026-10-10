<?php
/**
 * Base class for Abilities API abilities.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abs;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\AbilityGuard;
use WP_Error;

/**
 * Abstract base class for an ability registered with the WordPress Abilities API.
 *
 * Holds the registration arguments shared by every ability — category, base
 * permission check, MCP exposure — so each concrete ability only describes its
 * own schema and behaviour.
 */
abstract class Ability {

	/**
	 * Ability name, e.g. "tsmlt/search-media".
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Human-readable label.
	 *
	 * @return string
	 */
	abstract protected function get_label(): string;

	/**
	 * Description shown to MCP clients when they choose a tool.
	 *
	 * @return string
	 */
	abstract protected function get_description(): string;

	/**
	 * JSON Schema for the ability input.
	 *
	 * @return array
	 */
	abstract protected function get_input_schema(): array;

	/**
	 * JSON Schema for the ability output.
	 *
	 * @return array
	 */
	abstract protected function get_output_schema(): array;

	/**
	 * Behaviour annotations: readonly, destructive, idempotent.
	 *
	 * @return array<string, bool>
	 */
	abstract protected function get_annotations(): array;

	/**
	 * Execute the ability.
	 *
	 * Core validates the input against the schema before calling this, but
	 * validation is not sanitization — implementations must still sanitize.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return mixed|WP_Error
	 */
	abstract public function execute( $input = null );

	/**
	 * Permission callback.
	 *
	 * Every ability requires the plugin's base capability. Implementations that
	 * act on specific attachments must additionally check each one through
	 * AbilityGuard, since this callback only sees the raw input.
	 *
	 * Returns a plain boolean: when a permission callback returns a WP_Error,
	 * WP_Ability::execute() raises _doing_it_wrong() and replaces it with a
	 * generic `ability_invalid_permissions` error anyway.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return bool
	 */
	public function check_permission( $input = null ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature required by the Abilities API.
		return true === AbilityGuard::check_base_capability();
	}

	/**
	 * Register the ability. Must run during `wp_abilities_api_init`.
	 *
	 * @return void
	 */
	public function register(): void {
		wp_register_ability(
			$this->get_name(),
			[
				'label'               => $this->get_label(),
				'description'         => $this->get_description(),
				'category'            => AbilitiesInit::CATEGORY,
				'input_schema'        => $this->get_input_schema(),
				'output_schema'       => $this->get_output_schema(),
				'execute_callback'    => [ $this, 'execute' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'meta'                => [
					'annotations'  => $this->get_annotations(),
					// Not exposed through the core `wp-abilities/v1` REST routes;
					// the MCP Adapter executes abilities in PHP and does not need them.
					'show_in_rest' => false,
					// Read by the MCP Adapter. `meta.mcp.public` is used rather than
					// core's `meta.public`, which only exists from WordPress 7.1.
					'mcp'          => [
						'public' => true,
						'type'   => 'tool',
					],
				],
			]
		);
	}
}
