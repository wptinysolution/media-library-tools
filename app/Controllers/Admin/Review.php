<?php

namespace TheTinyTools\WM\Controllers\Admin;

use TheTinyTools\WM\Traits\SingletonTrait;

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
        add_action( 'admin_init', [ __CLASS__, 'tttwm_check_installation_time' ] );
        add_action( 'admin_init', [ __CLASS__, 'tttwm_spare_me' ], 5 );
	}

	/**
     * Check if review notice should be shown or not
     *
     * @return void
     */
	public static function tttwm_check_installation_time() {
        
		// Added Lines Start
		$nobug = get_option( 'tttwm_spare_me' );

        $rated = get_option( 'tttwm_rated' );

		if ( '1' == $nobug || 'yes' == $rated ) {
			return;
		}

		$now         = strtotime( 'now' );

		$install_date = get_option( 'tttwm_plugin_activation_time' );

        $past_date    = strtotime( '+10 days', $install_date );

		$remind_time = get_option( 'tttwm_remind_me' );

        $remind_due  = strtotime( '+350 seconds', $remind_time );

        if ( ! $now >= $past_date || $now <= $remind_due ) {
             return;
        }

        add_action( 'admin_notices', [ __CLASS__, 'tttwm_display_admin_notice' ] );

	}
    /**
     * Remove the notice for the user if review already done or if the user does not want to
     *
     * @return void
     */
	public static function tttwm_spare_me() {

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'tttwm_notice_nonce' ) ) {
			return;
		}

		if ( isset( $_GET['tttwm_spare_me'] ) && ! empty( $_GET['tttwm_spare_me'] ) ) {
			$spare_me = $_GET['tttwm_spare_me'];
			if ( 1 == $spare_me ) {
				update_option( 'tttwm_spare_me', '1' );
			}
		}

		if ( isset( $_GET['tttwm_remind_me'] ) && ! empty( $_GET['tttwm_remind_me'] ) ) {
			$remind_me = $_GET['tttwm_remind_me'];
			if ( 1 == $remind_me ) {
				$get_activation_time = strtotime( 'now' );
				update_option( 'tttwm_remind_me', $get_activation_time );
			}
		}

		if ( isset( $_GET['tttwm_rated'] ) && ! empty( $_GET['tttwm_rated'] ) ) {
			$tttwm_rated = $_GET['tttwm_rated'];
			if ( 1 == $tttwm_rated ) {
				update_option( 'tttwm_rated', 'yes' );
			}
		}
	}

	protected static function tttwm_current_admin_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$uri = preg_replace( '|^.*/wp-admin/|i', '', $uri );

		if ( ! $uri ) {
			return '';
		}
		return remove_query_arg( [ '_wpnonce', '_wc_notice_nonce', 'wc_db_update', 'wc_db_update_nonce', 'wc-hide-notice', 'tttwm_spare_me', 'tttwm_remind_me', 'tttwm_rated' ], admin_url( $uri ) );
	}


	/**
	 * Display Admin Notice, asking for a review
	 **/
	public static function tttwm_display_admin_notice() {
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

			$args = [ '_wpnonce' => wp_create_nonce( 'tttwm_notice_nonce' ) ];

			$dont_disturb = add_query_arg( $args + [ 'tttwm_spare_me' => '1' ], self::tttwm_current_admin_url() );
			$remind_me    = add_query_arg( $args + [ 'tttwm_remind_me' => '1' ], self::tttwm_current_admin_url() );
			$rated        = add_query_arg( $args + [ 'tttwm_rated' => '1' ], self::tttwm_current_admin_url() );
			$reviewurl    = 'https://wordpress.org/support/plugin/ttt-wp-media/reviews/?filter=5#new-post';
			?>
			<div class="notice tttwm-review-notice tttwm-review-notice--extended">
				<div class="tttwm-review-notice_content">
					<h3>Enjoying "WP Media Tools"? </h3>
                    <p>Thank you for choosing "<strong>WP Media Tools</strong>". If you have found our plugin useful and makes you smile, please consider giving us a 5-star rating on WordPress.org. It will help us to grow.</p>
					<div class="tttwm-review-notice_actions">
						<a href="<?php echo esc_url( $reviewurl ); ?>" class="tttwm-review-button tttwm-review-button--cta" target="_blank"><span>⭐ Yes, You Deserve It!</span></a>
						<a href="<?php echo esc_url( $rated ); ?>" class="tttwm-review-button tttwm-review-button--cta tttwm-review-button--outline"><span>😀 Already Rated!</span></a>
						<a href="<?php echo esc_url( $remind_me ); ?>" class="tttwm-review-button tttwm-review-button--cta tttwm-review-button--outline"><span>🔔 Remind Me Later</span></a>
						<a href="<?php echo esc_url( $dont_disturb ); ?>" class="tttwm-review-button tttwm-review-button--cta tttwm-review-button--error tttwm-review-button--outline"><span>😐 No Thanks </span></a>
					</div>
				</div> 
			</div>
			<style> 
			.tttwm-review-button--cta {
				--e-button-context-color: #5d3dfd;
				--e-button-context-color-dark: #5d3dfd;
				--e-button-context-tint: rgb(75 47 157/4%);
				--e-focus-color: rgb(75 47 157/40%);
			} 
			.tttwm-review-notice {
				position: relative;
				margin: 5px 20px 5px 2px;
				border: 1px solid #ccd0d4;
				background: #fff;
				box-shadow: 0 1px 4px rgba(0,0,0,0.15);
				font-family: Roboto, Arial, Helvetica, Verdana, sans-serif;
				border-inline-start-width: 4px;
			}
			.tttwm-review-notice.notice {
				padding: 0;
			}
			.tttwm-review-notice:before {
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
			.tttwm-review-notice_content {
				padding: 20px;
			} 
			.tttwm-review-notice_actions > * + * {
				margin-inline-start: 8px;
				-webkit-margin-start: 8px;
				-moz-margin-start: 8px;
			} 
			.tttwm-review-notice p {
				margin: 0;
				padding: 0;
				line-height: 1.5;
			}
			p + .tttwm-review-notice_actions {
				margin-top: 1rem;
			}
			.tttwm-review-notice h3 {
				margin: 0;
				font-size: 1.0625rem;
				line-height: 1.2;
			}
			.tttwm-review-notice h3 + p {
				margin-top: 8px;
			} 
			.tttwm-review-button {
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
			.tttwm-review-button:active {
				background: var(--e-button-context-color-dark);
				color: #fff;
				text-decoration: none;
			}
			.tttwm-review-button:focus {
				outline: 0;
				background: var(--e-button-context-color-dark);
				box-shadow: 0 0 0 2px var(--e-focus-color);
				color: #fff;
				text-decoration: none;
			}
			.tttwm-review-button:hover {
				background: var(--e-button-context-color-dark);
				color: #fff;
				text-decoration: none;
			} 
			.tttwm-review-button.focus {
				outline: 0;
				box-shadow: 0 0 0 2px var(--e-focus-color);
			} 
			.tttwm-review-button--error {
				--e-button-context-color: #d72b3f;
				--e-button-context-color-dark: #ae2131;
				--e-button-context-tint: rgba(215,43,63,0.04);
				--e-focus-color: rgba(215,43,63,0.4);
			}
			.tttwm-review-button.tttwm-review-button--outline {
				border: 1px solid;
				background: 0 0;
				color: var(--e-button-context-color);
			}
			.tttwm-review-button.tttwm-review-button--outline:focus {
				background: var(--e-button-context-tint);
				color: var(--e-button-context-color-dark);
			}
			.tttwm-review-button.tttwm-review-button--outline:hover {
				background: var(--e-button-context-tint);
				color: var(--e-button-context-color-dark);
			} 
			</style>
			<?php
		}
	}




}
