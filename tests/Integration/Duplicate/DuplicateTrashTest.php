<?php
/**
 * Move to Trash on the Duplicates page (Free; AJAX tsmlt_duplicate_trash,
 * DuplicateTrash::trash()): trash only, no licence, no usage scan or
 * reference check, administrators with delete_post, locks, trash off,
 * failures and repeats, and the duplicate results after it.
 *
 * Generated fixtures only.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\Duplicate\DuplicateScanner;
use TinySolutions\mlt\Modules\Duplicate\DuplicateTrash;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\Regenerate\RegenerateLock;
use TinySolutions\mlt\Modules\Trash\MediaTrash;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * DuplicateTrashTest
 */
class DuplicateTrashTest extends WP_Ajax_UnitTestCase {

	/**
	 * Users by role.
	 *
	 * @var array<string, int>
	 */
	private $users = [];

	/**
	 * Attachment ID => denied meta capability.
	 *
	 * @var array<int, string>
	 */
	private $denied = [];

	/**
	 * Whether wp_trash_post() is blocked.
	 *
	 * @var bool
	 */
	private $block_trash = false;

	/**
	 * Queries run, to show no usage scan is started.
	 *
	 * @var string[]
	 */
	private $queries = [];

	/**
	 * A user per role and no duplicate results.
	 */
	public function set_up() {
		parent::set_up();
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		// admin_init re-creates the plugin's tables when no version is
		// stored, which would empty the duplicate results mid-test.
		update_option( 'tsmlt_plugin_version', TSMLT_VERSION );
		foreach ( [ 'administrator', 'editor', 'author' ] as $role ) {
			$this->users[ $role ] = self::factory()->user->create( [ 'role' => $role ] );
		}
		wp_set_current_user( $this->users['administrator'] );
		delete_option( 'site_logo' );
		delete_option( 'site_icon' );
		Fns::DB()->delete( 'tsmlt_duplicate_file' )->where( 'id', '>', 0 )->execute();
		$this->denied      = [];
		$this->block_trash = false;
		$this->queries     = [];
		add_filter( 'map_meta_cap', [ $this, 'deny' ], 10, 4 );
		add_filter( 'pre_trash_post', [ $this, 'maybe_block_trash' ], 10, 2 );
	}

	/**
	 * Remove filters.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny' ], 10 );
		remove_filter( 'pre_trash_post', [ $this, 'maybe_block_trash' ], 10 );
		remove_filter( 'query', [ $this, 'record_query' ] );
		remove_filter( MediaTrash::DAYS_FILTER, '__return_zero' );
		parent::tear_down();
	}

	/**
	 * Deny a meta capability for listed IDs.
	 *
	 * @param string[] $caps    Caps.
	 * @param string   $cap     Meta cap.
	 * @param int      $user_id User.
	 * @param array    $args    Args.
	 *
	 * @return string[]
	 */
	public function deny( $caps, $cap, $user_id, $args ) {
		$id = (int) ( $args[0] ?? 0 );
		return isset( $this->denied[ $id ] ) && $cap === $this->denied[ $id ] ? [ 'do_not_allow' ] : $caps;
	}

	/**
	 * Block wp_trash_post() when asked.
	 *
	 * @param mixed $check Short-circuit value.
	 *
	 * @return mixed
	 */
	public function maybe_block_trash( $check ) {
		return $this->block_trash ? false : $check;
	}

	/**
	 * Record queries.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function record_query( $query ) {
		$this->queries[] = $query;
		return $query;
	}

	/**
	 * Two identical uploads recorded as a duplicate group.
	 *
	 * @return int[] Their IDs.
	 */
	private function group(): array {
		$ids = [];
		foreach ( [ 1, 2 ] as $n ) {
			$id    = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
			$file  = get_attached_file( $id );
			$ids[] = $id;
			Fns::DB()->insert(
				'tsmlt_duplicate_file',
				[
					[
						'attachment_id' => $id,
						'file_hash'     => md5_file( $file ),
						'file_size'     => filesize( $file ),
						'file_path'     => wp_basename( $file ),
					],
				]
			)->execute();
		}
		return $ids;
	}

	/**
	 * An attachment's stored status, read fresh.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return string
	 */
	private static function status( int $id ): string {
		clean_post_cache( $id );
		$post = get_post( $id );
		return $post ? (string) $post->post_status : '';
	}

	/**
	 * Call the AJAX action.
	 *
	 * @param string $role   Role.
	 * @param array  $params Params.
	 *
	 * @return array|null Decoded response.
	 */
	private function ajax( string $role, array $params ) {
		wp_set_current_user( $this->users[ $role ] );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['nonce']            = wp_create_nonce( Fns::NONCE_ID );
		$_POST['params']           = wp_slash( wp_json_encode( $params ) );
		$this->_last_response      = '';
		try {
			$this->_handleAjax( 'tsmlt_duplicate_trash' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}
		return json_decode( $this->_last_response, true );
	}

	/**
	 * Trash one image directly.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array
	 */
	private static function trash( int $id ): array {
		return DuplicateTrash::instance()->trash( $id );
	}

	/**
	 * Duplicate groups as the Duplicates page loads them.
	 *
	 * @return array
	 */
	private static function groups(): array {
		return json_decode( DuplicateScanner::instance()->get_duplicates( [ 'paged' => 1 ] ), true )['groups'];
	}

	/**
	 * A copy goes to the trash (files kept), leaves the duplicate results,
	 * and can be restored. No licence is involved.
	 */
	public function test_copy_is_trashed_and_restorable() {
		list( $keep, $copy ) = $this->group();
		$file                = get_attached_file( $copy );
		$this->assertCount( 1, self::groups(), 'Precondition: one group.' );

		$result = $this->ajax( 'administrator', [ 'attachment_id' => $copy ] );

		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['data']['updated'], wp_json_encode( $result ) );
		$this->assertSame( '', $result['data']['code'] );
		$this->assertSame( 'trash', self::status( $copy ) );
		$this->assertSame( 'inherit', self::status( $keep ) );
		$this->assertFileExists( $file, 'Files kept in the trash.' );
		$this->assertSame( [], self::groups(), 'The group is gone from the results.' );
		$this->assertFalse( RegenerateLock::is_held( $copy ), 'Released.' );

		$this->assertSame( 'restored', MediaTrash::restore( $copy )['result'] );
		$this->assertSame( 'inherit', self::status( $copy ) );
		$this->assertFileExists( $file );
	}

	/**
	 * Used images are trashed without a usage scan or reference check: the
	 * page always warns instead.
	 */
	public function test_no_usage_scan_or_reference_check() {
		list( , $copy ) = $this->group();
		$post           = (int) self::factory()->post->create( [ 'post_content' => '<img src="' . wp_get_attachment_url( $copy ) . '">' ] );
		set_post_thumbnail( (int) self::factory()->post->create(), $copy );
		update_post_meta(
			$copy,
			UsedWhereScanner::META_KEY,
			[
				[
					'post_id'    => $post,
					'post_title' => 'Uses it',
					'post_type'  => 'post',
					'usage_type' => 'content',
				],
			]
		);
		update_option( UsageScanStatus::OPTION, [ 'state' => 'running' ], false );
		$scan_before = get_option( UsageScanStatus::OPTION );
		add_filter( 'query', [ $this, 'record_query' ] );

		$result = self::trash( $copy );

		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertTrue( $result['updated'], wp_json_encode( $result ) );
		$this->assertSame( 'trash', self::status( $copy ) );
		$this->assertSame( $scan_before, get_option( UsageScanStatus::OPTION ), 'No scan was started.' );
		$this->assertFalse( (bool) wp_next_scheduled( UsedWhereScanner::SCAN_TICK_HOOK ) );
		$this->assertSame( [], preg_grep( '/LIKE .*' . preg_quote( wp_basename( get_attached_file( $copy ) ), '/' ) . '/', $this->queries ), 'No content search.' );
	}

	/**
	 * Administrators only, with delete_post on the image.
	 */
	public function test_permissions() {
		list( , $copy ) = $this->group();

		foreach ( [ 'editor', 'author' ] as $role ) {
			$refused = $this->ajax( $role, [ 'attachment_id' => $copy ] );
			$this->assertNotTrue( $refused['success'] ?? null, $role );
		}
		$this->assertSame( 'inherit', self::status( $copy ) );

		$this->denied = [ $copy => 'delete_post' ];
		$result       = $this->ajax( 'administrator', [ 'attachment_id' => $copy ] );
		$this->assertFalse( $result['data']['updated'] );
		$this->assertSame( 'forbidden', $result['data']['code'] );
		$this->assertSame( 'inherit', self::status( $copy ) );
	}

	/**
	 * Invalid targets: missing, not an attachment, not an ID, the site logo.
	 */
	public function test_invalid_targets() {
		list( , $copy ) = $this->group();
		$this->assertSame( 'not_found', self::trash( 999999 )['code'] );
		$this->assertSame( 'not_found', self::trash( (int) self::factory()->post->create() )['code'] );
		$this->assertSame( 'not_found', $this->ajax( 'administrator', [ 'attachment_id' => 'abc' ] )['data']['code'] );
		update_option( 'site_logo', $copy );
		$this->assertSame( 'site_media', self::trash( $copy )['code'] );
		$this->assertSame( 'inherit', self::status( $copy ) );
	}

	/**
	 * Trash off: refused, never a permanent delete.
	 */
	public function test_trash_disabled() {
		list( , $copy ) = $this->group();
		add_filter( MediaTrash::DAYS_FILTER, '__return_zero' );

		$result = self::trash( $copy );

		$this->assertSame( 'trash_disabled', $result['code'] );
		$this->assertSame( 'inherit', self::status( $copy ) );
		$this->assertFileExists( get_attached_file( $copy ) );
		$this->assertCount( 1, self::groups() );
	}

	/**
	 * Every operation lock refuses the move; the move's own lock is
	 * released afterwards.
	 */
	public function test_locks() {
		list( , $copy ) = $this->group();

		update_post_meta( $copy, AttachmentProcessor::LOCK_META_KEY, time() );
		$this->assertSame( 'locked', self::trash( $copy )['code'], 'Compression.' );
		delete_post_meta( $copy, AttachmentProcessor::LOCK_META_KEY );

		update_post_meta( $copy, AttachmentConverter::LOCK_META_KEY, time() );
		$this->assertSame( 'locked', self::trash( $copy )['code'], 'Conversion.' );
		delete_post_meta( $copy, AttachmentConverter::LOCK_META_KEY );

		$regen = RegenerateLock::acquire( $copy );
		$this->assertSame( 'locked', self::trash( $copy )['code'], 'Rename or regeneration.' );
		RegenerateLock::release( $copy, (string) $regen );

		$exif = ScanStartLock::instance()->acquire( ExifStripper::LOCK_PREFIX . $copy );
		$this->assertSame( 'locked', self::trash( $copy )['code'], 'EXIF.' );
		ScanStartLock::instance()->release( (string) $exif, ExifStripper::LOCK_PREFIX . $copy );

		$this->assertSame( 'inherit', self::status( $copy ) );
		$this->assertFalse( RegenerateLock::is_held( $copy ) );
		$this->assertTrue( self::trash( $copy )['updated'], 'Works once unlocked.' );
	}

	/**
	 * A trash that fails changes nothing and keeps the image listed; a
	 * repeated request after success is refused.
	 */
	public function test_failure_and_repeat() {
		list( , $copy )    = $this->group();
		$this->block_trash = true;

		$failed = self::trash( $copy );

		$this->assertSame( 'trash_failed', $failed['code'] );
		$this->assertSame( 'inherit', self::status( $copy ) );
		$this->assertCount( 1, self::groups(), 'Still listed.' );
		$this->assertFalse( RegenerateLock::is_held( $copy ), 'Released after a failure.' );

		$this->block_trash = false;
		$this->assertTrue( self::trash( $copy )['updated'] );
		$this->assertSame( 'already_trashed', self::trash( $copy )['code'], 'Repeated request.' );
		$this->assertSame( 'trash', self::status( $copy ) );
	}
}
