<?php
/**
 * Stand-in for the Pro plugin's licence object, for compression tests.
 *
 * @package TinySolutions\mlt
 */

/**
 * Stand-in for the object tsmltpro() returns, with a switchable licence.
 */
class TsmltProCompressionStub {

	/**
	 * Whether the licence is valid.
	 *
	 * @var bool
	 */
	public static $licensed = false;

	/**
	 * Mirrors the Pro plugin's licence check used by Tsmlt::has_pro().
	 *
	 * @return bool
	 */
	public function user_can_use_tsmltpro() {
		return self::$licensed;
	}
}
