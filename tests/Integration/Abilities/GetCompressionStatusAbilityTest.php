<?php
/**
 * `tsmlt/get-compression-status`: per-attachment compression status from
 * the stored compression data, read-only, no error texts or paths; listed on
 * the dedicated and the default server.
 *
 * Compression data is stored directly in the feature's format (no image is
 * compressed). Every attachment is a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Abilities\Media\GetCompressionStatus;
use TinySolutions\mlt\Modules\Compress\BackupManager;
use TinySolutions\mlt\Modules\Compress\CompressionJob;
use TinySolutions\mlt\Modules\Compress\CompressionMetadata;

/**
 * GetCompressionStatusAbilityTest
 */
class GetCompressionStatusAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/get-compression-status';

	/**
	 * A stored error text that must never be returned.
	 *
	 * @var string
	 */
	const ERROR_TEXT = 'unable to open image /var/www/secret/uploads/photo.jpg';

	/**
	 * Files and folders to remove.
	 *
	 * @var string[]
	 */
	private $temp_files = [];

	/**
	 * Denied meta caps per attachment ID.
	 *
	 * @var array<int, string>
	 */
	private $denied = [];

	/**
	 * Statements other than reads sent while observing.
	 *
	 * @var string[]
	 */
	private $writes = [];

	/**
	 * MCP session per route.
	 *
	 * @var array<string, string>
	 */
	private $sessions = [];

	/**
	 * Administrator; no backup folder.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( self::NAME ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->denied   = [];
		$this->writes   = [];
		$this->sessions = [];
		$this->remove_backup_root();
		add_filter( 'map_meta_cap', [ $this, 'deny' ], 10, 4 );
	}

	/**
	 * Remove fixtures and filters.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny' ], 10 );
		remove_filter( 'query', [ $this, 'record_query' ] );
		foreach ( $this->temp_files as $file ) {
			if ( is_string( $file ) && is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
		$this->remove_backup_root();
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
	 * The backup root folder.
	 *
	 * @return string
	 */
	private static function backup_root(): string {
		return trailingslashit( wp_get_upload_dir()['basedir'] ) . BackupManager::BACKUP_DIRNAME;
	}

	/**
	 * Remove the backup root folder and everything in it.
	 */
	private function remove_backup_root() {
		$root = self::backup_root();
		if ( ! is_dir( $root ) ) {
			return;
		}
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $files as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture.
			} else {
				wp_delete_file( $file->getPathname() );
			}
		}
		rmdir( $root ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture.
	}

	/**
	 * An attachment of a MIME type (no file).
	 *
	 * @param string $mime MIME type.
	 * @param string $name File name.
	 *
	 * @return int
	 */
	private function attachment( string $mime, string $name ): int {
		return (int) self::factory()->attachment->create(
			[
				'post_mime_type' => $mime,
				'file'           => '2026/11/' . $name,
			]
		);
	}

	/**
	 * Store compression data in the feature's format.
	 *
	 * @param int   $id      Attachment ID.
	 * @param array $changes Fields to change.
	 *
	 * @return void
	 */
	private static function store( int $id, array $changes = [] ) {
		update_post_meta(
			$id,
			CompressionMetadata::META_KEY,
			array_replace_recursive(
				[
					'version'       => 1,
					'status'        => 'completed',
					'original'      => [
						'size'      => 1000,
						'mime_type' => 'image/jpeg',
						'file'      => '2026/11/secret-relative.jpg',
					],
					'compressed'    => [
						'size'          => 600,
						'saved_bytes'   => 400,
						'saved_percent' => 40.0,
					],
					'settings'      => [
						'quality'         => 82,
						'mode'            => 'lossy',
						'engine'          => 'imagick',
						'generated_sizes' => true,
					],
					'backup'        => [
						'enabled'   => true,
						'available' => false,
						'directory' => BackupManager::BACKUP_DIRNAME,
					],
					'sizes'         => [
						'full'      => [
							'before' => 800,
							'after'  => 480,
							'status' => 'compressed',
							'reason' => '',
						],
						'thumbnail' => [
							'before' => 200,
							'after'  => 200,
							'status' => 'skipped',
							'reason' => 'no_improvement',
						],
					],
					'compressed_at' => '2026-11-18 10:00:00',
					'last_error'    => self::ERROR_TEXT,
				],
				$changes
			)
		);
	}

	/**
	 * Run the ability.
	 *
	 * @param int[] $ids IDs.
	 *
	 * @return mixed
	 */
	private static function call( array $ids ) {
		return wp_get_ability( self::NAME )->execute( [ 'ids' => $ids ] );
	}

	/**
	 * Items by ID.
	 *
	 * @param int[] $ids IDs.
	 *
	 * @return array<int, array>
	 */
	private static function items( array $ids ): array {
		return array_column( self::call( $ids )['items'], null, 'id' );
	}

	/**
	 * A single ID: the stored data, as reported.
	 */
	public function test_single_completed() {
		$id = $this->attachment( 'image/jpeg', 'done.jpg' );
		self::store( $id );

		$result = self::call( [ $id ] );
		$item   = $result['items'][0];

		$this->assertSame( $id, $item['id'] );
		$this->assertSame( 'completed', $item['status'] );
		$this->assertSame( 1000, $item['original_size'] );
		$this->assertSame( 600, $item['current_size'] );
		$this->assertSame( 400, $item['bytes_saved'] );
		$this->assertEquals( 40.0, $item['percent_saved'] );
		$this->assertSame( 'lossy', $item['mode'] );
		$this->assertSame( 82, $item['quality'] );
		$this->assertSame( 'imagick', $item['engine'] );
		$this->assertSame( '2026-11-18 10:00:00', $item['compressed_at'] );
		$this->assertFalse( $item['backup_exists'] );
		$this->assertSame(
			[
				[
					'name'   => 'full',
					'before' => 800,
					'after'  => 480,
					'status' => 'compressed',
					'reason' => '',
				],
				[
					'name'   => 'thumbnail',
					'before' => 200,
					'after'  => 200,
					'status' => 'skipped',
					'reason' => 'no_improvement',
				],
			],
			$item['generated_sizes']
		);
		$this->assertSame( 1, $result['summary']['completed'] );
		$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), wp_get_ability( self::NAME )->get_output_schema(), 'result' ) );
	}

	/**
	 * Every status, in one request, in the order asked.
	 */
	public function test_all_statuses() {
		$ids = [
			'completed'     => $this->attachment( 'image/jpeg', 'a.jpg' ),
			'skipped'       => $this->attachment( 'image/png', 'b.png' ),
			'failed'        => $this->attachment( 'image/webp', 'c.webp' ),
			'not_processed' => $this->attachment( 'image/jpeg', 'd.jpg' ),
			'unsupported'   => $this->attachment( 'image/gif', 'e.gif' ),
			'svg'           => $this->attachment( 'image/svg+xml', 'f.svg' ),
			'pdf'           => $this->attachment( 'application/pdf', 'g.pdf' ),
		];
		self::store( $ids['completed'] );
		self::store( $ids['skipped'], [ 'status' => 'skipped' ] );
		self::store( $ids['failed'], [ 'status' => 'failed' ] );

		$result = self::call( array_values( $ids ) );
		$this->assertSame( array_values( $ids ), array_column( $result['items'], 'id' ) );
		$this->assertSame( [ 'completed', 'skipped', 'failed', 'not_processed', 'unsupported', 'unsupported', 'unsupported' ], array_column( $result['items'], 'status' ) );
		$this->assertSame( 3, $result['summary']['unsupported'] );

		$none = $result['items'][3];
		$this->assertSame( 0, $none['original_size'] );
		$this->assertSame( [], $none['generated_sizes'] );
		$this->assertSame( '', $none['compressed_at'] );
	}

	/**
	 * A stored status the feature does not write is never reported as a
	 * success.
	 */
	public function test_unknown_stored_status_is_failed() {
		$id = $this->attachment( 'image/jpeg', 'odd.jpg' );
		self::store( $id, [ 'status' => 'done!' ] );
		$this->assertSame( 'failed', self::items( [ $id ] )[ $id ]['status'] );
	}

	/**
	 * Missing, non-media, trashed, unreadable and uneditable attachments.
	 */
	public function test_access_statuses() {
		$post    = self::factory()->post->create();
		$hidden  = $this->attachment( 'image/jpeg', 'hidden.jpg' );
		$locked  = $this->attachment( 'image/jpeg', 'locked.jpg' );
		$trashed = $this->attachment( 'image/jpeg', 'trashed.jpg' );
		self::store( $hidden );
		self::store( $locked );
		wp_trash_post( $trashed );
		$this->denied[ $hidden ] = 'read_post';
		$this->denied[ $locked ] = 'edit_post';

		$items = self::items( [ 999999, $post, $hidden, $locked, $trashed ] );
		$this->assertSame( 'not_found', $items[999999]['status'] );
		$this->assertSame( 'not_found', $items[ $post ]['status'] );
		$this->assertSame( 'not_found', $items[ $hidden ]['status'] );
		$this->assertSame( 'forbidden', $items[ $locked ]['status'] );
		$this->assertSame( 'not_found', $items[ $trashed ]['status'] );
		$this->assertSame( 0, $items[ $hidden ]['original_size'], 'Nothing about an unreadable attachment.' );
		$this->assertSame( 0, $items[ $locked ]['original_size'], 'Nothing about an uneditable attachment.' );
	}

	/**
	 * Invalid requests are refused as a whole.
	 */
	public function test_invalid_input() {
		$id = $this->attachment( 'image/jpeg', 'x.jpg' );
		foreach (
			[
				'no ids'     => [],
				'empty'      => [ 'ids' => [] ],
				'51 ids'     => [ 'ids' => range( 1, 51 ) ],
				'string id'  => [ 'ids' => [ (string) $id ] ],
				'zero'       => [ 'ids' => [ 0 ] ],
				'negative'   => [ 'ids' => [ -1 ] ],
				'float'      => [ 'ids' => [ 1.5 ] ],
				'duplicates' => [ 'ids' => [ $id, $id ] ],
				'not a list' => [ 'ids' => [ 'a' => $id ] ],
				'extra key'  => [
					'ids'  => [ $id ],
					'path' => '/',
				],
			] as $label => $input
		) {
			$this->assertWPError( GetCompressionStatus::instance()->execute( $input ), $label );
		}
		$this->assertCount( 50, GetCompressionStatus::instance()->execute( [ 'ids' => range( 1, 50 ) ] )['items'], '50 is allowed.' );
	}

	/**
	 * Only administrators.
	 */
	public function test_requires_manage_options() {
		$id = $this->attachment( 'image/jpeg', 'perm.jpg' );
		foreach ( [ 'editor', 'author' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertFalse( wp_get_ability( self::NAME )->check_permissions( [ 'ids' => [ $id ] ] ), $role );
			$this->assertWPError( GetCompressionStatus::instance()->execute( [ 'ids' => [ $id ] ] ), $role );
		}
		wp_set_current_user( 0 );
		$this->assertWPError( GetCompressionStatus::instance()->execute( [ 'ids' => [ $id ] ] ) );
	}

	/**
	 * No error texts or paths; reasons, sizes names, mode and engine only as
	 * codes.
	 */
	public function test_no_error_text_or_paths() {
		$id = $this->attachment( 'image/jpeg', 'error.jpg' );
		self::store(
			$id,
			[
				'settings' => [
					'mode'   => '<b>lossy</b>',
					'engine' => 'Imagick 7.1 /usr/lib',
				],
				'sizes'    => [
					'thumbnail' => [
						'status' => 'failed',
						'reason' => self::ERROR_TEXT,
					],
					'medium'    => [
						'before' => 300,
						'after'  => 300,
						'status' => 'exploded',
						'reason' => 'tsmlt_compression_engine_error',
					],
				],
			]
		);

		$item = self::items( [ $id ] )[ $id ];
		$json = wp_json_encode( $item );
		foreach ( [ self::ERROR_TEXT, '/var/www', 'secret-relative', wp_upload_dir()['basedir'], 'last_error', '<b>', '/usr/lib' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, $needle );
		}
		$sizes = array_column( $item['generated_sizes'], null, 'name' );
		$this->assertSame( '', $sizes['thumbnail']['reason'], 'Text is not a code.' );
		$this->assertSame( 'failed', $sizes['thumbnail']['status'] );
		$this->assertSame( '', $sizes['medium']['status'], 'Unknown size status dropped.' );
		$this->assertSame( 'tsmlt_compression_engine_error', $sizes['medium']['reason'] );
		$this->assertSame( '', $item['mode'] );
		$this->assertSame( '', $item['engine'] );
	}

	/**
	 * A backup is reported when its file exists; checking never creates the
	 * backup folder.
	 */
	public function test_backup_exists() {
		$with    = $this->attachment( 'image/jpeg', 'with-backup.jpg' );
		$without = $this->attachment( 'image/jpeg', 'without-backup.jpg' );
		self::store( $with, [ 'backup' => [ 'available' => true ] ] );
		self::store( $without, [ 'backup' => [ 'available' => true ] ] );

		$this->assertFalse( self::items( [ $with ] )[ $with ]['backup_exists'] );
		$this->assertDirectoryDoesNotExist( self::backup_root(), 'The check does not create the backup folder.' );

		$backup = self::backup_root() . '/2026/11/with-backup.jpg';
		wp_mkdir_p( dirname( $backup ) );
		file_put_contents( $backup, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$items = self::items( [ $with, $without ] );
		$this->assertTrue( $items[ $with ]['backup_exists'] );
		$this->assertFalse( $items[ $without ]['backup_exists'] );
		$this->assertFileDoesNotExist( self::backup_root() . '/index.php', 'Protection files are not written by a read.' );
	}

	/**
	 * Nothing is written: no queries other than reads, job state (even a
	 * state get_progress() would rewrite), meta, metadata and files unchanged.
	 */
	public function test_read_only() {
		$id = $this->attachment( 'image/jpeg', 'readonly.jpg' );
		self::store( $id, [ 'backup' => [ 'available' => true ] ] );
		update_option(
			CompressionJob::STATE_OPTION,
			[
				'job_id'       => 'job-1',
				'status'       => 'running',
				'queue'        => [],
				'cancelled_at' => 0,
				'failed'       => 0,
			],
			false
		);
		$job      = get_option( CompressionJob::STATE_OPTION );
		$meta     = get_post_meta( $id );
		$metadata = wp_get_attachment_metadata( $id );
		$post     = get_post( $id, ARRAY_A );
		$uploads  = scandir( wp_get_upload_dir()['basedir'] );
		$cron     = _get_cron_array();
		add_filter( 'query', [ $this, 'record_query' ] );

		self::call( [ $id ] );

		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertSame( [], $this->writes );
		$this->assertSame( $job, get_option( CompressionJob::STATE_OPTION ), 'Job state not healed or saved.' );
		$this->assertSame( $meta, get_post_meta( $id ) );
		$this->assertSame( $metadata, wp_get_attachment_metadata( $id ) );
		$this->assertSame( $post, get_post( $id, ARRAY_A ) );
		clearstatcache();
		$this->assertSame( $uploads, scandir( wp_get_upload_dir()['basedir'] ) );
		$this->assertSame( $cron, _get_cron_array() );
	}

	/**
	 * Listed by name on the dedicated and the default server and runs on
	 * both; not offered through the default gateway.
	 */
	public function test_listed_on_both_servers() {
		$this->assertFalse( wp_get_ability( self::NAME )->get_meta()['mcp']['public'] );
		$this->assertTrue( wp_get_ability( self::NAME )->get_meta()['annotations']['readonly'] );
		$this->assertContains( self::NAME, McpServerRegistration::instance()->get_tool_names() );

		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) || ! McpServerRegistration::adapter_supports_default_server() ) {
			$this->markTestSkipped( 'MCP Adapter 0.5.0+ not loaded; set MCP_ADAPTER_DIR to run the transport part.' );
		}
		$id = $this->attachment( 'image/jpeg', 'transport.jpg' );
		self::store( $id );
		foreach ( [ '/media-library-tools/mcp', '/mcp/mcp-adapter-default-server' ] as $route ) {
			$this->connect( $route );
			$tools = array_column( $this->send( $route, 'tools/list' )['result']['tools'], null, 'name' );
			$this->assertArrayHasKey( 'tsmlt-get-compression-status', $tools, $route );
			$this->assertFalse( $tools['tsmlt-get-compression-status']['inputSchema']['additionalProperties'], $route );
			$call = $this->send(
				$route,
				'tools/call',
				[
					'name'      => 'tsmlt-get-compression-status',
					'arguments' => [ 'ids' => [ $id ] ],
				]
			)['result'];
			$this->assertFalse( $call['isError'], $route . ' ' . wp_json_encode( $call ) );
			$this->assertSame( 'completed', $call['structuredContent']['items'][0]['status'] );
			$this->assertStringNotContainsString( self::ERROR_TEXT, wp_json_encode( $call ) );
		}

		foreach ( [ 'mcp-adapter-get-ability-info', 'mcp-adapter-execute-ability' ] as $gateway ) {
			$arguments = 'mcp-adapter-get-ability-info' === $gateway
				? [ 'ability_name' => self::NAME ]
				: [
					'ability_name' => self::NAME,
					'parameters'   => [ 'ids' => [ $id ] ],
				];
			$result    = $this->send(
				'/mcp/mcp-adapter-default-server',
				'tools/call',
				[
					'name'      => $gateway,
					'arguments' => $arguments,
				]
			);
			$refused   = isset( $result['error'] ) || true === ( $result['result']['isError'] ?? false );
			$this->assertTrue( $refused, $gateway . ' refuses it.' );
		}
	}

	/**
	 * Send one JSON-RPC message.
	 *
	 * @param string $route  Route.
	 * @param string $method Method.
	 * @param array  $params Params.
	 *
	 * @return array
	 */
	private function send( string $route, string $method, array $params = [] ): array {
		static $request_id = 0;
		$request           = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'Accept', 'application/json, text/event-stream' );
		if ( isset( $this->sessions[ $route ] ) ) {
			$request->set_header( 'Mcp-Session-Id', $this->sessions[ $route ] );
			$request->set_header( 'MCP-Protocol-Version', '2025-11-25' );
		}
		$request->set_body(
			wp_json_encode(
				[
					'jsonrpc' => '2.0',
					'id'      => ++$request_id,
					'method'  => $method,
					'params'  => (object) $params,
				]
			)
		);
		$response = rest_get_server()->dispatch( $request );
		if ( 'initialize' === $method ) {
			$this->sessions[ $route ] = $response->get_headers()['Mcp-Session-Id'];
		}
		$data = $response->get_data();
		return is_array( $data ) ? $data : json_decode( wp_json_encode( $data ), true );
	}

	/**
	 * Open a session.
	 *
	 * @param string $route Route.
	 */
	private function connect( string $route ) {
		$this->send(
			$route,
			'initialize',
			[
				'protocolVersion' => '2025-11-25',
				'capabilities'    => (object) [],
				'clientInfo'      => [
					'name'    => 'tsmlt-tests',
					'version' => '1.0.0',
				],
			]
		);
		$this->send( $route, 'notifications/initialized' );
	}
}
