<?php
/**
 * Dedicated MCP server for Media Library Tools abilities.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;
use WP_REST_Request;

/**
 * Registers an MCP server that exposes only this plugin's abilities.
 *
 * The MCP Adapter's default server is a gateway: its discover/execute
 * meta-tools reach every public ability on the site, from any plugin. This
 * server instead lists the plugin's abilities as its only tools, so a client
 * connected to it cannot discover or call anything else. Each tool still runs
 * through WP_Ability::execute(), so every ability's own validation and
 * permission checks apply unchanged.
 *
 * Uses the MCP Adapter's documented `mcp_adapter_init` / create_server() API
 * and does nothing when the adapter is not active. The default server is left
 * untouched.
 */
class McpServerRegistration {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Server ID.
	 *
	 * @var string
	 */
	const SERVER_ID = 'media-library-tools';

	/**
	 * REST namespace and route: /wp-json/media-library-tools/mcp.
	 *
	 * @var string
	 */
	const ROUTE_NAMESPACE = 'media-library-tools';

	/**
	 * REST route within the namespace.
	 *
	 * @var string
	 */
	const ROUTE = 'mcp';

	/**
	 * Filter that adds abilities to this server's tool list. See get_tool_names().
	 *
	 * @var string
	 */
	const TOOLS_FILTER = 'tsmlt_mcp_server_abilities';

	/**
	 * Ability name format, as WP_Abilities_Registry validates it.
	 *
	 * @var string
	 */
	const ABILITY_NAME_PATTERN = '/^[a-z0-9-]+\/[a-z0-9-]+$/';

	/**
	 * Class Constructor
	 */
	private function __construct() {
		add_action( 'mcp_adapter_init', [ $this, 'register_server' ] );
	}

	/**
	 * Create the server. Runs on `mcp_adapter_init`, the only point at which
	 * the adapter accepts new servers.
	 *
	 * @param object $adapter The \WP\MCP\Core\McpAdapter instance.
	 *
	 * @return void
	 */
	public function register_server( $adapter ): void {
		if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) || ! class_exists( '\WP\MCP\Transport\HttpTransport' ) ) {
			return;
		}

		$adapter->create_server(
			self::SERVER_ID,
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			'Media Library Tools',
			'Search, inspect and update media library attachments.',
			defined( 'TSMLT_VERSION' ) ? TSMLT_VERSION : '1.0.0',
			[ \WP\MCP\Transport\HttpTransport::class ],
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			null,
			$this->get_tool_names(),
			[],
			[],
			[ $this, 'check_transport_permission' ]
		);
	}

	/**
	 * Ability names offered as tools on this server.
	 *
	 * Always the Free abilities first, then any abilities added through the
	 * `tsmlt_mcp_server_abilities` filter — the extension point for the Pro
	 * plugin, which registers and owns its abilities itself.
	 *
	 * Filter contract:
	 *
	 *     add_filter( 'tsmlt_mcp_server_abilities', function ( array $names, array $free_names ) {
	 *         $names[] = 'tsmlt/my-pro-ability';
	 *         return $names;
	 *     }, 10, 2 );
	 *
	 * - Receives an empty array and the Free ability names (read-only context);
	 *   returns extra ability names. Free abilities cannot be removed.
	 * - Each name must be a registered ability (register it on
	 *   `wp_abilities_api_init`) in the `media-library-tools` ability category.
	 *   Anything else is skipped with a _doing_it_wrong() notice; duplicates
	 *   are listed once.
	 * - Listing an ability here does not check a licence or any capability for
	 *   it. Connecting still requires manage_options, but each added ability
	 *   must enforce its own requirements — for Pro abilities, a valid licence
	 *   (`tsmlt()->has_pro()`) in both its permission callback and its execute
	 *   callback.
	 * - The MCP Adapter lists explicitly named abilities whatever their
	 *   `meta.mcp.public` setting; leave it false to keep an ability off the
	 *   adapter's default gateway server.
	 *
	 * @return string[]
	 */
	public function get_tool_names(): array {
		$free_names = AbilitiesInit::instance()->get_ability_names();
		$names      = $free_names;

		/**
		 * Filter the extra abilities offered on the Media Library Tools MCP server.
		 *
		 * @param string[] $extra      Ability names to add. Empty by default.
		 * @param string[] $free_names The Free abilities, always included.
		 */
		$extra = apply_filters( self::TOOLS_FILTER, [], $free_names );

		if ( ! is_array( $extra ) ) {
			_doing_it_wrong(
				esc_html( self::TOOLS_FILTER ),
				esc_html__( 'The filter must return an array of ability names; the extra abilities were ignored.', 'media-library-tools' ),
				esc_html( defined( 'TSMLT_VERSION' ) ? TSMLT_VERSION : '' )
			);
			return $names;
		}

		foreach ( $extra as $name ) {
			$reason = $this->get_rejection_reason( $name );
			if ( '' !== $reason ) {
				_doing_it_wrong(
					esc_html( self::TOOLS_FILTER ),
					esc_html( $reason ),
					esc_html( defined( 'TSMLT_VERSION' ) ? TSMLT_VERSION : '' )
				);
				continue;
			}
			if ( ! in_array( $name, $names, true ) ) {
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * Why a name from the filter cannot be added, or '' when it can.
	 *
	 * @param mixed $name Value returned by the filter.
	 *
	 * @return string
	 */
	private function get_rejection_reason( $name ): string {
		if ( ! is_string( $name ) || 1 !== preg_match( self::ABILITY_NAME_PATTERN, $name ) ) {
			return __( 'Skipped a value that is not a valid ability name (namespace/name).', 'media-library-tools' );
		}

		if ( ! wp_has_ability( $name ) ) {
			/* translators: %s: ability name. */
			return sprintf( __( 'Skipped "%s": no ability is registered with that name.', 'media-library-tools' ), $name );
		}

		$ability = wp_get_ability( $name );
		if ( ! $ability || AbilitiesInit::CATEGORY !== $ability->get_category() ) {
			return sprintf(
				/* translators: 1: ability name, 2: ability category slug. */
				__( 'Skipped "%1$s": only abilities in the "%2$s" category can be added.', 'media-library-tools' ),
				$name,
				AbilitiesInit::CATEGORY
			);
		}

		return '';
	}

	/**
	 * Transport permission: who may open a session on this server at all.
	 *
	 * Stricter than the adapter default (any logged-in user): it requires the
	 * same base capability every ability already requires, so users who could
	 * never run a tool cannot list them either. Abilities still check
	 * permissions themselves on every call.
	 *
	 * @param WP_REST_Request|null $request Incoming request.
	 *
	 * @return bool
	 */
	public function check_transport_permission( $request = null ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature required by the MCP Adapter.
		return true === AbilityGuard::check_base_capability();
	}
}
