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
use TinySolutions\mlt\Modules\Trash\MediaTrash;
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
	 * Filter that overrides the suggested MCP client configuration key. See
	 * get_client_server_key().
	 *
	 * @var string
	 */
	const CLIENT_KEY_FILTER = 'tsmlt_mcp_client_server_key';

	/**
	 * Prefix of the suggested MCP client configuration key.
	 *
	 * @var string
	 */
	const CLIENT_KEY_PREFIX = 'mlt-';

	/**
	 * Endpoint identifier appended to the key for the MCP Adapter default server.
	 *
	 * @var string
	 */
	const CLIENT_KEY_DEFAULT_SERVER = 'mcp-adapter-default-server';

	/**
	 * Endpoint identifier appended to the key for the dedicated endpoint.
	 *
	 * @var string
	 */
	const CLIENT_KEY_DEDICATED = 'media-library-tools';

	/**
	 * Longest suggested key, endpoint identifier included; for longer ones
	 * the site part is cut and gets a hash suffix.
	 *
	 * @var int
	 */
	const CLIENT_KEY_MAX_LENGTH = 64;

	/**
	 * Length of the hash suffix of a cut or lossy key.
	 *
	 * @var int
	 */
	const CLIENT_KEY_HASH_LENGTH = 8;

	/**
	 * Characters a key returned by CLIENT_KEY_FILTER may use. The key is
	 * printed into a shell command, so nothing that needs quoting.
	 *
	 * @var string
	 */
	const CLIENT_KEY_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

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

		// Suggested client keys: the site address plus the endpoint.
		$client_key             = $this->get_client_server_key( self::CLIENT_KEY_DEDICATED );
		$gateway_client_key     = $this->get_client_server_key( self::CLIENT_KEY_DEFAULT_SERVER );
		$claude_command         = $this->claude_code_command( $client_key, $endpoint, $username );
		$json_config            = $this->json_config( $client_key, $endpoint, $username );
		$gateway_claude_command = $this->claude_code_command( $gateway_client_key, $gateway, $username );
		$gateway_json_config    = $this->json_config( $gateway_client_key, $gateway, $username );
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
							<td><?php esc_html_e( 'Lists matching attachments, one page at a time (up to 50 per page). Filter by text, part of the file name (filename), MIME type, Group (group_id), attached or unattached to a post (attached — not whether the media is used in content), or missing alt text.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-media-details</code></td>
							<td><?php esc_html_e( 'Returns one attachment\'s title, alt text, caption, description, URL, MIME type, file name, dimensions, file size, image sizes, attached post, and upload date.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-exif-fields</code></td>
							<td><?php esc_html_e( 'Returns one image\'s camera and lens make and model, software, dates taken and digitized, ISO, aperture, shutter speed, focal length, exposure program and mode, metering mode, white balance, flash and description, from the JPEG file or values saved in the EXIF editor. Never location, time zone offsets, copyright or artist.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-update-media-metadata</code></td>
							<td><?php esc_html_e( 'Changes only the fields you send: title, alt text, caption, description. An empty string clears a field.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-list-media-groups</code></td>
							<td><?php esc_html_e( 'Lists media Groups with their ID, name, parent Group and number of media items; filter by name or ID.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-create-media-group</code></td>
							<td><?php esc_html_e( 'Creates one media Group, at the top level or under an existing Group. A name already used under the same parent is refused.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-update-media-group</code></td>
							<td><?php esc_html_e( 'Renames one media Group; its ID, slug, parent and media stay the same. A name another Group already has under the same parent is refused.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-media-usage</code></td>
							<td><?php esc_html_e( 'Reports where up to 50 attachments are used: as featured images, in content, page builders and custom fields, and site-wide (logo, site icon, menus, widgets, site options), from the records of the Used Where scan. Says whether each item is used, not used, or unknown when no scan has completed.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-start-usage-scan</code></td>
							<td><?php esc_html_e( 'Starts the Used Where scan in the background, or reports the scan already running (a stalled one is resumed). A new scan replaces the previous usage results while it runs. Never changes media files; keeps each image\'s existing attached post and sets one only for an image that has none, from a post that uses it.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (usage scan results)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-usage-scan-status</code></td>
							<td><?php esc_html_e( 'Returns the Used Where scan state (never started, queued, running, complete, cancelled, error), progress, and whether it is stalled or failed. Never starts or resumes a scan.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-list-media-usage</code></td>
							<td><?php esc_html_e( 'Lists media by usage (used, unused or unknown), one page at a time, after a completed Used Where scan, with each item\'s title, file name, type and, for used media, the usage types; unknown media is never shown as unused. Never starts a scan or changes results.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-start-exif-privacy-scan</code></td>
							<td><?php esc_html_e( 'Starts the EXIF scan in the background (the same scan as the EXIF Data page), which records per image whether its EXIF holds location (GPS), camera, date taken, author or copyright data, or reports the scan already running (a stalled one is resumed). Never changes media files or EXIF.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (EXIF scan results)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-exif-privacy-scan-status</code></td>
							<td><?php esc_html_e( 'Returns the EXIF scan state (never started, running, complete, cancelled), progress, and whether it is stalled. Never starts or resumes a scan.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-list-exif-privacy-results</code></td>
							<td><?php esc_html_e( 'Lists media by EXIF privacy result, one page at a time, after a completed EXIF scan: media with sensitive EXIF and which categories it holds (never the values or GPS coordinates), or media without EXIF, unsupported, missing, unreadable or not scanned. Never starts a scan or reads files.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-set-media-groups</code></td>
							<td><?php esc_html_e( 'Adds, removes or replaces the Groups of one attachment, using existing Group IDs.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-find-duplicate-media</code></td>
							<td><?php esc_html_e( 'Lists groups of identical media files, largest first, from the last duplicate scan (Duplicates page), with each file\'s size and, per attachment, its details, parent post and usage from the Used Where scan. Never starts a scan, merges or deletes.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-start-duplicate-scan</code></td>
							<td><?php esc_html_e( 'Starts the duplicate scan in the background, or reports the scan already running (a stalled one is resumed). Shares its start lock with the Duplicates page. Never changes or deletes media files.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (duplicate scan results)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-duplicate-scan-status</code></td>
							<td><?php esc_html_e( 'Returns the duplicate scan state (never started, running, complete, cancelled), progress, and whether it is stalled. Never starts or resumes a scan.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-list-trashed-media</code></td>
							<td><?php esc_html_e( 'Lists media in the WordPress trash, one page at a time, with when each item was trashed and the earliest date WordPress may delete it permanently.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-preview-trash-media</code></td>
							<td><?php esc_html_e( 'Checks whether up to 20 attachments can be moved to the trash and returns a one-time confirmation token. Only media a complete, verified Used Where scan found unused qualifies, and not while a saved post\'s usage rescan is pending or running; used or unknown media, the site icon and the site logo are refused. Changes no media.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No media (stores a short-lived confirmation token)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-trash-media</code></td>
							<td><?php esc_html_e( 'Moves the previewed attachments to the WordPress trash, after you confirm, using the token from tsmlt-preview-trash-media. Checks every rule again and reports each item. Refuses everything when the site\'s trash is turned off (EMPTY_TRASH_DAYS is 0), and never deletes media permanently. Trashed media stops showing on the site.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (moves media to the trash)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-restore-media</code></td>
							<td><?php esc_html_e( 'Restores up to 20 attachments from the WordPress trash and reports each item. A restore that fails leaves the item in the trash with its trash date.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-export-media-csv</code></td>
							<td><?php esc_html_e( 'Exports up to 100 attachments, chosen by ID, as one CSV text in the order given: ID, slug, URL, title, alt text, caption, description, attached post, menu order and Groups — the columns the CSV import reads. IDs that are missing or not readable are listed as skipped. Never exports custom fields, file paths or metadata.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-preview-media-rename</code></td>
							<td><?php esc_html_e( 'Previews renaming the files of up to 20 attachments to names you type: the current and resulting file name (the extension is kept), whether the name was cleaned up or would get a -1 style suffix because it is taken, and how many image sizes a rename would make again. Renames nothing. Renaming by post title, SKU, alt text or AI is not available here.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-compression-status</code></td>
							<td><?php esc_html_e( 'Returns the image compression status of up to 50 attachments, as Compress Images recorded it: completed, skipped, failed, not processed or unsupported, the original and current size, the space saved, the mode, quality and engine used, when, whether a backup of the original exists, and the result per image size. Error messages are not returned. Never compresses or changes anything.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-exif-caption</code></td>
							<td><?php esc_html_e( 'Pro. Builds a caption from one image\'s camera make and model and exposure (aperture, shutter speed, ISO) and returns it without saving.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-update-exif-fields</code></td>
							<td><?php esc_html_e( 'Pro. Sets or clears EXIF fields of one image: camera, lens, exposure, dates, artist, copyright, description. Edits JPEG files; other image types keep the values in the plugin\'s database only. Location, orientation and serial numbers cannot be changed.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-export-media-csv-bulk</code></td>
							<td><?php esc_html_e( 'Pro. Exports the whole media library, or everything matching the tsmlt-search-media filters, as CSV files in the background: one file, or split evenly into up to 20 files with the same header and columns as tsmlt-export-media-csv. Returns an export job to follow with tsmlt-get-media-csv-export. Runs through WP-Cron (a system cron is recommended); without it, tsmlt-continue-media-csv-export moves it on. Custom fields, file paths and attachment metadata are never exported.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No media (writes export files kept for 24 hours)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-media-csv-export</code></td>
							<td><?php esc_html_e( 'Pro. Shows an export job\'s progress and, for each finished file, a download link valid for 15 minutes (only for the user who started the export; ask again for a new link).', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-continue-media-csv-export</code></td>
							<td><?php esc_html_e( 'Pro. Advances an export by one batch, for sites where WP-Cron does not run it in the background. Safe to repeat; only the user who started the export can continue it.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No media (writes export files)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-preview-media-csv-import</code></td>
							<td><?php esc_html_e( 'Pro. Checks CSV text (up to 256 KB and 50 rows) that updates existing media, and shows row by row what would change: title, alt text, caption, description, attached post, menu order and Groups. Changes nothing; when some rows would change, returns a plan you can apply within about 15 minutes.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-apply-media-csv-import</code></td>
							<td><?php esc_html_e( 'Pro. Applies a plan from tsmlt-preview-media-csv-import once, after you confirm it. Rows changed by someone else since the preview are skipped, never overwritten. Never creates, renames or downloads files.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-preview-gps-removal</code></td>
							<td><?php esc_html_e( 'Pro. Checks up to 50 images and shows, per image, whether GPS location data was found (never the coordinates) or why it cannot be cleaned. Changes nothing; returns a plan for tsmlt-remove-gps-metadata.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-remove-gps-metadata</code></td>
							<td><?php esc_html_e( 'Pro. Removes GPS location data from the images of a plan from tsmlt-preview-gps-removal once, after you confirm it. Only location tags are removed; other EXIF is kept; files are replaced only after a verified write. Cannot be undone.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (JPEG files)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-preview-bulk-exif-edit</code></td>
							<td><?php esc_html_e( 'Pro. Checks setting or clearing EXIF fields on up to 50 images and shows, per image and field, whether a value would be added, overwrite an existing one (only when you allow it), stay unchanged or be cleared, never the values. Changes nothing; returns a plan for tsmlt-apply-bulk-exif-edit.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-apply-bulk-exif-edit</code></td>
							<td><?php esc_html_e( 'Pro. Saves a plan from tsmlt-preview-bulk-exif-edit once, after you confirm it; images changed since the preview are skipped. Each change is logged and can be undone.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (EXIF)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-list-exif-change-logs</code></td>
							<td><?php esc_html_e( 'Pro. Lists one image\'s EXIF change log, newest first: action, time, user and the names of the changed fields, never the values.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-preview-exif-undo</code></td>
							<td><?php esc_html_e( 'Pro. Shows whether one image\'s newest EXIF change can be undone and which fields it would restore or clear. Changes nothing; returns a plan for tsmlt-undo-exif-change.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-undo-exif-change</code></td>
							<td><?php esc_html_e( 'Pro. Undoes the change of a plan from tsmlt-preview-exif-undo once, after you confirm it, only while it is still the newest change.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (EXIF)', 'media-library-tools' ); ?></td>
						</tr>
					</tbody>
				</table>
				<p>
					<?php esc_html_e( 'The tools never return server file paths, raw attachment metadata, raw EXIF data, GPS coordinates, copyright or artist text, camera serial numbers, or custom fields; the EXIF privacy and GPS tools report only whether such data is present. They never rename, move, upload, or delete files, never delete media permanently, and never delete or move Groups; tsmlt-create-media-group adds Groups and tsmlt-update-media-group renames them. Only tsmlt-trash-media moves media to the WordPress trash, after a preview and your confirmation, and tsmlt-restore-media brings it back. Only the Pro EXIF tools change EXIF data, inside JPEG files: tsmlt-update-exif-fields, and, after a preview and your confirmation, tsmlt-apply-bulk-exif-edit, tsmlt-remove-gps-metadata and tsmlt-undo-exif-change; the Pro tools are offered only with an active Media Library Tools Pro licence. Exporting selected media as CSV is free (tsmlt-export-media-csv); exporting the whole library to CSV files and CSV import through MCP are Pro features.', 'media-library-tools' ); ?>
					<?php esc_html_e( 'Every tool above is always listed on the MCP Adapter\'s default server, with no setting to turn on, and on the dedicated Media Library Tools endpoint, with the same name and the same permission checks. The Pro tools are listed while Media Library Tools Pro is active with a valid licence.', 'media-library-tools' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'MCP supports Trash and Restore only: there is no tool to delete media permanently or empty the trash.', 'media-library-tools' ); ?>
					<?php echo esc_html( MediaTrash::permanent_delete_guidance() ); ?>
					<?php esc_html_e( 'WordPress\'s scheduled trash cleanup permanently deletes trashed media EMPTY_TRASH_DAYS days (30 by default) after it was trashed, and if EMPTY_TRASH_DAYS is later set to 0 or a negative number, its next cleanup can permanently delete everything already in the trash. Restore anything you want to keep first.', 'media-library-tools' ); ?>
				</p>
			</div>

			<div class="tsmlt-mcp-docs__section">
				<h2><?php esc_html_e( 'Endpoint', 'media-library-tools' ); ?></h2>
				<p><?php esc_html_e( 'Connect your assistant to one of these endpoints. Both offer every Media Library Tools tool above — the Free tools, and the Pro tools while the Pro licence is active — with the same names and the same permission checks.', 'media-library-tools' ); ?></p>
				<ul>
					<li>
						<strong><?php esc_html_e( 'MCP Adapter default server (recommended):', 'media-library-tools' ); ?></strong>
						<code class="tsmlt-mcp-docs__url"><?php echo esc_html( $gateway ); ?></code>
						<?php esc_html_e( 'It always lists the Media Library Tools tools, together with the MCP Adapter\'s gateway tools, which can reach every public ability from every plugin on the site, including abilities that change or delete data.', 'media-library-tools' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Dedicated Media Library Tools endpoint (alternative):', 'media-library-tools' ); ?></strong>
						<code class="tsmlt-mcp-docs__url"><?php echo esc_html( $endpoint ); ?></code>
						<?php esc_html_e( 'It offers only the tools above and requires an administrator account before a session can start.', 'media-library-tools' ); ?>
					</li>
				</ul>

				<h3><?php esc_html_e( 'Which endpoint should I use?', 'media-library-tools' ); ?></h3>
				<p><?php esc_html_e( 'Pick one:', 'media-library-tools' ); ?></p>
				<ul>
					<li>
						<strong><?php esc_html_e( 'One connection for everything (recommended):', 'media-library-tools' ); ?></strong>
						<?php esc_html_e( 'use the MCP Adapter default server by itself. It includes every Media Library Tools tool, plus other plugins\' and WordPress core abilities.', 'media-library-tools' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Only Media Library Tools:', 'media-library-tools' ); ?></strong>
						<?php esc_html_e( 'use the dedicated endpoint. It is narrower and also works when the default server is turned off.', 'media-library-tools' ); ?>
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
				<p class="tsmlt-mcp-docs__one-config">
					<strong><?php esc_html_e( 'You only need ONE of these configurations. Choose either the recommended Default MCP Adapter or the Dedicated Media Library Tools endpoint. Do not add both unless you intentionally want separate connections.', 'media-library-tools' ); ?></strong>
				</p>
				<ul>
					<li>
						<strong><?php esc_html_e( 'Option 1: Default WordPress MCP Adapter — Recommended.', 'media-library-tools' ); ?></strong>
						<?php esc_html_e( 'Always provides the Media Library Tools Free abilities, and the Pro abilities while the Pro licence is active, through one connection, including CSV export and the Pro CSV Export MCP App. It may also expose other plugins\' public abilities.', 'media-library-tools' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Option 2: Dedicated Media Library Tools MCP Server — Alternative.', 'media-library-tools' ); ?></strong>
						<?php esc_html_e( 'Provides isolated Media Library Tools access and remains available as a fallback.', 'media-library-tools' ); ?>
					</li>
				</ul>
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: 1: suggested client key for the default server, 2: suggested client key for the dedicated endpoint. */
							esc_html__( 'The server names (%1$s and %2$s) are suggested from this site\'s address and the endpoint, so you can tell several sites apart; you can rename them.', 'media-library-tools' ),
							'<code>' . esc_html( $gateway_client_key ) . '</code>',
							'<code>' . esc_html( $client_key ) . '</code>'
						)
					);
					?>
				</p>

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
					<li><?php esc_html_e( 'Add one server. If the file already has an "mcpServers" section, add only that server\'s entry inside it.', 'media-library-tools' ); ?></li>
				</ol>
				<p><strong><?php esc_html_e( 'Option 1: Default WordPress MCP Adapter — Recommended', 'media-library-tools' ); ?></strong></p>
				<pre class="tsmlt-mcp-docs__code"><code><?php echo esc_html( $gateway_json_config ); ?></code></pre>
				<p><strong><?php esc_html_e( 'Option 2: Dedicated Media Library Tools MCP Server — Alternative', 'media-library-tools' ); ?></strong></p>
				<pre class="tsmlt-mcp-docs__code"><code><?php echo esc_html( $json_config ); ?></code></pre>
				<ol start="3">
					<li><?php esc_html_e( 'Replace "your application password" with the Application Password from step 2, save the file, and fully quit and reopen Claude Desktop.', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( 'In a new chat, open the tools menu: the Media Library Tools tools should be listed.', 'media-library-tools' ); ?></li>
				</ol>

				<h4><?php esc_html_e( 'Claude Code', 'media-library-tools' ); ?></h4>
				<p><?php esc_html_e( 'Run one of these in your own terminal (not inside a chat). It asks for the Application Password without showing it.', 'media-library-tools' ); ?></p>
				<p><strong><?php esc_html_e( 'Option 1: Default WordPress MCP Adapter — Recommended', 'media-library-tools' ); ?></strong></p>
				<pre class="tsmlt-mcp-docs__code"><code><?php echo esc_html( $gateway_claude_command ); ?></code></pre>
				<p><strong><?php esc_html_e( 'Option 2: Dedicated Media Library Tools MCP Server — Alternative', 'media-library-tools' ); ?></strong></p>
				<pre class="tsmlt-mcp-docs__code"><code><?php echo esc_html( $claude_command ); ?></code></pre>
				<p><?php esc_html_e( 'Restart Claude Code, then run "claude mcp list" — the server should show Connected.', 'media-library-tools' ); ?></p>

				<h3><?php esc_html_e( '4. Try it', 'media-library-tools' ); ?></h3>
				<ul>
					<li><?php esc_html_e( '"Find images in the media library that have no alt text."', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( '"Show me the details of attachment 1234."', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( '"Write alt text for attachment 1234 and save it."', 'media-library-tools' ); ?></li>
				</ul>
			</div>

			<div class="tsmlt-mcp-docs__section">
				<h2><?php esc_html_e( 'Using the MCP Adapter default server', 'media-library-tools' ); ?></h2>
				<p>
					<?php esc_html_e( 'The MCP Adapter\'s default server always lists every Media Library Tools tool directly, with the same names as the dedicated endpoint (tsmlt-search-media, tsmlt-export-media-csv, …), including the Pro tools and the CSV Export MCP App view while the Pro licence is active. Each tool still checks its own permissions, and users who cannot run them do not see them listed. It requires MCP Adapter 0.5.0 or newer; with an older adapter, use the dedicated endpoint.', 'media-library-tools' ); ?>
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
							<td><?php esc_html_e( 'The Media Library Tools tools', 'media-library-tools' ); ?></td>
							<td>
								<?php esc_html_e( 'The Media Library Tools tools, plus three gateway tools that can run any public ability on the site, from every plugin', 'media-library-tools' ); ?>
							</td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Who can connect', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Administrators only', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Any logged-in user; only administrators see and can run the Media Library Tools tools', 'media-library-tools' ); ?></td>
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
				<p><?php esc_html_e( 'Through the gateway\'s execute-ability tool, only the read-only search and details abilities can be run, named with a slash:', 'media-library-tools' ); ?> <code>tsmlt/search-media</code>, <code>tsmlt/get-media-details</code>.</p>
				<p><?php esc_html_e( 'An execute-ability call looks like this:', 'media-library-tools' ); ?></p>
				<pre class="tsmlt-mcp-docs__code"><code><?php echo esc_html( $gateway_example ); ?></code></pre>

				<h3><?php esc_html_e( 'Connect', 'media-library-tools' ); ?></h3>
				<p><?php esc_html_e( 'Use Option 1 under "3. Connect your MCP client" above, with the same Application Password.', 'media-library-tools' ); ?></p>

				<h3><?php esc_html_e( 'Try it', 'media-library-tools' ); ?></h3>
				<p><?php esc_html_e( 'The assistant usually discovers the ability first, then runs it. You can also name it directly:', 'media-library-tools' ); ?></p>
				<ul>
					<li><?php esc_html_e( '"Use execute-ability to run tsmlt/search-media for images with no alt text."', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( '"Run tsmlt/get-media-details for attachment 1234."', 'media-library-tools' ); ?></li>
				</ul>
				<p class="description">
					<?php esc_html_e( 'Call the Media Library Tools tools directly by their names with dashes (tsmlt-search-media). Through execute-ability, only the read-only search and details abilities are available, by their names with a slash (tsmlt/search-media).', 'media-library-tools' ); ?>
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
							<td><?php esc_html_e( 'Can use all the tools.', 'media-library-tools' ); ?></td>
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
					<?php esc_html_e( 'Every tool call also re-checks the administrator capability and the permission to read (or, for updates, edit; for trash and restore, delete) the specific attachment. Except for the trash tools, trashed and private attachments are reported as not found.', 'media-library-tools' ); ?>
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
							<td><?php esc_html_e( 'Expected for any tool other than those above — the dedicated endpoint offers only those.', 'media-library-tools' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Suggested key for one of this site's endpoints in an MCP client's
	 * configuration (the `mcpServers` entry name, or the name given to
	 * `claude mcp add`), so people who connect several sites can tell them
	 * apart.
	 *
	 * "mlt-", the site part, then the endpoint identifier
	 * (CLIENT_KEY_DEFAULT_SERVER or CLIENT_KEY_DEDICATED):
	 * http://plugindev.local gives mlt-plugindev-local-mcp-adapter-default-server
	 * and mlt-plugindev-local-media-library-tools. The site part is built from
	 * the site address: the host in lower case (international domain names in
	 * their ASCII "xn--" form; "www." kept), a port other than the scheme's
	 * default, and the path of a subdirectory install, with every run of other
	 * characters turned into one "-". When the key would be longer than
	 * CLIENT_KEY_MAX_LENGTH, or the site part lost characters (a domain name
	 * that cannot be converted to ASCII without the intl extension, or a path
	 * with other characters), the site part is cut and ends in a short hash of
	 * the address, so two sites do not share a key; the endpoint identifier
	 * is always kept.
	 *
	 * Only the suggestion in the client snippets changes; server IDs, routes
	 * and tool names do not. The key holds nothing beyond the public site
	 * address.
	 *
	 * @param string $endpoint Endpoint identifier.
	 * @param string $url      Site address; home_url() by default.
	 *
	 * @return string
	 */
	public function get_client_server_key( string $endpoint, string $url = '' ): string {
		$url      = '' === $url ? home_url() : $url;
		$endpoint = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $endpoint ) ), '-' );

		// parse_url() turns some bytes of non-ASCII characters (such as the
		// second byte of "Ü") into "_", so they are encoded first.
		$encoded = (string) preg_replace_callback(
			'/[^\x00-\x7F]+/',
			static function ( $matches ) {
				return rawurlencode( $matches[0] );
			},
			$url
		);
		$parts   = wp_parse_url( $encoded );
		$parts   = is_array( $parts ) ? $parts : [];

		$host = rawurldecode( (string) ( $parts['host'] ?? '' ) );
		$host = function_exists( 'mb_strtolower' ) ? mb_strtolower( $host, 'UTF-8' ) : strtolower( $host );
		if ( 1 === preg_match( '/[^\x20-\x7E]/', $host ) && function_exists( 'idn_to_ascii' ) ) {
			$ascii = defined( 'INTL_IDNA_VARIANT_UTS46' ) ? idn_to_ascii( $host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 ) : false;
			if ( is_string( $ascii ) && '' !== $ascii ) {
				$host = strtolower( $ascii );
			}
		}

		$scheme  = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$port    = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
		$default = [
			'http'  => 80,
			'https' => 443,
		];
		if ( isset( $default[ $scheme ] ) && $default[ $scheme ] === $port ) {
			$port = 0;
		}

		$path = trim( rawurldecode( (string) ( $parts['path'] ?? '' ) ), '/' );

		$identity = $host . ( $port ? ':' . $port : '' ) . ( '' !== $path ? '/' . $path : '' );
		$lossy    = '' === $host || 1 === preg_match( '/[^\x20-\x7E]/', $identity );

		$site = strtolower( remove_accents( $identity ) );
		$site = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $site ), '-' );

		$suffix = '' !== $endpoint ? '-' . $endpoint : '';
		$room   = self::CLIENT_KEY_MAX_LENGTH - strlen( self::CLIENT_KEY_PREFIX ) - strlen( $suffix );
		if ( $lossy || '' === $site || strlen( $site ) > $room ) {
			$hash = substr( hash( 'sha256', $identity ), 0, self::CLIENT_KEY_HASH_LENGTH );
			$cut  = rtrim( substr( $site, 0, max( 0, $room - self::CLIENT_KEY_HASH_LENGTH - 1 ) ), '-' );
			$site = '' !== $cut ? $cut . '-' . $hash : $hash;
		}
		$key = self::CLIENT_KEY_PREFIX . $site . $suffix;

		/**
		 * Filter the suggested MCP client configuration key for one of this
		 * site's endpoints.
		 *
		 * Changes only the key shown in the client snippets on the MCP Docs
		 * page. It must be 1–64 letters, digits, "-" or "_"; anything else is
		 * ignored with a _doing_it_wrong() notice.
		 *
		 * @param string $key      The suggested key.
		 * @param string $endpoint Endpoint identifier: "mcp-adapter-default-server" or "media-library-tools".
		 * @param string $url      The site address it was built from.
		 */
		$filtered = apply_filters( self::CLIENT_KEY_FILTER, $key, $endpoint, $url );
		if ( is_string( $filtered ) && 1 === preg_match( self::CLIENT_KEY_PATTERN, $filtered ) ) {
			return $filtered;
		}

		_doing_it_wrong(
			esc_html( self::CLIENT_KEY_FILTER ),
			esc_html__( 'The filter must return 1 to 64 letters, digits, hyphens or underscores; the suggested key was used instead.', 'media-library-tools' ),
			esc_html( defined( 'TSMLT_VERSION' ) ? TSMLT_VERSION : '' )
		);
		return $key;
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
