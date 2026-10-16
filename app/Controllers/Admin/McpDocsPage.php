<?php
/**
 * "MCP Docs" admin page: how to connect an AI assistant over MCP.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Renders the MCP documentation page under Media.
 *
 * The page is read-only documentation. When the MCP integration cannot run —
 * the MCP Adapter plugin is missing or inactive, or WordPress has no Abilities
 * API — a message at the top explains what to do first.
 */
class McpDocsPage {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'tsmlt-mcp-docs';

	/**
	 * Capability to view the page. MCP access itself requires it, so the page
	 * is not shown to users who could never connect.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * The MCP Adapter plugin's main file, relative to the plugins directory.
	 *
	 * @var string
	 */
	const ADAPTER_PLUGIN_FILE = 'mcp-adapter/mcp-adapter.php';

	/**
	 * Adapter status: loaded and running.
	 *
	 * @var string
	 */
	const ADAPTER_ACTIVE = 'active';

	/**
	 * Adapter status: installed but not activated.
	 *
	 * @var string
	 */
	const ADAPTER_INACTIVE = 'inactive';

	/**
	 * Adapter status: not installed.
	 *
	 * @var string
	 */
	const ADAPTER_MISSING = 'missing';

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * Whether the MCP Adapter is installed and active.
	 *
	 * "Active" is decided by the adapter's own class being loaded, which also
	 * covers installs as a must-use plugin or a Composer package.
	 *
	 * @return string One of the ADAPTER_* constants.
	 */
	public function get_adapter_status(): string {
		if ( class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			return self::ADAPTER_ACTIVE;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return array_key_exists( self::ADAPTER_PLUGIN_FILE, get_plugins() ) ? self::ADAPTER_INACTIVE : self::ADAPTER_MISSING;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'media-library-tools' ), 403 );
		}

		$endpoint = rest_url( McpServerRegistration::ROUTE_NAMESPACE . '/' . McpServerRegistration::ROUTE );
		$gateway  = rest_url( 'mcp/mcp-adapter-default-server' );
		$username = wp_get_current_user()->user_login;

		$claude_command = "read -rs WP_APP_PW   # paste the Application Password; nothing is shown\n"
			. "claude mcp add --transport http media-library-tools \\\n"
			. '  ' . $endpoint . " \\\n"
			. '  --header "Authorization: Basic $(printf \'%s:%s\' \'' . $username . '\' "$WP_APP_PW" | base64 | tr -d \'\\n\')"' . "\n"
			. 'unset WP_APP_PW';

		$json_config = wp_json_encode(
			[
				'mcpServers' => [
					'media-library-tools' => [
						'command' => 'npx',
						'args'    => [ '-y', '@automattic/mcp-wordpress-remote@latest' ],
						'env'     => [
							'WP_API_URL'      => $endpoint,
							'WP_API_USERNAME' => $username,
							'WP_API_PASSWORD' => 'your application password',
						],
					],
				],
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		?>
		<div class="wrap tsmlt-mcp-docs">
			<?php $this->render_styles(); ?>
			<h1><?php esc_html_e( 'MCP Docs', 'media-library-tools' ); ?></h1>

			<?php $this->render_status_notice( $this->get_adapter_status(), AbilitiesInit::is_supported() ); ?>

			<p class="tsmlt-mcp-docs__lead">
				<?php esc_html_e( 'Media Library Tools lets AI assistants that speak the Model Context Protocol (MCP) — such as Claude Code — search your media library, read media details, and update media text fields on your behalf.', 'media-library-tools' ); ?>
			</p>

			<div class="tsmlt-mcp-docs__section">
				<h2><?php esc_html_e( 'Requirements', 'media-library-tools' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'WordPress 6.9 or newer (the Abilities API is part of WordPress core from 6.9).', 'media-library-tools' ); ?></li>
					<li>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: link to the MCP Adapter plugin on WordPress.org. */
								esc_html__( 'The official %s plugin, active on this site. Tested with version 0.7.0.', 'media-library-tools' ),
								'<a href="https://wordpress.org/plugins/mcp-adapter/" target="_blank" rel="noopener noreferrer">MCP Adapter</a>'
							)
						);
						?>
					</li>
					<li><?php esc_html_e( 'A WordPress administrator account (the manage_options capability).', 'media-library-tools' ); ?></li>
				</ul>
			</div>

			<div class="tsmlt-mcp-docs__section">
				<h2><?php esc_html_e( 'Available tools', 'media-library-tools' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tool', 'media-library-tools' ); ?></th>
							<th><?php esc_html_e( 'What it does', 'media-library-tools' ); ?></th>
							<th><?php esc_html_e( 'Changes data', 'media-library-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>tsmlt-search-media</code></td>
							<td><?php esc_html_e( 'Lists matching attachments, one page at a time (up to 50 per page). Filter by text, MIME type, or missing alt text.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-media-details</code></td>
							<td><?php esc_html_e( 'Returns one attachment\'s title, alt text, caption, description, URL, MIME type, file name, dimensions, file size, image sizes, attached post, and upload date.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-update-media-metadata</code></td>
							<td><?php esc_html_e( 'Changes only the fields you send: title, alt text, caption, description. An empty string clears a field.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'media-library-tools' ); ?></td>
						</tr>
					</tbody>
				</table>
				<p>
					<?php esc_html_e( 'The tools never return server file paths, raw attachment metadata, EXIF data (including GPS location), or custom fields. They never rename, move, upload, or delete files.', 'media-library-tools' ); ?>
				</p>
			</div>

			<div class="tsmlt-mcp-docs__section">
				<h2><?php esc_html_e( 'Endpoint', 'media-library-tools' ); ?></h2>
				<p><?php esc_html_e( 'Connect your assistant to the dedicated Media Library Tools endpoint:', 'media-library-tools' ); ?></p>
				<p><code class="tsmlt-mcp-docs__url"><?php echo esc_html( $endpoint ); ?></code></p>
				<p>
					<?php esc_html_e( 'It offers only the three tools above and requires an administrator account before a session can start.', 'media-library-tools' ); ?>
				</p>
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: URL of the MCP Adapter default server. */
							esc_html__( 'The MCP Adapter also provides a default server at %s. It reaches every public ability from every plugin on the site, including abilities that change or delete data, so the dedicated endpoint above is the safer choice.', 'media-library-tools' ),
							'<code>' . esc_html( $gateway ) . '</code>'
						)
					);
					?>
				</p>
			</div>

			<div class="tsmlt-mcp-docs__section">
				<h2><?php esc_html_e( 'Setup', 'media-library-tools' ); ?></h2>

				<h3><?php esc_html_e( '1. Install the MCP Adapter', 'media-library-tools' ); ?></h3>
				<p><?php esc_html_e( 'Go to Plugins → Add New, search for "MCP Adapter", then install and activate it.', 'media-library-tools' ); ?></p>

				<h3><?php esc_html_e( '2. Create an Application Password', 'media-library-tools' ); ?></h3>
				<ol>
					<li>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: link to the current user's profile. */
								esc_html__( 'Open your %s and scroll to Application Passwords.', 'media-library-tools' ),
								'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '">' . esc_html__( 'profile', 'media-library-tools' ) . '</a>'
							)
						);
						?>
					</li>
					<li><?php esc_html_e( 'Enter a name such as "Claude Code – laptop" and click Add New Application Password.', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( 'Copy the password shown — it is displayed only once.', 'media-library-tools' ); ?></li>
				</ol>
				<p class="description">
					<?php esc_html_e( 'Create one password per assistant and device so you can revoke one without affecting the others. Never paste the password into an AI chat; enter it only into the client\'s configuration. WordPress only offers Application Passwords over HTTPS, or on sites marked as a local development environment.', 'media-library-tools' ); ?>
				</p>

				<h3><?php esc_html_e( '3. Connect your MCP client', 'media-library-tools' ); ?></h3>
				<h4><?php esc_html_e( 'Claude Code', 'media-library-tools' ); ?></h4>
				<p><?php esc_html_e( 'Run this in your own terminal (not inside a chat). It asks for the Application Password without showing it:', 'media-library-tools' ); ?></p>
				<pre class="tsmlt-mcp-docs__code"><code><?php echo esc_html( $claude_command ); ?></code></pre>
				<p><?php esc_html_e( 'Restart Claude Code, then run "claude mcp list" — the server should show Connected.', 'media-library-tools' ); ?></p>

				<h4><?php esc_html_e( 'Clients that use a JSON configuration file', 'media-library-tools' ); ?></h4>
				<p><?php esc_html_e( 'The MCP Adapter documents the @automattic/mcp-wordpress-remote helper for these clients (not yet tested with this endpoint):', 'media-library-tools' ); ?></p>
				<pre class="tsmlt-mcp-docs__code"><code><?php echo esc_html( $json_config ); ?></code></pre>

				<h3><?php esc_html_e( '4. Try it', 'media-library-tools' ); ?></h3>
				<ul>
					<li><?php esc_html_e( '"Find images in the media library that have no alt text."', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( '"Show me the details of attachment 1234."', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( '"Write alt text for attachment 1234 and save it."', 'media-library-tools' ); ?></li>
				</ul>
			</div>

			<div class="tsmlt-mcp-docs__section">
				<h2><?php esc_html_e( 'Access control', 'media-library-tools' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Who', 'media-library-tools' ); ?></th>
							<th><?php esc_html_e( 'Result on the dedicated endpoint', 'media-library-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Administrators', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Can use all three tools.', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Editors, authors, and other roles', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Refused before any tool is listed (HTTP 403).', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Requests without a valid Application Password', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Refused (HTTP 401).', 'media-library-tools' ); ?></td>
						</tr>
					</tbody>
				</table>
				<p>
					<?php esc_html_e( 'Every tool call also re-checks the administrator capability and the permission to read (or, for updates, edit) the specific attachment. Trashed and private attachments are reported as not found.', 'media-library-tools' ); ?>
				</p>
			</div>

			<div class="tsmlt-mcp-docs__section">
				<h2><?php esc_html_e( 'Important notes', 'media-library-tools' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'Review before saving: tsmlt-update-media-metadata overwrites the current value and cannot be undone. Keep your client\'s tool-approval prompts turned on.', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( 'Revoking access: delete the Application Password in your profile. The assistant loses access immediately.', 'media-library-tools' ); ?></li>
				</ul>
			</div>

			<div class="tsmlt-mcp-docs__section">
				<h2><?php esc_html_e( 'Troubleshooting', 'media-library-tools' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Symptom', 'media-library-tools' ); ?></th>
							<th><?php esc_html_e( 'Fix', 'media-library-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>404</code></td>
							<td><?php esc_html_e( 'Activate the MCP Adapter on WordPress 6.9 or newer.', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>401 Unauthorized</code></td>
							<td><?php esc_html_e( 'Check the username and Application Password, or create a new password and update the client.', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>403 Forbidden</code></td>
							<td><?php esc_html_e( 'Use an administrator account.', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( '"Tool not found"', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Expected for any tool other than the three above — the dedicated endpoint offers only those.', 'media-library-tools' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Message shown at the top of the page when the integration cannot run.
	 *
	 * Nothing is shown when the MCP Adapter is active on a WordPress version
	 * with the Abilities API. Uses the `inline` notice class so WordPress does
	 * not move the message out of the page.
	 *
	 * @param string $adapter_status    One of the ADAPTER_* constants.
	 * @param bool   $abilities_support Whether WordPress provides the Abilities API.
	 *
	 * @return void
	 */
	public function render_status_notice( string $adapter_status, bool $abilities_support ) {
		if ( ! $abilities_support ) {
			?>
			<div class="notice notice-error inline tsmlt-mcp-docs__notice">
				<p>
					<strong><?php esc_html_e( 'MCP is not available on this WordPress version.', 'media-library-tools' ); ?></strong>
					<?php esc_html_e( 'The MCP integration needs the Abilities API, which is part of WordPress 6.9 and newer. Update WordPress to use it.', 'media-library-tools' ); ?>
				</p>
			</div>
			<?php
		}

		if ( self::ADAPTER_MISSING === $adapter_status ) {
			?>
			<div class="notice notice-warning inline tsmlt-mcp-docs__notice">
				<p>
					<strong><?php esc_html_e( 'The MCP Adapter plugin is not installed.', 'media-library-tools' ); ?></strong>
					<?php esc_html_e( 'AI assistants cannot connect until it is installed and activated.', 'media-library-tools' ); ?>
				</p>
				<?php if ( current_user_can( 'install_plugins' ) ) : ?>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'plugin-install.php?s=mcp-adapter&tab=search&type=term' ) ); ?>">
							<?php esc_html_e( 'Install MCP Adapter', 'media-library-tools' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
			<?php
		} elseif ( self::ADAPTER_INACTIVE === $adapter_status ) {
			?>
			<div class="notice notice-warning inline tsmlt-mcp-docs__notice">
				<p>
					<strong><?php esc_html_e( 'The MCP Adapter plugin is installed but not active.', 'media-library-tools' ); ?></strong>
					<?php esc_html_e( 'AI assistants cannot connect until it is activated.', 'media-library-tools' ); ?>
				</p>
				<?php if ( current_user_can( 'activate_plugin', self::ADAPTER_PLUGIN_FILE ) ) : ?>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( self::ADAPTER_PLUGIN_FILE ) ), 'activate-plugin_' . self::ADAPTER_PLUGIN_FILE ) ); ?>">
							<?php esc_html_e( 'Activate MCP Adapter', 'media-library-tools' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Page styles, scoped to the page wrapper.
	 *
	 * @return void
	 */
	private function render_styles() {
		?>
		<style>
			/* Submenu arrows, as admin-settings.css draws them on the plugin's other pages; that stylesheet is not loaded here because its CSS reset would restyle this page. */
			ul#adminmenu span.dashicons-icons:before { content: "↳"; padding: 0 2px; font-size: 13px; }
			.current .tsmlt-submenu { color: #1677ff !important; }
			.tsmlt-mcp-docs { max-width: 960px; }
			.tsmlt-mcp-docs__lead { font-size: 14px; }
			.tsmlt-mcp-docs__notice { margin: 12px 0 20px; }
			.tsmlt-mcp-docs__section { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 4px 20px 16px; margin: 0 0 20px; }
			.tsmlt-mcp-docs__section table { margin: 12px 0; }
			.tsmlt-mcp-docs__url { font-size: 13px; padding: 6px 8px; display: inline-block; word-break: break-all; }
			.tsmlt-mcp-docs__code { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; padding: 12px; overflow-x: auto; }
			.tsmlt-mcp-docs__code code { background: none; padding: 0; white-space: pre; }
		</style>
		<?php
	}
}
