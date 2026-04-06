<?php

namespace TinySolutions\mlt\Modules;

use TinySolutions\mlt\Traits\SingletonTrait;
use TinySolutions\mlt\Modules\Rubbish\RubbishScanner;
use TinySolutions\mlt\Modules\Duplicate\DuplicateScanner;
use TinySolutions\mlt\Modules\Rename\RenameModule;
use TinySolutions\mlt\Modules\ImageSize\ImageSizeModule;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Module initialiser — boots all feature modules.
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
		RubbishScanner::instance();
		DuplicateScanner::instance();
		RenameModule::instance();
		ImageSizeModule::instance();
		UsedWhereScanner::instance();
		RegenerateThumbnails::instance();
	}
}
