<?php
/**
 * `tsmlt/get-media-health`: per-attachment file and record integrity,
 * read-only, fixed codes only; listed on the dedicated and the default
 * server.
 *
 * Uploads WordPress's test images (canola.jpg 640x480, test-image.png), so
 * WordPress records the real metadata.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Abilities\Media\GetMediaHealth;

/**
 * GetMediaHealthAbilityTest
 */
class GetMediaHealthAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/get-media-health';

	/**
	 * Files to remove.
	 *
	 * @var string[]
	 */
	private $temp_files = [];

	/**
	 * Files made unreadable.
	 *
	 * @var string[]
	 */
	private $locked_files = [];

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
		foreach ( $this->locked_files as $file ) {
			chmod( $file, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		}
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
	 * Upload a WordPress test image under a name; WordPress records its
	 * metadata.
	 *
	 * @param string $source Test image file name.
	 * @param string $name   Uploaded name.
	 *
	 * @return int
	 */
	private function upload( string $source, string $name ): int {
		$temp = trailingslashit( get_temp_dir() ) . $name;
		copy( DIR_TESTDATA . '/images/' . $source, $temp );
		$this->temp_files[] = $temp;
		$id                 = (int) self::factory()->attachment->create_upload_object( $temp );
		$file               = get_attached_file( $id );
		$this->temp_files[] = $file;
		foreach ( (array) ( wp_get_attachment_metadata( $id )['sizes'] ?? [] ) as $size ) {
			$this->temp_files[] = dirname( $file ) . '/' . $size['file'];
		}
		return $id;
	}

	/**
	 * An attachment whose file is written directly into uploads.
	 *
	 * @param string $name     File name.
	 * @param string $contents File contents.
	 * @param string $mime     Stored MIME type.
	 *
	 * @return int
	 */
	private function file_attachment( string $name, string $contents, string $mime ): int {
		$relative = '2026/11/' . $name;
		$path     = trailingslashit( wp_upload_dir()['basedir'] ) . $relative;
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $path;
		return (int) self::factory()->attachment->create(
			[
				'post_mime_type' => $mime,
				'file'           => $relative,
			]
		);
	}

	/**
	 * Change attachment metadata.
	 *
	 * @param int   $id      Attachment ID.
	 * @param array $changes Keys to set (null removes).
	 *
	 * @return void
	 */
	private static function change_metadata( int $id, array $changes ) {
		$metadata = wp_get_attachment_metadata( $id );
		foreach ( $changes as $key => $value ) {
			if ( null === $value ) {
				unset( $metadata[ $key ] );
			} else {
				$metadata[ $key ] = $value;
			}
		}
		wp_update_attachment_metadata( $id, $metadata );
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
	 * A freshly uploaded image and a PDF are healthy.
	 */
	public function test_healthy_image_and_pdf() {
		$image = $this->upload( 'canola.jpg', 'healthy.jpg' );
		update_post_meta( $image, '_wp_attachment_image_alt', 'A field' );
		$pdf = $this->file_attachment( 'healthy.pdf', "%PDF-1.4\n%%EOF\n", 'application/pdf' );
		// The plugin's default settings set alt text on new media; clear it.
		delete_post_meta( $pdf, '_wp_attachment_image_alt' );

		$result = self::call( [ $image, $pdf ] );
		$this->assertSame( [ 'healthy', 'healthy' ], array_column( $result['items'], 'status' ) );
		$this->assertSame( [ [], [] ], array_column( $result['items'], 'issues' ) );
		$this->assertTrue( $result['items'][0]['has_alt_text'] );
		$this->assertFalse( $result['items'][1]['has_alt_text'] );
		$this->assertEquals( (object) [], $result['items'][0]['details'] );
		$this->assertSame( 2, $result['summary']['statuses']['healthy'] );
		$this->assertSame( 0, array_sum( $result['summary']['issues'] ) );
		$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), wp_get_ability( self::NAME )->get_output_schema(), 'result' ) );
	}

	/**
	 * No recorded file, a missing file and an unreadable file.
	 */
	public function test_file_issues() {
		$none = $this->upload( 'canola.jpg', 'no-reference.jpg' );
		delete_post_meta( $none, '_wp_attached_file' );
		$gone = $this->upload( 'canola.jpg', 'gone.jpg' );
		wp_delete_file( get_attached_file( $gone ) );

		$this->assertSame( [ 'no_attached_file' ], self::one( $none )['issues'] );
		$this->assertSame( [ 'file_missing' ], self::one( $gone )['issues'] );
		$this->assertSame( 'issues', self::one( $gone )['status'] );

		$locked = $this->upload( 'canola.jpg', 'locked.jpg' );
		$file   = get_attached_file( $locked );
		chmod( $file, 0000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		$this->locked_files[] = $file;
		if ( is_readable( $file ) ) {
			$this->markTestSkipped( 'Files stay readable for this user (running as root?); file_unreadable not tested.' );
		}
		$this->assertSame( [ 'file_unreadable' ], self::one( $locked )['issues'] );
	}

	/**
	 * A PNG stored as JPEG is a MIME mismatch; a file whose type the header
	 * cannot identify is not guessed.
	 */
	public function test_mime_mismatch() {
		$png = $this->upload( 'test-image.png', 'really-png.png' );
		wp_update_post(
			[
				'ID'             => $png,
				'post_mime_type' => 'image/jpeg',
			]
		);
		$item = self::one( $png );
		$this->assertContains( 'mime_mismatch', $item['issues'] );
		$this->assertSame(
			[
				'stored'   => 'image/jpeg',
				'detected' => 'image/png',
			],
			$item['details']->mime_mismatch
		);

		$text = $this->file_attachment( 'notes.pdf', 'Plain text, not a PDF.', 'application/pdf' );
		$this->assertSame( 'healthy', self::one( $text )['status'], 'An unidentified type is not a mismatch.' );

		$jpg = $this->upload( 'canola.jpg', 'jpg-alias.jpg' );
		wp_update_post(
			[
				'ID'             => $jpg,
				'post_mime_type' => 'image/jpg',
			]
		);
		$this->assertNotContains( 'mime_mismatch', self::one( $jpg )['issues'], 'image/jpg is image/jpeg.' );
	}

	/**
	 * Missing metadata, file size and dimension mismatches; a missing
	 * recorded file size is not an issue.
	 */
	public function test_metadata_issues() {
		$no_meta = $this->upload( 'canola.jpg', 'no-meta.jpg' );
		delete_post_meta( $no_meta, '_wp_attachment_metadata' );
		$this->assertSame( [ 'metadata_missing' ], self::one( $no_meta )['issues'] );

		$size   = $this->upload( 'canola.jpg', 'filesize.jpg' );
		$actual = filesize( get_attached_file( $size ) );
		self::change_metadata( $size, [ 'filesize' => $actual + 10 ] );
		$item = self::one( $size );
		$this->assertSame( [ 'filesize_mismatch' ], $item['issues'] );
		$this->assertSame(
			[
				'recorded' => $actual + 10,
				'actual'   => $actual,
			],
			$item['details']->filesize_mismatch
		);

		$legacy = $this->upload( 'canola.jpg', 'legacy.jpg' );
		self::change_metadata( $legacy, [ 'filesize' => null ] );
		$this->assertSame( 'healthy', self::one( $legacy )['status'], 'No recorded size is not a mismatch.' );

		$dims = $this->upload( 'canola.jpg', 'dimensions.jpg' );
		self::change_metadata( $dims, [ 'width' => 800 ] );
		$item = self::one( $dims );
		$this->assertSame( [ 'dimensions_mismatch' ], $item['issues'] );
		$this->assertSame(
			[
				'recorded_width'  => 800,
				'recorded_height' => 480,
				'actual_width'    => 640,
				'actual_height'   => 480,
			],
			$item['details']->dimensions_mismatch
		);
	}

	/**
	 * Image-only checks are not applied to SVG or non-image files.
	 */
	public function test_image_only_checks() {
		$svg = $this->file_attachment( 'logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>', 'image/svg+xml' );
		update_post_meta(
			$svg,
			'_wp_attachment_metadata',
			[
				'width'    => 999,
				'height'   => 999,
				'filesize' => 1,
			]
		);
		$pdf = $this->file_attachment( 'no-meta.pdf', "%PDF-1.4\n%%EOF\n", 'application/pdf' );

		$this->assertSame( 'healthy', self::one( $svg )['status'], 'No dimension, size or metadata checks for SVG.' );
		$this->assertSame( 'healthy', self::one( $pdf )['status'], 'No metadata check for a PDF.' );
	}

	/**
	 * Parents that are deleted, trashed or not real posts; unattached and
	 * normally attached media are fine; parents are never changed.
	 */
	public function test_invalid_parent() {
		$deleted = self::factory()->post->create();
		wp_delete_post( $deleted, true );
		$trashed = self::factory()->post->create();
		wp_trash_post( $trashed );
		$font  = self::factory()->post->create( [ 'post_type' => 'wp_font_face' ] );
		$valid = self::factory()->post->create();

		$ids = [];
		foreach ( [ $deleted, $trashed, $font, $valid, 0 ] as $index => $parent ) {
			$id = $this->upload( 'canola.jpg', 'parent-' . $index . '.jpg' );
			wp_update_post(
				[
					'ID'          => $id,
					'post_parent' => $parent,
				]
			);
			$ids[] = $id;
		}

		$items = self::call( $ids )['items'];
		foreach ( [ $deleted, $trashed, $font ] as $index => $parent ) {
			$this->assertSame( [ 'invalid_parent' ], $items[ $index ]['issues'], (string) $index );
			$this->assertSame( [ 'parent_id' => $parent ], $items[ $index ]['details']->invalid_parent );
		}
		$this->assertSame( 'healthy', $items[3]['status'] );
		$this->assertSame( 'healthy', $items[4]['status'] );
		$this->assertSame( $deleted, (int) get_post_field( 'post_parent', $ids[0] ), 'The parent is never changed.' );
	}

	/**
	 * Missing, non-media, trashed, unreadable and uneditable attachments;
	 * nothing about them is returned; summary counts every status.
	 */
	public function test_access_statuses_and_summary() {
		$post    = self::factory()->post->create();
		$hidden  = $this->upload( 'canola.jpg', 'hidden.jpg' );
		$locked  = $this->upload( 'canola.jpg', 'locked-out.jpg' );
		$trashed = $this->upload( 'canola.jpg', 'trashed.jpg' );
		$healthy = $this->upload( 'canola.jpg', 'fine.jpg' );
		$broken  = $this->upload( 'canola.jpg', 'broken.jpg' );
		delete_post_meta( $hidden, '_wp_attached_file' );
		delete_post_meta( $locked, '_wp_attached_file' );
		update_post_meta( $locked, '_wp_attachment_image_alt', 'Secret' );
		wp_delete_file( get_attached_file( $broken ) );
		wp_trash_post( $trashed );
		$this->denied[ $hidden ] = 'read_post';
		$this->denied[ $locked ] = 'edit_post';

		$result = self::call( [ 999999, $post, $hidden, $locked, $trashed, $healthy, $broken ] );
		$this->assertSame( [ 'not_found', 'not_found', 'not_found', 'forbidden', 'not_found', 'healthy', 'issues' ], array_column( $result['items'], 'status' ) );
		foreach ( [ 2, 3 ] as $index ) {
			$this->assertSame( [], $result['items'][ $index ]['issues'], 'Nothing about an inaccessible attachment.' );
			$this->assertFalse( $result['items'][ $index ]['has_alt_text'] );
		}
		$this->assertSame(
			[
				'healthy'   => 1,
				'issues'    => 1,
				'not_found' => 4,
				'forbidden' => 1,
			],
			$result['summary']['statuses']
		);
		$this->assertSame( 1, $result['summary']['issues']['file_missing'] );
		$this->assertSame( 0, $result['summary']['issues']['no_attached_file'], 'Inaccessible items are not counted.' );
	}

	/**
	 * Invalid requests are refused as a whole.
	 */
	public function test_invalid_input() {
		$id = $this->file_attachment( 'x.pdf', '%PDF', 'application/pdf' );
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
					'repair' => true,
				],
			] as $label => $input
		) {
			$this->assertWPError( GetMediaHealth::instance()->execute( $input ), $label );
		}
		$this->assertCount( 50, GetMediaHealth::instance()->execute( [ 'ids' => range( 1, 50 ) ] )['items'], '50 is allowed.' );
	}

	/**
	 * Only administrators.
	 */
	public function test_requires_manage_options() {
		$id = $this->file_attachment( 'perm.pdf', '%PDF', 'application/pdf' );
		foreach ( [ 'editor', 'author' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertFalse( wp_get_ability( self::NAME )->check_permissions( [ 'ids' => [ $id ] ] ), $role );
			$this->assertWPError( GetMediaHealth::instance()->execute( [ 'ids' => [ $id ] ] ), $role );
		}
		wp_set_current_user( 0 );
		$this->assertWPError( GetMediaHealth::instance()->execute( [ 'ids' => [ $id ] ] ) );
	}

	/**
	 * No paths, file names, alt text, EXIF/GPS or parent titles.
	 */
	public function test_no_private_data() {
		$parent = self::factory()->post->create( [ 'post_title' => 'Private Parent Title' ] );
		$id     = $this->upload( 'canola.jpg', 'private-name.jpg' );
		wp_update_post(
			[
				'ID'          => $id,
				'post_parent' => $parent,
			]
		);
		wp_trash_post( $parent );
		update_post_meta( $id, '_wp_attachment_image_alt', 'Private alt text' );
		self::change_metadata(
			$id,
			[
				'width'      => 1,
				'image_meta' => [
					'camera'   => 'SecretCam 9',
					'latitude' => 51.5072,
				],
			]
		);

		$json = wp_json_encode( self::call( [ $id ] ) );
		foreach ( [ wp_upload_dir()['basedir'], 'private-name', '.jpg', 'Private alt text', 'Private Parent Title', 'image_meta', 'SecretCam', '51.5072', 'latitude' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, $needle );
		}
		$this->assertDoesNotMatchRegularExpression( '#[0-9]{4}\\\\/[0-9]{2}\\\\/#', $json, 'No upload folders.' );
	}

	/**
	 * Nothing is written: no write queries; metadata, post meta, parent,
	 * options, files and cron unchanged.
	 */
	public function test_read_only() {
		$trashed = self::factory()->post->create();
		$id      = $this->upload( 'canola.jpg', 'readonly.jpg' );
		wp_update_post(
			[
				'ID'          => $id,
				'post_parent' => $trashed,
			]
		);
		wp_trash_post( $trashed );
		self::change_metadata( $id, [ 'filesize' => 1 ] );
		$file     = get_attached_file( $id );
		$metadata = wp_get_attachment_metadata( $id );
		$meta     = get_post_meta( $id );
		$post     = get_post( $id, ARRAY_A );
		$options  = wp_load_alloptions();
		$folder   = scandir( dirname( $file ) );
		$cron     = _get_cron_array();
		add_filter( 'query', [ $this, 'record_query' ] );

		$item = self::one( $id );

		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertSame( [ 'filesize_mismatch', 'invalid_parent' ], $item['issues'] );
		$this->assertSame( [], $this->writes );
		$this->assertSame( $metadata, wp_get_attachment_metadata( $id ) );
		$this->assertSame( $meta, get_post_meta( $id ) );
		$this->assertSame( $post, get_post( $id, ARRAY_A ), 'Parent not relinked.' );
		$this->assertSame( $options, wp_load_alloptions() );
		clearstatcache();
		$this->assertSame( $folder, scandir( dirname( $file ) ) );
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
		$id = $this->upload( 'canola.jpg', 'transport.jpg' );
		foreach ( [ '/media-library-tools/mcp', '/mcp/mcp-adapter-default-server' ] as $route ) {
			$this->connect( $route );
			$tools = array_column( $this->send( $route, 'tools/list' )['result']['tools'], null, 'name' );
			$this->assertArrayHasKey( 'tsmlt-get-media-health', $tools, $route );
			$this->assertFalse( $tools['tsmlt-get-media-health']['inputSchema']['additionalProperties'], $route );
			$call = $this->send(
				$route,
				'tools/call',
				[
					'name'      => 'tsmlt-get-media-health',
					'arguments' => [ 'ids' => [ $id ] ],
				]
			)['result'];
			$this->assertFalse( $call['isError'], $route . ' ' . wp_json_encode( $call ) );
			$this->assertSame( 'healthy', $call['structuredContent']['items'][0]['status'] );
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
