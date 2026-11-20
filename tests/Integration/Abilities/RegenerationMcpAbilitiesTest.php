<?php
/**
 * The MCP thumbnail regeneration tools: preview, start, job status and
 * cancel.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\ConfirmationToken;
use TinySolutions\mlt\Abilities\Media\CancelThumbnailRegeneration;
use TinySolutions\mlt\Abilities\Media\GetThumbnailRegenerationStatus;
use TinySolutions\mlt\Abilities\Media\PreviewThumbnailRegeneration;
use TinySolutions\mlt\Abilities\Media\RegenerateThumbnailsAbility;
use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\CompressionMetadata;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\Regenerate\McpRegenerate;
use TinySolutions\mlt\Modules\Regenerate\RegenerateLock;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';

/**
 * RegenerationMcpAbilitiesTest
 */
class RegenerationMcpAbilitiesTest extends WP_UnitTestCase {

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
	 * Meta capability to deny per attachment ID.
	 *
	 * @var array<int, string>
	 */
	private $denied = [];

	/**
	 * Recorded non-read SQL statements.
	 *
	 * @var string[]
	 */
	private $writes = [];

	/**
	 * Files reported as remote.
	 *
	 * @var int
	 */
	private $remote = 0;

	/**
	 * Administrator; no job.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/preview-thumbnail-regeneration' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( RegenerateThumbnails::STATE_OPTION );
		wp_clear_scheduled_hook( RegenerateThumbnails::TICK_HOOK );
		$this->denied = [];
		$this->writes = [];
		$this->remote = 0;
		add_filter( 'map_meta_cap', [ $this, 'deny' ], 10, 4 );
		add_filter( 'get_attached_file', [ $this, 'remote_file' ], 10, 2 );
	}

	/**
	 * Remove fixtures.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny' ], 10 );
		remove_filter( 'get_attached_file', [ $this, 'remote_file' ], 10 );
		remove_filter( 'query', [ $this, 'record_query' ] );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		delete_option( RegenerateThumbnails::STATE_OPTION );
		wp_clear_scheduled_hook( RegenerateThumbnails::TICK_HOOK );
		foreach ( $this->ids as $id ) {
			delete_option( ExifStripper::LOCK_PREFIX . $id );
			$file = get_attached_file( $id );
			if ( $file && is_file( $file ) ) {
				chmod( $file, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
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
	 * Deny a meta capability for listed IDs (read_post also denies edit_post).
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
		if ( isset( $this->denied[ $id ] ) && ( $cap === $this->denied[ $id ] || ( 'read_post' === $this->denied[ $id ] && 'edit_post' === $cap ) ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
	}

	/**
	 * Report one attachment's file as offloaded.
	 *
	 * @param string $file          File.
	 * @param int    $attachment_id Attachment ID.
	 *
	 * @return string
	 */
	public function remote_file( $file, $attachment_id ) {
		return (int) $attachment_id === $this->remote ? 's3://bucket/remote.jpg' : $file;
	}

	/**
	 * Record every statement that is not a read.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function record_query( $query ) {
		if ( ! preg_match( '/^\s*(SELECT|SHOW)\b/i', $query ) ) {
			$this->writes[] = $query;
		}
		return $query;
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
	 * Upload a JPEG.
	 *
	 * @param string $name  File name.
	 * @param string $bytes Bytes, or '' for canola.jpg.
	 *
	 * @return int
	 */
	private function jpeg( string $name, string $bytes = '' ): int {
		$temp = trailingslashit( get_temp_dir() ) . $name;
		file_put_contents( $temp, '' !== $bytes ? $bytes : file_get_contents( DIR_TESTDATA . '/images/canola.jpg' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test fixture.
		$this->files[] = $temp;
		$id            = (int) self::factory()->attachment->create_upload_object( $temp );
		$this->ids[]   = $id;
		return $id;
	}

	/**
	 * Run an ability through the Abilities API.
	 *
	 * @param string $name  Ability name.
	 * @param mixed  $input Input.
	 *
	 * @return mixed
	 */
	private static function call( string $name, $input = null ) {
		return wp_get_ability( $name )->execute( $input );
	}

	/**
	 * Preview, asserting a token was issued.
	 *
	 * @param int[] $ids IDs.
	 *
	 * @return array
	 */
	private function preview( array $ids ): array {
		$result = self::call( 'tsmlt/preview-thumbnail-regeneration', [ 'ids' => $ids ] );
		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertIsString( $result['confirmation_token'], wp_json_encode( $result ) );
		return $result;
	}

	/**
	 * Start with a token.
	 *
	 * @param string $token Token.
	 *
	 * @return mixed
	 */
	private static function start( string $token ) {
		return self::call(
			'tsmlt/regenerate-thumbnails',
			[
				'confirmation_token' => $token,
				'confirm'            => true,
			]
		);
	}

	/**
	 * Hash of a thumbnail file.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return string
	 */
	private static function thumb_hash( int $id ): string {
		$meta = wp_get_attachment_metadata( $id );
		clearstatcache();
		return (string) md5_file( dirname( get_attached_file( $id ) ) . '/' . $meta['sizes']['thumbnail']['file'] );
	}

	/**
	 * Every preview status, scaled and rotated uploads; nothing changed but
	 * the token.
	 */
	public function test_preview_statuses_and_read_only() {
		$ready = $this->jpeg( 'mcp-ready.jpg' );
		add_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		$scaled     = $this->jpeg( 'mcp-scaled.jpg' );
		$lost       = $this->jpeg( 'mcp-lost.jpg' );
		$compressed = $this->jpeg( 'mcp-compressed.jpg' );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		wp_delete_file( wp_get_original_image_path( $lost ) );
		CompressionMetadata::instance()->save( $compressed, [ 'status' => 'completed' ] );
		$missing = $this->jpeg( 'mcp-missing.jpg' );
		wp_delete_file( get_attached_file( $missing ) );
		$unreadable = $this->jpeg( 'mcp-unreadable.jpg' );
		chmod( get_attached_file( $unreadable ), 0000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		$remote       = $this->jpeg( 'mcp-remote.jpg' );
		$this->remote = $remote;
		$text         = (int) self::factory()->attachment->create_object(
			[
				'file'           => 'notes.txt',
				'post_mime_type' => 'text/plain',
			]
		);
		$this->ids[]  = $text;
		$hidden       = $this->jpeg( 'mcp-hidden.jpg' );
		$readonly     = $this->jpeg( 'mcp-readonly.jpg' );
		$trashed      = $this->jpeg( 'mcp-trashed.jpg' );
		wp_trash_post( $trashed );
		$post         = (int) self::factory()->post->create();
		$this->denied = [
			$hidden   => 'read_post',
			$readonly => 'edit_post',
		];
		$hash         = self::thumb_hash( $ready );
		$meta         = wp_get_attachment_metadata( $scaled );

		add_filter( 'query', [ $this, 'record_query' ] );
		$result = self::call( 'tsmlt/preview-thumbnail-regeneration', [ 'ids' => [ $ready, $scaled, $lost, $compressed, $missing, $unreadable, $remote, $text, $hidden, $readonly, $trashed, $post ] ] );
		remove_filter( 'query', [ $this, 'record_query' ] );

		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$by_id = array_column( $result['items'], null, 'id' );
		$this->assertSame(
			[ 'ready', 'ready', 'original_missing', 'ready', 'file_missing', 'file_missing', 'remote_storage', 'not_image', 'not_found', 'forbidden', 'not_found', 'not_found' ],
			array_column( $result['items'], 'status' )
		);
		$this->assertSame( 'scaled', $by_id[ $scaled ]['upload_copy'] );
		$this->assertSame( 'available', $by_id[ $scaled ]['original'] );
		$this->assertSame( 'missing', $by_id[ $lost ]['original'] );
		$this->assertSame( 'none', $by_id[ $ready ]['original'] );
		$this->assertTrue( $by_id[ $compressed ]['compressed'] );
		$this->assertFalse( $result['reversible'] );
		$this->assertContains( 'thumbnail', $result['sizes'] );
		$this->assertSame( 3, $result['ready_count'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $result['confirmation_token'] );

		foreach ( $this->writes as $sql ) {
			$this->assertStringContainsString( McpRegenerate::TOKEN_OPTION_PREFIX, $sql, 'Only the token is written.' );
		}
		$this->assertSame( $hash, self::thumb_hash( $ready ) );
		$this->assertSame( $meta, wp_get_attachment_metadata( $scaled ) );
		$this->assertFalse( get_option( RegenerateThumbnails::STATE_OPTION ), 'No job.' );
		$this->assertFalse( (bool) wp_next_scheduled( RegenerateThumbnails::TICK_HOOK ) );
	}

	/**
	 * A rotated upload is ready, with its original available.
	 */
	public function test_preview_rotated_upload() {
		if ( ! function_exists( 'exif_read_data' ) ) {
			$this->markTestSkipped( 'The PHP EXIF extension is required.' );
		}
		$id     = $this->jpeg( 'mcp-turn.jpg', TsmltExifJpegFixture::build( DIR_TESTDATA . '/images/canola.jpg', [ [ 0x0112, TsmltExifJpegFixture::SHORT, 6 ] ], [], [] ) );
		$result = $this->preview( [ $id ] );
		$this->assertSame( 'ready', $result['items'][0]['status'] );
		$this->assertSame( 'rotated', $result['items'][0]['upload_copy'] );
		$this->assertSame( 'available', $result['items'][0]['original'] );
	}

	/**
	 * Each lock makes an image locked; nothing is ready, no token.
	 */
	public function test_preview_locks() {
		$ids = [ $this->jpeg( 'lk-a.jpg' ), $this->jpeg( 'lk-b.jpg' ), $this->jpeg( 'lk-c.jpg' ), $this->jpeg( 'lk-d.jpg' ) ];
		update_post_meta( $ids[0], AttachmentProcessor::LOCK_META_KEY, time() );
		update_post_meta( $ids[1], AttachmentConverter::LOCK_META_KEY, time() );
		$regen = RegenerateLock::acquire( $ids[2] );
		$exif  = ScanStartLock::instance()->acquire( ExifStripper::LOCK_PREFIX . $ids[3] );

		$result = self::call( 'tsmlt/preview-thumbnail-regeneration', [ 'ids' => $ids ] );

		$this->assertSame( [ 'locked', 'locked', 'locked', 'locked' ], array_column( $result['items'], 'status' ) );
		$this->assertNull( $result['confirmation_token'] );
		$this->assertTrue( RegenerateLock::is_held( $ids[2] ), 'Locks are only read.' );
		RegenerateLock::release( $ids[2], $regen );
		ScanStartLock::instance()->release( $exif, ExifStripper::LOCK_PREFIX . $ids[3] );
	}

	/**
	 * Only 1 to 20 distinct IDs; nothing else, no library option.
	 */
	public function test_invalid_input() {
		foreach ( [
			[],
			[ 'ids' => [] ],
			[ 'ids' => range( 1, 21 ) ],
			[ 'ids' => [ 1, 1 ] ],
			[ 'ids' => [ '1' ] ],
			[ 'ids' => [ 0 ] ],
			[ 'scope' => 'library' ],
			[
				'ids'   => [ 1 ],
				'scope' => 'library',
			],
		] as $input ) {
			$result = PreviewThumbnailRegeneration::instance()->execute( $input );
			$this->assertWPError( $result, wp_json_encode( $input ) );
			$this->assertSame( 'tsmlt_invalid_input', $result->get_error_code(), wp_json_encode( $input ) );
		}
		$this->assertIsArray( PreviewThumbnailRegeneration::instance()->execute( [ 'ids' => range( 1, 20 ) ] ), '20 IDs are accepted.' );
	}

	/**
	 * Logged-out users and non-administrators are refused by every tool.
	 */
	public function test_permissions() {
		foreach ( [ 0, (int) self::factory()->user->create( [ 'role' => 'editor' ] ) ] as $user ) {
			wp_set_current_user( $user );
			foreach ( [ PreviewThumbnailRegeneration::instance(), RegenerateThumbnailsAbility::instance(), GetThumbnailRegenerationStatus::instance(), CancelThumbnailRegeneration::instance() ] as $tool ) {
				$this->assertFalse( $tool->check_permission() );
				$result = $tool->execute( [] );
				$this->assertWPError( $result );
				$this->assertContains( $result->get_error_code(), [ 'tsmlt_unauthenticated', 'tsmlt_forbidden' ] );
			}
		}
	}

	/**
	 * Start: a background job with a fixed queue of the ready IDs; nothing
	 * regenerated in the request.
	 */
	public function test_start_creates_fixed_queue_job() {
		$a       = $this->jpeg( 'st-a.jpg' );
		$b       = $this->jpeg( 'st-b.jpg' );
		$post    = (int) self::factory()->post->create();
		$hash    = self::thumb_hash( $a );
		$preview = $this->preview( [ $a, $post, $b ] );

		$result = self::start( $preview['confirmation_token'] );

		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertSame( 'selected', $result['job_type'] );
		$this->assertSame( 'running', $result['status'] );
		$this->assertSame( 2, $result['total'] );
		$this->assertMatchesRegularExpression( '/^tsmlt_regen_[a-f0-9]{16}$/', $result['job_id'] );
		$this->assertSame( [ $a, $b ], array_column( $result['items'], 'id' ) );
		$state = RegenerateThumbnails::instance()->get_state();
		$this->assertSame( [ $a, $b ], $state['queue'] );
		$this->assertSame( 'selected', $state['job_type'] );
		$this->assertNotFalse( wp_next_scheduled( RegenerateThumbnails::TICK_HOOK ) );
		$this->assertSame( $hash, self::thumb_hash( $a ), 'Nothing is regenerated in the request.' );

		$done = RegenerateThumbnails::instance()->run_batch();
		$this->assertSame( 2, $done['success_count'] );
	}

	/**
	 * Images that changed since the preview are left out; when none is
	 * ready, nothing starts.
	 */
	public function test_start_rechecks_images() {
		$a       = $this->jpeg( 'rc-a.jpg' );
		$b       = $this->jpeg( 'rc-b.jpg' );
		$preview = $this->preview( [ $a, $b ] );
		update_post_meta( $b, AttachmentConverter::LOCK_META_KEY, time() );

		$result = self::start( $preview['confirmation_token'] );

		$this->assertSame( [ true, false ], array_column( $result['items'], 'queued' ) );
		$this->assertSame( [ 'ready', 'locked' ], array_column( $result['items'], 'status' ) );
		$this->assertSame( [ $a ], RegenerateThumbnails::instance()->get_state()['queue'] );

		delete_option( RegenerateThumbnails::STATE_OPTION );
		$preview      = $this->preview( [ $a ] );
		$this->denied = [ $a => 'edit_post' ];
		$nothing      = self::start( $preview['confirmation_token'] );
		$this->assertSame( McpRegenerate::ERROR_NOTHING, $nothing->get_error_code() );
		$this->assertFalse( get_option( RegenerateThumbnails::STATE_OPTION ) );
	}

	/**
	 * A missing original after the preview: the image is left out.
	 */
	public function test_start_rechecks_original() {
		add_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		$id = $this->jpeg( 'rc-orig.jpg' );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		$preview = $this->preview( [ $id ] );
		wp_delete_file( wp_get_original_image_path( $id ) );

		$result = self::start( $preview['confirmation_token'] );

		$this->assertSame( McpRegenerate::ERROR_NOTHING, $result->get_error_code() );
	}

	/**
	 * Changed image sizes since the preview refuse the run.
	 */
	public function test_sizes_changed_since_preview() {
		$id      = $this->jpeg( 'sz.jpg' );
		$preview = $this->preview( [ $id ] );
		update_option( 'thumbnail_size_w', 120 );

		$result = self::start( $preview['confirmation_token'] );

		$this->assertSame( McpRegenerate::ERROR_CHANGED, $result->get_error_code() );
		$this->assertFalse( get_option( RegenerateThumbnails::STATE_OPTION ) );
	}

	/**
	 * Tokens: confirm required, ownership, replay, expiry.
	 */
	public function test_token_rules() {
		$id      = $this->jpeg( 'tk.jpg' );
		$tool    = RegenerateThumbnailsAbility::instance();
		$preview = $this->preview( [ $id ] );
		$this->assertSame( 'tsmlt_invalid_input', $tool->execute( [ 'confirmation_token' => $preview['confirmation_token'] ] )->get_error_code() );
		$this->assertSame(
			'tsmlt_invalid_input',
			$tool->execute(
				[
					'confirmation_token' => $preview['confirmation_token'],
					'confirm'            => false,
				]
			)->get_error_code()
		);

		$owner = get_current_user_id();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( McpRegenerate::ERROR_TOKEN, self::start( $preview['confirmation_token'] )->get_error_code() );
		wp_set_current_user( $owner );
		$this->assertIsArray( self::start( $preview['confirmation_token'] ) );

		RegenerateThumbnails::instance()->cancel();
		$this->assertSame( McpRegenerate::ERROR_TOKEN, self::start( $preview['confirmation_token'] )->get_error_code(), 'Replay.' );

		delete_option( RegenerateThumbnails::STATE_OPTION );
		$preview            = $this->preview( [ $id ] );
		$option             = ConfirmationToken::option( McpRegenerate::TOKEN_OPTION_PREFIX, $preview['confirmation_token'] );
		$payload            = get_option( $option );
		$payload['expires'] = time() - 1;
		update_option( $option, $payload, false );
		$this->assertSame( McpRegenerate::ERROR_TOKEN, self::start( $preview['confirmation_token'] )->get_error_code(), 'Expired.' );
		$this->assertFalse( get_option( RegenerateThumbnails::STATE_OPTION ) );
	}

	/**
	 * A running regeneration job refuses a start and keeps the token.
	 */
	public function test_refuses_while_job_running() {
		$id      = $this->jpeg( 'run.jpg' );
		$preview = $this->preview( [ $id ] );
		$running = RegenerateThumbnails::instance()->start( [ $id ] );

		$result = self::start( $preview['confirmation_token'] );

		$this->assertSame( McpRegenerate::ERROR_JOB_RUNNING, $result->get_error_code() );
		$this->assertSame( $running['job_id'], RegenerateThumbnails::instance()->get_state()['job_id'], 'The running job is untouched.' );
		delete_option( RegenerateThumbnails::STATE_OPTION );
		$this->assertIsArray( self::start( $preview['confirmation_token'] ), 'The token was kept.' );
	}

	/**
	 * Status: read-only, no file names or error texts, unreadable results hidden.
	 */
	public function test_status_is_read_only_and_safe() {
		$this->assertSame( 'idle', self::call( 'tsmlt/get-thumbnail-regeneration-status' )['status'] );
		$visible = $this->jpeg( 'vis.jpg' );
		$secret  = $this->jpeg( 'secret-name.jpg' );
		update_option(
			RegenerateThumbnails::STATE_OPTION,
			[
				'job_id'        => 'tsmlt_regen_0123456789abcdef',
				'job_type'      => 'selected',
				'user_id'       => get_current_user_id(),
				'status'        => 'running',
				'queue'         => [ 99 ],
				'total'         => 4,
				'offset'        => 3,
				'success_count' => 1,
				'skipped_count' => 1,
				'failed_count'  => 1,
				'started_at'    => 1700000000,
				'updated_at'    => 1700000100,
				'recent_done'   => [
					[
						'id'   => $visible,
						'file' => 'vis.jpg',
					],
				],
				'recent_errors' => [
					[
						'id'    => $secret,
						'file'  => 'secret-name.jpg',
						'error' => 'Imagick: /var/www/uploads/secret-name.jpg broken',
						'code'  => 'generation_failed',
					],
					[
						'id'    => 424242,
						'file'  => 'ID:424242',
						'error' => 'x',
						'code'  => 'not_found',
					],
				],
			],
			false
		);
		$this->denied = [ $secret => 'read_post' ];
		$stored       = get_option( RegenerateThumbnails::STATE_OPTION );

		add_filter( 'query', [ $this, 'record_query' ] );
		$result = self::call( 'tsmlt/get-thumbnail-regeneration-status', [] );
		remove_filter( 'query', [ $this, 'record_query' ] );

		$this->assertSame( 'running', $result['status'] );
		$this->assertTrue( $result['stale'], 'Old update, no tick: stale, but not changed.' );
		$this->assertSame( 75, $result['percent'] );
		$this->assertSame( 1, $result['remaining'] );
		$this->assertSame(
			[
				[
					'id'     => $visible,
					'result' => 'regenerated',
					'code'   => '',
				],
				[
					'id'     => 424242,
					'result' => 'skipped',
					'code'   => 'not_found',
				],
			],
			$result['results']['items']
		);
		$this->assertSame( 1, $result['results']['hidden'] );
		$this->assertSame( [], $this->writes );
		$this->assertSame( $stored, get_option( RegenerateThumbnails::STATE_OPTION ) );
		$json = wp_json_encode( $result );
		foreach ( [ 'vis.jpg', 'secret-name', '/var/www', 'Imagick', '"queue"', 'user_id' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, $needle );
		}
	}

	/**
	 * Cancel only the matching running job.
	 */
	public function test_cancel() {
		$id = $this->jpeg( 'cn.jpg' );
		$this->assertSame( McpRegenerate::ERROR_JOB_MISMATCH, self::call( 'tsmlt/cancel-thumbnail-regeneration', [ 'job_id' => 'tsmlt_regen_x' ] )->get_error_code(), 'No job.' );

		$job = RegenerateThumbnails::instance()->start( [ $id ] );
		$this->assertSame( McpRegenerate::ERROR_JOB_MISMATCH, self::call( 'tsmlt/cancel-thumbnail-regeneration', [ 'job_id' => 'tsmlt_regen_other' ] )->get_error_code() );
		$this->assertSame( 'running', RegenerateThumbnails::instance()->get_state()['status'] );

		$result = self::call( 'tsmlt/cancel-thumbnail-regeneration', [ 'job_id' => $job['job_id'] ] );
		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertSame( 'cancelled', $result['status'] );
		$this->assertSame( McpRegenerate::ERROR_NOT_RUNNING, self::call( 'tsmlt/cancel-thumbnail-regeneration', [ 'job_id' => $job['job_id'] ] )->get_error_code(), 'Cancelled job.' );

		delete_option( RegenerateThumbnails::STATE_OPTION );
		$done = RegenerateThumbnails::instance()->start( [ $id ] );
		RegenerateThumbnails::instance()->run_batch();
		$this->assertSame( 'done', RegenerateThumbnails::instance()->get_state()['status'] );
		$this->assertSame( McpRegenerate::ERROR_NOT_RUNNING, self::call( 'tsmlt/cancel-thumbnail-regeneration', [ 'job_id' => $done['job_id'] ] )->get_error_code(), 'Completed job.' );
	}

	/**
	 * No paths, file names or titles in any response.
	 */
	public function test_no_paths_names_or_titles() {
		$id = $this->jpeg( 'private-name.jpg' );
		wp_update_post(
			[
				'ID'         => $id,
				'post_title' => 'Private Title',
			]
		);
		$preview = $this->preview( [ $id ] );
		$start   = self::start( $preview['confirmation_token'] );
		RegenerateThumbnails::instance()->run_batch();
		$status = self::call( 'tsmlt/get-thumbnail-regeneration-status' );
		$json   = wp_json_encode( [ $preview, $start, $status ] );
		foreach ( [ wp_get_upload_dir()['basedir'], 'private-name', 'Private Title' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, $needle );
		}
	}

	/**
	 * Listed by name, not runnable through the default gateway.
	 */
	public function test_not_mcp_public() {
		foreach ( [ 'tsmlt/preview-thumbnail-regeneration', 'tsmlt/regenerate-thumbnails', 'tsmlt/get-thumbnail-regeneration-status', 'tsmlt/cancel-thumbnail-regeneration' ] as $name ) {
			$this->assertFalse( wp_get_ability( $name )->get_meta()['mcp']['public'], $name );
		}
		$this->assertTrue( wp_get_ability( 'tsmlt/regenerate-thumbnails' )->get_meta()['annotations']['destructive'] );
		$this->assertTrue( wp_get_ability( 'tsmlt/get-thumbnail-regeneration-status' )->get_meta()['annotations']['readonly'] );
	}
}
