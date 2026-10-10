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
			AbilitiesInit::instance()->get_ability_names(),
			[],
			[],
			[ $this, 'check_transport_permission' ]
		);
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
