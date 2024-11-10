<?php
/**
 * Special Offer.
 *
 * @package RadiusTheme\SB
 */

namespace TinySolutions\mlt\Abs;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Black Friday Offer.
 */
abstract class Discount {
	/**
	 * @var
	 */
	protected $options = [];

	/**
	 * Class Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'show_notice' ] );
	}

	/**
	 * @return array
	 */
	abstract public function the_options(): array;

	/**
	 * @return void
	 */
	public function show_notice() {
		$defaults      = [
			'download_link'  => tsmlt()->pro_version_link(),
			'plugin_name'    => 'Media Library Tools Pro',
			'image_url'      => tsmlt()->get_assets_uri( 'images/media-library-tools-icon-128x128.png' ),
			'option_name'    => '',
			'start_date'     => '',
			'end_date'       => '',
			'notice_for'     => 'Black Friday Cyber Monday Deal!!',
			'notice_message' => '',
		];
		$options       = apply_filters( 'tsmlt_offer_notice', $this->the_options() );
		$this->options = wp_parse_args( $options, $defaults );
		$current       = time();
		$start         = strtotime( $this->options['start_date'] );
		$end           = strtotime( $this->options['end_date'] );
		// Black Friday Notice.
		if ( ! tsmlt()->has_pro() && $start <= $current && $current <= $end ) {
			if ( get_option( $this->options['option_name'] ) != '1' ) {
				if ( ! isset( $GLOBALS['tsmlt__notice'] ) ) {
					$GLOBALS['tsmlt__notice'] = 'tsmlt__notice';
					$this->offer_notice();
					if ( ! empty( $this->options['prev_option_name'] ) ) {
						delete_option( $this->options['prev_option_name'] );
					}
				}
			}
		}
	}

	/**
	 * Black Friday Notice.
	 *
	 * @return void
	 */
	private function offer_notice() {
		add_action(
			'admin_enqueue_scripts',
			function () {
				wp_enqueue_script( 'jquery' );
			}
		);

		add_action(
			'admin_notices',
			function () {
				?>
				<style>
					.tsmlt-offer-notice {
						--e-button-context-color: #2179c0;
						--e-button-context-color-dark: #2271b1;
						--e-button-context-tint: rgb(75 47 157/4%);
						--e-focus-color: rgb(75 47 157/40%);
						display: grid;
						grid-template-columns: 100px auto;
						padding-top: 12px;
						padding-bottom: 12px;
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
						max-width: 100%;
						font-size: 14px;
					}

					.tsmlt-offer-notice .button-primary,
					.tsmlt-offer-notice .button-dismiss {
						display: inline-block;
						border: 0;
						border-radius: 3px;
						background: var(--e-button-context-color-dark);
						color: #fff;
						vertical-align: middle;
						text-align: center;
						text-decoration: none;
						white-space: nowrap;
						margin-right: 5px;
						transition: all 0.3s;
					}

					.tsmlt-offer-notice .button-primary:hover,
					.tsmlt-offer-notice .button-dismiss:hover {
						background: var(--e-button-context-color);
						border-color: var(--e-button-context-color);
						color: #fff;
					}

					.tsmlt-offer-notice .button-primary:focus,
					.tsmlt-offer-notice .button-dismiss:focus {
						box-shadow: 0 0 0 1px #fff, 0 0 0 3px var(--e-button-context-color);
						background: var(--e-button-context-color);
						color: #fff;
					}

					.tsmlt-offer-notice .button-dismiss {
						border: 1px solid;
						background: 0 0;
						color: var(--e-button-context-color);
						background: #fff;
					}
				</style>
				<div class="tsmlt-offer-notice notice notice-info is-dismissible"
					 data-tsmltdismissable="tsmlt_offer">
					<img alt="<?php echo esc_attr( $this->options['plugin_name'] ); ?>"
						 src="<?php echo esc_url( $this->options['image_url'] ); ?>"
						 width="100px"
						 height="100px"/>
					<h3><?php echo sprintf( '%s', esc_html( $this->options['notice_for'] ) ); ?></h3>

					<p class="notice-text">
						<?php echo wp_kses_post( $this->options['notice_message'] ); ?>
					</p>
					<p>
						<a class="button button-primary"
						   href="<?php echo esc_url( $this->options['download_link'] ); ?>" target="_blank">Buy Now</a>
						<a class="button button-dismiss" href="#">Dismiss</a>
					</p>
				</div>
				<?php
			}
		);

		add_action(
			'admin_footer',
			function () {
				?>
				<script type="text/javascript">
					(function ($) {
						$(function () {
							setTimeout(function () {
								$('div[data-tsmltdismissable] .notice-dismiss, div[data-tsmltdismissable] .button-dismiss')
									.on('click', function (e) {
										e.preventDefault();
										$.post(ajaxurl, {
											'action': 'tsmlt_dismiss_offer_admin_notice',
											'nonce': <?php echo wp_json_encode( wp_create_nonce( 'tsmlt-offer-dismissible-notice' ) ); ?>
										});
										$(e.target).closest('.is-dismissible').remove();
									});
							}, 1000);
						});
					})(jQuery);
				</script>
				<?php
			}
		);

		add_action(
			'wp_ajax_tsmlt_dismiss_offer_admin_notice',
			function () {
				check_ajax_referer( 'tsmlt-offer-dismissible-notice', 'nonce' );
				if ( ! empty( $this->options['option_name'] ) ) {
					update_option( $this->options['option_name'], '1' );
				}
				wp_die();
			}
		);
	}
}
