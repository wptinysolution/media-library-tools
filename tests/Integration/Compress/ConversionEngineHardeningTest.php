<?php
/**
 * Conversion engine hardening: converted copies never replace or delete a
 * file that is not the attachment's own copy, conversion respects the other
 * operations' locks, trashed attachments are refused, regenerating copies
 * is all or nothing, and library selection is unchanged.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\CompressionJob;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionCapabilities;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionManager;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionMetadata;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionSettings;
use TinySolutions\mlt\Modules\Compress\Conversion\ConvertModule;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\Regenerate\RegenerateLock;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;

/**
 * ConversionEngineHardeningTest
 */
class ConversionEngineHardeningTest extends WP_UnitTestCase {

	/**
	 * Folder of this test's files.
	 *
	 * @var string
	 */
	private $dir = '';

	/**
	 * Attachments created.
	 *
	 * @var int[]
	 */
	private $ids = [];

	/**
	 * Administrator, a fresh folder, no job.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! ConversionCapabilities::instance()->supports_format( 'webp' ) ) {
			$this->markTestSkipped( 'This server cannot write WebP.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->dir = trailingslashit( wp_get_upload_dir()['basedir'] ) . 'tsmlt-conv-' . wp_generate_password( 8, false, false );
		wp_mkdir_p( $this->dir );
		delete_option( CompressionJob::STATE_OPTION );
		wp_clear_scheduled_hook( CompressionJob::TICK_HOOK );
	}

	/**
	 * Remove the folder, attachments and job.
	 */
	public function tear_down() {
		foreach ( $this->ids as $id ) {
			delete_option( ExifStripper::LOCK_PREFIX . $id );
		}
		if ( is_dir( $this->dir ) ) {
			chmod( $this->dir, 0755 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
			foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
				wp_delete_file( $file );
			}
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture.
		}
		delete_option( CompressionJob::STATE_OPTION );
		wp_clear_scheduled_hook( CompressionJob::TICK_HOOK );
		parent::tear_down();
	}

	/**
	 * Put a file in the test folder.
	 *
	 * @param string $name   File name.
	 * @param string $format Image format to write it as: jpeg, png or webp.
	 * @param int    $width  Width.
	 *
	 * @return string Absolute path.
	 */
	private function file( string $name, string $format = 'jpeg', int $width = 0 ): string {
		$path   = $this->dir . '/' . $name;
		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/canola.jpg' );
		if ( $width > 0 ) {
			$editor->resize( $width, $width, true );
		}
		$editor->save( $path, 'image/' . $format );
		return $path;
	}

	/**
	 * An attachment for a file in the test folder, with generated sizes.
	 *
	 * @param string $path      Absolute path.
	 * @param string $mime      MIME type.
	 * @param bool   $with_meta Whether to generate sizes.
	 *
	 * @return int
	 */
	private function attach( string $path, string $mime = 'image/jpeg', bool $with_meta = true ): int {
		$id          = (int) self::factory()->attachment->create_object(
			[
				'file'           => $path,
				'post_mime_type' => $mime,
			]
		);
		$this->ids[] = $id;
		if ( $with_meta ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
		}
		return $id;
	}

	/**
	 * WebP run settings.
	 *
	 * @param bool $sizes Whether to convert generated sizes.
	 *
	 * @return array
	 */
	private static function webp( bool $sizes = false ): array {
		return [
			'formats'         => [ 'webp' ],
			'quality'         => [ 'webp' => 80 ],
			'generated_sizes' => $sizes,
		];
	}

	/**
	 * Hash of a file.
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	private static function hash( string $path ): string {
		clearstatcache();
		return (string) md5_file( $path );
	}

	/**
	 * Temporary files left in the folder.
	 *
	 * @return array
	 */
	private function temps(): array {
		return (array) glob( $this->dir . '/*.tmp' );
	}

	/**
	 * A file at the target name that is not in the library is not replaced.
	 */
	public function test_unregistered_file_is_never_overwritten() {
		$a     = $this->attach( $this->file( 'stray.jpg' ) );
		$stray = $this->file( 'stray.webp', 'webp', 50 );
		$hash  = self::hash( $stray );

		$result = AttachmentConverter::instance()->convert( $a, self::webp() );

		$this->assertWPError( $result );
		$this->assertSame( 'tsmlt_conversion_name_conflict', $result->get_error_code() );
		$this->assertSame( $hash, self::hash( $stray ) );
		$this->assertSame( 'tsmlt_conversion_name_conflict', ConversionMetadata::instance()->get( $a )['formats']['webp']['reason'] );
		$this->assertSame( [], $this->temps() );
		$this->assertFalse( AttachmentConverter::instance()->is_locked( $a ), 'Lock released after failure.' );
	}

	/**
	 * Another attachment's main file at the target name is preserved, and
	 * deleting copies never deletes it, even when a record points at it.
	 */
	public function test_other_attachments_main_file_is_preserved() {
		$b_file = $this->file( 'photo.webp', 'webp', 60 );
		$b      = $this->attach( $b_file, 'image/webp' );
		$a      = $this->attach( $this->file( 'photo.jpg' ) );
		$hash   = self::hash( $b_file );

		$this->assertSame( 'tsmlt_conversion_name_conflict', AttachmentConverter::instance()->convert( $a, self::webp() )->get_error_code() );
		$this->assertSame( $hash, self::hash( $b_file ) );

		// A record (wrong or tampered) naming B's file as A's copy.
		$relative = _wp_relative_upload_path( $b_file );
		ConversionMetadata::instance()->save(
			$a,
			[
				'status'  => 'completed',
				'formats' => [
					'webp' => [
						'status' => 'completed',
						'file'   => $relative,
						'sizes'  => [],
					],
				],
			]
		);
		$deleted = ConversionManager::instance()->delete_conversions( $a );

		$this->assertSame( 0, $deleted['deleted'] );
		$this->assertSame( 1, $deleted['kept'] );
		$this->assertFileExists( $b_file );
		$this->assertSame( $hash, self::hash( $b_file ) );
		$this->assertSame( [], ConversionMetadata::instance()->get( $a ), 'The record is dropped.' );
	}

	/**
	 * JPEG and PNG with one base name: the first owns the copy, the second is
	 * refused and the first copy is untouched.
	 */
	public function test_same_basename_jpeg_and_png() {
		$jpeg = $this->attach( $this->file( 'twin.jpg' ) );
		$png  = $this->attach( $this->file( 'twin.png', 'png' ), 'image/png' );

		$first = AttachmentConverter::instance()->convert( $jpeg, self::webp() );
		$this->assertIsArray( $first, wp_json_encode( $first ) );
		$copy = $this->dir . '/twin.webp';
		$hash = self::hash( $copy );

		$second = AttachmentConverter::instance()->convert( $png, self::webp() );

		$this->assertSame( 'tsmlt_conversion_name_conflict', $second->get_error_code() );
		$this->assertSame( $hash, self::hash( $copy ) );
		$this->assertSame( _wp_relative_upload_path( $copy ), ConversionMetadata::instance()->get( $jpeg )['formats']['webp']['file'] );

		// Deleting the PNG's (refused) copies does not touch the JPEG's copy.
		$this->assertSame( 0, ConversionManager::instance()->delete_conversions( $png )['deleted'] );
		$this->assertFileExists( $copy );
		$this->assertSame( $hash, self::hash( $copy ) );
	}

	/**
	 * A generated size whose copy name is another attachment's file: that size
	 * is refused, the rest is converted, the file is preserved.
	 */
	public function test_generated_size_collision() {
		$a     = $this->attach( $this->file( 'sized.jpg' ) );
		$thumb = wp_get_attachment_metadata( $a )['sizes']['thumbnail']['file'];
		$other = $this->file( pathinfo( $thumb, PATHINFO_FILENAME ) . '.webp', 'webp', 40 );
		$this->attach( $other, 'image/webp', false );
		$hash = self::hash( $other );

		$result = AttachmentConverter::instance()->convert( $a, self::webp( true ) );

		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$entry = ConversionMetadata::instance()->get( $a )['formats']['webp'];
		$this->assertSame( 'completed', $entry['status'] );
		$this->assertSame( 'failed', $entry['sizes']['thumbnail']['status'] );
		$this->assertSame( 'tsmlt_conversion_name_conflict', $entry['sizes']['thumbnail']['reason'] );
		$this->assertSame( 'completed', $entry['sizes']['medium']['status'] );
		$this->assertSame( $hash, self::hash( $other ) );
		$this->assertSame( [], $this->temps() );
	}

	/**
	 * The attachment's own copy is replaced on a new conversion.
	 */
	public function test_own_copy_is_replaced() {
		$a = $this->attach( $this->file( 'own.jpg' ) );
		AttachmentConverter::instance()->convert( $a, self::webp() );
		$copy = $this->dir . '/own.webp';
		$hash = self::hash( $copy );

		$again = AttachmentConverter::instance()->convert(
			$a,
			[
				'formats'         => [ 'webp' ],
				'quality'         => [ 'webp' => 30 ],
				'generated_sizes' => false,
			]
		);

		$this->assertIsArray( $again, wp_json_encode( $again ) );
		$this->assertNotSame( $hash, self::hash( $copy ), 'Replaced with the new copy.' );
		$this->assertSame( 30, ConversionMetadata::instance()->get( $a )['formats']['webp']['quality'] );
		$this->assertSame( [], $this->temps() );
	}

	/**
	 * Deleting owned copies still works.
	 */
	public function test_delete_owned_copies() {
		$a = $this->attach( $this->file( 'del.jpg' ) );
		AttachmentConverter::instance()->convert( $a, self::webp( true ) );
		$files = (array) glob( $this->dir . '/del*.webp' );
		$this->assertGreaterThan( 1, count( $files ) );

		$result = ConversionManager::instance()->delete_conversions( $a );

		$this->assertSame( count( $files ), $result['deleted'] );
		$this->assertSame( 0, $result['kept'] );
		$this->assertSame( [], (array) glob( $this->dir . '/del*.webp' ) );
		$this->assertFileExists( $this->dir . '/del.jpg' );
	}

	/**
	 * An unwritable folder: a clean failure, nothing written or left behind.
	 */
	public function test_filesystem_failure() {
		$a = $this->attach( $this->file( 'ro.jpg' ) );
		chmod( $this->dir, 0555 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		if ( wp_is_writable( $this->dir ) ) {
			$this->markTestSkipped( 'The folder stays writable (running as root?).' );
		}

		$result = AttachmentConverter::instance()->convert( $a, self::webp() );

		chmod( $this->dir, 0755 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		$this->assertWPError( $result );
		$this->assertFileDoesNotExist( $this->dir . '/ro.webp' );
		$this->assertSame( [], $this->temps() );
		$this->assertFalse( AttachmentConverter::instance()->is_locked( $a ) );
	}

	/**
	 * Compression, EXIF-removal and regeneration locks block conversion;
	 * the other operations still refuse a converting image.
	 */
	public function test_locks_both_ways() {
		$a = $this->attach( $this->file( 'lock.jpg' ) );

		update_post_meta( $a, AttachmentProcessor::LOCK_META_KEY, time() );
		$this->assertSame( 'tsmlt_conversion_operation_locked', AttachmentConverter::instance()->convert( $a, self::webp() )->get_error_code() );
		delete_post_meta( $a, AttachmentProcessor::LOCK_META_KEY );

		$exif = ScanStartLock::instance()->acquire( ExifStripper::LOCK_PREFIX . $a );
		$this->assertSame( 'tsmlt_conversion_operation_locked', AttachmentConverter::instance()->convert( $a, self::webp() )->get_error_code() );
		ScanStartLock::instance()->release( $exif, ExifStripper::LOCK_PREFIX . $a );

		$regen = RegenerateLock::acquire( $a );
		$this->assertSame( 'tsmlt_conversion_operation_locked', AttachmentConverter::instance()->convert( $a, self::webp() )->get_error_code() );
		RegenerateLock::release( $a, $regen );

		$this->assertFalse( AttachmentConverter::instance()->is_locked( $a ), 'Released after each refusal.' );
		$this->assertFileDoesNotExist( $this->dir . '/lock.webp' );

		// Reverse: a converting image is refused by compression and EXIF removal.
		update_post_meta( $a, AttachmentConverter::LOCK_META_KEY, time() );
		$this->assertTrue( AttachmentProcessor::instance()->is_busy_elsewhere( $a ) );
		$this->assertFalse( ExifStripper::instance()->strip_exif_from_attachment( $a )['success'] );
		delete_post_meta( $a, AttachmentConverter::LOCK_META_KEY );

		$this->assertIsArray( AttachmentConverter::instance()->convert( $a, self::webp() ) );
		$this->assertFalse( AttachmentConverter::instance()->is_locked( $a ), 'Released after success.' );
	}

	/**
	 * Trashed, deleted and unsupported attachments are refused; a job skips
	 * an image trashed before its batch.
	 */
	public function test_attachment_validation() {
		$a       = $this->attach( $this->file( 'keep.jpg' ) );
		$trashed = $this->attach( $this->file( 'gone.jpg' ) );
		wp_trash_post( $trashed );
		$gif = (int) self::factory()->attachment->create_object(
			[
				'file'           => 'x.gif',
				'post_mime_type' => 'image/gif',
			]
		);

		$this->assertSame( 'tsmlt_conversion_not_found', AttachmentConverter::instance()->convert( $trashed, self::webp() )->get_error_code() );
		$this->assertSame( 'tsmlt_conversion_not_found', AttachmentConverter::instance()->convert( 999999, self::webp() )->get_error_code() );
		$this->assertSame( 'tsmlt_conversion_unsupported_source', AttachmentConverter::instance()->convert( $gif, self::webp() )->get_error_code() );
		$this->assertFileDoesNotExist( $this->dir . '/gone.webp' );

		// Job: trashed at start is not queued; trashed before the batch is refused.
		$later = $this->attach( $this->file( 'later.jpg' ) );
		$start = CompressionJob::instance()->start_conversion( [ $a, $trashed, $later ], [ 'formats' => [ 'webp' ] ] );
		$this->assertIsArray( $start, wp_json_encode( $start ) );
		$this->assertSame( [ $a, $later ], CompressionJob::instance()->get_state()['queue'] );
		wp_trash_post( $later );

		CompressionJob::instance()->run_batch( false );

		$state = CompressionJob::instance()->get_state();
		$this->assertSame( [ $later ], $state['failed_ids'] );
		$this->assertFileExists( $this->dir . '/keep.webp' );
		$this->assertFileDoesNotExist( $this->dir . '/later.webp' );
	}

	/**
	 * Regenerating copies: a failure keeps the old copies and records; a
	 * success replaces owned copies and removes owned copies not made again.
	 */
	public function test_regenerate_copies_is_all_or_nothing() {
		$a = $this->attach( $this->file( 'regen.jpg' ) );
		AttachmentConverter::instance()->convert( $a, self::webp( true ) );
		$copy   = $this->dir . '/regen.webp';
		$hash   = self::hash( $copy );
		$record = ConversionMetadata::instance()->get( $a );
		$sizes  = (array) glob( $this->dir . '/regen-*.webp' );
		$this->assertNotEmpty( $sizes );

		// Failure: one size file is no longer an image.
		$medium = $this->dir . '/' . wp_get_attachment_metadata( $a )['sizes']['medium']['file'];
		$backup = file_get_contents( $medium ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
		file_put_contents( $medium, 'not an image' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

		$failed = AttachmentConverter::instance()->regenerate_copies( $a, self::webp( true ) );

		$this->assertWPError( $failed );
		$this->assertSame( $hash, self::hash( $copy ), 'Old copy kept.' );
		$this->assertSame( $record, ConversionMetadata::instance()->get( $a ), 'Record unchanged.' );
		foreach ( $sizes as $size ) {
			$this->assertFileExists( $size );
		}
		$this->assertSame( [], $this->temps() );

		// Success without sizes: the copy is replaced, old owned size copies go.
		file_put_contents( $medium, $backup ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$done = ConversionManager::instance()->regenerate(
			$a,
			[
				'formats'         => [ 'webp' ],
				'generated_sizes' => false,
			]
		);

		$this->assertIsArray( $done, wp_json_encode( $done ) );
		$this->assertFileExists( $copy );
		$this->assertSame( [], (array) glob( $this->dir . '/regen-*.webp' ), 'Owned size copies not made again were removed.' );
		$entry = ConversionMetadata::instance()->get( $a )['formats']['webp'];
		$this->assertSame( _wp_relative_upload_path( $copy ), $entry['file'] );
		$this->assertSame( [], $entry['sizes'] );
	}

	/**
	 * Library figures and selection keep their rules: supported, in the
	 * library, not yet converted (unless asked), newest first, Free limit.
	 */
	public function test_library_selection() {
		$old       = $this->attach( $this->file( 'lib-old.jpg' ), 'image/jpeg', false );
		$png       = $this->attach( $this->file( 'lib.png', 'png' ), 'image/png', false );
		$converted = $this->attach( $this->file( 'lib-done.jpg' ), 'image/jpeg', false );
		ConversionMetadata::instance()->save( $converted, [ 'status' => 'completed' ] );
		$trashed = $this->attach( $this->file( 'lib-trash.jpg' ), 'image/jpeg', false );
		wp_trash_post( $trashed );
		self::factory()->attachment->create_object(
			[
				'file'           => 'lib.gif',
				'post_mime_type' => 'image/gif',
			]
		);

		$stats = ConvertModule::instance()->get_library_stats();
		$this->assertSame(
			[
				'total'     => 3,
				'converted' => 1,
				'remaining' => 2,
			],
			$stats
		);

		$started = ConvertModule::instance()->start_library_job( [ 'formats' => [ 'webp' ] ] );
		$this->assertIsArray( $started, wp_json_encode( $started ) );
		$this->assertSame( [ $png, $old ], CompressionJob::instance()->get_state()['queue'], 'Not converted, newest first.' );

		delete_option( CompressionJob::STATE_OPTION );
		ConvertModule::instance()->start_library_job(
			[
				'formats'           => [ 'webp' ],
				'include_converted' => true,
			]
		);
		$this->assertSame( [ $converted, $png, $old ], CompressionJob::instance()->get_state()['queue'] );
	}

	/**
	 * Free gating is unchanged: AVIF and custom quality need Pro.
	 */
	public function test_free_gating_unchanged() {
		$settings = ConversionSettings::instance()->resolve_run_settings(
			[
				'formats'         => [ 'webp', 'avif' ],
				'quality'         => [ 'webp' => 10 ],
				'generated_sizes' => true,
			]
		);
		$this->assertSame( [ 'webp' ], $settings['formats'] );
		$this->assertNotSame( 10, $settings['quality']['webp'] );
		$this->assertFalse( $settings['generated_sizes'] );
	}
}
