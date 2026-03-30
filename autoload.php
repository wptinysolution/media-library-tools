<?php
/**
 * Standalone PSR-4 autoloader for prefixed vendor packages.
 *
 * Replaces vendor/autoload.php in production so the vendor/ directory
 * can be excluded from the release zip.
 *
 * @package TinySolutions\mlt
 */

spl_autoload_register(
	function ( $class ) {
		$map = [
			'TinySolutions\\mlt\\'                                => __DIR__ . '/app/',
			'TinySolutions\\mlt\\Vendor\\CodesVault\\Howdyqb\\'   => __DIR__ . '/vendor_prefixed/codesvault/howdy-qb/src/',
			'TinySolutions\\mlt\\Vendor\\enshrined\\svgSanitize\\' => __DIR__ . '/vendor_prefixed/enshrined/svg-sanitize/src/',
		];

		foreach ( $map as $prefix => $base_dir ) {
			$len = strlen( $prefix );
			if ( strncmp( $prefix, $class, $len ) !== 0 ) {
				continue;
			}
			$relative_class = substr( $class, $len );
			$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
			if ( file_exists( $file ) ) {
				require $file;
				return;
			}
		}
	}
);
