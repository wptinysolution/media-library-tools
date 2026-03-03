<?php
/**
 * Special Offer.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abs;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Abstract base class for time-limited promotional offer notices.
 *
 * Extend this class and implement the_options() to define a specific
 * offer notice displayed in the WordPress admin.
 */
abstract class Discount {

	/**
	 * Resolved notice options, merged with defaults.
	 *
	 * @var array<string, mixed>
	 */
	protected array $options = [];

	/**
	 * Class Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'show_notice' ] );
	}

	/**
	 * Return the options for this specific offer.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function the_options(): array;

	/**
	 * Evaluate whether the notice should be shown and register its hooks.
	 *
	 * @return void
	 */
	public function show_notice(): void {
		$defaults = [
			'download_link'           => tsmlt()->pro_version_link(),
			'plugin_name'             => 'Media Library Tools Pro',
			'image_url'               => tsmlt()->get_assets_uri( 'images/media-library-tools-icon-128x128.png' ),
			'option_name'             => '',
			'prev_option_name'        => '',
			'start_date'              => '',
			'end_date'                => '',
			'minimum_activation_days' => false,
			'maximum_activation_days' => false,
			'download_button_text'    => 'Buy Now',
			'notice_for'              => 'Black Friday Cyber Monday Deal!!',
			'notice_message'          => '',
		];

		$this->options = wp_parse_args(
			apply_filters( 'tsmlt_offer_notice', $this->the_options() ),
			$defaults
		);

		if ( ! $this->should_display() ) {
			return;
		}

		$this->register_notice_hooks();
		$this->cleanup_previous_options();
	}

	/**
	 * Determine whether all notice display conditions are satisfied.
	 *
	 * @return bool
	 */
	private function should_display(): bool {
		if ( tsmlt()->has_pro() ) {
			return false;
		}

		if ( get_option( $this->options['option_name'] ) === '1' ) {
			return false;
		}

		$current               = time();
		$install_time          = (int) get_option( 'tsmlt_plugin_activation_time', 0 );
		$days_since_activation = ( $current - $install_time ) / DAY_IN_SECONDS;

		$min_days = $this->options['minimum_activation_days'];
		$max_days = $this->options['maximum_activation_days'];

		if ( $min_days && $days_since_activation <= $min_days ) {
			return false;
		}

		if ( $max_days && $days_since_activation > $max_days ) {
			return false;
		}

		$start = strtotime( $this->options['start_date'] );
		$end   = strtotime( $this->options['end_date'] );

		if ( false === $start || false === $end || $current < $start || $current > $end ) {
			return false;
		}

		return true;
	}

	/**
	 * Register all hooks required to display and dismiss the notice.
	 *
	 * @return void
	 */
	private function register_notice_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_notice_assets' ] );
		add_action( 'admin_notices', [ $this, 'render_notice' ] );
		add_action( 'admin_footer', [ $this, 'render_dismiss_script' ] );
		add_action( 'wp_ajax_tsmlt_dismiss_offer_admin_notice', [ $this, 'handle_ajax_dismiss' ] );
	}

	/**
	 * Enqueue assets required by the notice.
	 *
	 * @return void
	 */
	public function enqueue_notice_assets(): void {
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Output the notice markup and scoped styles.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		?>
		<style>
			.tsmlt-offer-notice {
				--tsmlt-primary: #2271b1;
				--tsmlt-primary-dark: #135e96;
				display: grid;
				grid-template-columns: 100px auto;
				padding: 12px 15px;
				column-gap: 15px;
			}
			.tsmlt-offer-notice img {
				grid-row: 1 / 4;
				align-self: center;
				justify-self: center;
			}
			.tsmlt-offer-notice h3,
			.tsmlt-offer-notice p {
				margin: 0 !important;
			}
			.tsmlt-offer-notice .notice-text {
				margin: 0 0 2px;
				padding: 5px 0;
				font-size: 14px;
			}
			.tsmlt-offer-notice .button-primary,
			.tsmlt-offer-notice .button-dismiss {
				display: inline-block;
				border: 0;
				border-radius: 3px;
				background: var(--tsmlt-primary-dark);
				color: #fff;
				vertical-align: middle;
				text-align: center;
				text-decoration: none;
				white-space: nowrap;
				margin-right: 5px;
				transition: background 0.2s, color 0.2s;
			}
			.tsmlt-offer-notice .button-primary:hover,
			.tsmlt-offer-notice .button-primary:focus,
			.tsmlt-offer-notice .button-dismiss:hover,
			.tsmlt-offer-notice .button-dismiss:focus {
				background: var(--tsmlt-primary);
				color: #fff;
				outline: none;
				box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--tsmlt-primary);
			}
			.tsmlt-offer-notice .button-dismiss {
				border: 1px solid var(--tsmlt-primary);
				background: #fff;
				color: var(--tsmlt-primary);
			}
			.tsmlt-offer-notice .button-dismiss:hover,
			.tsmlt-offer-notice .button-dismiss:focus {
				color: #fff;
			}
		</style>
		<div
			class="tsmlt-offer-notice notice notice-info is-dismissible"
			data-tsmltdismissable="tsmlt_offer"
		>
			<img
				src="<?php echo esc_url( $this->options['image_url'] ); ?>"
				alt="<?php echo esc_attr( $this->options['plugin_name'] ); ?>"
				width="100"
				height="100"
			/>
			<h3><?php echo esc_html( $this->options['notice_for'] ); ?></h3>
			<p class="notice-text">
				<?php echo wp_kses_post( $this->options['notice_message'] ); ?>
			</p>
			<p>
				<a
					class="button button-primary"
					href="<?php echo esc_url( $this->options['download_link'] ); ?>"
					target="_blank"
					rel="noopener noreferrer"
				><?php echo esc_html( $this->options['download_button_text'] ); ?></a>
				<a class="button button-dismiss" href="#"><?php esc_html_e( 'Dismiss', 'media-library-tools' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Output the inline dismiss script in the admin footer.
	 *
	 * @return void
	 */
	public function render_dismiss_script(): void {
		$nonce = wp_json_encode( wp_create_nonce( 'tsmlt-offer-dismissible-notice' ) );
		?>
		<script type="text/javascript">
			(function ($) {
				$(function () {
					setTimeout(function () {
						$('[data-tsmltdismissable] .notice-dismiss, [data-tsmltdismissable] .button-dismiss')
							.on('click', function (e) {
								e.preventDefault();
								$.post(ajaxurl, {
									action: 'tsmlt_dismiss_offer_admin_notice',
									nonce: <?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output is safe. ?>
								});
								$(e.target).closest('.is-dismissible').remove();
							});
					}, 500);
				});
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * Handle the AJAX request to permanently dismiss the notice.
	 *
	 * @return void
	 */
	public function handle_ajax_dismiss(): void {
		check_ajax_referer( 'tsmlt-offer-dismissible-notice', 'nonce' );
		if ( ! empty( $this->options['option_name'] ) ) {
			update_option( $this->options['option_name'], '1' );
		}
		wp_die();
	}

	/**
	 * Delete any options left over from previous offer campaigns.
	 *
	 * @return void
	 */
	private function cleanup_previous_options(): void {
		$prev = $this->options['prev_option_name'] ?? '';
		if ( empty( $prev ) ) {
			return;
		}
		if ( is_array( $prev ) ) {
			array_map( 'delete_option', $prev );
		} else {
			delete_option( $prev );
		}
	}
}
