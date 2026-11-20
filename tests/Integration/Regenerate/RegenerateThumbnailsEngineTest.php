<?php
/**
 * Thumbnail regeneration engine: ID queue, job lifecycle, locks in both
 * directions, scaled and rotated originals, disabled sizes, failures,
 * temporary files and per-image permissions.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\Regenerate\RegenerateLock;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';

/**
 * RegenerateThumbnailsEngineTest
 */
class RegenerateThumbnailsEngineTest extends WP_UnitTestCase {

	/**
	 * Attachments to delete.
	 *
	 * @var int[]
	 */
	private $ids = [];

	/**
	 * Extra files to delete.
	 *
	 * @var string[]
	 */
	private $files = [];

	/**
	 * Attachment IDs whose edit_post is denied.
	 *
	 * @var int[]
	 */
	private $denied = [];

	/**
	 * Administrator; no job.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( RegenerateThumbnails::STATE_OPTION );
		delete_option( 'tsmlt_settings' );
		wp_clear_scheduled_hook( RegenerateThumbnails::TICK_HOOK );
		$this->denied = [];
		add_filter( 'map_meta_cap', [ $this, 'deny' ], 10, 4 );
	}

	/**
	 * Remove fixtures.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny' ], 10 );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		remove_filter( 'intermediate_image_sizes_advanced', [ $this, 'add_broken_size' ] );
		delete_option( RegenerateThumbnails::STATE_OPTION );
		wp_clear_scheduled_hook( RegenerateThumbnails::TICK_HOOK );
		foreach ( $this->ids as $id ) {
			$file = get_attached_file( $id );
			if ( $file ) {
				foreach ( (array) glob( dirname( $file ) . '/' . RegenerateThumbnails::TEMP_PREFIX . '*' ) as $temp ) {
					wp_delete_file( $temp );
				}
			}
			wp_delete_attachment( $id, true );
		}
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
		parent::tear_down();
	}

	/**
	 * Deny edit_post on listed attachments.
	 *
	 * @param string[] $caps    Caps.
	 * @param string   $cap     Meta cap.
	 * @param int      $user_id User.
	 * @param array    $args    Args.
	 *
	 * @return string[]
	 */
	public function deny( $caps, $cap, $user_id, $args ) {
		if ( 'edit_post' === $cap && in_array( (int) ( $args[0] ?? 0 ), $this->denied, true ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
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
	 * Add a size WordPress cannot make (no width or height).
	 *
	 * @param array $sizes Sizes.
	 *
	 * @return array
	 */
	public function add_broken_size( $sizes ) {
		$sizes['tsmlt_broken'] = [ 'crop' => false ];
		return $sizes;
	}

	/**
	 * Upload a JPEG (canola.jpg, 640x480, or given bytes).
	 *
	 * @param string $name  File name.
	 * @param string $bytes JPEG bytes, or '' for canola.jpg.
	 *
	 * @return int
	 */
	private function upload( string $name = 'regen.jpg', string $bytes = '' ): int {
		$temp = trailingslashit( get_temp_dir() ) . $name;
		file_put_contents( $temp, '' !== $bytes ? $bytes : file_get_contents( DIR_TESTDATA . '/images/canola.jpg' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test fixture.
		$this->files[] = $temp;
		$id            = (int) self::factory()->attachment->create_upload_object( $temp );
		$this->ids[]   = $id;
		return $id;
	}

	/**
	 * Path of a size's file.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $size Size name.
	 *
	 * @return string
	 */
	private static function size_path( int $id, string $size ): string {
		$meta = wp_get_attachment_metadata( $id );
		return dirname( get_attached_file( $id ) ) . '/' . $meta['sizes'][ $size ]['file'];
	}

	/**
	 * Temporary files left in an image's folder.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array
	 */
	private static function temps( int $id ): array {
		return (array) glob( dirname( get_attached_file( $id ) ) . '/' . RegenerateThumbnails::TEMP_PREFIX . '*' );
	}

	/**
	 * Regenerate one image now, as the current user.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array
	 */
	private static function regen( int $id ): array {
		return RegenerateThumbnails::instance()->regenerate_attachment( $id, get_current_user_id() );
	}

	/**
	 * One image and chosen images: an ID queue with a job ID.
	 */
	public function test_selected_job_queue() {
		$a     = $this->upload( 'sel-a.jpg' );
		$b     = $this->upload( 'sel-b.jpg' );
		$start = RegenerateThumbnails::instance()->start( [ $a, $b, $a ] );

		$this->assertIsArray( $start );
		$this->assertSame( 'running', $start['status'] );
		$this->assertSame( 'selected', $start['job_type'] );
		$this->assertMatchesRegularExpression( '/^tsmlt_regen_[a-f0-9]{16}$/', $start['job_id'] );
		$this->assertSame( 2, $start['total'] );
		$this->assertArrayNotHasKey( 'queue', $start, 'The queue is not returned.' );
		$this->assertSame( [ $a, $b ], RegenerateThumbnails::instance()->get_state()['queue'] );
		$this->assertNotFalse( wp_next_scheduled( RegenerateThumbnails::TICK_HOOK ) );

		$done = RegenerateThumbnails::instance()->run_batch();
		$this->assertSame( 'done', $done['status'] );
		$this->assertSame( 2, $done['offset'] );
		$this->assertSame( 2, $done['success_count'] );

		delete_option( RegenerateThumbnails::STATE_OPTION );
		$single = RegenerateThumbnails::instance()->start( [ $a ] );
		$this->assertSame( 1, $single['total'] );
		$this->assertWPError( RegenerateThumbnails::instance()->start( [] ) );
	}

	/**
	 * The library queue is fixed when the job starts.
	 */
	public function test_library_queue_is_stable() {
		$a     = $this->upload( 'lib-a.jpg' );
		$b     = $this->upload( 'lib-b.jpg' );
		$start = RegenerateThumbnails::instance()->start();
		$this->assertSame( 'library', $start['job_type'] );
		$this->assertSame( [ $a, $b ], RegenerateThumbnails::instance()->get_state()['queue'] );

		$c = $this->upload( 'lib-c.jpg' );
		wp_delete_attachment( $b, true );

		$done = RegenerateThumbnails::instance()->run_batch();
		$this->assertSame( 'done', $done['status'] );
		$this->assertSame( 2, $done['total'] );
		$this->assertSame( 2, $done['offset'] );
		$this->assertSame( 1, $done['success_count'] );
		$this->assertSame( 1, $done['skipped_count'] );
		$this->assertSame( [ $a ], array_column( $done['recent_done'], 'id' ) );
		$this->assertSame( 'not_found', $done['recent_errors'][0]['code'] );
		$this->assertNotContains( $c, array_column( $done['recent_done'], 'id' ), 'Media added later is not processed.' );
	}

	/**
	 * A running job is never restarted; a stalled one may be replaced.
	 */
	public function test_concurrent_start_refused_and_stale_job_replaced() {
		$id    = $this->upload( 'conc.jpg' );
		$first = RegenerateThumbnails::instance()->start( [ $id ] );

		$second = RegenerateThumbnails::instance()->start( [ $id ] );
		$this->assertWPError( $second );
		$this->assertSame( 'tsmlt_regenerate_running', $second->get_error_code() );
		$this->assertSame( $first['job_id'], RegenerateThumbnails::instance()->get_state()['job_id'] );

		// Stalled: no tick and no progress for longer than STALE_AFTER.
		wp_clear_scheduled_hook( RegenerateThumbnails::TICK_HOOK );
		$state               = RegenerateThumbnails::instance()->get_state();
		$state['updated_at'] = time() - RegenerateThumbnails::STALE_AFTER - 1;
		update_option( RegenerateThumbnails::STATE_OPTION, $state, false );
		$this->assertTrue( RegenerateThumbnails::instance()->get_progress()['stale'] );

		$third = RegenerateThumbnails::instance()->start( [ $id ] );
		$this->assertIsArray( $third );
		$this->assertNotSame( $first['job_id'], $third['job_id'] );
	}

	/**
	 * Cancel applies only to the matching, running job; nothing runs after.
	 */
	public function test_cancel() {
		$id    = $this->upload( 'cancel.jpg' );
		$start = RegenerateThumbnails::instance()->start( [ $id ] );

		$this->assertWPError( RegenerateThumbnails::instance()->cancel( 'tsmlt_regen_other' ) );
		$this->assertSame( 'running', RegenerateThumbnails::instance()->get_state()['status'] );

		$cancelled = RegenerateThumbnails::instance()->cancel( $start['job_id'] );
		$this->assertSame( 'cancelled', $cancelled['status'] );
		$this->assertSame( [], RegenerateThumbnails::instance()->get_state()['queue'] );
		$this->assertFalse( wp_next_scheduled( RegenerateThumbnails::TICK_HOOK ) );

		$after = RegenerateThumbnails::instance()->run_batch();
		$this->assertSame( 'cancelled', $after['status'] );
		$this->assertSame( 0, $after['offset'], 'Nothing is processed after cancel.' );
	}

	/**
	 * Regeneration refuses an image under a compression, conversion or EXIF
	 * lock, changes nothing and leaves that lock alone.
	 */
	public function test_refuses_images_locked_by_other_operations() {
		$id    = $this->upload( 'locks.jpg' );
		$thumb = self::size_path( $id, 'thumbnail' );
		$hash  = md5_file( $thumb );
		$meta  = wp_get_attachment_metadata( $id );

		foreach ( [ AttachmentProcessor::LOCK_META_KEY, AttachmentConverter::LOCK_META_KEY ] as $key ) {
			$taken = time();
			update_post_meta( $id, $key, $taken );
			$result = self::regen( $id );
			$this->assertSame( 'locked', $result['code'], $key );
			$this->assertSame( (string) $taken, (string) get_post_meta( $id, $key, true ), 'The other lock is untouched.' );
			delete_post_meta( $id, $key );
		}
		$lock = ScanStartLock::instance()->acquire( ExifStripper::LOCK_PREFIX . $id );
		$this->assertSame( 'locked', self::regen( $id )['code'] );
		$this->assertTrue( ScanStartLock::instance()->is_held( ExifStripper::LOCK_PREFIX . $id ) );
		ScanStartLock::instance()->release( $lock, ExifStripper::LOCK_PREFIX . $id );

		$this->assertSame( $hash, md5_file( $thumb ) );
		$this->assertSame( $meta, wp_get_attachment_metadata( $id ) );
		$this->assertFalse( RegenerateLock::is_held( $id ), 'The regeneration lock was released.' );
	}

	/**
	 * Compression, conversion and EXIF removal refuse an image being regenerated.
	 */
	public function test_other_operations_refuse_while_regenerating() {
		$id   = $this->upload( 'reverse.jpg' );
		$lock = RegenerateLock::acquire( $id );
		$this->assertIsString( $lock );
		$this->assertNull( RegenerateLock::acquire( $id ), 'Held once.' );
		$this->assertSame( 'locked', self::regen( $id )['code'], 'A second regeneration is refused.' );

		$compressed = AttachmentProcessor::instance()->process(
			$id,
			[
				'mode'                     => 'balanced',
				'quality'                  => 0,
				'use_custom_quality'       => false,
				'backup_originals'         => false,
				'compress_generated_sizes' => false,
			]
		);
		$this->assertWPError( $compressed );
		$this->assertSame( 'tsmlt_compression_operation_locked', $compressed->get_error_code() );
		$this->assertFalse( AttachmentProcessor::instance()->is_locked( $id ), 'The compression lock was released.' );

		$converted = AttachmentConverter::instance()->convert( $id, [ 'formats' => [ 'webp' ] ] );
		$this->assertWPError( $converted );
		$this->assertSame( 'tsmlt_conversion_operation_locked', $converted->get_error_code() );
		$this->assertFalse( AttachmentConverter::instance()->is_locked( $id ), 'The conversion lock was released.' );

		$stripped = ExifStripper::instance()->strip_exif_from_attachment( $id );
		$this->assertFalse( $stripped['success'] );
		$this->assertStringContainsString( 'regenerated', $stripped['message'] );

		RegenerateLock::release( $id, $lock );
		$this->assertFalse( RegenerateLock::is_held( $id ) );

		// An abandoned lock expires.
		update_post_meta( $id, RegenerateLock::META_KEY, ( time() - RegenerateLock::EXPIRY - 1 ) . ':old' );
		$this->assertFalse( RegenerateLock::is_held( $id ) );
	}

	/**
	 * Success: sizes rebuilt, lock released, no temporary files.
	 */
	public function test_regenerates_missing_size_and_cleans_up() {
		$id    = $this->upload( 'ok.jpg' );
		$thumb = self::size_path( $id, 'thumbnail' );
		wp_delete_file( $thumb );

		$result = self::regen( $id );

		$this->assertSame( 'regenerated', $result['status'], wp_json_encode( $result ) );
		$this->assertFileExists( $thumb );
		$meta = wp_get_attachment_metadata( $id );
		$this->assertSame( wp_basename( $thumb ), $meta['sizes']['thumbnail']['file'] );
		$this->assertSame( wp_filesize( $thumb ), $meta['sizes']['thumbnail']['filesize'] );
		$this->assertSame( [], self::temps( $id ) );
		$this->assertFalse( RegenerateLock::is_held( $id ) );
		$this->assertFalse( RegenerateThumbnails::is_regenerating() );
	}

	/**
	 * A size that cannot be made: nothing replaced, records unchanged, no
	 * temporary files, lock released.
	 */
	public function test_failure_preserves_files_and_metadata() {
		$id    = $this->upload( 'fail.jpg' );
		$thumb = self::size_path( $id, 'thumbnail' );
		$hash  = md5_file( $thumb );
		$meta  = wp_get_attachment_metadata( $id );
		add_filter( 'intermediate_image_sizes_advanced', [ $this, 'add_broken_size' ] );

		$result = self::regen( $id );

		$this->assertSame( 'failed', $result['status'] );
		$this->assertSame( 'generation_failed', $result['code'] );
		$this->assertSame( $hash, md5_file( $thumb ) );
		$this->assertSame( $meta, wp_get_attachment_metadata( $id ) );
		$this->assertSame( [], self::temps( $id ) );
		$this->assertFalse( RegenerateLock::is_held( $id ) );
	}

	/**
	 * A leftover temporary file older than TEMP_MAX_AGE is removed; a fresh
	 * one (possibly another request's) is not.
	 */
	public function test_stale_temporary_files_are_removed() {
		$id    = $this->upload( 'temp.jpg' );
		$dir   = dirname( get_attached_file( $id ) );
		$old   = $dir . '/' . RegenerateThumbnails::TEMP_PREFIX . 'oldleftover.jpg';
		$fresh = $dir . '/' . RegenerateThumbnails::TEMP_PREFIX . 'freshleftover.jpg';
		file_put_contents( $old, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		file_put_contents( $fresh, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->files[] = $fresh;
		touch( $old, time() - RegenerateThumbnails::TEMP_MAX_AGE - 10 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Test fixture: an old leftover.

		self::regen( $id );

		$this->assertFileDoesNotExist( $old );
		$this->assertFileExists( $fresh );
	}

	/**
	 * Scaled uploads: sizes come from the original; the original and its
	 * record are kept.
	 */
	public function test_scaled_upload_uses_original() {
		add_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		$id = $this->upload( 'big.jpg' );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		$meta     = wp_get_attachment_metadata( $id );
		$original = wp_get_original_image_path( $id );
		$this->assertNotEmpty( $meta['original_image'], 'Precondition: scaled upload.' );
		$this->assertStringContainsString( '-scaled', get_attached_file( $id ) );
		$attached_hash = md5_file( get_attached_file( $id ) );
		wp_delete_file( self::size_path( $id, 'medium' ) );

		$result = self::regen( $id );

		$this->assertSame( 'regenerated', $result['status'], wp_json_encode( $result ) );
		$after = wp_get_attachment_metadata( $id );
		$this->assertSame( $meta['original_image'], $after['original_image'] );
		$this->assertSame( $original, wp_get_original_image_path( $id ) );
		$this->assertFileExists( $original );
		$this->assertSame( $attached_hash, md5_file( get_attached_file( $id ) ), 'The scaled copy is unchanged.' );
		$stem = pathinfo( $original, PATHINFO_FILENAME );
		$this->assertStringStartsWith( $stem . '-', $after['sizes']['medium']['file'], 'Made from the original, named after it.' );
		$this->assertStringNotContainsString( '-scaled', $after['sizes']['medium']['file'] );
		$this->assertFileExists( self::size_path( $id, 'medium' ) );
	}

	/**
	 * A recorded original that is missing: refused, nothing changed.
	 */
	public function test_missing_original_is_refused() {
		add_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		$id = $this->upload( 'lost.jpg' );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		$meta  = wp_get_attachment_metadata( $id );
		$thumb = self::size_path( $id, 'thumbnail' );
		$hash  = md5_file( $thumb );
		wp_delete_file( wp_get_original_image_path( $id ) );

		$result = self::regen( $id );

		$this->assertSame( 'original_missing', $result['code'] );
		$this->assertSame( $meta, wp_get_attachment_metadata( $id ), 'The original record is never dropped.' );
		$this->assertSame( $hash, md5_file( $thumb ) );
	}

	/**
	 * Rotated uploads: sizes come from the original, turned upright.
	 */
	public function test_rotated_upload() {
		if ( ! function_exists( 'exif_read_data' ) ) {
			$this->markTestSkipped( 'The PHP EXIF extension is required.' );
		}
		$bytes = TsmltExifJpegFixture::build( DIR_TESTDATA . '/images/canola.jpg', [ [ 0x0112, TsmltExifJpegFixture::SHORT, 6 ] ], [], [] );
		$id    = $this->upload( 'turn.jpg', $bytes );
		$meta  = wp_get_attachment_metadata( $id );
		$this->assertNotEmpty( $meta['original_image'], 'Precondition: WordPress made a rotated copy.' );
		wp_delete_file( self::size_path( $id, 'medium' ) );

		$result = self::regen( $id );

		$this->assertSame( 'regenerated', $result['status'], wp_json_encode( $result ) );
		$after = wp_get_attachment_metadata( $id );
		$this->assertSame( $meta['original_image'], $after['original_image'] );
		$this->assertLessThan( $after['sizes']['medium']['height'], $after['sizes']['medium']['width'], 'Upright (portrait).' );
		$size = wp_getimagesize( self::size_path( $id, 'medium' ) );
		$this->assertSame( [ $after['sizes']['medium']['width'], $after['sizes']['medium']['height'] ], [ $size[0], $size[1] ] );
	}

	/**
	 * Disabled sizes are not regenerated or recorded, and their files stay.
	 * Sizes no longer registered keep their record and file.
	 */
	public function test_disabled_and_unregistered_size_files_are_kept() {
		$id     = $this->upload( 'disabled.jpg' );
		$medium = self::size_path( $id, 'medium' );
		$hash   = md5_file( $medium );
		$legacy = dirname( $medium ) . '/legacy-size-copy.jpg';
		copy( self::size_path( $id, 'thumbnail' ), $legacy );
		$this->files[]               = $legacy;
		$meta                        = wp_get_attachment_metadata( $id );
		$meta['sizes']['tsmlt_gone'] = [
			'file'      => wp_basename( $legacy ),
			'width'     => 150,
			'height'    => 150,
			'mime-type' => 'image/jpeg',
		];
		wp_update_attachment_metadata( $id, $meta );
		update_option( 'tsmlt_settings', [ 'deregistered_image_sizes' => [ 'medium' ] ] );

		$result = self::regen( $id );

		$this->assertSame( 'regenerated', $result['status'], wp_json_encode( $result ) );
		$after = wp_get_attachment_metadata( $id );
		$this->assertArrayNotHasKey( 'medium', $after['sizes'] );
		$this->assertFileExists( $medium );
		$this->assertSame( $hash, md5_file( $medium ), 'The disabled size file is untouched.' );
		$this->assertArrayHasKey( 'tsmlt_gone', $after['sizes'] );
		$this->assertFileExists( $legacy );
		$this->files[] = $medium;
	}

	/**
	 * A size whose dimensions changed: the new file is made, the old one removed.
	 */
	public function test_changed_dimensions_replace_old_file() {
		$id  = $this->upload( 'dims.jpg' );
		$old = self::size_path( $id, 'thumbnail' );
		update_option( 'thumbnail_size_w', 100 );
		update_option( 'thumbnail_size_h', 100 );

		$result = self::regen( $id );

		$this->assertSame( 'regenerated', $result['status'], wp_json_encode( $result ) );
		$this->assertSame( [ 'thumbnail' ], $result['deleted_sizes'] );
		$this->assertFileDoesNotExist( $old );
		$meta = wp_get_attachment_metadata( $id );
		$this->assertSame( 100, $meta['sizes']['thumbnail']['width'] );
		$this->assertFileExists( self::size_path( $id, 'thumbnail' ) );
	}

	/**
	 * Permissions, trashed and missing images are checked when each image is
	 * processed, for the user who started the job.
	 */
	public function test_processing_time_checks() {
		$allowed = $this->upload( 'allowed.jpg' );
		$denied  = $this->upload( 'denied.jpg' );
		$trashed = $this->upload( 'trashed.jpg' );
		$missing = $this->upload( 'missing.jpg' );
		$deleted = $this->upload( 'deleted.jpg' );
		$post    = (int) self::factory()->post->create();
		$thumb   = self::size_path( $denied, 'thumbnail' );
		$hash    = md5_file( $thumb );
		$started = RegenerateThumbnails::instance()->start( [ $allowed, $denied, $trashed, $missing, $deleted, $post ] );
		$this->assertSame( 6, $started['total'] );

		// After the start: permission revoked, trashed, file removed, deleted.
		$this->denied = [ $denied ];
		wp_trash_post( $trashed );
		wp_delete_file( get_attached_file( $missing ) );
		wp_delete_attachment( $deleted, true );
		wp_set_current_user( 0 ); // Background: no current user.

		$done = RegenerateThumbnails::instance()->run_batch();

		$codes = array_column( $done['recent_errors'], 'code', 'id' );
		$this->assertSame(
			[
				$denied  => 'forbidden',
				$trashed => 'trashed',
				$missing => 'file_missing',
				$deleted => 'not_found',
				$post    => 'not_found',
			],
			$codes
		);
		$this->assertSame( [ $allowed ], array_column( $done['recent_done'], 'id' ) );
		$this->assertSame( 'done', $done['status'] );
		$this->assertSame( $hash, md5_file( $thumb ) );
		$json = wp_json_encode( $done );
		$this->assertStringNotContainsString( wp_get_upload_dir()['basedir'], $json, 'No paths.' );
	}

	/**
	 * A job started by a user who later loses manage_options does nothing.
	 */
	public function test_job_owner_without_manage_options() {
		$id   = $this->upload( 'owner.jpg' );
		$user = wp_get_current_user();
		RegenerateThumbnails::instance()->start( [ $id ] );
		$user->set_role( 'editor' );

		$done = RegenerateThumbnails::instance()->run_batch();

		$this->assertSame( 'forbidden', $done['recent_errors'][0]['code'] );
	}

	/**
	 * The page-driven batch action still walks the library by position.
	 */
	public function test_page_driven_batch() {
		$a      = $this->upload( 'page-a.jpg' );
		$b      = $this->upload( 'page-b.jpg' );
		$result = RegenerateThumbnails::instance()->regenerate_batch( 0, 10 );

		$this->assertSame( 2, $result['total'] );
		$this->assertSame( 2, $result['processed'] );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( [ $a, $b ], array_column( $result['succeeded'], 'id' ) );
	}
}
