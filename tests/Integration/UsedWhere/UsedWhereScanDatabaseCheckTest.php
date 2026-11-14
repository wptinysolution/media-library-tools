<?php
/**
 * A usage scan starts only when the tables its batches write to in one
 * transaction (options, postmeta, posts) use InnoDB: otherwise, or when the
 * engines cannot be read, nothing changes and the reason is reported.
 *
 * The storage-engine lookup is answered by a `query` filter, so no table is
 * really converted. Outgoing HTTP is blocked.
 *
 * @package TinySolutions\mlt
 */

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
	 * A MyISAM postmeta table: the scan does not start and says which table
	 * to convert.
	 */
	public function test_myisam_postmeta_prevents_the_start() {
		global $wpdb;
		$this->engines = self::engines_with( $wpdb->postmeta, 'MyISAM' );

		$result = UsedWhereScanner::instance()->start_scheduled_scan();

		$this->assertWPError( $result );
		$this->assertSame( 'tsmlt_usage_scan_database_unsupported', $result->get_error_code() );
		$this->assertStringContainsString( $wpdb->postmeta, $result->get_error_message() );
		$this->assertStringContainsString( 'InnoDB', $result->get_error_message() );
		$this->assertStringNotContainsString( $wpdb->options, $result->get_error_message() );
		$this->assert_nothing_changed();
	}

	/**
	 * A MyISAM options table: the scan does not start.
	 */
	public function test_myisam_options_prevents_the_start() {
		global $wpdb;
		$this->engines = self::engines_with( $wpdb->options, 'MyISAM' );

		$result = UsedWhereScanner::instance()->start_scheduled_scan();

		$this->assertWPError( $result );
		$this->assertStringContainsString( $wpdb->options, $result->get_error_message() );
		$this->assert_nothing_changed();
	}

	/**
	 * A table missing from the lookup counts as unsupported.
	 */
	public function test_unlisted_table_prevents_the_start() {
		global $wpdb;
		$this->engines = self::engines_with( $wpdb->posts, 'InnoDB' );
		unset( $this->engines[ $wpdb->posts ] );

		$result = UsedWhereScanner::instance()->start_scheduled_scan();

		$this->assertWPError( $result );
		$this->assertStringContainsString( $wpdb->posts, $result->get_error_message() );
		$this->assert_nothing_changed();
	}

	/**
	 * Engines that cannot be read: the scan does not start.
	 */
	public function test_unreadable_engines_prevent_the_start() {
		global $wpdb;
		$this->engines = null;

		$suppressed = $wpdb->suppress_errors( true );
		$result     = UsedWhereScanner::instance()->start_scheduled_scan();
		$wpdb->suppress_errors( $suppressed );

		$this->assertWPError( $result );
		$this->assertSame( 'tsmlt_usage_scan_database_unchecked', $result->get_error_code() );
		$this->assert_nothing_changed();
	}

	/**
	 * The start-usage-scan ability reports the reason as its start error.
	 */
	public function test_ability_reports_the_reason() {
		global $wpdb;
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/start-usage-scan' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		$this->engines = self::engines_with( $wpdb->postmeta, 'MyISAM' );

		$result = wp_get_ability( 'tsmlt/start-usage-scan' )->execute();

		$this->assertWPError( $result );
		$this->assertSame( StartUsageScan::ERROR_START_FAILED, $result->get_error_code() );
		$this->assertStringContainsString( 'InnoDB', $result->get_error_message() );
		$this->assert_nothing_changed();
	}

	/**
	 * InnoDB tables (the real ones): the scan starts.
	 */
	public function test_innodb_tables_allow_the_start() {
		$result = UsedWhereScanner::instance()->start_scheduled_scan();

		$this->assertIsArray( $result );
		$this->assertSame( 'queued', get_option( self::STATUS )['state'] );
		$this->assertNotFalse( wp_next_scheduled( UsedWhereScanner::SCAN_TICK_HOOK, [ 0 ] ) );
	}
}
