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
	 * The MCP Adapter plugin's slug on WordPress.org.
	 *
	 * @var string
	 */
	const ADAPTER_SLUG = 'mcp-adapter';

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
	 * Whether the MCP Adapter is running, installed but inactive, or missing.
	 *
	 * "Active" means the adapter is actually running on this site — not merely
	 * that its class can be loaded. Another plugin can bundle the adapter
	 * library without starting it; the endpoint only exists once something
	 * has started it (see is_adapter_running()).
	 *
	 * @return string One of the ADAPTER_* constants.
	 */
	public function get_adapter_status(): string {
		if ( $this->is_adapter_running() ) {
			return self::ADAPTER_ACTIVE;
		}

		return array_key_exists( self::ADAPTER_PLUGIN_FILE, $this->get_plugins() ) ? self::ADAPTER_INACTIVE : self::ADAPTER_MISSING;
	}

	/**
	 * Whether an MCP Adapter instance has been started on this request.
	 *
	 * The adapter does nothing until McpAdapter::instance() is called — by the
	 * MCP Adapter plugin, or by another plugin that bundles it and turns it
	 * on. instance() hooks the adapter's init onto `rest_api_init` (or `init`
	 * under WP-CLI), and init fires `mcp_adapter_init`, where this plugin's
	 * endpoint is created. The class itself is never autoloaded here.
	 *
	 * @return bool
	 */
	public function is_adapter_running(): bool {
		if ( did_action( 'mcp_adapter_init' ) ) {
			return true;
		}

		$class = 'WP\MCP\Core\McpAdapter';
		if ( ! class_exists( $class, false ) ) {
			return false;
		}

		global $wp_filter;
		foreach ( [ 'rest_api_init', 'init' ] as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) || ! $wp_filter[ $hook ] instanceof \WP_Hook ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'] ?? null;
					if ( is_array( $function ) && isset( $function[0] ) && $function[0] instanceof $class ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Name of the plugin the running adapter was loaded from, when that is not
	 * the MCP Adapter plugin itself — for example a plugin that bundles it.
	 *
	 * @return string Plugin name, or '' for the MCP Adapter plugin or when unknown.
	 */
	public function get_adapter_provider(): string {
		$class = 'WP\MCP\Core\McpAdapter';
		if ( ! class_exists( $class, false ) ) {
			return '';
		}

		$file    = wp_normalize_path( (string) ( new \ReflectionClass( $class ) )->getFileName() );
		$plugins = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
		if ( 0 !== strpos( $file, $plugins ) ) {
			return 0 === strpos( $file, trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) ) ) ? __( 'a must-use plugin', 'media-library-tools' ) : '';
		}

		$folder = strtok( substr( $file, strlen( $plugins ) ), '/' );
		if ( dirname( self::ADAPTER_PLUGIN_FILE ) === $folder ) {
			return '';
		}

		foreach ( $this->get_plugins() as $plugin_file => $data ) {
			if ( 0 === strpos( $plugin_file, $folder . '/' ) && ! empty( $data['Name'] ) ) {
				return (string) $data['Name'];
			}
		}

		return (string) $folder;
	}

	/**
	 * Version of the running adapter, when it can be read.
	 *
	 * @return string
	 */
	private function get_adapter_version(): string {
		if ( defined( 'WP_MCP_VERSION' ) ) {
			return (string) WP_MCP_VERSION;
		}
		if ( class_exists( 'WP\MCP\Core\McpAdapter', false ) && defined( 'WP\MCP\Core\McpAdapter::VERSION' ) ) {
			return (string) constant( 'WP\MCP\Core\McpAdapter::VERSION' );
		}
		return '';
	}

	/**
	 * Installed plugins.
	 *
	 * @return array<string, array>
	 */
	private function get_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugins();
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

		$claude_command         = $this->claude_code_command( 'media-library-tools', $endpoint, $username );
		$json_config            = $this->json_config( 'media-library-tools', $endpoint, $username );
		$gateway_claude_command = $this->claude_code_command( 'mcp-adapter-default', $gateway, $username );
		$gateway_json_config    = $this->json_config( 'mcp-adapter-default', $gateway, $username );
		$gateway_example        = (string) wp_json_encode(
			[
				'ability_name' => 'tsmlt/search-media',
				'parameters'   => [
					'mime_type'   => 'image',
					'missing_alt' => true,
				],
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
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
								esc_html__( 'The official %s plugin, installed and active. Tested with version 0.7.0.', 'media-library-tools' ),
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

				<h3><?php esc_html_e( 'Which endpoint should I use?', 'media-library-tools' ); ?></h3>
				<p><?php esc_html_e( 'Pick one:', 'media-library-tools' ); ?></p>
				<ul>
					<li>
						<strong><?php esc_html_e( 'Only Media Library Tools:', 'media-library-tools' ); ?></strong>
						<?php esc_html_e( 'use the dedicated endpoint above. It is simpler and narrower.', 'media-library-tools' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'One connection for everything', 'media-library-tools' ); ?></strong>
						<?php esc_html_e( '(media plus other plugins\' and WordPress core abilities): use the MCP Adapter default server by itself. It already includes the Media Library Tools abilities.', 'media-library-tools' ); ?>
					</li>
				</ul>
				<p>
					<strong><?php esc_html_e( 'Avoid configuring both:', 'media-library-tools' ); ?></strong>
					<?php esc_html_e( 'the media tools would appear twice, and the assistant may switch between them unpredictably.', 'media-library-tools' ); ?>
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

				<h4><?php esc_html_e( 'Claude Desktop and other clients with a JSON configuration file', 'media-library-tools' ); ?></h4>
				<p><?php esc_html_e( 'These clients connect through the @automattic/mcp-wordpress-remote helper, documented by the MCP Adapter. It needs Node.js installed on your computer.', 'media-library-tools' ); ?></p>
				<ol>
					<li>
						<?php esc_html_e( 'Open the client\'s configuration file. For Claude Desktop: Settings → Developer → Edit Config, which opens:', 'media-library-tools' ); ?>
						<ul>
							<li><?php esc_html_e( 'macOS:', 'media-library-tools' ); ?> <code>~/Library/Application Support/Claude/claude_desktop_config.json</code></li>
							<li><?php esc_html_e( 'Windows:', 'media-library-tools' ); ?> <code>%APPDATA%\Claude\claude_desktop_config.json</code></li>
						</ul>
					</li>
					<li><?php esc_html_e( 'Add this server. If the file already has an "mcpServers" section, add only the "media-library-tools" entry inside it:', 'media-library-tools' ); ?></li>
				</ol>
				<pre class="tsmlt-mcp-docs__code"><code><?php echo esc_html( $json_config ); ?></code></pre>
				<ol start="3">
					<li><?php esc_html_e( 'Replace "your application password" with the Application Password from step 2, save the file, and fully quit and reopen Claude Desktop.', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( 'In a new chat, open the tools menu: the three Media Library Tools tools should be listed.', 'media-library-tools' ); ?></li>
				</ol>

				<h3><?php esc_html_e( '4. Try it', 'media-library-tools' ); ?></h3>
				<ul>
					<li><?php esc_html_e( '"Find images in the media library that have no alt text."', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( '"Show me the details of attachment 1234."', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( '"Write alt text for attachment 1234 and save it."', 'media-library-tools' ); ?></li>
				</ul>
			</div>

			<div class="tsmlt-mcp-docs__section">
				<h2><?php esc_html_e( 'Using the MCP Adapter default server (optional)', 'media-library-tools' ); ?></h2>
				<p>
					<?php esc_html_e( 'You can also reach the same three tools through the MCP Adapter\'s default server. It is a gateway for every plugin\'s public abilities, so prefer the dedicated endpoint above unless a client needs this one.', 'media-library-tools' ); ?>
				</p>
				<p><code class="tsmlt-mcp-docs__url"><?php echo esc_html( $gateway ); ?></code></p>

				<h3><?php esc_html_e( 'How it differs', 'media-library-tools' ); ?></h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th></th>
							<th><?php esc_html_e( 'Dedicated endpoint', 'media-library-tools' ); ?></th>
							<th><?php esc_html_e( 'Default server', 'media-library-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Tools the client sees', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'The three Media Library Tools tools', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Three gateway tools that can run any public ability on the site, from every plugin', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Who can connect', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Administrators only', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Any logged-in user can list abilities; running a Media Library Tools ability still needs an administrator', 'media-library-tools' ); ?></td>
						</tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'The gateway tools', 'media-library-tools' ); ?></h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tool', 'media-library-tools' ); ?></th>
							<th><?php esc_html_e( 'What it does', 'media-library-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>mcp-adapter-discover-abilities</code></td>
							<td><?php esc_html_e( 'Lists the public abilities on the site.', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>mcp-adapter-get-ability-info</code></td>
							<td><?php esc_html_e( 'Shows one ability\'s description and input fields.', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>mcp-adapter-execute-ability</code></td>
							<td><?php esc_html_e( 'Runs an ability by its name with the given parameters.', 'media-library-tools' ); ?></td>
						</tr>
					</tbody>
				</table>
				<p><?php esc_html_e( 'On the default server, the Media Library Tools abilities are named with a slash:', 'media-library-tools' ); ?> <code>tsmlt/search-media</code>, <code>tsmlt/get-media-details</code>, <code>tsmlt/update-media-metadata</code>.</p>
				<p><?php esc_html_e( 'An execute-ability call looks like this:', 'media-library-tools' ); ?></p>
				<pre class="tsmlt-mcp-docs__code"><code><?php echo esc_html( $gateway_example ); ?></code></pre>

				<h3><?php esc_html_e( 'Connect', 'media-library-tools' ); ?></h3>
				<p><?php esc_html_e( 'Use the same Application Password as above. Claude Code:', 'media-library-tools' ); ?></p>
				<pre class="tsmlt-mcp-docs__code"><code><?php echo esc_html( $gateway_claude_command ); ?></code></pre>
				<p><?php esc_html_e( 'Claude Desktop and other JSON clients (add the "mcp-adapter-default" entry to the same configuration file):', 'media-library-tools' ); ?></p>
				<pre class="tsmlt-mcp-docs__code"><code><?php echo esc_html( $gateway_json_config ); ?></code></pre>

				<h3><?php esc_html_e( 'Try it', 'media-library-tools' ); ?></h3>
				<p><?php esc_html_e( 'The assistant usually discovers the ability first, then runs it. You can also name it directly:', 'media-library-tools' ); ?></p>
				<ul>
					<li><?php esc_html_e( '"Use execute-ability to run tsmlt/search-media for images with no alt text."', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( '"Run tsmlt/get-media-details for attachment 1234."', 'media-library-tools' ); ?></li>
				</ul>
				<p class="description">
					<?php esc_html_e( 'Use the ability name with a slash (tsmlt/search-media). The tool name with dashes (tsmlt-search-media) only exists on the dedicated endpoint, and the default server reports it as not found.', 'media-library-tools' ); ?>
				</p>
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
	 * Claude Code command that adds an MCP server, asking for the Application
	 * Password without echoing it.
	 *
	 * @param string $name     Server name in the client.
	 * @param string $url      MCP endpoint URL.
	 * @param string $username WordPress username.
	 *
	 * @return string
	 */
	private function claude_code_command( string $name, string $url, string $username ): string {
		return "read -rs WP_APP_PW   # paste the Application Password; nothing is shown\n"
			. 'claude mcp add --transport http ' . $name . " \\\n"
			. '  ' . $url . " \\\n"
			. '  --header "Authorization: Basic $(printf \'%s:%s\' \'' . $username . '\' "$WP_APP_PW" | base64 | tr -d \'\\n\')"' . "\n"
			. 'unset WP_APP_PW';
	}

	/**
	 * JSON configuration for clients such as Claude Desktop, which connect
	 * through the mcp-wordpress-remote helper from Automattic.
	 *
	 * @param string $name     Server name in the client.
	 * @param string $url      MCP endpoint URL.
	 * @param string $username WordPress username.
	 *
	 * @return string
	 */
	private function json_config( string $name, string $url, string $username ): string {
		return (string) wp_json_encode(
			[
				'mcpServers' => [
					$name => [
						'command' => 'npx',
						'args'    => [ '-y', '@automattic/mcp-wordpress-remote@latest' ],
						'env'     => [
							'WP_API_URL'      => $url,
							'WP_API_USERNAME' => $username,
							'WP_API_PASSWORD' => 'your application password',
						],
					],
				],
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	}

	/**
	 * Status box at the top of the page.
	 *
	 * Always shown: green when the MCP Adapter is active on a WordPress
	 * version with the Abilities API, otherwise a warning with an Install or
	 * Activate button. Uses the `inline` notice class so WordPress does not
	 * move the box out of the page.
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
				<?php
				if ( current_user_can( 'install_plugins' ) ) {
					$this->render_adapter_button( 'install', __( 'Install MCP Adapter plugin', 'media-library-tools' ), $this->get_install_url() );
				}
				?>
			</div>
			<?php
		} elseif ( self::ADAPTER_INACTIVE === $adapter_status ) {
			?>
			<div class="notice notice-warning inline tsmlt-mcp-docs__notice">
				<p>
					<strong><?php esc_html_e( 'The MCP Adapter plugin is installed but not active.', 'media-library-tools' ); ?></strong>
					<?php esc_html_e( 'AI assistants cannot connect until it is activated.', 'media-library-tools' ); ?>
				</p>
				<?php
				if ( current_user_can( 'activate_plugin', self::ADAPTER_PLUGIN_FILE ) ) {
					$this->render_adapter_button( 'activate', __( 'Activate MCP Adapter plugin', 'media-library-tools' ), $this->get_activate_url() );
				}
				?>
			</div>
			<?php
		} elseif ( self::ADAPTER_ACTIVE === $adapter_status && $abilities_support ) {
			$version  = $this->get_adapter_version();
			$provider = $this->get_adapter_provider();
			if ( '' !== $provider ) {
				/* translators: 1: plugin name, e.g. WooCommerce. */
				$status = sprintf( __( 'The MCP Adapter is running, provided by %1$s.', 'media-library-tools' ), $provider );
			} elseif ( '' !== $version ) {
				/* translators: %s: MCP Adapter version, e.g. 0.7.0. */
				$status = sprintf( __( 'The MCP Adapter plugin is active (version %s).', 'media-library-tools' ), $version );
			} else {
				$status = __( 'The MCP Adapter plugin is active.', 'media-library-tools' );
			}
			?>
			<div class="notice notice-success inline tsmlt-mcp-docs__notice">
				<p>
					<strong><?php echo esc_html( $status ); ?></strong>
					<?php esc_html_e( 'AI assistants can connect to the endpoint below.', 'media-library-tools' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Install or activate button for the MCP Adapter, done over AJAX.
	 *
	 * The script calls WordPress core's own `install-plugin` and
	 * `activate-plugin` AJAX actions, which check the `updates` nonce and the
	 * install_plugins / activate_plugin capabilities themselves; after an
	 * install it activates the plugin, then reloads the page. The link's href
	 * is the matching non-AJAX core screen, used if the script cannot run or
	 * the request fails (for example when the server needs FTP credentials).
	 *
	 * @param string $step     `install` or `activate`.
	 * @param string $label    Button label.
	 * @param string $fallback Non-AJAX URL for the same action.
	 *
	 * @return void
	 */
	private function render_adapter_button( string $step, string $label, string $fallback ) {
		$config = [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'updates' ),
			'slug'    => self::ADAPTER_SLUG,
			'plugin'  => self::ADAPTER_PLUGIN_FILE,
			'name'    => 'MCP Adapter',
			'i18n'    => [
				'installing' => __( 'Installing MCP Adapter…', 'media-library-tools' ),
				'activating' => __( 'Activating MCP Adapter…', 'media-library-tools' ),
				'done'       => __( 'Done. Reloading…', 'media-library-tools' ),
				'failed'     => __( 'Something went wrong.', 'media-library-tools' ),
				'fallback'   => __( 'Try again from the Plugins screen.', 'media-library-tools' ),
			],
		];
		?>
		<p>
			<a class="button button-primary tsmlt-mcp-adapter-action" data-step="<?php echo esc_attr( $step ); ?>" href="<?php echo esc_url( $fallback ); ?>"><?php echo esc_html( $label ); ?></a>
		</p>
		<p class="tsmlt-mcp-adapter-feedback" role="status" aria-live="polite"></p>
		<script>
		( function () {
			var config = <?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON in a script block, with <, >, &, quotes hex-encoded. ?>;
			var button = document.querySelector( '.tsmlt-mcp-adapter-action' );
			var feedback = document.querySelector( '.tsmlt-mcp-adapter-feedback' );
			if ( ! button || ! feedback || ! window.fetch || ! window.FormData ) {
				return;
			}
			var label = button.textContent;

			function post( action, fields ) {
				var body = new FormData();
				body.append( 'action', action );
				body.append( '_ajax_nonce', config.nonce );
				Object.keys( fields ).forEach( function ( key ) {
					body.append( key, fields[ key ] );
				} );
				return fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( result ) {
						if ( ! result || ! result.success ) {
							var message = result && result.data && result.data.errorMessage;
							throw new Error( Array.isArray( message ) ? message.join( ' ' ) : ( message || config.i18n.failed ) );
						}
						return result.data;
					} );
			}

			function activate() {
				button.textContent = config.i18n.activating;
				return post( 'activate-plugin', { slug: config.slug, plugin: config.plugin, name: config.name } );
			}

			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				if ( 'true' === button.getAttribute( 'aria-disabled' ) ) {
					return;
				}
				// Core's spinning update icon (wp-admin/css/common.css), as on the Plugins screen.
				button.setAttribute( 'aria-disabled', 'true' );
				button.setAttribute( 'aria-busy', 'true' );
				button.classList.add( 'disabled', 'updating-message' );
				feedback.textContent = '';

				var run;
				if ( 'install' === button.getAttribute( 'data-step' ) ) {
					button.textContent = config.i18n.installing;
					run = post( 'install-plugin', { slug: config.slug } ).then( activate );
				} else {
					run = activate();
				}

				run.then( function () {
					button.classList.remove( 'updating-message' );
					button.classList.add( 'updated-message' );
					button.textContent = config.i18n.done;
					window.location.reload();
				} ).catch( function ( error ) {
					var link = document.createElement( 'a' );
					link.href = button.href;
					link.textContent = config.i18n.fallback;
					feedback.textContent = ( error && error.message ? error.message : config.i18n.failed ) + ' ';
					feedback.appendChild( link );
					button.textContent = label;
					button.removeAttribute( 'aria-disabled' );
					button.removeAttribute( 'aria-busy' );
					button.classList.remove( 'disabled', 'updating-message' );
				} );
			} );
		}() );
		</script>
		<?php
	}

	/**
	 * Non-AJAX install of the MCP Adapter from WordPress.org: WordPress's own
	 * installer screen (update.php?action=install-plugin), which checks the
	 * nonce and install_plugins itself and can ask for FTP credentials.
	 *
	 * @return string
	 */
	public function get_install_url(): string {
		return wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=' . self::ADAPTER_SLUG ),
			'install-plugin_' . self::ADAPTER_SLUG
		);
	}

	/**
	 * Non-AJAX activation of the MCP Adapter: WordPress's own activate link
	 * on the Plugins screen, protected by its nonce.
	 *
	 * @return string
	 */
	public function get_activate_url(): string {
		return wp_nonce_url(
			admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( self::ADAPTER_PLUGIN_FILE ) ),
			'activate-plugin_' . self::ADAPTER_PLUGIN_FILE
		);
	}

	/**
	 * Page styles, scoped to the page wrapper.
	 *
	 * @return void
	 */
	private function render_styles() {
		?>
		<style>
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
