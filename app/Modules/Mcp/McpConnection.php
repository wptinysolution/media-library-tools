<?php
/**
 * Data and credentials for the MCP connection page.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\Mcp;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abilities\McpAccess;
use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Controllers\Admin\McpDocsPage;
use TinySolutions\mlt\Controllers\Admin\McpPage;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Application_Passwords;
use WP_Error;
use WP_User;

/**
 * The MCP page connects an MCP client to the WordPress default MCP server
 * (the recommended single connection). It reports the page's settings and,
 * on request, creates a WordPress Application Password for the current user
 * (one per client, so each can be revoked on its own) for the page to build
 * the client's configuration and setup prompt. The password is returned once
 * to the requesting user and is never stored or logged here.
 */
class McpConnection {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Clients the page has instructions for.
	 *
	 * @var array<string, string>
	 */
	const CLIENTS = [
		'claude-code'    => 'Claude Code',
		'claude-desktop' => 'Claude Desktop',
		'codex'          => 'Codex',
		'cursor'         => 'Cursor',
		'other'          => 'Other',
	];

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * The default MCP server's URL for this site.
	 *
	 * @return string
	 */
	public static function default_server_url(): string {
		return rest_url( 'mcp/' . McpDocsPage::CLIENT_KEY_DEFAULT_SERVER );
	}

	/**
	 * The dedicated Media Library Tools MCP server's URL for this site (only
	 * this plugin's tools), kept as the alternative connection.
	 *
	 * @return string
	 */
	public static function dedicated_server_url(): string {
		return rest_url( McpServerRegistration::ROUTE_NAMESPACE . '/' . McpServerRegistration::ROUTE );
	}

	/**
	 * What the page shows: whether access is on, the connection URL and
	 * server name, whether the MCP Adapter and the Abilities API are ready,
	 * and whether an Application Password can be created.
	 *
	 * @return array
	 */
	public function settings(): array {
		$docs    = McpDocsPage::instance();
		$blocker = self::application_password_blocker( wp_get_current_user() );
		return [
			'enabled'                => McpAccess::is_enabled(),
			'serverUrl'              => esc_url_raw( self::default_server_url() ),
			'dedicatedUrl'           => esc_url_raw( self::dedicated_server_url() ),
			'serverName'             => $docs->get_client_server_key( McpDocsPage::CLIENT_KEY_DEFAULT_SERVER ),
			'adapterStatus'          => $docs->get_adapter_status(),
			'abilitiesSupported'     => function_exists( 'wp_register_ability' ),
			'defaultServerSupported' => McpServerRegistration::adapter_supports_default_server(),
			'canCreatePassword'      => null === $blocker,
			'passwordWarning'        => $blocker ? $blocker->get_error_message() : '',
			'docsUrl'                => esc_url_raw( McpPage::tab_url( McpPage::TAB_DOCUMENTATION ) ),
			'profileUrl'             => esc_url_raw( admin_url( 'profile.php#application-passwords-section' ) ),
		];
	}

	/**
	 * Create an Application Password for the current user and a client.
	 *
	 * Refused while MCP access is turned off, for an unknown client, and when
	 * Application Passwords are not available.
	 *
	 * @param string $client Client ID (CLIENTS).
	 *
	 * @return array{username: string, password: string, encodedCredentials: string, serverUrl: string, serverName: string}|WP_Error
	 */
	public function create_credentials( string $client ) {
		if ( ! McpAccess::is_enabled() ) {
			return new WP_Error( 'tsmlt_mcp_disabled', __( 'MCP access is turned off. Turn it on first.', 'media-library-tools' ) );
		}
		if ( ! isset( self::CLIENTS[ $client ] ) ) {
			return new WP_Error( 'tsmlt_mcp_unknown_client', __( 'Choose an MCP client.', 'media-library-tools' ) );
		}
		$user    = wp_get_current_user();
		$blocker = self::application_password_blocker( $user );
		if ( $blocker ) {
			return $blocker;
		}
		$created = WP_Application_Passwords::create_new_application_password(
			$user->ID,
			[
				/* translators: 1: MCP client name, 2: date and time (UTC). */
				'name' => sprintf( __( 'Media Library Tools MCP – %1$s (%2$s)', 'media-library-tools' ), self::CLIENTS[ $client ], gmdate( 'Y-m-d H:i' ) ),
			]
		);
		if ( is_wp_error( $created ) ) {
			return new WP_Error( 'tsmlt_mcp_password_failed', __( 'The Application Password could not be created.', 'media-library-tools' ) );
		}
		$password = (string) $created[0];
		return [
			'username'           => $user->user_login,
			'password'           => WP_Application_Passwords::chunk_password( $password ),
			'encodedCredentials' => base64_encode( $user->user_login . ':' . $password ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication value for the client's configuration.
			'serverUrl'          => esc_url_raw( self::default_server_url() ),
			'serverName'         => McpDocsPage::instance()->get_client_server_key( McpDocsPage::CLIENT_KEY_DEFAULT_SERVER ),
		];
	}

	/**
	 * Why an Application Password cannot be created for a user, or null.
	 *
	 * @param WP_User|null $user User.
	 *
	 * @return WP_Error|null
	 */
	public static function application_password_blocker( ?WP_User $user ): ?WP_Error {
		if ( ! class_exists( WP_Application_Passwords::class ) || ! wp_is_application_passwords_available() ) {
			return new WP_Error( 'tsmlt_mcp_passwords_unavailable', __( 'Application Passwords are not available on this site. The site may need HTTPS, or a plugin may have turned them off.', 'media-library-tools' ) );
		}
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return new WP_Error( 'tsmlt_mcp_no_user', __( 'Could not determine the current user.', 'media-library-tools' ) );
		}
		if ( ! wp_is_application_passwords_available_for_user( $user ) ) {
			return new WP_Error( 'tsmlt_mcp_passwords_off', __( 'Application Passwords are turned off for your account.', 'media-library-tools' ) );
		}
		return null;
	}
}
