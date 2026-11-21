<?php
/**
 * Site-wide switch for MCP access to Media Library Tools.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * The "Turn off MCP access" setting of the MCP page. Off (the default),
 * Media Library Tools' MCP tools and resources work as before. On, every
 * Free and Pro ability refuses through AbilityGuard::check_base_capability(),
 * the dedicated endpoint refuses at its transport, and the tools are left
 * off and hidden from the default MCP server. Nothing is unregistered, so
 * turning it off again restores access without reconnecting clients; other
 * plugins' abilities and MCP servers are not affected.
 */
class McpAccess {

	/**
	 * Option holding the switch (true: access turned off).
	 *
	 * @var string
	 */
	const OPTION = 'tsmlt_mcp_access_disabled';

	/**
	 * Whether MCP access is on (the default).
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return ! (bool) get_option( self::OPTION, false );
	}

	/**
	 * Turn MCP access off (true) or on (false).
	 *
	 * @param bool $disabled Whether to turn access off.
	 *
	 * @return bool Whether the stored value now matches.
	 */
	public static function set_disabled( bool $disabled ): bool {
		update_option( self::OPTION, $disabled, true );
		return self::is_enabled() === ! $disabled;
	}
}
