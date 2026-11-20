<?php
/**
 * `tsmlt/audit-media-health`: library-wide media health in windows by
 * attachment ID (keyset paging), with GetMediaHealth::inspect(); read-only
 * and stateless; listed on the dedicated and the default server.
 *
 * Each test starts its cursor after the highest attachment ID that existed
 * before it, so only its own fixtures are in the windows it reads.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Abilities\Media\AuditMediaHealth;
use TinySolutions\mlt\Abilities\Media\GetMediaHealth;

/**
 * AuditMediaHealthAbilityTest
 */
class AuditMediaHealthAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/audit-media-health';

	/**
	 * Highest attachment ID before the test.
	 *
	 * @var int
	 */
	private $start = 0;

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
	 * Administrator; cursor after the existing attachments.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( self::NAME ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$last           = get_posts(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
			]
		);
		$this->start    = $last ? (int) $last[0] : 0;
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
	 * An attachment whose file is missing (issue file_missing).
	 *
	 * @param string $name File name.
	 *
	 * @return int
	 */
	private function broken( string $name ): int {
		return (int) self::factory()->attachment->create(
			[
				'post_mime_type' => 'application/pdf',
				'file'           => '2026/11/' . $name,
			]
		);
	}

	/**
	 * A healthy PDF attachment with its file.
	 *
	 * @param string $name File name.
	 *
	 * @return int
	 */
	private function healthy( string $name ): int {
		$relative = '2026/11/' . $name;
		$path     = trailingslashit( wp_upload_dir()['basedir'] ) . $relative;
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, "%PDF-1.4\n%%EOF\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $path;
		return (int) self::factory()->attachment->create(
			[
				'post_mime_type' => 'application/pdf',
				'file'           => $relative,
			]
		);
	}

	/**
	 * Upload a WordPress test image under a name.
	 *
	 * @param string $source Test image.
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
	 * Change attachment metadata.
	 *
	 * @param int   $id      Attachment ID.
	 * @param array $changes Keys to set.
	 *
	 * @return void
	 */
	private static function change_metadata( int $id, array $changes ) {
		wp_update_attachment_metadata( $id, array_merge( wp_get_attachment_metadata( $id ), $changes ) );
	}

	/**
	 * Run the ability.
	 *
	 * @param array $input Input.
	 *
	 * @return mixed
	 */
	private static function audit( array $input ) {
		return wp_get_ability( self::NAME )->execute( $input );
	}

	/**
	 * Every listed ID of an audit from a cursor to the end.
	 *
	 * @param int $after Cursor.
	 * @param int $limit Window.
	 *
	 * @return array{ids: int[], pages: array[]}
	 */
	private static function walk( int $after, int $limit ): array {
		$ids   = [];
		$pages = [];
		$calls = 0;
		do {
			++$calls;
			$page    = self::audit(
				[
					'after_id' => $after,
					'limit'    => $limit,
				]
			);
			$pages[] = $page;
			$ids     = array_merge( $ids, array_column( $page['items'], 'id' ) );
			$after   = (int) $page['next_after_id'];
		} while ( ! $page['done'] && $calls < 50 );
		return [
			'ids'   => $ids,
			'pages' => $pages,
		];
	}

	/**
	 * Windows of 3 cover every attachment once, in ID order; the cursor
	 * chains; done comes from the look-ahead, also when the last window is
	 * full.
	 */
	public function test_pagination() {
		$ids = [];
		for ( $i = 0; $i < 7; $i++ ) {
			$ids[] = $this->broken( 'page-' . $i . '.pdf' );
		}

		$walk = self::walk( $this->start, 3 );
		$this->assertSame( $ids, $walk['ids'], 'Every attachment once, in order.' );
		$this->assertSame( [ 3, 3, 1 ], array_column( $walk['pages'], 'examined' ) );
		$this->assertSame( [ false, false, true ], array_column( $walk['pages'], 'done' ) );
		$this->assertSame( [ $ids[2], $ids[5], $ids[6] ], array_column( $walk['pages'], 'next_after_id' ) );

		wp_delete_post( $ids[6], true );
		$full = self::walk( $this->start, 3 );
		$this->assertSame( [ false, true ], array_column( $full['pages'], 'done' ), 'A full last window is done when nothing follows.' );

		$schema = wp_get_ability( self::NAME )->get_output_schema();
		$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $walk['pages'][0] ), true ), $schema, 'result' ) );
	}

	/**
	 * A cursor past the last attachment examines nothing.
	 */
	public function test_cursor_beyond_last() {
		$id   = $this->broken( 'last.pdf' );
		$page = self::audit( [ 'after_id' => $id ] );
		$this->assertSame( [], $page['items'] );
		$this->assertSame( 0, $page['examined'] );
		$this->assertNull( $page['next_after_id'] );
		$this->assertTrue( $page['done'] );
		$this->assertSame( array_fill_keys( GetMediaHealth::ISSUES, 0 ), $page['issues_summary'] );

		$far = self::audit( [ 'after_id' => PHP_INT_MAX >> 1 ] );
		$this->assertSame( 0, $far['examined'] );
		$this->assertTrue( $far['done'] );
	}

	/**
	 * Default window 50; 100 accepted; total_attachments counts the
	 * library.
	 */
	public function test_limits_and_total() {
		for ( $i = 0; $i < 51; $i++ ) {
			$this->broken( 'many-' . $i . '.pdf' );
		}
		$default = self::audit( [ 'after_id' => $this->start ] );
		$this->assertSame( 50, $default['examined'] );
		$this->assertFalse( $default['done'] );

		$max = self::audit(
			[
				'after_id' => $this->start,
				'limit'    => 100,
			]
		);
		$this->assertSame( 51, $max['examined'] );
		$this->assertTrue( $max['done'] );

		$library = (int) ( new WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		) )->found_posts;
		$this->assertSame( $library, $max['total_attachments'] );
	}

	/**
	 * Changes between calls: a new upload is reached later; deleting or
	 * trashing ahead of the cursor causes no duplicates; a restore behind
	 * the cursor is not seen by this audit.
	 */
	public function test_library_changes_between_calls() {
		$restored = $this->broken( 'restored.pdf' );
		wp_trash_post( $restored );
		$ids = [];
		for ( $i = 0; $i < 4; $i++ ) {
			$ids[] = $this->broken( 'change-' . $i . '.pdf' );
		}

		$first = self::audit(
			[
				'after_id' => $this->start,
				'limit'    => 2,
			]
		);
		$this->assertSame( [ $ids[0], $ids[1] ], array_column( $first['items'], 'id' ) );

		wp_untrash_post( $restored );
		wp_update_post(
			[
				'ID'          => $restored,
				'post_status' => 'inherit',
			]
		);
		wp_trash_post( $ids[2] );
		$new = $this->broken( 'uploaded-later.pdf' );

		$rest = self::walk( (int) $first['next_after_id'], 2 );
		$this->assertSame( [ $ids[3], $new ], $rest['ids'], 'Trashed one skipped, new upload reached, no duplicates.' );
		$this->assertNotContains( $restored, array_merge( array_column( $first['items'], 'id' ), $rest['ids'] ), 'Restored behind the cursor: not seen.' );
	}

	/**
	 * Only items with issues are listed; healthy ones are counted; every
	 * issue code is found and summarised; the filter narrows items only.
	 */
	public function test_issues_summary_and_filter() {
		$healthy = $this->healthy( 'fine.pdf' );
		$codes   = [];

		$codes['no_attached_file'] = $this->upload( 'canola.jpg', 'no-reference.jpg' );
		delete_post_meta( $codes['no_attached_file'], '_wp_attached_file' );
		$codes['file_missing']  = $this->broken( 'missing.pdf' );
		$codes['mime_mismatch'] = $this->upload( 'test-image.png', 'really-png.png' );
		wp_update_post(
			[
				'ID'             => $codes['mime_mismatch'],
				'post_mime_type' => 'image/jpeg',
			]
		);
		$codes['metadata_missing'] = $this->upload( 'canola.jpg', 'no-meta.jpg' );
		delete_post_meta( $codes['metadata_missing'], '_wp_attachment_metadata' );
		$codes['filesize_mismatch'] = $this->upload( 'canola.jpg', 'filesize.jpg' );
		self::change_metadata( $codes['filesize_mismatch'], [ 'filesize' => 1 ] );
		$codes['dimensions_mismatch'] = $this->upload( 'canola.jpg', 'dimensions.jpg' );
		self::change_metadata( $codes['dimensions_mismatch'], [ 'width' => 800 ] );
		$parent                  = self::factory()->post->create();
		$codes['invalid_parent'] = $this->healthy( 'orphan.pdf' );
		wp_update_post(
			[
				'ID'          => $codes['invalid_parent'],
				'post_parent' => $parent,
			]
		);
		wp_delete_post( $parent, true );
		$codes['file_unreadable'] = $this->upload( 'canola.jpg', 'locked.jpg' );
		$locked                   = get_attached_file( $codes['file_unreadable'] );
		chmod( $locked, 0000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test fixture.
		$this->locked_files[] = $locked;
		$unreadable_testable  = ! is_readable( $locked );

		$page = self::audit(
			[
				'after_id' => $this->start,
				'limit'    => 100,
			]
		);
		$this->assertNotContains( $healthy, array_column( $page['items'], 'id' ), 'Healthy items are not listed.' );
		$this->assertSame( 1, $page['healthy'] );
		$this->assertSame( 9, $page['examined'] );
		foreach ( $codes as $code => $id ) {
			if ( 'file_unreadable' === $code && ! $unreadable_testable ) {
				continue;
			}
			$this->assertSame( 1, $page['issues_summary'][ $code ], $code );
			$item = array_column( $page['items'], null, 'id' )[ $id ];
			$this->assertEquals( GetMediaHealth::inspect( $id ), $item, $code . ': the get-media-health item.' );
		}

		$filtered = self::audit(
			[
				'after_id' => $this->start,
				'limit'    => 100,
				'issue'    => 'mime_mismatch',
			]
		);
		$this->assertSame( [ $codes['mime_mismatch'] ], array_column( $filtered['items'], 'id' ) );
		$this->assertSame( $page['issues_summary'], $filtered['issues_summary'], 'Counters cover every examined item.' );
		$this->assertSame( $page['healthy'], $filtered['healthy'] );
		if ( ! $unreadable_testable ) {
			$this->markTestSkipped( 'Files stay readable for this user (running as root?); file_unreadable not tested.' );
		}
	}

	/**
	 * Unreadable and uneditable attachments are counted, never listed; only
	 * library (inherit) attachments are examined.
	 */
	public function test_hidden_forbidden_and_inherit_only() {
		$hidden  = $this->broken( 'hidden.pdf' );
		$locked  = $this->broken( 'locked-out.pdf' );
		$shown   = $this->broken( 'shown.pdf' );
		$trashed = $this->broken( 'trashed.pdf' );
		wp_trash_post( $trashed );
		$private                 = (int) self::factory()->attachment->create(
			[
				'post_mime_type' => 'application/pdf',
				'file'           => '2026/11/private.pdf',
				'post_status'    => 'private',
			]
		);
		$this->denied[ $hidden ] = 'read_post';
		$this->denied[ $locked ] = 'edit_post';

		$page = self::audit(
			[
				'after_id' => $this->start,
				'limit'    => 100,
			]
		);
		$this->assertSame( 3, $page['examined'], 'Trashed and private attachments are not examined.' );
		$this->assertSame( 1, $page['hidden'] );
		$this->assertSame( 1, $page['forbidden'] );
		$this->assertSame( [ $shown ], array_column( $page['items'], 'id' ) );
		$this->assertSame( 1, $page['issues_summary']['file_missing'], 'Inaccessible items are not summarised.' );
		$json = wp_json_encode( $page['items'] );
		foreach ( [ $hidden, $locked, $trashed, $private ] as $id ) {
			$this->assertStringNotContainsString( '"id":' . $id . ',', $json );
		}
	}

	/**
	 * Invalid requests are refused as a whole.
	 */
	public function test_invalid_input() {
		foreach (
			[
				'string after'   => [ 'after_id' => '5' ],
				'float after'    => [ 'after_id' => 1.5 ],
				'negative after' => [ 'after_id' => -1 ],
				'limit 0'        => [ 'limit' => 0 ],
				'limit 101'      => [ 'limit' => 101 ],
				'string limit'   => [ 'limit' => '10' ],
				'unknown issue'  => [ 'issue' => 'broken' ],
				'issue not text' => [ 'issue' => 1 ],
				'extra key'      => [ 'repair' => true ],
				'not an object'  => 'all',
			] as $label => $input
		) {
			$this->assertWPError( AuditMediaHealth::instance()->execute( $input ), $label );
		}
		$this->assertIsArray( AuditMediaHealth::instance()->execute( [] ), 'No input starts at 0.' );
		$this->assertIsArray( AuditMediaHealth::instance()->execute( null ) );
	}

	/**
	 * Only administrators.
	 */
	public function test_requires_manage_options() {
		foreach ( [ 'editor', 'author' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertFalse( wp_get_ability( self::NAME )->check_permissions( [] ), $role );
			$this->assertWPError( AuditMediaHealth::instance()->execute( [] ), $role );
		}
		wp_set_current_user( 0 );
		$this->assertWPError( AuditMediaHealth::instance()->execute( [] ) );
	}

	/**
	 * No paths, file names, alt text, EXIF/GPS or error text.
	 */
	public function test_no_private_data() {
		$id = $this->upload( 'canola.jpg', 'private-name.jpg' );
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
		$this->broken( 'gone-private.pdf' );

		$json = wp_json_encode(
			self::audit(
				[
					'after_id' => $this->start,
					'limit'    => 100,
				]
			)
		);
		foreach ( [ wp_upload_dir()['basedir'], 'private-name', 'gone-private', '.jpg', '.pdf', 'Private alt text', 'image_meta', 'SecretCam', '51.5072', 'latitude', 'Warning', 'Exception' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, $needle );
		}
		$this->assertDoesNotMatchRegularExpression( '#[0-9]{4}\\\\/[0-9]{2}\\\\/#', $json, 'No upload folders.' );
	}

	/**
	 * Nothing is written: no write queries; metadata, posts, post meta,
	 * options, files and cron unchanged.
	 */
	public function test_read_only() {
		$parent = self::factory()->post->create();
		$id     = $this->upload( 'canola.jpg', 'readonly.jpg' );
		wp_update_post(
			[
				'ID'          => $id,
				'post_parent' => $parent,
			]
		);
		wp_trash_post( $parent );
		self::change_metadata( $id, [ 'filesize' => 1 ] );
		$this->broken( 'readonly-missing.pdf' );
		$file     = get_attached_file( $id );
		$metadata = wp_get_attachment_metadata( $id );
		$meta     = get_post_meta( $id );
		$post     = get_post( $id, ARRAY_A );
		$options  = wp_load_alloptions();
		$folder   = scandir( dirname( $file ) );
		$cron     = _get_cron_array();
		add_filter( 'query', [ $this, 'record_query' ] );

		$walk = self::walk( $this->start, 1 );

		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertCount( 2, $walk['ids'] );
		$this->assertSame( [], $this->writes );
		$this->assertSame( $metadata, wp_get_attachment_metadata( $id ) );
		$this->assertSame( $meta, get_post_meta( $id ) );
		$this->assertSame( $post, get_post( $id, ARRAY_A ), 'Parent not relinked.' );
		$this->assertSame( $options, wp_load_alloptions(), 'No cursor or state stored.' );
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
		$id = $this->broken( 'transport.pdf' );
		foreach ( [ '/media-library-tools/mcp', '/mcp/mcp-adapter-default-server' ] as $route ) {
			$this->connect( $route );
			$tools = array_column( $this->send( $route, 'tools/list' )['result']['tools'], null, 'name' );
			$this->assertArrayHasKey( 'tsmlt-audit-media-health', $tools, $route );
			$this->assertFalse( $tools['tsmlt-audit-media-health']['inputSchema']['additionalProperties'], $route );
			$call = $this->send(
				$route,
				'tools/call',
				[
					'name'      => 'tsmlt-audit-media-health',
					'arguments' => [ 'after_id' => $this->start ],
				]
			)['result'];
			$this->assertFalse( $call['isError'], $route . ' ' . wp_json_encode( $call ) );
			$this->assertSame( [ $id ], array_column( $call['structuredContent']['items'], 'id' ) );
		}

		foreach ( [ 'mcp-adapter-get-ability-info', 'mcp-adapter-execute-ability' ] as $gateway ) {
			$arguments = 'mcp-adapter-get-ability-info' === $gateway
				? [ 'ability_name' => self::NAME ]
				: [
					'ability_name' => self::NAME,
					'parameters'   => [ 'after_id' => 0 ],
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
