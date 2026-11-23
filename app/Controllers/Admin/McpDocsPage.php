<?php
/**
 * The Documentation tab of the MCP Connection page: how to connect an AI
 * assistant over MCP, the tools and the rules.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Controllers\Admin;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

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
	 * The tab's sections, for its "On this page" navigation: anchor => title.
	 *
	 * @return array<string, string>
	 */
	private function sections(): array {
		return [
			'requirements'    => __( 'Requirements', 'media-library-tools' ),
			'tools'           => __( 'Available tools', 'media-library-tools' ),
			'endpoints'       => __( 'Endpoints', 'media-library-tools' ),
			'examples'        => __( 'Usage examples', 'media-library-tools' ),
			'access'          => __( 'Access control', 'media-library-tools' ),
			'notes'           => __( 'Important notes', 'media-library-tools' ),
			'troubleshooting' => __( 'Troubleshooting', 'media-library-tools' ),
		];
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

		$gateway_example = (string) wp_json_encode(
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
		<div class="tsmlt-mcp-docs">
			<?php $this->render_styles(); ?>

			<div class="tsmlt-mcp-docs__intro">
				<p class="tsmlt-mcp-docs__lead">
					<?php esc_html_e( 'Reference for the Media Library Tools MCP integration: the tools AI assistants can use, what they may change, who can use them, and how to fix common problems.', 'media-library-tools' ); ?>
				</p>
				<p class="tsmlt-mcp-docs__callout">
					<?php esc_html_e( 'Setting up a client? Connection URLs, prompts and configurations are on the Connection tab.', 'media-library-tools' ); ?>
					<a href="<?php echo esc_url( McpPage::tab_url() ); ?>"><?php esc_html_e( 'Open the Connection tab', 'media-library-tools' ); ?></a>
				</p>
			</div>

			<div class="tsmlt-mcp-docs__layout">
				<nav class="tsmlt-mcp-docs__toc" aria-label="<?php esc_attr_e( 'On this page', 'media-library-tools' ); ?>">
					<p class="tsmlt-mcp-docs__toc-title"><?php esc_html_e( 'On this page', 'media-library-tools' ); ?></p>
					<ul>
						<?php foreach ( $this->sections() as $section_id => $section_label ) : ?>
							<li><a href="#tsmlt-mcp-docs-<?php echo esc_attr( $section_id ); ?>"><?php echo esc_html( $section_label ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</nav>
				<div class="tsmlt-mcp-docs__body">
			<section class="tsmlt-mcp-docs__section" id="tsmlt-mcp-docs-requirements" aria-labelledby="tsmlt-mcp-docs-requirements-title">
				<h2 id="tsmlt-mcp-docs-requirements-title"><?php esc_html_e( 'Requirements', 'media-library-tools' ); ?></h2>
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
			</section>

			<section class="tsmlt-mcp-docs__section" id="tsmlt-mcp-docs-tools" aria-labelledby="tsmlt-mcp-docs-tools-title">
				<h2 id="tsmlt-mcp-docs-tools-title"><?php esc_html_e( 'Available tools', 'media-library-tools' ); ?></h2>
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
							<td><?php esc_html_e( 'Previews renaming the files of up to 20 attachments to names you type: each item\'s status (ready, unchanged, name refused, not found, forbidden, file missing, stored off the server, full-size original missing, locked by another operation or name unavailable), the current file name and the exact new one (the extension and a -scaled or -rotated ending are kept, and a -1 style number is added when the name is taken), the new name of the full-size original, and how many image sizes a rename would make again. Issues a one-time confirmation token. Renames nothing. Renaming by post title, SKU, alt text or AI is not available here and is refused.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-rename-media</code></td>
							<td><?php esc_html_e( 'Renames the previewed files, with the confirmation token and confirm: true, after checking everything again; items no longer ready are reported, not renamed. One file is renamed at once; several run in the background. Also renames the full-size original, makes the sizes again under the new name and updates links in post content, excerpts and Elementor data. Refused while a bulk rename is running.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (renames files and updates links)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-media-rename-status</code></td>
							<td><?php esc_html_e( 'Returns the bulk rename\'s status, counts, percentage, whether the next background step is scheduled, and per-file results: the result, a code, the new file name and the posts whose links were updated. Never changes the job.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-cancel-media-rename</code></td>
							<td><?php esc_html_e( 'Stops the running bulk rename with the given job ID. Files already renamed keep their new names. Never stops compression, conversion or regeneration jobs.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (stops the job)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-compression-status</code></td>
							<td><?php esc_html_e( 'Returns the image compression status of up to 50 attachments, as Compress Images recorded it: completed, skipped, failed, not processed or unsupported, the original and current size, the space saved, the mode, quality and engine used, when, whether a backup of the original exists, and the result per image size. Error messages are not returned. Never compresses or changes anything.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-preview-compression</code></td>
							<td><?php esc_html_e( 'Checks a compression run before it starts — up to 20 chosen images or the whole library — and returns each image\'s status (ready, already compressed, unsupported, file missing, locked, not found or forbidden), the settings that would be used, whether the result can be undone (only when a backup is kept, a Pro feature) and a one-time confirmation token. Custom quality, backups and generated sizes need Pro. Compresses nothing.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-compress-media</code></td>
							<td><?php esc_html_e( 'Starts the run a preview checked, with its confirmation token and confirm: true, after checking everything again. Compression runs in the background through the Compress Images job, never in the request; up to 20 images per job on Free. Refused while a compression or conversion job is running. Without a backup (Pro) each image file is replaced permanently.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (compresses image files)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-compression-job-status</code></td>
							<td><?php esc_html_e( 'Returns the progress of the Compress Images job: its type (compression, or a conversion run, which shares the job), status, how many images are done, succeeded, skipped and failed, the percentage and whether the next background step is scheduled. Error messages are not returned. Never changes the job.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-cancel-compression-job</code></td>
							<td><?php esc_html_e( 'Stops the running compression job, like Stop on the Compress Images page. Never cancels a conversion job; images already compressed stay compressed.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (stops the job)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-preview-thumbnail-regeneration</code></td>
							<td><?php esc_html_e( 'Checks whether the thumbnails (image sizes) of up to 20 images can be regenerated: ready, not found, forbidden, not an image, file missing, stored off the server, full-size original missing, or locked by another operation; for scaled and rotated uploads, whether the full-size original is available. Lists the sizes that would be made and the disabled sizes, which are kept. Issues a one-time confirmation token. Regenerates nothing.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-regenerate-thumbnails</code></td>
							<td><?php esc_html_e( 'Starts regenerating the previewed images, with the confirmation token and confirm: true, after checking everything again. Runs in the background, never in the request; never the whole library. Refused while a regeneration is running. Not reversible: existing size files are replaced.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (replaces image size files)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-thumbnail-regeneration-status</code></td>
							<td><?php esc_html_e( 'Returns the regeneration job\'s status, counts, percentage, whether the next background step is scheduled, and recent per-image results as codes. No file names or error messages. Never changes the job.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-cancel-thumbnail-regeneration</code></td>
							<td><?php esc_html_e( 'Stops the running regeneration job with the given job ID, like Stop on the Regenerate page. Images already regenerated stay regenerated.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (stops the job)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-preview-media-conversion</code></td>
							<td><?php esc_html_e( 'Checks converting up to 20 images to WebP and/or AVIF copies: ready, already converted, same format, unsupported, file missing, name conflict (a file with the copy\'s name that is not this image\'s copy is never replaced), locked, not found or forbidden. AVIF, custom quality and generated sizes need Pro and are refused without it. Issues a one-time confirmation token. Converts nothing.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-convert-media</code></td>
							<td><?php esc_html_e( 'Starts converting the previewed images, with the confirmation token and confirm: true, after checking everything again. Runs in the background, never in the request; never the whole library; up to 20 images per job on Free. Refused while a compression or conversion job runs. Copies are extra files; the images are not changed.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (creates converted copies)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-cancel-conversion-job</code></td>
							<td><?php esc_html_e( 'Stops the running conversion job with the given job ID, like Stop on the Convert page. Never cancels compression or thumbnail regeneration. Copies already made stay. Progress: tsmlt-get-compression-job-status.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (stops the job)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-media-image-preview</code></td>
							<td><?php esc_html_e( 'Returns one JPEG, PNG or WebP image as an image the assistant can look at: at most 1024 pixels on its longest side and about 1 MB, turned upright, re-encoded and stripped of all metadata (no EXIF, GPS, IPTC, XMP or comments). Use it before writing alt text or captions with tsmlt-update-media-metadata. No URL, path or file name is returned. Never changes the image.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-list-image-sizes</code></td>
							<td><?php esc_html_e( 'Lists every registered image size: name, width and height, crop, where it comes from (WordPress, a Media Library Tools custom size, or the theme or another plugin) and whether it is disabled on the Image Sizes page.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-image-size-details</code></td>
							<td><?php esc_html_e( 'Returns one registered image size by name: width and height, crop, where it comes from and whether it is disabled. An unknown name is refused.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-conversion-status</code></td>
							<td><?php esc_html_e( 'Returns the WebP/AVIF conversion status of up to 50 attachments, as the Convert feature recorded it: completed, partial, skipped, failed, not converted or unsupported, whether the image changed since it was converted, whether a conversion is running, and per format the size, space saved, quality and when it was made. Error messages and file paths are not returned. Never converts or changes anything.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-thumbnail-status</code></td>
							<td><?php esc_html_e( 'Returns the thumbnail status of up to 50 images: whether every image size WordPress should have is generated and its file is on disk, with each size\'s dimensions and file size, the sizes that are missing, sizes no longer registered, and the original\'s dimensions for scaled uploads. Sizes disabled in Media Library Tools are not counted as missing. Never regenerates or deletes anything.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-get-media-health</code></td>
							<td><?php esc_html_e( 'Checks the file and record integrity of up to 50 attachments: no file recorded, file missing or unreadable, a file type that differs from the stored one, images without WordPress image data or whose recorded file size or dimensions differ from the file, and attachments linked to a post that no longer exists. Also says whether alt text is set. Never repairs or changes anything.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'No', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-audit-media-health</code></td>
							<td><?php esc_html_e( 'Audits the whole media library with the same checks as tsmlt-get-media-health, up to 100 attachments per call: lists only the attachments with problems, counts the healthy ones, and gives the position to continue from. Stores nothing between calls. Never repairs or changes anything.', 'media-library-tools' ); ?></td>
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
						<tr>
							<td><code>tsmlt-register-image-size</code></td>
							<td><?php esc_html_e( 'Pro. Adds a custom image size (tsmlt_ plus a key, width, height, crop), like the Image Sizes page. The first call previews it and returns a plan; a second call with the plan and confirm: true saves it. Existing thumbnails are not regenerated.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (image size settings)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-update-image-size</code></td>
							<td><?php esc_html_e( 'Pro. Changes the width, height or crop of a Media Library Tools custom size, after a preview and confirmation. Sizes of WordPress, the theme or other plugins are refused. Existing thumbnails are not regenerated.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (image size settings)', 'media-library-tools' ); ?></td>
						</tr>
						<tr>
							<td><code>tsmlt-unregister-image-size</code></td>
							<td><?php esc_html_e( 'Pro. Removes a Media Library Tools custom size, after a preview and confirmation. Other sizes are refused. Files already made are not deleted and thumbnails are not regenerated.', 'media-library-tools' ); ?></td>
							<td><?php esc_html_e( 'Yes (image size settings)', 'media-library-tools' ); ?></td>
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
			</section>

			<section class="tsmlt-mcp-docs__section" id="tsmlt-mcp-docs-endpoints" aria-labelledby="tsmlt-mcp-docs-endpoints-title">
				<h2 id="tsmlt-mcp-docs-endpoints-title"><?php esc_html_e( 'Endpoints', 'media-library-tools' ); ?></h2>
				<p><?php esc_html_e( 'Media Library Tools is available on two MCP servers. Both offer every Media Library Tools tool — the Free tools, and the Pro tools while the Pro licence is active — with the same names and the same permission checks. Both are served by the MCP Adapter, which needs MCP Adapter 0.5.0 or newer for the default server.', 'media-library-tools' ); ?></p>
				<ul>
					<li>
						<strong><?php esc_html_e( 'WordPress default MCP server (recommended):', 'media-library-tools' ); ?></strong>
						<?php esc_html_e( 'one connection for Media Library Tools and every other public WordPress and plugin ability, reachable through the MCP Adapter\'s gateway tools — including abilities that change or delete data.', 'media-library-tools' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Dedicated Media Library Tools server (alternative):', 'media-library-tools' ); ?></strong>
						<?php esc_html_e( 'only the Media Library Tools tools, for a narrower connection. It requires an administrator account before a session can start, and also works when the default server is turned off.', 'media-library-tools' ); ?>
					</li>
				</ul>
				<p>
					<strong><?php esc_html_e( 'Use one, not both:', 'media-library-tools' ); ?></strong>
					<?php esc_html_e( 'with both configured, the media tools appear twice and the assistant may switch between them unpredictably.', 'media-library-tools' ); ?>
				</p>

				<h3><?php esc_html_e( 'Comparison', 'media-library-tools' ); ?></h3>
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

				<h3><?php esc_html_e( 'Gateway examples', 'media-library-tools' ); ?></h3>
				<p><?php esc_html_e( 'The assistant usually discovers the ability first, then runs it. You can also name it directly:', 'media-library-tools' ); ?></p>
				<ul>
					<li><?php esc_html_e( '"Use execute-ability to run tsmlt/search-media for images with no alt text."', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( '"Run tsmlt/get-media-details for attachment 1234."', 'media-library-tools' ); ?></li>
				</ul>
				<p class="description">
					<?php esc_html_e( 'Call the Media Library Tools tools directly by their names with dashes (tsmlt-search-media). Through execute-ability, only the read-only search and details abilities are available, by their names with a slash (tsmlt/search-media).', 'media-library-tools' ); ?>
				</p>
			</section>

			<section class="tsmlt-mcp-docs__section" id="tsmlt-mcp-docs-examples" aria-labelledby="tsmlt-mcp-docs-examples-title">
				<h2 id="tsmlt-mcp-docs-examples-title"><?php esc_html_e( 'Usage examples', 'media-library-tools' ); ?></h2>
				<ul>
					<li><?php esc_html_e( '"Find images in the media library that have no alt text."', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( '"Show me the details of attachment 1234."', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( '"Write alt text for attachment 1234 and save it."', 'media-library-tools' ); ?></li>
				</ul>
			</section>

			<section class="tsmlt-mcp-docs__section" id="tsmlt-mcp-docs-access" aria-labelledby="tsmlt-mcp-docs-access-title">
				<h2 id="tsmlt-mcp-docs-access-title"><?php esc_html_e( 'Access control', 'media-library-tools' ); ?></h2>
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
			</section>

			<section class="tsmlt-mcp-docs__section" id="tsmlt-mcp-docs-notes" aria-labelledby="tsmlt-mcp-docs-notes-title">
				<h2 id="tsmlt-mcp-docs-notes-title"><?php esc_html_e( 'Important notes', 'media-library-tools' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'Review before saving: tsmlt-update-media-metadata overwrites the current value and cannot be undone. Keep your client\'s tool-approval prompts turned on.', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( 'Revoking access: delete the Application Password in your profile. The assistant loses access immediately.', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( 'Application Passwords: use one per assistant and device, so you can revoke one without affecting the others. WordPress only offers them over HTTPS, or on sites marked as a local development environment.', 'media-library-tools' ); ?></li>
					<li><?php esc_html_e( 'Turning off all MCP access: check "Turn off MCP access" on the Connection tab. Every Media Library Tools tool and resource is then refused on both endpoints, until you uncheck it; other plugins\' MCP tools are not affected.', 'media-library-tools' ); ?></li>
				</ul>
			</section>

			<section class="tsmlt-mcp-docs__section" id="tsmlt-mcp-docs-troubleshooting" aria-labelledby="tsmlt-mcp-docs-troubleshooting-title">
				<h2 id="tsmlt-mcp-docs-troubleshooting-title"><?php esc_html_e( 'Troubleshooting', 'media-library-tools' ); ?></h2>
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
			</section>
				</div>
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
		 * Changes only the key shown in the client snippets on the MCP Connection
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
	 * MCP Adapter notice of the MCP Connection page, shown under its heading
	 * and above the tabs, so on both tabs. Nothing when the adapter is
	 * running (from the MCP Adapter plugin or a plugin that bundles it).
	 * Otherwise a warning with an Install or Activate action for users who
	 * may do that (WordPress core's own nonce-protected install and activate
	 * actions, see render_adapter_button()), and an error when WordPress has
	 * no Abilities API. No `inline` class: the Tailwind build on the
	 * Connection tab has an `.inline` utility (display: inline, in a layer, so
	 * it cannot be overridden) that breaks the box. The page prints
	 * `wp-header-end` just before it, so WordPress leaves it there.
	 *
	 * @param string $adapter_status    One of the ADAPTER_* constants.
	 * @param bool   $abilities_support Whether WordPress provides the Abilities API.
	 *
	 * @return void
	 */
	public function render_status_notice( string $adapter_status, bool $abilities_support ) {
		if ( ! $abilities_support ) {
			?>
			<div class="notice notice-error tsmlt-mcp-notice">
				<p>
					<strong><?php esc_html_e( 'MCP is not available on this WordPress version.', 'media-library-tools' ); ?></strong>
					<?php esc_html_e( 'The MCP integration needs the Abilities API, which is part of WordPress 6.9 and newer. Update WordPress to use it.', 'media-library-tools' ); ?>
				</p>
			</div>
			<?php
		}

		if ( self::ADAPTER_MISSING === $adapter_status ) {
			?>
			<div class="notice notice-warning tsmlt-mcp-notice">
				<p><strong><?php esc_html_e( 'MCP Adapter is not installed.', 'media-library-tools' ); ?></strong></p>
				<p><?php esc_html_e( 'The WordPress Default MCP Adapter connection requires the MCP Adapter plugin. Install and activate it to use the recommended WordPress MCP connection.', 'media-library-tools' ); ?></p>
				<?php
				if ( current_user_can( 'install_plugins' ) ) {
					$this->render_adapter_button( 'install', __( 'Install Now', 'media-library-tools' ), $this->get_install_url() );
				} else {
					echo '<p>' . esc_html__( 'Ask a site administrator who can install plugins to install it.', 'media-library-tools' ) . '</p>';
				}
				?>
			</div>
			<?php
		} elseif ( self::ADAPTER_INACTIVE === $adapter_status ) {
			?>
			<div class="notice notice-warning tsmlt-mcp-notice">
				<p><strong><?php esc_html_e( 'MCP Adapter is inactive.', 'media-library-tools' ); ?></strong></p>
				<p><?php esc_html_e( 'Activate the MCP Adapter plugin to enable the recommended WordPress Default MCP connection.', 'media-library-tools' ); ?></p>
				<?php
				if ( current_user_can( 'activate_plugin', self::ADAPTER_PLUGIN_FILE ) ) {
					$this->render_adapter_button( 'activate', __( 'Activate Now', 'media-library-tools' ), $this->get_activate_url() );
				} else {
					echo '<p>' . esc_html__( 'Ask a site administrator who can activate plugins to activate it.', 'media-library-tools' ) . '</p>';
				}
				?>
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
			.tsmlt-mcp-docs { padding: 24px 0 48px; color: #334155; font-size: 13px; line-height: 1.6; }
			.tsmlt-mcp-docs__intro { margin: 0 0 24px; }
			.tsmlt-mcp-docs__lead { margin: 0 0 12px; font-size: 14px; color: #475569; max-width: 760px; }
			.tsmlt-mcp-docs__callout { margin: 0; padding: 10px 14px; border: 1px solid #bfdbfe; border-radius: 8px; background: #eff6ff; color: #1e3a8a; }
			.tsmlt-mcp-docs__callout a { font-weight: 600; color: #1d4ed8; margin-left: 4px; }
			.tsmlt-mcp-docs__layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 24px; align-items: start; }
			.tsmlt-mcp-docs__toc { padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; }
			.tsmlt-mcp-docs__toc-title { margin: 0 0 6px; font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #64748b; }
			.tsmlt-mcp-docs__toc ul { margin: 0; display: flex; flex-wrap: wrap; gap: 2px 16px; }
			.tsmlt-mcp-docs__toc li { margin: 0; }
			.tsmlt-mcp-docs__toc a { display: block; padding: 3px 0; color: #475569; text-decoration: none; }
			.tsmlt-mcp-docs__toc a:hover { color: #1d4ed8; }
			.tsmlt-mcp-docs__toc a:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 4px; box-shadow: none; }
			.tsmlt-mcp-docs__body { min-width: 0; display: grid; gap: 20px; }
			.tsmlt-mcp-docs__section { min-width: 0; overflow-x: auto; padding: 20px 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); scroll-margin-top: 48px; }
			.tsmlt-mcp-docs__section h2 { margin: 0 0 10px; font-size: 16px; font-weight: 600; color: #0f172a; }
			.tsmlt-mcp-docs__section h3 { margin: 20px 0 8px; font-size: 14px; font-weight: 600; color: #0f172a; }
			.tsmlt-mcp-docs__section p, .tsmlt-mcp-docs__section li { font-size: 13px; line-height: 1.6; }
			.tsmlt-mcp-docs__section p { margin: 0 0 10px; }
			.tsmlt-mcp-docs__section ul { margin: 0 0 10px; padding-left: 20px; list-style: disc; }
			.tsmlt-mcp-docs__section li { margin: 0 0 4px; }
			.tsmlt-mcp-docs__section code { padding: 1px 5px; border-radius: 4px; background: #f1f5f9; color: #0f172a; font-size: 12px; }
			.tsmlt-mcp-docs__section table.widefat { margin: 12px 0; border: 1px solid #e2e8f0; border-radius: 8px; border-collapse: separate; border-spacing: 0; overflow: hidden; box-shadow: none; }
			.tsmlt-mcp-docs__section table.widefat th { padding: 8px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; color: #475569; }
			.tsmlt-mcp-docs__section table.widefat td { padding: 10px 12px; border-top: 1px solid #f1f5f9; font-size: 13px; vertical-align: top; }
			.tsmlt-mcp-docs__section table.widefat tbody tr:first-child td { border-top: 0; }
			.tsmlt-mcp-docs__section table.striped > tbody > :nth-child(odd) { background: #fcfcfd; }
			.tsmlt-mcp-docs__section td code { white-space: nowrap; }
			.tsmlt-mcp-docs__code { margin: 8px 0 12px; padding: 14px 16px; border-radius: 8px; background: #0f172a; overflow-x: auto; }
			.tsmlt-mcp-docs__code code { padding: 0; background: none; color: #e2e8f0; white-space: pre; font-size: 12px; }
			.tsmlt-mcp-docs .description { color: #64748b; }
			@media (min-width: 1100px) {
				.tsmlt-mcp-docs__layout { grid-template-columns: 200px minmax(0, 1fr); }
				.tsmlt-mcp-docs__toc { position: sticky; top: 48px; }
				.tsmlt-mcp-docs__toc ul { display: block; }
			}
			@media (max-width: 600px) {
				.tsmlt-mcp-docs__section { padding: 16px; }
			}
		</style>
		<?php
	}
}
