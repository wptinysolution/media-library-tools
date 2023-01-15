<?php

namespace TheTinyTools\ME\Controllers;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

use TheTinyTools\ME\Traits\SingletonTrait;
use TheTinyTools\ME\Helpers\Fns;

class Installation {

    use SingletonTrait;

    private function __construct() {
    }

    public function activate() {
    }

    public function deactivation() {
    }


}