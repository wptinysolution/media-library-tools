<?php
/**
 * Compression refuses an image while a conversion or an EXIF removal is
 * running on it, without touching the file, a backup or the other
 * operation's lock, and keeps each lock's own expiry.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\BackupManager;
use TinySolutions\mlt\Modules\Compress\CompressionAccess;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;

/**
 * CompressionCrossLockTest
 */
class CompressionCrossLockTest extends WP_UnitTestCase {

	/**
	 * Uploaded attachment.
	 *
	 * @var int
	 */
	private $id = 0;

	/**
	 * Upload a JPEG to compress.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! CompressionAccess::instance()->is_compression_feature_available() ) {
			$this->markTestSkipped( 'No image compression library is available.' );
		}
		$this->id = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
	}

	/**
	 * Remove the attachment, its files, backups and any lock rows.
	 */
	public function tear_down() {
		if ( $this->id ) {
			BackupManager::instance()->delete_attachment_backups( $this->id );
			delete_option( ExifStripper::LOCK_PREFIX . $this->id );
			wp_delete_attachment( $this->id, true );
		}
		parent::tear_down();
	}

	/**
	 * Run settings with a backup of the original requested.
	 *
	 * @return array
	 */
	private static function settings(): array {
		return [
			'mode'                     => 'maximum',
			'quality'                  => 0,
			'use_custom_quality'       => false,
			'backup_originals'         => true,
			'compress_generated_sizes' => false,
		];
	}

	/**
	 * Hash of the attached file.
	 *
	 * @return string
	 */
	private function file_hash(): string {
		clearstatcache();
		return (string) md5_file( get_attached_file( $this->id ) );
	}

	/**
	 * Whether a compression backup of the attached file exists.
	 *
	 * @return bool
	 */
	private function backup_exists(): bool {
		$relative = BackupManager::instance()->to_relative_path( get_attached_file( $this->id ) );
		return ! is_wp_error( $relative ) && BackupManager::instance()->backup_exists( $relative );
	}

	/**
	 * Assert a refusal left the file, backups, stored results and the
	 * compression lock as they were.
	 *
	 * @param mixed  $result Result of process().
	 * @param string $before File hash before.
	 */
	private function assert_refused_untouched( $result, string $before ) {
		$this->assertWPError( $result );
		$this->assertSame( 'tsmlt_compression_operation_locked', $result->get_error_code() );
		$this->assertSame( $before, $this->file_hash(), 'The image file is unchanged.' );
		$this->assertFalse( $this->backup_exists(), 'No backup was made.' );
		$this->assertSame( '', get_post_meta( $this->id, AttachmentProcessor::LOCK_META_KEY, true ), 'The compression lock was released.' );
		$this->assertFalse( AttachmentProcessor::instance()->is_locked( $this->id ) );
	}

	/**
	 * Without a conflicting lock, compression runs and releases its lock.
	 */
	public function test_compresses_without_conflicting_lock() {
		$result = AttachmentProcessor::instance()->process( $this->id, self::settings() );

		$this->assertNotWPError( $result );
		$this->assertContains( $result['status'], [ 'completed', 'skipped' ], wp_json_encode( $result ) );
		$this->assertFalse( AttachmentProcessor::instance()->is_locked( $this->id ), 'The compression lock was released.' );
	}

	/**
	 * A running EXIF removal blocks compression; its lock stays as it was.
	 */
	public function test_refuses_while_exif_strip_lock_is_held() {
		$option = ExifStripper::LOCK_PREFIX . $this->id;
		$lock   = ScanStartLock::instance()->acquire( $option );
		$this->assertIsString( $lock, 'Precondition: the EXIF strip lock is taken.' );
		$before = $this->file_hash();

		$result = AttachmentProcessor::instance()->process( $this->id, self::settings() );

		$this->assert_refused_untouched( $result, $before );
		$this->assertTrue( ScanStartLock::instance()->is_held( $option ), 'The EXIF strip lock is still held.' );
		$this->assertNull( ScanStartLock::instance()->acquire( $option ), 'Nobody else can take it.' );
		ScanStartLock::instance()->release( $lock, $option );
		$this->assertFalse( ScanStartLock::instance()->is_held( $option ) );
	}

	/**
	 * A running conversion blocks compression; its lock stays as it was.
	 */
	public function test_refuses_while_conversion_lock_is_held() {
		$taken = time();
		update_post_meta( $this->id, AttachmentConverter::LOCK_META_KEY, $taken );
		$before = $this->file_hash();

		$result = AttachmentProcessor::instance()->process( $this->id, self::settings() );

		$this->assert_refused_untouched( $result, $before );
		$this->assertSame( (string) $taken, get_post_meta( $this->id, AttachmentConverter::LOCK_META_KEY, true ), 'The conversion lock is unchanged.' );
		$this->assertTrue( AttachmentConverter::instance()->is_locked( $this->id ) );
	}

	/**
	 * Another compression run's lock still gives the existing refusal and is
	 * left alone.
	 */
	public function test_existing_compression_lock_semantics_unchanged() {
		$taken = time();
		update_post_meta( $this->id, AttachmentProcessor::LOCK_META_KEY, $taken );
		$before = $this->file_hash();

		$result = AttachmentProcessor::instance()->process( $this->id, self::settings() );

		$this->assertWPError( $result );
		$this->assertSame( 'tsmlt_compression_locked', $result->get_error_code() );
		$this->assertSame( $before, $this->file_hash() );
		$this->assertSame( (string) $taken, get_post_meta( $this->id, AttachmentProcessor::LOCK_META_KEY, true ) );
	}

	/**
	 * Abandoned locks follow their own expiry: a conversion lock older than
	 * ten minutes and an EXIF strip lock older than ScanStartLock::SECONDS no
	 * longer block, and neither is removed or replaced by compression.
	 */
	public function test_expired_locks_do_not_block() {
		$old_conversion = time() - 11 * MINUTE_IN_SECONDS;
		update_post_meta( $this->id, AttachmentConverter::LOCK_META_KEY, $old_conversion );
		$option    = ExifStripper::LOCK_PREFIX . $this->id;
		$old_strip = ( time() - ScanStartLock::SECONDS - 5 ) . ':abandoned';
		add_option( $option, $old_strip, '', false );
		$this->assertFalse( ScanStartLock::instance()->is_held( $option ), 'Precondition: the strip lock has expired.' );

		$result = AttachmentProcessor::instance()->process( $this->id, self::settings() );

		$this->assertNotWPError( $result );
		$this->assertSame( (string) $old_conversion, get_post_meta( $this->id, AttachmentConverter::LOCK_META_KEY, true ), 'The expired conversion lock is left for its owner.' );
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$this->assertSame( $old_strip, get_option( $option ), 'The expired strip lock is left for its owner.' );
	}

	/**
	 * A strip lock still within ScanStartLock::SECONDS blocks even when it was
	 * not taken by this request, by the same rule acquire() uses.
	 */
	public function test_recent_strip_lock_row_blocks() {
		$option = ExifStripper::LOCK_PREFIX . $this->id;
		add_option( $option, ( time() - 5 ) . ':other-request', '', false );
		$before = $this->file_hash();

		$result = AttachmentProcessor::instance()->process( $this->id, self::settings() );

		$this->assert_refused_untouched( $result, $before );
	}
}
