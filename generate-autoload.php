<?php
/**
 * Generates an optimized classmap autoload.php after PHP-Scoper runs.
 *
 * Scans app/ and vendor_prefixed/ directories, extracts fully-qualified
 * class names, and builds a classmap for O(1) autoloading — equivalent
 * to `composer dumpautoload -o` but scoped-aware.
 *
 * Usage: Called automatically by `composer prefix-vendor`
 *
 * @package TinySolutions\mlt
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- CLI build script, not loaded in WordPress.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI script.
// phpcs:disable WordPress.Security.EscapeOutput -- CLI script.

/**
 * Recursively find all PHP files in a directory.
 *
 * @param string $dir Directory to scan.
 *
 * @return array List of absolute file paths.
 */
function tsmlt_find_php_files( string $dir ): array {
	$files = [];
	if ( ! is_dir( $dir ) ) {
		return $files;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		if ( $file->isFile() && $file->getExtension() === 'php' ) {
			$files[] = $file->getPathname();
		}
	}
	return $files;
}

/**
 * Extract the fully-qualified class/interface/trait/enum name from a PHP file.
 *
 * @param string $file_path Absolute path to PHP file.
 *
 * @return string|null The FQCN or null if none found.
 */
function tsmlt_extract_class_name( string $file_path ): ?string {
	$contents = file_get_contents( $file_path );
	if ( false === $contents ) {
		return null;
	}

	$namespace = '';
	$tokens    = token_get_all( $contents );
	$count     = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) ) {
			continue;
		}

		// Extract namespace.
		if ( T_NAMESPACE === $tokens[ $i ][0] ) {
			$ns_parts = '';
			for ( $j = $i + 1; $j < $count; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], [ T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED ], true ) ) {
					$ns_parts .= $tokens[ $j ][1];
				} elseif ( is_string( $tokens[ $j ] ) && ( ';' === $tokens[ $j ] || '{' === $tokens[ $j ] ) ) {
					break;
				}
			}
			$namespace = trim( $ns_parts );
		}

		// Extract class/interface/trait/enum name.
		if ( in_array( $tokens[ $i ][0], [ T_CLASS, T_INTERFACE, T_TRAIT ], true )
			|| ( defined( 'T_ENUM' ) && T_ENUM === $tokens[ $i ][0] )
		) {
			// Skip anonymous classes.
			for ( $j = $i - 1; $j >= 0; $j-- ) {
				if ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
					continue;
				}
				if ( is_array( $tokens[ $j ] ) && T_NEW === $tokens[ $j ][0] ) {
					continue 2; // Anonymous class, skip.
				}
				break;
			}

			for ( $j = $i + 1; $j < $count; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
					$class_name = $tokens[ $j ][1];
					return $namespace ? $namespace . '\\' . $class_name : $class_name;
				}
			}
		}
	}

	return null;
}

// Discover PSR-4 namespace → directory mappings.
$tsmlt_psr4_map = [
	'TinySolutions\\mlt\\' => __DIR__ . '/app/',
];

$tsmlt_vendor_dir = __DIR__ . '/vendor_prefixed';

foreach ( glob( $tsmlt_vendor_dir . '/*/*/composer.json' ) as $tsmlt_composer_file ) {
	$tsmlt_json = json_decode( file_get_contents( $tsmlt_composer_file ), true );

	if ( empty( $tsmlt_json['autoload']['psr-4'] ) ) {
		continue;
	}

	$tsmlt_package_dir = dirname( $tsmlt_composer_file );

	foreach ( $tsmlt_json['autoload']['psr-4'] as $tsmlt_namespace => $tsmlt_src ) {
		$tsmlt_abs_path                    = rtrim( $tsmlt_package_dir, '/' ) . '/' . rtrim( $tsmlt_src, '/' ) . '/';
		$tsmlt_psr4_map[ $tsmlt_namespace ] = $tsmlt_abs_path;
	}
}

// Build classmap by scanning all directories.
$tsmlt_classmap = [];
foreach ( $tsmlt_psr4_map as $tsmlt_ns => $tsmlt_abs_dir ) {
	$tsmlt_files = tsmlt_find_php_files( $tsmlt_abs_dir );
	foreach ( $tsmlt_files as $tsmlt_file ) {
		$tsmlt_fqcn = tsmlt_extract_class_name( $tsmlt_file );
		if ( $tsmlt_fqcn && 0 === strpos( $tsmlt_fqcn, rtrim( $tsmlt_ns, '\\' ) ) ) {
			// Convert absolute path to relative path from plugin root.
			$tsmlt_rel = str_replace( __DIR__, '', $tsmlt_file );
			$tsmlt_classmap[ $tsmlt_fqcn ] = $tsmlt_rel;
		}
	}
}

// Sort for consistent output.
ksort( $tsmlt_classmap );

// Generate classmap entries.
$tsmlt_entries = '';
foreach ( $tsmlt_classmap as $tsmlt_fqcn => $tsmlt_rel_path ) {
	$tsmlt_escaped = addcslashes( $tsmlt_fqcn, '\\' );
	$tsmlt_entries .= "\t\t'{$tsmlt_escaped}' => __DIR__ . '{$tsmlt_rel_path}',\n";
}

// Generate PSR-4 fallback entries (for classes added after build).
$tsmlt_psr4_entries = '';
foreach ( $tsmlt_psr4_map as $tsmlt_ns => $tsmlt_abs_dir ) {
	$tsmlt_escaped_ns   = addcslashes( $tsmlt_ns, '\\' );
	$tsmlt_rel_dir      = str_replace( __DIR__, '', $tsmlt_abs_dir );
	$tsmlt_psr4_entries .= "\t\t\t'{$tsmlt_escaped_ns}' => __DIR__ . '{$tsmlt_rel_dir}',\n";
}

$tsmlt_content = <<<'HEADER'
<?php
/**
 * Optimized classmap autoloader for scoped vendor packages.
 *
 * Auto-generated by generate-autoload.php — do not edit manually.
 *
 * @package TinySolutions\mlt
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

spl_autoload_register(
	function ( $class ) {
		// Optimized classmap — O(1) lookup.
		static $classmap = [

HEADER;

$tsmlt_content .= $tsmlt_entries;

$tsmlt_content .= <<<'MIDDLE'
		];

		if ( isset( $classmap[ $class ] ) ) {
			require $classmap[ $class ];
			return;
		}

		// PSR-4 fallback for unlisted classes.
		static $psr4 = [

MIDDLE;

$tsmlt_content .= $tsmlt_psr4_entries;

$tsmlt_content .= <<<'FOOTER'
		];

		foreach ( $psr4 as $prefix => $base_dir ) {
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

FOOTER;

file_put_contents( __DIR__ . '/autoload.php', $tsmlt_content );

echo 'Optimized autoload.php generated with ' . count( $tsmlt_classmap ) . " classmap entries and " . count( $tsmlt_psr4_map ) . " PSR-4 fallbacks.\n";
