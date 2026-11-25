<?php
/**
 * A usage scan starts on any storage engine. Its batches are saved in
 * transactions only when the tables they write to (options, postmeta,
 * posts) are known to be InnoDB; another engine, a table missing from the
 * lookup or an unreadable lookup selects the fallback save
 * (UsedWhereScanner::transactions_supported(); the fallback itself is
 * covered by UsedWhereScanFallbackTest).
 *
 * The storage-engine lookup is answered by a `query` filter, so no table is
 * really converted: the engines are simulated, the database here is
 * InnoDB. Outgoing HTTP is blocked.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\StartUsageScan;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereScanDatabaseCheckTest
 */
class UsedWhereScanDatabaseCheckTest extends WP_UnitTestCase {

	/**
	 * Status option.
	 *
	 * @var string
	 */
	const STATUS = 'tsmlt_used_where_scan_status';

	/**
	 * Engine reported per table, or null to break the lookup.
	 *
	 * @var array|null
	 */
	private $engines = [];

	/**
	 * Previous scan's status, and an attachment with a usage record.
	 *
	 * @var array
	 */
	private $before = [];

	/**
	 * Attachment with a usage record.
	 *
	 * @var int
	 */
	private $attachment = 0;

	/**
	 * Administrator; a completed scan with one usage record.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'query', [ $this, 'report_engines' ] );

		$this->attachment = (int) self::factory()->attachment->create(
			[
				'file'           => 'engine/image.jpg',
				'post_mime_type' => 'image/jpeg',
			]
		);
		update_post_meta(
			$this->attachment,
			UsedWhereScanner::META_KEY,
			[
				[
					'post_id'    => 1,
					'post_title' => 'Post',
					'post_type'  => 'post',
					'usage_type' => 'content',
				],
			]
		);
		$this->before = [
			'state'     => 'complete',
			'complete'  => true,
			'processed' => 1,
			'total'     => 1,
			'scan_id'   => 'previous-scan',
		];
		update_option( self::STATUS, $this->before, false );
	}

	/**
	 * Remove filters and scheduled batches.
	 */
	public function tear_down() {
		remove_filter( 'query', [ $this, 'report_engines' ] );
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
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
	 * Answer the storage-engine lookup from $this->engines.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function report_engines( $query ) {
		global $wpdb;
		if ( false === strpos( $query, 'information_schema.TABLES' ) || [] === $this->engines ) {
			return $query;
		}
		if ( null === $this->engines ) {
			return 'SELECT * FROM tsmlt_no_such_table';
		}
		$rows = [];
		foreach ( $this->engines as $table => $engine ) {
			$rows[] = $wpdb->prepare( 'SELECT %s AS table_name, %s AS engine', $table, $engine );
		}
		return implode( ' UNION ALL ', $rows );
	}

	/**
	 * Engines with one table changed.
	 *
	 * @param string $table  Table.
	 * @param string $engine Engine.
	 *
	 * @return array
	 */
	private static function engines_with( string $table, string $engine ): array {
		global $wpdb;
		$engines           = [
			$wpdb->options  => 'InnoDB',
			$wpdb->postmeta => 'InnoDB',
			$wpdb->posts    => 'InnoDB',
		];
		$engines[ $table ] = $engine;
		return $engines;
	}

	/**
	 * Nothing changed: previous status and records kept, nothing scheduled.
	 */
	private function assert_nothing_changed() {
		$this->assertSame( $this->before, get_option( self::STATUS ) );
		$this->assertNotEmpty( get_post_meta( $this->attachment, UsedWhereScanner::META_KEY, true ) );
		$this->assertFalse( wp_next_scheduled( UsedWhereScanner::SCAN_TICK_HOOK, [ 0 ] ) );
	}

	/**
	 * The scan started: queued, a new scan, the first batch scheduled.
	 *
	 * @param mixed $result start_scheduled_scan() result.
	 */
	private function assert_started( $result ) {
		$this->assertIsArray( $result );
		$this->assertSame( 'queued', get_option( self::STATUS )['state'] );
		$this->assertNotSame( 'previous-scan', get_option( self::STATUS )['scan_id'] );
		$this->assertNotFalse( wp_next_scheduled( UsedWhereScanner::SCAN_TICK_HOOK, [ 0 ] ) );
	}

	/**
	 * Every non-InnoDB case starts the scan and selects the fallback save:
	 * a MyISAM postmeta or options table, a MariaDB Aria posts table, a
	 * table missing from the lookup, and an unreadable lookup.
	 */
	public function test_other_engines_start_with_the_fallback() {
		global $wpdb;
		$unlisted = self::engines_with( $wpdb->posts, 'InnoDB' );
		unset( $unlisted[ $wpdb->posts ] );
		$cases = [
			'MyISAM postmeta' => self::engines_with( $wpdb->postmeta, 'MyISAM' ),
			'MyISAM options'  => self::engines_with( $wpdb->options, 'MyISAM' ),
			'Aria posts'      => self::engines_with( $wpdb->posts, 'Aria' ),
			'unlisted table'  => $unlisted,
			'unreadable'      => null,
		];
		foreach ( $cases as $case => $engines ) {
			update_option( self::STATUS, $this->before, false );
			wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
			$this->engines = $engines;

			$suppressed = $wpdb->suppress_errors( true );
			$this->assertFalse( UsedWhereScanner::transactions_supported(), $case );
			$result = UsedWhereScanner::instance()->start_scheduled_scan();
			$wpdb->suppress_errors( $suppressed );

			$this->assert_started( $result );
		}
	}

	/**
	 * The start-usage-scan ability starts a scan on MyISAM, with its usual
	 * response.
	 */
	public function test_ability_starts_on_myisam() {
		global $wpdb;
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/start-usage-scan' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		$this->engines = self::engines_with( $wpdb->postmeta, 'MyISAM' );

		$result = wp_get_ability( 'tsmlt/start-usage-scan' )->execute();

		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		$this->assertSame( 'started', $result['result'] );
		$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), wp_get_ability( 'tsmlt/start-usage-scan' )->get_output_schema(), 'result' ) );
		$this->assertSame( 'queued', get_option( self::STATUS )['state'] );
	}

	/**
	 * A user without manage_options is refused before the database is
	 * checked, through the ability and by a direct call to its execute
	 * callback; neither error reveals anything about the database.
	 */
	public function test_unauthorized_callers_learn_nothing() {
		global $wpdb;
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/start-usage-scan' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		$this->engines = self::engines_with( $wpdb->postmeta, 'MyISAM' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$via_ability = wp_get_ability( 'tsmlt/start-usage-scan' )->execute();
		$direct      = StartUsageScan::instance()->execute();

		$this->assertWPError( $via_ability );
		$this->assertSame( 'ability_invalid_permissions', $via_ability->get_error_code() );
		$this->assertWPError( $direct );
		$this->assertSame( AbilityGuard::ERROR_FORBIDDEN, $direct->get_error_code() );
		foreach ( [ $via_ability, $direct ] as $error ) {
			$this->assertStringNotContainsString( 'InnoDB', $error->get_error_message() );
			$this->assertStringNotContainsString( $wpdb->postmeta, $error->get_error_message() );
		}
		$this->assert_nothing_changed();
	}

	/**
	 * InnoDB tables (the real ones): the scan starts and uses transactions.
	 */
	public function test_innodb_tables_use_transactions() {
		$this->assertTrue( UsedWhereScanner::transactions_supported() );
		$this->assert_started( UsedWhereScanner::instance()->start_scheduled_scan() );
	}

	/**
	 * The filter can turn transactions off on InnoDB, never on elsewhere.
	 */
	public function test_filter_only_turns_transactions_off() {
		global $wpdb;
		add_filter( UsedWhereScanner::TRANSACTIONS_FILTER, '__return_false' );
		$this->assertFalse( UsedWhereScanner::transactions_supported(), 'Off on InnoDB.' );
		remove_filter( UsedWhereScanner::TRANSACTIONS_FILTER, '__return_false' );

		$this->engines = self::engines_with( $wpdb->postmeta, 'MyISAM' );
		add_filter( UsedWhereScanner::TRANSACTIONS_FILTER, '__return_true' );
		$this->assertFalse( UsedWhereScanner::transactions_supported(), 'Never on MyISAM.' );
		remove_filter( UsedWhereScanner::TRANSACTIONS_FILTER, '__return_true' );
	}
}
