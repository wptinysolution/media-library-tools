<?php
/**
 * The MCP rename abilities: preview names match the engine, one-time
 * tokens, single and bulk renames, checks at execution, progress and
 * cancellation, and no paths in any response.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\ConfirmationToken;
use TinySolutions\mlt\Abilities\Media\CancelMediaRename;
use TinySolutions\mlt\Abilities\Media\PreviewMediaRename;
use TinySolutions\mlt\Abilities\Media\RenameMedia;
use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Regenerate\RegenerateLock;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;
use TinySolutions\mlt\Modules\Rename\McpRename;
use TinySolutions\mlt\Modules\Rename\RenameJob;

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';

/**
 * RenameMcpAbilitiesTest
 */
class RenameMcpAbilitiesTest extends WP_UnitTestCase {

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
	 * IDs whose read_post (and so edit_post) is denied.
	 *
	 * @var int[]
	 */
	private $unreadable = [];

	/**
	 * IDs whose edit_post is denied.
	 *
	 * @var int[]
	 */
	private $uneditable = [];

	/**
	 * Administrator; no jobs.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/rename-media' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( RenameJob::STATE_OPTION );
		delete_option( RegenerateThumbnails::STATE_OPTION );
		$this->unreadable = [];
		$this->uneditable = [];
		add_filter( 'map_meta_cap', [ $this, 'deny' ], 10, 4 );
	}

	/**
	 * Remove fixtures.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny' ], 10 );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		delete_option( RenameJob::STATE_OPTION );
		delete_option( RegenerateThumbnails::STATE_OPTION );
		wp_clear_scheduled_hook( RenameJob::TICK_HOOK );
		wp_clear_scheduled_hook( RegenerateThumbnails::TICK_HOOK );
		foreach ( $this->ids as $id ) {
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
	 * Deny capabilities on listed IDs.
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
		if ( in_array( $cap, [ 'read_post', 'edit_post' ], true ) && in_array( $id, $this->unreadable, true ) ) {
			return [ 'do_not_allow' ];
		}
		if ( 'edit_post' === $cap && in_array( $id, $this->uneditable, true ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
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
	 * Upload a JPEG named mcpren-{name}.jpg.
	 *
	 * @param string $name   Name.
	 * @param bool   $scaled Scale it on upload.
	 * @param string $bytes  Bytes, or '' for canola.jpg.
	 *
	 * @return int
	 */
	private function jpeg( string $name, bool $scaled = false, string $bytes = '' ): int {
		$temp = trailingslashit( get_temp_dir() ) . 'mcpren-' . $name . '.jpg';
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
	 * Run an ability.
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
	 * Preview id => name pairs.
	 *
	 * @param array<int, string> $names Names.
	 *
	 * @return array
	 */
	private function preview( array $names ): array {
		$items = [];
		foreach ( $names as $id => $name ) {
			$items[] = [
				'id'       => $id,
				'filename' => $name,
			];
		}
		$result = self::call( 'tsmlt/preview-media-rename', [ 'items' => $items ] );
		$this->assertIsArray( $result, wp_json_encode( $result ) );
		return $result;
	}

	/**
	 * Rename with a token.
	 *
	 * @param mixed $token Token.
	 *
	 * @return mixed
	 */
	private static function rename( $token ) {
		return self::call(
			'tsmlt/rename-media',
			[
				'confirmation_token' => $token,
				'confirm'            => true,
			]
		);
	}

	/**
	 * Whether a value matches the ability's output schema.
	 *
	 * @param string $name   Ability name.
	 * @param mixed  $result Result.
	 *
	 * @return void
	 */
	private function assert_schema( string $name, $result ): void {
		$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), wp_get_ability( $name )->get_output_schema(), 'result' ), $name );
	}

	/**
	 * Scaled upload: the preview shows the exact names (suffix kept, original
	 * renamed too) and the rename gives exactly those names.
	 */
	public function test_scaled_preview_matches_rename() {
		$id = $this->jpeg( 'big', true );
		$this->assertStringContainsString( '-scaled', get_attached_file( $id ), 'Precondition.' );

		$preview = $this->preview( [ $id => 'mcpren-big-new' ] );
		$item    = $preview['items'][0];
		$this->assertSame( 'ready', $item['status'] );
		$this->assertSame( 'mcpren-big-new-scaled.jpg', $item['proposed_filename'] );
		$this->assertSame( 'mcpren-big-new.jpg', $item['proposed_original_filename'] );
		$this->assertTrue( $item['affects']['original_image_kept'] );
		$this->assert_schema( 'tsmlt/preview-media-rename', $preview );

		$result = self::rename( $preview['confirmation_token'] );

		$this->assertSame( 'single', $result['mode'], wp_json_encode( $result ) );
		$this->assertSame( 'renamed', $result['items'][0]['result'] );
		$this->assertSame( $item['proposed_filename'], $result['items'][0]['filename'] );
		$this->assertSame( $item['proposed_filename'], wp_basename( get_attached_file( $id ) ) );
		$this->assertSame( $item['proposed_original_filename'], wp_basename( wp_get_original_image_path( $id ) ) );
		$this->assert_schema( 'tsmlt/rename-media', $result );
	}

	/**
	 * Rotated upload: the -rotated ending is shown and kept.
	 */
	public function test_rotated_preview_matches_rename() {
		if ( ! function_exists( 'exif_read_data' ) ) {
			$this->markTestSkipped( 'The PHP EXIF extension is required.' );
		}
		$bytes = TsmltExifJpegFixture::build( DIR_TESTDATA . '/images/canola.jpg', [ [ 0x0112, TsmltExifJpegFixture::SHORT, 6 ] ], [], [] );
		$id    = $this->jpeg( 'turn', false, $bytes );

		$preview = $this->preview( [ $id => 'mcpren-upright' ] );

		$this->assertSame( 'mcpren-upright-rotated.jpg', $preview['items'][0]['proposed_filename'] );
		$this->assertSame( 'mcpren-upright.jpg', $preview['items'][0]['proposed_original_filename'] );
		self::rename( $preview['confirmation_token'] );
		$this->assertSame( 'mcpren-upright-rotated.jpg', wp_basename( get_attached_file( $id ) ) );
	}

	/**
	 * A taken name: the preview shows the numbered name the rename uses; the
	 * other file is untouched.
	 */
	public function test_conflict_numbered_name() {
		$taken = $this->jpeg( 'taken' );
		$hash  = md5_file( get_attached_file( $taken ) );
		$id    = $this->jpeg( 'mover' );

		$preview = $this->preview( [ $id => 'mcpren-taken' ] );

		$this->assertTrue( $preview['items'][0]['needs_unique_suffix'] );
		$this->assertSame( 'mcpren-taken-1.jpg', $preview['items'][0]['proposed_filename'] );
		self::rename( $preview['confirmation_token'] );
		$this->assertSame( 'mcpren-taken-1.jpg', wp_basename( get_attached_file( $id ) ) );
		$this->assertSame( $hash, md5_file( get_attached_file( $taken ) ) );
	}

	/**
	 * The preview renames nothing; with nothing ready it issues no token.
	 */
	public function test_preview_read_only_and_no_token_without_ready() {
		$id   = $this->jpeg( 'still' );
		$file = get_attached_file( $id );
		$meta = wp_get_attachment_metadata( $id );

		$preview = $this->preview( [ $id => 'mcpren-still-new' ] );

		$this->assertIsString( $preview['confirmation_token'] );
		$this->assertSame( $file, get_attached_file( $id ) );
		$this->assertFileExists( $file );
		$this->assertSame( $meta, wp_get_attachment_metadata( $id ) );

		$none = $this->preview( [ $id => pathinfo( $file, PATHINFO_FILENAME ) ] );
		$this->assertSame( 'unchanged', $none['items'][0]['status'] );
		$this->assertNull( $none['confirmation_token'] );
		$this->assertSame( 0, $none['ready_count'] );
	}

	/**
	 * Per-item statuses: Pro strategies, permissions, locks, duplicates,
	 * missing files and bad names; only ready items enter the token.
	 */
	public function test_preview_statuses() {
		$ready      = $this->jpeg( 'ok' );
		$hidden     = $this->jpeg( 'hidden' );
		$forbidden  = $this->jpeg( 'forbidden' );
		$locked     = $this->jpeg( 'locked' );
		$missing    = $this->jpeg( 'missing' );
		$trashed    = $this->jpeg( 'trashed' );
		$regen_lock = $this->jpeg( 'regen' );
		wp_trash_post( $trashed );
		wp_delete_file( get_attached_file( $missing ) );
		update_post_meta( $locked, AttachmentProcessor::LOCK_META_KEY, time() );
		$token_lock       = RegenerateLock::acquire( $regen_lock );
		$this->unreadable = [ $hidden ];
		$this->uneditable = [ $forbidden ];

		// Called directly: the Abilities API's schema check would refuse the
		// non-integer ID before execute().
		$result = PreviewMediaRename::instance()->execute(
			[
				'items' => [
					[
						'id'       => $ready,
						'filename' => 'mcpren-ok-new',
					],
					[
						'id'       => $ready,
						'filename' => 'bulkRenameByPostTitle',
					],
					[
						'id'       => $ready,
						'filename' => 'mcpren-ok-again',
					],
					[
						'id'       => $hidden,
						'filename' => 'x1',
					],
					[
						'id'       => $forbidden,
						'filename' => 'x2',
					],
					[
						'id'       => $locked,
						'filename' => 'x3',
					],
					[
						'id'       => $missing,
						'filename' => 'x4',
					],
					[
						'id'       => $trashed,
						'filename' => 'x5',
					],
					[
						'id'       => $regen_lock,
						'filename' => 'x6',
					],
					[
						'id'       => 'abc',
						'filename' => 'x7',
					],
					[
						'id'       => $locked,
						'filename' => '...',
					],
				],
			]
		);
		RegenerateLock::release( $regen_lock, $token_lock );

		$this->assertSame(
			[ 'ready', 'pro_strategy_not_supported', 'duplicate_id', 'not_found', 'forbidden', 'locked', 'file_missing', 'not_found', 'locked', 'invalid_id', 'invalid_filename' ],
			array_column( $result['items'], 'status' )
		);
		$this->assertSame( 1, $result['ready_count'] );
		$this->assertSame( '', $result['items'][3]['current_filename'], 'Nothing about an unreadable attachment.' );
		$this->assert_schema( 'tsmlt/preview-media-rename', $result );

		$payload = get_option( ConfirmationToken::option( McpRename::TOKEN_OPTION_PREFIX, $result['confirmation_token'] ) );
		$this->assertSame(
			[
				[
					'id'   => $ready,
					'name' => 'mcpren-ok-new',
				],
			],
			$payload['items']
		);
	}

	/**
	 * Every Pro strategy keyword is refused and gives no token.
	 */
	public function test_pro_strategies_refused() {
		$id = $this->jpeg( 'pro' );
		foreach ( [ 'bulkRenameByPostTitle', 'bulkRenameBySKU', 'bulkRenameByAltText', 'bulkAiFilename', 'bulk-rename-by-sku.jpg' ] as $keyword ) {
			$result = $this->preview( [ $id => $keyword ] );
			$this->assertSame( 'pro_strategy_not_supported', $result['items'][0]['status'], $keyword );
			$this->assertNull( $result['confirmation_token'], $keyword );
		}
	}

	/**
	 * Several items: a background job with an ID; each item checked again
	 * at the start (a trashed one is reported, not queued); progress and
	 * per-item results from the status tool.
	 */
	public function test_bulk_rename_job() {
		$a       = $this->jpeg( 'bulk-a' );
		$b       = $this->jpeg( 'bulk-b' );
		$c       = $this->jpeg( 'bulk-c' );
		$preview = $this->preview(
			[
				$a => 'mcpren-bulk-one',
				$b => 'mcpren-bulk-two',
				$c => 'mcpren-bulk-three',
			]
		);
		$this->assertSame( 3, $preview['ready_count'] );
		wp_trash_post( $c );

		$result = self::rename( $preview['confirmation_token'] );

		$this->assertSame( 'bulk', $result['mode'], wp_json_encode( $result ) );
		$this->assertMatchesRegularExpression( McpRename::JOB_ID_PATTERN, $result['job_id'] );
		$this->assertSame( 'running', $result['status'] );
		$this->assertSame( 2, $result['total'] );
		$this->assertSame( [ true, true, false ], array_column( $result['items'], 'queued' ) );
		$this->assertSame( 'not_found', $result['items'][2]['status'] );
		$this->assert_schema( 'tsmlt/rename-media', $result );
		$this->assertSame( wp_basename( get_attached_file( $a ) ), 'mcpren-bulk-a.jpg', 'Not renamed in the request.' );

		$running = self::call( 'tsmlt/get-media-rename-status' );
		$this->assertSame( $result['job_id'], $running['job_id'] );
		$this->assertSame( 0, $running['processed'] );

		RenameJob::instance()->run_batch();

		$done = self::call( 'tsmlt/get-media-rename-status', [] );
		$this->assertSame( 'done', $done['status'] );
		$this->assertSame( 2, $done['processed'] );
		$this->assertSame( 2, $done['renamed'] );
		$this->assertSame( 100, $done['percent'] );
		$this->assertSame( [ $a, $b ], array_column( $done['results']['items'], 'id' ) );
		$this->assertSame( [ 'mcpren-bulk-one.jpg', 'mcpren-bulk-two.jpg' ], array_column( $done['results']['items'], 'filename' ) );
		$this->assertSame( 'mcpren-bulk-one.jpg', wp_basename( get_attached_file( $a ) ) );
		$this->assert_schema( 'tsmlt/get-media-rename-status', $done );
	}

	/**
	 * A single item checked again and no longer ready: reported, not renamed.
	 */
	public function test_single_rechecked_at_execution() {
		$id      = $this->jpeg( 'gone' );
		$file    = get_attached_file( $id );
		$preview = $this->preview( [ $id => 'mcpren-gone-new' ] );
		update_post_meta( $id, AttachmentProcessor::LOCK_META_KEY, time() );

		$result = self::rename( $preview['confirmation_token'] );

		$this->assertSame( 'none', $result['mode'] );
		$this->assertSame( 'locked', $result['items'][0]['status'] );
		$this->assertFalse( $result['items'][0]['queued'] );
		$this->assertSame( $file, get_attached_file( $id ) );
		$this->assert_schema( 'tsmlt/rename-media', $result );
	}

	/**
	 * A permission lost after the preview is caught at execution.
	 */
	public function test_permission_rechecked_at_execution() {
		$id               = $this->jpeg( 'perm' );
		$file             = get_attached_file( $id );
		$preview          = $this->preview( [ $id => 'mcpren-perm-new' ] );
		$this->uneditable = [ $id ];

		$result = self::rename( $preview['confirmation_token'] );

		$this->assertSame( 'forbidden', $result['items'][0]['status'] );
		$this->assertSame( $file, get_attached_file( $id ) );
	}

	/**
	 * Tokens: confirm required, bound to the user, used once, expiring.
	 */
	public function test_token_rules() {
		$id      = $this->jpeg( 'token' );
		$preview = $this->preview( [ $id => 'mcpren-token-new' ] );
		$this->assertSame( 'ability_invalid_input', wp_get_ability( 'tsmlt/rename-media' )->execute( [ 'confirmation_token' => $preview['confirmation_token'] ] )->get_error_code(), 'Schema.' );
		$tool = RenameMedia::instance();
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
		$this->assertSame( McpRename::ERROR_TOKEN, self::rename( str_repeat( 'a', 64 ) )->get_error_code() );

		$owner = get_current_user_id();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( McpRename::ERROR_TOKEN, self::rename( $preview['confirmation_token'] )->get_error_code(), 'Another user.' );
		wp_set_current_user( $owner );
		$this->assertSame( 'renamed', self::rename( $preview['confirmation_token'] )['items'][0]['result'] );
		$this->assertSame( McpRename::ERROR_TOKEN, self::rename( $preview['confirmation_token'] )->get_error_code(), 'Replay.' );

		$preview            = $this->preview( [ $id => 'mcpren-token-later' ] );
		$option             = ConfirmationToken::option( McpRename::TOKEN_OPTION_PREFIX, $preview['confirmation_token'] );
		$payload            = get_option( $option );
		$payload['expires'] = time() - 1;
		update_option( $option, $payload, false );
		$this->assertSame( McpRename::ERROR_TOKEN, self::rename( $preview['confirmation_token'] )->get_error_code(), 'Expired.' );
		$this->assertSame( 'mcpren-token-new.jpg', wp_basename( get_attached_file( $id ) ) );
	}

	/**
	 * A running bulk rename refuses execution and keeps the token.
	 */
	public function test_refuses_while_job_running() {
		$a       = $this->jpeg( 'run-a' );
		$b       = $this->jpeg( 'run-b' );
		$preview = $this->preview( [ $a => 'mcpren-run-new' ] );
		$job     = RenameJob::instance()->start( [ $b => 'mcpren-run-other' ] );

		$result = self::rename( $preview['confirmation_token'] );

		$this->assertSame( McpRename::ERROR_JOB_RUNNING, $result->get_error_code() );
		$this->assertSame( $job['job_id'], RenameJob::instance()->get_state()['job_id'] );
		$this->assertSame( 'mcpren-run-a.jpg', wp_basename( get_attached_file( $a ) ) );
		RenameJob::instance()->cancel( $job['job_id'] );
		$this->assertSame( 'renamed', self::rename( $preview['confirmation_token'] )['items'][0]['result'], 'The token was kept.' );
	}

	/**
	 * Cancel stops only the matching running rename job; a regeneration job
	 * is never touched.
	 */
	public function test_cancel() {
		$a     = $this->jpeg( 'cancel-a' );
		$b     = $this->jpeg( 'cancel-b' );
		$regen = RegenerateThumbnails::instance()->start( [ $a ] );
		$this->assertIsArray( $regen );
		$preview = $this->preview(
			[
				$a => 'mcpren-cancel-one',
				$b => 'mcpren-cancel-two',
			]
		);
		$started = self::rename( $preview['confirmation_token'] );

		$this->assertSame( McpRename::ERROR_JOB_MISMATCH, self::call( 'tsmlt/cancel-media-rename', [ 'job_id' => 'tsmlt_rename_0000000000000000' ] )->get_error_code() );
		$this->assertSame( McpRename::ERROR_JOB_MISMATCH, self::call( 'tsmlt/cancel-media-rename', [ 'job_id' => $regen['job_id'] ] )->get_error_code(), 'A regeneration job ID is not a rename job.' );
		$this->assertSame( 'ability_invalid_input', self::call( 'tsmlt/cancel-media-rename', [] )->get_error_code(), 'Schema.' );
		$this->assertSame( 'tsmlt_invalid_input', CancelMediaRename::instance()->execute( [] )->get_error_code() );

		$cancelled = self::call( 'tsmlt/cancel-media-rename', [ 'job_id' => $started['job_id'] ] );

		$this->assertSame( 'cancelled', $cancelled['status'] );
		$this->assert_schema( 'tsmlt/cancel-media-rename', $cancelled );
		$this->assertSame( McpRename::ERROR_NOT_RUNNING, self::call( 'tsmlt/cancel-media-rename', [ 'job_id' => $started['job_id'] ] )->get_error_code() );
		RenameJob::instance()->run_batch();
		$this->assertSame( 'mcpren-cancel-a.jpg', wp_basename( get_attached_file( $a ) ), 'Nothing renamed after cancel.' );
		$state = RegenerateThumbnails::instance()->get_state();
		$this->assertSame( $regen['job_id'], $state['job_id'] );
		$this->assertSame( 'running', $state['status'], 'The regeneration job is untouched.' );
	}

	/**
	 * Status: results for attachments the user may not read are hidden,
	 * and changed posts the user may not read are counted only.
	 */
	public function test_status_hides_unreadable() {
		$a       = $this->jpeg( 'see-a' );
		$b       = $this->jpeg( 'see-b' );
		$post    = (int) self::factory()->post->create(
			[
				'post_content' => '<img src="' . wp_get_attachment_url( $b ) . '">',
				'post_status'  => 'private',
				'post_author'  => self::factory()->user->create( [ 'role' => 'editor' ] ),
			]
		);
		$preview = $this->preview(
			[
				$a => 'mcpren-see-one',
				$b => 'mcpren-see-two',
			]
		);
		self::rename( $preview['confirmation_token'] );
		RenameJob::instance()->run_batch();

		$this->unreadable = [ $a, $post ];
		$status           = self::call( 'tsmlt/get-media-rename-status' );

		$this->assertSame( [ $b ], array_column( $status['results']['items'], 'id' ) );
		$this->assertSame( 1, $status['results']['hidden'] );
		$this->assertSame( [], $status['results']['items'][0]['content_posts'] );
		$this->assertSame( 1, $status['results']['items'][0]['content_posts_hidden'] );
		$this->assertSame( 2, $status['renamed'], 'Counts include every item.' );
	}

	/**
	 * No folders, paths or raw errors in any response.
	 */
	public function test_no_paths_in_responses() {
		$a       = $this->jpeg( 'path-a' );
		$b       = $this->jpeg( 'path-b' );
		$preview = $this->preview(
			[
				$a => 'mcpren-path-one',
				$b => 'mcpren-path-two',
			]
		);
		$started = self::rename( $preview['confirmation_token'] );
		RenameJob::instance()->run_batch();
		$status = self::call( 'tsmlt/get-media-rename-status' );
		$base   = wp_upload_dir()['basedir'];
		foreach ( [ $preview['items'], $started['items'], $status ] as $output ) {
			$json = wp_json_encode( $output );
			$this->assertStringNotContainsString( $base, $json );
			$this->assertStringNotContainsString( '\/', $json, 'No folders.' );
			$this->assertStringNotContainsString( ABSPATH, $json );
		}
	}

	/**
	 * The manage_options capability is required by the permission callback and again in
	 * execute(); every tool is dedicated-only.
	 */
	public function test_permissions_and_exposure() {
		$names = [ 'tsmlt/preview-media-rename', 'tsmlt/rename-media', 'tsmlt/get-media-rename-status', 'tsmlt/cancel-media-rename' ];
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		foreach ( $names as $name ) {
			$ability = wp_get_ability( $name );
			$this->assertNotTrue( $ability->check_permissions( [] ), $name );
			$this->assertWPError( $ability->execute( [] ), $name );
			$this->assertFalse( $ability->get_meta()['mcp']['public'], $name );
		}
		$this->assertTrue( wp_get_ability( 'tsmlt/rename-media' )->get_meta()['annotations']['destructive'] );
		$this->assertTrue( wp_get_ability( 'tsmlt/get-media-rename-status' )->get_meta()['annotations']['readonly'] );
	}
}
