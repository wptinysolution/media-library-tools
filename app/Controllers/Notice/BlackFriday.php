<?php
/**
 * Black Friday Offer.
 *
 * @package RadiusTheme\SB
 */

namespace TinySolutions\mlt\Controllers\Notice;

use TinySolutions\mlt\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Black Friday Offer.
 */
class BlackFriday {

	/**
	 * Singleton Trait.
	 */
	use SingletonTrait;

	/**
	 * Class Constructor.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action(
			'admin_init',
			function () {
				$current = time();
                $start         = strtotime( '19 November 2023' );
                $end           = strtotime( '05 January 2024' );
                // Black Friday Notice
                if ( ! tsmlt()->has_pro() && $start <= $current && $current <= $end ) {
                    if ( get_option( 'tsmlt_bf_2023' ) != '1' ) {
                        if ( ! isset( $GLOBALS['tsmlt_bf_2023_notice'] ) ) {
                            $GLOBALS['tsmlt_bf_2023_notice'] = 'tsmlt_bf_2023';
                            self::black_friday_notice();
                        }
                    }
                }
			}
		);
	}

	/**
	 * Black Friday Notice.
	 *
	 * @return void
	 */
	public static function black_friday_notice() {
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
					.tsmlt-bf-notice {
						--e-button-context-color: #2179c0;
						--e-button-context-color-dark: #2271b1;
						--e-button-context-tint: rgb(75 47 157/4%);
						--e-focus-color: rgb(75 47 157/40%);
						display:grid;
						grid-template-columns: 100px auto;
						padding-top: 25px;
						padding-bottom: 22px;
						column-gap: 15px;
					}

					.tsmlt-bf-notice img {
						grid-row: 1 / 4;
						align-self: center;
						justify-self: center;
					}

					.tsmlt-bf-notice h3,
					.tsmlt-bf-notice p {
						margin: 0 !important;
					}

					.tsmlt-bf-notice .notice-text {
						margin: 0 0 2px;
						padding: 5px 0;
						max-width: 100%;
						font-size: 14px;
					}

					.tsmlt-bf-notice .button-primary,
					.tsmlt-bf-notice .button-dismiss {
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

					.tsmlt-bf-notice .button-primary:hover,
					.tsmlt-bf-notice .button-dismiss:hover {
						background: var(--e-button-context-color);
                        border-color: var(--e-button-context-color);
						color: #fff;
					}

					.tsmlt-bf-notice .button-primary:focus,
					.tsmlt-bf-notice .button-dismiss:focus {
						box-shadow: 0 0 0 1px #fff, 0 0 0 3px var(--e-button-context-color);
						background: var(--e-button-context-color);
						color: #fff;
					}

					.tsmlt-bf-notice .button-dismiss {
						border: 1px solid;
						background: 0 0;
						color: var(--e-button-context-color);
						background: #fff;
					}
				</style>
				<?php
				$plugin_name   = 'Media Library Tools Pro';
				$download_link = 'https://www.wptinysolutions.com/tiny-products/media-library-tools/';
				?>
				<div class="tsmlt-bf-notice notice notice-info is-dismissible" data-tsmltdismissable="tsmlt_bf_2023">
					<img alt="<?php echo esc_attr( $plugin_name ); ?>"
						 src="<?php echo esc_url( tsmlt()->get_assets_uri( 'images/media-library-tools-icon-128x128.png' ) ); ?>" width="100px"
						 height="100px" />
					<h3><?php echo sprintf( '%s – Black Friday Deal!!', esc_html( $plugin_name ) ); ?></h3>

					<p class="notice-text">
						Don't miss out on our biggest sale of the year! Get your
						<b>Media Library Tools Pro plan</b> with <b>UP TO 40% OFF</b>! Limited time
						offer!!
					</p>

					<p>
						<a class="button button-primary" href="<?php echo esc_url( $download_link ); ?>" target="_blank">Buy
							Now</a>
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
											'action': 'tsmlt_dismiss_bf_admin_notice',
											'nonce': <?php echo wp_json_encode( wp_create_nonce( 'tsmlt-bf-dismissible-notice' ) ); ?>
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
			'wp_ajax_tsmlt_dismiss_bf_admin_notice',
			function () {
				check_ajax_referer( 'tsmlt-bf-dismissible-notice', 'nonce' );

				update_option( 'tsmlt_bf_2023', '1' );
				wp_die();
			}
		);
	}
}
