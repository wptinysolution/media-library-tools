<?php
/**
 * `tsmlt/preview-media-rename`: read-only preview of renaming files to typed
 * names, with the rules of Fns::wp_rename_attachment(); Pro strategies
 * refused; listed on the dedicated and the default server.
 *
 * Generated fixtures only (WordPress test images).
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Abilities\Media\PreviewMediaRename;
use TinySolutions\mlt\Helpers\Fns;

/**
 * PreviewMediaRenameAbilityTest
 */
class PreviewMediaRenameAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/preview-media-rename';

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
			if ( is_string( $file ) && file_exists( $file ) ) {
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
	 * Upload a JPEG under a name.
	 *
	 * @param string $name File name.
	 *
	 * @return int
	 */
	private function jpeg( string $name ): int {
		$temp = trailingslashit( get_temp_dir() ) . $name;
		copy( DIR_TESTDATA . '/images/canola.jpg', $temp );
		$this->temp_files[] = $temp;
		$id                 = (int) self::factory()->attachment->create_upload_object( $temp );
		$this->temp_files[] = get_attached_file( $id );
		$metadata           = wp_get_attachment_metadata( $id );
		foreach ( (array) ( $metadata['sizes'] ?? [] ) as $size ) {
			$this->temp_files[] = dirname( get_attached_file( $id ) ) . '/' . $size['file'];
		}
		return $id;
	}

	/**
	 * Run the ability.
	 *
	 * @param array $items Items.
	 *
	 * @return mixed
	 */
	private static function preview( array $items ) {
		return wp_get_ability( self::NAME )->execute( [ 'items' => $items ] );
	}

	/**
	 * Preview one item.
	 *
	 * @param int|string $id       ID.
	 * @param string     $filename Typed name.
	 *
	 * @return array
	 */
	private static function one( $id, string $filename ): array {
		return PreviewMediaRename::instance()->execute(
			[
				'items' => [
					[
						'id'       => $id,
						'filename' => $filename,
					],
				],
			]
		)['items'][0];
	}

	/**
	 * Single preview: resulting name, extension kept, sizes counted.
	 */
	public function test_single_preview() {
		$id     = $this->jpeg( 'rename-source.jpg' );
		$result = self::preview(
			[
				[
					'id'       => $id,
					'filename' => 'new-name',
				],
			]
		);
		$item   = $result['items'][0];

		$this->assertSame( 'ready', $item['status'] );
		$this->assertSame( 'rename-source.jpg', $item['current_filename'] );
		$this->assertSame( 'new-name.jpg', $item['proposed_filename'] );
		$this->assertFalse( $item['sanitized'] );
		$this->assertFalse( $item['extension_ignored'] );
		$this->assertFalse( $item['unchanged'] );
		$this->assertFalse( $item['needs_unique_suffix'] );
		$this->assertSame( count( wp_get_attachment_metadata( $id )['sizes'] ), $item['affects']['generated_sizes'] );
		$this->assertGreaterThan( 0, $item['affects']['generated_sizes'] );
		$this->assertTrue( $item['affects']['content_links_updated'] );
		$this->assertSame( 1, $result['summary']['ready'] );
		$this->assertArrayNotHasKey( 'ok', $result['summary'] );
		$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), wp_get_ability( self::NAME )->get_output_schema(), 'result' ) );
	}

	/**
	 * Sanitizing and extension handling follow Fns::rename_target_name()
	 * (the rename's own rules): typed extensions are dropped, the current
	 * one kept; "-scaled" is removed; unchanged names are reported.
	 */
	public function test_sanitization_and_extension() {
		$id = $this->jpeg( 'rename-rules.jpg' );

		$typed = self::one( $id, 'My Photo!.png' );
		$this->assertSame( 'ready', $typed['status'] );
		$this->assertSame( Fns::rename_target_name( 'My Photo!.png' ) . '.jpg', $typed['proposed_filename'] );
		$this->assertStringEndsWith( '.jpg', $typed['proposed_filename'] );
		$this->assertTrue( $typed['extension_ignored'] );
		$this->assertTrue( $typed['sanitized'] );

		$scaled = self::one( $id, 'holiday-scaled' );
		$this->assertSame( 'holiday.jpg', $scaled['proposed_filename'] );
		$this->assertTrue( $scaled['sanitized'] );

		$same = self::one( $id, 'rename-rules.jpeg' );
		$this->assertSame( 'unchanged', $same['status'] );
		$this->assertTrue( $same['unchanged'] );
		$this->assertSame( 'rename-rules.jpg', $same['proposed_filename'] );

		$this->assertSame( 'invalid_filename', self::one( $id, '...' )['status'] );
		foreach ( [ '../secret', 'a/b', 'a\\b', "bad\x01name" ] as $name ) {
			$this->assertSame( 'invalid_filename', self::one( $id, $name )['status'], $name );
		}
	}

	/**
	 * The helper keeps wp_rename_attachment()'s previous rules.
	 */
	public function test_shared_helper_rules() {
		$this->assertSame( 'new-name', Fns::rename_target_name( 'new name.png' ) );
		$this->assertSame( 'photo', Fns::rename_target_name( 'photo-scaled.jpg' ) );
		$this->assertSame( 'photo', Fns::rename_target_name( 'photo-rotated' ) );
		$this->assertSame( 'my', Fns::rename_target_name( 'my.photo' ) );
	}

	/**
	 * A taken name gets the suffix wp_unique_filename() would add; two items
	 * asking for the same name are flagged.
	 */
	public function test_unique_suffix_and_same_name_in_request() {
		$taken  = $this->jpeg( 'taken-name.jpg' );
		$first  = $this->jpeg( 'first-source.jpg' );
		$second = $this->jpeg( 'second-source.jpg' );

		$item = self::one( $first, 'taken-name' );
		$this->assertTrue( $item['needs_unique_suffix'] );
		$this->assertSame( 'taken-name-1.jpg', $item['proposed_filename'] );
		$this->assertFileExists( get_attached_file( $taken ) );

		$both = self::preview(
			[
				[
					'id'       => $first,
					'filename' => 'shared',
				],
				[
					'id'       => $second,
					'filename' => 'shared',
				],
			]
		)['items'];
		$this->assertSame( [ true, true ], array_column( $both, 'same_name_in_request' ) );
		$this->assertSame( [ false, false ], array_column( $both, 'needs_unique_suffix' ) );
	}

	/**
	 * A scaled-upload original and backup sizes are reported as left with
	 * their old names.
	 */
	public function test_original_and_backup_sizes() {
		$id       = $this->jpeg( 'with-original.jpg' );
		$file     = get_attached_file( $id );
		$original = dirname( $file ) . '/with-original-full.jpg';
		copy( $file, $original );
		$this->temp_files[]         = $original;
		$metadata                   = wp_get_attachment_metadata( $id );
		$metadata['original_image'] = 'with-original-full.jpg';
		wp_update_attachment_metadata( $id, $metadata );
		update_post_meta(
			$id,
			'_wp_attachment_backup_sizes',
			[
				'full-orig'      => [ 'file' => 'with-original-orig.jpg' ],
				'thumbnail-orig' => [ 'file' => 'with-original-orig-150x150.jpg' ],
			]
		);

		$affects = self::one( $id, 'renamed' )['affects'];
		$this->assertTrue( $affects['original_image_kept'] );
		$this->assertSame( 2, $affects['backup_sizes'] );
	}

	/**
	 * Per-item statuses: missing file, invalid and unknown IDs, posts that
	 * are not attachments, unreadable and uneditable attachments.
	 */
	public function test_item_statuses() {
		$ok                      = $this->jpeg( 'status-ok.jpg' );
		$missing                 = (int) self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'file'           => '2026/10/gone-file.jpg',
			]
		);
		$post                    = self::factory()->post->create();
		$hidden                  = $this->jpeg( 'status-hidden.jpg' );
		$locked                  = $this->jpeg( 'status-locked.jpg' );
		$this->denied[ $hidden ] = 'read_post';
		$this->denied[ $locked ] = 'edit_post';

		$missing_item = self::one( $missing, 'x' );
		$this->assertSame( 'file_missing', $missing_item['status'] );
		$this->assertSame( 'gone-file.jpg', $missing_item['current_filename'] );
		$this->assertSame( 'invalid_id', self::one( '5', 'x' )['status'] );
		$this->assertSame( 'invalid_id', self::one( 0, 'x' )['status'] );
		$this->assertSame( 'not_found', self::one( 999999, 'x' )['status'] );
		$this->assertSame( 'not_found', self::one( $post, 'x' )['status'] );
		$this->assertSame( 'not_found', self::one( $hidden, 'x' )['status'] );
		$this->assertSame( 'forbidden', self::one( $locked, 'x' )['status'] );
		$this->assertSame( '', self::one( $hidden, 'x' )['current_filename'], 'Nothing about an unreadable attachment.' );

		wp_trash_post( $ok );
		$this->assertSame( 'not_found', self::one( $ok, 'x' )['status'], 'Trashed media is not in the library.' );
	}

	/**
	 * Pro strategy keywords are refused, never used as names.
	 */
	public function test_pro_keywords_refused() {
		$id = $this->jpeg( 'pro-keyword.jpg' );
		foreach ( [ 'bulkRenameByPostTitle', 'bulkRenameBySKU', 'bulkRenameByAltText', 'bulkAiFilename', 'BULKRENAMEBYSKU.jpg', 'bulk-rename-by-post-title' ] as $keyword ) {
			$item = self::one( $id, $keyword );
			$this->assertSame( 'pro_strategy_not_supported', $item['status'], $keyword );
			$this->assertSame( '', $item['proposed_filename'] );
		}
	}

	/**
	 * Invalid requests are refused as a whole.
	 */
	public function test_invalid_input() {
		$id   = $this->jpeg( 'input.jpg' );
		$item = [
			'id'       => $id,
			'filename' => 'x',
		];
		foreach (
			[
				'no items'         => [],
				'empty items'      => [ 'items' => [] ],
				'21 items'         => [ 'items' => array_fill( 0, 21, $item ) ],
				'extra key'        => [
					'items' => [ $item ],
					'path'  => '/',
				],
				'item extra key'   => [ 'items' => [ $item + [ 'mode' => 'title' ] ] ],
				'missing filename' => [ 'items' => [ [ 'id' => $id ] ] ],
				'empty filename'   => [
					'items' => [
						[
							'id'       => $id,
							'filename' => '',
						],
					],
				],
				'too long'         => [
					'items' => [
						[
							'id'       => $id,
							'filename' => str_repeat( 'a', 201 ),
						],
					],
				],
			] as $label => $input
		) {
			$this->assertWPError( PreviewMediaRename::instance()->execute( $input ), $label );
		}
	}

	/**
	 * Only administrators may preview.
	 */
	public function test_requires_manage_options() {
		$id    = $this->jpeg( 'perm.jpg' );
		$input = [
			'items' => [
				[
					'id'       => $id,
					'filename' => 'x',
				],
			],
		];
		foreach ( [ 'editor', 'author' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertFalse( wp_get_ability( self::NAME )->check_permissions( $input ), $role );
			$this->assertWPError( PreviewMediaRename::instance()->execute( $input ), $role );
		}
		wp_set_current_user( 0 );
		$this->assertWPError( PreviewMediaRename::instance()->execute( $input ) );
	}

	/**
	 * Nothing is written or moved; no folders or paths are returned.
	 */
	public function test_read_only() {
		$id       = $this->jpeg( 'readonly.jpg' );
		$other    = $this->jpeg( 'readonly-other.jpg' );
		$file     = get_attached_file( $id );
		$listing  = scandir( dirname( $file ) );
		$metadata = wp_get_attachment_metadata( $id );
		$post     = get_post( $id, ARRAY_A );
		$parent   = wp_get_post_parent_id( $id );
		add_filter( 'query', [ $this, 'record_query' ] );

		$result = self::preview(
			[
				[
					'id'       => $id,
					'filename' => 'readonly-other',
				],
				[
					'id'       => $other,
					'filename' => 'fresh',
				],
			]
		);

		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertSame( [], $this->writes );
		clearstatcache();
		$this->assertSame( $listing, scandir( dirname( $file ) ) );
		$this->assertSame( $metadata, wp_get_attachment_metadata( $id ) );
		$this->assertSame( $post, get_post( $id, ARRAY_A ) );
		$this->assertSame( $parent, wp_get_post_parent_id( $id ) );
		$this->assertSame( $file, get_attached_file( $id ) );

		$json = wp_json_encode( $result );
		$this->assertStringNotContainsString( wp_upload_dir()['basedir'], $json );
		$this->assertStringNotContainsString( '\/', $json, 'No folders.' );
	}

	/**
	 * Listed by name on the dedicated and the default server; runs on both;
	 * not offered through the default gateway.
	 */
	public function test_listed_on_both_servers() {
		$this->assertFalse( wp_get_ability( self::NAME )->get_meta()['mcp']['public'] );
		$this->assertTrue( wp_get_ability( self::NAME )->get_meta()['annotations']['readonly'] );
		$this->assertContains( self::NAME, McpServerRegistration::instance()->get_tool_names() );

		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) || ! McpServerRegistration::adapter_supports_default_server() ) {
			$this->markTestSkipped( 'MCP Adapter 0.5.0+ not loaded; set MCP_ADAPTER_DIR to run the transport part.' );
		}
		$id = $this->jpeg( 'transport.jpg' );
		foreach ( [ '/media-library-tools/mcp', '/mcp/mcp-adapter-default-server' ] as $route ) {
			$this->connect( $route );
			$tools = array_column( $this->send( $route, 'tools/list' )['result']['tools'], null, 'name' );
			$this->assertArrayHasKey( 'tsmlt-preview-media-rename', $tools, $route );
			$call = $this->send(
				$route,
				'tools/call',
				[
					'name'      => 'tsmlt-preview-media-rename',
					'arguments' => [
						'items' => [
							[
								'id'       => $id,
								'filename' => 'over-mcp',
							],
						],
					],
				]
			)['result'];
			$this->assertFalse( $call['isError'], $route . ' ' . wp_json_encode( $call ) );
			$this->assertSame( 'over-mcp.jpg', $call['structuredContent']['items'][0]['proposed_filename'] );
		}
		$this->assertFileExists( get_attached_file( $id ), 'Not renamed.' );

		$info = $this->send(
			'/mcp/mcp-adapter-default-server',
			'tools/call',
			[
				'name'      => 'mcp-adapter-get-ability-info',
				'arguments' => [ 'ability_name' => self::NAME ],
			]
		)['result'];
		$this->assertTrue( $info['isError'], 'Not offered through the gateway.' );
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
