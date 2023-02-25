<?php
/**
 * Main ActionHooks class.
 *
 * @package TheTinyTools\WM
 */

namespace TheTinyTools\WM\Controllers\Hooks;

use TheTinyTools\WM\Helpers\Fns;

defined( 'ABSPATH' ) || exit();

/**
 * Main ActionHooks class.
 */
class ActionHooks {
	/**
	 * Init Hooks.
	 *
	 * @return void
	 */
	public static function init_hooks() {
        add_action( 'manage_media_custom_column', [ __CLASS__, 'display_column_value' ], 10, 2 );
	}

    /**
     * @param $column
     * @param $post_id
     * @return void
     */
    public static function display_column_value( $column, $post_id ) {
        $image = Fns::wp_get_attachment( $post_id );
        switch ( $column ) {
            case 'alt':
                echo esc_html( wp_strip_all_tags( $image['alt'] ) );
                break;
            case 'caption':
                echo esc_html( $image['caption'] );
                break;
            case 'description':
                echo esc_html( $image['description'] );
                break;
            default:
                break;
        }
    }


}
