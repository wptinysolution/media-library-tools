<?php
/**
 * Used Where scans on tables that are not InnoDB: batches and cleanup steps
 * are saved without a transaction, in the fallback order (records first,
 * the status claim last), so a scan completes, a failed batch is not
 * reported as saved and is run again without duplicates, and progress,
 * cancel and the batch lock work as on InnoDB.
 *
 * The storage engines are simulated: a `query` filter answers the engine
 * lookup (information_schema), while the tables here are really InnoDB. So
 * this checks which save is chosen and the fallback order; it does not show
 * how a real MyISAM table behaves. Every post and attachment is a generated
 * fixture; outgoing HTTP is blocked.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Modules\UsedWhere\ScanBatchLock;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereScanFallbackTest
 */
class UsedWhereScanFallbackTest extends WP_UnitTestCase {

	/**
	 * Status option.
	 *
	 * @var string
	 */
	const STATUS = 'tsmlt_used_where_scan_status';

	/**
	 * Engines the lookup reports: 'myisam', 'mixed', 'unreadable' or '' (real).
	 *
	 * @var string
	 */
	private $engines = 'myisam';

	/**
	 * Transaction statements sent.
	 *
	 * @var int
	 */
	private $transactions = 0;

	/**
	 * Usage-record INSERT to break (1-based), or 0.
	 *
	 * @var int
	 */
	private $break_insert = 0;

	/**
	 * Usage-record INSERTs seen.
	 *
	 * @var int
	 */
	private $inserts = 0;

	/**
	 * Fixture attachments (featured images, unattached).
	 *
	 * @var int[]
	 */
	private $attachments = [];

	/**
	 * Administrator; eight posts with a featured image each, plus one more
	 * post using the first image.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		delete_option( self::STATUS );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'query', [ $this, 'observe' ], 1 );
		$this->attachments = [];
		for ( $i = 0; $i < 8; $i++ ) {
			$post  = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
			$image = (int) self::factory()->attachment->create(
				[
					'file'           => 'fallback/image-' . $i . '.jpg',
					'post_mime_type' => 'image/jpeg',
					'post_parent'    => 0,
				]
			);
			set_post_thumbnail( $post, $image );
			$this->attachments[] = $image;
		}
		set_post_thumbnail( (int) self::factory()->post->create( [ 'post_status' => 'publish' ] ), $this->attachments[0] );
	}

	/**
	 * Remove filters and scheduled batches.
	 */
	public function tear_down() {
		remove_filter( 'query', [ $this, 'observe' ], 1 );
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
	 * Answer the engine lookup, count transaction statements, and break a
	 * chosen usage-record INSERT.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function observe( $query ) {
		global $wpdb;
		if ( false !== strpos( $query, 'tsmlt_used_where_batch' ) ) {
			++$this->transactions;
		}
		if ( 0 === stripos( ltrim( $query ), 'INSERT' ) && false !== strpos( $query, $wpdb->postmeta ) && false !== strpos( $query, UsedWhereScanner::META_KEY ) ) {
			++$this->inserts;
			if ( $this->inserts === $this->break_insert ) {
				return 'INSERT INTO tsmlt_no_such_table VALUES (1)';
			}
		}
		if ( '' === $this->engines || false === strpos( $query, 'information_schema.TABLES' ) ) {
			return $query;
		}
		if ( 'unreadable' === $this->engines ) {
			return 'SELECT * FROM tsmlt_no_such_table';
		}
		$postmeta = 'mixed' === $this->engines ? 'InnoDB' : 'MyISAM';
		$options  = 'mixed' === $this->engines ? 'MyISAM' : 'MyISAM';
		return $wpdb->prepare(
			'SELECT %s AS table_name, %s AS engine UNION ALL SELECT %s, %s UNION ALL SELECT %s, %s',
			$wpdb->options,
			$options,
			$wpdb->postmeta,
			$postmeta,
			$wpdb->posts,
			'mixed' === $this->engines ? 'InnoDB' : 'MyISAM'
		);
	}

	/**
	 * The status in the database.
	 *
	 * @return array
	 */
	private static function stored_status(): array {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::STATUS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
		return null === $value ? [] : (array) maybe_unserialize( $value );
	}

	/**
	 * Start a scan and leave its first batch unscheduled.
	 *
	 * @return array
	 */
	private static function start(): array {
		UsedWhereScanner::instance()->start_scheduled_scan();
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		return self::stored_status();
	}

	/**
	 * Run one batch at an offset, with database errors kept quiet.
	 *
	 * @param int $offset Offset.
	 */
	private static function tick( int $offset ) {
		global $wpdb;
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		$suppressed = $wpdb->suppress_errors( true );
		UsedWhereScanner::instance()->run_tick_batch( $offset );
		$wpdb->suppress_errors( $suppressed );
	}

	/**
	 * Run batches until the scan is no longer active.
	 *
	 * @return array Final status.
	 */
	private static function run_to_end(): array {
		for ( $i = 0; $i < 20; $i++ ) {
			$status = self::stored_status();
			if ( ! in_array( $status['state'] ?? '', [ 'queued', 'running' ], true ) ) {
				break;
			}
			self::tick( (int) $status['next_offset'] );
		}
		return self::stored_status();
	}

	/**
	 * Offsets of scheduled batches.
	 *
	 * @return int[]
	 */
	private static function ticks(): array {
		$ticks = [];
		foreach ( (array) _get_cron_array() as $hooks ) {
			foreach ( $hooks[ UsedWhereScanner::SCAN_TICK_HOOK ] ?? [] as $event ) {
				$ticks[] = (int) ( $event['args'][0] ?? 0 );
			}
		}
		sort( $ticks );
		return $ticks;
	}

	/**
	 * Usage entries of an attachment, read from the database.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array
	 */
	private static function usages( int $id ): array {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $id, UsedWhereScanner::META_KEY ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
		return null === $value ? [] : (array) maybe_unserialize( $value );
	}

	/**
	 * Every fixture image has its usages, each once, and a parent.
	 */
	private function assert_complete_results() {
		foreach ( $this->attachments as $index => $id ) {
			$usages = self::usages( $id );
			$keys   = array_map(
				static function ( $usage ) {
					return $usage['post_id'] . ':' . $usage['usage_type'];
				},
				$usages
			);
			$this->assertSame( 0 === $index ? 2 : 1, count( $usages ), "Usages of image $index." );
			$this->assertSame( count( $keys ), count( array_unique( $keys ) ), "No duplicate usage of image $index." );
			clean_post_cache( $id );
			$this->assertNotSame( 0, (int) get_post( $id )->post_parent, "Parent of image $index." );
		}
	}

	/**
	 * MyISAM tables: the scan completes with the same results and status as
	 * on InnoDB, without a single transaction statement, reporting progress
	 * batch by batch; the batch lock is released.
	 */
	public function test_scan_completes_without_transactions() {
		$this->assertFalse( UsedWhereScanner::transactions_supported() );
		$start = self::start();

		self::tick( 0 );
		$first = self::stored_status();
		$this->assertSame( 'running', $first['state'] );
		$this->assertGreaterThan( 0, (int) $first['next_offset'] );
		$this->assertSame( (int) $first['next_offset'], (int) $first['processed'], 'Progress.' );
		$this->assertSame( [ (int) $first['next_offset'] ], self::ticks(), 'The next batch is scheduled.' );

		$status = self::run_to_end();
		$this->assertSame( 'complete', $status['state'] );
		$this->assertTrue( $status['complete'] );
		$this->assertSame( $start['scan_id'], $status['scan_id'] );
		$this->assertSame( (int) $status['total'], (int) $status['processed'] );
		$this->assert_complete_results();
		$this->assertSame( 0, $this->transactions, 'No transaction statement was sent.' );
		$this->assertSame( [], self::ticks() );
		$this->assertNull( ScanBatchLock::instance()->read_lock(), 'The lock is released.' );
	}

	/**
	 * Mixed engines and an unreadable engine list use the fallback too.
	 */
	public function test_mixed_and_unreadable_engines_complete() {
		foreach ( [ 'mixed', 'unreadable' ] as $engines ) {
			$this->engines      = $engines;
			$this->transactions = 0;
			global $wpdb;
			$suppressed = $wpdb->suppress_errors( true );
			$this->assertFalse( UsedWhereScanner::transactions_supported(), $engines );
			$wpdb->suppress_errors( $suppressed );

			self::start();
			$this->assertSame( 'complete', self::run_to_end()['state'], $engines );
			$this->assert_complete_results();
			$this->assertSame( 0, $this->transactions, $engines );
		}
	}

	/**
	 * InnoDB (the real tables): the same scan saves in transactions.
	 */
	public function test_innodb_uses_transactions() {
		$this->engines = '';
		$this->assertTrue( UsedWhereScanner::transactions_supported() );
		self::start();
		$this->assertSame( 'complete', self::run_to_end()['state'] );
		$this->assert_complete_results();
		$this->assertGreaterThan( 0, $this->transactions, 'Saved in transactions.' );
	}

	/**
	 * A database error in the middle of a batch: the batch is not reported
	 * as saved (offset and progress unchanged, no next batch, no error state
	 * hiding it), the lock is released, stall recovery schedules the batch
	 * again, and running it again completes the scan without duplicates.
	 */
	public function test_failed_batch_recovers_without_duplicates() {
		self::start();
		$this->break_insert = 3;

		self::tick( 0 );

		$this->assertGreaterThanOrEqual( 3, $this->inserts, 'Precondition: the third record write was broken.' );
		$status = self::stored_status();
		$this->assertSame( 'running', $status['state'] );
		$this->assertSame( 0, (int) $status['next_offset'], 'Not reported as saved.' );
		$this->assertSame( 0, (int) $status['processed'] );
		$this->assertSame( [], self::ticks(), 'No next batch.' );
		$this->assertNull( ScanBatchLock::instance()->read_lock(), 'The lock is released.' );

		// Stall recovery, as the polling page triggers it: the same batch again.
		$status['last_tick_at'] = gmdate( 'Y-m-d H:i:s', time() - 600 );
		update_option( self::STATUS, $status, false );
		UsedWhereScanner::instance()->get_scan_status();
		$this->assertSame( [ 0 ], self::ticks() );

		$this->break_insert = 0;
		$this->assertSame( 'complete', self::run_to_end()['state'] );
		$this->assert_complete_results();
	}

	/**
	 * A batch whose status move was lost after its records were written
	 * (the case the fallback order allows) runs again: merged usages are not
	 * duplicated.
	 */
	public function test_replayed_batch_does_not_duplicate() {
		self::start();
		self::tick( 0 );
		$after_first = self::stored_status();
		$offset      = (int) $after_first['next_offset'];
		self::tick( $offset );

		// Back to before the second batch's status move, records kept.
		$replay = array_merge( self::stored_status(), [ 'next_offset' => $offset, 'processed' => $offset, 'state' => 'running', 'complete' => false ] );
		unset( $replay['cleanup_stage'], $replay['cleanup_cursor'] );
		update_option( self::STATUS, $replay, false );
		self::tick( $offset );

		$this->assertSame( 'complete', self::run_to_end()['state'] );
		$this->assert_complete_results();
	}

	/**
	 * Cancel stops the scan: the old scan's next batch writes nothing.
	 */
	public function test_cancel_stops_the_scan() {
		self::start();
		self::tick( 0 );
		$offset  = (int) self::stored_status()['next_offset'];
		$written = array_map( [ self::class, 'usages' ], $this->attachments );

		UsedWhereScanner::instance()->cancel_scheduled_scan();
		self::tick( $offset );

		$this->assertSame( 'cancelled', self::stored_status()['state'] );
		$this->assertSame( $written, array_map( [ self::class, 'usages' ], $this->attachments ) );
		$this->assertSame( [], self::ticks() );
		$this->assertNull( ScanBatchLock::instance()->read_lock() );
	}

	/**
	 * Admin and MCP callers get the same response shapes after a fallback
	 * scan as after a transactional one.
	 */
	public function test_responses_match_innodb() {
		self::start();
		self::run_to_end();
		$fallback     = UsedWhereScanner::instance()->get_scan_status();
		$fallback_mcp = UsageScanStatus::read();

		$this->engines = '';
		self::start();
		self::run_to_end();
		$innodb     = UsedWhereScanner::instance()->get_scan_status();
		$innodb_mcp = UsageScanStatus::read();

		$this->assertSame( array_keys( $innodb ), array_keys( $fallback ) );
		$this->assertSame( array_keys( $innodb_mcp ), array_keys( $fallback_mcp ) );
		$this->assertTrue( $fallback['complete'] );
		$this->assertTrue( $fallback_mcp['complete'] );
		if ( function_exists( 'wp_get_ability' ) && wp_get_ability( 'tsmlt/get-usage-scan-status' ) ) {
			$this->engines = 'myisam';
			$result        = wp_get_ability( 'tsmlt/get-usage-scan-status' )->execute( [] );
			$this->assertTrue( rest_validate_value_from_schema( json_decode( wp_json_encode( $result ), true ), wp_get_ability( 'tsmlt/get-usage-scan-status' )->get_output_schema(), 'result' ) );
		}
	}
}
