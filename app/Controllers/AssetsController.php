<?php

namespace TheTinyTools\ME\Controllers;


use TheTinyTools\ME\Helpers\Fns;
use TheTinyTools\ME\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if (!defined('ABSPATH')) {
    exit('This script cannot be accessed directly.');
}

class AssetsController
{

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
        add_action('admin_enqueue_scripts', [$this, 'register_backend_assets'], 1);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_backend_scripts']);

    }


    /**
     * Registers Admin scripts.
     *
     * @return void
     */
    public function register_backend_assets()
    {
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

    }

    /**
     * Enqueues admin scripts.
     *
     * @param string $hook Hooks.
     * @return void
     */
    public function enqueue_backend_scripts($hook)
    {
        wp_enqueue_style('ttteme-settings');
        wp_enqueue_script('ttteme-settings');

        wp_localize_script(
            'ttteme-settings',
            'tttemeParams',
            [
                'ajaxUrl' => esc_url(admin_url('admin-ajax.php')),
                'restApiUrl' => site_url(rest_get_url_prefix()),
                'current_user' => get_current_user_id(),
                tttme()->nonceId => wp_create_nonce(tttme()->nonceId),
            ]
        );
    }


}
