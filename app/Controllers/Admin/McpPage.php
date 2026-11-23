<?php
/**
 * The MCP Connection admin page.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Media › MCP Connection, the plugin's one MCP page, with two tabs:
 * Connection (the React app in src/js/Component/Mcp: the access switch,
 * the connection URLs and client setup) and Documentation (the reference
 * that McpDocsPage renders, which used to be a page of its own). The old
 * MCP Docs address redirects to the Documentation tab.
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
	 * Query parameter selecting the tab.
	 *
	 * @var string
	 */
	const TAB_PARAM = 'tab';

	/**
	 * Connection tab (the default).
	 *
	 * @var string
	 */
	const TAB_CONNECTION = 'connection';

	/**
	 * Documentation tab.
	 *
	 * @var string
	 */
	const TAB_DOCUMENTATION = 'documentation';

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * URL of a tab.
	 *
	 * @param string $tab TAB_CONNECTION or TAB_DOCUMENTATION.
	 *
	 * @return string
	 */
	public static function tab_url( string $tab = self::TAB_CONNECTION ): string {
		$url = admin_url( 'upload.php?page=' . self::PAGE_SLUG );
		return self::TAB_DOCUMENTATION === $tab ? add_query_arg( self::TAB_PARAM, self::TAB_DOCUMENTATION, $url ) : $url;
	}

	/**
	 * The tab requested in the address (Connection unless Documentation).
	 *
	 * @return string
	 */
	public static function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection.
		$tab = isset( $_GET[ self::TAB_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::TAB_PARAM ] ) ) : '';
		return self::TAB_DOCUMENTATION === $tab ? self::TAB_DOCUMENTATION : self::TAB_CONNECTION;
	}

	/**
	 * Output the page: the heading, the two tabs, and the selected tab.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'media-library-tools' ) );
		}
		$current = self::current_tab();
		$tabs    = [
			self::TAB_CONNECTION    => __( 'Connection', 'media-library-tools' ),
			self::TAB_DOCUMENTATION => __( 'Documentation', 'media-library-tools' ),
		];
		?>
		<div class="wrap tsmlt-mcp-connection">
			<?php $this->render_styles(); ?>
			<div class="tsmlt-mcp-header">
				<h1><?php esc_html_e( 'MCP Connection', 'media-library-tools' ); ?></h1>
				<p><?php esc_html_e( 'Connect AI assistants such as Claude, Codex and Cursor to your media library through the Model Context Protocol (MCP), and control their access.', 'media-library-tools' ); ?></p>
			</div>
			<hr class="wp-header-end">
			<?php
			// The MCP Adapter notice belongs to the page, not a tab.
			$docs = McpDocsPage::instance();
			$docs->render_status_notice( $docs->get_adapter_status(), AbilitiesInit::is_supported() );
			?>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'MCP Connection', 'media-library-tools' ); ?>">
				<?php foreach ( $tabs as $tab => $label ) : ?>
					<a href="<?php echo esc_url( self::tab_url( $tab ) ); ?>" class="nav-tab<?php echo $current === $tab ? ' nav-tab-active' : ''; ?>"<?php echo $current === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php if ( self::TAB_DOCUMENTATION === $current ) : ?>
				<?php McpDocsPage::instance()->render(); ?>
			<?php else : ?>
				<div id="tsmlt_mcp_root"></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Styles for the page's header, adapter notice and tabs, shared by both
	 * tabs (the Connection tab's own content is styled by the React app).
	 *
	 * @return void
	 */
	private function render_styles(): void {
		?>
		<style>
			.tsmlt-mcp-connection { max-width: 1120px; }
			.tsmlt-mcp-header { margin: 8px 0 16px; }
			.tsmlt-mcp-connection .tsmlt-mcp-header h1 { margin: 0; padding: 0; font-size: 24px; font-weight: 600; line-height: 1.3; color: #0f172a; }
			.tsmlt-mcp-header p { margin: 4px 0 0; max-width: 720px; font-size: 14px; line-height: 1.5; color: #475569; }
			.tsmlt-mcp-connection .tsmlt-mcp-notice { display: block; margin: 0 0 16px; padding: 12px 16px; border: 1px solid #fcd34d; border-left-width: 4px; border-radius: 8px; background: #fffbeb; box-shadow: none; }
			.tsmlt-mcp-connection .tsmlt-mcp-notice.notice-error { border-color: #fca5a5; background: #fef2f2; }
			.tsmlt-mcp-connection .tsmlt-mcp-notice p { margin: 0 0 6px; padding: 0; font-size: 13px; line-height: 1.5; color: #334155; }
			.tsmlt-mcp-connection .tsmlt-mcp-notice p:first-child strong { font-size: 14px; color: #0f172a; }
			.tsmlt-mcp-connection .tsmlt-mcp-notice p:last-child { margin-bottom: 0; }
			.tsmlt-mcp-connection .tsmlt-mcp-notice .button { margin-top: 4px; }
			.tsmlt-mcp-connection .tsmlt-mcp-adapter-feedback:empty { display: none; }
			.tsmlt-mcp-connection .nav-tab-wrapper { display: flex; gap: 24px; margin: 0; padding: 0; border-bottom: 1px solid #e2e8f0; }
			.tsmlt-mcp-connection .nav-tab-wrapper .nav-tab { float: none; margin: 0 0 -1px; padding: 10px 2px; border: 0; border-bottom: 2px solid transparent; background: none; font-size: 14px; font-weight: 500; line-height: 1.4; color: #64748b; }
			.tsmlt-mcp-connection .nav-tab-wrapper .nav-tab:hover { color: #0f172a; }
			.tsmlt-mcp-connection .nav-tab-wrapper .nav-tab-active, .tsmlt-mcp-connection .nav-tab-wrapper .nav-tab-active:hover { border-bottom-color: #2563eb; background: none; color: #0f172a; }
			.tsmlt-mcp-connection .nav-tab-wrapper .nav-tab:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 4px; box-shadow: none; }
		</style>
		<?php
	}

	/**
	 * Where the old MCP Docs address goes: the Documentation tab, for users
	 * who may open it; '' for any other request.
	 *
	 * @param string $page Requested page slug.
	 *
	 * @return string
	 */
	public static function legacy_redirect_url( string $page ): string {
		return McpDocsPage::PAGE_SLUG === $page && current_user_can( self::CAPABILITY ) ? self::tab_url( self::TAB_DOCUMENTATION ) : '';
	}

	/**
	 * Redirect the old MCP Docs page to the Documentation tab. Runs on
	 * `admin_page_access_denied`, which WordPress fires for that address now
	 * that the page is no longer registered.
	 *
	 * @return void
	 */
	public function redirect_legacy_docs(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$url  = self::legacy_redirect_url( $page );
		if ( '' !== $url ) {
			wp_safe_redirect( $url );
			exit;
		}
	}
}
