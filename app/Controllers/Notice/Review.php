<?php

namespace TinySolutions\mlt\Controllers\Notice;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * Review class
 */
class Review {
	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Template builder post type
	 *
	 * @var string
	 */
	public string $textdomain = 'tsmlt';

	/**
	 * Init
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'admin_init', [ $this, 'tsmlt_check_installation_time' ], 10 );
		add_action( 'admin_init', [ $this, 'tsmlt_spare_me' ], 5 );
		add_action( 'admin_footer', [ $this, 'deactivation_popup' ], 99 );
	}

	/**
	 * Check if review notice should be shown or not
	 *
	 * @return void
	 */
	public function tsmlt_check_installation_time() {
		if ( isset( $GLOBALS['tsmlt__notice'] ) ) {
			 return;
		}
		// Added Lines Start.
		$nobug = get_option( 'tsmlt_spare_me' );

		$rated = get_option( 'tsmlt_rated' );

		if ( '1' == $nobug || 'yes' == $rated ) {
			return;
		}

		$now = strtotime( 'now' );

		$install_date = get_option( 'tsmlt_plugin_activation_time' );

		$past_date = strtotime( '+2 days', $install_date );

		$remind_time = get_option( 'tsmlt_remind_me' );

		if ( ! $remind_time ) {
			$remind_time = $install_date;
		}

		$remind_due = strtotime( '+10 days', $remind_time );

		if ( ! $now > $past_date || $now < $remind_due ) {
			return;
		}

		 add_action( 'admin_notices', [ $this, 'tsmlt_display_admin_notice' ] );
	}

	/**
	 * Remove the notice for the user if review already done or if the user does not want to
	 *
	 * @return void
	 */
	public function tsmlt_spare_me() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'tsmlt_notice_nonce' ) ) {
			return;
		}

		if ( isset( $_GET['tsmlt_spare_me'] ) && ! empty( $_GET['tsmlt_spare_me'] ) ) {
			$spare_me = absint( $_GET['tsmlt_spare_me'] );
			if ( 1 == $spare_me ) {
				update_option( 'tsmlt_spare_me', '1' );
			}
		}

		if ( isset( $_GET['tsmlt_remind_me'] ) && ! empty( $_GET['tsmlt_remind_me'] ) ) {
			$remind_me = absint( $_GET['tsmlt_remind_me'] );
			if ( 1 == $remind_me ) {
				$get_activation_time = strtotime( 'now' );
				update_option( 'tsmlt_remind_me', $get_activation_time );
			}
		}

		if ( isset( $_GET['tsmlt_rated'] ) && ! empty( $_GET['tsmlt_rated'] ) ) {
			$tsmlt_rated = absint( $_GET['tsmlt_rated'] );
			if ( 1 == $tsmlt_rated ) {
				update_option( 'tsmlt_rated', 'yes' );
			}
		}
	}

	/**
	 * @return false|string
	 */
	protected function tsmlt_current_admin_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$uri = preg_replace( '|^.*/wp-admin/|i', '', $uri );

		if ( ! $uri ) {
			return '';
		}

		return remove_query_arg(
			[
				'_wpnonce',
				'_wc_notice_nonce',
				'wc_db_update',
				'wc_db_update_nonce',
				'wc-hide-notice',
				'tsmlt_spare_me',
				'tsmlt_remind_me',
				'tsmlt_rated',
			],
			admin_url( $uri )
		);
	}

	/**
	 * Display Admin Notice, asking for a review
	 **/
	public function tsmlt_display_admin_notice() {
		// WordPress global variable.
		global $pagenow;
		$exclude = [
			'themes.php',
			'users.php',
			'tools.php',
			'options-general.php',
			'options-writing.php',
			'options-reading.php',
			'options-discussion.php',
			'options-media.php',
			'options-permalink.php',
			'options-privacy.php',
			'admin.php',
			'import.php',
			'export.php',
			'site-health.php',
			'export-personal-data.php',
			'erase-personal-data.php',
		];

		if ( ! in_array( $pagenow, $exclude, true ) ) {
			$args = [ '_wpnonce' => wp_create_nonce( 'tsmlt_notice_nonce' ) ];

			$dont_disturb = add_query_arg( $args + [ 'tsmlt_spare_me' => '1' ], $this->tsmlt_current_admin_url() );
			$remind_me    = add_query_arg( $args + [ 'tsmlt_remind_me' => '1' ], $this->tsmlt_current_admin_url() );
			$rated        = add_query_arg( $args + [ 'tsmlt_rated' => '1' ], $this->tsmlt_current_admin_url() );
			$reviewurl    = 'https://wordpress.org/support/plugin/media-library-tools/reviews/#new-post';
			$plugin_name  = 'Media Library Tools';
			?>
			<div class="notice tsmlt-review-notice tsmlt-review-notice--extended">
				<div class="tsmlt-review-notice_content">
					<h3>Enjoying "<?php echo esc_html( $plugin_name ); ?>"? </h3>
					<p>
						Thank you for choosing " <strong><?php echo esc_html( $plugin_name ); ?></strong>". If you have indeed benefited from our services, we kindly request that you, please consider giving us a 5-star rating on WordPress.org.
					</p>
					<div class="tsmlt-review-notice_actions">
						<a href="<?php echo esc_url( $reviewurl ); ?>"
						   class="tsmlt-review-button tsmlt-review-button-deseeve tsmlt-review-button--cta" target="_blank"><span>⭐ Yes, You Deserve It!</span></a>
						<a href="<?php echo esc_url( $rated ); ?>"
						   class="tsmlt-review-button tsmlt-review-button--cta tsmlt-review-button--outline"><span>😀 Already Rated!</span></a>
						<a href="<?php echo esc_url( $remind_me ); ?>"
						   class="tsmlt-review-button tsmlt-review-button--cta tsmlt-review-button--outline"><span>🔔 Remind Me Later</span></a>
					</div>
				</div>
			</div>
			<style>
				.tsmlt-review-button--cta {
					--e-button-context-color: #1677ff;
					--e-button-context-color-dark: #1677ff;
					--e-button-context-tint: rgb(75 47 157/4%);
					--e-focus-color: rgb(75 47 157/40%);
				}

				.tsmlt-review-notice {
					position: relative;
					margin: 5px 20px 5px 2px;
					border: 1px solid #ccd0d4;
					background: #fff;
					box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
					font-family: Roboto, Arial, Helvetica, Verdana, sans-serif;
					border-inline-start-width: 4px;
				}

				.tsmlt-review-notice.notice {
					padding: 0;
				}

				.tsmlt-review-notice:before {
					position: absolute;
					top: -1px;
					bottom: -1px;
					left: -4px;
					display: block;
					width: 4px;
					background: -webkit-linear-gradient(bottom, #5d3dfd 0%, #6939c6 100%);
					background: linear-gradient(0deg, #5d3dfd 0%, #6939c6 100%);
					content: "";
				}

				.tsmlt-review-notice_content {
					padding: 20px;
				}

				.tsmlt-review-notice_actions > * + * {
					margin-inline-start: 8px;
					-webkit-margin-start: 8px;
					-moz-margin-start: 8px;
				}

				.tsmlt-review-notice p {
					margin: 0;
					padding: 0;
					line-height: 1.5;
				}

				p + .tsmlt-review-notice_actions {
					margin-top: 1rem;
				}

				.tsmlt-review-notice h3 {
					margin: 0;
					font-size: 1.0625rem;
					line-height: 1.2;
				}

				.tsmlt-review-notice h3 + p {
					margin-top: 8px;
				}

				.tsmlt-review-button {
					display: inline-block;
					padding: 0.4375rem 0.75rem;
					border: 0;
					border-radius: 3px;;
					background: var(--e-button-context-color);
					color: #fff;
					vertical-align: middle;
					text-align: center;
					text-decoration: none !important;
					white-space: nowrap;
				}
				.tsmlt-review-button-deseeve{
					color: #fff !important;
				}
				.tsmlt-review-button:active {
					background: var(--e-button-context-color-dark);
					color: #fff;
					text-decoration: none;
				}

				.tsmlt-review-button:focus {
					outline: 0;
					background: var(--e-button-context-color-dark);
					box-shadow: 0 0 0 2px var(--e-focus-color);
					color: #fff;
					text-decoration: none;
				}

				.tsmlt-review-button:hover {
					background: var(--e-button-context-color-dark);
					color: #fff;
					text-decoration: none;
				}

				.tsmlt-review-button.focus {
					outline: 0;
					box-shadow: 0 0 0 2px var(--e-focus-color);
				}

				.tsmlt-review-button--error {
					--e-button-context-color: #d72b3f;
					--e-button-context-color-dark: #ae2131;
					--e-button-context-tint: rgba(215, 43, 63, 0.04);
					--e-focus-color: rgba(215, 43, 63, 0.4);
				}

				.tsmlt-review-button.tsmlt-review-button--outline {
					border: 1px solid;
					background: 0 0;
					color: var(--e-button-context-color);
				}

				.tsmlt-review-button.tsmlt-review-button--outline:focus {
					background: var(--e-button-context-tint);
					color: var(--e-button-context-color-dark);
				}

				.tsmlt-review-button.tsmlt-review-button--outline:hover {
					background: var(--e-button-context-tint);
					color: var(--e-button-context-color-dark);
				}
			</style>
			<?php
		}
	}

	/***
	 *
	 * @return mixed
	 */
	public function deactivation_popup() {
		global $pagenow;
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		$this->dialog_box_style();
		$this->deactivation_scripts();
		$td = esc_attr( $this->textdomain );
		?>
		<div id="deactivation-dialog-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above ?>" title="<?php esc_attr_e( 'Quick Feedback: How can we improve the plugin?', 'media-library-tools' ); ?>">
			<div class="tsmlt-deactivate-modal">

				<p class="tsmlt-deactivate-intro">
					<?php esc_html_e( "We're sorry to see you go! Before you leave, could you spare a moment to tell us why? Your feedback helps us make the plugin better for everyone.", 'media-library-tools' ); ?>
				</p>

				<div class="tsmlt-deactivate-support">
					<span class="tsmlt-deactivate-support__text"><?php esc_html_e( 'Having trouble? Our support team is here to help.', 'media-library-tools' ); ?></span>
					<a class="tsmlt-deactivate-support__btn" target="_blank" rel="noopener" href="https://help.wptinysolutions.com/"><?php esc_html_e( 'Contact Support', 'media-library-tools' ); ?> &rarr;</a>
				</div>

				<div class="tsmlt-deactivate-divider">
					<span><?php esc_html_e( 'Or tell us why you\'re leaving', 'media-library-tools' ); ?></span>
				</div>

				<div class="tsmlt-deactivate-reasons" id="feedback-form-body-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">

					<label class="tsmlt-deactivate-reason">
						<input class="tsmlt-deactivate-reason__radio" type="radio" name="reason_key"
							   id="feedback-deactivate-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>-bug_issue_detected"
							   value="bug_issue_detected">
						<span class="tsmlt-deactivate-reason__icon">🐛</span>
						<span class="tsmlt-deactivate-reason__text"><?php esc_html_e( 'Bug or issue detected', 'media-library-tools' ); ?></span>
					</label>

					<label class="tsmlt-deactivate-reason">
						<input class="tsmlt-deactivate-reason__radio" type="radio" name="reason_key"
							   id="feedback-deactivate-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>-no_longer_needed"
							   value="no_longer_needed">
						<span class="tsmlt-deactivate-reason__icon">👋</span>
						<span class="tsmlt-deactivate-reason__text"><?php esc_html_e( 'I no longer need the plugin', 'media-library-tools' ); ?></span>
					</label>

					<label class="tsmlt-deactivate-reason">
						<input class="tsmlt-deactivate-reason__radio" type="radio" name="reason_key"
							   id="feedback-deactivate-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>-found_a_better_plugin"
							   value="found_a_better_plugin">
						<span class="tsmlt-deactivate-reason__icon">🔍</span>
						<span class="tsmlt-deactivate-reason__text"><?php esc_html_e( 'I found a better plugin', 'media-library-tools' ); ?></span>
					</label>
					<div class="tsmlt-deactivate-better-plugin" id="tsmlt-better-plugin-input-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
						<input class="tsmlt-deactivate-better-plugin__input" type="text" name="reason_found_a_better_plugin"
							   placeholder="<?php esc_attr_e( 'Which plugin did you switch to?', 'media-library-tools' ); ?>">
					</div>

					<label class="tsmlt-deactivate-reason">
						<input class="tsmlt-deactivate-reason__radio" type="radio" name="reason_key"
							   id="feedback-deactivate-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>-couldnt_get_the_plugin_to_work"
							   value="couldnt_get_the_plugin_to_work">
						<span class="tsmlt-deactivate-reason__icon">⚙️</span>
						<span class="tsmlt-deactivate-reason__text"><?php esc_html_e( "I couldn't get the plugin to work", 'media-library-tools' ); ?></span>
					</label>

					<label class="tsmlt-deactivate-reason">
						<input class="tsmlt-deactivate-reason__radio" type="radio" name="reason_key"
							   id="feedback-deactivate-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>-temporary_deactivation"
							   value="temporary_deactivation">
						<span class="tsmlt-deactivate-reason__icon">⏸️</span>
						<span class="tsmlt-deactivate-reason__text"><?php esc_html_e( "It's a temporary deactivation", 'media-library-tools' ); ?></span>
					</label>

					<span class="tsmlt-deactivate-error" id="tsmlt-reason-error-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"></span>
				</div>

				<div class="tsmlt-deactivate-textarea-wrap" id="tsmlt-textarea-wrap-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
					<textarea id="deactivation-feedback-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
							  placeholder="<?php esc_attr_e( 'How can we improve the plugin? Any details help...', 'media-library-tools' ); ?>"></textarea>
					<span class="tsmlt-deactivate-error" id="tsmlt-feedback-error-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"></span>
				</div>

				<div class="tsmlt-deactivate-warning">
					⚠️ <?php esc_html_e( 'Deactivating will disable scheduled features like the rubbish file scanner. See the Settings page for details.', 'media-library-tools' ); ?>
				</div>

			</div>
		</div>
		<?php
	}

	/***
	 *
	 * @return mixed
	 */
	public function dialog_box_style() {
		$td = esc_attr( $this->textdomain );
		?>
		<style>
			/* ── Overlay ── */
			.ui-widget-overlay.ui-front {
				position: fixed;
				inset: 0;
				z-index: 9;
				background: rgba(0, 0, 0, 0.55);
			}

			/* ── Dialog shell ── */
			#deactivation-dialog-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> {
				display: none;
			}

			.ui-dialog[aria-describedby="deactivation-dialog-<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"] {
				background: #fff;
				border-radius: 10px;
				box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
				border: none;
				padding: 0;
				overflow: hidden;
				z-index: 99999;
			}

			/* ── Title bar ── */
			.ui-dialog-titlebar-close { display: none; }

			.ui-dialog-title {
				font-size: 16px;
				font-weight: 700;
				color: #1d2327;
			}

			.ui-draggable .ui-dialog-titlebar {
				padding: 16px 20px;
				border-bottom: 1px solid #f0f0f0;
				background: #f9f9f9;
			}

			/* ── Modal body ── */
			.tsmlt-deactivate-modal {
				padding: 20px 20px 8px;
				display: flex;
				flex-direction: column;
				gap: 14px;
			}

			/* Intro text */
			.tsmlt-deactivate-intro {
				font-size: 13px;
				color: #50575e;
				margin: 0;
				line-height: 1.6;
			}

			/* Support banner */
			.tsmlt-deactivate-support {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 12px;
				background: #f0f7ff;
				border: 1px solid #bdd7f5;
				border-radius: 6px;
				padding: 10px 14px;
			}

			.tsmlt-deactivate-support__text {
				font-size: 13px;
				color: #1d2327;
				font-weight: 500;
			}

			.tsmlt-deactivate-support__btn {
				display: inline-block;
				background: #2271b1;
				color: #fff;
				font-size: 12px;
				font-weight: 600;
				padding: 6px 14px;
				border-radius: 4px;
				text-decoration: none;
				white-space: nowrap;
				transition: background 0.2s;
			}

			.tsmlt-deactivate-support__btn:hover {
				background: #135e96;
				color: #fff;
			}

			/* OR divider */
			.tsmlt-deactivate-divider {
				display: flex;
				align-items: center;
				gap: 10px;
				font-size: 12px;
				color: #8c8f94;
				font-weight: 500;
				text-transform: uppercase;
				letter-spacing: 0.05em;
			}

			.tsmlt-deactivate-divider::before,
			.tsmlt-deactivate-divider::after {
				content: '';
				flex: 1;
				height: 1px;
				background: #e0e0e0;
			}

			/* Reasons list */
			.tsmlt-deactivate-reasons {
				display: flex;
				flex-direction: column;
				gap: 4px;
			}

			.tsmlt-deactivate-reason {
				display: flex;
				align-items: center;
				gap: 5px;
				padding: 8px 12px;
				border-radius: 6px;
				border: 1px solid transparent;
				cursor: pointer;
				transition: background 0.15s, border-color 0.15s;
			}

			.tsmlt-deactivate-reason:hover {
				background: #f6f7f7;
				border-color: #e0e0e0;
			}

			.tsmlt-deactivate-reason:has(input:checked) {
				background: #f0f7ff;
				border-color: #bdd7f5;
			}

			.tsmlt-deactivate-reason__radio {
				margin: 0;
				flex-shrink: 0;
				accent-color: #2271b1;
				width: 15px;
				height: 15px;
				cursor: pointer;
			}

			.tsmlt-deactivate-reason__icon {
				font-size: 16px;
				line-height: 1;
				flex-shrink: 0;
			}

			.tsmlt-deactivate-reason__text {
				font-size: 15px;
				color: #1d2327;
				line-height: 1.4;
			}

			/* "Better plugin" name input — hidden until its radio is checked */
			.tsmlt-deactivate-better-plugin {
				display: none;
				padding: 4px 12px 4px 48px;
			}

			.tsmlt-deactivate-better-plugin__input {
				width: 100%;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 6px 10px;
				font-size: 13px;
				color: #1d2327;
				box-sizing: border-box;
			}

			.tsmlt-deactivate-better-plugin__input:focus {
				border-color: #2271b1;
				outline: none;
				box-shadow: 0 0 0 1px #2271b1;
			}

			/* Textarea */
			.tsmlt-deactivate-textarea-wrap {
				display: flex;
				flex-direction: column;
				gap: 4px;
			}

			.tsmlt-deactivate-textarea-wrap textarea {
				width: 100%;
				min-height: 80px;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 10px;
				font-size: 13px;
				color: #1d2327;
				resize: vertical;
				box-sizing: border-box;
				transition: border-color 0.15s;
			}

			.tsmlt-deactivate-textarea-wrap textarea:focus {
				border-color: #2271b1;
				outline: none;
				box-shadow: 0 0 0 1px #2271b1;
			}

			/* Error messages */
			.tsmlt-deactivate-error {
				font-size: 12px;
				color: #d63638;
				min-height: 16px;
				display: block;
			}

			/* Warning notice */
			.tsmlt-deactivate-warning {
				background: #fff8e5;
				border-left: 3px solid #dba617;
				border-radius: 0 4px 4px 0;
				padding: 9px 12px;
				font-size: 12px;
				color: #50575e;
				line-height: 1.5;
			}

			/* ── Button bar ── */
			.ui-dialog-buttonset {
				background: #fff;
				border-top: 1px solid #f0f0f0;
				padding: 14px 20px;
				display: flex;
				gap: 10px;
				justify-content: flex-end;
			}

			.ui-dialog-buttonset button {
				min-width: 140px;
				height: 36px;
				padding: 0 16px;
				border-radius: 5px;
				font-size: 13px;
				font-weight: 600;
				cursor: pointer;
				transition: background 0.2s, color 0.2s;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				margin: 0;
				border: 1px solid #c3c4c7;
				background: #f6f7f7;
				color: #1d2327;
			}

			/* "Send Feedback & Deactivate" — primary action */
			.ui-dialog-buttonset button:first-child {
				background: #2271b1;
				border-color: #2271b1;
				color: #fff;
			}

			.ui-dialog-buttonset button:first-child:hover {
				background: #135e96;
				border-color: #135e96;
			}

			.ui-dialog-buttonset button:first-child:hover .deactive-loading-spinner {
				border-color: #fff;
				border-top-color: transparent;
			}

			/* "Skip & Deactivate" — secondary action */
			.ui-dialog-buttonset button:nth-child(2):hover {
				background: #dcdcde;
			}

			/* Loading spinner */
			.deactive-loading-spinner {
				display: inline-block;
				width: 10px;
				height: 10px;
				border: 2px solid #fff;
				border-top-color: transparent;
				border-radius: 50%;
				animation: tsmlt-spin 0.8s linear infinite;
				margin-left: 8px;
				flex-shrink: 0;
			}

			@keyframes tsmlt-spin {
				to { transform: rotate(360deg); }
			}
		</style>
		<?php
	}

	/***
	 *
	 * @return mixed
	 */
	public function deactivation_scripts() {
		wp_enqueue_script( 'jquery-ui-dialog' );
		$td = esc_js( $this->textdomain );
		?>
		<script>
			jQuery(document).ready(function ($) {
				var td          = '<?php echo $td; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>';
				var $dialog     = $('#deactivation-dialog-' + td);
				var $deactLink  = $('.deactivate #deactivate-media-library-tools');

				// Show/hide "better plugin" text input when its radio is selected
				$dialog.on('change', 'input[type="radio"]', function () {
					var $betterInput = $('#tsmlt-better-plugin-input-' + td);
					if ($(this).val() === 'found_a_better_plugin') {
						$betterInput.slideDown(150);
					} else {
						$betterInput.slideUp(150);
					}
					$('#tsmlt-reason-error-' + td).text('');
				});

				$dialog.on('input', 'textarea', function () {
					$('#tsmlt-feedback-error-' + td).text('');
				});

				// Open dialog when Deactivate link is clicked
				$deactLink.on('click', function (e) {
					e.preventDefault();
					var deactivateHref = $deactLink.attr('href');

					var dialogbox = $dialog.dialog({
						modal:  true,
						width:  580,
						show: { effect: 'fadeIn',  duration: 250 },
						hide: { effect: 'fadeOut', duration: 150 },
						buttons: {
							Submit: function () {
								var $btn = $(this).parents('.ui-dialog.ui-front').find('.ui-dialog-buttonset button.ui-button:first-child');
								submitFeedback($btn, deactivateHref);
							},
							Cancel: function () {
								$(this).dialog('close');
								window.location.href = deactivateHref;
							}
						}
					});

					// Close on overlay click
					$(document).off('click.tsmlt-deactivate').on('click.tsmlt-deactivate', '.ui-widget-overlay.ui-front', function (e) {
						if ($(e.target).closest(dialogbox.parent()).length === 0) {
							dialogbox.dialog('close');
						}
					});

					// Rename buttons
					$('.ui-dialog-buttonpane button:contains("Submit")').text(<?php echo wp_json_encode( __( 'Send Feedback & Deactivate', 'media-library-tools' ) ); ?>);
					$('.ui-dialog-buttonpane button:contains("Cancel")').text(<?php echo wp_json_encode( __( 'Skip & Deactivate', 'media-library-tools' ) ); ?>);
				});

				function submitFeedback($btn, deactivateHref) {
					var reasons      = $dialog.find('input[type="radio"]:checked').val();
					var feedback     = $('#deactivation-feedback-' + td).val().trim();
					var betterPlugin = $dialog.find('input[name="reason_found_a_better_plugin"]').val();

					// Validate: reason required
					if (!reasons) {
						$('#tsmlt-reason-error-' + td).text('<?php echo esc_js( __( 'Please choose a reason before submitting.', 'media-library-tools' ) ); ?>');
						return;
					}

					// Validate: feedback required unless temporary deactivation
					if (reasons !== 'temporary_deactivation' && !feedback) {
						$('#tsmlt-feedback-error-' + td).text('<?php echo esc_js( __( 'Kindly share a few details so we can address this in a future update.', 'media-library-tools' ) ); ?>');
						return;
					}

					// Temporary deactivation — skip feedback, just deactivate
					if (reasons === 'temporary_deactivation') {
						window.location.href = deactivateHref;
						return;
					}

					$btn.html('<?php echo esc_js( __( 'Sending…', 'media-library-tools' ) ); ?> <span class="deactive-loading-spinner"></span>');
					$btn.prop('disabled', true);

					$.ajax({
						url:      'https://www.wptinysolutions.com/wp-json/TinySolutions/pluginSurvey/v1/Survey/appendToSheet',
						method:   'GET',
						dataType: 'json',
						data: {
							website:      '<?php echo esc_url( home_url() ); ?>',
							reasons:      reasons || '',
							better_plugin: betterPlugin || '',
							feedback:     feedback,
							wpplugin:     'media-tools',
						},
						complete: function () {
							window.location.href = deactivateHref;
						}
					});
				}
			});
		</script>
		<?php
	}
}
