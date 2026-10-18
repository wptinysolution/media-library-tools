<?php
/**
 * Free "Remove EXIF Data" (ExifStripper) when the stored MIME type differs
 * from the attached file: WordPress keeps `image/heic` for HEIC uploads it
 * converts to JPEG, so the stripper must go by the file's actual type.
 *
 * @package TinySolutions\mlt
 */

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';

use TinySolutions\mlt\Modules\ExifData\ExifDataReader;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;

/**
 * ExifStripperMimeTest
 */
class ExifStripperMimeTest extends WP_UnitTestCase {

	/**
	 * Temporary files created by a test.
	 *
	 * @var string[]
	 */
	private $temp_files = [];

	/**
	 * Requirements and a fresh reader cache.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'exif_read_data' ) || ! function_exists( 'imagecreatefromjpeg' ) ) {
			$this->markTestSkipped( 'The PHP EXIF extension and GD are required.' );
		}
		ExifDataReader::clear_cache();
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
	 * Upload bytes and store a MIME type.
	 *
	 * @param string $bytes File contents.
	 * @param string $name  File name.
	 * @param string $mime  Stored MIME type.
	 *
	 * @return int Attachment ID.
	 */
	private function upload_as( string $bytes, string $name, string $mime ): int {
		$temp = trailingslashit( get_temp_dir() ) . wp_unique_filename( get_temp_dir(), $name );
		file_put_contents( $temp, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $temp;

		$id                 = self::factory()->attachment->create_upload_object( $temp );
		$this->temp_files[] = get_attached_file( $id );
		wp_update_post(
			[
				'ID'             => $id,
				'post_mime_type' => $mime,
			]
		);
		clean_post_cache( $id );
		return $id;
	}

	/**
	 * A HEIC-stored attachment whose attached file is a JPEG with EXIF is
	 * strippable, and stripping removes its EXIF.
	 */
	public function test_heic_mime_with_jpeg_file_is_stripped() {
		$bytes = TsmltExifJpegFixture::build(
			DIR_TESTDATA . '/images/test-square-150.jpg',
			[ [ 0x010F, TsmltExifJpegFixture::ASCII, 'Apple' ] ],
			[],
			[ [ 0x0002, TsmltExifJpegFixture::RATIONAL, [ [ 25, 1 ], [ 48, 1 ], [ 2600, 100 ] ] ] ]
		);
		$id    = $this->upload_as( $bytes, 'converted.jpg', 'image/heic' );

		$this->assertTrue( ExifStripper::instance()->check_strippable_exif( $id )['supported'] );

		$result = ExifStripper::instance()->strip_exif_from_attachment( $id );

		$this->assertTrue( $result['success'], $result['message'] );
		$exif = @exif_read_data( get_attached_file( $id ), null, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A file without EXIF warns.
		$this->assertEmpty( is_array( $exif ) ? ( $exif['IFD0']['Make'] ?? null ) : null );
		$this->assertEmpty( is_array( $exif ) ? ( $exif['GPS']['GPSLatitude'] ?? null ) : null );
	}

	/**
	 * A HEIC-stored attachment whose file is a PNG stays unsupported and is
	 * not modified.
	 */
	public function test_heic_mime_with_png_file_is_refused() {
		$png = (string) file_get_contents( DIR_TESTDATA . '/images/test-image.png' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
		$id  = $this->upload_as( $png, 'converted.png', 'image/heic' );

		$this->assertFalse( ExifStripper::instance()->check_strippable_exif( $id )['supported'] );
		$this->assertFalse( ExifStripper::instance()->strip_exif_from_attachment( $id )['success'] );
		$this->assertSame( sha1( $png ), sha1_file( get_attached_file( $id ) ) );
	}
}
