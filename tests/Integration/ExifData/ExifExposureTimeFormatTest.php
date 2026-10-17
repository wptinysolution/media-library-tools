<?php
/**
 * Exposure time formatting in ExifDataReader: fractions such as 1/4000 never
 * get a thousands separator, and other exposure times keep their format.
 *
 * The private format_exposure_time() formats the EXIF ExposureTime value
 * (a rational string from exif_read_data()) for the EXIF viewer and the Pro
 * "EXIF to caption" feature.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\ExifData\ExifDataReader;

/**
 * ExifExposureTimeFormatTest
 */
class ExifExposureTimeFormatTest extends WP_UnitTestCase {

	/**
	 * Format a value with ExifDataReader::format_exposure_time().
	 *
	 * @param mixed $value Exposure time.
	 *
	 * @return string
	 */
	private function format( $value ): string {
		$method = new ReflectionMethod( ExifDataReader::class, 'format_exposure_time' );
		$method->setAccessible( true );
		return $method->invoke( ExifDataReader::instance(), $value );
	}

	/**
	 * Fractions of a second have no thousands separator.
	 *
	 * @dataProvider data_fractions
	 *
	 * @param mixed  $value    Exposure time.
	 * @param string $expected Formatted value.
	 */
	public function test_fractions_have_no_thousands_separator( $value, string $expected ) {
		$formatted = $this->format( $value );

		$this->assertSame( $expected, $formatted );
		$this->assertStringNotContainsString( ',', $formatted );
	}

	/**
	 * Fractions and their expected format.
	 *
	 * @return array
	 */
	public function data_fractions(): array {
		return [
			'1/1000'         => [ '1/1000', '1/1000' ],
			'1/4000'         => [ '1/4000', '1/4000' ],
			'1/8000'         => [ '1/8000', '1/8000' ],
			'1/32000'        => [ '1/32000', '1/32000' ],
			'1/500'          => [ '1/500', '1/500' ],
			'1/250'          => [ '1/250', '1/250' ],
			'1/2'            => [ '1/2', '1/2' ],
			'unreduced'      => [ '10/40000', '1/4000' ],
			'rounded'        => [ '1/3', '1/3' ],
			'float 1/1000'   => [ 0.001, '1/1000' ],
			'numeric string' => [ '0.00025', '1/4000' ],
		];
	}

	/**
	 * Exposures of a second or more keep the "N.NN s" format.
	 *
	 * @dataProvider data_long_exposures
	 *
	 * @param mixed  $value    Exposure time.
	 * @param string $expected Formatted value.
	 */
	public function test_long_exposures_unchanged( $value, string $expected ) {
		$this->assertSame( $expected, $this->format( $value ) );
	}

	/**
	 * Long exposures and their expected format.
	 *
	 * @return array
	 */
	public function data_long_exposures(): array {
		return [
			'1 second'   => [ '1/1', '1.00 s' ],
			'2 seconds'  => [ '2/1', '2.00 s' ],
			'2.5'        => [ '5/2', '2.50 s' ],
			'30 seconds' => [ '30/1', '30.00 s' ],
			'float'      => [ 1.5, '1.50 s' ],
		];
	}

	/**
	 * Values that are not a positive exposure are returned as given.
	 */
	public function test_unusable_values_returned_as_given() {
		$this->assertSame( '0/1', $this->format( '0/1' ) );
		$this->assertSame( '1/0', $this->format( '1/0' ) );
		$this->assertSame( 'abc', $this->format( 'abc' ) );
	}
}
