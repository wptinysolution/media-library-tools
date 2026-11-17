<?php
/**
 * `tsmlt/list-media-usage`: pages of used, unused or unknown media after a
 * completed Used Where scan, with UsageClassifier's lists and the per-item
 * rule of tsmlt/get-media-usage; read-only; dedicated endpoint only.
 *
 * Usage records and scan statuses are stored directly in the scanner's
 * formats (no scan is run). Every post and attachment is a generated
 * fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\McpServerRegistration;
use TinySolutions\mlt\Abilities\Media\ListMediaUsage;
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Modules\UsedWhere\UsageClassifier;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * ListMediaUsageAbilityTest
 */
class ListMediaUsageAbilityTest extends WP_UnitTestCase {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	const NAME = 'tsmlt/list-media-usage';

	/**
	 * Dedicated MCP route.
	 *
	 * @var string
	 */
	const SCOPED_ROUTE = '/media-library-tools/mcp';

	/**
	 * Default gateway route.
	 *
	 * @var string
	 */
	const DEFAULT_ROUTE = '/mcp/mcp-adapter-default-server';

	/**
	 * IDs for which read_post is denied.
	 *
	 * @var int[]
	 */
	private $denied = [];

	/**
	 * Statements other than reads sent while observing.
	 *
	 * @var string[]
	 */
	private $writes = [];

	/**
	 * Whether the scan should be changed during the next records read.
	 *
	 * @var bool
	 */
	private $change_scan = false;

	/**
	 * MCP session per route.
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
	 * Administrator; no scan status.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( self::NAME ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( UsageScanStatus::OPTION );
		$this->denied      = [];
		$this->writes      = [];
		$this->change_scan = false;
		$this->sessions    = [];
		add_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10, 4 );
	}

	/**
	 * Remove filters.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10 );
		remove_filter( 'query', [ $this, 'record_query' ] );
		remove_filter( 'get_post_metadata', [ $this, 'change_scan_during_read' ], 10 );
		parent::tear_down();
	}

	/**
	 * Deny read_post for the IDs in $denied.
	 *
	 * @param string[] $caps    Primitive caps.
	 * @param string   $cap     Meta cap.
	 * @param int      $user_id User ID.
	 * @param array    $args    Arguments.
	 *
	 * @return string[]
	 */
	public function deny_read( $caps, $cap, $user_id, $args ) {
		if ( 'read_post' === $cap && in_array( (int) ( $args[0] ?? 0 ), $this->denied, true ) ) {
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
	 * Start another scan the first time usage records are read.
	 *
	 * @param mixed  $value    Short-circuit value.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 *
	 * @return mixed
	 */
	public function change_scan_during_read( $value, $post_id, $meta_key ) {
		if ( $this->change_scan && UsedWhereScanner::META_KEY === $meta_key ) {
			$this->change_scan = false;
			$status            = get_option( UsageScanStatus::OPTION );
			$status['scan_id'] = wp_generate_uuid4();
			update_option( UsageScanStatus::OPTION, $status, false );
		}
		return $value;
	}

	/**
	 * Store a complete scan status (verified unless gaps are given).
	 *
	 * @param array $gaps    Coverage gaps.
	 * @param array $changes Other fields.
	 */
	private static function scan( array $gaps = [], array $changes = [] ) {
		update_option(
			UsageScanStatus::OPTION,
			array_merge(
				[
					'state'            => 'complete',
					'complete'         => true,
					'processed'        => 10,
					'total'            => 10,
					'scan_id'          => wp_generate_uuid4(),
					'started_at'       => '2099-01-01 00:00:00',
					'timestamp'        => '2099-01-01 01:00:00',
					'coverage_version' => UsedWhereScanner::COVERAGE_VERSION,
					'coverage_gaps'    => $gaps,
				],
				$changes
			),
			false
		);
	}

	/**
	 * An image attachment.
	 *
	 * @param string $name      File name.
	 * @param string $post_date Upload date.
	 *
	 * @return int
	 */
	private function image( string $name, string $post_date = '2026-01-01 00:00:00' ): int {
		return (int) self::factory()->attachment->create(
			[
				'file'           => '2026/01/' . $name,
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Title ' . $name,
				'post_date'      => $post_date,
			]
		);
	}

	/**
	 * Store usage records in the scanner's format.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $records       [ post_id, usage_type ] pairs.
	 */
	private static function record( int $attachment_id, array $records ) {
		$rows = [];
		foreach ( $records as $record ) {
			$rows[] = [
				'post_id'    => $record[0],
				'post_title' => 'Cached private title',
				'post_type'  => 0 === $record[0] ? 'site_settings' : 'post',
				'usage_type' => $record[1],
			];
		}
		update_post_meta( $attachment_id, UsedWhereScanner::META_KEY, $rows );
	}

	/**
	 * Run the ability.
	 *
	 * @param mixed $input Input.
	 *
	 * @return mixed
	 */
	private static function call( $input ) {
		return wp_get_ability( self::NAME )->execute( $input );
	}

	/**
	 * Fixtures: used (one post, and two posts plus a site-wide usage), a
	 * stale record (deleted post only), unused, and one uploaded after the
	 * scan started.
	 *
	 * @return array<string, int>
	 */
	private function library(): array {
		$post_a = self::factory()->post->create();
		$post_b = self::factory()->post->create();
		$ids    = [
			'used_one'   => $this->image( 'used-one.jpg' ),
			'used_many'  => $this->image( 'used-many.jpg' ),
			'stale'      => $this->image( 'stale.jpg' ),
			'unused_one' => $this->image( 'unused-one.jpg' ),
			'unused_two' => $this->image( 'unused-two.jpg' ),
			'late'       => $this->image( 'late.jpg', '2100-01-01 00:00:00' ),
		];
		self::record( $ids['used_one'], [ [ $post_a, 'featured' ] ] );
		self::record( $ids['used_many'], [ [ $post_a, 'content' ], [ $post_b, 'content' ], [ $post_b, 'gallery' ], [ 0, 'site_logo' ] ] );
		self::record( $ids['stale'], [ [ 999999, 'content' ] ] );
		return $ids;
	}

	/**
	 * Used, unused and unknown each list their media with the safe fields;
	 * a stored record for a post that no longer exists is not used (left
	 * out of the used list and counted as excluded).
	 */
	public function test_three_statuses() {
		self::scan();
		$ids = $this->library();

		$used = self::call( [ 'status' => 'used' ] );
		$this->assertSame( [ $ids['used_one'], $ids['used_many'] ], wp_list_pluck( $used['items'], 'id' ) );
		$this->assertSame( 1, $used['excluded'], 'The stale record is not a usage.' );
		$this->assertSame( 3, $used['total'], 'Total as the Used Where page counts the list.' );
		$many = $used['items'][1];
		$this->assertSame( 'used-many.jpg', $many['filename'] );
		$this->assertSame( 'Title used-many.jpg', $many['title'] );
		$this->assertSame( 'image/jpeg', $many['mime_type'] );
		$this->assertSame( 'used', $many['usage_status'] );
		$this->assertEquals(
			(object) [
				'content'   => 2,
				'gallery'   => 1,
				'site_logo' => 1,
			],
			$many['usage_types']
		);
		$this->assertSame( 2, $many['used_in_posts'] );

		$unused = self::call( [ 'status' => 'unused' ] );
		$this->assertSame( [ $ids['unused_one'], $ids['unused_two'] ], wp_list_pluck( $unused['items'], 'id' ) );
		$this->assertArrayNotHasKey( 'usage_types', $unused['items'][0] );
		$this->assertTrue( $unused['scan']['verified'] );

		$unknown = self::call( [ 'status' => 'unknown' ] );
		$this->assertSame( [ $ids['late'] ], wp_list_pluck( $unknown['items'], 'id' ) );
		$this->assertContains( 'not_covered', $unknown['items'][0]['unknown_reasons'] );

		$schema = wp_get_ability( self::NAME )->get_output_schema();
		foreach ( [ $used, $unused, $unknown ] as $result ) {
			$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), $schema, 'result' ) );
		}
	}

	/**
	 * A complete scan whose coverage is not verified lists nothing as
	 * unused: media without a recorded usage is unknown.
	 */
	public function test_unverified_scan_never_lists_unused() {
		self::scan( [ 'woocommerce_inactive' ] );
		$ids = $this->library();

		$unused = self::call( [ 'status' => 'unused' ] );
		$this->assertSame( [], $unused['items'] );
		$this->assertSame( 0, $unused['total'] );
		$this->assertFalse( $unused['scan']['verified'] );
		$this->assertSame( [ 'woocommerce_inactive' ], $unused['scan']['gaps'] );

		$unknown = self::call(
			[
				'status'   => 'unknown',
				'per_page' => 100,
			]
		);
		$listed  = wp_list_pluck( $unknown['items'], 'id' );
		foreach ( [ 'unused_one', 'unused_two', 'late' ] as $key ) {
			$this->assertContains( $ids[ $key ], $listed );
		}
		$this->assertContains( 'woocommerce_inactive', $unknown['items'][0]['unknown_reasons'] );
	}

	/**
	 * Pages follow attachment IDs: no overlap, every item once, an empty
	 * page past the end, total and total_pages consistent.
	 */
	public function test_pagination() {
		self::scan();
		$created = [];
		for ( $i = 0; $i < 5; $i++ ) {
			$created[] = $this->image( 'page-' . $i . '.jpg' );
		}
		$seen = [];
		for ( $page = 1; $page <= 3; $page++ ) {
			$result = self::call(
				[
					'status'   => 'unused',
					'page'     => $page,
					'per_page' => 2,
				]
			);
			$this->assertSame( 5, $result['total'] );
			$this->assertSame( 3, $result['total_pages'] );
			$this->assertSame( $page, $result['page'] );
			$this->assertSame( 2, $result['per_page'] );
			$this->assertLessThanOrEqual( 2, count( $result['items'] ) );
			$seen = array_merge( $seen, wp_list_pluck( $result['items'], 'id' ) );
		}
		$this->assertSame( $created, $seen, 'Each once, in ID order.' );
		$this->assertSame(
			[],
			self::call(
				[
					'status'   => 'unused',
					'page'     => 4,
					'per_page' => 2,
				]
			)['items']
		);
		$this->assertSame( 20, self::call( [ 'status' => 'unused' ] )['per_page'], 'Default page size.' );
	}

	/**
	 * Invalid input is refused.
	 */
	public function test_invalid_input() {
		self::scan();
		foreach (
			[
				'no status'       => [],
				'unknown status'  => [ 'status' => 'trashed' ],
				'page 0'          => [
					'status' => 'used',
					'page'   => 0,
				],
				'per_page 101'    => [
					'status'   => 'used',
					'per_page' => 101,
				],
				'per_page string' => [
					'status'   => 'used',
					'per_page' => '10',
				],
				'extra key'       => [
					'status' => 'used',
					'search' => 'x',
				],
			] as $label => $input
		) {
			$this->assertWPError( self::call( $input ), $label );
			$this->assertWPError( ListMediaUsage::instance()->execute( $input ), $label );
		}
	}

	/**
	 * Without a completed scan (none, running, cancelled) the list is
	 * refused with how to get one; an empty list is an empty page.
	 */
	public function test_requires_a_completed_scan() {
		$this->image( 'any.jpg' );
		foreach (
			[
				'no scan'   => null,
				'running'   => [
					'state'    => 'running',
					'complete' => false,
				],
				'cancelled' => [
					'state'    => 'cancelled',
					'complete' => false,
				],
			] as $label => $changes
		) {
			if ( null === $changes ) {
				delete_option( UsageScanStatus::OPTION );
			} else {
				self::scan( [], $changes );
			}
			$result = self::call( [ 'status' => 'unused' ] );
			$this->assertWPError( $result, $label );
			$this->assertSame( ListMediaUsage::ERROR_SCAN_REQUIRED, $result->get_error_code() );
			$this->assertStringContainsString( 'tsmlt/start-usage-scan', $result->get_error_message() );
		}

		self::scan();
		$empty = self::call( [ 'status' => 'used' ] );
		$this->assertSame( [], $empty['items'] );
		$this->assertSame( 0, $empty['total'] );
		$this->assertSame( 0, $empty['total_pages'] );
	}

	/**
	 * Media the user cannot read is left out and counted in hidden.
	 */
	public function test_unreadable_media_is_hidden() {
		self::scan();
		$ids          = $this->library();
		$this->denied = [ $ids['unused_one'] ];

		$result = self::call( [ 'status' => 'unused' ] );
		$this->assertSame( [ $ids['unused_two'] ], wp_list_pluck( $result['items'], 'id' ) );
		$this->assertSame( 1, $result['hidden'] );
	}

	/**
	 * Only administrators may list.
	 */
	public function test_requires_manage_options() {
		self::scan();
		foreach ( [ 'editor', 'author', 'subscriber' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertFalse( wp_get_ability( self::NAME )->check_permissions( [ 'status' => 'used' ] ), $role );
			$this->assertWPError( ListMediaUsage::instance()->execute( [ 'status' => 'used' ] ), $role );
		}
		wp_set_current_user( 0 );
		$this->assertWPError( ListMediaUsage::instance()->execute( [ 'status' => 'used' ] ) );
	}

	/**
	 * Listing writes nothing, schedules nothing and keeps the stale record
	 * the Used Where page would clean up; classification counts are the
	 * same before and after; no paths or cached post titles are returned.
	 */
	public function test_read_only_and_no_sensitive_data() {
		self::scan();
		$ids    = $this->library();
		$stale  = get_post_meta( $ids['stale'], UsedWhereScanner::META_KEY, true );
		$status = get_option( UsageScanStatus::OPTION );
		$cron   = _get_cron_array();
		$counts = UsageClassifier::counts( '', UsageClassifier::context() );
		add_filter( 'query', [ $this, 'record_query' ] );

		$results = [];
		foreach ( UsageClassifier::TABS as $tab ) {
			$results[] = self::call( [ 'status' => $tab ] );
		}

		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertSame( [], $this->writes );
		$this->assertSame( $stale, get_post_meta( $ids['stale'], UsedWhereScanner::META_KEY, true ), 'The stale record is kept.' );
		$this->assertSame( $status, get_option( UsageScanStatus::OPTION ) );
		$this->assertSame( $cron, _get_cron_array() );
		$this->assertSame( $counts, UsageClassifier::counts( '', UsageClassifier::context() ) );

		$json = wp_json_encode( $results );
		$this->assertStringNotContainsString( wp_upload_dir()['basedir'], $json );
		$this->assertStringNotContainsString( '2026\/01\/', $json, 'No stored file paths.' );
		$this->assertStringNotContainsString( 'Cached private title', $json );
		$this->assertStringNotContainsString( 'post_title', $json );
	}

	/**
	 * A scan that changes while the page is read is refused.
	 */
	public function test_scan_changing_during_the_read_is_refused() {
		self::scan();
		$this->library();
		$this->change_scan = true;
		add_filter( 'get_post_metadata', [ $this, 'change_scan_during_read' ], 10, 3 );

		$result = self::call( [ 'status' => 'used' ] );

		$this->assertWPError( $result );
		$this->assertSame( ListMediaUsage::ERROR_SCAN_CHANGED, $result->get_error_code() );
	}

	/**
	 * Listed on the dedicated endpoint only; works over MCP there and is
	 * refused on the default gateway.
	 */
	public function test_dedicated_endpoint_only() {
		$this->assertFalse( wp_get_ability( self::NAME )->get_meta()['mcp']['public'] );
		$this->assertTrue( wp_get_ability( self::NAME )->get_meta()['annotations']['readonly'] );
		$this->assertContains( self::NAME, McpServerRegistration::instance()->get_tool_names() );

		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run the transport part.' );
		}
		self::scan();
		$ids = $this->library();
		$this->connect( self::SCOPED_ROUTE );
		$tools = array_column( $this->send( self::SCOPED_ROUTE, 'tools/list' )['result']['tools'], null, 'name' );
		$this->assertArrayHasKey( 'tsmlt-list-media-usage', $tools );
		$this->assertFalse( $tools['tsmlt-list-media-usage']['inputSchema']['additionalProperties'] );
		$call = $this->send(
			self::SCOPED_ROUTE,
			'tools/call',
			[
				'name'      => 'tsmlt-list-media-usage',
				'arguments' => [ 'status' => 'used' ],
			]
		)['result'];
		$this->assertFalse( $call['isError'], wp_json_encode( $call ) );
		$this->assertSame( [ $ids['used_one'], $ids['used_many'] ], array_column( $call['structuredContent']['items'], 'id' ) );

		$this->connect( self::DEFAULT_ROUTE );
		$info = $this->send(
			self::DEFAULT_ROUTE,
			'tools/call',
			[
				'name'      => 'mcp-adapter-get-ability-info',
				'arguments' => [ 'ability_name' => self::NAME ],
			]
		)['result'];
		$this->assertTrue( $info['isError'], 'Not offered on the default gateway.' );
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
	 * Open a session on a route.
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
