<?php
/**
 * WooCommerce coverage of the Used Where scan: product and variation rows
 * are scanned whether or not WooCommerce is active (so whether or not their
 * post types are registered, and although variations are never public), and
 * a scan that could not check every source (WooCommerce inactive, or the
 * types filtered out) is recorded as such: media without a recorded usage is
 * then unknown, never unused, the trash tools refuse, and the Used Where
 * page shows a warning.
 *
 * WooCommerce is not installed in the test environment: "active" registers
 * its post types and product category taxonomy the way WooCommerce does;
 * "inactive" leaves them unregistered while product rows exist. WP-Cron does
 * not run in tests; scan batches are run directly. Every post, product and
 * attachment is a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\TrashEligibility;
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereWooCoverageTest
 */
class UsedWhereWooCoverageTest extends WP_UnitTestCase {

	/**
	 * Notice shown when WooCommerce is inactive.
	 */
	const INACTIVE_NOTICE = 'Usage verification may be incomplete. WooCommerce is inactive, so some product and variation images may not be detected as used. These images must not be treated as unused or moved to Trash until their usage can be verified.';

	/**
	 * Post IDs whose read_post is denied.
	 *
	 * @var int[]
	 */
	private $denied = [];

	/**
	 * Administrator, no scan, no HTTP, fast scan, WooCommerce types not
	 * registered.
	 */
	public function set_up() {
		parent::set_up();
		self::deactivate_woocommerce();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( UsageScanStatus::OPTION );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		wp_unschedule_hook( TrashEligibility::POST_RESCAN_HOOK );
		$this->denied = [];
		add_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10, 4 );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
	}

	/**
	 * Restore.
	 */
	public function tear_down() {
		remove_filter( 'map_meta_cap', [ $this, 'deny_read' ], 10 );
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		remove_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		remove_all_filters( 'tsmlt_used_where_scannable_post_types' );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		wp_unschedule_hook( TrashEligibility::POST_RESCAN_HOOK );
		self::deactivate_woocommerce();
		parent::tear_down();
	}

	/**
	 * Deny read_post for chosen posts.
	 *
	 * @param string[] $caps    Required capabilities.
	 * @param string   $cap     Requested capability.
	 * @param int      $user_id User ID.
	 * @param array    $args    Arguments.
	 *
	 * @return string[]
	 */
	public function deny_read( $caps, $cap, $user_id, $args ) {
		if ( 'read_post' === $cap && isset( $args[0] ) && in_array( (int) $args[0], $this->denied, true ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
	}

	/**
	 * Refuse outgoing HTTP requests.
	 *
	 * @return WP_Error
	 */
	public function block_http() {
		return new WP_Error( 'blocked', 'No HTTP in tests.' );
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * Register the WooCommerce types as WooCommerce does.
	 */
	private static function activate_woocommerce() {
		register_post_type(
			'product',
			[
				'public'          => true,
				'capability_type' => 'product',
				'map_meta_cap'    => true,
			]
		);
		register_post_type(
			'product_variation',
			[
				'public'          => false,
				'capability_type' => 'product',
				'map_meta_cap'    => true,
			]
		);
		register_taxonomy( 'product_cat', 'product', [ 'public' => true ] );
	}

	/**
	 * Unregister them (WooCommerce inactive).
	 */
	private static function deactivate_woocommerce() {
		foreach ( [ 'product', 'product_variation' ] as $type ) {
			if ( post_type_exists( $type ) ) {
				unregister_post_type( $type );
			}
		}
		if ( taxonomy_exists( 'product_cat' ) ) {
			unregister_taxonomy( 'product_cat' );
		}
	}

	/**
	 * An image attachment with a file name.
	 *
	 * @param string $file File.
	 *
	 * @return int
	 */
	private function image( string $file ): int {
		return (int) self::factory()->attachment->create(
			[
				'file'           => 'woo/' . $file,
				'post_mime_type' => 'image/jpeg',
				'post_date'      => '2020-01-01 00:00:00',
				'post_date_gmt'  => '2020-01-01 00:00:00',
			]
		);
	}

	/**
	 * A store: a published product with a featured image, a private product
	 * with a gallery, a variation with its own image, a regular post, and an
	 * image used nowhere.
	 *
	 * @return array<string, int>
	 */
	private function store(): array {
		$ids              = [];
		$ids['featured']  = $this->image( 'featured.jpg' );
		$ids['gallery1']  = $this->image( 'gallery-1.jpg' );
		$ids['gallery2']  = $this->image( 'gallery-2.jpg' );
		$ids['variation'] = $this->image( 'variation.jpg' );
		$ids['unused']    = $this->image( 'unused.jpg' );

		$ids['product'] = (int) self::factory()->post->create(
			[
				'post_type'  => 'product',
				'post_title' => 'Blue Hoodie',
			]
		);
		set_post_thumbnail( $ids['product'], $ids['featured'] );
		update_post_meta( $ids['product'], '_thumbnail_id', $ids['featured'] );

		$ids['private_product'] = (int) self::factory()->post->create(
			[
				'post_type'   => 'product',
				'post_status' => 'private',
				'post_title'  => 'Secret Prototype',
			]
		);
		update_post_meta( $ids['private_product'], '_product_image_gallery', $ids['gallery1'] . ',' . $ids['gallery2'] );

		$ids['variation_post'] = (int) self::factory()->post->create(
			[
				'post_type'   => 'product_variation',
				'post_parent' => $ids['product'],
				'post_title'  => 'Blue Hoodie - Large',
			]
		);
		update_post_meta( $ids['variation_post'], '_thumbnail_id', $ids['variation'] );

		$ids['post'] = (int) self::factory()->post->create();
		return $ids;
	}

	/**
	 * Run a whole scan, as WP-Cron would.
	 *
	 * @param callable|null $between Called after the start, before the batches.
	 *
	 * @return array Stored status.
	 */
	private static function scan( ?callable $between = null ): array {
		UsedWhereScanner::instance()->start_scheduled_scan();
		if ( $between ) {
			$between();
		}
		for ( $i = 0; $i < 50; $i++ ) {
			$tick = null;
			foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
				foreach ( $hooks[ UsedWhereScanner::SCAN_TICK_HOOK ] ?? [] as $event ) {
					$tick = [ $timestamp, (int) $event['args'][0] ];
					break 2;
				}
			}
			if ( ! $tick ) {
				break;
			}
			wp_unschedule_event( $tick[0], UsedWhereScanner::SCAN_TICK_HOOK, [ $tick[1] ] );
			UsedWhereScanner::instance()->run_tick_batch( $tick[1] );
		}
		wp_unschedule_hook( TrashEligibility::POST_RESCAN_HOOK );
		return get_option( UsageScanStatus::OPTION );
	}

	/**
	 * Usage types recorded for an attachment, sorted.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return string[]
	 */
	private static function recorded_types( int $id ): array {
		$records = get_post_meta( $id, UsedWhereScanner::META_KEY, true );
		$types   = is_array( $records ) ? wp_list_pluck( $records, 'usage_type' ) : [];
		sort( $types );
		return $types;
	}

	/**
	 * tsmlt/get-media-usage items by ID.
	 *
	 * @param int[] $ids IDs.
	 *
	 * @return array
	 */
	private static function usage( array $ids ): array {
		$result = wp_get_ability( 'tsmlt/get-media-usage' )->execute( [ 'ids' => $ids ] );
		return [ $result['scan'], array_column( $result['items'], null, 'id' ) ];
	}

	// ---------------------------------------------------------------------
	// Scanner coverage
	// ---------------------------------------------------------------------

	/**
	 * WooCommerce inactive: product featured images, product galleries and
	 * variation images are still found; products and variations count in
	 * the scan total.
	 */
	public function test_inactive_woocommerce_usage_is_detected() {
		$ids = $this->store();
		$this->assertFalse( post_type_exists( 'product' ), 'Precondition: WooCommerce inactive.' );

		$status = self::scan();

		$this->assertSame( 'complete', $status['state'] );
		$this->assertSame( 4, $status['total'], 'Two products, a variation and a post.' );
		$this->assertSame( 4, $status['processed'] );
		$this->assertSame( [ 'featured' ], self::recorded_types( $ids['featured'] ) );
		$this->assertSame( [ 'woo_gallery' ], self::recorded_types( $ids['gallery1'] ) );
		$this->assertSame( [ 'woo_gallery' ], self::recorded_types( $ids['gallery2'] ) );
		$this->assertSame( [ 'featured' ], self::recorded_types( $ids['variation'] ) );
		$this->assertSame( [], self::recorded_types( $ids['unused'] ) );
		$this->assertContains( 'product', $status['scanned_post_types'] );
		$this->assertContains( 'product_variation', $status['scanned_post_types'] );
	}

	/**
	 * WooCommerce active: variations are scanned although their type is not
	 * public, and the scan's coverage is verified.
	 */
	public function test_active_woocommerce_variation_is_detected() {
		self::activate_woocommerce();
		$ids = $this->store();
		$this->assertFalse( is_post_type_viewable( 'product_variation' ), 'Precondition: variations are not public.' );

		$status = self::scan();

		$this->assertSame( 4, $status['total'] );
		$this->assertSame( [ 'featured' ], self::recorded_types( $ids['variation'] ) );
		$this->assertSame( [], $status['coverage_gaps'] );
		$this->assertTrue( UsageScanStatus::read()['coverage']['verified'] );

		[ $scan, $items ] = self::usage( [ $ids['featured'], $ids['variation'], $ids['unused'] ] );
		$this->assertTrue( $scan['complete'] );
		$this->assertTrue( $items[ $ids['featured'] ]['used'] );
		$this->assertSame( 'Blue Hoodie', $items[ $ids['featured'] ]['usages'][0]['title'] );
		$this->assertTrue( $items[ $ids['variation'] ]['used'] );
		$this->assertFalse( $items[ $ids['unused'] ]['used'], 'Verified unused.' );
	}

	/**
	 * A site without any product rows keeps its old scan scope and has no
	 * coverage gap.
	 */
	public function test_site_without_products_is_unchanged() {
		$unused = $this->image( 'plain.jpg' );
		self::factory()->post->create();

		$status = self::scan();

		$this->assertSame( 1, $status['total'] );
		$this->assertNotContains( 'product', $status['scanned_post_types'] );
		$this->assertSame( [], $status['coverage_gaps'] );
		$this->assertFalse( self::usage( [ $unused ] )[1][ $unused ]['used'] );
		$this->assertSame( '', UsedWhereScanner::instance()->get_scan_status()['coverage_notice'] );
	}

	// ---------------------------------------------------------------------
	// Unknown instead of unused
	// ---------------------------------------------------------------------

	/**
	 * WooCommerce inactive: the scan records the gap; media without a usage
	 * is unknown (not unused), found product usages are used; products in an
	 * unregistered type, and private products, are counted but never listed.
	 */
	public function test_inactive_scan_reports_unknown_not_unused() {
		$ids    = $this->store();
		$status = self::scan();

		$this->assertSame( [ 'woocommerce_inactive' ], $status['coverage_gaps'] );
		$coverage = UsageScanStatus::read()['coverage'];
		$this->assertSame( [ 'verified' => false, 'gaps' => [ 'woocommerce_inactive' ] ], $coverage );

		[ $scan, $items ] = self::usage( [ $ids['featured'], $ids['gallery1'], $ids['variation'], $ids['unused'] ] );
		$this->assertSame( 'complete', $scan['state'] );
		$this->assertFalse( $scan['complete'], 'Not verified.' );
		$this->assertNull( $items[ $ids['unused'] ]['used'], 'Unknown, not unused.' );
		foreach ( [ 'featured', 'gallery1', 'variation' ] as $key ) {
			$this->assertTrue( $items[ $ids[ $key ] ]['used'], $key );
			$this->assertSame( $items[ $ids[ $key ] ]['total_usage'], $items[ $ids[ $key ] ]['hidden'], $key . ': not listed (type not registered).' );
		}
		$json = (string) wp_json_encode( $items );
		$this->assertStringNotContainsString( 'Secret Prototype', $json );
		$this->assertStringNotContainsString( 'Blue Hoodie', $json );
	}

	/**
	 * WooCommerce active: a private product the caller cannot read is counted
	 * as hidden, without its title.
	 */
	public function test_private_product_usage_stays_hidden() {
		self::activate_woocommerce();
		$ids = $this->store();
		self::scan();
		$this->denied = [ $ids['private_product'] ];

		[ , $items ] = self::usage( [ $ids['gallery1'] ] );

		$this->assertTrue( $items[ $ids['gallery1'] ]['used'] );
		$this->assertSame( 1, $items[ $ids['gallery1'] ]['hidden'] );
		$this->assertSame( [], $items[ $ids['gallery1'] ]['usages'] );
		$this->assertStringNotContainsString( 'Secret Prototype', (string) wp_json_encode( $items ) );
	}

	/**
	 * A filter that removes the product types is a coverage gap too.
	 */
	public function test_filtered_out_products_are_a_gap() {
		self::activate_woocommerce();
		$ids = $this->store();
		add_filter(
			'tsmlt_used_where_scannable_post_types',
			static function ( $types ) {
				return array_diff( $types, [ 'product', 'product_variation' ] );
			}
		);

		$status = self::scan();

		$this->assertSame( [ 'woocommerce_types_not_scanned' ], $status['coverage_gaps'] );
		$this->assertSame( [], self::recorded_types( $ids['featured'] ) );
		$this->assertNull( self::usage( [ $ids['featured'] ] )[1][ $ids['featured'] ]['used'], 'Unknown, not unused.' );
	}

	/**
	 * A scan status without a coverage record (made by an earlier version)
	 * is not verified.
	 */
	public function test_status_without_coverage_record_is_not_verified() {
		$unused = $this->image( 'old.jpg' );
		update_option(
			UsageScanStatus::OPTION,
			[
				'state'      => 'complete',
				'complete'   => true,
				'scan_id'    => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
				'started_at' => current_time( 'mysql' ),
			],
			false
		);

		$this->assertSame( [ 'verified' => false, 'gaps' => [ 'not_recorded' ] ], UsageScanStatus::read()['coverage'] );
		$this->assertNull( self::usage( [ $unused ] )[1][ $unused ]['used'] );
		$result = wp_get_ability( 'tsmlt/trash-unused-media' )->execute( [ 'ids' => [ $unused ] ] );
		$this->assertWPError( $result );
		$this->assertSame( TrashEligibility::ERROR_COVERAGE, $result->get_error_code() );
		$this->assertStringContainsString( 'earlier plugin version', $result->get_error_message() );
	}

	// ---------------------------------------------------------------------
	// Trash tools
	// ---------------------------------------------------------------------

	/**
	 * After a scan made while WooCommerce was inactive, preview and trash
	 * refuse with a machine-readable reason and the explanation; nothing is
	 * moved.
	 */
	public function test_trash_tools_refuse_after_unverified_scan() {
		$ids = $this->store();
		self::scan();

		foreach ( [ 'tsmlt/preview-trash-unused-media', 'tsmlt/trash-unused-media' ] as $name ) {
			$result = wp_get_ability( $name )->execute( [ 'ids' => [ $ids['unused'], $ids['featured'] ] ] );
			$this->assertWPError( $result, $name );
			$this->assertSame( TrashEligibility::ERROR_COVERAGE, $result->get_error_code() );
			$this->assertSame( [ 'woocommerce_inactive' ], $result->get_error_data()['gaps'] );
			$this->assertSame( 409, $result->get_error_data()['status'] );
			$this->assertStringContainsString( 'WooCommerce is inactive', $result->get_error_message() );
		}
		$this->assertSame( 'inherit', get_post( $ids['unused'] )->post_status, 'Not moved.' );
		$this->assertSame( 'inherit', get_post( $ids['featured'] )->post_status, 'Not moved.' );
	}

	/**
	 * With WooCommerce active and a verified scan, only the unused image is
	 * eligible: product, gallery and variation images are skipped as used.
	 */
	public function test_trash_after_verified_scan_keeps_product_images() {
		self::activate_woocommerce();
		$ids = $this->store();
		self::scan();

		$result = wp_get_ability( 'tsmlt/trash-unused-media' )->execute( [ 'ids' => [ $ids['featured'], $ids['gallery1'], $ids['variation'], $ids['unused'] ] ] );

		$this->assertSame( [ $ids['unused'] ], $result['trashed'] );
		$this->assertSame( [ 'used', 'used', 'used', null ], wp_list_pluck( $result['results'], 'reason' ) );
	}

	// ---------------------------------------------------------------------
	// Coverage lifecycle
	// ---------------------------------------------------------------------

	/**
	 * Every scan records its own coverage: a gap from an inactive-WooCommerce
	 * run is gone after a run with WooCommerce active, and comes back when a
	 * run starts while it is inactive again.
	 */
	public function test_coverage_is_recorded_per_scan() {
		$this->store();

		$this->assertSame( [ 'woocommerce_inactive' ], self::scan()['coverage_gaps'] );

		self::activate_woocommerce();
		$active = self::scan();
		$this->assertSame( [], $active['coverage_gaps'] );
		$this->assertTrue( UsageScanStatus::read()['coverage']['verified'] );

		self::deactivate_woocommerce();
		UsedWhereScanner::instance()->start_scheduled_scan();
		$this->assertSame( [ 'woocommerce_inactive' ], get_option( UsageScanStatus::OPTION )['coverage_gaps'], 'A restart records its own coverage.' );
		$this->assertNotSame( $active['scan_id'], get_option( UsageScanStatus::OPTION )['scan_id'] );
	}

	/**
	 * A gap that appears while a scan runs (WooCommerce turned off after the
	 * start) is added by the batches.
	 */
	public function test_gap_found_by_a_batch_is_kept() {
		self::activate_woocommerce();
		$this->store();

		$status = self::scan(
			static function () {
				self::deactivate_woocommerce();
			}
		);

		$this->assertSame( [ 'woocommerce_inactive' ], $status['coverage_gaps'] );
		$this->assertFalse( UsageScanStatus::read()['coverage']['verified'] );
	}

	// ---------------------------------------------------------------------
	// Used Where page notice and MCP status
	// ---------------------------------------------------------------------

	/**
	 * The Used Where page's status carries the warning when WooCommerce is
	 * inactive and products exist (also before any scan), and none when
	 * coverage is verified.
	 */
	public function test_notice_only_when_applicable() {
		$scanner = UsedWhereScanner::instance();
		$this->assertSame( '', $scanner->get_scan_status()['coverage_notice'], 'No products, no notice.' );

		$this->store();
		$before = $scanner->get_scan_status();
		$this->assertSame( self::INACTIVE_NOTICE, $before['coverage_notice'], 'Shown before a scan.' );
		$this->assertSame( [ 'woocommerce_inactive' ], $before['coverage_gaps'] );

		self::scan();
		$this->assertSame( self::INACTIVE_NOTICE, $scanner->get_scan_status()['coverage_notice'] );

		self::activate_woocommerce();
		$this->assertSame( self::INACTIVE_NOTICE, $scanner->get_scan_status()['coverage_notice'], 'Kept until a scan with WooCommerce active.' );
		self::scan();
		$after = $scanner->get_scan_status();
		$this->assertSame( '', $after['coverage_notice'] );
		$this->assertSame( [], $after['coverage_gaps'] );
	}

	/**
	 * MCP callers get the coverage in the status tool's output.
	 */
	public function test_status_tool_reports_coverage() {
		$this->store();
		self::scan();

		$status = wp_get_ability( 'tsmlt/get-usage-scan-status' )->execute();

		$this->assertSame( 'complete', $status['state'] );
		$this->assertSame( [ 'verified' => false, 'gaps' => [ 'woocommerce_inactive' ] ], $status['coverage'] );
	}
}
