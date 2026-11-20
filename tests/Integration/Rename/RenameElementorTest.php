<?php
/**
 * Renaming updates image URLs in Elementor data and clears Elementor's
 * caches only for the posts that changed. Defines ELEMENTOR_VERSION, so it
 * runs in its own process.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\Rename\MediaRenamer;

/**
 * RenameElementorTest
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RenameElementorTest extends WP_UnitTestCase {

	/**
	 * Elementor present, administrator.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			define( 'ELEMENTOR_VERSION', '3.25.0' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * A page using the image in escaped JSON is updated and only its caches
	 * are cleared; an unrelated page keeps its data and caches.
	 */
	public function test_elementor_urls_and_targeted_cache() {
		$id        = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$url       = wp_get_attachment_url( $id );
		$json      = wp_json_encode( [ [ 'settings' => [ 'image' => [ 'url' => $url ] ] ] ] );
		$page      = (int) self::factory()->post->create( [ 'post_type' => 'page' ] );
		$unrelated = (int) self::factory()->post->create( [ 'post_type' => 'page' ] );
		update_post_meta( $page, '_elementor_data', wp_slash( $json ) );
		update_post_meta( $unrelated, '_elementor_data', wp_slash( '[{"settings":{"title":"x"}}]' ) );
		foreach ( [ $page, $unrelated ] as $post_id ) {
			update_post_meta( $post_id, '_elementor_css', [ 'status' => 'file' ] );
			update_post_meta( $post_id, '_elementor_element_cache', 'cached' );
		}
		$this->assertStringContainsString( '\\/', $json, 'Precondition: escaped slashes, as Elementor stores them.' );

		$result = MediaRenamer::rename( $id, 'elementor-renamed', get_current_user_id() );

		$this->assertSame( 'renamed', $result['status'], wp_json_encode( $result ) );
		$new_url = wp_get_attachment_url( $id );
		$data    = (string) get_post_meta( $page, '_elementor_data', true );
		$this->assertStringContainsString( str_replace( '/', '\\/', $new_url ), $data );
		$this->assertStringNotContainsString( str_replace( '/', '\\/', $url ), $data );
		$this->assertContains( $page, $result['content']['posts'] );
		$this->assertSame( '', get_post_meta( $page, '_elementor_css', true ), 'The changed page\'s cache was cleared.' );
		$this->assertSame( '', get_post_meta( $page, '_elementor_element_cache', true ) );
		$this->assertSame( [ 'status' => 'file' ], get_post_meta( $unrelated, '_elementor_css', true ), 'Unrelated caches are kept.' );
		$this->assertSame( 'cached', get_post_meta( $unrelated, '_elementor_element_cache', true ) );
		wp_delete_attachment( $id, true );
	}
}
