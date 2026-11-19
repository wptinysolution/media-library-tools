<?php
/**
 * `tsmlt/get-thumbnail-status`: per-image thumbnail status from the
 * attachment metadata, the registered sizes and the files on disk;
 * read-only; listed on the dedicated and the default server.
 *
 * Uploads WordPress's test images (canola.jpg, 640x480), so WordPress makes
 * the real sizes.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Abilities\Media\GetThumbnailStatus;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;

/**
 * GetThumbnailStatusAbilityTest
 */
class GetThumbnailStatusAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/get-thumbnail-status';

	/**
	 * Size registered by a test, as a theme or Pro would.
	 *
	 * @var string
	 */
	const RUNTIME_SIZE = 'tsmlt_test_square';

	/**
	 * Size too large for the test image.
	 *
	 * @var string
	 */
	const HUGE_SIZE = 'tsmlt_test_huge';

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
	 * Administrator; default settings.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( self::NAME ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( 'tsmlt_settings' );
		$this->denied   = [];
		$this->writes   = [];
		$this->sessions = [];
		add_filter( 'map_meta_cap', [ $this, 'deny' ], 10, 4 );
	}

	/**
	 * Remove fixtures, sizes and filters.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny' ], 10 );
		remove_filter( 'query', [ $this, 'record_query' ] );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		remove_image_size( self::RUNTIME_SIZE );
		remove_image_size( self::HUGE_SIZE );
		foreach ( $this->temp_files as $file ) {
			if ( is_string( $file ) && is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
		delete_option( 'tsmlt_settings' );
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
	 * A big-image threshold below the test image, so uploads are scaled.
	 *
	 * @return int
	 */
	public function small_threshold() {
		return 400;
	}

	/**
	 * Upload canola.jpg under a name; WordPress makes its sizes.
	 *
	 * @param string $name File name.
	 *
	 * @return int
	 */
	private function upload( string $name ): int {
		$temp = trailingslashit( get_temp_dir() ) . $name;
		copy( DIR_TESTDATA . '/images/canola.jpg', $temp );
		$this->temp_files[] = $temp;
		$id                 = (int) self::factory()->attachment->create_upload_object( $temp );
		$file               = get_attached_file( $id );
		$this->temp_files[] = $file;
		$metadata           = wp_get_attachment_metadata( $id );
		foreach ( (array) ( $metadata['sizes'] ?? [] ) as $size ) {
			$this->temp_files[] = dirname( $file ) . '/' . $size['file'];
		}
		if ( ! empty( $metadata['original_image'] ) ) {
			$this->temp_files[] = dirname( $file ) . '/' . $metadata['original_image'];
		}
		return $id;
	}

	/**
	 * An attachment of a MIME type with no file.
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
	 * One item.
	 *
	 * @param int $id ID.
	 *
	 * @return array
	 */
	private static function one( int $id ): array {
		return self::call( [ $id ] )['items'][0];
	}

	/**
	 * A freshly uploaded image is complete: every size recorded, every file
	 * present, nothing missing; sizes larger than the image are not expected.
	 */
	public function test_complete() {
		$id     = $this->upload( 'complete.jpg' );
		$result = self::call( [ $id ] );
		$item   = $result['items'][0];

		$this->assertSame( 'complete', $item['status'] );
		$this->assertSame( 640, $item['width'] );
		$this->assertSame( 480, $item['height'] );
		$this->assertFalse( $item['has_original'] );
		$this->assertNull( $item['original_width'] );
		$this->assertSame( [], $item['missing'] );
		$sizes = array_column( $item['sizes'], null, 'name' );
		$this->assertArrayHasKey( 'thumbnail', $sizes );
		$this->assertArrayHasKey( 'medium', $sizes );
		$this->assertArrayNotHasKey( 'large', $sizes, '1024 is larger than the image.' );
		$this->assertSame( 150, $sizes['thumbnail']['width'] );
		$this->assertSame( 'image/jpeg', $sizes['thumbnail']['mime_type'] );
		$this->assertTrue( $sizes['thumbnail']['file_exists'] );
		$this->assertTrue( $sizes['thumbnail']['registered'] );
		$this->assertSame( 1, $result['summary']['complete'] );
		$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), wp_get_ability( self::NAME )->get_output_schema(), 'result' ) );
	}

	/**
	 * A runtime-registered size the image is large enough for is missing;
	 * one larger than the image is not; a disabled size is excluded instead.
	 */
	public function test_missing_sizes_and_disabled() {
		$id = $this->upload( 'missing-sizes.jpg' );
		add_image_size( self::RUNTIME_SIZE, 100, 100, true );
		add_image_size( self::HUGE_SIZE, 5000, 5000, true );

		$item = self::one( $id );
		$this->assertSame( 'missing_sizes', $item['status'] );
		$this->assertSame(
			[
				[
					'name'   => self::RUNTIME_SIZE,
					'width'  => 100,
					'height' => 100,
					'crop'   => true,
				],
			],
			$item['missing']
		);
		$this->assertSame( [], $item['disabled_sizes_excluded'] );

		update_option( 'tsmlt_settings', [ 'deregistered_image_sizes' => [ self::RUNTIME_SIZE ] ] );
		$item = self::one( $id );
		$this->assertSame( 'complete', $item['status'] );
		$this->assertSame( [], $item['missing'] );
		$this->assertSame( [ self::RUNTIME_SIZE ], $item['disabled_sizes_excluded'] );
	}

	/**
	 * A recorded size whose file is gone is missing_files, which comes
	 * before missing_sizes.
	 */
	public function test_missing_files_precedence() {
		$id       = $this->upload( 'missing-files.jpg' );
		$metadata = wp_get_attachment_metadata( $id );
		wp_delete_file( dirname( get_attached_file( $id ) ) . '/' . $metadata['sizes']['thumbnail']['file'] );
		add_image_size( self::RUNTIME_SIZE, 100, 100, true );

		$item  = self::one( $id );
		$sizes = array_column( $item['sizes'], null, 'name' );
		$this->assertSame( 'missing_files', $item['status'] );
		$this->assertFalse( $sizes['thumbnail']['file_exists'] );
		$this->assertTrue( $sizes['medium']['file_exists'] );
		$this->assertNotEmpty( $item['missing'], 'Missing sizes are still listed.' );
	}

	/**
	 * A recorded size that is no longer registered is reported with
	 * registered false and does not make the image incomplete.
	 */
	public function test_unregistered_recorded_size() {
		$id       = $this->upload( 'unregistered.jpg' );
		$metadata = wp_get_attachment_metadata( $id );

		$metadata['sizes']['old_theme_size'] = $metadata['sizes']['thumbnail'];
		wp_update_attachment_metadata( $id, $metadata );

		$item  = self::one( $id );
		$sizes = array_column( $item['sizes'], null, 'name' );
		$this->assertFalse( $sizes['old_theme_size']['registered'] );
		$this->assertTrue( $sizes['old_theme_size']['file_exists'] );
		$this->assertSame( 'complete', $item['status'] );
	}

	/**
	 * A scaled upload reports the original's dimensions, and expected sizes
	 * are measured on the original.
	 */
	public function test_scaled_upload() {
		add_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		$id = $this->upload( 'scaled.jpg' );
		remove_filter( 'big_image_size_threshold', [ $this, 'small_threshold' ] );
		$this->assertNotEmpty( wp_get_attachment_metadata( $id )['original_image'], 'Precondition: scaled.' );

		add_image_size( self::RUNTIME_SIZE, 500, 400, true );
		$item = self::one( $id );
		$this->assertTrue( $item['has_original'] );
		$this->assertSame( 640, $item['original_width'] );
		$this->assertSame( 480, $item['original_height'] );
		$this->assertSame( 400, $item['width'], 'The scaled copy.' );
		$this->assertSame( [ self::RUNTIME_SIZE ], array_column( $item['missing'], 'name' ), 'Larger than the scaled copy, smaller than the original.' );
	}

	/**
	 * Empty metadata, a missing main file, SVG, PDF and video, and the
	 * precedence between them.
	 */
	public function test_other_statuses() {
		$no_meta = $this->upload( 'no-meta.jpg' );
		delete_post_meta( $no_meta, '_wp_attachment_metadata' );
		$gone = $this->attachment( 'image/jpeg', 'gone.jpg' );
		$svg  = $this->attachment( 'image/svg+xml', 'logo.svg' );
		update_post_meta(
			$svg,
			'_wp_attachment_metadata',
			[
				'width'  => 100,
				'height' => 100,
				'sizes'  => [
					'thumbnail' => [
						'width'  => 150,
						'height' => 150,
					],
				],
			]
		);
		$pdf   = $this->attachment( 'application/pdf', 'doc.pdf' );
		$video = $this->attachment( 'video/mp4', 'clip.mp4' );

		$result = self::call( [ $no_meta, $gone, $svg, $pdf, $video ] );
		$this->assertSame( [ 'no_metadata', 'file_missing', 'not_applicable', 'not_applicable', 'not_applicable' ], array_column( $result['items'], 'status' ) );
		$this->assertSame( [], $result['items'][2]['sizes'], 'Nothing reported for SVG.' );
		$this->assertSame( 3, $result['summary']['not_applicable'] );
	}

	/**
	 * A stored size name pointing outside the folder is checked by file name
	 * inside the folder only.
	 */
	public function test_path_traversal() {
		$id      = $this->upload( 'traversal.jpg' );
		$folder  = dirname( get_attached_file( $id ) );
		$outside = dirname( $folder ) . '/tsmlt-outside.jpg';
		copy( DIR_TESTDATA . '/images/canola.jpg', $outside );
		$this->temp_files[] = $outside;

		$metadata                       = wp_get_attachment_metadata( $id );
		$metadata['sizes']['thumbnail'] = array_merge( $metadata['sizes']['thumbnail'], [ 'file' => '../tsmlt-outside.jpg' ] );
		$metadata['original_image']     = '../tsmlt-outside.jpg';
		wp_update_attachment_metadata( $id, $metadata );

		$item  = self::one( $id );
		$sizes = array_column( $item['sizes'], null, 'name' );
		$this->assertFalse( $sizes['thumbnail']['file_exists'], 'The file outside the folder is not seen.' );
		$this->assertFalse( $item['has_original'] );
		$this->assertSame( 'missing_files', $item['status'] );
	}

	/**
	 * Missing, non-media, trashed, unreadable and uneditable attachments.
	 */
	public function test_access_statuses() {
		$post    = self::factory()->post->create();
		$hidden  = $this->upload( 'hidden.jpg' );
		$locked  = $this->upload( 'locked.jpg' );
		$trashed = $this->upload( 'trashed.jpg' );
		wp_trash_post( $trashed );
		$this->denied[ $hidden ] = 'read_post';
		$this->denied[ $locked ] = 'edit_post';

		$items = array_column( self::call( [ 999999, $post, $hidden, $locked, $trashed ] )['items'], null, 'id' );
		$this->assertSame( 'not_found', $items[999999]['status'] );
		$this->assertSame( 'not_found', $items[ $post ]['status'] );
		$this->assertSame( 'not_found', $items[ $hidden ]['status'] );
		$this->assertSame( 'forbidden', $items[ $locked ]['status'] );
		$this->assertSame( 'not_found', $items[ $trashed ]['status'] );
		foreach ( [ $hidden, $locked ] as $id ) {
			$this->assertNull( $items[ $id ]['width'] );
			$this->assertSame( [], $items[ $id ]['sizes'], 'Nothing about an inaccessible attachment.' );
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
					'ids'        => [ $id ],
					'regenerate' => true,
				],
			] as $label => $input
		) {
			$this->assertWPError( GetThumbnailStatus::instance()->execute( $input ), $label );
		}
		$this->assertCount( 50, GetThumbnailStatus::instance()->execute( [ 'ids' => range( 1, 50 ) ] )['items'], '50 is allowed.' );
	}

	/**
	 * Only administrators.
	 */
	public function test_requires_manage_options() {
		$id = $this->attachment( 'image/jpeg', 'perm.jpg' );
		foreach ( [ 'editor', 'author' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertFalse( wp_get_ability( self::NAME )->check_permissions( [ 'ids' => [ $id ] ] ), $role );
			$this->assertWPError( GetThumbnailStatus::instance()->execute( [ 'ids' => [ $id ] ] ), $role );
		}
		wp_set_current_user( 0 );
		$this->assertWPError( GetThumbnailStatus::instance()->execute( [ 'ids' => [ $id ] ] ) );
	}

	/**
	 * No paths, file names or EXIF in the output.
	 */
	public function test_no_paths_or_exif() {
		$id       = $this->upload( 'private-name.jpg' );
		$metadata = wp_get_attachment_metadata( $id );

		$metadata['image_meta'] = [
			'camera'    => 'SecretCam 9',
			'copyright' => 'Private Holder',
		];
		wp_update_attachment_metadata( $id, $metadata );

		$json = wp_json_encode( self::call( [ $id ] ) );
		foreach ( [ wp_upload_dir()['basedir'], 'private-name', '.jpg', 'image_meta', 'SecretCam', 'Private Holder' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, $needle );
		}
		$this->assertDoesNotMatchRegularExpression( '#[0-9]{4}\\\\/[0-9]{2}\\\\/#', $json, 'No upload folders.' );
	}

	/**
	 * Nothing is written: no write queries; metadata, settings, regenerate
	 * state, files and cron unchanged.
	 */
	public function test_read_only() {
		$id = $this->upload( 'readonly.jpg' );
		add_image_size( self::RUNTIME_SIZE, 100, 100, true );
		update_option(
			RegenerateThumbnails::STATE_OPTION,
			[
				'status' => 'running',
				'offset' => 3,
			],
			false
		);
		$file     = get_attached_file( $id );
		$metadata = wp_get_attachment_metadata( $id );
		$meta     = get_post_meta( $id );
		$state    = get_option( RegenerateThumbnails::STATE_OPTION );
		$settings = get_option( 'tsmlt_settings' );
		$folder   = scandir( dirname( $file ) );
		$cron     = _get_cron_array();
		add_filter( 'query', [ $this, 'record_query' ] );

		self::call( [ $id ] );

		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertSame( [], $this->writes );
		$this->assertSame( $metadata, wp_get_attachment_metadata( $id ) );
		$this->assertSame( $meta, get_post_meta( $id ) );
		$this->assertSame( $state, get_option( RegenerateThumbnails::STATE_OPTION ) );
		$this->assertSame( $settings, get_option( 'tsmlt_settings' ) );
		clearstatcache();
		$this->assertSame( $folder, scandir( dirname( $file ) ), 'The missing size was not generated.' );
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
		$id = $this->upload( 'transport.jpg' );
		foreach ( [ '/media-library-tools/mcp', '/mcp/mcp-adapter-default-server' ] as $route ) {
			$this->connect( $route );
			$tools = array_column( $this->send( $route, 'tools/list' )['result']['tools'], null, 'name' );
			$this->assertArrayHasKey( 'tsmlt-get-thumbnail-status', $tools, $route );
			$this->assertFalse( $tools['tsmlt-get-thumbnail-status']['inputSchema']['additionalProperties'], $route );
			$call = $this->send(
				$route,
				'tools/call',
				[
					'name'      => 'tsmlt-get-thumbnail-status',
					'arguments' => [ 'ids' => [ $id ] ],
				]
			)['result'];
			$this->assertFalse( $call['isError'], $route . ' ' . wp_json_encode( $call ) );
			$this->assertSame( 'complete', $call['structuredContent']['items'][0]['status'] );
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
