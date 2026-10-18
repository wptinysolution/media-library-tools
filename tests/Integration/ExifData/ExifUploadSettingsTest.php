<?php
/**
 * EXIF-on-upload settings: defaults, saving through the settings endpoint,
 * sanitization, access, and the Pro-only GPS key the free plugin never writes.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Helpers\Fns;

/**
 * ExifUploadSettingsTest
 */
class ExifUploadSettingsTest extends WP_Ajax_UnitTestCase {

	/**
	 * The free EXIF-on-upload keys.
	 *
	 * @var string[]
	 */
	const FREE_KEYS = [ 'auto_process_exif', 'auto_fill_metadata_from_exif', 'auto_set_date_from_exif' ];

	/**
	 * Administrator and empty settings for each test.
	 */
	public function set_up() {
		parent::set_up();
		// _handleAjax() fires admin_init; keep core's update checks off the network.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( 'tsmlt_settings' );
	}

	/**
	 * Save settings through the `tsmlt_update_option` endpoint.
	 *
	 * @param array $params Request params.
	 *
	 * @return array|null Decoded response.
	 */
	private function save( array $params ) {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['nonce']            = wp_create_nonce( Fns::NONCE_ID );
		$_POST['params']           = wp_slash( wp_json_encode( $params ) );
		$this->_last_response      = '';
		try {
			$this->_handleAjax( 'tsmlt_update_option' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}
		return json_decode( $this->_last_response, true );
	}

	/**
	 * Every EXIF-on-upload option, including the Pro GPS key, is off by default.
	 */
	public function test_defaults_are_off() {
		$options = Fns::get_options();

		foreach ( array_merge( self::FREE_KEYS, [ 'auto_remove_gps_on_upload' ] ) as $key ) {
			$this->assertArrayHasKey( $key, $options );
			$this->assertSame( 0, $options[ $key ], $key );
		}
	}

	/**
	 * Saving stores each free key as 1 or 0.
	 *
	 * @dataProvider data_submitted_values
	 *
	 * @param mixed $submitted Submitted value.
	 * @param int   $expected  Stored value.
	 */
	public function test_free_keys_are_saved_as_flags( $submitted, int $expected ) {
		$this->save( array_fill_keys( self::FREE_KEYS, $submitted ) );

		$stored = get_option( 'tsmlt_settings' );
		foreach ( self::FREE_KEYS as $key ) {
			$this->assertSame( $expected, $stored[ $key ], $key );
			$this->assertSame( $expected, Fns::get_options()[ $key ], $key );
		}
	}

	/**
	 * Submitted values and the stored flag.
	 *
	 * @return array
	 */
	public function data_submitted_values(): array {
		return [
			'1'            => [ 1, 1 ],
			'true'         => [ true, 1 ],
			'"1"'          => [ '1', 1 ],
			'0'            => [ 0, 0 ],
			'false'        => [ false, 0 ],
			'empty string' => [ '', 0 ],
			'"0"'          => [ '0', 0 ],
			'null'         => [ null, 0 ],
		];
	}

	/**
	 * Omitted keys are saved as off, so a save never leaves a rule enabled by
	 * accident.
	 */
	public function test_omitted_keys_are_saved_off() {
		update_option( 'tsmlt_settings', array_fill_keys( self::FREE_KEYS, 1 ) );

		$this->save( [ 'media_per_page' => 20 ] );

		foreach ( self::FREE_KEYS as $key ) {
			$this->assertSame( 0, get_option( 'tsmlt_settings' )[ $key ], $key );
		}
	}

	/**
	 * The free plugin never writes the Pro-only GPS key: a submitted value is
	 * ignored and a stored one is kept.
	 */
	public function test_free_save_never_writes_gps_key() {
		$this->assertFalse( tsmlt()->has_pro(), 'Precondition: Pro is not active.' );

		$this->save( [ 'auto_remove_gps_on_upload' => 1 ] );
		$this->assertArrayNotHasKey( 'auto_remove_gps_on_upload', get_option( 'tsmlt_settings' ) );
		$this->assertSame( 0, Fns::get_options()['auto_remove_gps_on_upload'] );

		update_option( 'tsmlt_settings', [ 'auto_remove_gps_on_upload' => 1 ] );
		$this->save( [ 'auto_remove_gps_on_upload' => 0 ] );
		$this->assertSame( 1, get_option( 'tsmlt_settings' )['auto_remove_gps_on_upload'] );
	}

	/**
	 * Users without manage_options cannot change the settings.
	 */
	public function test_non_administrators_cannot_save() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$response = $this->save( array_fill_keys( self::FREE_KEYS, 1 ) );

		$this->assertFalse( $response['success'] ?? null );
		$this->assertFalse( get_option( 'tsmlt_settings' ) );
		foreach ( self::FREE_KEYS as $key ) {
			$this->assertSame( 0, Fns::get_options()[ $key ], $key );
		}
	}
}
