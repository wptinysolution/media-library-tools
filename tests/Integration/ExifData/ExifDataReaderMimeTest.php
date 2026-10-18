<?php
/**
 * ExifDataReader::get_exif_data() when the stored MIME type differs from the
 * attached file: WordPress keeps `image/heic` for HEIC uploads it converts to
 * JPEG, so the reader must go by the file's actual type.
 *
 * @package TinySolutions\mlt
 */

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';

use TinySolutions\mlt\Modules\ExifData\ExifDataReader;

/**
 * ExifDataReaderMimeTest
 */
class ExifDataReaderMimeTest extends WP_UnitTestCase {

	/**
	 * Temporary files created by a test.
	 *
	 * @var string[]
	 */
	private $temp_files = [];

	/**
	 * Fresh reader cache for each test.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'exif_read_data' ) ) {
			$this->markTestSkipped( 'The PHP EXIF extension is required.' );
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
		ExifDataReader::clear_cache();
		parent::tear_down();
	}

	/**
	 * Upload bytes as an attachment.
	 *
	 * @param string $bytes File contents.
	 * @param string $name  File name.
	 *
	 * @return int Attachment ID.
	 */
	private function upload( string $bytes, string $name ): int {
		$temp = trailingslashit( get_temp_dir() ) . wp_unique_filename( get_temp_dir(), $name );
		file_put_contents( $temp, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $temp;

		$id                 = self::factory()->attachment->create_upload_object( $temp );
		$this->temp_files[] = get_attached_file( $id );
		return $id;
	}

	/**
	 * Upload a JPEG with known camera and exposure EXIF.
	 *
	 * @return int Attachment ID.
	 */
	private function upload_exif_jpeg(): int {
		$bytes = TsmltExifJpegFixture::build(
			DIR_TESTDATA . '/images/test-square-150.jpg',
			[
				[ 0x010F, TsmltExifJpegFixture::ASCII, 'Apple' ],
				[ 0x0110, TsmltExifJpegFixture::ASCII, 'iPhone 12' ],
			],
			[
				[ 0x829A, TsmltExifJpegFixture::RATIONAL, [ [ 1, 932 ] ] ],
				[ 0x829D, TsmltExifJpegFixture::RATIONAL, [ [ 16, 10 ] ] ],
				[ 0x8827, TsmltExifJpegFixture::SHORT, 32 ],
			],
			[]
		);
		return $this->upload( $bytes, 'converted.jpg' );
	}

	/**
	 * Give an attachment a stored MIME type.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $mime MIME type.
	 *
	 * @return int The same ID.
	 */
	private function store_mime( int $id, string $mime ): int {
		wp_update_post(
			[
				'ID'             => $id,
				'post_mime_type' => $mime,
			]
		);
		clean_post_cache( $id );
		$this->assertSame( $mime, get_post_mime_type( $id ), 'Precondition: stored MIME type.' );
		return $id;
	}

	/**
	 * Stored `image/heic` with an attached JPEG: the JPEG's EXIF is read.
	 */
	public function test_heic_mime_with_jpeg_file_reads_exif() {
		$id = $this->store_mime( $this->upload_exif_jpeg(), 'image/heic' );

		$result = ExifDataReader::instance()->get_exif_data( $id );

		$this->assertTrue( $result['supported'] );
		$this->assertTrue( $result['has_exif'] );
		$this->assertSame( 'Apple', $result['data']['camera']['Make'] );
		$this->assertSame( 'iPhone 12', $result['data']['camera']['Model'] );
		$this->assertSame( 'f/1.6', $result['data']['exposure']['Aperture'] );
		$this->assertSame( '1/932', $result['data']['exposure']['Exposure Time'] );
		$this->assertSame( 32, (int) $result['data']['exposure']['ISO'] );
		$this->assertSame( [ 'supported', 'has_exif', 'data', 'raw' ], array_keys( $result ) );
	}

	/**
	 * Stored `image/heic` with an attached PNG stays unsupported, with the
	 * existing response.
	 */
	public function test_heic_mime_with_png_file_stays_unsupported() {
		$png = (string) file_get_contents( DIR_TESTDATA . '/images/test-image.png' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
		$id  = $this->store_mime( $this->upload( $png, 'converted.png' ), 'image/heic' );

		$this->assertSame(
			[
				'supported' => false,
				'has_exif'  => false,
				'error'     => 'EXIF data is only available for JPEG, TIFF, and WebP images.',
			],
			ExifDataReader::instance()->get_exif_data( $id )
		);
	}

	/**
	 * Stored `image/heic` with a missing file stays unsupported.
	 */
	public function test_heic_mime_with_missing_file_stays_unsupported() {
		$id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/heic',
				'file'           => '2026/10/missing.heic',
			]
		);

		$result = ExifDataReader::instance()->get_exif_data( $id );

		$this->assertFalse( $result['supported'] );
		$this->assertFalse( $result['has_exif'] );
	}

	/**
	 * Supported stored MIME types behave as before: a JPEG with EXIF is read;
	 * a JPEG without EXIF is supported with no camera or exposure data
	 * (exif_read_data() still returns its FILE/COMPUTED sections, so the
	 * existing result reports `has_exif` with empty `data`); a missing JPEG
	 * reports the missing file; a stored PNG is unsupported.
	 */
	public function test_supported_stored_mime_types_are_unchanged() {
		$exif = $this->upload_exif_jpeg();
		$this->assertTrue( ExifDataReader::instance()->get_exif_data( $exif )['has_exif'] );

		$plain        = $this->upload( (string) file_get_contents( DIR_TESTDATA . '/images/test-square-150.jpg' ), 'plain.jpg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
		$plain_result = ExifDataReader::instance()->get_exif_data( $plain );
		$this->assertTrue( $plain_result['supported'] );
		$this->assertSame( [], $plain_result['data'] );

		$missing = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'file'           => '2026/10/missing.jpg',
			]
		);
		$this->assertSame(
			[
				'supported' => true,
				'has_exif'  => false,
				'error'     => 'Image file not found on server.',
			],
			ExifDataReader::instance()->get_exif_data( $missing )
		);

		$png = $this->upload( (string) file_get_contents( DIR_TESTDATA . '/images/test-image.png' ), 'image.png' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
		$this->assertFalse( ExifDataReader::instance()->get_exif_data( $png )['supported'] );
	}

	/**
	 * The cached result for a HEIC-stored JPEG is the detected one: repeated
	 * calls return the same EXIF, never a cached "unsupported" result.
	 */
	public function test_cache_holds_detected_result() {
		$id = $this->store_mime( $this->upload_exif_jpeg(), 'image/heic' );

		$first  = ExifDataReader::instance()->get_exif_data( $id );
		$second = ExifDataReader::instance()->get_exif_data( $id );

		$this->assertTrue( $first['has_exif'] );
		$this->assertSame( $first, $second );

		// After clearing the cache the file is read again with the same result.
		ExifDataReader::clear_cache();
		$this->assertSame( $first, ExifDataReader::instance()->get_exif_data( $id ) );
	}
}
