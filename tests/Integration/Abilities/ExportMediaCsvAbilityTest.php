<?php
/**
 * Integration tests for tsmlt/export-media-csv: permissions at both levels,
 * closed and bounded input, the order of the IDs kept, skipped IDs reported
 * alike (missing, not media, trashed, private, not readable), column
 * selection, exact values (readable parent or 0, Group names), CSV
 * encoding (RFC 4180, formula protection, numbers unprotected, invalid
 * UTF-8), no data beyond the allowlist, a fixed number of queries, and
 * MCP exposure on the dedicated endpoint only.
 *
 * Every attachment is a generated fixture. Outgoing HTTP is blocked.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\ExportMediaCsv;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Csv\MediaCsv;

require_once dirname( __DIR__ ) . '/Mcp/DefaultGatewayClientTrait.php';

/**
 * ExportMediaCsvAbilityTest
 */
class ExportMediaCsvAbilityTest extends WP_UnitTestCase {

	use DefaultGatewayClientTrait;

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/export-media-csv';

	/**
	 * Dedicated MCP route.
	 *
	 * @var string
	 */
	const SCOPED_ROUTE = '/media-library-tools/mcp';

	/**
	 * Post IDs denied read_post.
	 *
	 * @var int[]
	 */
	private $no_read = [];

	/**
	 * MCP sessions by route.
	 *
	 * @var array<string, string>
	 */
	private $sessions = [];

	/**
	 * JSON-RPC request counter.
	 *
	 * @var int
	 */
	private $request_id = 0;

	/**
	 * Administrator; filters for denied reads.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( self::NAME ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10, 4 );
		$this->no_read = [];
	}

	/**
	 * Remove filters.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		remove_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10 );
		parent::tear_down();
	}

	/**
	 * Refuse outgoing HTTP requests.
	 *
	 * @return WP_Error
	 */
	public function block_http() {
		return new WP_Error( 'blocked', 'No HTTP in tests.' );
	}

	/**
	 * Deny read_post on the posts in $this->no_read.
	 *
	 * @param string[] $caps    Capabilities.
	 * @param string   $cap     Capability checked.
	 * @param int      $user_id User ID.
	 * @param array    $args    Arguments.
	 *
	 * @return string[]
	 */
	public function deny_read( $caps, $cap, $user_id, $args ) {
		if ( 'read_post' === $cap && in_array( (int) ( $args[0] ?? 0 ), $this->no_read, true ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
	}

	/**
	 * A generated attachment.
	 *
	 * @param array $args Post arguments.
	 *
	 * @return int
	 */
	private function attachment( array $args = [] ): int {
		static $n = 0;
		++$n;
		return (int) self::factory()->attachment->create(
			array_merge(
				[
					'file'           => 'csv/image-' . $n . '.jpg',
					'post_mime_type' => 'image/jpeg',
				],
				$args
			)
		);
	}

	/**
	 * Run the ability through the Abilities API.
	 *
	 * @param mixed $input Input.
	 *
	 * @return mixed
	 */
	private static function call( $input ) {
		return wp_get_ability( self::NAME )->execute( $input );
	}

	/**
	 * Parse CSV text back into rows (RFC 4180, no escape character).
	 *
	 * @param string $csv CSV text.
	 *
	 * @return array[]
	 */
	private static function parse( string $csv ): array {
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream.
		fwrite( $handle, $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- In-memory stream.
		rewind( $handle );
		$rows = [];
		$row  = fgetcsv( $handle, 0, ',', '"', '' );
		while ( false !== $row ) {
			$rows[] = $row;
			$row    = fgetcsv( $handle, 0, ',', '"', '' );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
		return $rows;
	}

	/**
	 * Without Pro, the description has the client ask which images to
	 * include (names, file names, keywords, IDs or a post, page or product),
	 * find them with tsmlt/search-media, let the user choose, export exactly
	 * the chosen IDs with every column, ask to narrow more than 100 without
	 * splitting, and explain Pro (with the plugin's own Pro link) for a
	 * whole-library request; with Pro, it sends library exports to Pro.
	 */
	public function test_description_guides_the_selection() {
		$description = wp_get_ability( self::NAME )->get_description();
		foreach (
			[
				'1 to 100 distinct IDs per export',
				'If tsmlt/export-media-csv-bulk (Pro) is available, use it for any CSV export of the Media Library, at once and without asking which images',
				'ask which images to include: they can give image names, file names, keywords, media IDs, or a post, page or WooCommerce product by title or ID',
				'Find them with tsmlt/search-media (search, filename, ids, content_id or content_title',
				'show the matches with their title, file name, date and ID so similar images can be told apart',
				'let the user choose specific images or all of them; if nothing matches, say so and offer another search',
				'export exactly the IDs of the images the user chose from those results, at once and without asking about columns',
				'never export images the user did not choose, and never guess IDs',
				'when more are chosen, say so and ask the user to narrow the choice, without splitting it into several exports',
				'full Media Library CSV export is available in Media Library Tools Pro',
				'Media Library Tools Pro: ' . tsmlt()->pro_version_link(),
				'Never imitate a whole-library export with this tool',
				'never say the free version can export the whole library',
				'always all of them unless the user names specific columns: leave columns out, and never ask which columns to include',
			] as $phrase
		) {
			$this->assertStringContainsString( $phrase, $description );
		}
		foreach ( [ 'ID, slug, url, title, alt_text, caption, description, post_parent', 'menu_order and groups' ] as $fields ) {
			$this->assertStringContainsString( $fields, $description, 'Every standard field is named.' );
		}
	}

	/**
	 * The input schema tells the client to leave columns out (all standard
	 * columns) and that IDs come from the user.
	 */
	public function test_schema_defaults_to_every_column() {
		$schema = wp_get_ability( self::NAME )->get_input_schema();
		$this->assertStringContainsString( 'Leave out to export all standard columns (the default; do not ask the user)', $schema['properties']['columns']['description'] );
		$this->assertStringContainsString( 'IDs of the media the user chose', $schema['properties']['ids']['description'] );
		$this->assertArrayNotHasKey( 'columns', array_flip( $schema['required'] ?? [] ) );
	}

	/**
	 * Given only IDs, an export has every standard column, in the order Pro
	 * uses (MediaCsv::COLUMNS).
	 */
	public function test_ids_alone_export_every_standard_column() {
		$id     = $this->attachment();
		$result = self::call( [ 'ids' => [ $id ] ] );
		$this->assertSame( MediaCsv::COLUMNS, $result['columns'] );
		$this->assertSame( [ 'ID', 'slug', 'url', 'title', 'alt_text', 'caption', 'description', 'post_parent', 'menu_order', 'groups' ], self::parse( $result['csv'] )[0] );
	}

	/**
	 * Only users with manage_options: refused through the permission
	 * callback and by a direct call, logged out or not.
	 */
	public function test_requires_manage_options() {
		$id = $this->attachment();
		foreach ( [ 'editor', 'author' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertSame( 'ability_invalid_permissions', self::call( [ 'ids' => [ $id ] ] )->get_error_code(), $role );
			$this->assertSame( AbilityGuard::ERROR_FORBIDDEN, ExportMediaCsv::instance()->execute( [ 'ids' => [ $id ] ] )->get_error_code(), $role );
		}
		wp_set_current_user( 0 );
		$this->assertSame( AbilityGuard::ERROR_UNAUTHENTICATED, ExportMediaCsv::instance()->execute( [ 'ids' => [ $id ] ] )->get_error_code() );
	}

	/**
	 * Closed, bounded input, through the schema and by a direct call.
	 */
	public function test_input_is_closed_and_bounded() {
		$id      = $this->attachment();
		$invalid = [
			[],
			[ 'ids' => [] ],
			[ 'ids' => range( 1, ExportMediaCsv::MAX_IDS + 1 ) ],
			[ 'ids' => [ $id, $id ] ],
			[ 'ids' => [ 0 ] ],
			[
				'ids' => [ $id ],
				'x'   => 1,
			],
			[
				'ids'     => [ $id ],
				'columns' => [],
			],
			[
				'ids'     => [ $id ],
				'columns' => [ 'title', 'title' ],
			],
			[
				'ids'     => [ $id ],
				'columns' => [ 'rename_to' ],
			],
			[
				'ids'     => [ $id ],
				'columns' => [ 'custom_meta:_x' ],
			],
		];
		foreach ( $invalid as $input ) {
			$this->assertSame( 'ability_invalid_input', self::call( $input )->get_error_code(), wp_json_encode( $input ) );
			$this->assertSame( AbilityGuard::ERROR_INVALID_INPUT, ExportMediaCsv::instance()->execute( $input )->get_error_code(), wp_json_encode( $input ) );
		}
		foreach ( [ [ 'ids' => [ (string) $id ] ], null ] as $input ) {
			$this->assertInstanceOf( WP_Error::class, self::call( $input ), wp_json_encode( $input ) );
			$this->assertSame( AbilityGuard::ERROR_INVALID_INPUT, ExportMediaCsv::instance()->execute( $input )->get_error_code(), wp_json_encode( $input ) );
		}
		$this->assertSame( 1, self::call( [ 'ids' => range( $id, $id + ExportMediaCsv::MAX_IDS - 1 ) ] )['rows'], 'The maximum is accepted.' );
	}

	/**
	 * Several IDs: rows in the order given, every column with its exact
	 * value, and the header the importers read.
	 */
	public function test_exports_selected_ids_in_order() {
		$parent = self::factory()->post->create();
		$b      = wp_insert_term( 'Beta', Fns::CATEGORY )['term_id'];
		$a      = wp_insert_term( 'Alpha', Fns::CATEGORY )['term_id'];
		$first  = $this->attachment(
			[
				'post_title'   => 'First',
				'post_excerpt' => 'Cap',
				'post_content' => 'Desc',
				'post_parent'  => $parent,
				'menu_order'   => 3,
			]
		);
		$second = $this->attachment( [ 'post_title' => 'Second' ] );
		update_post_meta( $first, '_wp_attachment_image_alt', 'Alt' );
		wp_set_object_terms( $first, [ $b, $a ], Fns::CATEGORY );

		$result = self::call( [ 'ids' => [ $second, $first ] ] );
		$rows   = self::parse( $result['csv'] );

		$this->assertSame( MediaCsv::COLUMNS, $result['columns'] );
		$this->assertSame( [ 'ID', 'slug', 'url', 'title', 'alt_text', 'caption', 'description', 'post_parent', 'menu_order', 'groups' ], $rows[0] );
		$this->assertSame( [ $second, $first ], $result['exported_ids'] );
		$this->assertSame( 2, $result['rows'] );
		$this->assertSame( [], $result['skipped'] );
		$this->assertSame( (string) $second, $rows[1][0] );
		$this->assertSame(
			[ (string) $first, get_post( $first )->post_name, wp_get_attachment_url( $first ), 'First', 'Alt', 'Cap', 'Desc', (string) $parent, '3', 'Alpha, Beta' ],
			$rows[2]
		);
		$this->assertCount( 3, $rows );
	}

	/**
	 * Missing, non-media, trashed, private and unreadable IDs are skipped
	 * and reported alike, in order; the others are exported. With nothing
	 * to export, the CSV is the header only.
	 */
	public function test_skipped_ids_are_reported_alike() {
		$ok            = $this->attachment();
		$post          = self::factory()->post->create();
		$trashed       = $this->attachment();
		$private       = $this->attachment( [ 'post_status' => 'private' ] );
		$hidden        = $this->attachment();
		$this->no_read = [ $hidden ];
		wp_trash_post( $trashed );

		$result = self::call( [ 'ids' => [ 999999, $ok, $post, $trashed, $private, $hidden ] ] );

		$this->assertSame( [ $ok ], $result['exported_ids'] );
		$this->assertSame(
			array_map(
				static function ( $id ) {
					return [
						'id'     => $id,
						'reason' => ExportMediaCsv::REASON_NOT_AVAILABLE,
					];
				},
				[ 999999, $post, $trashed, $private, $hidden ]
			),
			$result['skipped']
		);

		$none = self::call( [ 'ids' => [ 999999 ] ] );
		$this->assertSame( 0, $none['rows'] );
		$this->assertSame( implode( ',', MediaCsv::COLUMNS ) . "\n", $none['csv'] );
	}

	/**
	 * Chosen columns, in the order given.
	 */
	public function test_column_selection() {
		$id = $this->attachment( [ 'post_title' => 'T' ] );

		$result = self::call(
			[
				'ids'     => [ $id ],
				'columns' => [ 'title', 'ID' ],
			]
		);

		$this->assertSame( [ 'title', 'ID' ], $result['columns'] );
		$this->assertSame( "title,ID\nT,{$id}\n", $result['csv'] );
	}

	/**
	 * The parent is exported only when the user may read it; otherwise, and
	 * for a parent that is gone, 0.
	 */
	public function test_parent_needs_read_access() {
		$readable = self::factory()->post->create();
		$hidden   = self::factory()->post->create();
		$gone     = self::factory()->post->create();
		$a        = $this->attachment( [ 'post_parent' => $readable ] );
		$b        = $this->attachment( [ 'post_parent' => $hidden ] );
		$c        = $this->attachment( [ 'post_parent' => $gone ] );
		wp_delete_post( $gone, true );
		$this->no_read = [ $hidden ];

		$rows = self::parse(
			self::call(
				[
					'ids'     => [ $a, $b, $c ],
					'columns' => [ 'post_parent' ],
				]
			)['csv']
		);

		$this->assertSame( [ [ 'post_parent' ], [ (string) $readable ], [ '0' ], [ '0' ] ], $rows );
	}

	/**
	 * Encoding: commas, quotes and line breaks survive; formula-like text
	 * gets one apostrophe, also after apostrophes and in Group names;
	 * numbers do not; empty stays empty; multibyte text is kept and invalid
	 * UTF-8 removed.
	 */
	public function test_csv_encoding_and_formula_protection() {
		$group = wp_insert_term( '=Danger, "quoted"', Fns::CATEGORY )['term_id'];
		$id    = $this->attachment(
			[
				'post_title'   => '=SUM(1)',
				'post_excerpt' => "Line 1\nLine \"2\", with comma",
				'post_content' => '',
				'menu_order'   => -5,
			]
		);
		update_post_meta( $id, '_wp_attachment_image_alt', "'+quoted formula" );
		wp_set_object_terms( $id, [ $group ], Fns::CATEGORY );

		$result = self::call(
			[
				'ids'     => [ $id ],
				'columns' => [ 'title', 'alt_text', 'caption', 'description', 'menu_order', 'groups' ],
			]
		);
		$row    = self::parse( $result['csv'] )[1];

		$this->assertSame( [ "'=SUM(1)", "''+quoted formula", "Line 1\nLine \"2\", with comma", '', '-5', "'=Danger, \"quoted\"" ], $row );
		foreach ( [ '=x', '+x', '-x', '@x', "\tx", "\rx", "''=x" ] as $value ) {
			$this->assertSame( "'" . $value, MediaCsv::protect( $value ), wp_json_encode( $value ) );
		}
		foreach ( [ 'a=b', "'Twas", '', 'Été' ] as $value ) {
			$this->assertSame( $value, MediaCsv::protect( $value ), wp_json_encode( $value ) );
		}
		$this->assertSame( "a,b\nÉté,c\n", MediaCsv::to_csv( [ [ 'a', 'b' ], [ 'Été', 'c' ] ] ) );
		$invalid = MediaCsv::to_csv( [ [ "ok\xC3\x28", 'next' ] ] );
		$this->assertSame( 1, preg_match( '//u', $invalid ), 'Invalid UTF-8 never reaches the CSV.' );
	}

	/**
	 * Only the allowlisted columns: no file path, attachment metadata, EXIF
	 * or custom field ever appears.
	 */
	public function test_no_data_beyond_the_allowlist() {
		$id = $this->attachment();
		update_post_meta( $id, 'secret_api_key', 'TOP-SECRET-VALUE' );
		update_post_meta(
			$id,
			'_wp_attachment_metadata',
			[
				'file'       => '2026/11/private-path.jpg',
				'image_meta' => [ 'camera' => 'SecretCam' ],
			]
		);

		$csv = self::call( [ 'ids' => [ $id ] ] )['csv'];

		foreach ( [ 'TOP-SECRET-VALUE', 'secret_api_key', 'SecretCam', 'private-path', ABSPATH, wp_upload_dir()['basedir'] ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $csv, $needle );
		}
	}

	/**
	 * The number of queries does not grow with the number of IDs.
	 */
	public function test_queries_do_not_grow_with_the_ids() {
		global $wpdb;
		$group = wp_insert_term( 'G', Fns::CATEGORY )['term_id'];
		$ids   = [];
		for ( $i = 0; $i < 12; $i++ ) {
			$ids[] = $this->attachment( [ 'post_parent' => self::factory()->post->create() ] );
			wp_set_object_terms( end( $ids ), [ $group ], Fns::CATEGORY );
		}
		$counts = [];
		foreach ( [ 2, 12 ] as $n ) {
			wp_cache_flush();
			$before = $wpdb->num_queries;
			self::call( [ 'ids' => array_slice( $ids, 0, $n ) ] );
			$counts[ $n ] = $wpdb->num_queries - $before;
		}
		$this->assertSame( $counts[2], $counts[12], wp_json_encode( $counts ) );
	}

	/**
	 * Annotations and closed schemas as registered.
	 */
	public function test_annotations_and_schemas() {
		$ability = wp_get_ability( self::NAME );
		$this->assertSame(
			[
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			array_intersect_key( $ability->get_meta()['annotations'], array_flip( [ 'readonly', 'destructive', 'idempotent' ] ) )
		);
		$this->assertFalse( $ability->get_input_schema()['additionalProperties'] );
		$this->assertFalse( $ability->get_output_schema()['additionalProperties'] );
		$this->assertFalse( $ability->get_meta()['mcp']['public'] );
		$this->assertSame( MediaCsv::COLUMNS, $ability->get_input_schema()['properties']['columns']['items']['enum'] );
	}

	/**
	 * Send one JSON-RPC message to a route.
	 *
	 * @param string $route  Route.
	 * @param string $method Method.
	 * @param array  $params Params.
	 *
	 * @return array
	 */
	private function send( string $route, string $method, array $params = [] ): array {
		$request = new WP_REST_Request( 'POST', $route );
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
					'id'      => ++$this->request_id,
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
	 * The dedicated endpoint lists and runs it; the default gateway neither
	 * lists, describes nor runs it.
	 */
	public function test_dedicated_endpoint_only() {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run these tests.' );
		}
		$id = $this->attachment( [ 'post_title' => 'Via MCP' ] );
		$this->send(
			self::SCOPED_ROUTE,
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
		$this->send( self::SCOPED_ROUTE, 'notifications/initialized' );
		$tools = array_column( $this->send( self::SCOPED_ROUTE, 'tools/list' )['result']['tools'], null, 'name' );
		$this->assertArrayHasKey( 'tsmlt-export-media-csv', $tools );
		$this->assertTrue( $tools['tsmlt-export-media-csv']['annotations']['readOnlyHint'] );
		$run = $this->send(
			self::SCOPED_ROUTE,
			'tools/call',
			[
				'name'      => 'tsmlt-export-media-csv',
				'arguments' => [ 'ids' => [ $id ] ],
			]
		)['result'];
		$this->assertFalse( $run['isError'], wp_json_encode( $run ) );
		$this->assertStringContainsString( 'Via MCP', $run['structuredContent']['csv'] );

		$this->gw_connect_as( 'administrator' );
		$listed = array_column( $this->gw_call( 'mcp-adapter-discover-abilities', [] )['structuredContent']['abilities'], 'name' );
		$this->assertNotContains( self::NAME, $listed );
		$this->assertTrue( $this->gw_call( 'mcp-adapter-get-ability-info', [ 'ability_name' => self::NAME ] )['isError'] );
		$gateway_run = $this->gw_call( 'mcp-adapter-execute-ability', self::gw_execute_args( self::NAME, [ 'ids' => [ $id ] ] ) );
		$this->assertTrue( $gateway_run['isError'] );
		$this->assertStringNotContainsString( 'Via MCP', wp_json_encode( $gateway_run ) );
	}
}
