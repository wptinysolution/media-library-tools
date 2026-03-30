<?php
/**
 * PHP-Scoper configuration.
 *
 * Prefixes vendor namespaces to avoid conflicts with other WordPress plugins
 * that may bundle the same dependencies.
 *
 * Usage: composer prefix-vendor
 *
 * @package TinySolutions\mlt
 */

declare( strict_types=1 );

use Isolated\Symfony\Component\Finder\Finder;

return [
	'prefix' => 'TinySolutions\\mlt\\Vendor',

	'finders' => [
		// Scope only the two vendor packages we ship.
		Finder::create()
			->files()
			->ignoreVCS( true )
			->in( 'vendor/enshrined' ),
		Finder::create()
			->files()
			->ignoreVCS( true )
			->in( 'vendor/codesvault' ),
	],

	'exclude-namespaces' => [
		// Do not prefix the plugin's own namespace.
		'TinySolutions\mlt',
		'TinySolutions\mlt\Vendor',
	],

	'exclude-classes' => [
		// WordPress global classes used by vendor packages.
		'wpdb',
		'WP_Error',
		'WP_Post',
		'WP_Query',
		'WP_User',
	],

	'exclude-constants' => [
		// WordPress constants.
		'ABSPATH',
		'WPINC',
		'WP_CONTENT_DIR',
		'WP_PLUGIN_DIR',
		'ARRAY_A',
		'ARRAY_N',
		'OBJECT',
	],

	'exclude-functions' => [
		// WordPress global functions used by vendor packages.
		'wp_cache_get',
		'wp_cache_set',
		'wp_cache_delete',
		'esc_sql',
		'absint',
	],

	'expose-global-constants' => true,
	'expose-global-classes'   => false,
	'expose-global-functions' => true,
];
