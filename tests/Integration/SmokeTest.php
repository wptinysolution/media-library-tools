<?php
/**
 * Smoke test: the plugin loads inside the WordPress test suite.
 *
 * @package TinySolutions\mlt
 */

/**
 * SmokeTest
 */
class SmokeTest extends WP_UnitTestCase {

	/**
	 * The plugin bootstrapped and initialised.
	 */
	public function test_plugin_is_loaded() {
		$this->assertTrue( defined( 'TSMLT_VERSION' ) );
		$this->assertTrue( function_exists( 'tsmlt' ) );
		$this->assertInstanceOf( \TinySolutions\mlt\Tsmlt::class, tsmlt() );
		$this->assertGreaterThan( 0, did_action( 'tsmlt/after_loaded' ) );
	}

	/**
	 * The AJAX endpoint used by the media table is registered.
	 */
	public function test_get_media_ajax_action_is_registered() {
		$this->assertNotFalse( has_action( 'wp_ajax_tsmlt_get_media' ) );
	}
}
