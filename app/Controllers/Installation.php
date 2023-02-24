<?php

namespace TheTinyTools\WM\Controllers;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

use TheTinyTools\WM\Traits\SingletonTrait;
use TheTinyTools\WM\Helpers\Fns;

class Installation {
    /**
     * Singleton
     */
    use SingletonTrait;

    private function __construct() {
    }

    public function activate() {
    }

    public function deactivation() {
    }


}