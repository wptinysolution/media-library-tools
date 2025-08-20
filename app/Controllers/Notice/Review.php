<?php

namespace TinySolutions\mlt\Controllers\Notice;

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
		// Added Lines Start
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

		$remind_due = strtotime( '+20 days', $remind_time );

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

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'tsmlt_notice_nonce' ) ) {
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
		// WordPress global variable
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

		if ( ! in_array( $pagenow, $exclude ) ) {

			$args = [ '_wpnonce' => wp_create_nonce( 'tsmlt_notice_nonce' ) ];

			$dont_disturb = add_query_arg( $args + [ 'tsmlt_spare_me' => '1' ], $this->tsmlt_current_admin_url() );
			$remind_me    = add_query_arg( $args + [ 'tsmlt_remind_me' => '1' ], $this->tsmlt_current_admin_url() );
			$rated        = add_query_arg( $args + [ 'tsmlt_rated' => '1' ], $this->tsmlt_current_admin_url() );
			$reviewurl    = 'https://wordpress.org/support/plugin/media-library-tools/reviews/?filter=5#new-post';
			$plugin_name  = 'Media Library Tools';
			?>
			<div class="notice tsmlt-review-notice tsmlt-review-notice--extended">
				<div class="tsmlt-review-notice_content">
					<h3>Enjoying "<?php echo $plugin_name; ?>"? </h3>
					<p>
						Thank you for choosing " <strong><?php echo $plugin_name; ?></strong>". If you have indeed benefited from our services, we kindly request that you, please consider giving us a 5-star rating on WordPress.org.
					</p>
					<div class="tsmlt-review-notice_actions">
						<a href="<?php echo esc_url( $reviewurl ); ?>"
						   class="tsmlt-review-button tsmlt-review-button--cta" target="_blank"><span>⭐ Yes, You Deserve It!</span></a>
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
					text-decoration: none;
					white-space: nowrap;
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

	// Servay

	/***
	 * @param $mimes
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
		?>
		<div id="deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?>" title="Quick Feedback: How can we improve the plugin?">
			<!-- Modal content -->
			<div class="modal-content">
				<div id="feedback-form-body-<?php echo esc_attr( $this->textdomain ); ?>">
					<p style="margin: 0 0 15px 0;">
						Contact: <span style="color: green; font-size:20px;"> <a class="contact-link" target="_blank" data-subject="pre_sale_question" href="https://help.wptinysolutions.com/">To Our Support</a> </span>
					</p>
					<p style="font-size: 20px;margin: 0 0 15px 0;">
						OR
					</p>
					<div class="feedback-input-wrapper">
						<input id="feedback-deactivate-<?php echo esc_attr( $this->textdomain ); ?>-bug_issue_detected" class="feedback-input"
							   type="radio" name="reason_key" value="bug_issue_detected">
						<label for="feedback-deactivate-<?php echo esc_attr( $this->textdomain ); ?>-bug_issue_detected" class="feedback-label">Bug Or Issue detected.</label>
					</div>

					<div class="feedback-input-wrapper">
						<input id="feedback-deactivate-<?php echo esc_attr( $this->textdomain ); ?>-no_longer_needed" class="feedback-input" type="radio"
							   name="reason_key" value="no_longer_needed">
						<label for="feedback-deactivate-<?php echo esc_attr( $this->textdomain ); ?>-no_longer_needed" class="feedback-label">I no longer
							need the plugin</label>
					</div>
					<div class="feedback-input-wrapper">
						<input id="feedback-deactivate-<?php echo esc_attr( $this->textdomain ); ?>-found_a_better_plugin" class="feedback-input"
							   type="radio" name="reason_key" value="found_a_better_plugin">
						<label for="feedback-deactivate-<?php echo esc_attr( $this->textdomain ); ?>-found_a_better_plugin" class="feedback-label">I found a
							better plugin</label>
						<input class="feedback-feedback-text" type="text" name="reason_found_a_better_plugin"
							   placeholder="Please share the plugin name">
					</div>
					<div class="feedback-input-wrapper">
						<input id="feedback-deactivate-<?php echo esc_attr( $this->textdomain ); ?>-couldnt_get_the_plugin_to_work" class="feedback-input"
							   type="radio" name="reason_key" value="couldnt_get_the_plugin_to_work">
						<label for="feedback-deactivate-<?php echo esc_attr( $this->textdomain ); ?>-couldnt_get_the_plugin_to_work" class="feedback-label">I
							couldn't get the plugin to work</label>
					</div>

					<div class="feedback-input-wrapper">
						<input id="feedback-deactivate-<?php echo esc_attr( $this->textdomain ); ?>-temporary_deactivation" class="feedback-input"
							   type="radio" name="reason_key" value="temporary_deactivation">
						<label for="feedback-deactivate-<?php echo esc_attr( $this->textdomain ); ?>-temporary_deactivation" class="feedback-label">It's a
							temporary deactivation</label>
					</div>
					<span style="color:red;font-size: 16px;"></span>
				</div>
				<p style="margin: 0 0 15px 0; color:red;">
					If you deactivate the plugin, some features will cease to function.
					For instance, the rubbish file scanner is a notable example.
					For additional information, please consult the settings page.
				</p>
				<div class="feedback-text-wrapper-<?php echo esc_attr( $this->textdomain ); ?>">
					<textarea id="deactivation-feedback-<?php echo esc_attr( $this->textdomain ); ?>" rows="4" cols="40"
							  placeholder=" Write something here. How can we improve the plugin?"></textarea>
					<span style="color:red;font-size: 16px;"></span>
				</div>
				<p style="margin: 0;">
					Please let us know about any issues you are facing with the plugin.
				</p>
			</div>
		</div>
		<?php
	}

	/***
	 * @param $mimes
	 *
	 * @return mixed
	 */
	public function dialog_box_style() {
		?>
		<style>
			/* Add Animation */
			@-webkit-keyframes animatetop {
				from {
					top: -300px;
					opacity: 0
				}
				to {
					top: 0;
					opacity: 1
				}
			}

			@keyframes animatetop {
				from {
					top: -300px;
					opacity: 0
				}
				to {
					top: 0;
					opacity: 1
				}
			}

			#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?> {
				display: none;
			}

			.ui-dialog-titlebar-close {
				display: none;
			}

			/* The Modal (background) */
			#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?> .modal {
				display: none; /* Hidden by default */
				position: fixed; /* Stay in place */
				z-index: 1; /* Sit on top */
				padding-top: 100px; /* Location of the box */
				left: 0;
				top: 0;
				width: 100%; /* Full width */
				height: 100%; /* Full height */
				overflow: auto; /* Enable scroll if needed */
			}

			/* Modal Content */
			#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?> .modal-content {
				position: relative;
				margin: auto;
				padding: 0;
			}


			#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?> .feedback-label {
				font-size: 13px;
			}

			div#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?> p {
				font-size: 13px;
			}

			#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?> .modal-content > * {
				width: 100%;
				padding: 5px 2px;
				overflow: hidden;
			}

			#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?> .modal-content textarea {
				border: 1px solid rgba(0, 0, 0, 0.3);
				padding: 10px;
				width: 100%;
				height: 80px;
			}

			#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?> .modal-content input.feedback-feedback-text {
				border: 1px solid rgba(0, 0, 0, 0.3);
				min-width: 250px;
			}

			/* The Close Button */
			#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?> input[type="radio"] {
				margin: 0;
			}

			.ui-dialog-title {
				font-size: 18px;
				font-weight: 600;
			}

			#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?> .modal-body {
				padding: 2px 16px;
			}

			.ui-dialog-buttonset {
				background-color: #fefefe;
				padding: 0 17px 25px;
				display: flex;
				justify-content: space-between;
				gap: 10px;
			}

			.ui-dialog-buttonset button {
				min-width: 110px;
				text-align: center;
				border: 1px solid rgba(0, 0, 0, 0.1);
				padding: 0 15px;
				border-radius: 5px;
				height: 40px;
				font-size: 15px;
				font-weight: 600;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				cursor: pointer;
				transition: 0.3s all;
				background: rgba(0, 0, 0, 0.02);
				margin: 0;
				flex: 1;
			}

			.ui-dialog-buttonset button:nth-child(2) {
				background: transparent;
			}

			.ui-dialog-buttonset button:hover {
				background: #2271b1;
				color: #fff;
			}

			.ui-dialog-buttonset button:hover .deactive-loading-spinner{
				border-color: #fff;
				border-top-color: transparent;
			}

			.ui-dialog[aria-describedby="deactivation-dialog-tsmlt"] {
				background-color: #fefefe;
				box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
				z-index: 99;
			}

			div#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?>,
			.ui-draggable .ui-dialog-titlebar {
				padding: 18px 15px;
				box-shadow: 0 0 3px rgba(0, 0, 0, 0.1);
				text-align: left;
			}

			.modal-content .feedback-input-wrapper {
				margin-bottom: 8px;
				display: flex;
				align-items: center;
				gap: 8px;
				line-height: 2;
				padding: 0 2px;
			}

			.ui-widget-overlay.ui-front {
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				z-index: 9;
				background-color: rgba(0, 0, 0, 0.5);
			}
			/* Loading spinner styles */
			.deactive-loading-spinner {
				display: inline-block;
				width: 10px;
				height: 10px;
				border: 2px solid #333;
				border-top: 2px solid transparent;
				border-radius: 50%;
				animation: spin 1s linear infinite;
				margin-left: 10px;
			}

			@keyframes spin {
				0% { transform: rotate(0deg); }
				100% { transform: rotate(360deg); }
			}

		</style>

		<?php
	}

	/***
	 * @param $mimes
	 *
	 * @return mixed
	 */
	public function deactivation_scripts() {
		wp_enqueue_script( 'jquery-ui-dialog' );
		?>
		<script>
			jQuery(document).ready(function ($) {

				// Open the deactivation dialog when the 'Deactivate' link is clicked
				$('.deactivate #deactivate-media-library-tools').on('click', function (e) {
					e.preventDefault();
					var href = $('.deactivate #deactivate-media-library-tools').attr('href');
	 
					var dialogbox = $('#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?>').dialog({
						modal: true,
						width: 600,
						show: {
							effect: "fadeIn",
							duration: 400
						},
						hide: {
							effect: "fadeOut",
							duration: 100
						},
						buttons: {
							Submit: function () {
								// Set the submit button variable
								var button = $( this ).parents( '.ui-dialog.ui-front' ).find( '.ui-dialog-buttonset button.ui-button:first-child' );
								// Call the submitFeedback function to send the AJAX request.
								submitFeedback( button );
							},
							Cancel: function () {
								$(this).dialog('close');
								window.location.href = href;
							}
						}
					});
					
					// Close the dialog when clicking outside of it
					$(document).on('click', '.ui-widget-overlay.ui-front', function (event) {
						if ($(event.target).closest(dialogbox.parent()).length === 0) {
							dialogbox.dialog('close');
						}
					});

					// Customize the button text
					$('.ui-dialog-buttonpane button:contains("Submit")').text('Send Feedback & Deactivate');
					$('.ui-dialog-buttonpane button:contains("Cancel")').text('Skip & Deactivate');
				});

				// Submit the feedback
				// Submit the feedback
				function submitFeedback( button ) {
					// Define the submit button variable

					button.html('Loading... <span class="deactive-loading-spinner"></span>');
					button.prop('disabled', true);

					var href = $('.deactivate #deactivate-media-library-tools').attr('href');
					var reasons = $('#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?> input[type="radio"]:checked').val();
					var feedback = $('#deactivation-feedback-<?php echo esc_attr( $this->textdomain ); ?>').val();
					var better_plugin = $('#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?> .modal-content input[name="reason_found_a_better_plugin"]').val();
					// Perform AJAX request to submit feedback
					if ( 'temporary_deactivation' !== reasons && ! feedback ) {
						// Enable the submit button and update its text
						button.prop('disabled', false);
						button.html('Send Feedback & Deactivate');
						$('.feedback-text-wrapper-<?php echo esc_attr( $this->textdomain ); ?> span').text('Kindly provide additional details so we can address it in future updates.');
						return;
					}
					if (!reasons && !better_plugin) {
						// Enable the submit button and update its text
						button.prop('disabled', false);
						button.html('Send Feedback & Deactivate');
						// Define flag variables
						$('#feedback-form-body-<?php echo esc_attr( $this->textdomain ); ?> span').text('Choose The Reason');
					}

					if ('temporary_deactivation' == reasons && !feedback) {
						window.location.href = href;
					}

					$.ajax({
						url: 'https://www.wptinysolutions.com/wp-json/TinySolutions/pluginSurvey/v1/Survey/appendToSheet',
						method: 'GET',
						dataType: 'json',
						data: {
							website: '<?php echo esc_url( home_url() ); ?>',
							reasons: reasons ? reasons : '',
							better_plugin: better_plugin,
							feedback: feedback,
							wpplugin: 'media-tools',
						},
						success: function (response) {
							console.log('response', response );
							$('#deactivation-dialog-<?php echo esc_attr( $this->textdomain ); ?>').dialog('close');
						},
						error: function (xhr, status, error) {
							// Handle the error response
							console.error('Error', error);
							window.location.href = href;
						},
						complete: function (xhr, status) {
							window.location.href = href;
						}
					});
				}

			});

		</script>

		<?php
	}
}
