<?php
/**
 * The read-only image-size tools: tsmlt/list-image-sizes and
 * tsmlt/get-image-size-details (sources, crop, disabled sizes, unknown
 * names, permissions and exposure).
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\Media\GetImageSizeDetails;
use TinySolutions\mlt\Abilities\Media\ListImageSizes;
use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Modules\ImageSize\ImageSizeModule;

/**
 * ImageSizeAbilitiesTest
 */
class ImageSizeAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Administrator; a theme-style size and a positional crop; no disabled sizes.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/list-image-sizes' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		add_image_size( 'tsmlt_test_theme_hero', 1200, 600, true );
		add_image_size( 'tsmlt_test_positioned', 300, 300, [ 'left', 'top' ] );
		add_filter( ImageSizeModule::MANAGED_FILTER, [ $this, 'managed' ] );
		delete_option( 'tsmlt_settings' );
	}

	/**
	 * Remove fixtures.
	 */
	public function tear_down() {
		remove_image_size( 'tsmlt_test_theme_hero' );
		remove_image_size( 'tsmlt_test_positioned' );
		remove_filter( ImageSizeModule::MANAGED_FILTER, [ $this, 'managed' ] );
		delete_option( 'tsmlt_settings' );
		parent::tear_down();
	}

	/**
	 * Mark the positioned size as managed, as Pro marks its custom sizes.
	 *
	 * @param array $names Names.
	 *
	 * @return array
	 */
	public function managed( $names ) {
		$names[] = 'tsmlt_test_positioned';
		return $names;
	}

	/**
	 * Sizes keyed by name from the list tool.
	 *
	 * @return array
	 */
	private static function listed(): array {
		$result = wp_get_ability( 'tsmlt/list-image-sizes' )->execute( [] );
		return array_column( $result['sizes'], null, 'name' );
	}

	/**
	 * Every registered size is listed with its source, crop and state.
	 */
	public function test_list_sources_crop_and_disabled() {
		update_option( 'tsmlt_settings', [ 'deregistered_image_sizes' => [ 'medium_large' ] ] );

		$result = wp_get_ability( 'tsmlt/list-image-sizes' )->execute();
		$sizes  = array_column( $result['sizes'], null, 'name' );

		$this->assertSame( count( wp_get_registered_image_subsizes() ), $result['total'] );
		$this->assertSame( array_keys( wp_get_registered_image_subsizes() ), array_column( $result['sizes'], 'name' ) );
		$this->assertSame( 'core', $sizes['thumbnail']['source'] );
		$this->assertSame( (int) get_option( 'thumbnail_size_w' ), $sizes['thumbnail']['width'] );
		$this->assertTrue( $sizes['medium_large']['disabled'] );
		$this->assertFalse( $sizes['thumbnail']['disabled'] );
		$this->assertSame( 'other', $sizes['tsmlt_test_theme_hero']['source'] );
		$this->assertFalse( $sizes['tsmlt_test_theme_hero']['managed'] );
		$this->assertTrue( $sizes['tsmlt_test_theme_hero']['crop'] );
		$this->assertNull( $sizes['tsmlt_test_theme_hero']['crop_position'] );
		$this->assertSame( 'media_library_tools', $sizes['tsmlt_test_positioned']['source'] );
		$this->assertTrue( $sizes['tsmlt_test_positioned']['managed'] );
		$this->assertSame( [ 'left', 'top' ], $sizes['tsmlt_test_positioned']['crop_position'] );
		$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), wp_get_ability( 'tsmlt/list-image-sizes' )->get_output_schema(), 'result' ) );
	}

	/**
	 * Details by exact name; unknown names and bad input are refused.
	 */
	public function test_details() {
		$details = wp_get_ability( 'tsmlt/get-image-size-details' )->execute( [ 'name' => 'tsmlt_test_theme_hero' ] );

		$this->assertSame( self::listed()['tsmlt_test_theme_hero'], $details );
		$this->assertSame( 1200, $details['width'] );
		$this->assertSame( 600, $details['height'] );
		$this->assertTrue( rest_validate_value_from_schema( $details, wp_get_ability( 'tsmlt/get-image-size-details' )->get_output_schema(), 'result' ) );

		$this->assertSame( GetImageSizeDetails::ERROR_NOT_FOUND, wp_get_ability( 'tsmlt/get-image-size-details' )->execute( [ 'name' => 'no-such-size' ] )->get_error_code() );
		$this->assertSame( 'tsmlt_invalid_input', GetImageSizeDetails::instance()->execute( [ 'name' => '' ] )->get_error_code() );
		$this->assertSame(
			'tsmlt_invalid_input',
			GetImageSizeDetails::instance()->execute(
				[
					'name' => 'thumbnail',
					'x'    => 1,
				]
			)->get_error_code()
		);
		$this->assertSame( 'tsmlt_invalid_input', ListImageSizes::instance()->execute( [ 'x' => 1 ] )->get_error_code() );
	}

	/**
	 * Responses carry no paths.
	 */
	public function test_no_paths() {
		$json = (string) wp_json_encode( wp_get_ability( 'tsmlt/list-image-sizes' )->execute() );
		$this->assertStringNotContainsString( ABSPATH, $json );
		$this->assertStringNotContainsString( wp_upload_dir()['basedir'], $json );
	}

	/**
	 * The manage_options capability is checked in the permission callback and in execute(); listed on
	 * the plugin's servers, not run through the default gateway; read-only.
	 */
	public function test_permissions_and_exposure() {
		$tools = McpServerRegistration::instance()->get_tool_names();
		foreach ( [ 'tsmlt/list-image-sizes', 'tsmlt/get-image-size-details' ] as $name ) {
			$ability = wp_get_ability( $name );
			$this->assertFalse( $ability->get_meta()['mcp']['public'], $name );
			$this->assertTrue( $ability->get_meta()['annotations']['readonly'], $name );
			$this->assertContains( $name, $tools, $name );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		foreach ( [ 'tsmlt/list-image-sizes', 'tsmlt/get-image-size-details' ] as $name ) {
			$this->assertNotTrue( wp_get_ability( $name )->check_permissions( [ 'name' => 'thumbnail' ] ), $name );
		}
		$this->assertWPError( ListImageSizes::instance()->execute() );
		$this->assertWPError( GetImageSizeDetails::instance()->execute( [ 'name' => 'thumbnail' ] ) );
	}

	/**
	 * Without Pro, no size is reported as managed.
	 */
	public function test_nothing_managed_without_a_filter() {
		remove_filter( ImageSizeModule::MANAGED_FILTER, [ $this, 'managed' ] );
		$this->assertSame( [], array_values( array_filter( array_column( self::listed(), 'managed' ) ) ) );
	}
}
