<?php
/**
 * Tests for the `tsmlt/get-exif-fields` ability.
 *
 * Executed through WP_Ability::execute(), the path the MCP Adapter uses.
 * Images are real files: an EXIF-free JPEG and PNG from the WordPress test
 * data, and the JPEG with known camera, exposure, copyright, artist, serial
 * number and GPS EXIF added by TsmltExifJpegFixture. Stored fields mirror what
 * the EXIF editor saves in `_tsmlt_exif_meta`.
 *
 * @package TinySolutions\mlt
 */

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\GetExifFields;

/**
 * GetExifFieldsAbilityTest
 */
class GetExifFieldsAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/get-exif-fields';

	/**
	 * Values in the images or stored fields that must never be returned.
	 */
	const SERIAL    = 'SERIAL-MARKER-7731';
	const COPYRIGHT = 'COPYRIGHT-MARKER-5521';
	const ARTIST    = 'ARTIST-MARKER-9043';
	const GPS_LAT   = 23.8103917;
	const GPS_LNG   = 90.4125181;

	/**
	 * Every field, empty.
	 *
	 * @var array
	 */
	const NO_FIELDS = [
		'make'               => null,
		'model'              => null,
		'date_time_original' => null,
		'iso'                => null,
		'aperture'           => null,
		'shutter_speed'      => null,
	];

	/**
	 * Temporary files created by a test.
	 *
	 * @var string[]
	 */
	private $temp_files = [];

	/**
	 * Administrator for each test.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Remove temporary files.
	 */
	public function tear_down() {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
		$this->temp_files = [];
		parent::tear_down();
	}

	/**
	 * Upload raw bytes as an attachment.
	 *
	 * @param string $bytes File contents.
	 * @param string $name  File name.
	 *
	 * @return int Attachment ID.
	 */
	private function upload_bytes( string $bytes, string $name ): int {
		$temp = trailingslashit( get_temp_dir() ) . wp_unique_filename( get_temp_dir(), $name );
		file_put_contents( $temp, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $temp;

		$id                 = self::factory()->attachment->create_upload_object( $temp );
		$this->temp_files[] = get_attached_file( $id );
		return $id;
	}

	/**
	 * Upload a copy of a WordPress test-data image.
	 *
	 * @param string $file File name under DIR_TESTDATA/images.
	 *
	 * @return int Attachment ID.
	 */
	private function upload_test_image( string $file ): int {
		return $this->upload_bytes( (string) file_get_contents( DIR_TESTDATA . '/images/' . $file ), $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
	}

	/**
	 * Upload a JPEG with known camera, exposure, date, copyright, artist,
	 * serial number and GPS EXIF.
	 *
	 * @return int Attachment ID.
	 */
	private function upload_exif_jpeg(): int {
		$bytes = TsmltExifJpegFixture::build(
			DIR_TESTDATA . '/images/test-square-150.jpg',
			[
				[ 0x010F, TsmltExifJpegFixture::ASCII, 'TestCam' ],
				[ 0x0110, TsmltExifJpegFixture::ASCII, 'Model X1' ],
				[ 0x013B, TsmltExifJpegFixture::ASCII, self::ARTIST ],
				[ 0x8298, TsmltExifJpegFixture::ASCII, self::COPYRIGHT ],
			],
			[
				[ 0x829A, TsmltExifJpegFixture::RATIONAL, [ [ 1, 250 ] ] ],
				[ 0x829D, TsmltExifJpegFixture::RATIONAL, [ [ 18, 10 ] ] ],
				[ 0x8827, TsmltExifJpegFixture::SHORT, 400 ],
				[ 0x9003, TsmltExifJpegFixture::ASCII, '2026:03:14 09:26:53' ],
				[ 0xA431, TsmltExifJpegFixture::ASCII, self::SERIAL ],
			],
			[
				[ 0x0001, TsmltExifJpegFixture::ASCII, 'N' ],
				[ 0x0002, TsmltExifJpegFixture::RATIONAL, [ [ 51, 1 ], [ 30, 1 ], [ 1234, 100 ] ] ],
				[ 0x0003, TsmltExifJpegFixture::ASCII, 'W' ],
				[ 0x0004, TsmltExifJpegFixture::RATIONAL, [ [ 0, 1 ], [ 7, 1 ], [ 3956, 100 ] ] ],
			]
		);
		return $this->upload_bytes( $bytes, 'exif-fixture.jpg' );
	}

	/**
	 * The stored fields of a PNG edited in the EXIF editor, including the
	 * fields that must never be returned.
	 *
	 * @return array
	 */
	private static function stored_fields(): array {
		return [
			'make'               => 'Canon',
			'model'              => 'EOD R5',
			'date_time_original' => '2026:09:01 18:30:00',
			'iso'                => 100,
			'aperture'           => 1.8,
			'shutter_speed'      => '1/250',
			'gps_lat'            => self::GPS_LAT,
			'gps_lng'            => self::GPS_LNG,
			'copyright'          => self::COPYRIGHT,
			'artist'             => self::ARTIST,
		];
	}

	/**
	 * Execute the ability through the Abilities API.
	 *
	 * @param mixed $input Input.
	 *
	 * @return mixed
	 */
	private function run_ability( $input ) {
		$ability = wp_get_ability( self::NAME );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		return $ability->execute( $input );
	}

	/**
	 * Assert a successful result that matches the output schema.
	 *
	 * @param mixed $result Result.
	 */
	private function assert_valid_output( $result ) {
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		$this->assertSame( [ 'id', 'supported', 'source', 'fields' ], array_keys( $result ) );
		$this->assertSame( array_keys( self::NO_FIELDS ), array_keys( $result['fields'] ) );
		$this->assertTrue( rest_validate_value_from_schema( $result, wp_get_ability( self::NAME )->get_output_schema(), 'output' ) );
	}

	/**
	 * Everything about an attachment the ability must never change.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array
	 */
	private function snapshot( int $id ): array {
		clean_post_cache( $id );
		wp_cache_delete( $id, 'post_meta' );
		$post = get_post( $id );
		$meta = get_post_meta( $id );
		unset( $meta['_edit_lock'], $meta['_edit_last'] );
		ksort( $meta );
		$file = get_attached_file( $id );
		return [
			'post' => [ $post->post_title, $post->post_excerpt, $post->post_content, $post->post_name, $post->post_status, $post->post_modified_gmt ],
			'meta' => $meta,
			'file' => [ $file, sha1_file( $file ), filemtime( $file ) ],
		];
	}

	// ---------------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------------

	/**
	 * Registered in the plugin's category, read-only, and not public on MCP.
	 */
	public function test_registration_and_meta() {
		$ability = wp_get_ability( self::NAME );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( AbilitiesInit::CATEGORY, $ability->get_category() );
		$this->assertContains( self::NAME, AbilitiesInit::instance()->get_ability_names() );

		$meta = $ability->get_meta();
		$this->assertFalse( $meta['show_in_rest'] );
		$this->assertSame(
			[
				'public' => false,
				'type'   => 'tool',
			],
			$meta['mcp']
		);
		$this->assertTrue( $meta['annotations']['readonly'] );
		$this->assertFalse( $meta['annotations']['destructive'] );
		$this->assertTrue( $meta['annotations']['idempotent'] );

		$input = $ability->get_input_schema();
		$this->assertFalse( $input['additionalProperties'] );
		$this->assertSame( [ 'id' ], $input['required'] );
		$this->assertSame( [ 'id' ], array_keys( $input['properties'] ) );
		$this->assertSame( 1, $input['properties']['id']['minimum'] );

		$output = $ability->get_output_schema();
		$this->assertFalse( $output['additionalProperties'] );
		$this->assertSame( [ 'id', 'supported', 'source', 'fields' ], $output['required'] );
		$this->assertSame( [ 'id', 'supported', 'source', 'fields' ], array_keys( $output['properties'] ) );
		$this->assertSame( [ 'file', 'stored', 'none' ], $output['properties']['source']['enum'] );
		$this->assertFalse( $output['properties']['fields']['additionalProperties'] );
		$this->assertSame( array_keys( self::NO_FIELDS ), array_keys( $output['properties']['fields']['properties'] ) );
		$this->assertSame( array_keys( self::NO_FIELDS ), $output['properties']['fields']['required'] );
	}

	/**
	 * The other free abilities stay public on the default gateway.
	 */
	public function test_other_free_abilities_stay_public() {
		foreach ( [ 'tsmlt/search-media', 'tsmlt/get-media-details', 'tsmlt/update-media-metadata' ] as $name ) {
			$this->assertTrue( wp_get_ability( $name )->get_meta()['mcp']['public'], $name );
		}
	}

	// ---------------------------------------------------------------------
	// Sources
	// ---------------------------------------------------------------------

	/**
	 * A JPEG with EXIF in the file and nothing stored: the file's fields.
	 */
	public function test_jpeg_file_exif() {
		$id = $this->upload_exif_jpeg();

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assert_valid_output( $result );
		$this->assertSame(
			[
				'id'        => $id,
				'supported' => true,
				'source'    => 'file',
				'fields'    => [
					'make'               => 'TestCam',
					'model'              => 'Model X1',
					'date_time_original' => '2026:03:14 09:26:53',
					'iso'                => 400,
					'aperture'           => 1.8,
					'shutter_speed'      => '1/250',
				],
			],
			$result
		);
	}

	/**
	 * A JPEG with stored fields: they take precedence over the file and the
	 * source is "stored".
	 */
	public function test_jpeg_stored_fields_take_precedence() {
		$id = $this->upload_exif_jpeg();
		update_post_meta(
			$id,
			'_tsmlt_exif_meta',
			[
				'make' => 'Edited',
				'iso'  => 800,
			]
		);

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assert_valid_output( $result );
		$this->assertSame( 'stored', $result['source'] );
		$this->assertSame( 'Edited', $result['fields']['make'] );
		$this->assertSame( 800, $result['fields']['iso'] );
		// Fields not stored still come from the file.
		$this->assertSame( 'Model X1', $result['fields']['model'] );
	}

	/**
	 * A PNG edited in the EXIF editor: the stored fields.
	 */
	public function test_png_stored_fields() {
		$id = $this->upload_test_image( 'test-image.png' );
		$this->assertSame( 'image/png', get_post_mime_type( $id ), 'Precondition: a PNG attachment.' );
		update_post_meta( $id, '_tsmlt_exif_meta', self::stored_fields() );

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assert_valid_output( $result );
		$this->assertSame(
			[
				'id'        => $id,
				'supported' => true,
				'source'    => 'stored',
				'fields'    => [
					'make'               => 'Canon',
					'model'              => 'EOD R5',
					'date_time_original' => '2026:09:01 18:30:00',
					'iso'                => 100,
					'aperture'           => 1.8,
					'shutter_speed'      => '1/250',
				],
			],
			$result
		);
	}

	/**
	 * Images without EXIF or stored fields: supported, but nothing found.
	 *
	 * @dataProvider data_images_without_fields
	 *
	 * @param string $file Test-data image.
	 */
	public function test_image_without_fields( string $file ) {
		$id = $this->upload_test_image( $file );

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assert_valid_output( $result );
		$this->assertSame(
			[
				'id'        => $id,
				'supported' => true,
				'source'    => 'none',
				'fields'    => self::NO_FIELDS,
			],
			$result
		);
	}

	/**
	 * Images without fields.
	 *
	 * @return array
	 */
	public function data_images_without_fields(): array {
		return [
			'JPEG without EXIF' => [ 'test-square-150.jpg' ],
			'PNG'               => [ 'test-image.png' ],
		];
	}

	/**
	 * Stored fields that are empty or unusable give no values.
	 */
	public function test_unusable_stored_fields() {
		$id = $this->upload_test_image( 'test-image.png' );
		update_post_meta(
			$id,
			'_tsmlt_exif_meta',
			[
				'make'               => [ 'Canon' ],
				'model'              => '   ',
				'date_time_original' => 'yesterday',
				'iso'                => 0,
				'aperture'           => 'wide',
				'shutter_speed'      => 'fast',
				'gps_lat'            => self::GPS_LAT,
				'copyright'          => self::COPYRIGHT,
			]
		);

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assert_valid_output( $result );
		$this->assertSame( 'none', $result['source'] );
		$this->assertSame( self::NO_FIELDS, $result['fields'] );
	}

	/**
	 * Stored values are sanitized and normalized.
	 */
	public function test_stored_values_are_normalized() {
		$id = $this->upload_test_image( 'test-image.png' );
		update_post_meta(
			$id,
			'_tsmlt_exif_meta',
			[
				'make'          => '<b>Canon</b> <script>alert(1)</script>',
				'model'         => str_repeat( 'M', 300 ),
				'iso'           => '200',
				'aperture'      => 'f/2.8',
				'shutter_speed' => '1.00 s',
			]
		);

		$fields = $this->run_ability( [ 'id' => $id ] )['fields'];

		$this->assertSame( 'Canon', $fields['make'] );
		$this->assertSame( str_repeat( 'M', GetExifFields::MAX_TEXT_LENGTH ), $fields['model'] );
		$this->assertSame( 200, $fields['iso'] );
		$this->assertSame( 2.8, $fields['aperture'] );
		$this->assertSame( '1.00', $fields['shutter_speed'] );
	}

	/**
	 * Shutter speeds are returned in seconds without a unit.
	 *
	 * @dataProvider data_shutter_speeds
	 *
	 * @param string      $stored   Stored value.
	 * @param string|null $expected Returned value.
	 */
	public function test_shutter_speed_formats( string $stored, ?string $expected ) {
		$id = $this->upload_test_image( 'test-image.png' );
		update_post_meta( $id, '_tsmlt_exif_meta', [ 'shutter_speed' => $stored ] );

		$this->assertSame( $expected, $this->run_ability( [ 'id' => $id ] )['fields']['shutter_speed'] );
	}

	/**
	 * Stored shutter speeds and the returned value.
	 *
	 * @return array
	 */
	public function data_shutter_speeds(): array {
		return [
			'fraction'      => [ '1/250', '1/250' ],
			'fraction s'    => [ '1/250s', '1/250' ],
			'seconds sec'   => [ '2 sec', '2' ],
			'decimal'       => [ '0.5', '0.5' ],
			'thousands sep' => [ '1/4,000', null ],
			'text'          => [ 'Bulb', null ],
		];
	}

	/**
	 * Non-image attachments are not supported, even with stored fields.
	 */
	public function test_non_image_attachment() {
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'application/pdf',
				'file'           => '2026/10/document.pdf',
			]
		);
		update_post_meta( $id, '_tsmlt_exif_meta', self::stored_fields() );

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assert_valid_output( $result );
		$this->assertSame(
			[
				'id'        => $id,
				'supported' => false,
				'source'    => 'none',
				'fields'    => self::NO_FIELDS,
			],
			$result
		);
	}

	/**
	 * A JPEG whose file is missing is not supported.
	 */
	public function test_jpeg_with_missing_file() {
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'file'           => '2026/10/missing-file.jpg',
			]
		);

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assert_valid_output( $result );
		$this->assertFalse( $result['supported'] );
		$this->assertSame( 'none', $result['source'] );
		$this->assertSame( self::NO_FIELDS, $result['fields'] );
	}

	// ---------------------------------------------------------------------
	// Stored MIME type differs from the file (HEIC converted to JPEG)
	// ---------------------------------------------------------------------

	/**
	 * Give an attachment the stored MIME type WordPress keeps for an HEIC
	 * upload it converted to JPEG.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return int The same ID.
	 */
	private function as_heic( int $id ): int {
		wp_update_post(
			[
				'ID'             => $id,
				'post_mime_type' => 'image/heic',
			]
		);
		clean_post_cache( $id );
		$this->assertSame( 'image/heic', get_post_mime_type( $id ), 'Precondition: stored MIME type is HEIC.' );
		return $id;
	}

	/**
	 * Stored MIME `image/heic` with an attached JPEG: the JPEG's EXIF is read.
	 */
	public function test_heic_mime_with_jpeg_file_reads_file_exif() {
		$id = $this->as_heic( $this->upload_exif_jpeg() );

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assert_valid_output( $result );
		$this->assertSame(
			[
				'id'        => $id,
				'supported' => true,
				'source'    => 'file',
				'fields'    => [
					'make'               => 'TestCam',
					'model'              => 'Model X1',
					'date_time_original' => '2026:03:14 09:26:53',
					'iso'                => 400,
					'aperture'           => 1.8,
					'shutter_speed'      => '1/250',
				],
			],
			$result
		);

		$json = wp_json_encode( $result );
		foreach ( [ self::SERIAL, self::COPYRIGHT, self::ARTIST, '51.5', '-0.1', 'gps', 'GPS', 'latitude', 'longitude', 'location' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, $needle );
		}
	}

	/**
	 * Stored MIME `image/heic` with an attached JPEG: saved editor fields
	 * still win over the file.
	 */
	public function test_heic_mime_with_jpeg_file_prefers_stored_fields() {
		$id = $this->as_heic( $this->upload_exif_jpeg() );
		update_post_meta(
			$id,
			'_tsmlt_exif_meta',
			[
				'make' => 'Edited',
				'iso'  => 800,
			]
		);

		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assert_valid_output( $result );
		$this->assertSame( 'stored', $result['source'] );
		$this->assertSame( 'Edited', $result['fields']['make'] );
		$this->assertSame( 800, $result['fields']['iso'] );
		$this->assertSame( 'Model X1', $result['fields']['model'], 'Fields not saved still come from the file.' );
	}

	/**
	 * Stored MIME `image/heic` with a file that is not a JPEG keeps the
	 * saved-fields behavior.
	 */
	public function test_heic_mime_with_non_jpeg_file_uses_stored_fields() {
		$id = $this->as_heic( $this->upload_test_image( 'test-image.png' ) );

		$this->assertSame(
			[
				'id'        => $id,
				'supported' => true,
				'source'    => 'none',
				'fields'    => self::NO_FIELDS,
			],
			$this->run_ability( [ 'id' => $id ] )
		);

		update_post_meta( $id, '_tsmlt_exif_meta', self::stored_fields() );
		$result = $this->run_ability( [ 'id' => $id ] );

		$this->assertSame( 'stored', $result['source'] );
		$this->assertSame( 'Canon', $result['fields']['make'] );
	}

	/**
	 * Stored MIME `image/heic` with a missing file keeps the saved-fields
	 * behavior.
	 */
	public function test_heic_mime_with_missing_file_uses_stored_fields() {
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/heic',
				'file'           => '2026/10/missing.heic',
			]
		);

		$this->assertSame(
			[
				'id'        => $id,
				'supported' => true,
				'source'    => 'none',
				'fields'    => self::NO_FIELDS,
			],
			$this->run_ability( [ 'id' => $id ] )
		);
	}

	// ---------------------------------------------------------------------
	// Privacy and side effects
	// ---------------------------------------------------------------------

	/**
	 * GPS (and whether there is any), copyright, artist, serial number, raw
	 * EXIF and file paths are never returned, from the file or stored fields.
	 */
	public function test_private_metadata_is_never_returned() {
		$jpeg = $this->upload_exif_jpeg();
		$raw  = exif_read_data( get_attached_file( $jpeg ), null, true );
		$this->assertArrayHasKey( 'GPS', $raw, 'Precondition: the fixture has GPS data.' );
		$this->assertStringContainsString( self::SERIAL, wp_json_encode( $raw ), 'Precondition: the fixture has a serial number.' );

		$png = $this->upload_test_image( 'test-image.png' );
		update_post_meta( $png, '_tsmlt_exif_meta', self::stored_fields() );
		update_post_meta( $jpeg, '_tsmlt_exif_meta', [ 'copyright' => self::COPYRIGHT ] );

		foreach ( [ $jpeg, $png ] as $id ) {
			$json = wp_json_encode( $this->run_ability( [ 'id' => $id ] ) );
			$path = str_replace( '\\/', '/', $json );

			foreach ( [ self::SERIAL, self::COPYRIGHT, self::ARTIST, '51.5', '-0.1', '23.81', '90.41', 'gps', 'GPS', 'latitude', 'longitude', 'location', 'copyright', 'artist', 'IFD0', 'EXIF' ] as $needle ) {
				$this->assertStringNotContainsString( $needle, $json, $needle );
			}
			foreach ( [ ABSPATH, WP_CONTENT_DIR, wp_upload_dir()['basedir'], get_attached_file( $id ) ] as $dir ) {
				$this->assertStringNotContainsString( untrailingslashit( $dir ), $path );
			}
		}
	}

	/**
	 * Reading fields changes nothing: post fields, meta and the file.
	 */
	public function test_no_side_effects() {
		$jpeg = $this->upload_exif_jpeg();
		$png  = $this->upload_test_image( 'test-image.png' );
		update_post_meta( $png, '_tsmlt_exif_meta', self::stored_fields() );

		foreach ( [ $jpeg, $png ] as $id ) {
			$before = $this->snapshot( $id );
			$this->run_ability( [ 'id' => $id ] );
			$this->run_ability( [ 'id' => $id ] );
			$this->assertSame( $before, $this->snapshot( $id ) );
		}
	}

	// ---------------------------------------------------------------------
	// Input and access
	// ---------------------------------------------------------------------

	/**
	 * Invalid input is rejected before execution.
	 *
	 * @dataProvider data_invalid_input
	 *
	 * @param mixed $input Input.
	 */
	public function test_invalid_input_is_rejected( $input ) {
		$result = $this->run_ability( $input );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Invalid inputs.
	 *
	 * @return array
	 */
	public function data_invalid_input(): array {
		return [
			'no input'         => [ null ],
			'empty object'     => [ [] ],
			'not an object'    => [ 5 ],
			'zero'             => [ [ 'id' => 0 ] ],
			'negative'         => [ [ 'id' => -3 ] ],
			'float'            => [ [ 'id' => 1.5 ] ],
			'non numeric'      => [ [ 'id' => 'abc' ] ],
			'array'            => [ [ 'id' => [ 1 ] ] ],
			'null id'          => [ [ 'id' => null ] ],
			'unknown property' => [
				[
					'id'     => 1,
					'fields' => [ 'gps_lat' ],
				],
			],
			'only unknown'     => [ [ 'ID' => 1 ] ],
		];
	}

	/**
	 * Missing, non-attachment and trashed IDs are not found.
	 */
	public function test_missing_non_attachment_and_trashed_are_not_found() {
		$post_id = self::factory()->post->create();
		$trashed = $this->upload_exif_jpeg();
		wp_update_post(
			[
				'ID'          => $trashed,
				'post_status' => 'trash',
			]
		);

		foreach ( [ 99999999, $post_id, $trashed ] as $id ) {
			$result = $this->run_ability( [ 'id' => $id ] );
			$this->assertWPError( $result );
			$this->assertSame( AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND, $result->get_error_code() );
			$this->assertSame( 404, $result->get_error_data()['status'] );
			$this->assertStringNotContainsString( 'TestCam', wp_json_encode( $result ) );
		}
	}

	/**
	 * An attachment the user may not read is reported exactly like a missing
	 * one.
	 */
	public function test_unreadable_attachment_is_not_found() {
		$id   = $this->upload_exif_jpeg();
		$deny = static function ( $caps, $cap, $user_id, $args ) use ( $id ) {
			if ( 'read_post' === $cap && isset( $args[0] ) && (int) $args[0] === $id ) {
				return [ 'do_not_allow' ];
			}
			return $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 4 );
		$result = $this->run_ability( [ 'id' => $id ] );
		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assertWPError( $result );
		$this->assertSame( AbilityGuard::ERROR_ATTACHMENT_NOT_FOUND, $result->get_error_code() );
		$this->assertStringNotContainsString( 'TestCam', wp_json_encode( $result ) );
	}

	/**
	 * Logged-out requests are refused.
	 */
	public function test_logged_out_is_refused() {
		$id = $this->upload_exif_jpeg();
		wp_set_current_user( 0 );

		$this->assertFalse( GetExifFields::instance()->check_permission( [ 'id' => $id ] ) );
		$result = $this->run_ability( [ 'id' => $id ] );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Users without the plugin's base capability are refused.
	 *
	 * @dataProvider data_roles_without_manage_options
	 *
	 * @param string $role Role.
	 */
	public function test_users_without_manage_options_are_refused( string $role ) {
		$id = $this->upload_exif_jpeg();
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );

		$this->assertFalse( GetExifFields::instance()->check_permission( [ 'id' => $id ] ) );
		$result = $this->run_ability( [ 'id' => $id ] );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		$this->assertStringNotContainsString( 'TestCam', wp_json_encode( $result ) );
	}

	/**
	 * Roles without manage_options.
	 *
	 * @return array
	 */
	public function data_roles_without_manage_options(): array {
		return [
			'editor'     => [ 'editor' ],
			'author'     => [ 'author' ],
			'subscriber' => [ 'subscriber' ],
		];
	}
}
