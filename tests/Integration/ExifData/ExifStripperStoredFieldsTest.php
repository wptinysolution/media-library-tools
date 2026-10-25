<?php
/**
 * Removing all EXIF (ExifStripper::strip_exif_from_attachment()) also
 * removes the values the EXIF editor saved in `_tsmlt_exif_meta`, which the
 * readers overlay on the file's EXIF — otherwise they would keep reporting
 * camera, exposure and location values the file no longer has.
 *
 * @package TinySolutions\mlt
 */

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';

use TinySolutions\mlt\Helpers\ExifFilter;
use TinySolutions\mlt\Modules\ExifData\ExifDataReader;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;

/**
 * ExifStripperStoredFieldsTest
 */
class ExifStripperStoredFieldsTest extends WP_UnitTestCase {

	/**
	 * Stored editor fields used by the tests.
	 */
	const STORED = [
		'make'      => 'Stored Make',
		'model'     => 'Stored Model',
		'iso'       => 800,
		'gps_lat'   => 12.5,
		'gps_lng'   => 45.25,
		'copyright' => 'Stored Copyright',
	];

	/**
	 * Camera and GPS index meta written on upload for the media filters.
	 */
	const INDEX = [
		'_tsmlt_exif_camera'  => 'Stored Make Stored Model',
		'_tsmlt_exif_gps_lat' => '12.5',
		'_tsmlt_exif_gps_lng' => '45.25',
	];

	/**
	 * Temporary files created by a test.
	 *
	 * @var string[]
	 */
	private $temp_files = [];

	/**
	 * Administrator; requirements; fresh reader cache.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'exif_read_data' ) || ! function_exists( 'imagecreatefromjpeg' ) ) {
			$this->markTestSkipped( 'The PHP EXIF extension and GD are required.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
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
	 * @param string $bytes Bytes.
	 * @param string $name  Name.
	 *
	 * @return int Attachment ID.
	 */
	private function upload( string $bytes, string $name ): int {
		$temp = trailingslashit( get_temp_dir() ) . wp_unique_filename( get_temp_dir(), $name );
		file_put_contents( $temp, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $temp;
		$id                 = self::factory()->attachment->create_upload_object( $temp );
		$this->temp_files[] = get_attached_file( $id );
		ExifDataReader::clear_cache();
		return $id;
	}

	/**
	 * A JPEG with camera, exposure and GPS EXIF, plus stored editor fields.
	 *
	 * @return int Attachment ID.
	 */
	private function jpeg_with_stored_fields(): int {
		$id = $this->upload(
			TsmltExifJpegFixture::build(
				DIR_TESTDATA . '/images/test-square-150.jpg',
				[
					[ 0x010F, TsmltExifJpegFixture::ASCII, 'File Make' ],
					[ 0x0110, TsmltExifJpegFixture::ASCII, 'File Model' ],
				],
				[ [ 0x8827, TsmltExifJpegFixture::SHORT, 32 ] ],
				[ [ 0x0002, TsmltExifJpegFixture::RATIONAL, [ [ 25, 1 ], [ 48, 1 ], [ 2600, 100 ] ] ] ]
			),
			'stored.jpg'
		);
		update_post_meta( $id, '_tsmlt_exif_meta', self::STORED );
		$this->seed_index( $id );
		update_post_meta( $id, '_wp_attachment_image_alt', 'Alt stays' );
		update_post_meta( $id, '_tsmlt_unrelated', 'Kept' );
		ExifDataReader::clear_cache();
		return $id;
	}

	/**
	 * Seed the camera and GPS index meta.
	 *
	 * @param int $id Attachment ID.
	 */
	private function seed_index( int $id ) {
		foreach ( self::INDEX as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
	}

	/**
	 * IDs the media table's EXIF filters return.
	 *
	 * @param array $params Filter parameters.
	 *
	 * @return int[]
	 */
	private static function filtered_ids( array $params ): array {
		$args = ExifFilter::apply_filters(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			],
			$params
		);
		return array_map( 'intval', ( new WP_Query( $args ) )->posts );
	}

	/**
	 * A full strip clears the stored editor fields and the camera and GPS
	 * index, so the media filters no longer list the image; other meta and
	 * the result shape are unchanged.
	 */
	public function test_full_strip_removes_index_meta() {
		$id = $this->jpeg_with_stored_fields();
		$this->assertContains( $id, self::filtered_ids( [ 'exif_has_gps' => 'yes' ] ), 'Precondition: listed as having GPS.' );
		$this->assertContains( $id, self::filtered_ids( [ 'exif_camera' => 'Stored Make' ] ), 'Precondition: listed by camera.' );

		$stripped = [];
		$listener = static function ( $attachment_id, $mode ) use ( &$stripped ) {
			$stripped[] = [ $attachment_id, $mode ];
		};
		add_action( 'tsmlt_exif_stripped', $listener, 10, 2 );
		$result = ExifStripper::instance()->strip_exif_from_attachment( $id );
		remove_action( 'tsmlt_exif_stripped', $listener, 10 );

		$this->assertSame( [ 'success', 'message' ], array_keys( $result ) );
		$this->assertTrue( $result['success'], $result['message'] );
		$this->assertSame( [ [ $id, 'all' ] ], $stripped, 'tsmlt_exif_stripped fires once after the strip.' );
		foreach ( array_merge( [ '_tsmlt_exif_meta' ], array_keys( self::INDEX ) ) as $key ) {
			$this->assertFalse( metadata_exists( 'post', $id, $key ), "$key removed" );
		}
		$this->assertNotContains( $id, self::filtered_ids( [ 'exif_has_gps' => 'yes' ] ) );
		$this->assertContains( $id, self::filtered_ids( [ 'exif_has_gps' => 'no' ] ) );
		$this->assertNotContains( $id, self::filtered_ids( [ 'exif_camera' => 'Stored Make' ] ) );
		$this->assertSame( 'Alt stays', get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		$this->assertSame( 'Kept', get_post_meta( $id, '_tsmlt_unrelated', true ) );
	}

	/**
	 * Before the strip the stored values win; after it, neither the file nor
	 * the stored fields report anything, and other meta is untouched.
	 */
	public function test_full_strip_removes_stored_editor_fields() {
		$id     = $this->jpeg_with_stored_fields();
		$before = ExifDataReader::instance()->get_editable_exif( $id );
		$this->assertSame( 'Stored Make', $before['make'], 'Precondition: stored values overlay the file.' );
		$this->assertSame( 12.5, $before['gps_lat'] );

		$result = ExifStripper::instance()->strip_exif_from_attachment( $id );

		$this->assertTrue( $result['success'], $result['message'] );
		$this->assertSame( '', get_post_meta( $id, '_tsmlt_exif_meta', true ) );
		$after = ExifDataReader::instance()->get_editable_exif( $id );
		foreach ( [ 'make', 'model', 'copyright' ] as $key ) {
			$this->assertSame( '', $after[ $key ], $key );
		}
		foreach ( [ 'iso', 'gps_lat', 'gps_lng' ] as $key ) {
			$this->assertNull( $after[ $key ], $key );
		}
		$this->assertStringNotContainsString( 'Stored', ExifDataReader::instance()->get_camera_display( $id ) );
		$this->assertSame( 'Alt stays', get_post_meta( $id, '_wp_attachment_image_alt', true ), 'Unrelated meta kept.' );
	}

	/**
	 * The MCP ability reads the stored fields; after a full strip it reports
	 * none.
	 */
	public function test_get_exif_fields_reports_nothing_after_full_strip() {
		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'tsmlt/get-exif-fields' ) : null;
		if ( ! $ability instanceof WP_Ability ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		$id = $this->jpeg_with_stored_fields();
		$this->assertSame( 'stored', $ability->execute( [ 'id' => $id ] )['source'], 'Precondition.' );

		$this->assertTrue( ExifStripper::instance()->strip_exif_from_attachment( $id )['success'] );
		ExifDataReader::clear_cache();

		$result = $ability->execute( [ 'id' => $id ] );
		$this->assertSame( 'none', $result['source'] );
		$this->assertSame( [], array_filter( $result['fields'], 'is_scalar' ) );
	}

	/**
	 * A strip that does not happen (not a JPEG) leaves the stored fields.
	 */
	public function test_refused_strip_keeps_stored_fields() {
		$png = (string) file_get_contents( DIR_TESTDATA . '/images/test-image.png' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
		$id  = $this->upload( $png, 'image.png' );
		update_post_meta( $id, '_tsmlt_exif_meta', self::STORED );
		$this->seed_index( $id );

		$fired = did_action( 'tsmlt_exif_stripped' );
		$this->assertFalse( ExifStripper::instance()->strip_exif_from_attachment( $id )['success'] );
		$this->assertSame( $fired, did_action( 'tsmlt_exif_stripped' ), 'No strip action for a refused strip.' );
		$this->assertSame( self::STORED, get_post_meta( $id, '_tsmlt_exif_meta', true ) );
		foreach ( self::INDEX as $key => $value ) {
			$this->assertSame( $value, get_post_meta( $id, $key, true ), "$key kept" );
		}
	}
}
