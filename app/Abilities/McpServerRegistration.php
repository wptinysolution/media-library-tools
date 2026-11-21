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
 * and does nothing when the adapter is not active.
 *
 * The same tools and MCP App resources are always listed directly on the
 * adapter's default server too, through its `mcp_adapter_default_server_config`
 * filter, so one client configuration reaches them. This server stays as it
 * is, as the narrower endpoint and the fallback when the default server is
 * turned off. No ability is made `meta.mcp.public`, so the default server's
 * execute-ability gateway still cannot reach them.
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
	 * Filter that adds MCP App view resources to this server. See
	 * get_resource_names().
	 *
	 * @var string
	 */
	const RESOURCES_FILTER = 'tsmlt_mcp_server_resources';

	/**
	 * URI scheme every resource added through RESOURCES_FILTER must use.
	 *
	 * @var string
	 */
	const APP_URI_SCHEME = 'ui://';

	/**
	 * MIME type every resource added through RESOURCES_FILTER must declare
	 * (an MCP Apps view).
	 *
	 * @var string
	 */
	const APP_MIME_TYPE = 'text/html;profile=mcp-app';

	/**
	 * Ability name format, as WP_Abilities_Registry validates it.
	 *
	 * @var string
	 */
	const ABILITY_NAME_PATTERN = '/^[a-z0-9-]+\/[a-z0-9-]+$/';

	/**
	 * Oldest MCP Adapter that can list the tools on its default server: it
	 * needs the tools/resources list filters (which hide the tools from users
	 * who cannot run them) and reads resource URIs from `meta.mcp.uri`, both
	 * added in 0.5.0.
	 *
	 * @var string
	 */
	const DEFAULT_SERVER_MIN_ADAPTER_VERSION = '0.5.0';

	/**
	 * Priority of the `mcp_adapter_default_server_config` callback: after
	 * plugins that use the default priority, so their changes to the tool and
	 * resource lists are kept.
	 *
	 * @var int
	 */
	const DEFAULT_SERVER_CONFIG_PRIORITY = 20;

	/**
	 * Class Constructor
	 */
	private function __construct() {
		add_action( 'mcp_adapter_init', [ $this, 'register_server' ] );
		add_filter( 'mcp_adapter_default_server_config', [ $this, 'add_to_default_server' ], self::DEFAULT_SERVER_CONFIG_PRIORITY );
		add_filter( 'mcp_adapter_tools_list', [ $this, 'hide_tools_from_unauthorized' ] );
		add_filter( 'mcp_adapter_resources_list', [ $this, 'hide_resources_from_unauthorized' ] );
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
			'Search, inspect and update media library attachments, and move media to the WordPress trash and restore it. Permanent deletion is not available through MCP. Before permanently deleting media, inspect the entire website for dependencies, create and verify a backup, then log in to WordPress Admin and carefully perform the deletion manually.',
			defined( 'TSMLT_VERSION' ) ? TSMLT_VERSION : '1.0.0',
			[ \WP\MCP\Transport\HttpTransport::class ],
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			null,
			$this->get_tool_names(),
			$this->get_resource_names(),
			[],
			[ $this, 'check_transport_permission' ]
		);
	}

	/**
	 * Whether the loaded MCP Adapter can list the tools on its default server
	 * safely (see DEFAULT_SERVER_MIN_ADAPTER_VERSION). An adapter that does not
	 * report its version is treated as too old.
	 *
	 * @param string|null $version Adapter version; the loaded adapter's by default.
	 *
	 * @return bool
	 */
	public static function adapter_supports_default_server( ?string $version = null ): bool {
		if ( null === $version ) {
			$constant = '\WP\MCP\Core\McpAdapter::VERSION';
			$version  = defined( $constant ) ? (string) constant( $constant ) : '0';
		}
		return version_compare( $version, self::DEFAULT_SERVER_MIN_ADAPTER_VERSION, '>=' );
	}

	/**
	 * Add this server's tools and resources to the MCP Adapter's default
	 * server. Runs on `mcp_adapter_default_server_config`, always; skipped only
	 * for an adapter older than DEFAULT_SERVER_MIN_ADAPTER_VERSION.
	 *
	 * The names are the ones this server lists — get_tool_names() and
	 * get_resource_names(), validated the same way — so the Pro abilities
	 * appear only while Pro adds them (with an active licence). They become
	 * direct tools, with their own annotations and `_meta`, not entries behind
	 * the execute-ability gateway; their `meta.mcp.public` is not changed.
	 * Every call still runs the ability's own permission checks, and
	 * hide_tools_from_unauthorized() keeps them out of other users' lists.
	 *
	 * @param mixed $config Default server configuration.
	 *
	 * @return mixed
	 */
	public function add_to_default_server( $config ) {
		// MCP access turned off (McpAccess): the plugin's tools are not added.
		if ( ! is_array( $config ) || ! self::adapter_supports_default_server() || ! McpAccess::is_enabled() ) {
			return $config;
		}

		$config['tools']     = $this->merge_names( $config['tools'] ?? [], $this->get_tool_names() );
		$config['resources'] = $this->merge_names( $config['resources'] ?? [], $this->get_resource_names() );

		return $config;
	}

	/**
	 * Hide this plugin's tools from tools/list for users who cannot run them.
	 * Runs on `mcp_adapter_tools_list` for every server.
	 *
	 * Every ability in the plugin's category requires the base capability, so
	 * a user without it could only be refused. The dedicated server already
	 * refuses such users at the transport; this matters for the default
	 * server, whose transport admits any logged-in user. Calls are refused by
	 * the abilities themselves either way.
	 *
	 * @param mixed $tools Tool records.
	 *
	 * @return mixed
	 */
	public function hide_tools_from_unauthorized( $tools ) {
		if ( ! is_array( $tools ) || true === AbilityGuard::check_base_capability() ) {
			return $tools;
		}

		$hidden = $this->get_category_tool_names();

		return array_values(
			array_filter(
				$tools,
				static function ( $tool ) use ( $hidden ) {
					return ! in_array( self::get_record_value( $tool, 'name' ), $hidden, true );
				}
			)
		);
	}

	/**
	 * Hide this plugin's resources (MCP App views) from resources/list for
	 * users who cannot read them. Runs on `mcp_adapter_resources_list`. See
	 * hide_tools_from_unauthorized().
	 *
	 * @param mixed $resources Resource records.
	 *
	 * @return mixed
	 */
	public function hide_resources_from_unauthorized( $resources ) {
		if ( ! is_array( $resources ) || true === AbilityGuard::check_base_capability() ) {
			return $resources;
		}

		$hidden = $this->get_category_resource_uris();

		return array_values(
			array_filter(
				$resources,
				static function ( $item ) use ( $hidden ) {
					return ! in_array( self::get_record_value( $item, 'uri' ), $hidden, true );
				}
			)
		);
	}

	/**
	 * MCP tool names of every ability registered in the plugin's category,
	 * as the adapter names them: the ability name with "/" replaced by "-",
	 * passed through the adapter's `mcp_adapter_tool_name` filter.
	 *
	 * @return string[]
	 */
	private function get_category_tool_names(): array {
		$names = [];
		foreach ( $this->get_category_abilities() as $ability ) {
			$name    = str_replace( '/', '-', $ability->get_name() );
			$names[] = $name;
			// Mirrors the adapter's naming so a renamed tool is still hidden.
			$filtered = apply_filters( 'mcp_adapter_tool_name', $name, $ability ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The MCP Adapter's hook.
			if ( is_string( $filtered ) && $filtered !== $name ) {
				$names[] = $filtered;
			}
		}
		return $names;
	}

	/**
	 * Resource URIs (`meta.mcp.uri`) of every ability registered in the
	 * plugin's category.
	 *
	 * @return string[]
	 */
	private function get_category_resource_uris(): array {
		$uris = [];
		foreach ( $this->get_category_abilities() as $ability ) {
			$meta = $ability->get_meta();
			$uri  = is_array( $meta['mcp'] ?? null ) ? ( $meta['mcp']['uri'] ?? null ) : null;
			if ( is_string( $uri ) && '' !== $uri ) {
				$uris[] = $uri;
			}
		}
		return $uris;
	}

	/**
	 * Registered abilities in the plugin's category, Free and Pro.
	 *
	 * @return \WP_Ability[]
	 */
	private function get_category_abilities(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return [];
		}
		return array_filter(
			wp_get_abilities(),
			static function ( $ability ) {
				return $ability instanceof \WP_Ability && AbilitiesInit::CATEGORY === $ability->get_category();
			}
		);
	}

	/**
	 * A tool's name or a resource's URI from a list record: a protocol record
	 * object (getName() / getUri()) or an array. '' when it cannot be read.
	 *
	 * @param mixed  $record Tool or resource record.
	 * @param string $key    'name' or 'uri'.
	 *
	 * @return string
	 */
	private static function get_record_value( $record, string $key ): string {
		$getter = 'name' === $key ? 'getName' : 'getUri';
		if ( is_object( $record ) && method_exists( $record, $getter ) ) {
			$value = $record->$getter();
		} elseif ( is_array( $record ) ) {
			$value = $record[ $key ] ?? '';
		} else {
			$value = '';
		}
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Append names not already in a server configuration list.
	 *
	 * @param mixed    $existing The configuration's list (names or component objects).
	 * @param string[] $names    Names to add.
	 *
	 * @return array
	 */
	private function merge_names( $existing, array $names ): array {
		$merged = is_array( $existing ) ? array_values( $existing ) : [];
		foreach ( $names as $name ) {
			if ( ! in_array( $name, $merged, true ) ) {
				$merged[] = $name;
			}
		}
		return $merged;
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
	 *   adapter's default gateway (execute-ability). The same names are also
	 *   listed as direct tools on the default server (see
	 *   add_to_default_server()).
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
	 * Resources offered on this server: none of its own, plus the MCP App
	 * views added through the `tsmlt_mcp_server_resources` filter — the
	 * extension point for the Pro plugin's views.
	 *
	 * Filter contract:
	 *
	 *     add_filter( 'tsmlt_mcp_server_resources', function ( array $names ) {
	 *         $names[] = 'tsmlt/my-pro-view';
	 *         return $names;
	 *     } );
	 *
	 * - Receives an empty array; returns ability names.
	 * - Each name must be a registered ability in the `media-library-tools`
	 *   category (as for the tools filter) that declares an MCP Apps view:
	 *   `meta.mcp.uri` starting with `ui://` and `meta.mcp.mimeType`
	 *   `text/html;profile=mcp-app`. Free abilities, which are tools, are
	 *   refused. Anything else is skipped with a _doing_it_wrong() notice;
	 *   duplicates are listed once.
	 * - Listing a resource here checks no licence or capability. Connecting
	 *   still requires manage_options; each resource ability must enforce its
	 *   own requirements in its permission and execute callbacks.
	 *
	 * @return string[]
	 */
	public function get_resource_names(): array {
		/**
		 * Filter the MCP App view resources offered on the Media Library Tools MCP server.
		 *
		 * @param string[] $names Ability names of MCP App views. Empty by default.
		 */
		$extra = apply_filters( self::RESOURCES_FILTER, [] );

		if ( ! is_array( $extra ) ) {
			_doing_it_wrong(
				esc_html( self::RESOURCES_FILTER ),
				esc_html__( 'The filter must return an array of ability names; the resources were ignored.', 'media-library-tools' ),
				esc_html( defined( 'TSMLT_VERSION' ) ? TSMLT_VERSION : '' )
			);
			return [];
		}

		$names = [];
		foreach ( $extra as $name ) {
			$reason = $this->get_rejection_reason( $name );
			if ( '' === $reason ) {
				$reason = $this->get_resource_rejection_reason( $name );
			}
			if ( '' !== $reason ) {
				_doing_it_wrong(
					esc_html( self::RESOURCES_FILTER ),
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
	 * Why a valid, registered ability of this plugin's category cannot be
	 * added as a resource, or '' when it can: it must be an MCP Apps view and
	 * not one of the Free abilities.
	 *
	 * @param string $name Ability name.
	 *
	 * @return string
	 */
	private function get_resource_rejection_reason( string $name ): string {
		if ( in_array( $name, AbilitiesInit::instance()->get_ability_names(), true ) ) {
			/* translators: %s: ability name. */
			return sprintf( __( 'Skipped "%s": Free abilities are tools, not resources.', 'media-library-tools' ), $name );
		}

		$meta = wp_get_ability( $name )->get_meta();
		$mcp  = isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) ? $meta['mcp'] : [];
		$uri  = $mcp['uri'] ?? null;
		if ( ! is_string( $uri ) || 0 !== strpos( $uri, self::APP_URI_SCHEME ) || self::APP_MIME_TYPE !== ( $mcp['mimeType'] ?? null ) ) {
			return sprintf(
				/* translators: 1: ability name, 2: URI scheme, 3: MIME type. */
				__( 'Skipped "%1$s": only MCP App views can be added (meta.mcp.uri starting with "%2$s" and meta.mcp.mimeType "%3$s").', 'media-library-tools' ),
				$name,
				self::APP_URI_SCHEME,
				self::APP_MIME_TYPE
			);
		}

		return '';
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
