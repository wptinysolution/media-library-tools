<?php
/**
 * The MCP admin page.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Media › MCP: connect an MCP client to the WordPress default MCP server
 * and turn Media Library Tools' MCP access off or on. The page itself is
 * the plugin's React app (src/js/Component/Mcp); this class only provides
 * the screen.
 */
class McpPage {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Page slug (upload.php?page=…).
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'media-library-tools-mcp';

	/**
	 * Capability required.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Output the React mount point.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'media-library-tools' ) );
		}
		echo '<div id="tsmlt_mcp_root" style="margin-left: -20px;"></div>';
	}
}
