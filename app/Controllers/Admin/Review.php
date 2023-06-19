<?php

namespace TinySolutions\mlt\Controllers\Admin;

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
	 * Init
	 *
	 * @return void
	 */
	private function __construct() {
        add_action( 'admin_init', [ __CLASS__, 'tsmlt_check_installation_time' ] );
        add_action( 'admin_init', [ __CLASS__, 'tsmlt_spare_me' ], 5 );
		add_action( 'admin_footer', [ __CLASS__, 'deactivation_popup' ], 99 );
	}

	/**
     * Check if review notice should be shown or not
     *
     * @return void
     */
	public static function tsmlt_check_installation_time() {

		// Added Lines Start
		$nobug = get_option( 'tsmlt_spare_me' );

        $rated = get_option( 'tsmlt_rated' );

		if ( '1' == $nobug || 'yes' == $rated ) {
			return;
		}

		$now         = strtotime( 'now' );

		$install_date = get_option( 'tsmlt_plugin_activation_time' );

        $past_date    = strtotime( '+10 days', $install_date );

		$remind_time = get_option( 'tsmlt_remind_me' );

		if( ! $remind_time ){
			$remind_time = $install_date;
		}

        $remind_due  = strtotime( '+10 days', $remind_time );

        if ( ! $now > $past_date || $now < $remind_due ) {
            return;
        }

        add_action( 'admin_notices', [ __CLASS__, 'tsmlt_display_admin_notice' ] );

	}
    /**
     * Remove the notice for the user if review already done or if the user does not want to
     *
     * @return void
     */
    public static function tsmlt_spare_me() {

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
            $tsmlt_rated = absint(  $_GET['tsmlt_rated'] );
            if ( 1 == $tsmlt_rated ) {
                update_option( 'tsmlt_rated', 'yes' );
            }
        }
    }

	protected static function tsmlt_current_admin_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$uri = preg_replace( '|^.*/wp-admin/|i', '', $uri );

		if ( ! $uri ) {
			return '';
		}
		return remove_query_arg( [ '_wpnonce', '_wc_notice_nonce', 'wc_db_update', 'wc_db_update_nonce', 'wc-hide-notice', 'tsmlt_spare_me', 'tsmlt_remind_me', 'tsmlt_rated' ], admin_url( $uri ) );
	}

	/**
	 * Display Admin Notice, asking for a review
	 **/
	public static function tsmlt_display_admin_notice() {
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
            'erase-personal-data.php'
        ];

		if ( ! in_array( $pagenow, $exclude ) ) {

			$args = [ '_wpnonce' => wp_create_nonce( 'tsmlt_notice_nonce' ) ];

			$dont_disturb = add_query_arg( $args + [ 'tsmlt_spare_me' => '1' ], self::tsmlt_current_admin_url() );
			$remind_me    = add_query_arg( $args + [ 'tsmlt_remind_me' => '1' ], self::tsmlt_current_admin_url() );
			$rated        = add_query_arg( $args + [ 'tsmlt_rated' => '1' ], self::tsmlt_current_admin_url() );
			$reviewurl    = 'https://wordpress.org/support/plugin/media-library-tools/reviews/?filter=5#new-post';
			$plugin_name = 'Media Library Tools';
			?>
			<div class="notice tsmlt-review-notice tsmlt-review-notice--extended">
				<div class="tsmlt-review-notice_content">
                    <h3>Enjoying "<?php echo $plugin_name; ?>"? </h3>
                    <p>Thank you for choosing "<string><?php echo $plugin_name; ?></string>". If you found our plugin useful, please consider giving us a 5-star rating on WordPress.org. Your feedback  will motivate us to grow. </p>
					<div class="tsmlt-review-notice_actions">
						<a href="<?php echo esc_url( $reviewurl ); ?>" class="tsmlt-review-button tsmlt-review-button--cta" target="_blank"><span>⭐ Yes, You Deserve It!</span></a>
						<a href="<?php echo esc_url( $rated ); ?>" class="tsmlt-review-button tsmlt-review-button--cta tsmlt-review-button--outline"><span>😀 Already Rated!</span></a>
						<a href="<?php echo esc_url( $remind_me ); ?>" class="tsmlt-review-button tsmlt-review-button--cta tsmlt-review-button--outline"><span>🔔 Remind Me Later</span></a>
						<a href="<?php echo esc_url( $dont_disturb ); ?>" class="tsmlt-review-button tsmlt-review-button--cta tsmlt-review-button--error tsmlt-review-button--outline"><span>😐 No Thanks </span></a>
						<a href="<?php echo esc_url( 'https://www.wptinysolutions.com/' ); ?>" class="tsmlt-review-button tsmlt-review-button--cta tsmlt-review-button--error tsmlt-review-button--outline"><span>😐 Need Help. Contact our support </span></a>
                    </div>
				</div> 
			</div>
			<style> 
			.tsmlt-review-button--cta {
				--e-button-context-color: #5d3dfd;
				--e-button-context-color-dark: #5d3dfd;
				--e-button-context-tint: rgb(75 47 157/4%);
				--e-focus-color: rgb(75 47 157/40%);
			} 
			.tsmlt-review-notice {
				position: relative;
				margin: 5px 20px 5px 2px;
				border: 1px solid #ccd0d4;
				background: #fff;
				box-shadow: 0 1px 4px rgba(0,0,0,0.15);
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
				--e-button-context-tint: rgba(215,43,63,0.04);
				--e-focus-color: rgba(215,43,63,0.4);
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
	public static function deactivation_popup() {
		global $pagenow;
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		self::dialog_box_style();
		self::deactivation_scripts();
		$text_domain = 'tsmlt'; // This is for unique ID.
		?>
        <div id="deactivation-dialog" title="Quick Feedback">
            <!-- Modal content -->
            <div class="modal-content">
                <div id="feedback-form-body">
                    <div class="feedback-input-wrapper">
                        <input id="feedback-deactivate-<?php echo $text_domain; ?>-bug_issue_detected" class="feedback-input"
                               type="radio" name="reason_key" value="bug_issue_detected">
                        <label for="feedback-deactivate-<?php echo $text_domain; ?>-bug_issue_detected" class="feedback-label">Bug Or Issue detected.</label>
                    </div>

                    <div class="feedback-input-wrapper">
                        <input id="feedback-deactivate-<?php echo $text_domain; ?>-no_longer_needed" class="feedback-input" type="radio"
                               name="reason_key" value="no_longer_needed">
                        <label for="feedback-deactivate-<?php echo $text_domain; ?>-no_longer_needed" class="feedback-label">I no longer
                            need the plugin</label>
                    </div>
                    <div class="feedback-input-wrapper">
                        <input id="feedback-deactivate-<?php echo $text_domain; ?>-found_a_better_plugin" class="feedback-input"
                               type="radio" name="reason_key" value="found_a_better_plugin">
                        <label for="feedback-deactivate-<?php echo $text_domain; ?>-found_a_better_plugin" class="feedback-label">I found a
                            better plugin</label>
                        <input class="feedback-feedback-text" type="text" name="reason_found_a_better_plugin"
                               placeholder="Please share which plugin">
                    </div>
                    <div class="feedback-input-wrapper">
                        <input id="feedback-deactivate-<?php echo $text_domain; ?>-couldnt_get_the_plugin_to_work" class="feedback-input"
                               type="radio" name="reason_key" value="couldnt_get_the_plugin_to_work">
                        <label for="feedback-deactivate-<?php echo $text_domain; ?>-couldnt_get_the_plugin_to_work" class="feedback-label">I
                            couldn't get the plugin to work</label>
                    </div>
                    <div class="feedback-input-wrapper">
                        <input id="feedback-deactivate-<?php echo $text_domain; ?>-temporary_deactivation" class="feedback-input"
                               type="radio" name="reason_key" value="temporary_deactivation">
                        <label for="feedback-deactivate-<?php echo $text_domain; ?>-temporary_deactivation" class="feedback-label">It's a
                            temporary deactivation</label>
                    </div>

                </div>
                <p style="margin: 0 0 15px 0;">
                    Please let us know about any issues you are facing with the plugin.
                    How can we improve the plugin?
                </p>
                <textarea id="deactivation-feedback" rows="4" cols="40" placeholder=" Write something here. How can we improve the plugin?"></textarea>
                <p style="margin: 0;">
                    Your satisfaction is our utmost inspiration. Thank you for your feedback.
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
	public static function dialog_box_style() { ?>
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

            #deactivation-dialog {
                display: none;
            }

            .ui-dialog-titlebar-close {
                display: none;
            }

            /* The Modal (background) */
            #deactivation-dialog .modal {
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
            #deactivation-dialog .modal-content {
                position: relative;
                margin: auto;
                padding: 0;
            }

            #deactivation-dialog .modal-content > * {
                width: 100%;
                padding: 10px 0 2px;
                overflow: hidden;
            }
            #deactivation-dialog .feedback-label,
            div#deactivation-dialog p{
                font-weight: 500;
            }
            #deactivation-dialog .feedback-label {
                font-size: 15px;
            }
            div#deactivation-dialog p{
                font-size: 16px;
            }

            #deactivation-dialog .modal-content textarea {
                border: 1px solid rgba(0, 0, 0, 0.3);
                padding: 15px;
            }

            #deactivation-dialog .modal-content input.feedback-feedback-text {
                border: 1px solid rgba(0, 0, 0, 0.3);
            }

            /* The Close Button */
            #deactivation-dialog input[type="radio"] {
                margin: 0;
            }

            .ui-dialog-title {
                font-size: 18px;
                font-weight: 600;
            }

            #deactivation-dialog .modal-body {
                padding: 2px 16px;
            }

            .ui-dialog-buttonset {
                background-color: #fefefe;
                padding: 0 18px 25px;
            }

            .ui-dialog-buttonset button {
                min-width: 110px;
                text-align: center;
                border: 1px solid rgba(0, 0, 0, 0.1);
                padding: 0 15px;
                margin-right: 10px;
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
            }

            .ui-dialog-buttonset button:hover {
                background: #2271b1;
                color: #fff;
            }

            .ui-draggable {
                background-color: #fefefe;
                box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
            }

            div#deactivation-dialog,
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

        </style>

		<?php
	}

	/***
	 * @param $mimes
	 *
	 * @return mixed
	 */
	public static function deactivation_scripts() {
		wp_enqueue_script( 'jquery-ui-dialog' );
		?>
        <script>
            jQuery(document).ready(function ($) {

                // Open the deactivation dialog when the 'Deactivate' link is clicked
                $('.deactivate #deactivate-media-library-tools').on('click', function (e) {
                    e.preventDefault();
                    var href = $('.deactivate #deactivate-media-library-tools').attr('href');
                    var given = localRetrieveData("feedback-given");
                    if( 'given' === given ){
                      // window.location.href = href;
                      // return;
                    }
                    $('#deactivation-dialog').dialog({
                        modal: true,
                        width: 500,
                        buttons: {
                            Submit: function () {
                                submitFeedback();
                            },
                            Cancel: function () {
                                $(this).dialog('close');
                                window.location.href = href;
                            }
                        }
                    });
                    // Customize the button text
                    $('.ui-dialog-buttonpane button:contains("Submit")').text('Submit & Deactivate');
                    $('.ui-dialog-buttonpane button:contains("Cancel")').text('Skip & Deactivate');
                });

                // Submit the feedback
                function submitFeedback() {
                    var href = $('.deactivate #deactivate-media-library-tools').attr('href');
                    var reasons = $('#deactivation-dialog input[type="radio"]:checked').val();
                    var feedback = $('#deactivation-feedback').val();
                    var better_plugin = $('#deactivation-dialog .modal-content input[name="reason_found_a_better_plugin"]').val();
                    // Perform AJAX request to submit feedback
                    if( ! reasons && ! feedback && ! better_plugin ){
                        return;
                    }
                    if( 'temporary_deactivation' == reasons && ! feedback ){
                        window.location.href = href;
                    }
                  
                    $.ajax({
                        url: 'https://www.wptinysolutions.com/wp-json/TinySolutions/pluginSurvey/v1/Survey/appendToSheet',
                        method: 'GET',
                        dataType: 'json',
                        data: {
                            website: '<?php echo esc_url( home_url() )?>',
                            reasons: reasons ? reasons : '',
                            better_plugin: better_plugin,
                            feedback: feedback,
                            wpplugin: 'media-tools',
                        },
                        success: function (response) {
                            if( response.success ){
                                console.log( 'Success');
                                localStoreData( "feedback-given", 'given');
                            }
                        },
                        error: function(xhr, status, error) {
                            // Handle the error response
                            console.error( 'Error', error);
                        },
                        complete: function(xhr, status) {
                            $('#deactivation-dialog').dialog('close');
                            window.location.href = href;
                        }

                    });
                }

                // Store data in local storage with an expiration time of 1 hour
                function localStoreData(key, value) {
                    // Calculate the expiration time in milliseconds (1 hour = 60 minutes * 60 seconds * 1000 milliseconds)
                    var expirationTime = Date.now() + ( 60 * 60 * 1000  );

                    // Create an object to store the data and expiration time
                    var dataObject = {
                        value: value,
                        expirationTime: expirationTime
                    };

                    // Store the object in local storage
                    localStorage.setItem(key, JSON.stringify(dataObject));
                }


                // Retrieve data from local storage
                function localRetrieveData(key) {
                    // Get the stored data from local storage
                    var data = localStorage.getItem(key);
                    if (data) {
                        // Parse the stored JSON data
                        var dataObject = JSON.parse(data);
                        // Check if the data has expired
                        if (Date.now() <= dataObject.expirationTime) {
                            // Return the stored value
                            return dataObject.value;
                        } else {
                            // Data has expired, remove it from local storage
                            localStorage.removeItem(key);
                        }
                    }
                    // Return null if data doesn't exist or has expired
                    return null;
                }

            });

        </script>

		<?php
	}


}
