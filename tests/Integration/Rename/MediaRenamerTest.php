<?php
/**
 * The media rename engine: original and attached files, sizes rebuilt,
 * no overwrites, backups and copies moved, locks, content URLs, the bulk
 * job and the Rename page.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\BackupManager;
use TinySolutions\mlt\Modules\Compress\CompressionMetadata;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionCapabilities;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionMetadata;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\Regenerate\RegenerateLock;
use TinySolutions\mlt\Modules\Rename\MediaRenamer;
use TinySolutions\mlt\Modules\Rename\RenameJob;
use TinySolutions\mlt\Modules\Rename\RenameModule;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';

/**
 * MediaRenamerTest
 */
class MediaRenamerTest extends WP_UnitTestCase {

	/**
	 * Attachments to delete.
	 *
	 * @var int[]
	 */
	private $ids = [];

	/**
	 * Files to delete.
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
	 * Whether to break the content update queries.
	 *
	 * @var bool
	 */
	private $break_content = false;

	/**
	 * Administrator; no job.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( RenameJob::STATE_OPTION );
		delete_option( 'tsmlt_settings' );
		$this->denied        = [];
		$this->break_content = false;
		add_filter( 'map_meta_cap', [ $this, 'deny' ], 10, 4 );
		add_filter( 'query', [ $this, 'maybe_break' ] );
	}

	/**
	 * Remove fixtures.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny' ], 10 );
		remove_filter( 'query', [ $this, 'maybe_break' ] );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		remove_filter( 'intermediate_image_sizes_advanced', [ $this, 'add_broken_size' ] );
		delete_option( RenameJob::STATE_OPTION );
		wp_clear_scheduled_hook( RenameJob::TICK_HOOK );
		foreach ( $this->ids as $id ) {
			BackupManager::instance()->delete_attachment_backups( $id );
			$file = get_attached_file( $id );
			if ( $file ) {
				foreach ( (array) glob( dirname( $file ) . '/ren-*' ) as $leftover ) {
					$this->files[] = $leftover;
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
	 * Break the content replacement queries when asked.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function maybe_break( $query ) {
		if ( $this->break_content && false !== strpos( $query, '= REPLACE(' ) ) {
			return 'UPDATE tsmlt_no_such_table SET x = 1';
		}
		return $query;
	}

	/**
	 * A big-image threshold below the test image.
	 *
	 * @return int
	 */
	public function small_threshold() {
		return 400;
	}

	/**
	 * Add a size WordPress cannot make.
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
	 * Upload a JPEG named ren-{name}.jpg.
	 *
	 * @param string $name   Name.
	 * @param bool   $scaled Whether to scale it on upload.
	 * @param string $bytes  Bytes, or '' for canola.jpg.
	 *
	 * @return int
	 */
	private function upload( string $name, bool $scaled = false, string $bytes = '' ): int {
		$temp = trailingslashit( get_temp_dir() ) . 'ren-' . $name . '.jpg';
		file_put_contents( $temp, '' !== $bytes ? $bytes : file_get_contents( DIR_TESTDATA . '/images/canola.jpg' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test fixture.
		$this->files[] = $temp;
		if ( $scaled ) {
			add_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		}
		$id = (int) self::factory()->attachment->create_upload_object( $temp );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		$this->ids[] = $id;
		return $id;
	}

	/**
	 * Rename as the current user.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $name New name.
	 *
	 * @return array
	 */
	private static function rename( int $id, string $name ): array {
		return MediaRenamer::rename( $id, $name, get_current_user_id() );
	}

	/**
	 * Paths of an attachment's size files.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return string[]
	 */
	private static function size_paths( int $id ): array {
		$meta  = wp_get_attachment_metadata( $id );
		$dir   = dirname( get_attached_file( $id ) );
		$paths = [];
		foreach ( $meta['sizes'] as $name => $size ) {
			$paths[ $name ] = $dir . '/' . $size['file'];
		}
		return $paths;
	}

	/**
	 * Scaled upload: original and scaled copy renamed, link kept, sizes
	 * rebuilt under the new name from the original, old sizes deleted.
	 */
	public function test_scaled_upload_rename() {
		$id        = $this->upload( 'scaled', true );
		$old_file  = get_attached_file( $id );
		$old_orig  = wp_get_original_image_path( $id );
		$old_sizes = self::size_paths( $id );
		$this->assertStringContainsString( '-scaled', $old_file, 'Precondition.' );

		$result = self::rename( $id, 'ren-new name.png' );

		$this->assertSame( 'renamed', $result['status'], wp_json_encode( $result ) );
		$this->assertSame( 'regenerated', $result['sizes'] );
		$this->assertSame( 'ren-new-name-scaled.jpg', wp_basename( get_attached_file( $id ) ) );
		$meta = wp_get_attachment_metadata( $id );
		$this->assertSame( 'ren-new-name.jpg', $meta['original_image'] );
		$this->assertFileExists( wp_get_original_image_path( $id ) );
		$this->assertFileDoesNotExist( $old_file );
		$this->assertFileDoesNotExist( $old_orig );
		foreach ( self::size_paths( $id ) as $path ) {
			$this->assertFileExists( $path );
			$this->assertStringStartsWith( 'ren-new-name-', wp_basename( $path ) );
		}
		foreach ( $old_sizes as $path ) {
			$this->assertFileDoesNotExist( $path, 'Old size removed after replacement.' );
		}
		$this->assertSame( 'ren-new-name', get_post( $id )->post_name );
	}

	/**
	 * Rotated upload: the -rotated suffix and the original link are kept.
	 */
	public function test_rotated_upload_rename() {
		if ( ! function_exists( 'exif_read_data' ) ) {
			$this->markTestSkipped( 'The PHP EXIF extension is required.' );
		}
		$bytes = TsmltExifJpegFixture::build( DIR_TESTDATA . '/images/canola.jpg', [ [ 0x0112, TsmltExifJpegFixture::SHORT, 6 ] ], [], [] );
		$id    = $this->upload( 'turn', false, $bytes );
		$this->assertStringContainsString( '-rotated', get_attached_file( $id ), 'Precondition.' );

		$result = self::rename( $id, 'ren-upright' );

		$this->assertSame( 'renamed', $result['status'], wp_json_encode( $result ) );
		$this->assertSame( 'ren-upright-rotated.jpg', wp_basename( get_attached_file( $id ) ) );
		$this->assertSame( 'ren-upright.jpg', wp_get_attachment_metadata( $id )['original_image'] );
		$this->assertFileExists( wp_get_original_image_path( $id ) );
	}

	/**
	 * When the sizes cannot be rebuilt, the files are renamed but the old
	 * sizes and their records stay, all pointing at files that exist.
	 */
	public function test_regeneration_failure_keeps_old_sizes() {
		$id        = $this->upload( 'keep' );
		$old_sizes = self::size_paths( $id );
		add_filter( 'intermediate_image_sizes_advanced', [ $this, 'add_broken_size' ] );

		$result = self::rename( $id, 'ren-kept' );

		$this->assertSame( 'renamed', $result['status'] );
		$this->assertSame( 'kept', $result['sizes'] );
		$this->assertSame( 'generation_failed', $result['sizes_code'] );
		$this->assertSame( $old_sizes, self::size_paths( $id ), 'Old size records kept.' );
		foreach ( self::size_paths( $id ) as $path ) {
			$this->assertFileExists( $path );
		}
		$this->assertSame( [], (array) glob( dirname( get_attached_file( $id ) ) . '/.tsmlt-regen-*' ) );
	}

	/**
	 * A taken name is never overwritten: a numbered name is used.
	 */
	public function test_no_overwrite() {
		$other      = $this->upload( 'taken' );
		$other_file = get_attached_file( $other );
		$hash       = md5_file( $other_file );
		$id         = $this->upload( 'mover' );

		$result = self::rename( $id, wp_basename( $other_file ) );

		$this->assertSame( 'renamed', $result['status'] );
		$this->assertNotSame( wp_basename( $other_file ), $result['file'] );
		$this->assertStringStartsWith( pathinfo( $other_file, PATHINFO_FILENAME ) . '-', $result['file'] );
		$this->assertSame( $hash, md5_file( $other_file ), 'The other attachment\'s file is untouched.' );
	}

	/**
	 * Compression backups of the renamed file move; backups of the rebuilt
	 * sizes are removed and the sizes count as not compressed.
	 */
	public function test_compression_backups() {
		$id     = $this->upload( 'backup' );
		$file   = get_attached_file( $id );
		$sizes  = self::size_paths( $id );
		$backup = BackupManager::instance();
		$backup->backup_file( $file );
		$backup->backup_file( $sizes['thumbnail'] );
		CompressionMetadata::instance()->save(
			$id,
			[
				'status'     => 'completed',
				'original'   => [
					'size' => 1000,
					'file' => _wp_relative_upload_path( $file ),
				],
				'compressed' => [ 'size' => 600 ],
				'sizes'      => [
					'full'      => [
						'before' => 800,
						'after'  => 500,
					],
					'thumbnail' => [
						'before' => 200,
						'after'  => 100,
					],
				],
			]
		);

		$result = self::rename( $id, 'ren-compressed' );

		$this->assertSame( 'renamed', $result['status'] );
		$new_rel = _wp_relative_upload_path( get_attached_file( $id ) );
		$this->assertTrue( $backup->backup_exists( $new_rel ), 'Backup moved with the file.' );
		$this->assertFalse( $backup->backup_exists( _wp_relative_upload_path( $file ) ) );
		$this->assertFalse( $backup->backup_exists( _wp_relative_upload_path( $sizes['thumbnail'] ) ), 'Old size backup removed.' );
		$data = CompressionMetadata::instance()->get( $id );
		$this->assertSame( [ 'full' ], array_keys( $data['sizes'] ), 'Rebuilt sizes count as not compressed.' );
		$this->assertSame( $new_rel, $data['original']['file'] );
		$this->assertSame( 1, $result['backups']['moved'] );
	}

	/**
	 * The attachment's own WebP copies are renamed; a recorded file that is
	 * not its own is left untouched.
	 */
	public function test_converted_copies() {
		if ( ! ConversionCapabilities::instance()->supports_format( 'webp' ) ) {
			$this->markTestSkipped( 'No WebP support.' );
		}
		$id = $this->upload( 'conv' );
		AttachmentConverter::instance()->convert(
			$id,
			[
				'formats'         => [ 'webp' ],
				'quality'         => [ 'webp' => 80 ],
				'generated_sizes' => true,
			]
		);
		$old_copy = dirname( get_attached_file( $id ) ) . '/' . pathinfo( get_attached_file( $id ), PATHINFO_FILENAME ) . '.webp';
		$this->assertFileExists( $old_copy, 'Precondition.' );

		$result = self::rename( $id, 'ren-converted' );

		$this->assertSame( 'renamed', $result['status'] );
		$new_copy = dirname( get_attached_file( $id ) ) . '/ren-converted.webp';
		$this->assertFileExists( $new_copy );
		$this->assertFileDoesNotExist( $old_copy );
		$data = ConversionMetadata::instance()->get( $id );
		$this->assertSame( _wp_relative_upload_path( $new_copy ), $data['formats']['webp']['file'] );
		foreach ( $data['formats']['webp']['sizes'] as $size ) {
			if ( ! empty( $size['file'] ) ) {
				$this->assertStringStartsWith( 'ren-converted-', wp_basename( $size['file'] ) );
			}
		}
		$this->assertGreaterThan( 0, $result['copies']['renamed'] );

		// A record naming another attachment's file: never moved.
		$other                           = $this->upload( 'notmine' );
		$other_file                      = get_attached_file( $other );
		$data['formats']['webp']['file'] = _wp_relative_upload_path( $other_file );
		ConversionMetadata::instance()->save( $id, $data );
		self::rename( $id, 'ren-converted-again' );
		$this->assertFileExists( $other_file );
	}

	/**
	 * Another operation's lock refuses the rename and changes nothing.
	 */
	public function test_locks() {
		$id   = $this->upload( 'lock' );
		$file = get_attached_file( $id );
		foreach ( [ AttachmentProcessor::LOCK_META_KEY, AttachmentConverter::LOCK_META_KEY ] as $key ) {
			update_post_meta( $id, $key, time() );
			$this->assertSame( 'locked', self::rename( $id, 'ren-locked' )['code'], $key );
			delete_post_meta( $id, $key );
		}
		$exif = ScanStartLock::instance()->acquire( ExifStripper::LOCK_PREFIX . $id );
		$this->assertSame( 'locked', self::rename( $id, 'ren-locked' )['code'] );
		ScanStartLock::instance()->release( $exif, ExifStripper::LOCK_PREFIX . $id );
		$regen = RegenerateLock::acquire( $id );
		$this->assertSame( 'locked', self::rename( $id, 'ren-locked' )['code'] );
		RegenerateLock::release( $id, $regen );

		$this->assertSame( $file, get_attached_file( $id ), 'Nothing renamed.' );
		$this->assertFalse( RegenerateLock::is_held( $id ), 'Lock released.' );
		$this->assertSame( 'renamed', self::rename( $id, 'ren-unlocked' )['status'] );
		$this->assertFalse( RegenerateLock::is_held( $id ) );
	}

	/**
	 * Missing, trashed, forbidden and invalid names are refused.
	 */
	public function test_refusals() {
		$this->assertSame( 'not_found', self::rename( 999999, 'x' )['code'] );
		$trashed = $this->upload( 'trash' );
		wp_trash_post( $trashed );
		$this->assertSame( 'not_found', self::rename( $trashed, 'ren-x' )['code'] );
		$denied       = $this->upload( 'denied' );
		$this->denied = [ $denied ];
		$this->assertSame( 'forbidden', self::rename( $denied, 'ren-x' )['code'] );
		$this->denied = [];
		$this->assertSame( 'invalid_name', self::rename( $denied, '...' )['code'] );
		$this->assertSame( 'unchanged', self::rename( $denied, pathinfo( get_attached_file( $denied ), PATHINFO_FILENAME ) )['status'] );
	}

	/**
	 * Image URLs in posts and products are updated, several references at
	 * once, and the posts are reported.
	 */
	public function test_content_urls() {
		$id      = $this->upload( 'content' );
		$main    = wp_get_attachment_url( $id );
		$thumb   = wp_get_attachment_image_url( $id, 'thumbnail' );
		$post    = (int) self::factory()->post->create( [ 'post_content' => '<img src="' . $main . '"> and <img src="' . $thumb . '"> and again ' . $main ] );
		$product = (int) self::factory()->post->create(
			[
				'post_type'    => 'product',
				'post_content' => '<img src="' . $main . '">',
			]
		);

		$result = self::rename( $id, 'ren-content-new' );

		$new_main  = wp_get_attachment_url( $id );
		$new_thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
		$content   = get_post( $post )->post_content;
		$this->assertSame( 2, substr_count( $content, $new_main ) );
		$this->assertStringContainsString( $new_thumb, $content );
		$this->assertStringNotContainsString( $main, $content );
		$this->assertStringContainsString( $new_main, get_post( $product )->post_content );
		$this->assertEqualsCanonicalizing( [ $post, $product ], $result['content']['posts'] );
		$this->assertSame( 0, $result['content']['errors'] );
	}

	/**
	 * A failed content update is reported even though the file was renamed.
	 */
	public function test_content_failure_is_reported() {
		$id   = $this->upload( 'fail' );
		$main = wp_get_attachment_url( $id );
		$post = (int) self::factory()->post->create( [ 'post_content' => '<img src="' . $main . '">' ] );
		global $wpdb;
		$this->break_content = true;
		$suppressed          = $wpdb->suppress_errors( true );

		$result = self::rename( $id, 'ren-fail-new' );

		$wpdb->suppress_errors( $suppressed );
		$this->break_content = false;
		$this->assertSame( 'renamed', $result['status'] );
		$this->assertGreaterThan( 0, $result['content']['errors'] );
		$this->assertStringContainsString( $main, get_post( $post )->post_content, 'The post still has the old URL.' );
	}

	/**
	 * Bulk job: per-item results, progress, refusal of a second job, cancel.
	 */
	public function test_bulk_job() {
		$a       = $this->upload( 'bulk-a' );
		$b       = $this->upload( 'bulk-b' );
		$trashed = $this->upload( 'bulk-t' );
		wp_trash_post( $trashed );
		$job = RenameJob::instance();

		$this->assertWPError( $job->start( [] ) );
		$this->assertWPError( $job->start( array_fill_keys( range( 1, 21 ), 'x' ) ) );

		$started = $job->start(
			[
				$a       => 'ren-bulk-one',
				$trashed => 'ren-bulk-two',
				$b       => 'ren-bulk-three',
			]
		);
		$this->assertSame( 'running', $started['status'] );
		$this->assertSame( 3, $started['total'] );
		$this->assertArrayNotHasKey( 'queue', $started );
		$this->assertWPError( $job->start( [ $a => 'ren-other' ] ), 'One job at a time.' );

		$done = $job->run_batch();

		$this->assertSame( 'done', $done['status'] );
		$this->assertSame( 3, $done['processed'] );
		$this->assertSame( [ 'renamed', 'refused', 'renamed' ], array_column( $done['results'], 'status' ) );
		$this->assertSame( 'not_found', $done['results'][1]['code'] );
		$this->assertSame( 'ren-bulk-one.jpg', wp_basename( get_attached_file( $a ) ) );

		delete_option( RenameJob::STATE_OPTION );
		$started = $job->start( [ $a => 'ren-bulk-cancel' ] );
		$this->assertWPError( $job->cancel( 'tsmlt_rename_other' ) );
		$this->assertSame( 'cancelled', $job->cancel( $started['job_id'] )['status'] );
		$this->assertSame( 0, $job->run_batch()['processed'] );
		$this->assertSame( 'ren-bulk-one.jpg', wp_basename( get_attached_file( $a ) ), 'Nothing renamed after cancel.' );
	}

	/**
	 * The Rename page still renames, and an unresolved strategy keyword is
	 * never used as a file name.
	 */
	public function test_admin_rename_and_keywords() {
		$id     = $this->upload( 'admin' );
		$result = RenameModule::instance()->update_single_media(
			[
				'ID'      => $id,
				'newname' => 'ren-admin-new',
			]
		);
		$this->assertTrue( $result['updated'], wp_json_encode( $result ) );
		$this->assertSame( 'ren-admin-new.jpg', wp_basename( get_attached_file( $id ) ) );

		foreach ( RenameModule::STRATEGY_KEYWORDS as $keyword ) {
			$refused = RenameModule::instance()->update_single_media(
				[
					'ID'      => $id,
					'newname' => $keyword,
				]
			);
			$this->assertFalse( $refused['updated'], $keyword );
			$this->assertSame( 'ren-admin-new.jpg', wp_basename( get_attached_file( $id ) ), $keyword );
		}
	}
}
