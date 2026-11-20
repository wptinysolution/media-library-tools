<?php
/**
 * JpegMetadataStripper: lossless removal of EXIF, GPS, XMP, IPTC and other
 * metadata from a JPEG, with verified atomic replacement.
 *
 * Fixtures are WordPress's test JPEG with generated EXIF (TsmltExifJpegFixture)
 * plus XMP, extended XMP, IPTC, ICC, Adobe, COM and APP3 segments.
 *
 * @package TinySolutions\mlt
 */

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';
require_once dirname( __DIR__, 2 ) . '/Support/ShortWriteStripper.php';
require_once dirname( __DIR__, 2 ) . '/Support/FailedRenameStripper.php';

use TinySolutions\mlt\Modules\ExifData\JpegMetadataStripper;
use TinySolutions\mlt\Modules\ExifData\JpegSegments;

/**
 * JpegMetadataStripperTest
 */
class JpegMetadataStripperTest extends WP_UnitTestCase {

	/**
	 * ICC profile payload (APP2).
	 *
	 * @var string
	 */
	const ICC = "ICC_PROFILE\0\x01\x01ICC-MARKER-0001";

	/**
	 * Adobe payload (APP14).
	 *
	 * @var string
	 */
	const ADOBE = "Adobe\x00\x64\x00\x00\x00\x00\x01";

	/**
	 * Values that must not survive a strip.
	 *
	 * @var string[]
	 */
	const PRIVATE_VALUES = [ 'SecretCam', 'Model Z9', 'Jane Private', 'XMP-SECRET', 'EXT-XMP-SECRET', 'IPTC-SECRET', 'COMMENT-SECRET', 'APP3-SECRET' ];

	/**
	 * Temporary folder for this test.
	 *
	 * @var string
	 */
	private $dir = '';

	/**
	 * A fresh folder.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'exif_read_data' ) || ! function_exists( 'imagecreatefromstring' ) ) {
			$this->markTestSkipped( 'The EXIF extension and GD are required.' );
		}
		$this->dir = trailingslashit( get_temp_dir() ) . 'tsmlt-strip-' . wp_generate_password( 8, false );
		wp_mkdir_p( $this->dir );
	}

	/**
	 * Remove the folder.
	 */
	public function tear_down() {
		chmod( $this->dir, 0755 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			chmod( $file, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
			wp_delete_file( $file );
		}
		rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture.
		parent::tear_down();
	}

	/**
	 * A complete segment.
	 *
	 * @param int    $marker  Marker.
	 * @param string $payload Payload.
	 *
	 * @return string
	 */
	private static function segment( int $marker, string $payload ): string {
		return "\xFF" . chr( $marker ) . pack( 'n', strlen( $payload ) + 2 ) . $payload;
	}

	/**
	 * A JPEG with EXIF (camera, artist, date, GPS, optional Orientation),
	 * XMP, extended XMP, IPTC, an ICC profile, an Adobe segment, a comment
	 * and an APP3 segment.
	 *
	 * @param int $orientation Orientation (0 for none).
	 *
	 * @return string
	 */
	private static function full_jpeg( int $orientation = 6 ): string {
		$ifd0 = [
			[ 0x010F, TsmltExifJpegFixture::ASCII, 'SecretCam' ],
			[ 0x0110, TsmltExifJpegFixture::ASCII, 'Model Z9' ],
			[ 0x013B, TsmltExifJpegFixture::ASCII, 'Jane Private' ],
		];
		if ( $orientation ) {
			$ifd0[] = [ 0x0112, TsmltExifJpegFixture::SHORT, $orientation ];
		}
		$jpeg   = TsmltExifJpegFixture::build(
			DIR_TESTDATA . '/images/test-square-150.jpg',
			$ifd0,
			[ [ 0x9003, TsmltExifJpegFixture::ASCII, '2019:05:06 07:08:09' ] ],
			[
				[ 0x0001, TsmltExifJpegFixture::ASCII, 'N' ],
				[ 0x0002, TsmltExifJpegFixture::RATIONAL, [ [ 51, 1 ], [ 30, 1 ], [ 2617, 100 ] ] ],
			]
		);
		$extras = self::segment( 0xE1, "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta>XMP-SECRET</x:xmpmeta>" )
			. self::segment( 0xE1, "http://ns.adobe.com/xmp/extension/\0" . str_repeat( '0', 32 ) . 'EXT-XMP-SECRET' )
			. self::segment( 0xED, "Photoshop 3.0\08BIM\x04\x04\0\0\0\0\0\x10IPTC-SECRET" )
			. self::segment( 0xE2, self::ICC )
			. self::segment( 0xEE, self::ADOBE )
			. self::segment( 0xFE, 'COMMENT-SECRET' )
			. self::segment( 0xE3, 'APP3-SECRET' );
		return substr( $jpeg, 0, 2 ) . $extras . substr( $jpeg, 2 );
	}

	/**
	 * Write bytes to a file in the test folder.
	 *
	 * @param string $bytes Bytes.
	 * @param string $name  Name.
	 *
	 * @return string Path.
	 */
	private function file( string $bytes, string $name = 'photo.jpg' ): string {
		$path = $this->dir . '/' . $name;
		file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		return $path;
	}

	/**
	 * File contents.
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	private static function read( string $path ): string {
		clearstatcache();
		return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
	}

	/**
	 * The image data of JPEG bytes (from the first start-of-scan on).
	 *
	 * @param string $bytes Bytes.
	 *
	 * @return string
	 */
	private static function image_data( string $bytes ): string {
		return (string) substr( $bytes, JpegSegments::layout( $bytes )['image_start'] );
	}

	/**
	 * Files other than the given one left in the test folder.
	 *
	 * @param string $path The original.
	 *
	 * @return string[]
	 */
	private function leftovers( string $path ): array {
		return array_values( array_diff( (array) glob( $this->dir . '/*' ), [ $path ] ) );
	}

	/**
	 * Everything private goes; ICC, Adobe and the image data stay byte for
	 * byte; dimensions are unchanged; the image still decodes; the
	 * Orientation is kept as the only EXIF tag.
	 */
	public function test_lossless_strip() {
		$original = self::full_jpeg( 6 );
		$path     = $this->file( $original );

		$this->assertSame(
			[
				'status' => 'stripped',
				'code'   => '',
			],
			JpegMetadataStripper::strip_file( $path )
		);

		$after = self::read( $path );
		foreach ( self::PRIVATE_VALUES as $value ) {
			$this->assertStringNotContainsString( $value, $after, $value );
		}
		$this->assertStringContainsString( self::segment( 0xE2, self::ICC ), $after, 'ICC kept.' );
		$this->assertStringContainsString( self::segment( 0xEE, self::ADOBE ), $after, 'Adobe kept.' );
		$this->assertSame( self::image_data( $original ), self::image_data( $after ), 'Image data identical.' );
		$this->assertSame( array_slice( getimagesizefromstring( $original ), 0, 3 ), array_slice( getimagesizefromstring( $after ), 0, 3 ) );
		$this->assertNotFalse( imagecreatefromstring( $after ), 'Still decodes.' );
		$exif = @exif_read_data( $path, null, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Test.
		$this->assertSame( [ 'Orientation' => 6 ], $exif['IFD0'] );
		$this->assertArrayNotHasKey( 'GPS', $exif );
		$this->assertArrayNotHasKey( 'EXIF', $exif );
		$this->assertSame( [], $this->leftovers( $path ), 'No temporary or backup file left.' );
	}

	/**
	 * Without an Orientation (or with the normal one) no EXIF is written.
	 */
	public function test_no_orientation_no_exif() {
		foreach ( [ 0, 1 ] as $orientation ) {
			$path = $this->file( self::full_jpeg( $orientation ), 'o' . $orientation . '.jpg' );
			$this->assertSame( 'stripped', JpegMetadataStripper::strip_file( $path )['status'] );
			$exif = @exif_read_data( $path, null, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Test.
			$this->assertArrayNotHasKey( 'IFD0', is_array( $exif ) ? $exif : [], (string) $orientation );
			$this->assertStringNotContainsString( "Exif\0\0", self::read( $path ) );
		}
	}

	/**
	 * A second run finds nothing to remove and does not touch the file.
	 */
	public function test_repeat_run_does_not_rewrite() {
		$path = $this->file( self::full_jpeg( 6 ) );
		JpegMetadataStripper::strip_file( $path );
		$bytes = self::read( $path );
		touch( $path, time() - 3600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Test fixture: an older time to detect a rewrite.
		clearstatcache();
		$mtime = filemtime( $path );

		$this->assertSame( 'already_clean', JpegMetadataStripper::strip_file( $path )['status'] );
		clearstatcache();
		$this->assertSame( $bytes, self::read( $path ) );
		$this->assertSame( $mtime, filemtime( $path ), 'Not rewritten.' );
	}

	/**
	 * Refusals leave the file as it was: malformed, embedded metadata after
	 * the image data, not a JPEG, missing, offloaded storage.
	 */
	public function test_refusals() {
		$full = self::full_jpeg();

		$malformed = $this->file( substr( $full, 0, 40 ), 'malformed.jpg' );
		$this->assertSame(
			[
				'status' => 'failed',
				'code'   => 'malformed',
			],
			JpegMetadataStripper::strip_file( $malformed )
		);
		$this->assertSame( substr( $full, 0, 40 ), self::read( $malformed ) );

		$embedded = $this->file( $full . self::full_jpeg(), 'embedded.jpg' );
		$this->assertSame(
			[
				'status' => 'unsupported',
				'code'   => 'embedded_metadata',
			],
			JpegMetadataStripper::strip_file( $embedded )
		);
		$this->assertSame( $full . self::full_jpeg(), self::read( $embedded ) );

		$png = $this->file( (string) file_get_contents( DIR_TESTDATA . '/images/test-image.png' ), 'image.png' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
		$this->assertSame( 'unsupported', JpegMetadataStripper::strip_file( $png )['status'] );

		$this->assertSame( 'missing', JpegMetadataStripper::strip_file( $this->dir . '/none.jpg' )['status'] );
		$this->assertSame(
			[
				'status' => 'unsupported',
				'code'   => 'remote_storage',
			],
			JpegMetadataStripper::strip_file( 's3://bucket/uploads/photo.jpg' )
		);
	}

	/**
	 * Extended XMP spread over segments is removed with the rest.
	 */
	public function test_extended_xmp() {
		$path = $this->file( self::full_jpeg() );
		$this->assertSame( 'stripped', JpegMetadataStripper::strip_file( $path )['status'] );
		$this->assertStringNotContainsString( 'http://ns.adobe.com/xmp/extension/', self::read( $path ) );
	}

	/**
	 * A short write (full disk) and a failed rename leave the original
	 * untouched and no temporary file behind.
	 */
	public function test_write_and_rename_failures() {
		$original = self::full_jpeg();

		$short = $this->file( $original, 'short.jpg' );
		$this->assertSame(
			[
				'status' => 'failed',
				'code'   => 'write_failed',
			],
			TsmltShortWriteStripper::strip_file( $short )
		);
		$this->assertSame( $original, self::read( $short ) );
		$this->assertSame( [], $this->leftovers( $short ) );
		wp_delete_file( $short );

		$rename = $this->file( $original, 'rename.jpg' );
		$this->assertSame(
			[
				'status' => 'failed',
				'code'   => 'replace_failed',
			],
			TsmltFailedRenameStripper::strip_file( $rename )
		);
		$this->assertSame( $original, self::read( $rename ) );
		$this->assertSame( [], $this->leftovers( $rename ) );
	}

	/**
	 * Unreadable files and unwritable folders are refused; the original stays.
	 */
	public function test_unreadable_and_unwritable() {
		$original   = self::full_jpeg();
		$unreadable = $this->file( $original, 'locked.jpg' );
		chmod( $unreadable, 0000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		if ( is_readable( $unreadable ) ) {
			$this->markTestSkipped( 'Files stay readable for this user (running as root?).' );
		}
		$this->assertSame( 'unreadable', JpegMetadataStripper::strip_file( $unreadable )['status'] );
		chmod( $unreadable, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		wp_delete_file( $unreadable );

		$path = $this->file( $original, 'folder.jpg' );
		chmod( $this->dir, 0555 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		$result = JpegMetadataStripper::strip_file( $path );
		chmod( $this->dir, 0755 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		$this->assertSame(
			[
				'status' => 'failed',
				'code'   => 'not_writable',
			],
			$result
		);
		$this->assertSame( $original, self::read( $path ) );
		$this->assertSame( [], $this->leftovers( $path ) );
	}

	/**
	 * Verification refuses bytes whose image data, kept segments or
	 * metadata differ from what was intended.
	 */
	public function test_verify_refuses_bad_results() {
		$original = self::full_jpeg( 6 );
		$edit     = JpegMetadataStripper::strip_bytes( $original );
		$this->assertTrue( JpegMetadataStripper::verify( $original, $edit['bytes'], 6 ) );

		$changed = substr( $edit['bytes'], 0, -3 ) . "\x01\xFF\xD9";
		$this->assertSame( 'image_data_changed', JpegMetadataStripper::verify( $original, $changed, 6 ) );
		$this->assertSame( 'metadata_remains', JpegMetadataStripper::verify( $original, $edit['bytes'], 0 ), 'An Orientation segment that was not expected.' );
		$plain = self::full_jpeg( 0 );
		$this->assertSame( 'orientation_changed', JpegMetadataStripper::verify( $plain, JpegMetadataStripper::strip_bytes( $plain )['bytes'], 6 ), 'An expected Orientation segment is missing.' );
		$with_comment = substr( $edit['bytes'], 0, 2 ) . self::segment( 0xFE, 'x' ) . substr( $edit['bytes'], 2 );
		$this->assertSame( 'metadata_remains', JpegMetadataStripper::verify( $original, $with_comment, 6 ) );
		$without_icc = str_replace( self::segment( 0xE2, self::ICC ), '', $edit['bytes'] );
		$this->assertSame( 'segments_changed', JpegMetadataStripper::verify( $original, $without_icc, 6 ) );
	}
}
