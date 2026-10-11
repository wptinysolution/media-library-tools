<?php
/**
 * WordPress hooks for encrypted AI credentials.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Controllers\AI;

use TinySolutions\mlt\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Runs the plaintext-key migration, guards `tsmlt_settings` writes against
 * restoring stale plaintext keys, shows the recovery notice for unreadable
 * keys, and reports encryption strength in Site Health.
 */
class CredentialHooks {
	use SingletonTrait;

	/**
	 * Screens (by base) that show the recovery notice.
	 */
	const NOTICE_SCREENS = [
		'dashboard',
		'media_page_media-library-tools',
		'media_page_tsmlt-get-pro',
		'media_page_tsmlt-pricing-pro',
	];

	/**
	 * Register hooks.
	 */
	private function __construct() {
		add_action( 'admin_init', [ $this, 'maybe_migrate' ] );
		add_filter( 'pre_update_option_tsmlt_settings', [ $this, 'strip_stale_plaintext' ] );
		// The plugin's own screens clear admin_notices at priority 99, so the
		// notice is registered afterwards.
		add_action( 'in_admin_header', [ $this, 'register_notice' ], 100 );
		add_filter( 'site_status_tests', [ $this, 'register_site_health_test' ] );
	}

	/**
	 * Migrate legacy plaintext keys on admin requests.
	 *
	 * @return void
	 */
	public function maybe_migrate() {
		Credentials::maybe_migrate();
	}

	/**
	 * Drop stale plaintext keys from any `tsmlt_settings` write.
	 *
	 * @param mixed $value New settings value.
	 *
	 * @return mixed
	 */
	public function strip_stale_plaintext( $value ) {
		return Credentials::strip_stale_plaintext( $value );
	}

	/**
	 * Queue the recovery notice on the Dashboard and plugin screens.
	 *
	 * @return void
	 */
	public function register_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->base, self::NOTICE_SCREENS, true ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_action( 'admin_notices', [ $this, 'render_notice' ] );
	}

	/**
	 * Explain which keys cannot be read and how to recover.
	 *
	 * @return void
	 */
	public function render_notice() {
		$unreadable = Credentials::unreadable();
		if ( empty( $unreadable ) ) {
			return;
		}

		$labels  = array_map( [ __CLASS__, 'provider_label' ], array_keys( $unreadable ) );
		$damaged = in_array( Credentials::REASON_DAMAGED, $unreadable, true );
		?>
		<div class="notice notice-error">
			<p><strong>
				<?php
				printf(
					esc_html(
						/* translators: %s: provider names, e.g. "Gemini, Claude". */
						_n( 'Media Library Tools can\'t read your saved %s API key.', 'Media Library Tools can\'t read your saved %s API keys.', count( $labels ), 'media-library-tools' )
					),
					esc_html( implode( ', ', $labels ) )
				);
				?>
			</strong></p>
			<p>
				<?php
				if ( $damaged ) {
					esc_html_e( 'The stored key data appears to have been changed or damaged.', 'media-library-tools' );
				} else {
					esc_html_e( 'This usually happens when the site\'s WordPress security keys (salts) change — for example after a security plugin rotates them, a move to a new host, or creating a staging copy.', 'media-library-tools' );
				}
				?>
				<?php esc_html_e( 'No AI requests are sent with an unreadable key, and the saved data is kept.', 'media-library-tools' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'To fix it, re-enter the key in Media Library Tools → Settings → AI. If you restore the previous security keys in wp-config.php, the saved key will work again automatically.', 'media-library-tools' ); ?>
				<a href="<?php echo esc_url( admin_url( 'upload.php?page=media-library-tools' ) ); ?>"><?php esc_html_e( 'Open settings', 'media-library-tools' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Add the encryption-strength test to Site Health.
	 *
	 * @param array $tests Site Health tests.
	 *
	 * @return array
	 */
	public function register_site_health_test( $tests ) {
		$tests['direct']['tsmlt_credential_encryption'] = [
			'label' => __( 'Media Library Tools API key encryption', 'media-library-tools' ),
			'test'  => [ $this, 'site_health_test' ],
		];

		return $tests;
	}

	/**
	 * Report whether encryption protects API keys against a database-only leak.
	 *
	 * @return array
	 */
	public function site_health_test() {
		$in_config = Credentials::salts_in_config();

		return [
			'label'       => $in_config
				? __( 'AI API keys are encrypted with keys stored outside the database', 'media-library-tools' )
				: __( 'AI API keys are encrypted with keys stored in the database', 'media-library-tools' ),
			'status'      => $in_config ? 'good' : 'recommended',
			'badge'       => [
				'label' => __( 'Security', 'media-library-tools' ),
				'color' => 'blue',
			],
			'description' => '<p>' . esc_html(
				$in_config
					? __( 'Media Library Tools encrypts saved AI API keys using this site\'s LOGGED_IN security keys from wp-config.php, so a copy of the database alone does not reveal them.', 'media-library-tools' )
					: __( 'wp-config.php does not define LOGGED_IN_KEY and LOGGED_IN_SALT, so WordPress keeps them in the database. Saved AI API keys are still encrypted, but a copy of the database would be enough to decrypt them. Adding security keys to wp-config.php makes saved API keys unreadable, so they must be re-entered afterwards.', 'media-library-tools' )
			) . '</p>',
			'test'        => 'tsmlt_credential_encryption',
		];
	}

	/**
	 * Human-readable provider name.
	 *
	 * @param string $provider Provider key.
	 *
	 * @return string
	 */
	public static function provider_label( string $provider ): string {
		$labels = [
			'chatgpt' => 'OpenAI',
			'gemini'  => 'Gemini',
			'claude'  => 'Claude',
		];

		return $labels[ $provider ] ?? $provider;
	}
}
