<?php
/**
 * The MCP compression tools: tsmlt/preview-compression,
 * tsmlt/compress-media, tsmlt/get-compression-job-status and
 * tsmlt/cancel-compression-job.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\Media\CancelCompressionJob;
use TinySolutions\mlt\Abilities\Media\CompressMedia;
use TinySolutions\mlt\Abilities\Media\GetCompressionJobStatus;
use TinySolutions\mlt\Abilities\Media\PreviewCompression;
use TinySolutions\mlt\Modules\Compress\BackupManager;
use TinySolutions\mlt\Modules\Compress\CompressionAccess;
use TinySolutions\mlt\Modules\Compress\CompressionJob;
use TinySolutions\mlt\Modules\Compress\CompressionMetadata;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\Compress\McpCompression;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;

/**
 * CompressionMcpAbilitiesTest
 */
class CompressionMcpAbilitiesTest extends WP_UnitTestCase {

	/**
	 * A stored error text that must never be returned.
	 *
	 * @var string
	 */
	const SECRET = 'unable to open image /var/www/secret/uploads/photo.jpg';

	/**
	 * Attachments to delete.
	 *
	 * @var int[]
	 */
	private $ids = [];

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
	 * Administrator, no job, no tokens.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/preview-compression' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		if ( ! CompressionAccess::instance()->is_compression_feature_available() ) {
			$this->markTestSkipped( 'No image compression library is available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->denied = [];
		$this->writes = [];
		delete_option( CompressionJob::STATE_OPTION );
		wp_clear_scheduled_hook( CompressionJob::TICK_HOOK );
		add_filter( 'map_meta_cap', [ $this, 'deny' ], 10, 4 );
	}

	/**
	 * Remove fixtures, the job and filters.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny' ], 10 );
		remove_filter( 'query', [ $this, 'record_query' ] );
		delete_option( CompressionJob::STATE_OPTION );
		wp_clear_scheduled_hook( CompressionJob::TICK_HOOK );
		foreach ( $this->ids as $id ) {
			BackupManager::instance()->delete_attachment_backups( $id );
			delete_option( ExifStripper::LOCK_PREFIX . $id );
			wp_delete_attachment( $id, true );
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
	 * Upload a JPEG.
	 *
	 * @return int
	 */
	private function jpeg(): int {
		$id          = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->ids[] = $id;
		return $id;
	}

	/**
	 * Hash of an attachment's file.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return string
	 */
	private static function hash( int $id ): string {
		clearstatcache();
		return (string) md5_file( get_attached_file( $id ) );
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
	 * @param array $input Preview input.
	 *
	 * @return array
	 */
	private function preview( array $input ): array {
		$result = self::call( 'tsmlt/preview-compression', $input );
		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertIsString( $result['confirmation_token'], wp_json_encode( $result ) );
		return $result;
	}

	/**
	 * Store a job state.
	 *
	 * @param array $state State.
	 */
	private static function set_job( array $state ) {
		update_option(
			CompressionJob::STATE_OPTION,
			array_merge(
				[
					'job_id'     => 'tsmlt_testjob',
					'job_type'   => CompressionJob::TYPE_COMPRESSION,
					'status'     => 'running',
					'queue'      => [ 1, 2 ],
					'total'      => 4,
					'processed'  => 2,
					'succeeded'  => 1,
					'failed'     => 1,
					'started_at' => 1700000000,
					'updated_at' => 1700000100,
				],
				$state
			),
			false
		);
	}

	/**
	 * Preview statuses for every case; Free settings; nothing changed but the token.
	 */
	public function test_preview_reports_each_status_and_is_read_only() {
		$ready      = $this->jpeg();
		$compressed = $this->jpeg();
		CompressionMetadata::instance()->save( $compressed, [ 'status' => 'completed' ] );
		$locked = $this->jpeg();
		update_post_meta( $locked, AttachmentConverter::LOCK_META_KEY, time() );
		$missing = $this->jpeg();
		wp_delete_file( get_attached_file( $missing ) );
		$text        = (int) self::factory()->attachment->create_object(
			[
				'file'           => 'notes.txt',
				'post_mime_type' => 'text/plain',
			]
		);
		$this->ids[] = $text;
		$hidden      = $this->jpeg();
		$readonly    = $this->jpeg();
		$post        = (int) self::factory()->post->create();
		$trashed     = $this->jpeg();
		wp_trash_post( $trashed );
		$this->denied = [
			$hidden   => 'read_post',
			$readonly => 'edit_post',
		];
		$hashes       = [];
		foreach ( [ $ready, $compressed, $locked, $hidden, $readonly ] as $id ) {
			$hashes[ $id ] = self::hash( $id );
		}

		$backup_root = trailingslashit( wp_get_upload_dir()['basedir'] ) . BackupManager::BACKUP_DIRNAME;
		$had_backups = is_dir( $backup_root );
		add_filter( 'query', [ $this, 'record_query' ] );
		$result = self::call( 'tsmlt/preview-compression', [ 'ids' => [ $ready, $compressed, $locked, $missing, $text, $hidden, $readonly, $post, $trashed ] ] );
		remove_filter( 'query', [ $this, 'record_query' ] );

		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertSame(
			[ 'ready', 'already_compressed', 'locked', 'file_missing', 'unsupported', 'not_found', 'forbidden', 'not_found', 'not_found' ],
			array_column( $result['items'], 'status' )
		);
		$this->assertSame( 1, $result['ready_count'] );
		$this->assertSame( 'selected', $result['scope'] );
		$this->assertFalse( $result['settings']['backup_originals'], 'Free keeps no backup.' );
		$this->assertFalse( $result['settings']['use_custom_quality'] );
		$this->assertFalse( $result['settings']['compress_generated_sizes'] );
		$this->assertFalse( $result['reversible'] );
		$this->assertSame( CompressionAccess::FREE_JOB_LIMIT, $result['job_limit'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $result['confirmation_token'] );
		$this->assertNotSame( '', $result['expires_at'] );

		foreach ( $this->writes as $sql ) {
			$this->assertStringContainsString( McpCompression::TOKEN_OPTION_PREFIX, $sql, 'Only the token is written.' );
		}
		foreach ( $hashes as $id => $hash ) {
			$this->assertSame( $hash, self::hash( $id ), 'No image changed.' );
		}
		$this->assertFalse( get_option( CompressionJob::STATE_OPTION ), 'No job was created.' );
		$this->assertFalse( (bool) wp_next_scheduled( CompressionJob::TICK_HOOK ) );
		$this->assertSame( [], CompressionMetadata::instance()->get( $ready ) );
		$this->assertSame( $had_backups, is_dir( $backup_root ), 'The backup folder was not created.' );
		$this->assertFalse( BackupManager::instance()->has_backup_read_only( $ready ), 'No backup was made.' );
	}

	/**
	 * With include_compressed an already compressed image is ready again.
	 */
	public function test_include_compressed() {
		$id = $this->jpeg();
		CompressionMetadata::instance()->save( $id, [ 'status' => 'skipped' ] );
		$result = $this->preview(
			[
				'ids'                => [ $id ],
				'include_compressed' => true,
			]
		);
		$this->assertSame( 'ready', $result['items'][0]['status'] );
	}

	/**
	 * Nothing ready: no token.
	 */
	public function test_no_token_when_nothing_is_ready() {
		$post   = (int) self::factory()->post->create();
		$result = self::call( 'tsmlt/preview-compression', [ 'ids' => [ $post ] ] );
		$this->assertNull( $result['confirmation_token'] );
		$this->assertSame( 0, $result['ready_count'] );
	}

	/**
	 * Invalid input is refused by the ability itself.
	 */
	public function test_invalid_preview_input() {
		$preview = PreviewCompression::instance();
		foreach ( [
			[ 'ids' => [] ],
			[ 'ids' => range( 1, 21 ) ],
			[ 'ids' => [ 1, 1 ] ],
			[ 'ids' => [ '1' ] ],
			[],
			[
				'scope' => 'library',
				'ids'   => [ 1 ],
			],
			[ 'scope' => 'everything' ],
			[
				'ids'  => [ 1 ],
				'mode' => 'lossless',
			],
			[
				'ids'     => [ 1 ],
				'quality' => 0,
			],
			[
				'ids'   => [ 1 ],
				'extra' => true,
			],
		] as $input ) {
			$result = $preview->execute( $input );
			$this->assertWPError( $result, wp_json_encode( $input ) );
			$this->assertSame( 'tsmlt_invalid_input', $result->get_error_code(), wp_json_encode( $input ) );
		}
	}

	/**
	 * Pro-only options are refused without Pro; asking for them off is fine.
	 */
	public function test_pro_options_refused_on_free() {
		$id = $this->jpeg();
		foreach ( [ [ 'quality' => 70 ], [ 'backup_originals' => true ], [ 'compress_generated_sizes' => true ] ] as $option ) {
			$result = self::call( 'tsmlt/preview-compression', [ 'ids' => [ $id ] ] + $option );
			$this->assertWPError( $result );
			$this->assertSame( McpCompression::ERROR_PRO_REQUIRED, $result->get_error_code() );
		}
		$result = $this->preview(
			[
				'ids'                      => [ $id ],
				'backup_originals'         => false,
				'compress_generated_sizes' => false,
				'mode'                     => 'maximum',
			]
		);
		$this->assertSame( 'maximum', $result['settings']['mode'] );
	}

	/**
	 * Logged-out users and non-administrators are refused by every tool,
	 * in the permission callback and in execute().
	 */
	public function test_permissions() {
		foreach ( [ 0, (int) self::factory()->user->create( [ 'role' => 'editor' ] ) ] as $user ) {
			wp_set_current_user( $user );
			foreach ( [ PreviewCompression::instance(), CompressMedia::instance(), GetCompressionJobStatus::instance(), CancelCompressionJob::instance() ] as $tool ) {
				$this->assertFalse( $tool->check_permission() );
				$result = $tool->execute( [] );
				$this->assertWPError( $result );
				$this->assertContains( $result->get_error_code(), [ 'tsmlt_unauthenticated', 'tsmlt_forbidden' ] );
			}
		}
	}

	/**
	 * One image: the existing asynchronous job is started; nothing is
	 * compressed in the request.
	 */
	public function test_compress_single_image_starts_async_job() {
		$id      = $this->jpeg();
		$hash    = self::hash( $id );
		$preview = $this->preview( [ 'ids' => [ $id ] ] );

		$result = self::call(
			'tsmlt/compress-media',
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);

		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertSame( 'compression', $result['job_type'] );
		$this->assertSame( 'running', $result['status'] );
		$this->assertSame( 'selected', $result['scope'] );
		$this->assertSame( 1, $result['total'] );
		$this->assertFalse( $result['reversible'] );
		$this->assertSame(
			[
				[
					'id'     => $id,
					'status' => 'ready',
					'queued' => true,
				],
			],
			$result['items']
		);
		$state = CompressionJob::instance()->get_state();
		$this->assertSame( $result['job_id'], $state['job_id'] );
		$this->assertSame( [ $id ], $state['queue'] );
		$this->assertFalse( $state['settings']['backup_originals'] );
		$this->assertNotFalse( wp_next_scheduled( CompressionJob::TICK_HOOK ), 'Runs through WP-Cron.' );
		$this->assertSame( $hash, self::hash( $id ), 'Nothing is compressed in the request.' );

		// The job compresses the image without a backup (Free).
		CompressionJob::instance()->run_batch( false );
		$this->assertContains( CompressionMetadata::instance()->get( $id )['status'] ?? '', [ 'completed', 'skipped' ] );
		$this->assertFalse( BackupManager::instance()->has_backup_read_only( $id ), 'No backup on Free.' );
	}

	/**
	 * Selected images: only those still ready at execution are queued.
	 */
	public function test_compress_selected_rechecks_each_image() {
		$a       = $this->jpeg();
		$b       = $this->jpeg();
		$c       = $this->jpeg();
		$preview = $this->preview( [ 'ids' => [ $a, $b, $c ] ] );
		$this->assertSame( 3, $preview['ready_count'] );

		$taken = time();
		update_post_meta( $b, AttachmentConverter::LOCK_META_KEY, $taken );
		$lock = ScanStartLock::instance()->acquire( ExifStripper::LOCK_PREFIX . $c );
		$this->assertIsString( $lock );

		$result = self::call(
			'tsmlt/compress-media',
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		ScanStartLock::instance()->release( $lock, ExifStripper::LOCK_PREFIX . $c );

		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertSame( 1, $result['total'] );
		$this->assertSame( [ true, false, false ], array_column( $result['items'], 'queued' ) );
		$this->assertSame( [ 'ready', 'locked', 'locked' ], array_column( $result['items'], 'status' ) );
		$this->assertSame( [ $a ], CompressionJob::instance()->get_state()['queue'] );
		$this->assertSame( (string) $taken, (string) get_post_meta( $b, AttachmentConverter::LOCK_META_KEY, true ), 'The conversion lock is untouched.' );
	}

	/**
	 * Every previewed image locked at execution: nothing is started.
	 */
	public function test_compress_refuses_when_nothing_is_ready_any_more() {
		$id      = $this->jpeg();
		$preview = $this->preview( [ 'ids' => [ $id ] ] );
		$lock    = ScanStartLock::instance()->acquire( ExifStripper::LOCK_PREFIX . $id );

		$result = self::call(
			'tsmlt/compress-media',
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		ScanStartLock::instance()->release( $lock, ExifStripper::LOCK_PREFIX . $id );

		$this->assertWPError( $result );
		$this->assertSame( McpCompression::ERROR_NOTHING, $result->get_error_code() );
		$this->assertFalse( get_option( CompressionJob::STATE_OPTION ) );
	}

	/**
	 * Library scope: counts, the Free limit, and a library job.
	 */
	public function test_library_scope() {
		$a = $this->jpeg();
		$b = $this->jpeg();
		CompressionMetadata::instance()->save( $b, [ 'status' => 'completed' ] );

		$preview = $this->preview( [ 'scope' => 'library' ] );
		$this->assertSame( [], $preview['items'] );
		$this->assertSame( 2, $preview['library']['total'] );
		$this->assertSame( 1, $preview['library']['compressed'] );
		$this->assertSame( 1, $preview['library']['to_queue'] );

		$result = self::call(
			'tsmlt/compress-media',
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertSame( 'library', $result['scope'] );
		$this->assertSame( 1, $result['total'] );
		$this->assertSame( [ $a ], CompressionJob::instance()->get_state()['queue'] );
		$this->assertSame( CompressionAccess::FREE_JOB_LIMIT, $result['job_limit'] );
	}

	/**
	 * The Free per-job limit caps a library run.
	 */
	public function test_library_preview_applies_free_limit() {
		for ( $i = 0; $i < CompressionAccess::FREE_JOB_LIMIT + 1; $i++ ) {
			$this->ids[] = (int) self::factory()->attachment->create_object(
				[
					'file'           => "bulk-$i.jpg",
					'post_mime_type' => 'image/jpeg',
				]
			);
		}
		$preview = $this->preview( [ 'scope' => 'library' ] );
		$this->assertSame( CompressionAccess::FREE_JOB_LIMIT + 1, $preview['library']['total'] );
		$this->assertSame( CompressionAccess::FREE_JOB_LIMIT, $preview['library']['to_queue'] );
	}

	/**
	 * Tokens: confirm required, single use, expiry, ownership.
	 */
	public function test_token_rules() {
		$id       = $this->jpeg();
		$compress = CompressMedia::instance();

		$preview = $this->preview( [ 'ids' => [ $id ] ] );
		$this->assertSame( 'tsmlt_invalid_input', $compress->execute( [ 'confirmation_token' => $preview['confirmation_token'] ] )->get_error_code() );
		$this->assertSame(
			'tsmlt_invalid_input',
			$compress->execute(
				[
					'confirmation_token' => $preview['confirmation_token'],
					'confirm'            => false,
				]
			)->get_error_code()
		);

		// Another administrator cannot use it; the owner still can.
		$owner = get_current_user_id();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$other = $compress->execute(
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$this->assertSame( McpCompression::ERROR_TOKEN, $other->get_error_code() );
		wp_set_current_user( $owner );
		$first = $compress->execute(
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$this->assertIsArray( $first, wp_json_encode( $first ) );

		// Replay.
		CompressionJob::instance()->cancel();
		$replay = $compress->execute(
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$this->assertSame( McpCompression::ERROR_TOKEN, $replay->get_error_code() );

		// Expired.
		delete_option( CompressionJob::STATE_OPTION );
		$preview            = $this->preview( [ 'ids' => [ $id ] ] );
		$option             = McpCompression::TOKEN_OPTION_PREFIX . hash( 'sha256', $preview['confirmation_token'] );
		$payload            = get_option( $option );
		$payload['expires'] = time() - 1;
		update_option( $option, $payload, false );
		$expired = $compress->execute(
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$this->assertSame( McpCompression::ERROR_TOKEN, $expired->get_error_code() );
		$this->assertFalse( get_option( CompressionJob::STATE_OPTION ), 'Nothing started.' );
	}

	/**
	 * A running compression or conversion job refuses a start and keeps the token.
	 */
	public function test_refuses_while_a_job_is_running() {
		$id      = $this->jpeg();
		$preview = $this->preview( [ 'ids' => [ $id ] ] );
		$this->assertFalse( $preview['job_running'] );
		$input = [
			'confirmation_token' => $preview['confirmation_token'],
			'confirm'            => true,
		];

		foreach ( [ CompressionJob::TYPE_CONVERSION, CompressionJob::TYPE_COMPRESSION ] as $type ) {
			self::set_job( [ 'job_type' => $type ] );
			$result = self::call( 'tsmlt/compress-media', $input );
			$this->assertWPError( $result );
			$this->assertSame( McpCompression::ERROR_JOB_RUNNING, $result->get_error_code() );
			$this->assertSame( 'tsmlt_testjob', CompressionJob::instance()->get_state()['job_id'], 'The running job is untouched.' );
		}

		// The token was not used up.
		delete_option( CompressionJob::STATE_OPTION );
		$this->assertIsArray( self::call( 'tsmlt/compress-media', $input ) );
	}

	/**
	 * Changed settings between preview and execution refuse the run.
	 */
	public function test_settings_changed_since_preview() {
		$id       = $this->jpeg();
		$settings = (array) get_option( 'tsmlt_settings', [] );
		update_option( 'tsmlt_settings', [ 'compression_mode' => 'balanced' ] + $settings );
		$preview = $this->preview( [ 'ids' => [ $id ] ] );
		update_option( 'tsmlt_settings', [ 'compression_mode' => 'maximum' ] + $settings );

		$result = self::call(
			'tsmlt/compress-media',
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$this->assertWPError( $result );
		$this->assertSame( McpCompression::ERROR_SETTINGS_CHANGED, $result->get_error_code() );
		$this->assertFalse( get_option( CompressionJob::STATE_OPTION ) );
	}

	/**
	 * Job status: read-only, corrections not saved, no error texts.
	 */
	public function test_job_status_is_read_only() {
		$this->assertSame( 'idle', self::call( 'tsmlt/get-compression-job-status' )['status'] );

		self::set_job(
			[
				'queue'         => [],
				'last_error'    => self::SECRET,
				'recent_errors' => [ [ 'message' => self::SECRET ] ],
			]
		);
		$stored = get_option( CompressionJob::STATE_OPTION );

		add_filter( 'query', [ $this, 'record_query' ] );
		$result = self::call( 'tsmlt/get-compression-job-status', [] );
		remove_filter( 'query', [ $this, 'record_query' ] );

		$this->assertSame( 'partial', $result['status'], 'A running job with an empty queue is finished.' );
		$this->assertSame( 'tsmlt_testjob', $result['job_id'] );
		$this->assertSame( 'compression', $result['job_type'] );
		$this->assertSame( 50, $result['percent'] );
		$this->assertSame( 2, $result['remaining'] );
		$this->assertSame( '2023-11-14 22:13:20', $result['started_at'] );
		$this->assertSame( [], $this->writes );
		$this->assertSame( $stored, get_option( CompressionJob::STATE_OPTION ), 'The stored job is unchanged.' );
		$this->assertStringNotContainsString( '/var/www', wp_json_encode( $result ) );

		self::set_job( [ 'cancelled_at' => time() ] );
		$this->assertSame( 'cancelled', self::call( 'tsmlt/get-compression-job-status' )['status'] );
	}

	/**
	 * Cancel only the current, running compression job.
	 */
	public function test_cancel_only_compression_jobs() {
		$this->assertSame( McpCompression::ERROR_JOB_MISMATCH, self::call( 'tsmlt/cancel-compression-job', [ 'job_id' => 'tsmlt_testjob' ] )->get_error_code() );

		self::set_job( [ 'job_type' => CompressionJob::TYPE_CONVERSION ] );
		$stored = get_option( CompressionJob::STATE_OPTION );
		$result = self::call( 'tsmlt/cancel-compression-job', [ 'job_id' => 'tsmlt_testjob' ] );
		$this->assertSame( McpCompression::ERROR_NOT_COMPRESSION, $result->get_error_code() );
		$this->assertSame( $stored, get_option( CompressionJob::STATE_OPTION ), 'The conversion job is untouched.' );

		self::set_job( [] );
		$this->assertSame( McpCompression::ERROR_JOB_MISMATCH, self::call( 'tsmlt/cancel-compression-job', [ 'job_id' => 'tsmlt_other' ] )->get_error_code() );
		$result = self::call( 'tsmlt/cancel-compression-job', [ 'job_id' => 'tsmlt_testjob' ] );
		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertSame( 'cancelled', $result['status'] );
		$this->assertSame( [], CompressionJob::instance()->get_state()['queue'] );
		$this->assertSame( McpCompression::ERROR_NOT_RUNNING, self::call( 'tsmlt/cancel-compression-job', [ 'job_id' => 'tsmlt_testjob' ] )->get_error_code() );
	}

	/**
	 * No paths, file names or stored error texts in any response.
	 */
	public function test_no_paths_or_errors_in_responses() {
		$id = $this->jpeg();
		CompressionMetadata::instance()->save(
			$id,
			[
				'status'     => 'failed',
				'last_error' => self::SECRET,
			]
		);
		$preview = $this->preview( [ 'ids' => [ $id ] ] );
		$start   = self::call(
			'tsmlt/compress-media',
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$status  = self::call( 'tsmlt/get-compression-job-status' );
		$json    = wp_json_encode( [ $preview, $start, $status ] );
		foreach ( [ wp_get_upload_dir()['basedir'], basename( get_attached_file( $id ) ), '/var/www', 'last_error', '"queue":' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, $needle );
		}
	}

	/**
	 * The tools are listed by name but not run through the default gateway.
	 */
	public function test_not_mcp_public() {
		foreach ( [ 'tsmlt/preview-compression', 'tsmlt/compress-media', 'tsmlt/get-compression-job-status', 'tsmlt/cancel-compression-job' ] as $name ) {
			$this->assertFalse( wp_get_ability( $name )->get_meta()['mcp']['public'], $name );
		}
		$this->assertTrue( wp_get_ability( 'tsmlt/compress-media' )->get_meta()['annotations']['destructive'] );
		$this->assertTrue( wp_get_ability( 'tsmlt/get-compression-job-status' )->get_meta()['annotations']['readonly'] );
	}
}
