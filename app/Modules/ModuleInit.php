<?php

namespace TinySolutions\mlt\Modules;

use TinySolutions\mlt\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * AssetsController
 */
class ModuleInit {
	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Class Constructor
	 */
	public function __construct() {
		DownloadMedia::instance();
	}
}
