<?php
/**
 * EXIF processing on upload in the free plugin: each rule on and off, the
 * Pro-only GPS removal when Pro is absent, and uploads without EXIF.
 *
 * Uploads go through the real `add_attachment` hook. The JPEG carries known
 * camera, description, date and GPS EXIF, added by TsmltExifJpegFixture.
 *
 * @package TinySolutions\mlt
 */

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';

use TinySolutions\mlt\Modules\ExifData\ExifAutoProcessor;

/**
 * ExifAutoProcessorTest
 */
class ExifAutoProcessorTest extends WP_UnitTestCase {

	/**
	 * Date taken in the fixture, in EXIF and MySQL form.
	 */
	const DATE_TAKEN       = '2019:07:04 15:20:10';
	const DATE_TAKEN_MYSQL = '2019-07-04 15:20:10';

	/**
	 * Image description in the fixture.
	 */
	const DESCRIPTION = 'Harbour at dawn';

	/**
	 * Temporary files created by a test.
	 *
	 * @var string[]
	 */
	private $temp_files = [];

	/**
	 * Times the Pro GPS hook fired.
	 *
	 * @var int
	 */
	private $gps_hook_calls = 0;

	/**
	 * Administrator, default settings and a GPS hook spy for each test.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'exif_read_data' ) ) {
			$this->markTestSkipped( 'The PHP EXIF extension is required.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( 'tsmlt_settings' );
		$this->gps_hook_calls = 0;
		add_action( 'tsmlt_auto_remove_gps_on_upload', [ $this, 'count_gps_hook' ] );
	}

	/**
	 * Remove temporary files.
	 */
	public function tear_down() {
		remove_action( 'tsmlt_auto_remove_gps_on_upload', [ $this, 'count_gps_hook' ] );
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
		$this->temp_files = [];
		parent::tear_down();
	}

	/**
	 * GPS hook spy.
	 */
	public function count_gps_hook() {
		++$this->gps_hook_calls;
	}

	/**
	 * Save EXIF-on-upload settings.
	 *
	 * @param array $settings Settings.
	 */
	private function settings( array $settings ) {
		update_option( 'tsmlt_settings', $settings );
	}

	/**
	 * Upload raw bytes; `add_attachment` runs the processor.
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
	 * The JPEG bytes with camera, description, date and GPS EXIF.
	 *
	 * @return string
	 */
	private static function exif_jpeg_bytes(): string {
		return TsmltExifJpegFixture::build(
			DIR_TESTDATA . '/images/test-square-150.jpg',
			[
				[ 0x010F, TsmltExifJpegFixture::ASCII, 'TestCam' ],
				[ 0x0110, TsmltExifJpegFixture::ASCII, 'Model X1' ],
				[ 0x010E, TsmltExifJpegFixture::ASCII, self::DESCRIPTION ],
			],
			[
				[ 0x9003, TsmltExifJpegFixture::ASCII, self::DATE_TAKEN ],
			],
			[
				[ 0x0001, TsmltExifJpegFixture::ASCII, 'N' ],
				[ 0x0002, TsmltExifJpegFixture::RATIONAL, [ [ 51, 1 ], [ 30, 1 ], [ 1234, 100 ] ] ],
				[ 0x0003, TsmltExifJpegFixture::ASCII, 'W' ],
				[ 0x0004, TsmltExifJpegFixture::RATIONAL, [ [ 0, 1 ], [ 7, 1 ], [ 3956, 100 ] ] ],
			]
		);
	}

	/**
	 * Upload the EXIF JPEG.
	 *
	 * @return int Attachment ID.
	 */
	private function upload_exif_jpeg(): int {
		return $this->upload_bytes( self::exif_jpeg_bytes(), 'exif-upload.jpg' );
	}

	/**
	 * The stored EXIF index meta.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array
	 */
	private function index_meta( int $id ): array {
		return [
			'camera' => get_post_meta( $id, '_tsmlt_exif_camera', true ),
			'lat'    => get_post_meta( $id, '_tsmlt_exif_gps_lat', true ),
			'lng'    => get_post_meta( $id, '_tsmlt_exif_gps_lng', true ),
		];
	}

	// ---------------------------------------------------------------------
	// Master switch
	// ---------------------------------------------------------------------

	/**
	 * With default settings nothing is processed, even with every rule on
	 * but the master switch off.
	 *
	 * @dataProvider data_master_off
	 *
	 * @param array $settings Settings.
	 */
	public function test_nothing_happens_while_master_switch_is_off( array $settings ) {
		$this->settings( $settings );
		$bytes = self::exif_jpeg_bytes();

		$id   = $this->upload_exif_jpeg();
		$post = get_post( $id );

		$this->assertSame(
			[
				'camera' => '',
				'lat'    => '',
				'lng'    => '',
			],
			$this->index_meta( $id )
		);
		$this->assertSame( '', $post->post_excerpt );
		$this->assertNotSame( self::DATE_TAKEN_MYSQL, $post->post_date );
		$this->assertSame( 0, $this->gps_hook_calls );
		$this->assertSame( sha1( $bytes ), sha1_file( get_attached_file( $id ) ) );
	}

	/**
	 * Settings with the master switch off.
	 *
	 * @return array
	 */
	public function data_master_off(): array {
		return [
			'defaults'             => [ [] ],
			'rules on, master off' => [
				[
					'auto_process_exif'            => 0,
					'auto_fill_metadata_from_exif' => 1,
					'auto_set_date_from_exif'      => 1,
					'auto_remove_gps_on_upload'    => 1,
				],
			],
		];
	}

	/**
	 * Master switch on: camera and location are stored for filtering, and no
	 * rule runs.
	 */
	public function test_master_switch_stores_index_only() {
		$this->settings( [ 'auto_process_exif' => 1 ] );

		$id   = $this->upload_exif_jpeg();
		$post = get_post( $id );
		$meta = $this->index_meta( $id );

		$this->assertSame( 'TestCam Model X1', $meta['camera'] );
		$this->assertEqualsWithDelta( 51.503428, (float) $meta['lat'], 0.0001 );
		$this->assertEqualsWithDelta( -0.127656, (float) $meta['lng'], 0.0001 );
		$this->assertSame( '', $post->post_excerpt );
		$this->assertNotSame( self::DATE_TAKEN_MYSQL, $post->post_date );
		$this->assertSame( 0, $this->gps_hook_calls );
	}

	// ---------------------------------------------------------------------
	// Rules
	// ---------------------------------------------------------------------

	/**
	 * Fill missing metadata: the empty caption comes from the EXIF description;
	 * a title that is already set is kept.
	 */
	public function test_fill_missing_metadata_on() {
		$this->settings(
			[
				'auto_process_exif'            => 1,
				'auto_fill_metadata_from_exif' => 1,
			]
		);

		$id   = $this->upload_exif_jpeg();
		$post = get_post( $id );

		$this->assertSame( self::DESCRIPTION, $post->post_excerpt );
		$this->assertNotSame( '', $post->post_title, 'Precondition: WordPress set a title from the file name.' );
		$this->assertNotSame( 'TestCam Model X1', $post->post_title );
		$this->assertNotSame( '', get_post_meta( $id, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * Fill missing metadata off: the caption stays empty.
	 */
	public function test_fill_missing_metadata_off() {
		$this->settings( [ 'auto_process_exif' => 1 ] );

		$this->assertSame( '', get_post( $this->upload_exif_jpeg() )->post_excerpt );
	}

	/**
	 * Use date taken: the attachment date becomes the EXIF date.
	 */
	public function test_date_taken_on() {
		$this->settings(
			[
				'auto_process_exif'       => 1,
				'auto_set_date_from_exif' => 1,
			]
		);

		$post = get_post( $this->upload_exif_jpeg() );

		$this->assertSame( self::DATE_TAKEN_MYSQL, $post->post_date );
		$this->assertSame( get_gmt_from_date( self::DATE_TAKEN_MYSQL ), $post->post_date_gmt );
	}

	/**
	 * Use date taken off: the attachment keeps its upload date.
	 */
	public function test_date_taken_off() {
		$this->settings( [ 'auto_process_exif' => 1 ] );

		$this->assertNotSame( self::DATE_TAKEN_MYSQL, get_post( $this->upload_exif_jpeg() )->post_date );
	}

	// ---------------------------------------------------------------------
	// GPS removal without Pro
	// ---------------------------------------------------------------------

	/**
	 * GPS removal enabled without Pro: the Pro hook does not fire, the file is
	 * unchanged, and the stored location still matches the file.
	 */
	public function test_gps_removal_without_pro_does_nothing() {
		$this->assertFalse( tsmlt()->has_pro(), 'Precondition: Pro is not active.' );
		$this->settings(
			[
				'auto_process_exif'         => 1,
				'auto_remove_gps_on_upload' => 1,
			]
		);
		$bytes = self::exif_jpeg_bytes();

		$id = $this->upload_exif_jpeg();

		$this->assertSame( 0, $this->gps_hook_calls );
		$this->assertSame( sha1( $bytes ), sha1_file( get_attached_file( $id ) ) );
		$this->assertArrayHasKey( 'GPS', exif_read_data( get_attached_file( $id ), null, true ) );
		$this->assertNotSame( '', $this->index_meta( $id )['lat'] );
	}

	// ---------------------------------------------------------------------
	// Uploads without usable EXIF
	// ---------------------------------------------------------------------

	/**
	 * Images and files without EXIF upload normally with every rule on, and
	 * nothing is stored.
	 *
	 * @dataProvider data_files_without_exif
	 *
	 * @param string $file Test-data file.
	 */
	public function test_uploads_without_exif_are_unaffected( string $file ) {
		$this->settings(
			[
				'auto_process_exif'            => 1,
				'auto_fill_metadata_from_exif' => 1,
				'auto_set_date_from_exif'      => 1,
				'auto_remove_gps_on_upload'    => 1,
			]
		);

		$id = $this->upload_bytes( (string) file_get_contents( DIR_TESTDATA . '/' . $file ), basename( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.

		$this->assertGreaterThan( 0, $id );
		$this->assertSame( 'inherit', get_post( $id )->post_status );
		$this->assertSame(
			[
				'camera' => '',
				'lat'    => '',
				'lng'    => '',
			],
			$this->index_meta( $id )
		);
		$this->assertSame( '', get_post( $id )->post_excerpt );
		$this->assertSame( 0, $this->gps_hook_calls );
	}

	/**
	 * Files without EXIF.
	 *
	 * @return array
	 */
	public function data_files_without_exif(): array {
		return [
			'JPEG without EXIF' => [ 'images/test-square-150.jpg' ],
			'PNG'               => [ 'images/test-image.png' ],
		];
	}

	/**
	 * A missing file or attachment is ignored without errors.
	 */
	public function test_missing_file_or_attachment_is_ignored() {
		$this->settings(
			[
				'auto_process_exif'       => 1,
				'auto_set_date_from_exif' => 1,
			]
		);
		$id     = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'file'           => '2026/10/not-on-disk.jpg',
			]
		);
		$before = get_post( $id )->post_date;

		ExifAutoProcessor::instance()->process_on_upload( $id );
		ExifAutoProcessor::instance()->process_on_upload( 99999999 );

		$this->assertSame( $before, get_post( $id )->post_date );
		$this->assertSame( '', $this->index_meta( $id )['camera'] );
	}
}
