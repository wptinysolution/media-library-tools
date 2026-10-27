<?php
/**
 * Used Where page: Used / Unused / Unknown classification and the Unknown
 * tab's diagnostics, through the AJAX endpoint the page calls
 * (tsmlt_used_where_get_results).
 *
 * Scan states are stored directly in the scanner's status option; usage
 * records are written in the scanner's format. Every attachment, post and
 * term is a generated fixture. WooCommerce is not installed: its rows are
 * created directly.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereClassificationAjaxTest
 */
class UsedWhereClassificationAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * When the stored scans started.
	 */
	const SCAN_START = '2026-01-01 00:00:00';

	/**
	 * Administrator; no scan.
	 */
	public function set_up() {
		parent::set_up();
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( UsageScanStatus::OPTION );
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * Call a Used Where AJAX action.
	 *
	 * @param string $action Action without the wp_ajax_ prefix.
	 * @param array  $params Params.
	 *
	 * @return array Decoded response.
	 */
	private function ajax( string $action, array $params = [] ): array {
		$_POST['nonce']            = wp_create_nonce( Fns::NONCE_ID );
		$_POST['params']           = wp_json_encode( $params );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->_last_response      = '';
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}
		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, $this->_last_response );
		$this->assertTrue( $response['success'], $this->_last_response );
		return $response['data'];
	}

	/**
	 * Results of a tab.
	 *
	 * @param string $filter Tab.
	 * @param array  $extra  Search, limit, offset.
	 *
	 * @return array
	 */
	private function results( string $filter, array $extra = [] ): array {
		return $this->ajax( 'tsmlt_used_where_get_results', array_merge( [ 'filter' => $filter, 'limit' => 50, 'offset' => 0 ], $extra ) );
	}

	/**
	 * Attachment IDs in a response.
	 *
	 * @param array $data Response.
	 *
	 * @return int[]
	 */
	private static function ids( array $data ): array {
		$ids = array_map( 'intval', wp_list_pluck( $data['usages'], 'attachment_id' ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * An image uploaded at a given time.
	 *
	 * @param string $title Title.
	 * @param string $date  Upload time (site time).
	 * @param int    $parent Parent post.
	 *
	 * @return int
	 */
	private function image( string $title, string $date = '2025-06-01 00:00:00', int $parent = 0 ): int {
		return (int) self::factory()->attachment->create(
			[
				'post_title'     => $title,
				'post_mime_type' => 'image/jpeg',
				'file'           => 'cls/' . sanitize_title( $title ) . '.jpg',
				'post_date'      => $date,
				'post_date_gmt'  => $date,
				'post_parent'    => $parent,
			]
		);
	}

	/**
	 * Record a usage.
	 *
	 * @param int $id      Attachment.
	 * @param int $post_id Post.
	 */
	private static function used( int $id, int $post_id ) {
		update_post_meta( $id, UsedWhereScanner::META_KEY, [ [ 'post_id' => $post_id, 'post_title' => 'P', 'post_type' => 'post', 'usage_type' => 'featured' ] ] );
	}

	/**
	 * Store a completed scan with verified coverage (with overrides).
	 *
	 * @param array $overrides Changes.
	 */
	private static function verified_scan( array $overrides = [] ) {
		update_option(
			UsageScanStatus::OPTION,
			array_merge(
				[
					'state'              => 'complete',
					'complete'           => true,
					'processed'          => 10,
					'total'              => 10,
					'scan_id'            => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
					'started_at'         => self::SCAN_START,
					'last_tick_at'       => self::SCAN_START,
					'timestamp'          => '2026-01-01 00:05:00',
					'coverage_version'   => UsedWhereScanner::COVERAGE_VERSION,
					'coverage_gaps'      => [],
					'scanned_post_types' => UsedWhereScanner::instance()->scannable_post_types(),
				],
				$overrides
			),
			false
		);
	}

	/**
	 * Store a completed scan made before coverage was recorded.
	 */
	private static function legacy_scan() {
		update_option(
			UsageScanStatus::OPTION,
			[
				'state'      => 'complete',
				'complete'   => true,
				'processed'  => 77,
				'total'      => 77,
				'scan_id'    => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
				'started_at' => self::SCAN_START,
				'timestamp'  => '2026-01-01 00:05:00',
			],
			false
		);
	}

	/**
	 * Reason codes of an Unknown item.
	 *
	 * @param array $item Item.
	 *
	 * @return string[]
	 */
	private static function codes( array $item ): array {
		return wp_list_pluck( $item['unknown']['reasons'], 'code' );
	}

	/**
	 * The Unknown item for an attachment.
	 *
	 * @param array $data Response.
	 * @param int   $id   Attachment.
	 *
	 * @return array
	 */
	private function unknown_item( array $data, int $id ): array {
		foreach ( $data['usages'] as $item ) {
			if ( (int) $item['attachment_id'] === $id ) {
				$this->assertSame( 'unknown', $item['classification'] );
				return $item;
			}
		}
		$this->fail( "Attachment $id is not in the Unknown tab." );
	}

	// ---------------------------------------------------------------------
	// Classification
	// ---------------------------------------------------------------------

	/**
	 * Verified scan: a recorded usage is Used, no usage is Unused, an upload
	 * after the scan started is Unknown; the three tabs do not overlap and
	 * their counts match the lists.
	 */
	public function test_verified_scan_classification() {
		$post   = (int) self::factory()->post->create();
		$used   = $this->image( 'Used image' );
		$unused = $this->image( 'Unused image' );
		$late   = $this->image( 'Late image', '2026-02-01 00:00:00' );
		self::used( $used, $post );
		self::verified_scan();

		$used_tab    = $this->results( 'used' );
		$unused_tab  = $this->results( 'unused' );
		$unknown_tab = $this->results( 'unknown' );

		$this->assertSame( [ $used ], self::ids( $used_tab ) );
		$this->assertSame( [ $unused ], self::ids( $unused_tab ) );
		$this->assertSame( [ $late ], self::ids( $unknown_tab ) );
		$this->assertSame( [ 'used' => 1, 'unused' => 1, 'unknown' => 1 ], $unknown_tab['counts'] );
		$this->assertSame( 1, $unused_tab['total'] );
		$this->assertTrue( $unknown_tab['scan']['unused_verified'] );

		$late_item = $this->unknown_item( $unknown_tab, $late );
		$this->assertSame( [ 'not_covered' ], self::codes( $late_item ) );
		$this->assertTrue( $late_item['unknown']['uploaded_after_scan_start'] );
		$this->assertStringContainsString( 'Run a new scan', $late_item['unknown']['reasons'][0]['action'] );
	}

	/**
	 * A scan from before coverage was recorded: nothing is Unused; every
	 * image without a usage is Unknown with the coverage reason; recorded
	 * usages stay Used.
	 */
	public function test_legacy_scan_never_reports_unused() {
		$post   = (int) self::factory()->post->create();
		$used   = $this->image( 'Used legacy' );
		$plain  = $this->image( 'Plain legacy' );
		self::used( $used, $post );
		self::legacy_scan();

		$unused  = $this->results( 'unused' );
		$unknown = $this->results( 'unknown' );

		$this->assertSame( [], $unused['usages'] );
		$this->assertSame( 0, $unused['total'] );
		$this->assertSame( [ 'used' => 1, 'unused' => 0, 'unknown' => 1 ], $unknown['counts'] );
		$this->assertSame( [ $used ], self::ids( $this->results( 'used' ) ) );

		$item = $this->unknown_item( $unknown, $plain );
		$this->assertSame( [ 'coverage_not_recorded' ], self::codes( $item ) );
		$this->assertFalse( $unknown['scan']['coverage_recorded'] );
		$this->assertFalse( $unknown['scan']['unused_verified'] );
		$this->assertNull( $unknown['scan']['scanned_post_types'], 'Not recorded, not invented.' );
		$this->assertNull( $unknown['scan']['not_scanned_post_types'] );
		$this->assertContains( 'Which post types the scan checked was not recorded.', $unknown['scan']['missing'] );
		$this->assertStringNotContainsString( 'WooCommerce', wp_json_encode( $item ), 'No unrelated recommendation.' );
	}

	/**
	 * Unfinished scans and their reasons.
	 *
	 * @return array
	 */
	public function data_unfinished_scans(): array {
		return [
			'never scanned' => [ null, 'no_scan' ],
			'running'       => [ [ 'state' => 'running', 'complete' => false, 'processed' => 4, 'last_tick_at' => current_time( 'mysql' ) ], 'scan_in_progress' ],
			'queued'        => [ [ 'state' => 'queued', 'complete' => false, 'processed' => 0, 'last_tick_at' => '', 'started_at' => current_time( 'mysql' ) ], 'scan_in_progress' ],
			'stalled'       => [ [ 'state' => 'running', 'complete' => false, 'processed' => 4, 'last_tick_at' => '2000-01-01 00:00:00' ], 'scan_stalled' ],
			'cancelled'     => [ [ 'state' => 'cancelled', 'complete' => false, 'processed' => 4 ], 'scan_cancelled' ],
			'failed'        => [ [ 'state' => 'error', 'complete' => false, 'processed' => 4, 'last_error' => 'Boom' ], 'scan_failed' ],
			'old row'       => [ [ 'state' => null, 'complete' => false ], 'scan_incomplete' ],
		];
	}

	/**
	 * An unfinished scan leaves images without a usage Unknown, with the
	 * matching reason; nothing is Unused.
	 *
	 * @dataProvider data_unfinished_scans
	 *
	 * @param array|null $overrides Status changes (null: no scan).
	 * @param string     $code      Expected reason.
	 */
	public function test_unfinished_scans_are_unknown( $overrides, string $code ) {
		$plain = $this->image( 'Plain unfinished' );
		if ( null !== $overrides ) {
			self::verified_scan( $overrides );
			$row = array_filter(
				get_option( UsageScanStatus::OPTION ),
				static function ( $value ) {
					return null !== $value;
				}
			);
			update_option( UsageScanStatus::OPTION, $row, false );
		}

		$unknown = $this->results( 'unknown' );

		$this->assertSame( 0, $unknown['counts']['unused'] );
		$item = $this->unknown_item( $unknown, $plain );
		$this->assertSame( $code, self::codes( $item )[0] );
		foreach ( $item['unknown']['reasons'] as $reason ) {
			$this->assertNotSame( '', $reason['title'] );
			$this->assertNotSame( '', $reason['message'] );
			$this->assertNotSame( '', $reason['action'] );
		}
	}

	/**
	 * A failed scan's error is shown without server paths; a missing error
	 * message is reported as missing.
	 */
	public function test_failed_scan_error_is_safe_and_honest() {
		$plain = $this->image( 'Plain failed' );
		self::verified_scan( [ 'state' => 'error', 'complete' => false, 'last_error' => 'Allowed memory exhausted in ' . ABSPATH . 'wp-includes/class-wp-query.php on line 12' ] );

		$data = $this->results( 'unknown' );
		$item = $this->unknown_item( $data, $plain );
		$text = implode( ' ', $item['unknown']['reasons'][0]['evidence'] ) . ' ' . $data['scan']['scan_error'];
		$this->assertStringContainsString( 'Allowed memory exhausted', $text );
		$this->assertStringNotContainsString( rtrim( ABSPATH, '/' ), $text );
		$this->assertStringNotContainsString( 'wp-includes/class-wp-query.php', $text );

		self::verified_scan( [ 'state' => 'error', 'complete' => false, 'last_error' => '' ] );
		$data = $this->results( 'unknown' );
		$item = $this->unknown_item( $data, $plain );
		$this->assertSame( [ 'The error message was not recorded.' ], $item['unknown']['reasons'][0]['evidence'] );
		$this->assertContains( 'The scan error message was not recorded.', $data['scan']['missing'] );
	}

	// ---------------------------------------------------------------------
	// WooCommerce gaps
	// ---------------------------------------------------------------------

	/**
	 * WooCommerce inactive during the scan: images are Unknown with the
	 * WooCommerce reason, and the evidence differs per image (attached to a
	 * product, set as a category image, or no link found).
	 */
	public function test_woocommerce_inactive_reasons_are_specific() {
		$product   = (int) self::factory()->post->create( [ 'post_type' => 'product', 'post_title' => 'Shirt' ] );
		$attached  = $this->image( 'Attached to product', '2025-06-01 00:00:00', $product );
		$category  = $this->image( 'Category image' );
		$plain     = $this->image( 'Plain image' );
		$term      = wp_insert_term( 'Shirts', 'category' );
		update_term_meta( $term['term_id'], 'thumbnail_id', (string) $category );
		self::verified_scan( [ 'coverage_gaps' => [ 'woocommerce_inactive' ] ] );

		$data = $this->results( 'unknown' );

		$this->assertSame( 0, $data['counts']['unused'] );
		$this->assertSame( [ 'woocommerce_inactive' ], $data['scan']['coverage_gaps'] );
		$evidence = [];
		foreach ( [ $attached, $category, $plain ] as $id ) {
			$item = $this->unknown_item( $data, $id );
			$this->assertSame( [ 'woocommerce_inactive' ], self::codes( $item ) );
			$this->assertStringContainsString( 'Activate WooCommerce', $item['unknown']['reasons'][0]['action'] );
			$evidence[ $id ] = implode( ' ', $item['unknown']['reasons'][0]['evidence'] );
		}
		$this->assertStringContainsString( "product #$product", $evidence[ $attached ] );
		$this->assertStringContainsString( 'term #' . $term['term_id'], $evidence[ $category ] );
		$this->assertStringContainsString( 'No direct link', $evidence[ $plain ] );
		$this->assertContains( 'Product category images and rendered product pages were not checked.', $data['scan']['missing'] );
	}

	/**
	 * Products left out of the scan by the filter: the not-scanned type is
	 * named.
	 */
	public function test_woocommerce_types_not_scanned_names_the_type() {
		self::factory()->post->create( [ 'post_type' => 'product' ] );
		$plain = $this->image( 'Plain filtered' );
		self::verified_scan(
			[
				'coverage_gaps'      => [ 'woocommerce_types_not_scanned' ],
				'scanned_post_types' => [ 'post', 'page' ],
			]
		);

		$item = $this->unknown_item( $this->results( 'unknown' ), $plain );

		$this->assertSame( [ 'woocommerce_types_not_scanned' ], self::codes( $item ) );
		$this->assertSame( [ 'Not scanned: product' ], $item['unknown']['reasons'][0]['evidence'] );
	}

	/**
	 * Several reasons are all listed (old scan, and uploaded after it began).
	 */
	public function test_multiple_reasons() {
		$late = $this->image( 'Late legacy', '2026-03-01 00:00:00' );
		self::legacy_scan();

		$item = $this->unknown_item( $this->results( 'unknown' ), $late );

		$this->assertSame( [ 'coverage_not_recorded', 'not_covered' ], self::codes( $item ) );
	}

	/**
	 * A coverage gap does not move images with a recorded usage out of Used,
	 * and their usages stay listed.
	 */
	public function test_used_stays_used_with_gaps() {
		$post = (int) self::factory()->post->create( [ 'post_title' => 'Uses it' ] );
		$used = $this->image( 'Used with gap' );
		self::used( $used, $post );
		self::verified_scan( [ 'coverage_gaps' => [ 'woocommerce_inactive' ] ] );

		$data = $this->results( 'used' );

		$this->assertSame( [ $used ], self::ids( $data ) );
		$this->assertSame( 'used', $data['usages'][0]['classification'] );
		$this->assertSame( $post, (int) $data['usages'][0]['posts'][0]['post_id'] );
		$this->assertFalse( $data['scan']['coverage_verified'] );
		$this->assertSame( 0, $data['counts']['unknown'] );
	}

	// ---------------------------------------------------------------------
	// Search, pagination, counts
	// ---------------------------------------------------------------------

	/**
	 * Search and paging work in the Unknown tab and the counts follow the
	 * search.
	 */
	public function test_search_and_pagination_in_unknown() {
		foreach ( [ 'Alpha one', 'Alpha two', 'Alpha three', 'Beta one', 'Beta two' ] as $title ) {
			$this->image( $title );
		}
		self::legacy_scan();

		$alpha = $this->results( 'unknown', [ 'search' => 'Alpha' ] );
		$this->assertSame( 3, $alpha['total'] );
		$this->assertSame( 3, $alpha['counts']['unknown'] );

		$page1 = $this->results( 'unknown', [ 'limit' => 2, 'offset' => 0 ] );
		$page2 = $this->results( 'unknown', [ 'limit' => 2, 'offset' => 2 ] );
		$page3 = $this->results( 'unknown', [ 'limit' => 2, 'offset' => 4 ] );
		$this->assertSame( 5, $page1['total'] );
		$all = array_merge( self::ids( $page1 ), self::ids( $page2 ), self::ids( $page3 ) );
		$this->assertCount( 5, $all );
		$this->assertCount( 5, array_unique( $all ), 'No image on two pages.' );
	}

	/**
	 * The Unknown and Unused tabs never share an image, and Used + Unused +
	 * Unknown covers every non-trashed attachment.
	 */
	public function test_tabs_partition_the_library() {
		$post = (int) self::factory()->post->create();
		$ids  = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = $this->image( "Before $i" );
		}
		$ids[] = $this->image( 'After', '2026-05-01 00:00:00' );
		self::used( $ids[0], $post );
		$trashed = $this->image( 'Trashed one' );
		wp_trash_post( $trashed );
		self::verified_scan();

		$data   = $this->results( 'unknown' );
		$counts = $data['counts'];
		$this->assertSame( count( $ids ), $counts['used'] + $counts['unused'] + $counts['unknown'] );
		$this->assertSame( [], array_intersect( self::ids( $this->results( 'unused' ) ), self::ids( $data ) ) );
		$this->assertNotContains( $trashed, self::ids( $data ) );
	}

	/**
	 * An unknown tab name falls back to Used; reading the Unused or Unknown
	 * tab writes nothing.
	 */
	public function test_unknown_filter_and_no_writes() {
		$this->image( 'Plain' );
		self::legacy_scan();
		$this->assertSame( [], $this->results( 'bogus' )['usages'], 'Treated as Used (none recorded).' );

		$writes = [];
		$track  = static function () use ( &$writes ) {
			$writes[] = current_filter();
		};
		foreach ( [ 'update_option', 'add_option', 'delete_option', 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
			add_action( $hook, $track );
		}
		$this->results( 'unknown' );
		$this->results( 'unused' );
		foreach ( [ 'update_option', 'add_option', 'delete_option', 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
			remove_action( $hook, $track );
		}
		$this->assertSame( [], $writes );
	}

	// ---------------------------------------------------------------------
	// Existing behaviour
	// ---------------------------------------------------------------------

	/**
	 * Trash and restore from the page still work, and the counts follow.
	 */
	public function test_trash_and_restore_still_work() {
		$unused = $this->image( 'To trash' );
		self::verified_scan();
		$this->assertSame( 1, $this->results( 'unused' )['counts']['unused'] );

		$trashed = $this->ajax( 'tsmlt_used_where_trash', [ 'ids' => [ $unused ] ] );
		$this->assertSame( [ $unused ], array_map( 'intval', $trashed['trashed'] ) );
		$this->assertSame( 0, $this->results( 'unused' )['counts']['unused'] );
		$this->assertContains( $unused, array_map( 'intval', wp_list_pluck( $this->ajax( 'tsmlt_used_where_get_trashed', [ 'limit' => 10, 'offset' => 0 ] )['items'], 'attachment_id' ) ) );

		$restored = $this->ajax( 'tsmlt_used_where_untrash', [ 'ids' => [ $unused ] ] );
		$this->assertSame( [ $unused ], array_map( 'intval', $restored['untrashed'] ) );
		$this->assertSame( 1, $this->results( 'unused' )['counts']['unused'] );
	}

	/**
	 * tsmlt/get-media-usage agrees with the page: an upload after the
	 * verified scan started is unknown, an older one without usage unused.
	 */
	public function test_mcp_usage_agrees_with_the_page() {
		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'tsmlt/get-media-usage' ) : null;
		if ( ! $ability instanceof WP_Ability ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		$old  = $this->image( 'Old' );
		$late = $this->image( 'New', '2026-04-01 00:00:00' );
		self::verified_scan();

		$items = array_column( $ability->execute( [ 'ids' => [ $old, $late ] ] )['items'], 'used', 'id' );

		$this->assertSame( [ $old => false, $late => null ], $items );
		$this->assertSame( [ $old ], self::ids( $this->results( 'unused' ) ) );
		$this->assertSame( [ $late ], self::ids( $this->results( 'unknown' ) ) );
	}
}
