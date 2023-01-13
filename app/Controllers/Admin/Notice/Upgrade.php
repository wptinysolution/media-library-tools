<?php
/**
 * Main Upgrade class.
 *
 * @package TheTinyTools\ME
 */

namespace TheTinyTools\ME\Controllers\Admin\Notice;

use TheTinyTools\ME\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Main Upgrade class.
 */
class Upgrade {
	/**
	 * Singleton Trait.
	 */
	use SingletonTrait;

	/**
	 * Class Constructor.
	 */
	private function __construct() {
		add_action(
			'in_plugin_update_message-' . RTSB_ACTIVE_FILE_NAME,
			function ( $plugin_data ) {
				$this->version_update_warning( RTSB_VERSION, $plugin_data['new_version'] );
			}
		);
	}

	/**
	 * Update message
	 *
	 * @param int $current_version Current Version.
	 * @param int $new_version New Version.
	 *
	 * @return void
	 */
	public function version_update_warning( $current_version, $new_version ) {
		$current_version_major = explode( '.', $current_version )[1];
		$new_version_major     = explode( '.', $new_version )[1];

		if ( $current_version_major === $new_version_major ) {
			return;
		}
		?>
		<style>
			.rtsb-major-update-warning {
				border-top: 2px solid #d63638;
				padding-top: 15px;
				margin-top: 15px;
				margin-bottom: 15px;
				display: flex;
			}

			.rtsb-major-update-icon i {
				color: #d63638;
				margin-right: 8px;
			}

			.rtsb-major-update-warning + p {
				display: none;
			}

			.rtsb-major-update-title {
				font-weight: 600;
				margin-bottom: 10px;
			}

			.notice-success .rtsb-major-update-warning {
				border-color: #46b450;
			}

			.notice-success .rtsb-major-update-icon i {
				color: #79ba49;
			}
		</style>
		<div class="rtsb-major-update-warning">
			<div class="rtsb-major-update-icon">
				<i class="dashicons dashicons-info"></i>
			</div>
			<div>
				<div class="rtsb-major-update-title">
					<?php
					printf(
						'%s%s.',
						esc_html__( 'Heads up, Please backup before upgrade to version ', 'shopbuilder' ),
						esc_html( $new_version )
					);
					?>
				</div>
				<div class="rtsb-major-update-message">
					The latest update includes some substantial changes across different areas of the plugin. <br/>We
					highly recommend you to <b>backup your site before upgrading</b>, and make sure you first update in
					a staging environment.
				</div>
			</div>
		</div>
		<?php
	}
}
