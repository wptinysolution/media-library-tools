<?php
/**
 * The MCP conversion tools: tsmlt/preview-media-conversion,
 * tsmlt/convert-media and tsmlt/cancel-conversion-job.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\ConfirmationToken;
use TinySolutions\mlt\Abilities\Media\CancelConversionJob;
use TinySolutions\mlt\Abilities\Media\ConvertMedia;
use TinySolutions\mlt\Abilities\Media\PreviewMediaConversion;
use TinySolutions\mlt\Modules\Compress\AttachmentProcessor;
use TinySolutions\mlt\Modules\Compress\CompressionJob;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionCapabilities;
use TinySolutions\mlt\Modules\Compress\Conversion\McpConversion;
use TinySolutions\mlt\Modules\ExifData\ExifStripper;
use TinySolutions\mlt\Modules\Regenerate\RegenerateLock;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;
use TinySolutions\mlt\Modules\UsedWhere\ScanStartLock;

/**
 * ConversionMcpAbilitiesTest
 */
class ConversionMcpAbilitiesTest extends WP_UnitTestCase {

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
	 * Administrator, fresh folder, no jobs.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/preview-media-conversion' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		if ( ! ConversionCapabilities::instance()->supports_format( 'webp' ) ) {
			$this->markTestSkipped( 'This server cannot write WebP.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->dir = trailingslashit( wp_get_upload_dir()['basedir'] ) . 'tsmlt-mcpconv-' . wp_generate_password( 8, false, false );
		wp_mkdir_p( $this->dir );
		$this->denied = [];
		$this->writes = [];
		delete_option( CompressionJob::STATE_OPTION );
		delete_option( RegenerateThumbnails::STATE_OPTION );
		wp_clear_scheduled_hook( CompressionJob::TICK_HOOK );
		add_filter( 'map_meta_cap', [ $this, 'deny' ], 10, 4 );
	}

	/**
	 * Remove fixtures.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny' ], 10 );
		remove_filter( 'query', [ $this, 'record_query' ] );
		foreach ( $this->ids as $id ) {
			delete_option( ExifStripper::LOCK_PREFIX . $id );
		}
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			wp_delete_file( $file );
		}
		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture.
		}
		delete_option( CompressionJob::STATE_OPTION );
		delete_option( RegenerateThumbnails::STATE_OPTION );
		wp_clear_scheduled_hook( CompressionJob::TICK_HOOK );
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
	 * An image attachment in the test folder.
	 *
	 * @param string $name   File name.
	 * @param string $format jpeg, png or webp.
	 *
	 * @return int
	 */
	private function image( string $name, string $format = 'jpeg' ): int {
		$path   = $this->dir . '/' . $name;
		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/canola.jpg' );
		$editor->resize( 120, 90, true );
		$editor->save( $path, 'image/' . $format );
		$id          = (int) self::factory()->attachment->create_object(
			[
				'file'           => $path,
				'post_mime_type' => 'image/' . $format,
			]
		);
		$this->ids[] = $id;
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
	 * @param array $input Input.
	 *
	 * @return array
	 */
	private function preview( array $input ): array {
		$result = self::call( 'tsmlt/preview-media-conversion', $input );
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
			'tsmlt/convert-media',
			[
				'confirmation_token' => $token,
				'confirm'            => true,
			]
		);
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
					'job_type'   => CompressionJob::TYPE_CONVERSION,
					'status'     => 'running',
					'queue'      => [ 1 ],
					'total'      => 2,
					'processed'  => 1,
					'started_at' => 1700000000,
					'updated_at' => 1700000100,
				],
				$state
			),
			false
		);
	}

	/**
	 * Every preview status; nothing written but the token.
	 */
	public function test_preview_statuses_and_read_only() {
		$ready     = $this->image( 'ok.jpg' );
		$converted = $this->image( 'done.jpg' );
		AttachmentConverter::instance()->convert(
			$converted,
			[
				'formats'         => [ 'webp' ],
				'quality'         => [ 'webp' => 80 ],
				'generated_sizes' => false,
			]
		);
		$webp     = $this->image( 'already.webp', 'webp' );
		$gif      = (int) self::factory()->attachment->create_object(
			[
				'file'           => 'x.gif',
				'post_mime_type' => 'image/gif',
			]
		);
		$missing  = $this->image( 'missing.jpg' );
		$conflict = $this->image( 'clash.jpg' );
		$stray    = $this->dir . '/clash.webp';
		copy( $this->dir . '/already.webp', $stray );
		$locks = [ $this->image( 'l1.jpg' ), $this->image( 'l2.jpg' ), $this->image( 'l3.jpg' ), $this->image( 'l4.jpg' ) ];
		update_post_meta( $locks[0], AttachmentProcessor::LOCK_META_KEY, time() );
		update_post_meta( $locks[1], AttachmentConverter::LOCK_META_KEY, time() );
		$regen   = RegenerateLock::acquire( $locks[2] );
		$exif    = ScanStartLock::instance()->acquire( ExifStripper::LOCK_PREFIX . $locks[3] );
		$trashed = $this->image( 'trash.jpg' );
		wp_trash_post( $trashed );
		$hidden   = $this->image( 'hidden.jpg' );
		$readonly = $this->image( 'readonly.jpg' );
		$post     = (int) self::factory()->post->create();
		wp_delete_file( $this->dir . '/missing.jpg' );
		$this->denied = [
			$hidden   => 'read_post',
			$readonly => 'edit_post',
		];
		$stray_hash   = md5_file( $stray );

		add_filter( 'query', [ $this, 'record_query' ] );
		$result = self::call( 'tsmlt/preview-media-conversion', [ 'ids' => array_merge( [ $ready, $converted, $webp, $gif, $missing, $conflict ], $locks, [ $trashed, $hidden, $readonly, $post, 999999 ] ) ] );
		remove_filter( 'query', [ $this, 'record_query' ] );
		RegenerateLock::release( $locks[2], $regen );
		ScanStartLock::instance()->release( $exif, ExifStripper::LOCK_PREFIX . $locks[3] );

		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertSame(
			[ 'ready', 'already_converted', 'same_format', 'unsupported', 'file_missing', 'name_conflict', 'locked', 'locked', 'locked', 'locked', 'not_found', 'not_found', 'forbidden', 'not_found', 'not_found' ],
			array_column( $result['items'], 'status' )
		);
		$this->assertSame( [ 'webp' ], $result['settings']['formats'] );
		$this->assertFalse( $result['settings']['generated_sizes'] );
		$this->assertFalse( $result['pro_active'] );
		$this->assertSame( [ 'avif', 'quality', 'generated_sizes' ], $result['pro_only_options'] );
		$this->assertSame( 20, $result['job_limit'] );
		$this->assertSame( 1, $result['ready_count'] );
		foreach ( $this->writes as $sql ) {
			$this->assertStringContainsString( McpConversion::TOKEN_OPTION_PREFIX, $sql, 'Only the token is written.' );
		}
		$this->assertFileDoesNotExist( $this->dir . '/ok.webp', 'Nothing converted.' );
		$this->assertSame( $stray_hash, md5_file( $stray ) );
		$this->assertFalse( get_option( CompressionJob::STATE_OPTION ), 'No job.' );
	}

	/**
	 * Invalid input, including a whole-library request, is refused.
	 */
	public function test_invalid_input() {
		foreach ( [
			[],
			[ 'ids' => [] ],
			[ 'ids' => range( 1, 21 ) ],
			[ 'ids' => [ 1, 1 ] ],
			[ 'ids' => [ '1' ] ],
			[ 'scope' => 'library' ],
			[
				'ids'   => [ 1 ],
				'scope' => 'library',
			],
			[
				'ids'     => [ 1 ],
				'formats' => [ 'jpg' ],
			],
			[
				'ids'     => [ 1 ],
				'formats' => [],
			],
			[
				'ids'     => [ 1 ],
				'quality' => [ 'webp' => 0 ],
			],
			[
				'ids'     => [ 1 ],
				'quality' => [ 'avif' => 60 ],
			],
			[
				'ids'             => [ 1 ],
				'generated_sizes' => 'yes',
			],
		] as $input ) {
			$result = PreviewMediaConversion::instance()->execute( $input );
			$this->assertWPError( $result, wp_json_encode( $input ) );
			$this->assertSame( 'tsmlt_invalid_input', $result->get_error_code(), wp_json_encode( $input ) );
		}
	}

	/**
	 * Without Pro: AVIF, custom quality and generated sizes are refused, never
	 * dropped; asking for generated sizes off is fine.
	 */
	public function test_pro_options_refused_on_free() {
		$id = $this->image( 'free.jpg' );
		foreach ( [
			[ 'formats' => [ 'avif' ] ],
			[ 'formats' => [ 'webp', 'avif' ] ],
			[ 'quality' => [ 'webp' => 70 ] ],
			[ 'generated_sizes' => true ],
		] as $option ) {
			$result = self::call( 'tsmlt/preview-media-conversion', [ 'ids' => [ $id ] ] + $option );
			$this->assertWPError( $result, wp_json_encode( $option ) );
			$this->assertSame( McpConversion::ERROR_PRO_REQUIRED, $result->get_error_code() );
		}
		$off = $this->preview(
			[
				'ids'             => [ $id ],
				'generated_sizes' => false,
			]
		);
		$this->assertIsString( $off['confirmation_token'] );
	}

	/**
	 * Logged-out users and non-administrators are refused by every tool.
	 */
	public function test_permissions() {
		foreach ( [ 0, (int) self::factory()->user->create( [ 'role' => 'editor' ] ) ] as $user ) {
			wp_set_current_user( $user );
			foreach ( [ PreviewMediaConversion::instance(), ConvertMedia::instance(), CancelConversionJob::instance() ] as $tool ) {
				$this->assertFalse( $tool->check_permission() );
				$result = $tool->execute( [] );
				$this->assertWPError( $result );
				$this->assertContains( $result->get_error_code(), [ 'tsmlt_unauthenticated', 'tsmlt_forbidden' ] );
			}
		}
	}

	/**
	 * Start: the conversion job with the ready IDs; nothing converted in the request.
	 */
	public function test_start_creates_async_job() {
		$a       = $this->image( 'sa.jpg' );
		$b       = $this->image( 'sb.png', 'png' );
		$post    = (int) self::factory()->post->create();
		$preview = $this->preview( [ 'ids' => [ $a, $post, $b ] ] );

		$result = self::start( $preview['confirmation_token'] );

		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertSame( 'conversion', $result['job_type'] );
		$this->assertSame( 'running', $result['status'] );
		$this->assertSame( 2, $result['total'] );
		$this->assertSame( [ $a, $b ], array_column( $result['items'], 'id' ) );
		$state = CompressionJob::instance()->get_state();
		$this->assertSame( [ $a, $b ], $state['queue'] );
		$this->assertSame( [ 'webp' ], $state['settings']['formats'] );
		$this->assertNotFalse( wp_next_scheduled( CompressionJob::TICK_HOOK ) );
		$this->assertFileDoesNotExist( $this->dir . '/sa.webp', 'Nothing converted in the request.' );

		CompressionJob::instance()->run_batch( false );
		$this->assertFileExists( $this->dir . '/sa.webp' );
		$this->assertFileExists( $this->dir . '/sb.webp' );
	}

	/**
	 * Changes since the preview: locked, conflicting or trashed images are
	 * left out; with none left nothing starts.
	 */
	public function test_start_rechecks_images() {
		$a       = $this->image( 'ra.jpg' );
		$b       = $this->image( 'rb.jpg' );
		$c       = $this->image( 'rc.jpg' );
		$d       = $this->image( 'rd.jpg' );
		$preview = $this->preview( [ 'ids' => [ $a, $b, $c, $d ] ] );
		update_post_meta( $b, AttachmentProcessor::LOCK_META_KEY, time() );
		copy( DIR_TESTDATA . '/images/canola.jpg', $this->dir . '/rc.webp' );
		wp_trash_post( $d );

		$result = self::start( $preview['confirmation_token'] );

		$this->assertSame( [ 'ready', 'locked', 'name_conflict', 'not_found' ], array_column( $result['items'], 'status' ) );
		$this->assertSame( [ $a ], CompressionJob::instance()->get_state()['queue'] );

		delete_option( CompressionJob::STATE_OPTION );
		$preview      = $this->preview( [ 'ids' => [ $a ] ] );
		$this->denied = [ $a => 'edit_post' ];
		$this->assertSame( McpConversion::ERROR_NOTHING, self::start( $preview['confirmation_token'] )->get_error_code() );
		$this->assertFalse( get_option( CompressionJob::STATE_OPTION ) );
	}

	/**
	 * Changed settings since the preview refuse the run.
	 */
	public function test_settings_changed_since_preview() {
		$id       = $this->image( 'set.jpg' );
		$settings = (array) get_option( 'tsmlt_settings', [] );
		update_option( 'tsmlt_settings', [ 'conversion_webp_quality' => 80 ] + $settings );
		$preview = $this->preview( [ 'ids' => [ $id ] ] );
		update_option( 'tsmlt_settings', [ 'conversion_webp_quality' => 60 ] + $settings );

		$result = self::start( $preview['confirmation_token'] );

		$this->assertSame( McpConversion::ERROR_SETTINGS_CHANGED, $result->get_error_code() );
		$this->assertFalse( get_option( CompressionJob::STATE_OPTION ) );
	}

	/**
	 * Tokens: confirm required, ownership, replay, expiry.
	 */
	public function test_token_rules() {
		$id      = $this->image( 'tok.jpg' );
		$tool    = ConvertMedia::instance();
		$preview = $this->preview( [ 'ids' => [ $id ] ] );
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
		$this->assertSame( McpConversion::ERROR_TOKEN, self::start( $preview['confirmation_token'] )->get_error_code() );
		wp_set_current_user( $owner );
		$this->assertIsArray( self::start( $preview['confirmation_token'] ) );

		CompressionJob::instance()->cancel();
		$this->assertSame( McpConversion::ERROR_TOKEN, self::start( $preview['confirmation_token'] )->get_error_code(), 'Replay.' );

		delete_option( CompressionJob::STATE_OPTION );
		$preview            = $this->preview( [ 'ids' => [ $id ] ] );
		$option             = ConfirmationToken::option( McpConversion::TOKEN_OPTION_PREFIX, $preview['confirmation_token'] );
		$payload            = get_option( $option );
		$payload['expires'] = time() - 1;
		update_option( $option, $payload, false );
		$this->assertSame( McpConversion::ERROR_TOKEN, self::start( $preview['confirmation_token'] )->get_error_code(), 'Expired.' );
		$this->assertFalse( get_option( CompressionJob::STATE_OPTION ) );
	}

	/**
	 * A running compression or conversion job refuses a start and keeps the token.
	 */
	public function test_refuses_while_job_running() {
		$id      = $this->image( 'busy.jpg' );
		$preview = $this->preview( [ 'ids' => [ $id ] ] );
		$input   = $preview['confirmation_token'];

		foreach ( [ CompressionJob::TYPE_COMPRESSION, CompressionJob::TYPE_CONVERSION ] as $type ) {
			self::set_job( [ 'job_type' => $type ] );
			$result = self::start( $input );
			$this->assertSame( McpConversion::ERROR_JOB_RUNNING, $result->get_error_code() );
			$this->assertSame( 'tsmlt_testjob', CompressionJob::instance()->get_state()['job_id'] );
		}
		delete_option( CompressionJob::STATE_OPTION );
		$this->assertIsArray( self::start( $input ), 'The token was kept.' );
	}

	/**
	 * Cancel only the current, running conversion job; never compression or
	 * thumbnail regeneration jobs.
	 */
	public function test_cancel_only_conversion_jobs() {
		$this->assertSame( McpConversion::ERROR_JOB_MISMATCH, self::call( 'tsmlt/cancel-conversion-job', [ 'job_id' => 'tsmlt_testjob' ] )->get_error_code(), 'No job.' );

		self::set_job( [ 'job_type' => CompressionJob::TYPE_COMPRESSION ] );
		$stored = get_option( CompressionJob::STATE_OPTION );
		$this->assertSame( McpConversion::ERROR_NOT_CONVERSION, self::call( 'tsmlt/cancel-conversion-job', [ 'job_id' => 'tsmlt_testjob' ] )->get_error_code() );
		$this->assertSame( $stored, get_option( CompressionJob::STATE_OPTION ), 'The compression job is untouched.' );

		$regen = RegenerateThumbnails::instance()->start( [ $this->image( 'rg.jpg' ) ] );
		$this->assertSame( McpConversion::ERROR_JOB_MISMATCH, self::call( 'tsmlt/cancel-conversion-job', [ 'job_id' => $regen['job_id'] ] )->get_error_code() );
		$this->assertSame( 'running', RegenerateThumbnails::instance()->get_state()['status'], 'The regeneration job is untouched.' );

		self::set_job( [] );
		$this->assertSame( McpConversion::ERROR_JOB_MISMATCH, self::call( 'tsmlt/cancel-conversion-job', [ 'job_id' => 'tsmlt_other' ] )->get_error_code() );
		$result = self::call( 'tsmlt/cancel-conversion-job', [ 'job_id' => 'tsmlt_testjob' ] );
		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertSame( 'cancelled', $result['status'] );
		$this->assertSame( McpConversion::ERROR_NOT_RUNNING, self::call( 'tsmlt/cancel-conversion-job', [ 'job_id' => 'tsmlt_testjob' ] )->get_error_code(), 'Cancelled job.' );

		self::set_job( [ 'status' => 'completed' ] );
		$this->assertSame( McpConversion::ERROR_NOT_RUNNING, self::call( 'tsmlt/cancel-conversion-job', [ 'job_id' => 'tsmlt_testjob' ] )->get_error_code(), 'Completed job.' );
	}

	/**
	 * No paths or file names in any response.
	 */
	public function test_no_paths_or_names() {
		$id      = $this->image( 'privatename.jpg' );
		$preview = $this->preview( [ 'ids' => [ $id ] ] );
		$start   = self::start( $preview['confirmation_token'] );
		$json    = wp_json_encode( [ $preview, $start ] );
		foreach ( [ wp_get_upload_dir()['basedir'], 'privatename', 'tsmlt-mcpconv' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, $needle );
		}
	}

	/**
	 * Listed by name, not runnable through the default gateway.
	 */
	public function test_not_mcp_public() {
		foreach ( [ 'tsmlt/preview-media-conversion', 'tsmlt/convert-media', 'tsmlt/cancel-conversion-job' ] as $name ) {
			$this->assertFalse( wp_get_ability( $name )->get_meta()['mcp']['public'], $name );
		}
		$this->assertTrue( wp_get_ability( 'tsmlt/convert-media' )->get_meta()['annotations']['destructive'] );
	}
}
