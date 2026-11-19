<?php
/**
 * `tsmlt/get-conversion-status`: per-attachment WebP/AVIF conversion status
 * from the stored conversion data, read-only, no error texts or paths;
 * listed on the dedicated and the default server.
 *
 * Conversion data is stored directly in the feature's format (no image is
 * converted). Every attachment is a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Abilities\Media\GetConversionStatus;
use TinySolutions\mlt\Modules\Compress\CompressionJob;
use TinySolutions\mlt\Modules\Compress\Conversion\AttachmentConverter;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionMetadata;

/**
 * GetConversionStatusAbilityTest
 */
class GetConversionStatusAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/get-conversion-status';

	/**
	 * A stored error text that must never be returned.
	 *
	 * @var string
	 */
	const ERROR_TEXT = 'unable to open image /var/www/secret/uploads/photo.jpg';

	/**
	 * A stored relative path that must never be returned.
	 *
	 * @var string
	 */
	const RELATIVE_FILE = '2026/11/secret-output.webp';

	/**
	 * Files to remove.
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
	 * Administrator.
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
	 * A JPEG attachment with a real file.
	 *
	 * @param string $name File name.
	 *
	 * @return int
	 */
	private function jpeg( string $name ): int {
		$temp = trailingslashit( get_temp_dir() ) . $name;
		copy( DIR_TESTDATA . '/images/test-square-150.jpg', $temp );
		$this->temp_files[] = $temp;
		$id                 = (int) self::factory()->attachment->create_upload_object( $temp );
		$this->temp_files[] = get_attached_file( $id );
		return $id;
	}

	/**
	 * Store conversion data in the feature's format.
	 *
	 * @param int   $id      Attachment ID.
	 * @param array $changes Fields to change.
	 *
	 * @return void
	 */
	private static function store( int $id, array $changes = [] ) {
		update_post_meta(
			$id,
			ConversionMetadata::META_KEY,
			array_replace_recursive(
				[
					'version'    => 1,
					'status'     => 'completed',
					'source'     => [
						'file'      => '2026/11/secret-source.jpg',
						'mime_type' => 'image/jpeg',
						'size'      => 1000,
						'modified'  => 0,
					],
					'formats'    => [
						'webp' => [
							'status'       => 'completed',
							'file'         => self::RELATIVE_FILE,
							'size'         => 600,
							'mime_type'    => 'image/webp',
							'quality'      => 80,
							'generated_at' => '2026-11-18 10:00:00',
							'sizes'        => [
								'thumbnail' => [
									'status' => 'completed',
									'file'   => '2026/11/secret-thumb.webp',
									'size'   => 50,
								],
							],
						],
					],
					'last_error' => self::ERROR_TEXT,
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
	 * One ID: the stored data, as reported.
	 */
	public function test_single_completed() {
		$id = $this->attachment( 'image/jpeg', 'done.jpg' );
		self::store( $id );

		$result = self::call( [ $id ] );
		$item   = $result['items'][0];

		$this->assertSame( $id, $item['id'] );
		$this->assertSame( 'completed', $item['status'] );
		$this->assertSame( 1000, $item['source_size'] );
		$this->assertTrue( $item['stale'], 'The source file is missing.' );
		$this->assertFalse( $item['converting'] );
		$this->assertSame(
			[
				[
					'format'                => 'webp',
					'status'                => 'completed',
					'size'                  => 600,
					'percent_saved'         => 40.0,
					'quality'               => 80,
					'generated_at'          => '2026-11-18 10:00:00',
					'generated_sizes_count' => 1,
					'reason'                => '',
				],
			],
			$item['formats']
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
			'partial'       => $this->attachment( 'image/png', 'b.png' ),
			'skipped'       => $this->attachment( 'image/webp', 'c.webp' ),
			'failed'        => $this->attachment( 'image/jpeg', 'd.jpg' ),
			'not_converted' => $this->attachment( 'image/png', 'e.png' ),
			'gif'           => $this->attachment( 'image/gif', 'f.gif' ),
			'svg'           => $this->attachment( 'image/svg+xml', 'g.svg' ),
			'avif'          => $this->attachment( 'image/avif', 'h.avif' ),
			'pdf'           => $this->attachment( 'application/pdf', 'i.pdf' ),
		];
		self::store( $ids['completed'] );
		self::store(
			$ids['partial'],
			[
				'status'  => 'partial',
				'formats' => [
					'avif' => [
						'status' => 'failed',
						'reason' => 'tsmlt_conversion_engine_error',
					],
				],
			]
		);
		self::store(
			$ids['skipped'],
			[
				'status'  => 'skipped',
				'formats' => [
					'webp' => [
						'status' => 'skipped',
						'reason' => 'same_format',
					],
				],
			]
		);
		self::store( $ids['failed'], [ 'status' => 'failed' ] );

		$result = self::call( array_values( $ids ) );
		$this->assertSame( array_values( $ids ), array_column( $result['items'], 'id' ) );
		$this->assertSame( [ 'completed', 'partial', 'skipped', 'failed', 'not_converted', 'unsupported', 'unsupported', 'unsupported', 'unsupported' ], array_column( $result['items'], 'status' ) );
		$this->assertSame( 4, $result['summary']['unsupported'] );

		$partial = array_column( $result['items'][1]['formats'], null, 'format' );
		$this->assertSame( 'failed', $partial['avif']['status'] );
		$this->assertSame( 'tsmlt_conversion_engine_error', $partial['avif']['reason'] );
		$this->assertSame( 'same_format', $result['items'][2]['formats'][0]['reason'] );
		$this->assertSame( [], $result['items'][4]['formats'] );
		$this->assertSame( 0, $result['items'][4]['source_size'] );
	}

	/**
	 * Unknown stored statuses are never reported as a success.
	 */
	public function test_unknown_status_is_failed() {
		$id = $this->attachment( 'image/jpeg', 'odd.jpg' );
		self::store(
			$id,
			[
				'status'  => 'done!',
				'formats' => [
					'webp' => [ 'status' => 'great' ],
				],
			]
		);
		$item = self::items( [ $id ] )[ $id ];
		$this->assertSame( 'failed', $item['status'] );
		$this->assertSame( 'failed', $item['formats'][0]['status'] );
	}

	/**
	 * Stale follows the source file: matching size and time is current, a
	 * different size or a missing file is stale.
	 */
	public function test_stale() {
		$id   = $this->jpeg( 'stale-source.jpg' );
		$file = get_attached_file( $id );
		self::store(
			$id,
			[
				'source' => [
					'size'     => filesize( $file ),
					'modified' => filemtime( $file ),
				],
			]
		);
		$this->assertFalse( self::items( [ $id ] )[ $id ]['stale'] );

		self::store( $id, [ 'source' => [ 'size' => filesize( $file ) + 1 ] ] );
		$this->assertTrue( self::items( [ $id ] )[ $id ]['stale'] );
	}

	/**
	 * Converting follows the converter's lock; a lock older than 10 minutes
	 * is not a running conversion.
	 */
	public function test_converting() {
		$converted = $this->attachment( 'image/jpeg', 'locked.jpg' );
		$fresh     = $this->attachment( 'image/jpeg', 'first-run.jpg' );
		$old       = $this->attachment( 'image/jpeg', 'old-lock.jpg' );
		self::store( $converted );
		update_post_meta( $converted, AttachmentConverter::LOCK_META_KEY, time() );
		update_post_meta( $fresh, AttachmentConverter::LOCK_META_KEY, time() );
		update_post_meta( $old, AttachmentConverter::LOCK_META_KEY, time() - 11 * MINUTE_IN_SECONDS );

		$items = self::items( [ $converted, $fresh, $old ] );
		$this->assertTrue( $items[ $converted ]['converting'] );
		$this->assertTrue( $items[ $fresh ]['converting'] );
		$this->assertSame( 'not_converted', $items[ $fresh ]['status'] );
		$this->assertFalse( $items[ $old ]['converting'] );
	}

	/**
	 * Missing, non-media, trashed, unreadable and uneditable attachments.
	 */
	public function test_access_statuses() {
		$post    = self::factory()->post->create();
		$hidden  = $this->attachment( 'image/jpeg', 'hidden.jpg' );
		$locked  = $this->attachment( 'image/jpeg', 'locked-out.jpg' );
		$trashed = $this->attachment( 'image/jpeg', 'trashed.jpg' );
		self::store( $hidden );
		self::store( $locked );
		update_post_meta( $locked, AttachmentConverter::LOCK_META_KEY, time() );
		wp_trash_post( $trashed );
		$this->denied[ $hidden ] = 'read_post';
		$this->denied[ $locked ] = 'edit_post';

		$items = self::items( [ 999999, $post, $hidden, $locked, $trashed ] );
		$this->assertSame( 'not_found', $items[999999]['status'] );
		$this->assertSame( 'not_found', $items[ $post ]['status'] );
		$this->assertSame( 'not_found', $items[ $hidden ]['status'] );
		$this->assertSame( 'forbidden', $items[ $locked ]['status'] );
		$this->assertSame( 'not_found', $items[ $trashed ]['status'] );
		foreach ( [ $hidden, $locked ] as $id ) {
			$this->assertSame( 0, $items[ $id ]['source_size'] );
			$this->assertSame( [], $items[ $id ]['formats'] );
			$this->assertFalse( $items[ $id ]['converting'], 'Nothing about an inaccessible attachment.' );
		}
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
					'ids'    => [ $id ],
					'format' => 'webp',
				],
			] as $label => $input
		) {
			$this->assertWPError( GetConversionStatus::instance()->execute( $input ), $label );
		}
		$this->assertCount( 50, GetConversionStatus::instance()->execute( [ 'ids' => range( 1, 50 ) ] )['items'], '50 is allowed.' );
		$this->assertCount( 2, self::call( [ $id, 999999 ] )['items'] );
	}

	/**
	 * Only administrators.
	 */
	public function test_requires_manage_options() {
		$id = $this->attachment( 'image/jpeg', 'perm.jpg' );
		foreach ( [ 'editor', 'author' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertFalse( wp_get_ability( self::NAME )->check_permissions( [ 'ids' => [ $id ] ] ), $role );
			$this->assertWPError( GetConversionStatus::instance()->execute( [ 'ids' => [ $id ] ] ), $role );
		}
		wp_set_current_user( 0 );
		$this->assertWPError( GetConversionStatus::instance()->execute( [ 'ids' => [ $id ] ] ) );
	}

	/**
	 * No error texts or paths; unknown formats skipped; reasons only as
	 * codes.
	 */
	public function test_no_error_text_or_paths() {
		$id = $this->attachment( 'image/jpeg', 'error.jpg' );
		self::store(
			$id,
			[
				'status'  => 'partial',
				'formats' => [
					'webp' => [ 'reason' => self::ERROR_TEXT ],
					'avif' => [
						'status' => 'failed',
						'reason' => '<script>x</script>',
					],
					'jxl'  => [
						'status' => 'completed',
						'size'   => 1,
					],
				],
			]
		);

		$item = self::items( [ $id ] )[ $id ];
		$json = wp_json_encode( $item );
		foreach ( [ self::ERROR_TEXT, '/var/www', self::RELATIVE_FILE, 'secret-source', 'secret-thumb', wp_upload_dir()['basedir'], 'last_error', '<script>', 'jxl', '\/' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, $needle );
		}
		$formats = array_column( $item['formats'], null, 'format' );
		$this->assertSame( [ 'webp', 'avif' ], array_keys( $formats ) );
		$this->assertSame( '', $formats['webp']['reason'] );
		$this->assertSame( '', $formats['avif']['reason'] );
	}

	/**
	 * Nothing is written: no queries other than reads; job state (even a
	 * state get_progress() would rewrite), meta, metadata, files and cron
	 * unchanged; nothing converted.
	 */
	public function test_read_only() {
		$id   = $this->jpeg( 'readonly.jpg' );
		$file = get_attached_file( $id );
		self::store( $id );
		update_post_meta( $id, AttachmentConverter::LOCK_META_KEY, time() - HOUR_IN_SECONDS );
		update_option(
			CompressionJob::STATE_OPTION,
			[
				'job_id'       => 'job-1',
				'job_type'     => CompressionJob::TYPE_CONVERSION,
				'status'       => 'running',
				'queue'        => [],
				'cancelled_at' => 0,
				'failed'       => 0,
			],
			false
		);
		$job      = get_option( CompressionJob::STATE_OPTION );
		$settings = get_option( 'tsmlt_settings' );
		$meta     = get_post_meta( $id );
		$metadata = wp_get_attachment_metadata( $id );
		$post     = get_post( $id, ARRAY_A );
		$folder   = scandir( dirname( $file ) );
		$uploads  = scandir( wp_get_upload_dir()['basedir'] );
		$cron     = _get_cron_array();
		add_filter( 'query', [ $this, 'record_query' ] );

		self::call( [ $id ] );

		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertSame( [], $this->writes );
		$this->assertSame( $job, get_option( CompressionJob::STATE_OPTION ), 'Job state not healed or saved.' );
		$this->assertSame( $settings, get_option( 'tsmlt_settings' ) );
		$this->assertSame( $meta, get_post_meta( $id ), 'Stale lock not cleared, data not rewritten.' );
		$this->assertSame( $metadata, wp_get_attachment_metadata( $id ) );
		$this->assertSame( $post, get_post( $id, ARRAY_A ) );
		clearstatcache();
		$this->assertSame( $folder, scandir( dirname( $file ) ), 'No WebP/AVIF file made.' );
		$this->assertSame( $uploads, scandir( wp_get_upload_dir()['basedir'] ) );
		$this->assertSame( $cron, _get_cron_array() );
	}

	/**
	 * Listed by name on the dedicated and the default server and runs on
	 * both; refused by the default gateway tools.
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
			$this->assertArrayHasKey( 'tsmlt-get-conversion-status', $tools, $route );
			$this->assertFalse( $tools['tsmlt-get-conversion-status']['inputSchema']['additionalProperties'], $route );
			$call = $this->send(
				$route,
				'tools/call',
				[
					'name'      => 'tsmlt-get-conversion-status',
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
