<?php
/**
 * Regression tests for Tsmlt::has_pro() (audit finding C-1).
 *
 * has_pro() is true only when the Pro accessor `tsmltpro()` exists,
 * `TSMLTPRO_VERSION` is at least 1.2.3, and Pro's own licence check passes.
 * It used to OR that licence result with a debug override keyed on the
 * constant `TINY_DEBUG_TSMLT_PRO_1_2_3`; the override has been removed, and
 * these tests prove the constant no longer affects entitlement.
 *
 * Pro is represented by a synthetic `tsmltpro()` and `TSMLTPRO_VERSION`; the
 * real Pro plugin is never loaded, and no real licence or credential is used.
 * Functions and constants cannot be undone, so every test runs in its own
 * process.
 *
 * @package TinySolutions\mlt
 */

/**
 * Synthetic stand-in for the object the Pro accessor returns.
 */
class HasProLicenceProbe {

	/**
	 * Whether the synthetic licence is valid.
	 *
	 * @var bool
	 */
	public static $licensed = false;

	/**
	 * Number of licence checks.
	 *
	 * @var int
	 */
	public static $checks = 0;

	/**
	 * Mirrors the Pro licence check has_pro() calls.
	 *
	 * @return bool
	 */
	public function user_can_use_tsmltpro() {
		++self::$checks;
		return self::$licensed;
	}
}

/**
 * HasProEntitlementTest
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class HasProEntitlementTest extends WP_UnitTestCase {

	/**
	 * Synthetic value for the removed debug constant. Not a real value.
	 *
	 * @var string
	 */
	const SYNTHETIC_DEBUG_VALUE = 'synthetic-debug-value-for-tests';

	/**
	 * Define the synthetic Pro accessor and version.
	 *
	 * Declared inside a method so that loading this file in the parent
	 * PHPUnit process never defines tsmltpro().
	 *
	 * @param string $version  Pro version constant.
	 * @param bool   $licensed Synthetic licence state.
	 */
	private static function install_pro( string $version, bool $licensed ) {
		define( 'TSMLTPRO_VERSION', $version );
		HasProLicenceProbe::$licensed = $licensed;
		HasProLicenceProbe::$checks   = 0;
		if ( ! function_exists( 'tsmltpro' ) ) {
			/**
			 * Synthetic Pro accessor.
			 *
			 * @return HasProLicenceProbe
			 */
			function tsmltpro() {
				return new HasProLicenceProbe();
			}
		}
	}

	/**
	 * Pro absent → not licensed, with or without the debug constant.
	 *
	 * @dataProvider data_debug_constant
	 *
	 * @param bool $define_debug Whether to define the old debug constant.
	 */
	public function test_pro_absent_is_not_pro( bool $define_debug ) {
		if ( $define_debug ) {
			define( 'TINY_DEBUG_TSMLT_PRO_1_2_3', self::SYNTHETIC_DEBUG_VALUE );
		}
		$this->assertFalse( function_exists( 'tsmltpro' ) );

		$this->assertFalse( tsmlt()->has_pro() );
	}

	/**
	 * @return array
	 */
	public function data_debug_constant(): array {
		return [
			'no debug constant'   => [ false ],
			'debug constant set'  => [ true ],
		];
	}

	/**
	 * Version and licence matrix: below the minimum is never licensed (and
	 * the licence is not consulted); at or above it, the result is exactly
	 * Pro's licence check — with or without the old debug constant.
	 *
	 * @dataProvider data_version_licence_and_debug
	 *
	 * @param string $version         Pro version.
	 * @param bool   $licensed        Licence state.
	 * @param bool   $define_debug    Whether to define the old debug constant.
	 * @param bool   $expected        Expected has_pro().
	 * @param int    $expected_checks Expected licence checks.
	 */
	public function test_version_and_licence( string $version, bool $licensed, bool $define_debug, bool $expected, int $expected_checks ) {
		self::install_pro( $version, $licensed );
		if ( $define_debug ) {
			define( 'TINY_DEBUG_TSMLT_PRO_1_2_3', self::SYNTHETIC_DEBUG_VALUE );
		}

		$this->assertSame( $expected, tsmlt()->has_pro() );
		$this->assertSame( $expected_checks, HasProLicenceProbe::$checks );
	}

	/**
	 * @return array
	 */
	public function data_version_licence_and_debug(): array {
		return [
			'1.3.3 licensed'                         => [ '1.3.3', true, false, true, 1 ],
			'1.3.3 unlicensed'                       => [ '1.3.3', false, false, false, 1 ],
			'1.2.3 (boundary) licensed'              => [ '1.2.3', true, false, true, 1 ],
			'1.2.3 (boundary) unlicensed'            => [ '1.2.3', false, false, false, 1 ],
			'1.2.2 (below) licensed, not asked'      => [ '1.2.2', true, false, false, 0 ],
			'1.2.3-beta (below) licensed'            => [ '1.2.3-beta', true, false, false, 0 ],
			'1.3.3 unlicensed + debug constant'      => [ '1.3.3', false, true, false, 1 ],
			'1.2.3 unlicensed + debug constant'      => [ '1.2.3', false, true, false, 1 ],
			'1.3.3 licensed + debug constant'        => [ '1.3.3', true, true, true, 1 ],
			'1.2.2 licensed + debug constant'        => [ '1.2.2', true, true, false, 0 ],
		];
	}

	/**
	 * has_pro() no longer reads the debug constant or compares a hash, so no
	 * value of the constant can grant entitlement. Guards against the
	 * override being reintroduced.
	 */
	public function test_has_pro_has_no_debug_override() {
		$method = new ReflectionMethod( tsmlt(), 'has_pro' );
		$lines  = file( $method->getFileName() );
		$body   = implode( '', array_slice( $lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 ) );

		$this->assertStringNotContainsString( 'TINY_DEBUG', $body );
		$this->assertStringNotContainsString( 'md5', $body );
		$this->assertDoesNotMatchRegularExpression( '/[0-9a-f]{32}/', $body, 'No hash literal may decide entitlement.' );
		$this->assertStringContainsString( 'user_can_use_tsmltpro', $body );
	}
}
