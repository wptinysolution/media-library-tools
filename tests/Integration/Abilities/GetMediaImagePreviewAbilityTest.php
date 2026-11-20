<?php
/**
 * The get-media-image-preview ability: a downscaled, metadata-free JPEG,
 * PNG or WebP preview returned as an MCP image content block.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\ImagePreview;
use TinySolutions\mlt\Abilities\Media\GetMediaImagePreview;

require_once dirname( __DIR__, 2 ) . '/Support/ExifJpegFixture.php';

/**
 * GetMediaImagePreviewAbilityTest
 */
class GetMediaImagePreviewAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/get-media-image-preview';

	/**
	 * Text planted in fixture metadata.
	 *
	 * @var string
	 */
	const SECRET = 'TSMLTSECRET';

	/**
	 * Folder of this test's files.
	 *
	 * @var string
	 */
	private $dir = '';

	/**
	 * Meta capability to deny per attachment ID.
	 *
	 * @var array<int, string>
	 */
	private $denied = [];

	/**
	 * File reported as offloaded.
	 *
	 * @var int
	 */
	private $remote = 0;

	/**
	 * MCP session per route.
	 *
	 * @var array<string, string>
	 */
	private $sessions = [];

	/**
	 * Administrator and a fresh folder.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( self::NAME ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->dir      = trailingslashit( wp_get_upload_dir()['basedir'] ) . 'tsmlt-preview-' . wp_generate_password( 8, false, false );
		$this->denied   = [];
		$this->remote   = 0;
		$this->sessions = [];
		wp_mkdir_p( $this->dir );
		add_filter( 'map_meta_cap', [ $this, 'deny' ], 10, 4 );
		add_filter( 'get_attached_file', [ $this, 'remote_file' ], 10, 2 );
	}

	/**
	 * Remove fixtures.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny' ], 10 );
		remove_filter( 'get_attached_file', [ $this, 'remote_file' ], 10 );
		remove_filter( 'image_editor_output_format', [ $this, 'gif_output' ] );
		remove_filter( 'tsmlt_mcp_image_preview_max_bytes', [ $this, 'tiny_limit' ] );
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			chmod( $file, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
			wp_delete_file( $file );
		}
		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture.
		}
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
	 * Make the editor write GIF, which a preview refuses.
	 *
	 * @param array $formats Formats.
	 *
	 * @return array
	 */
	public function gif_output( $formats ) {
		$formats['image/jpeg'] = 'image/gif';
		return $formats;
	}

	/**
	 * Write bytes and attach them.
	 *
	 * @param string $name  File name.
	 * @param string $bytes Bytes.
	 * @param string $mime  MIME type.
	 *
	 * @return int
	 */
	private function attach_bytes( string $name, string $bytes, string $mime ): int {
		$path = $this->dir . '/' . $name;
		file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		return (int) self::factory()->attachment->create_object(
			[
				'file'           => $path,
				'post_mime_type' => $mime,
			]
		);
	}

	/**
	 * Canola encoded in a format by the image editor.
	 *
	 * @param string $mime MIME type.
	 *
	 * @return string
	 */
	private static function encoded( string $mime ): string {
		$editor = wp_get_image_editor( DIR_TESTDATA . '/images/canola.jpg' );
		$path   = trailingslashit( get_temp_dir() ) . 'tsmlt-fixture-' . wp_generate_password( 6, false, false );
		$saved  = $editor->save( $path, $mime );
		$bytes  = (string) file_get_contents( $saved['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
		wp_delete_file( $saved['path'] );
		return $bytes;
	}

	/**
	 * A JPEG carrying camera EXIF, GPS, XMP and a comment.
	 *
	 * @return string
	 */
	private static function jpeg_with_metadata(): string {
		$jpeg = TsmltExifJpegFixture::build(
			DIR_TESTDATA . '/images/canola.jpg',
			[ [ 0x010F, TsmltExifJpegFixture::ASCII, self::SECRET . 'CAM' ] ],
			[],
			[ [ 0x0001, TsmltExifJpegFixture::ASCII, 'N' ] ]
		);
		$xmp  = "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta>" . self::SECRET . 'XMP</x:xmpmeta>';
		$com  = self::SECRET . 'COMMENT';
		return "\xFF\xD8\xFF\xE1" . pack( 'n', strlen( $xmp ) + 2 ) . $xmp . "\xFF\xFE" . pack( 'n', strlen( $com ) + 2 ) . $com . substr( $jpeg, 2 );
	}

	/**
	 * A PNG with a text chunk before IEND.
	 *
	 * @return string
	 */
	private static function png_with_text(): string {
		$png  = self::encoded( 'image/png' );
		$data = 'Comment' . "\0" . self::SECRET . 'PNG';
		$text = pack( 'N', strlen( $data ) ) . 'tEXt' . $data . pack( 'N', crc32( 'tEXt' . $data ) );
		$iend = strrpos( $png, 'IEND' ) - 4;
		return substr( $png, 0, $iend ) . $text . substr( $png, $iend );
	}

	/**
	 * An extended WebP (VP8X) with an EXIF chunk.
	 *
	 * @return string
	 */
	private static function webp_with_exif(): string {
		$webp  = self::encoded( 'image/webp' );
		$image = substr( $webp, 12 );
		$size  = getimagesizefromstring( $webp );
		$vp8x  = 'VP8X' . pack( 'V', 10 ) . chr( 0x08 ) . "\0\0\0" . substr( pack( 'V', $size[0] - 1 ), 0, 3 ) . substr( pack( 'V', $size[1] - 1 ), 0, 3 );
		$exif  = 'Exif' . self::SECRET . 'WEBP';
		$chunk = 'EXIF' . pack( 'V', strlen( $exif ) ) . $exif . ( strlen( $exif ) % 2 ? "\0" : '' );
		$body  = 'WEBP' . $vp8x . $image . $chunk;
		return 'RIFF' . pack( 'V', strlen( $body ) ) . $body;
	}

	/**
	 * A noisy image of a size, as PNG or JPEG bytes.
	 *
	 * @param int    $width  Width.
	 * @param int    $height Height.
	 * @param string $mime   image/png or image/jpeg.
	 *
	 * @return string
	 */
	private static function noise( int $width, int $height, string $mime ): string {
		$image = imagecreatetruecolor( $width, $height );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		mt_srand( 7 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand -- Repeatable noise fixture.
		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				imagesetpixel( $image, $x, $y, mt_rand( 0, 0xFFFFFF ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- Repeatable noise fixture.
			}
		}
		ob_start();
		'image/png' === $mime ? imagepng( $image ) : imagejpeg( $image, null, 90 );
		$bytes = (string) ob_get_clean();
		self::destroy( $image );
		return $bytes;
	}

	/**
	 * Free a GD image (a no-op from PHP 8.0, deprecated in 8.5).
	 *
	 * @param mixed $image GD image.
	 */
	private static function destroy( $image ) {
		if ( PHP_VERSION_ID < 80000 ) {
			imagedestroy( $image );
		}
	}

	/**
	 * Lower the byte limit to a tiny value.
	 *
	 * @return int
	 */
	public function tiny_limit() {
		return 2000;
	}

	/**
	 * Try to raise the byte limit above MAX_BYTES.
	 *
	 * @return int
	 */
	public function huge_limit() {
		return 50000000;
	}

	/**
	 * Run through the Abilities API (input and output validated by core).
	 *
	 * @param mixed $input Input.
	 *
	 * @return mixed
	 */
	private static function call( $input ) {
		return wp_get_ability( self::NAME )->execute( $input );
	}

	/**
	 * Temporary preview files in the temp folder.
	 *
	 * @return array
	 */
	private static function temp_files(): array {
		return (array) glob( trailingslashit( get_temp_dir() ) . 'tsmlt-preview*' );
	}

	/**
	 * Assert a result is a clean preview of a type, within the limits.
	 *
	 * @param mixed  $result Result.
	 * @param string $mime   Expected MIME type.
	 *
	 * @return array Image size.
	 */
	private function assert_preview( $result, string $mime ): array {
		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_code() : '' );
		$this->assertSame( [ 'type', 'results', 'mimeType' ], array_keys( $result ) );
		$this->assertSame( 'image', $result['type'] );
		$this->assertSame( $mime, $result['mimeType'] );
		$this->assertSame( $mime, ImagePreview::detect( $result['results'] ) );
		$this->assertTrue( ImagePreview::is_clean( $result['results'], $mime ) );
		$this->assertLessThanOrEqual( ImagePreview::max_bytes(), strlen( $result['results'] ) );
		$this->assertStringNotContainsString( self::SECRET, $result['results'] );
		$size = getimagesizefromstring( $result['results'] );
		$this->assertNotFalse( $size );
		$this->assertLessThanOrEqual( ImagePreview::MAX_EDGE, max( $size[0], $size[1] ) );
		return $size;
	}

	/**
	 * JPEG: camera, GPS, XMP and comment removed; originals unchanged; no temp files.
	 */
	public function test_jpeg_preview_is_clean_and_original_unchanged() {
		$id   = $this->attach_bytes( 'meta.jpg', self::jpeg_with_metadata(), 'image/jpeg' );
		$path = get_attached_file( $id );
		$hash = md5_file( $path );
		$meta = get_post_meta( $id );
		$temp = self::temp_files();

		$result = self::call( [ 'id' => $id ] );

		$size = $this->assert_preview( $result, 'image/jpeg' );
		$this->assertSame( [ 640, 480 ], [ $size[0], $size[1] ] );
		foreach ( [ 'Exif', 'xmpmeta', 'ns.adobe.com/xap' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $result['results'], $needle );
		}
		$this->assertSame( $hash, md5_file( $path ), 'Original unchanged.' );
		$this->assertSame( $meta, get_post_meta( $id ), 'Attachment data unchanged.' );
		$this->assertSame( $temp, self::temp_files(), 'No temporary file left.' );
	}

	/**
	 * PNG: text chunks removed.
	 */
	public function test_png_preview() {
		$id     = $this->attach_bytes( 'meta.png', self::png_with_text(), 'image/png' );
		$result = self::call( [ 'id' => $id ] );
		$this->assert_preview( $result, 'image/png' );
		$this->assertStringNotContainsString( 'tEXt', $result['results'] );
	}

	/**
	 * WebP: EXIF chunk removed.
	 */
	public function test_webp_preview() {
		if ( ! wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] ) ) {
			$this->markTestSkipped( 'No WebP support in the image editor.' );
		}
		$source = self::webp_with_exif();
		$this->assertStringContainsString( self::SECRET, $source, 'Precondition: the fixture carries metadata.' );
		$id     = $this->attach_bytes( 'meta.webp', $source, 'image/webp' );
		$result = self::call( [ 'id' => $id ] );
		$this->assert_preview( $result, 'image/webp' );
		$this->assertStringNotContainsString( 'EXIF', $result['results'] );
	}

	/**
	 * Larger images are downscaled to 1024 pixels on the longest side.
	 */
	public function test_maximum_dimensions() {
		$image = imagecreatetruecolor( 3000, 1500 );
		ob_start();
		imagejpeg( $image, null, 80 );
		$bytes = (string) ob_get_clean();
		self::destroy( $image );
		$id   = $this->attach_bytes( 'wide.jpg', $bytes, 'image/jpeg' );
		$size = $this->assert_preview( self::call( [ 'id' => $id ] ), 'image/jpeg' );
		$this->assertSame( [ 1024, 512 ], [ $size[0], $size[1] ] );
	}

	/**
	 * Over the byte limit the preview is made smaller; if even the smallest
	 * size is over, it fails instead of returning anything larger.
	 */
	public function test_byte_limit() {
		$id   = $this->attach_bytes( 'noise.png', self::noise( 1200, 1200, 'image/png' ), 'image/png' );
		$size = $this->assert_preview( self::call( [ 'id' => $id ] ), 'image/png' );
		$this->assertLessThan( 1024, $size[0], 'A smaller preview was made.' );

		add_filter( 'tsmlt_mcp_image_preview_max_bytes', [ $this, 'huge_limit' ] );
		$this->assertSame( ImagePreview::MAX_BYTES, ImagePreview::max_bytes(), 'The limit cannot be raised.' );
		remove_filter( 'tsmlt_mcp_image_preview_max_bytes', [ $this, 'huge_limit' ] );

		$temp = self::temp_files();
		add_filter( 'tsmlt_mcp_image_preview_max_bytes', [ $this, 'tiny_limit' ] );
		$result = self::call( [ 'id' => $id ] );
		remove_filter( 'tsmlt_mcp_image_preview_max_bytes', [ $this, 'tiny_limit' ] );
		$this->assertWPError( $result );
		$this->assertSame( ImagePreview::ERROR_TOO_LARGE, $result->get_error_code() );
		$this->assertSame( $temp, self::temp_files(), 'No temporary file left after failure.' );
	}

	/**
	 * Invalid, missing, deleted, trashed, unreadable and non-media IDs.
	 */
	public function test_invalid_attachments() {
		$this->assertWPError( self::call( [ 'id' => 0 ] ), 'Schema minimum.' );
		$this->assertSame( 'tsmlt_invalid_input', GetMediaImagePreview::instance()->execute( [] )->get_error_code() );
		$this->assertSame( 'tsmlt_invalid_input', GetMediaImagePreview::instance()->execute( [ 'ids' => [ 1 ] ] )->get_error_code() );
		$this->assertSame( 'tsmlt_attachment_not_found', self::call( [ 'id' => 999999 ] )->get_error_code() );
		$this->assertSame( 'tsmlt_attachment_not_found', self::call( [ 'id' => (int) self::factory()->post->create() ] )->get_error_code() );

		$deleted = $this->attach_bytes( 'del.jpg', self::encoded( 'image/jpeg' ), 'image/jpeg' );
		wp_delete_attachment( $deleted, true );
		$this->assertSame( 'tsmlt_attachment_not_found', self::call( [ 'id' => $deleted ] )->get_error_code() );

		$trashed = $this->attach_bytes( 'trash.jpg', self::encoded( 'image/jpeg' ), 'image/jpeg' );
		wp_trash_post( $trashed );
		$this->assertSame( 'tsmlt_attachment_not_found', self::call( [ 'id' => $trashed ] )->get_error_code() );

		$missing = $this->attach_bytes( 'gone.jpg', self::encoded( 'image/jpeg' ), 'image/jpeg' );
		wp_delete_file( $this->dir . '/gone.jpg' );
		$this->assertSame( ImagePreview::ERROR_FILE, self::call( [ 'id' => $missing ] )->get_error_code() );

		$locked = $this->attach_bytes( 'locked.jpg', self::encoded( 'image/jpeg' ), 'image/jpeg' );
		chmod( $this->dir . '/locked.jpg', 0000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		if ( ! is_readable( $this->dir . '/locked.jpg' ) ) {
			$this->assertSame( ImagePreview::ERROR_FILE, self::call( [ 'id' => $locked ] )->get_error_code() );
		}

		$corrupt = $this->attach_bytes( 'bad.jpg', 'not an image', 'image/jpeg' );
		$this->assertSame( ImagePreview::ERROR_FILE, self::call( [ 'id' => $corrupt ] )->get_error_code() );
	}

	/**
	 * SVG, GIF and offloaded files have no preview.
	 */
	public function test_unsupported_and_remote() {
		$svg = $this->attach_bytes( 'v.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>', 'image/svg+xml' );
		$gif = $this->attach_bytes( 'a.gif', base64_decode( 'R0lGODlhAQABAAAAACw=' ), 'image/gif' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Tiny GIF fixture.
		$this->assertSame( ImagePreview::ERROR_UNSUPPORTED, self::call( [ 'id' => $svg ] )->get_error_code() );
		$this->assertSame( ImagePreview::ERROR_UNSUPPORTED, self::call( [ 'id' => $gif ] )->get_error_code() );

		$remote       = $this->attach_bytes( 'r.jpg', self::encoded( 'image/jpeg' ), 'image/jpeg' );
		$this->remote = $remote;
		$this->assertSame( ImagePreview::ERROR_FILE, self::call( [ 'id' => $remote ] )->get_error_code() );
	}

	/**
	 * The manage_options and read_post checks apply, in the method too.
	 */
	public function test_permissions() {
		$id = $this->attach_bytes( 'perm.jpg', self::encoded( 'image/jpeg' ), 'image/jpeg' );

		$this->denied = [ $id => 'read_post' ];
		$this->assertSame( 'tsmlt_forbidden', self::call( [ 'id' => $id ] )->get_error_code() );
		$this->denied = [];

		foreach ( [ 0, (int) self::factory()->user->create( [ 'role' => 'editor' ] ) ] as $user ) {
			wp_set_current_user( $user );
			$this->assertFalse( GetMediaImagePreview::instance()->check_permission( [ 'id' => $id ] ) );
			$result = GetMediaImagePreview::instance()->execute( [ 'id' => $id ] );
			$this->assertContains( $result->get_error_code(), [ 'tsmlt_unauthenticated', 'tsmlt_forbidden' ] );
		}
	}

	/**
	 * An encoding failure after the temporary file was written leaves no file.
	 */
	public function test_failure_cleans_up() {
		$id = $this->attach_bytes( 'fail.jpg', self::encoded( 'image/jpeg' ), 'image/jpeg' );
		add_filter( 'image_editor_output_format', [ $this, 'gif_output' ] );
		$temp = self::temp_files();

		$result = self::call( [ 'id' => $id ] );

		$this->assertSame( ImagePreview::ERROR_FAILED, $result->get_error_code() );
		$this->assertSame( $temp, self::temp_files() );
	}

	/**
	 * The metadata removal itself, on encoded bytes that still carry metadata
	 * (as an encoder that keeps it, such as Imagick without a resize, would
	 * write): every block is removed and the image stays readable. Independent
	 * of the image editor this server has.
	 */
	public function test_strip_removes_metadata_from_encoded_bytes() {
		$fixtures = [
			'image/jpeg' => self::jpeg_with_metadata(),
			'image/png'  => self::png_with_text(),
		];
		if ( wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] ) ) {
			$fixtures['image/webp'] = self::webp_with_exif();
		}
		foreach ( $fixtures as $mime => $bytes ) {
			$this->assertStringContainsString( self::SECRET, $bytes, $mime . ': precondition.' );
			$this->assertFalse( ImagePreview::is_clean( $bytes, $mime ), $mime . ': detected as not clean.' );

			$clean = ImagePreview::strip( $bytes, $mime );

			$this->assertIsString( $clean, $mime );
			$this->assertStringNotContainsString( self::SECRET, $clean, $mime );
			$this->assertTrue( ImagePreview::is_clean( $clean, $mime ), $mime );
			$this->assertSame( array_slice( getimagesizefromstring( $bytes ), 0, 2 ), array_slice( getimagesizefromstring( $clean ), 0, 2 ), $mime . ': still a readable image of the same size.' );
		}
	}

	/**
	 * Over the real MCP route: an image content block, no structured content,
	 * and no output schema in the tool listing.
	 */
	public function test_mcp_image_content_block() {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'The MCP Adapter is not loaded.' );
		}
		$id    = $this->attach_bytes( 'mcp.jpg', self::jpeg_with_metadata(), 'image/jpeg' );
		$route = '/media-library-tools/mcp';
		$this->connect( $route );

		$tools = $this->send( $route, 'tools/list' )['result']['tools'];
		$tool  = array_values( wp_list_filter( $tools, [ 'name' => 'tsmlt-get-media-image-preview' ] ) )[0];
		$this->assertArrayNotHasKey( 'outputSchema', $tool );

		$result = $this->send(
			$route,
			'tools/call',
			[
				'name'      => 'tsmlt-get-media-image-preview',
				'arguments' => [ 'id' => $id ],
			]
		)['result'];

		$this->assertFalse( $result['isError'] );
		$this->assertArrayNotHasKey( 'structuredContent', $result );
		$this->assertCount( 1, $result['content'] );
		$this->assertSame( 'image', $result['content'][0]['type'] );
		$this->assertSame( 'image/jpeg', $result['content'][0]['mimeType'] );
		$bytes = base64_decode( $result['content'][0]['data'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding the returned image.
		$this->assertSame( 'image/jpeg', ImagePreview::detect( (string) $bytes ) );
		$this->assertStringNotContainsString( self::SECRET, (string) $bytes );
		$this->assertStringNotContainsString( 'mcp.jpg', wp_json_encode( $result ) );
	}

	/**
	 * Send one MCP request.
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
	 * Open an MCP session.
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
