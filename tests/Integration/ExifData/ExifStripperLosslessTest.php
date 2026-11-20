<?php
/**
 * ExifStripper::strip_exif_from_attachment() with the lossless stripper:
 * every JPEG file of an attachment (attached image, scaled-upload original,
 * generated sizes, pre-edit backups), per-file results, the refreshed
 * WordPress data, locks, and the copies it leaves alone.
 *
 * Uploads WordPress's test image (canola.jpg, 640x480) with generated EXIF.
 *
 * @package TinySolutions\mlt
 */

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';

use TinySolutions\mlt\Abilities\Media\GetMediaHealth;
use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\BackupManager;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionMetadata;
use TinySolutions\mlt\Modules\ExifData\ExifScanner;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\ExifData\JpegMetadataStripper;
use TinySolutions\mlt\Modules\ExifData\JpegSegments;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;

/**
 * ExifStripperLosslessTest
 */
class ExifStripperLosslessTest extends WP_UnitTestCase {

	/**
	 * Files to remove.
	 *
	 * @var string[]
	 */
	private $temp_files = [];

	/**
	 * Arguments the strip action was fired with.
	 *
	 * @var array[]
	 */
	private $fired = [];

	/**
	 * Administrator.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'exif_read_data' ) ) {
			$this->markTestSkipped( 'The EXIF extension is required.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->fired = [];
		add_action( 'tsmlt_exif_stripped', [ $this, 'record_action' ], 10, 9 );
	}

	/**
	 * Remove fixtures and filters.
	 */
	public function tear_down() {
		remove_action( 'tsmlt_exif_stripped', [ $this, 'record_action' ], 10 );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		foreach ( $this->temp_files as $file ) {
			if ( is_string( $file ) && is_file( $file ) ) {
				chmod( $file, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
				wp_delete_file( $file );
			}
		}
		parent::tear_down();
	}

	/**
	 * Record the strip action's arguments (accepts more than it is given).
	 *
	 * @param mixed ...$args Arguments.
	 *
	 * @return void
	 */
	public function record_action( ...$args ) {
		$this->fired[] = $args;
	}

	/**
	 * A big-image threshold below the test image, so uploads are scaled.
	 *
	 * @return int
	 */
	public function small_threshold() {
		return 400;
	}

	/**
	 * The test image canola.jpg with private EXIF (camera, artist, GPS).
	 *
	 * @return string
	 */
	private static function exif_jpeg(): string {
		return TsmltExifJpegFixture::build(
			DIR_TESTDATA . '/images/canola.jpg',
			[
				[ 0x010F, TsmltExifJpegFixture::ASCII, 'SecretCam' ],
				[ 0x013B, TsmltExifJpegFixture::ASCII, 'Jane Private' ],
			],
			[],
			[
				[ 0x0001, TsmltExifJpegFixture::ASCII, 'N' ],
				[ 0x0002, TsmltExifJpegFixture::RATIONAL, [ [ 51, 1 ], [ 30, 1 ], [ 2617, 100 ] ] ],
			]
		);
	}

	/**
	 * Upload the EXIF JPEG, scaled (so a full-size original is kept).
	 *
	 * @param string $name File name.
	 *
	 * @return int
	 */
	private function upload( string $name ): int {
		$temp = trailingslashit( get_temp_dir() ) . $name;
		file_put_contents( $temp, self::exif_jpeg() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $temp;
		add_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		$id = (int) self::factory()->attachment->create_upload_object( $temp );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		foreach ( self::paths( $id ) as $path ) {
			$this->temp_files[] = $path;
		}
		return $id;
	}

	/**
	 * Every file of an attachment by role key.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array<string, string>
	 */
	private static function paths( int $id ): array {
		$file     = get_attached_file( $id );
		$dir      = trailingslashit( dirname( $file ) );
		$metadata = wp_get_attachment_metadata( $id );
		$paths    = [ 'attached' => $file ];
		if ( ! empty( $metadata['original_image'] ) ) {
			$paths['original'] = $dir . $metadata['original_image'];
		}
		foreach ( (array) ( $metadata['sizes'] ?? [] ) as $name => $size ) {
			$paths[ 'size:' . $name ] = $dir . $size['file'];
		}
		foreach ( (array) get_post_meta( $id, '_wp_attachment_backup_sizes', true ) as $name => $size ) {
			if ( is_array( $size ) ) {
				$paths[ 'backup:' . $name ] = $dir . $size['file'];
			}
		}
		return $paths;
	}

	/**
	 * Give an attachment a generated size and a pre-edit backup that hold
	 * EXIF.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return void
	 */
	private function pollute_size_and_backup( int $id ) {
		$paths = self::paths( $id );
		file_put_contents( $paths['size:thumbnail'], self::exif_jpeg() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$backup = dirname( $paths['attached'] ) . '/' . pathinfo( $paths['attached'], PATHINFO_FILENAME ) . '-e1700000000.jpg';
		file_put_contents( $backup, self::exif_jpeg() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $backup;
		update_post_meta(
			$id,
			'_wp_attachment_backup_sizes',
			[
				'full-orig' => [
					'file'   => wp_basename( $backup ),
					'width'  => 640,
					'height' => 480,
				],
			]
		);
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
	 * Per-file statuses by role key.
	 *
	 * @param array $result Strip result.
	 *
	 * @return array<string, string>
	 */
	private static function statuses( array $result ): array {
		$out = [];
		foreach ( $result['files'] as $file ) {
			$out[ $file['role'] . ( '' === $file['size'] ? '' : ':' . $file['size'] ) ] = $file['status'];
		}
		return $out;
	}

	/**
	 * Every JPEG file is processed: the original and the polluted size and
	 * backup are stripped, the others are already clean; the original's
	 * image data is unchanged; nothing private is left in any file.
	 */
	public function test_every_file_is_covered() {
		$id = $this->upload( 'coverage.jpg' );
		$this->pollute_size_and_backup( $id );
		$paths    = self::paths( $id );
		$original = self::read( $paths['original'] );
		$this->assertStringContainsString( 'SecretCam', $original, 'Precondition: the original holds EXIF.' );

		$result   = ExifStripper::instance()->strip_exif_from_attachment( $id );
		$statuses = self::statuses( $result );

		$this->assertTrue( $result['success'], $result['message'] );
		$this->assertSame( 'stripped', $result['status'] );
		$this->assertSame( 'stripped', $statuses['original'] );
		$this->assertSame( 'stripped', $statuses['size:thumbnail'] );
		$this->assertSame( 'stripped', $statuses['backup:full-orig'] );
		$this->assertContains( $statuses['attached'], [ 'stripped', 'already_clean' ] );
		$this->assertSame( array_keys( $paths ), array_keys( $statuses ), 'Each file once.' );
		foreach ( $paths as $key => $path ) {
			$this->assertStringNotContainsString( 'SecretCam', self::read( $path ), $key );
			$this->assertStringNotContainsString( 'Jane Private', self::read( $path ), $key );
		}
		$after = self::read( $paths['original'] );
		$this->assertSame( substr( $original, JpegSegments::layout( $original )['image_start'] ), substr( $after, JpegSegments::layout( $after )['image_start'] ), 'Original image data identical.' );
		$this->assertSame( [ [ $id, 'all' ] ], $this->fired, 'The action fires once with its two arguments.' );
	}

	/**
	 * WordPress's stored image data loses its private fields (Orientation
	 * kept), file sizes are refreshed, and the plugin's stored EXIF and
	 * privacy-scan result are removed; media health finds no mismatch.
	 */
	public function test_metadata_refresh() {
		$id       = $this->upload( 'metadata.jpg' );
		$metadata = wp_get_attachment_metadata( $id );

		$metadata['image_meta']                     = array_merge(
			(array) $metadata['image_meta'],
			[
				'camera'            => 'SecretCam',
				'credit'            => 'Jane Private',
				'copyright'         => 'Private Holder',
				'caption'           => 'Private caption',
				'title'             => 'Private title',
				'created_timestamp' => '1557126489',
				'keywords'          => [ 'private' ],
				'orientation'       => '3',
			]
		);
		$metadata['filesize']                       = 1;
		$metadata['sizes']['thumbnail']['filesize'] = 1;
		wp_update_attachment_metadata( $id, $metadata );
		update_post_meta( $id, '_tsmlt_exif_meta', [ 'make' => 'Stored' ] );
		update_post_meta( $id, ExifScanner::PRIVACY_META_KEY, ExifScanner::privacy_value( str_repeat( 'a', 16 ), 'sensitive', [ 'gps' ] ) );

		$this->assertTrue( ExifStripper::instance()->strip_exif_from_attachment( $id )['success'] );

		$after = wp_get_attachment_metadata( $id );
		$paths = self::paths( $id );
		$this->assertSame( '3', $after['image_meta']['orientation'], 'Orientation kept.' );
		foreach ( [ 'camera', 'credit', 'copyright', 'caption', 'title' ] as $key ) {
			$this->assertSame( '', $after['image_meta'][ $key ], $key );
		}
		$this->assertSame( 0, $after['image_meta']['created_timestamp'] );
		$this->assertSame( [], $after['image_meta']['keywords'] );
		$this->assertStringNotContainsString( 'Private', wp_json_encode( $after['image_meta'] ) );
		$this->assertSame( wp_filesize( $paths['attached'] ), $after['filesize'] );
		$this->assertSame( wp_filesize( $paths['size:thumbnail'] ), $after['sizes']['thumbnail']['filesize'] );
		$this->assertSame( '', get_post_meta( $id, '_tsmlt_exif_meta', true ) );
		$this->assertSame( '', get_post_meta( $id, ExifScanner::PRIVACY_META_KEY, true ), 'Stale privacy result removed.' );
		$this->assertNotContains( 'filesize_mismatch', GetMediaHealth::inspect( $id )['issues'] );
	}

	/**
	 * A size that cannot be cleaned makes the result partial; a missing size
	 * does not.
	 */
	public function test_partial_and_missing() {
		$id    = $this->upload( 'partial.jpg' );
		$paths = self::paths( $id );
		file_put_contents( $paths['size:thumbnail'], substr( self::exif_jpeg(), 0, 60 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

		$result = ExifStripper::instance()->strip_exif_from_attachment( $id );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'partial', $result['status'] );
		$this->assertSame( 'failed', self::statuses( $result )['size:thumbnail'] );
		$this->assertSame( 'stripped', self::statuses( $result )['original'] );
		$this->assertStringNotContainsString( 'SecretCam', self::read( $paths['original'] ) );

		$missing = $this->upload( 'missing-size.jpg' );
		wp_delete_file( self::paths( $missing )['size:thumbnail'] );
		$result = ExifStripper::instance()->strip_exif_from_attachment( $missing );
		$this->assertTrue( $result['success'], $result['message'] );
		$this->assertSame( 'missing', self::statuses( $result )['size:thumbnail'] );
	}

	/**
	 * An attached image that cannot be cleaned fails: the file is unchanged,
	 * the stored data is not touched, the action does not fire, the lock is
	 * released.
	 */
	public function test_attached_failure() {
		$relative = '2026/11/broken-strip.jpg';
		$path     = trailingslashit( wp_upload_dir()['basedir'] ) . $relative;
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, substr( self::exif_jpeg(), 0, 60 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $path;
		$id                 = (int) self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'file'           => $relative,
			]
		);
		update_post_meta( $id, '_tsmlt_exif_meta', [ 'make' => 'Stored' ] );

		$result = ExifStripper::instance()->strip_exif_from_attachment( $id );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'failed', $result['status'] );
		$this->assertSame( substr( self::exif_jpeg(), 0, 60 ), self::read( $path ) );
		$this->assertSame( [ 'make' => 'Stored' ], get_post_meta( $id, '_tsmlt_exif_meta', true ) );
		$this->assertSame( [], $this->fired );
		$lock = ScanStartLock::instance()->acquire( ExifStripper::LOCK_PREFIX . $id );
		$this->assertIsString( $lock, 'The lock was released.' );
		ScanStartLock::instance()->release( $lock, ExifStripper::LOCK_PREFIX . $id );
	}

	/**
	 * After a run stopped between replacing the files and updating the
	 * data, the next run finds nothing to strip but brings the data up to
	 * date.
	 */
	public function test_recovery_after_interrupted_run() {
		$id       = $this->upload( 'recovery.jpg' );
		$metadata = wp_get_attachment_metadata( $id );

		$metadata['image_meta']['camera'] = 'SecretCam';
		$metadata['filesize']             = 1;
		wp_update_attachment_metadata( $id, $metadata );
		foreach ( self::paths( $id ) as $path ) {
			JpegMetadataStripper::strip_file( $path );
		}

		$result = ExifStripper::instance()->strip_exif_from_attachment( $id );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 'already_clean', $result['status'] );
		$after = wp_get_attachment_metadata( $id );
		$this->assertSame( '', $after['image_meta']['camera'] );
		$this->assertSame( wp_filesize( get_attached_file( $id ) ), $after['filesize'] );
		$this->assertCount( 1, $this->fired );
	}

	/**
	 * A running strip, compression or conversion of the same attachment
	 * refuses the strip and leaves the files alone.
	 */
	public function test_locks() {
		$id       = $this->upload( 'locked.jpg' );
		$original = self::read( self::paths( $id )['original'] );

		$lock = ScanStartLock::instance()->acquire( ExifStripper::LOCK_PREFIX . $id );
		$this->assertFalse( ExifStripper::instance()->strip_exif_from_attachment( $id )['success'] );
		ScanStartLock::instance()->release( $lock, ExifStripper::LOCK_PREFIX . $id );

		foreach ( [ AttachmentProcessor::LOCK_META_KEY, AttachmentConverter::LOCK_META_KEY ] as $key ) {
			update_post_meta( $id, $key, time() );
			$this->assertFalse( ExifStripper::instance()->strip_exif_from_attachment( $id )['success'], $key );
			delete_post_meta( $id, $key );
		}
		$this->assertSame( $original, self::read( self::paths( $id )['original'] ), 'Nothing changed while locked.' );

		$this->assertTrue( ExifStripper::instance()->strip_exif_from_attachment( $id )['success'] );
		$again = ScanStartLock::instance()->acquire( ExifStripper::LOCK_PREFIX . $id );
		$this->assertIsString( $again, 'Released after success.' );
		ScanStartLock::instance()->release( $again, ExifStripper::LOCK_PREFIX . $id );
	}

	/**
	 * Compression backups and WebP/AVIF copies are reported and left
	 * unchanged; no backup of the original is kept; the result holds no
	 * path, file name or EXIF value.
	 */
	public function test_other_copies_and_privacy() {
		$id       = $this->upload( 'copies.jpg' );
		$file     = get_attached_file( $id );
		$relative = (string) BackupManager::instance()->to_relative_path( $file );
		$backup   = trailingslashit( wp_upload_dir()['basedir'] ) . BackupManager::BACKUP_DIRNAME . '/' . $relative;
		wp_mkdir_p( dirname( $backup ) );
		file_put_contents( $backup, self::exif_jpeg() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $backup;
		$webp               = $file . '.webp';
		file_put_contents( $webp, 'RIFF-WEBP-SecretCam' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $webp;
		ConversionMetadata::instance()->save(
			$id,
			[
				'status'  => 'completed',
				'formats' => [
					'webp' => [
						'status' => 'completed',
						'file'   => wp_basename( $webp ),
					],
				],
			]
		);

		$result = ExifStripper::instance()->strip_exif_from_attachment( $id );

		$this->assertSame(
			[
				'compression_backup' => true,
				'converted_formats'  => [ 'webp' ],
			],
			$result['untouched_copies']
		);
		$this->assertSame( self::exif_jpeg(), self::read( $backup ), 'Compression backup not modified.' );
		$this->assertSame( 'RIFF-WEBP-SecretCam', self::read( $webp ), 'WebP copy not modified.' );
		$folder = (array) glob( dirname( $file ) . '/*' );
		$this->assertSame( [], preg_grep( '/\.(backup|tmp)$/', $folder ), 'No backup or temporary file kept.' );

		$json = wp_json_encode( $result );
		foreach ( [ wp_upload_dir()['basedir'], wp_basename( $file ), pathinfo( $file, PATHINFO_FILENAME ), '.jpg', 'SecretCam', 'Jane Private' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, $needle );
		}
	}
}
