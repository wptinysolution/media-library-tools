<?php

namespace TinySolutions\mlt\Controllers;

use TinySolutions\mlt\Controllers\Admin\Settings;
use TinySolutions\mlt\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if (!defined('ABSPATH')) {
    exit('This script cannot be accessed directly.');
}

/**
 * AssetsController
 */
class AssetsController
{
    /**
     * Singleton
     */
    use SingletonTrait;

    /**
     * Plugin version
     *
     * @var string
     */
    private $version;

    /**
     * Ajax URL
     *
     * @var string
     */
    private $ajaxurl;

    /**
     * Class Constructor
     */
    public function __construct() {
        $this->version = (defined('WP_DEBUG') && WP_DEBUG) ? time() : TSMLT_VERSION;
        /**
         * Admin scripts.
         */
        add_action('admin_enqueue_scripts', [$this, 'backend_assets'], 1);
    }


    /**
     * Registers Admin scripts.
     *
     * @return void
     */
    public function backend_assets( $hook ) {

        $scripts = [
            [
                'handle' => 'tsmlt-settings',
                'src' => tsmlt()->get_assets_uri('js/backend/admin-settings.js'),
                'deps' => [],
                'footer' => true,
            ]
        ];

        // Register public scripts.
        foreach ($scripts as $script) {
            wp_register_script($script['handle'], $script['src'], $script['deps'], $this->version, $script['footer']);
        }

        $current_screen =  get_current_screen() ;

        if ( isset( $current_screen->id ) && 'media_page_tsmlt-media-tools' === $current_screen->id ){

            wp_enqueue_style('tsmlt-settings');
            wp_enqueue_script('tsmlt-settings');

            wp_localize_script(
                'tsmlt-settings',
                'tsmltParams',
                [
                    'ajaxUrl' => esc_url(admin_url('admin-ajax.php')),
                    'adminUrl' => esc_url(admin_url()),
                    'includesUrl' => esc_url(includes_url()),
                    'settings' => Settings::instance()->get_sections(),
                    'restApiUrl' => esc_url_raw(rest_url()), // site_url(rest_get_url_prefix()),
                    'rest_nonce' => wp_create_nonce( 'wp_rest' ),
                    tsmlt()->nonceId => wp_create_nonce(tsmlt()->nonceId),
                ]
            );

        }

    }



}
