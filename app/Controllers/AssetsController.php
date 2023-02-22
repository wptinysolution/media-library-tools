<?php

namespace TheTinyTools\ME\Controllers;

use TheTinyTools\ME\Traits\SingletonTrait;

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
    public function __construct()
    {
        $this->version = (defined('WP_DEBUG') && WP_DEBUG) ? time() : TTTME_VERSION;
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
        $styles = [
            [
                'handle' => 'ttteme-settings',
                'src' => tttme()->get_assets_uri('css/backend/admin-settings.css'),
            ]
        ];

        // Register public styles.
        foreach ($styles as $style) {
            wp_register_style($style['handle'], $style['src'], '', $this->version);
        }

        $scripts = [
            [
                'handle' => 'ttteme-settings',
                'src' => tttme()->get_assets_uri('js/backend/admin-settings.js'),
                'deps' => [],
                'footer' => true,
            ]
        ];

        // Register public scripts.
        foreach ($scripts as $script) {
            wp_register_script($script['handle'], $script['src'], $script['deps'], $this->version, $script['footer']);
        }
        $current_screen =  get_current_screen() ;
        if ( isset( $current_screen->id ) && 'media_page_tttme-wp-media' === $current_screen->id ){
            wp_enqueue_style('ttteme-settings');
            wp_enqueue_script('ttteme-settings');

            wp_localize_script(
                'ttteme-settings',
                'tttemeParams',
                [
                    'ajaxUrl' => esc_url(admin_url('admin-ajax.php')),
                    'restApiUrl' => esc_url_raw(rest_url()), // site_url(rest_get_url_prefix()),
                    'rest_nonce' => wp_create_nonce( 'wp_rest' ),
                    tttme()->nonceId => wp_create_nonce(tttme()->nonceId),
                ]
            );
        }



    }



}
