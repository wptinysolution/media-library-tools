<?php
/**
 * Tests for AbilityGuard.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilityGuard;

/**
 * AbilityGuardTest
 */
class AbilityGuardTest extends WP_UnitTestCase {

	/**
	 * Set up an administrator for each test.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Base capability: logged out, without manage_options, and administrator.
	 */
	public function test_check_base_capability() {
		$this->assertTrue( AbilityGuard::check_base_capability() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$forbidden = AbilityGuard::check_base_capability();
		$this->assertWPError( $forbidden );
		$this->assertSame( AbilityGuard::ERROR_FORBIDDEN, $forbidden->get_error_code() );
		$this->assertSame( 403, $forbidden->get_error_data()['status'] );

		wp_set_current_user( 0 );
		$anonymous = AbilityGuard::check_base_capability();
		$this->assertWPError( $anonymous );
		$this->assertSame( AbilityGuard::ERROR_UNAUTHENTICATED, $anonymous->get_error_code() );
		$this->assertSame( 401, $anonymous->get_error_data()['status'] );
	}

	/**
	 * Malformed IDs are rejected before any lookup.
	 *
	 * @dataProvider data_invalid_ids
	 *
	 * @param mixed $id Attachment ID.
	 */
	public function test_invalid_ids( $id ) {
		$result = AbilityGuard::resolve_attachment( $id );

		$this->assertWPError( $result );
		$this->assertSame( AbilityGuard::ERROR_INVALID_ATTACHMENT_ID, $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * @return array
	 */
	public function data_invalid_ids(): array {
		return [
			'zero'           => [ 0 ],
			'negative'       => [ -5 ],
			'float'          => [ 1.5 ],
			'numeric prefix' => [ '12abc' ],
			'non numeric'    => [ 'abc' ],
			'empty string'   => [ '' ],
			'null'           => [ null ],
			'array'          => [ [ 1 ] ],
			'leading zero'   => [ '012' ],
		];
	}

	/**
	 * IDs that do not belong to an attachment are reported as not found.
	 */
	public function test_missing_and_non_attachment_ids() {
		$post_id = self::factory()->post->create();

		foreach ( [ $post_id, 999999 ] as $id ) {
			$result = AbilityGuard::resolve_attachment( $id );
			$this->assertWPError( $result );
			$this->assertSame( AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND, $result->get_error_code() );
			$this->assertSame( 404, $result->get_error_data()['status'] );
		}
	}

	/**
	 * A valid attachment resolves, as an int or a numeric string.
	 */
	public function test_valid_attachment() {
		$id = self::factory()->attachment->create( [ 'post_mime_type' => 'image/jpeg' ] );

		$this->assertSame( $id, AbilityGuard::resolve_attachment( $id )->ID );
		$this->assertSame( $id, AbilityGuard::resolve_attachment( (string) $id )->ID );
	}

	/**
	 * The per-attachment capability is enforced.
	 */
	public function test_attachment_capability_is_enforced() {
		$id   = self::factory()->attachment->create( [ 'post_mime_type' => 'image/jpeg' ] );
		$deny = static function ( $caps, $cap, $user_id, $args ) use ( $id ) {
			if ( 'read_post' === $cap && isset( $args[0] ) && (int) $args[0] === $id ) {
				return [ 'do_not_allow' ];
			}
			return $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 4 );
		$result = AbilityGuard::resolve_attachment( $id, 'read_post' );
		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assertWPError( $result );
		$this->assertSame( AbilityGuard::ERROR_FORBIDDEN, $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}
}
