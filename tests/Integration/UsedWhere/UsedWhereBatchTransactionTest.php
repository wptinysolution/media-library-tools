<?php
/**
 * A Used Where scan batch saves its results in one real database
 * transaction that locks the scan's status row: a Cancel, Clear Results or
 * Re-scan waits until the batch has saved everything or nothing, an
 * obsolete or duplicate batch saves nothing, and a failure rolls every
 * usage record, permalink fingerprint and attachment parent back.
 *
 * Concurrency here is real at the database: the competing request's
 * statement (the status write or delete that Cancel, Clear Results and
 * Re-scan start with) is sent on a second MySQL connection, asynchronously,
 * while the batch's transaction is open, and the test checks that it waits
 * for the batch's COMMIT. It is not parallel PHP: the competing request's
 * other work runs afterwards in this process.
 *
 * Connection loss is tested two ways: simulated, by closing $wpdb's
 * connection and opening a new one in the middle of the save (what a
 * reconnect does; the closed connection's transaction is really discarded
 * by the server), and genuine, by killing the batch's connection from the
 * second connection (KILL CONNECTION).
 *
 * WP_UnitTestCase wraps every test in a transaction, which a real
 * transaction would commit; this class turns that off (autocommit on, the
 * bootstrap's savepoint filter removed), so fixtures are committed and
 * tear_down() deletes them. Outgoing HTTP is answered with a generated page;
 * every post and attachment is a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\UsedWhere\BatchTransaction;
use TinySolutions\mlt\Modules\UsedWhere\ScanBatchLock;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereBatchTransactionTest
 */
class UsedWhereBatchTransactionTest extends WP_UnitTestCase {

	/**
	 * Status option.
	 *
	 * @var string
	 */
	const STATUS = 'tsmlt_used_where_scan_status';

	/**
	 * Permalink fingerprint meta key.
	 *
	 * @var string
	 */
	const FINGERPRINT = '_tsmlt_permalink_fp';

	/**
	 * Posts and attachments created by the test.
	 *
	 * @var int[]
	 */
	private $created = [];

	/**
	 * Attachments created by the test.
	 *
	 * @var int[]
	 */
	private $attachments = [];

	/**
	 * Second database connection (the competing request).
	 *
	 * @var mysqli|null
	 */
	private $link = null;

	/**
	 * When the competing statement runs: 'before_claim' (synchronously, when
	 * the batch's transaction starts) or 'after_claim' (asynchronously, once
	 * the batch holds the status row).
	 *
	 * @var string
	 */
	private $when = '';

	/**
	 * Competing statement.
	 *
	 * @var string
	 */
	private $competitor = '';

	/**
	 * Whether the competing statement was sent.
	 *
	 * @var bool
	 */
	private $sent = false;

	/**
	 * Whether the competing statement was still waiting one second after it
	 * was sent.
	 *
	 * @var bool
	 */
	private $waited = false;

	/**
	 * Whether a batch transaction is open, and whether it claimed the status.
	 *
	 * @var array
	 */
	private $txn = [
		'open'    => false,
		'claimed' => false,
	];

	/**
	 * A statement to break inside the batch transaction: a substring of it,
	 * or ''.
	 *
	 * @var string
	 */
	private $break = '';

	/**
	 * How to drop the batch's connection when it first writes a permalink
	 * fingerprint: 'reconnect', 'kill' or ''.
	 *
	 * @var string
	 */
	private $drop = '';

	/**
	 * Thread ID of the connection that was dropped, or 0.
	 *
	 * @var int
	 */
	private $dropped = 0;

	/**
	 * After the connection was dropped: the connection each statement was
	 * sent from, while the batch's transaction was still open.
	 *
	 * @var int[]
	 */
	private $after_drop = [];

	/**
	 * $wpdb->reconnect_retries before the test.
	 *
	 * @var int
	 */
	private $retries = 5;

	/**
	 * Hooks fired, by name.
	 *
	 * @var array<string, int>
	 */
	private $fired = [];

	/**
	 * Real transactions; 8 published posts, each with an unattached featured
	 * image; rendered-page checking on, answered with a page showing it.
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		remove_filter( 'query', 'tsmlt_tests_batch_transaction_as_savepoint' );
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- End the test-case transaction.
		$wpdb->query( 'SET autocommit = 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Real transactions in this class.

		$this->retries           = (int) $wpdb->reconnect_retries;
		$wpdb->reconnect_retries = 7;
		$this->drop              = '';
		$this->dropped           = 0;
		$this->after_drop        = [];

		self::clean_scan_state();
		add_filter( 'pre_http_request', [ $this, 'serve_page' ], 10, 3 );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_true' );
		add_filter( 'query', [ $this, 'interfere' ] );
		foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta', 'attachment_updated' ] as $hook ) {
			add_action( $hook, [ $this, 'count_hook' ] );
		}

		for ( $i = 0; $i < 8; $i++ ) {
			$post  = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
			$image = (int) self::factory()->attachment->create(
				[
					'file'           => 'txn/image-' . $i . '.jpg',
					'post_mime_type' => 'image/jpeg',
					'post_parent'    => 0,
				]
			);
			set_post_thumbnail( $post, $image );
			$this->created[]     = $post;
			$this->created[]     = $image;
			$this->attachments[] = $image;
		}
		$this->fired = [];
	}

	/**
	 * Delete the committed fixtures and scan state; restore the savepoint
	 * filter.
	 */
	public function tear_down() {
		global $wpdb;
		remove_filter( 'query', [ $this, 'interfere' ] );
		remove_filter( 'query', [ $this, 'break_begin' ] );
		// Reconnect if a test killed or replaced the connection.
		$wpdb->reconnect_retries = $this->retries;
		$wpdb->check_connection( false );
		if ( $this->link ) {
			$this->link->close();
			$this->link = null;
		}
		foreach ( $this->created as $id ) {
			wp_delete_post( $id, true );
		}
		self::clean_scan_state();
		foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta', 'attachment_updated' ] as $hook ) {
			remove_action( $hook, [ $this, 'count_hook' ] );
		}
		remove_filter( 'pre_http_request', [ $this, 'serve_page' ] );
		remove_filter( 'tsmlt_scan_permalink_enabled_default', '__return_true' );
		remove_filter( 'tsmlt_used_where_scannable_post_types', [ $this, 'fail_batch' ] );
		add_filter( 'query', 'tsmlt_tests_batch_transaction_as_savepoint' );
		parent::tear_down();
	}

	/**
	 * No status, lock, scheduled batch, usage records or fingerprints.
	 */
	private static function clean_scan_state() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Test clean-up.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)", self::STATUS, ScanBatchLock::OPTION ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s)", UsedWhereScanner::META_KEY, self::FINGERPRINT, '_tsmlt_usage_tracked' ) );
		// phpcs:enable
		delete_transient( UsedWhereScanner::OPTION_SCAN_CACHE_KEY );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		wp_cache_flush();
	}

	/**
	 * Answer a permalink fetch with a page showing every fixture image.
	 *
	 * @param mixed  $response Response.
	 * @param array  $args     Request arguments.
	 * @param string $url      URL.
	 *
	 * @return array
	 */
	public function serve_page( $response, $args, $url ) {
		$html = '<html><body>';
		foreach ( $this->attachments as $id ) {
			$html .= '<img src="' . esc_url( (string) wp_get_attachment_url( $id ) ) . '">';
		}
		return [
			'headers'  => [],
			'body'     => $html . '</body></html>',
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Count a hook.
	 */
	public function count_hook() {
		$this->fired[ current_filter() ] = ( $this->fired[ current_filter() ] ?? 0 ) + 1;
	}

	/**
	 * Make the batch fail.
	 *
	 * @throws RuntimeException Always.
	 */
	public function fail_batch() {
		throw new RuntimeException( 'Batch failed.' );
	}

	/**
	 * Follow the batch transaction; send the competing statement at its
	 * point, or break a statement.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function interfere( $query ) {
		global $wpdb;
		if ( 0 !== $this->dropped && BatchTransaction::instance()->is_active() ) {
			$this->after_drop[] = $wpdb->dbh instanceof mysqli ? (int) mysqli_thread_id( $wpdb->dbh ) : 0;
		}
		// Without a batch transaction (the implementation before it, for
		// comparison), the same point is when the batch is about to write its
		// first usage record.
		if ( '' !== $this->when && ! $this->sent && ! $this->txn['open']
			&& preg_match( '/^\s*(INSERT|UPDATE)\b/i', $query ) && false !== strpos( $query, UsedWhereScanner::META_KEY ) ) {
			$this->sent = true;
			if ( 'after_claim' === $this->when ) {
				$this->link()->query( $this->competing_sql(), MYSQLI_ASYNC );
				$this->waited = ! self::finished( $this->link, 1.0 );
			} else {
				$this->link()->query( $this->competing_sql() );
			}
			return $query;
		}
		if ( BatchTransaction::BEGIN === $query ) {
			$this->txn = [
				'open'    => true,
				'claimed' => false,
			];
			if ( 'before_claim' === $this->when && ! $this->sent ) {
				$this->sent = true;
				$this->link()->query( $this->competing_sql() );
			}
			return $query;
		}
		if ( BatchTransaction::COMMIT === $query || BatchTransaction::ROLLBACK === $query ) {
			$this->txn['open'] = false;
			return $query;
		}
		if ( ! $this->txn['open'] ) {
			return $query;
		}
		if ( ! $this->txn['claimed'] ) {
			// The transaction's first statement: the status claim.
			$this->txn['claimed'] = true;
			return $query;
		}
		if ( 'after_claim' === $this->when && ! $this->sent ) {
			$this->sent = true;
			$this->link()->query( $this->competing_sql(), MYSQLI_ASYNC );
			$this->waited = ! self::finished( $this->link, 1.0 );
		}
		if ( '' !== $this->drop && false !== strpos( $query, self::FINGERPRINT ) ) {
			$this->drop_connection();
			return $query;
		}
		if ( '' !== $this->break && false !== strpos( $query, $this->break ) ) {
			$this->break = '';
			return 'INSERT INTO tsmlt_no_such_table VALUES (1)';
		}
		return $query;
	}

	/**
	 * Drop $wpdb's connection, by $this->drop: close it and open a new one
	 * (as a reconnect does), or kill it from the second connection.
	 */
	private function drop_connection() {
		global $wpdb;
		$this->dropped = (int) mysqli_thread_id( $wpdb->dbh );
		$mode          = $this->drop;
		$this->drop    = '';
		if ( 'reconnect' === $mode ) {
			$wpdb->close();
			$wpdb->db_connect();
			return;
		}
		$this->link()->query( 'KILL CONNECTION ' . $this->dropped );
		// Wait until the server has really ended it.
		for ( $i = 0; $i < 50; $i++ ) {
			$left = $this->link()->query( 'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID = ' . $this->dropped )->fetch_row();
			if ( '0' === (string) $left[0] ) {
				break;
			}
			usleep( 100000 );
		}
	}

	/**
	 * Make the transaction's START fail.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function break_begin( $query ) {
		return BatchTransaction::BEGIN === $query ? 'START TRANSACTION tsmlt_invalid' : $query;
	}

	/**
	 * The competing statement, with `{expected}` replaced by the committed
	 * status value as the second connection reads it now (before the
	 * batch's uncommitted claim).
	 *
	 * @return string
	 */
	private function competing_sql(): string {
		global $wpdb;
		if ( false === strpos( $this->competitor, '{expected}' ) ) {
			return $this->competitor;
		}
		$result = $this->link()->query( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::STATUS ) );
		$row    = $result->fetch_row();
		return str_replace( '{expected}', "'" . $this->link->real_escape_string( (string) $row[0] ) . "'", $this->competitor );
	}

	/**
	 * The second connection, with a 10 second lock wait.
	 *
	 * @return mysqli
	 */
	private function link(): mysqli {
		if ( ! $this->link ) {
			$host = DB_HOST;
			$port = 3306;
			if ( preg_match( '/^(.+):(\d+)$/', $host, $m ) ) {
				$host = $m[1];
				$port = (int) $m[2];
			}
			$this->link = mysqli_init();
			$this->link->real_connect( $host, DB_USER, DB_PASSWORD, DB_NAME, $port );
			$this->link->query( 'SET SESSION innodb_lock_wait_timeout = 10' );
		}
		return $this->link;
	}

	/**
	 * Whether an asynchronous query on a connection finished within a time.
	 *
	 * @param mysqli $link    Connection.
	 * @param float  $seconds Seconds to wait.
	 *
	 * @return bool
	 */
	private static function finished( mysqli $link, float $seconds ): bool {
		$read   = [ $link ];
		$error  = [ $link ];
		$reject = [ $link ];
		return mysqli_poll( $read, $error, $reject, 0, (int) ( $seconds * 1000000 ) ) > 0;
	}

	/**
	 * Wait for the asynchronous competing statement.
	 *
	 * @return int Rows it changed.
	 */
	private function reap(): int {
		$this->assertTrue( self::finished( $this->link, 15.0 ), 'The competing statement finished.' );
		$this->link->reap_async_query();
		$this->assertSame( '', $this->link->error, 'The competing statement succeeded.' );
		wp_cache_flush();
		return (int) $this->link->affected_rows;
	}

	/**
	 * Run a batch with a competing statement.
	 *
	 * @param int    $offset     Offset.
	 * @param string $when       'before_claim' or 'after_claim'.
	 * @param string $competitor SQL.
	 */
	private function run_batch_with( int $offset, string $when, string $competitor ) {
		$this->when       = $when;
		$this->competitor = $competitor;
		$this->sent       = false;
		$this->waited     = false;
		UsedWhereScanner::instance()->run_tick_batch( $offset );
		$this->when = '';
		$this->assertTrue( $this->sent, 'Precondition: the competing statement was sent.' );
	}

	/**
	 * SQL writing a status row value, as update_option() does.
	 *
	 * @param array $status Status.
	 *
	 * @return string
	 */
	private static function write_status_sql( array $status ): string {
		global $wpdb;
		return $wpdb->remove_placeholder_escape( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", maybe_serialize( $status ), self::STATUS ) );
	}

	/**
	 * SQL deleting the status row, as delete_option() does.
	 *
	 * @return string
	 */
	private static function delete_status_sql(): string {
		global $wpdb;
		return $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", self::STATUS );
	}

	/**
	 * Start a scan and leave its first batch unscheduled.
	 *
	 * @return array Status after the start.
	 */
	private static function start(): array {
		UsedWhereScanner::instance()->start_scheduled_scan();
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		return self::stored_status();
	}

	/**
	 * The status in the database.
	 *
	 * @return mixed The status, or null.
	 */
	private static function stored_status() {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::STATUS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
		return null === $value ? null : maybe_unserialize( $value );
	}

	/**
	 * Rows of a meta key, in order.
	 *
	 * @param string $key Meta key.
	 *
	 * @return array
	 */
	private static function meta_rows( string $key ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id", $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
	}

	/**
	 * Fixture attachments with a parent, in the database.
	 *
	 * @return int
	 */
	private function attached(): int {
		global $wpdb;
		$ids = implode( ',', array_map( 'intval', $this->attachments ) );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID IN ({$ids}) AND post_parent <> 0" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test inspection of integer IDs.
	}

	/**
	 * Offsets of scheduled batches, sorted.
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
	 * Run batches until the scan stops being active.
	 *
	 * @return array Final status.
	 */
	private static function run_to_end(): array {
		for ( $i = 0; $i < 10; $i++ ) {
			$status = self::stored_status();
			if ( ! in_array( $status['state'] ?? '', [ 'queued', 'running' ], true ) ) {
				break;
			}
			wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
			UsedWhereScanner::instance()->run_tick_batch( (int) $status['next_offset'] );
		}
		return self::stored_status();
	}

	/**
	 * Precondition: the tables are InnoDB and transactions are real here.
	 */
	public function test_real_transactions_on_innodb() {
		global $wpdb;
		$this->assertSame( [], BatchTransaction::unsupported_tables() );
		$this->assertSame( '1', (string) $wpdb->get_var( 'SELECT @@autocommit' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test precondition.
		$this->assertFalse( has_filter( 'query', 'tsmlt_tests_batch_transaction_as_savepoint' ) );
	}

	/**
	 * A normal scan, in real transactions, saves records, fingerprints and
	 * parents, fires the meta and attachment hooks after commit, and
	 * completes.
	 */
	public function test_normal_scan_completes() {
		$start = self::start();

		UsedWhereScanner::instance()->run_tick_batch( 0 );
		$first = self::stored_status();
		$this->assertSame( 'running', $first['state'] );
		$this->assertSame( 5, (int) $first['next_offset'] );
		$this->assertSame( [ 5 ], self::ticks() );
		$this->assertSame( 5, count( self::meta_rows( self::FINGERPRINT ) ) );

		$status = self::run_to_end();

		$this->assertSame( 'complete', $status['state'] );
		$this->assertSame( $start['scan_id'], $status['scan_id'] );
		$this->assertSame( (int) $status['total'], (int) $status['processed'] );
		$this->assertSame( 8, count( self::meta_rows( UsedWhereScanner::META_KEY ) ) );
		$this->assertSame( 8, count( self::meta_rows( self::FINGERPRINT ) ) );
		$this->assertSame( 8, $this->attached() );
		$this->assertGreaterThanOrEqual( 8, $this->fired['added_post_meta'] ?? 0 );
		$this->assertSame( 8, $this->fired['attachment_updated'] ?? 0 );
		$this->assertSame( [], self::ticks() );
		$this->assertNull( ScanBatchLock::instance()->read_lock(), 'The lock is released.' );
	}

	/**
	 * Cancel's status write, sent while the batch's transaction holds the
	 * status row, waits for its COMMIT; afterwards the scan is cancelled and
	 * the old scan's next batch writes nothing.
	 */
	public function test_cancel_waits_for_the_batch_then_stops_the_scan() {
		$start                = self::start();
		$cancelled            = $start;
		$cancelled['state']   = 'cancelled';

		$this->run_batch_with( 0, 'after_claim', self::write_status_sql( $cancelled ) );

		$this->assertTrue( $this->waited, 'Cancel waited for the batch transaction.' );
		$this->assertSame( 1, $this->reap() );
		$this->assertSame( 'cancelled', self::stored_status()['state'] );
		$records      = self::meta_rows( UsedWhereScanner::META_KEY );
		$fingerprints = self::meta_rows( self::FINGERPRINT );
		$this->assertSame( 5, count( $fingerprints ), 'The batch committed before the cancel.' );

		UsedWhereScanner::instance()->cancel_scheduled_scan();
		UsedWhereScanner::instance()->run_tick_batch( 5 );

		$this->assertSame( 'cancelled', self::stored_status()['state'] );
		$this->assertSame( $records, self::meta_rows( UsedWhereScanner::META_KEY ) );
		$this->assertSame( $fingerprints, self::meta_rows( self::FINGERPRINT ) );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * Cancel stored just before the batch saves: the batch saves nothing.
	 */
	public function test_cancel_before_the_save_leaves_nothing() {
		$start              = self::start();
		$cancelled          = $start;
		$cancelled['state'] = 'cancelled';

		$this->run_batch_with( 0, 'before_claim', self::write_status_sql( $cancelled ) );

		wp_cache_flush();
		$this->assertSame( 'cancelled', self::stored_status()['state'] );
		$this->assertSame( [], self::meta_rows( UsedWhereScanner::META_KEY ) );
		$this->assertSame( [], self::meta_rows( self::FINGERPRINT ) );
		$this->assertSame( 0, $this->attached() );
		$this->assertSame( [], self::ticks() );
		$this->assertArrayNotHasKey( 'added_post_meta', $this->fired );
	}

	/**
	 * Clear Results' status delete, sent while the batch's transaction holds
	 * the status row, waits for its COMMIT; the rest of Clear then removes
	 * every usage record and fingerprint, and the old scan's next batch
	 * writes nothing.
	 */
	public function test_clear_waits_for_the_batch_then_removes_everything() {
		self::start();

		$this->run_batch_with( 0, 'after_claim', self::delete_status_sql() );

		$this->assertTrue( $this->waited, 'Clear waited for the batch transaction.' );
		$this->assertSame( 1, $this->reap() );
		$this->assertNotEmpty( self::meta_rows( UsedWhereScanner::META_KEY ), 'The batch committed before the clear.' );

		UsedWhereScanner::instance()->clear_scan();
		UsedWhereScanner::instance()->run_tick_batch( 5 );

		$this->assertNull( self::stored_status() );
		$this->assertSame( [], self::meta_rows( UsedWhereScanner::META_KEY ) );
		$this->assertSame( [], self::meta_rows( self::FINGERPRINT ) );
		$this->assertFalse( get_transient( UsedWhereScanner::OPTION_SCAN_CACHE_KEY ) );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * Clear Results stored while the batch processed, before it saves: the
	 * batch saves nothing and recreates no status.
	 */
	public function test_clear_before_the_save_leaves_nothing() {
		self::start();

		$this->run_batch_with( 0, 'before_claim', self::delete_status_sql() );

		wp_cache_flush();
		$this->assertNull( self::stored_status() );
		$this->assertSame( [], self::meta_rows( UsedWhereScanner::META_KEY ) );
		$this->assertSame( [], self::meta_rows( self::FINGERPRINT ) );
		$this->assertSame( 0, $this->attached() );
		$this->assertFalse( get_transient( UsedWhereScanner::OPTION_SCAN_CACHE_KEY ) );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * Re-scan stored just before the old batch saves: the new scan's status
	 * is exactly as written and the old batch saves nothing.
	 */
	public function test_rescan_before_the_save_is_kept() {
		$old                = self::start();
		$new                = $old;
		$new['scan_id']     = wp_generate_uuid4();
		$new['state']       = 'queued';
		$new['started_at']  = current_time( 'mysql' );

		$this->run_batch_with( 0, 'before_claim', self::write_status_sql( $new ) );

		wp_cache_flush();
		$this->assertSame( $new, self::stored_status() );
		$this->assertSame( [], self::meta_rows( UsedWhereScanner::META_KEY ) );
		$this->assertSame( [], self::meta_rows( self::FINGERPRINT ) );
		$this->assertSame( 0, $this->attached() );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * Re-scan while the old batch is saving: its first write (cancelling the
	 * old scan) waits for the batch's COMMIT; the rest of the Re-scan then
	 * wipes the old batch's records and fingerprints, and the old scan's next
	 * batch does not touch the new scan.
	 */
	public function test_rescan_during_the_save_is_not_overwritten() {
		$old                = self::start();
		$cancelled          = $old;
		$cancelled['state'] = 'cancelled';

		$this->run_batch_with( 0, 'after_claim', self::write_status_sql( $cancelled ) );

		$this->assertTrue( $this->waited, 'The Re-scan waited for the batch transaction.' );
		$this->assertSame( 1, $this->reap() );
		$this->assertNotEmpty( self::meta_rows( UsedWhereScanner::META_KEY ) );

		$new = self::start();
		UsedWhereScanner::instance()->run_tick_batch( 5 );

		$this->assertNotSame( $old['scan_id'], $new['scan_id'] );
		$this->assertSame( $new, self::stored_status(), 'The new scan\'s status is exactly as its start wrote it.' );
		$this->assertSame( [], self::meta_rows( UsedWhereScanner::META_KEY ) );
		$this->assertSame( [], self::meta_rows( self::FINGERPRINT ) );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * Re-scan stored just before the last batch saves: the completion
	 * cleanup does not run and no record changes.
	 */
	public function test_rescan_before_the_last_save_skips_the_cleanup() {
		self::start();
		UsedWhereScanner::instance()->run_tick_batch( 0 );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		$records       = self::meta_rows( UsedWhereScanner::META_KEY );
		$new           = self::stored_status();
		$new['scan_id'] = wp_generate_uuid4();
		$new['state']   = 'queued';

		$this->run_batch_with( 5, 'before_claim', self::write_status_sql( $new ) );

		wp_cache_flush();
		$this->assertSame( $new, self::stored_status() );
		$this->assertSame( $records, self::meta_rows( UsedWhereScanner::META_KEY ), 'No record saved or cleaned up.' );
		$this->assertSame( 5, count( self::meta_rows( self::FINGERPRINT ) ) );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * Cancel stored just before the last batch saves: the completion cleanup
	 * does not run and the scan stays cancelled.
	 */
	public function test_cancel_before_the_last_save_skips_the_cleanup() {
		self::start();
		UsedWhereScanner::instance()->run_tick_batch( 0 );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		$records            = self::meta_rows( UsedWhereScanner::META_KEY );
		$cancelled          = self::stored_status();
		$cancelled['state'] = 'cancelled';

		$this->run_batch_with( 5, 'before_claim', self::write_status_sql( $cancelled ) );

		wp_cache_flush();
		$status = self::stored_status();
		$this->assertSame( 'cancelled', $status['state'] );
		$this->assertSame( 5, (int) $status['processed'] );
		$this->assertSame( $records, self::meta_rows( UsedWhereScanner::META_KEY ) );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * Two batches for the same scan and offset: a second claim of the same
	 * status, sent while the first batch holds the row, waits and then
	 * changes nothing; a batch whose offset another worker already saved
	 * saves nothing; and the duplicate tick run afterwards changes nothing.
	 */
	public function test_duplicate_batches_save_once() {
		global $wpdb;
		$start = self::start();
		$other = $start;

		// The second worker's claim: the status value it read (the one this
		// batch's claim replaced, still the committed one), its own result.
		$other['state']       = 'running';
		$other['next_offset'] = 5;
		$other['processed']   = 5;
		$claim                = $wpdb->remove_placeholder_escape(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = ",
				maybe_serialize( $other ),
				self::STATUS
			)
		) . '{expected}';
		$this->run_batch_with( 0, 'after_claim', $claim );

		$this->assertTrue( $this->waited, 'The second claim waited for the first batch.' );
		$this->assertSame( 0, $this->reap(), 'The second claim changed nothing.' );
		$records = self::meta_rows( UsedWhereScanner::META_KEY );
		$status  = self::stored_status();
		$this->assertSame( 5, (int) $status['next_offset'] );

		// A duplicate tick for offset 0 after the first one saved.
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		UsedWhereScanner::instance()->run_tick_batch( 0 );

		$this->assertSame( $records, self::meta_rows( UsedWhereScanner::META_KEY ) );
		$this->assertSame( $status, self::stored_status() );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * Another worker saves the same offset while this batch processes: this
	 * batch's save finds the offset taken and saves nothing.
	 */
	public function test_batch_whose_offset_was_saved_meanwhile_saves_nothing() {
		$start                = self::start();
		$other                = $start;
		$other['state']       = 'running';
		$other['next_offset'] = 5;
		$other['processed']   = 5;

		$this->run_batch_with( 0, 'before_claim', self::write_status_sql( $other ) );

		wp_cache_flush();
		$this->assertSame( $other, self::stored_status() );
		$this->assertSame( [], self::meta_rows( UsedWhereScanner::META_KEY ) );
		$this->assertSame( [], self::meta_rows( self::FINGERPRINT ) );
		$this->assertSame( 0, $this->attached() );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * A database error after usage records and parents were written rolls
	 * all of it back: no records, fingerprints or parents, no hooks, the
	 * status as the batch's start left it, nothing scheduled, caches clean.
	 */
	public function test_database_error_rolls_back_everything() {
		global $wpdb;
		$start       = self::start();
		$this->break = self::FINGERPRINT;
		$this->when  = '';

		$suppressed = $wpdb->suppress_errors( true );
		UsedWhereScanner::instance()->run_tick_batch( 0 );
		$wpdb->suppress_errors( $suppressed );

		$this->assertSame( '', $this->break, 'Precondition: the fingerprint write was broken.' );
		$status = self::stored_status();
		$this->assertSame( 'running', $status['state'] );
		$this->assertSame( 0, (int) $status['next_offset'] );
		$this->assertSame( '', $status['last_error'] );
		$this->assertSame( $start['scan_id'], $status['scan_id'] );
		$this->assertSame( [], self::meta_rows( UsedWhereScanner::META_KEY ) );
		$this->assertSame( [], self::meta_rows( self::FINGERPRINT ) );
		$this->assertSame( 0, $this->attached() );
		$this->assertSame( '', get_post_meta( $this->attachments[0], UsedWhereScanner::META_KEY, true ), 'No cached record.' );
		$this->assertSame( 0, (int) get_post( $this->attachments[0] )->post_parent, 'No cached parent.' );
		$this->assertArrayNotHasKey( 'attachment_updated', $this->fired );
		$this->assertArrayNotHasKey( 'added_post_meta', $this->fired );
		$this->assertSame( [], self::ticks() );
		$this->assertNull( ScanBatchLock::instance()->read_lock(), 'The lock is released.' );
	}

	/**
	 * An exception in the completion cleanup (which runs after the last batch
	 * committed) rolls the cleanup step back and marks the scan as failed,
	 * not complete; the batches' records stay as they were saved.
	 */
	public function test_failure_in_the_cleanup_rolls_back_and_reports_an_error() {
		self::start();
		UsedWhereScanner::instance()->run_tick_batch( 0 );
		UsedWhereScanner::instance()->run_tick_batch( 5 );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		$this->assertSame( 'reclassify', self::stored_status()['cleanup_stage'], 'Precondition: every post processed, cleanup pending.' );
		$records = self::meta_rows( UsedWhereScanner::META_KEY );
		add_filter( 'tsmlt_used_where_sitewide_pervasive_ratio', [ $this, 'fail_batch' ] );

		UsedWhereScanner::instance()->run_tick_batch( 8 );

		remove_filter( 'tsmlt_used_where_sitewide_pervasive_ratio', [ $this, 'fail_batch' ] );
		$status = self::stored_status();
		$this->assertSame( 'error', $status['state'] );
		$this->assertSame( 'Batch failed.', $status['last_error'] );
		$this->assertFalse( $status['complete'] );
		$this->assertSame( 'reclassify', $status['cleanup_stage'] );
		$this->assertSame( $records, self::meta_rows( UsedWhereScanner::META_KEY ), 'The cleanup changed no record.' );
		$this->assertSame( 8, count( self::meta_rows( self::FINGERPRINT ) ) );
		$this->assertSame( 8, $this->attached() );
		$this->assertSame( [], self::ticks() );
	}

	/**
	 * The reconnect setting is 0 while the transaction is open and restored
	 * after COMMIT; the statement guard is removed.
	 */
	public function test_reconnect_setting_restored_after_commit() {
		global $wpdb;
		$transaction = BatchTransaction::instance();

		$this->assertTrue( $transaction->begin() );
		$this->assertSame( 0, (int) $wpdb->reconnect_retries, 'No reconnect while the transaction is open.' );
		$this->assertTrue( $transaction->commit() );

		$this->assertSame( 7, (int) $wpdb->reconnect_retries );
		$this->assertFalse( has_filter( 'query', [ $transaction, 'guard_query' ] ) );
		$this->assertFalse( $transaction->is_active() );
	}

	/**
	 * The reconnect setting is restored after ROLLBACK, which reports that it
	 * ran.
	 */
	public function test_reconnect_setting_restored_after_rollback() {
		global $wpdb;
		$transaction = BatchTransaction::instance();

		$this->assertTrue( $transaction->begin() );
		$this->assertTrue( $transaction->rollback() );

		$this->assertSame( 7, (int) $wpdb->reconnect_retries );
		$this->assertFalse( has_filter( 'query', [ $transaction, 'guard_query' ] ) );
	}

	/**
	 * A START that fails leaves no transaction, and the reconnect setting
	 * and statement guard as they were.
	 */
	public function test_reconnect_setting_restored_when_begin_fails() {
		global $wpdb;
		$transaction = BatchTransaction::instance();
		add_filter( 'query', [ $this, 'break_begin' ] );

		$suppressed = $wpdb->suppress_errors( true );
		$started    = $transaction->begin();
		$wpdb->suppress_errors( $suppressed );

		$this->assertFalse( $started );
		$this->assertFalse( $transaction->is_active() );
		$this->assertSame( 7, (int) $wpdb->reconnect_retries );
		$this->assertFalse( has_filter( 'query', [ $transaction, 'guard_query' ] ) );
	}

	/**
	 * A second begin() while a transaction is open is refused (it would
	 * commit the open one) and keeps the saved reconnect setting.
	 */
	public function test_second_begin_is_refused() {
		global $wpdb;
		$transaction = BatchTransaction::instance();

		$this->assertTrue( $transaction->begin() );
		$this->assertFalse( $transaction->begin() );
		$this->assertTrue( $transaction->is_active() );
		$transaction->rollback();

		$this->assertSame( 7, (int) $wpdb->reconnect_retries );
	}

	/**
	 * A batch that fails with an exception (rolled back, scan marked failed)
	 * restores the reconnect setting.
	 */
	public function test_reconnect_setting_restored_after_a_failed_batch() {
		global $wpdb;
		self::start();
		add_filter( 'tsmlt_used_where_scannable_post_types', [ $this, 'fail_batch' ] );

		UsedWhereScanner::instance()->run_tick_batch( 0 );

		$this->assertSame( 'error', self::stored_status()['state'] );
		$this->assertSame( 7, (int) $wpdb->reconnect_retries );
		$this->assertFalse( BatchTransaction::instance()->is_active() );
	}

	/**
	 * A different connection before COMMIT (simulated reconnect): COMMIT
	 * and ROLLBACK are not sent on it, nothing is saved (the server
	 * discarded the closed connection's transaction), no hook fires, and the
	 * reconnect setting is restored.
	 */
	public function test_changed_connection_before_commit_fails() {
		global $wpdb;
		$transaction = BatchTransaction::instance();
		$this->assertTrue( $transaction->begin() );
		$transaction->put_meta( $this->attachments[0], self::FINGERPRINT, 'written-before-the-reconnect' );

		$wpdb->close();
		$wpdb->db_connect();
		$committed = $transaction->commit();

		$this->assertFalse( $committed );
		$this->assertFalse( $transaction->is_active() );
		$this->assertSame( [], self::meta_rows( self::FINGERPRINT ) );
		$this->assertArrayNotHasKey( 'added_post_meta', $this->fired );
		$this->assertSame( 7, (int) $wpdb->reconnect_retries );
		$this->assertFalse( has_filter( 'query', [ $transaction, 'guard_query' ] ) );
	}

	/**
	 * ROLLBACK on a different connection is not sent and is not reported as
	 * done.
	 */
	public function test_rollback_after_a_changed_connection_is_not_reported_as_done() {
		global $wpdb;
		$transaction = BatchTransaction::instance();
		$this->assertTrue( $transaction->begin() );

		$wpdb->close();
		$wpdb->db_connect();

		$this->assertFalse( $transaction->rollback() );
		$this->assertSame( 7, (int) $wpdb->reconnect_retries );
	}

	/**
	 * A reconnect in the middle of the save (simulated: the connection is
	 * closed and a new one opened, after usage records and parents were
	 * written): no later statement runs on the new connection, nothing is
	 * saved, no hook fires, nothing is scheduled, and the scan is left as
	 * the batch's start left it, for stall recovery.
	 */
	public function test_reconnect_during_the_save_fails_closed() {
		global $wpdb;
		$start      = self::start();
		$this->drop = 'reconnect';

		UsedWhereScanner::instance()->run_tick_batch( 0 );

		$this->assertNotSame( 0, $this->dropped, 'Precondition: the connection was replaced during the save.' );
		$this->assertNotSame( $this->dropped, (int) mysqli_thread_id( $wpdb->dbh ) );
		$this->assertSame( [], array_diff( $this->after_drop, [ $this->dropped ] ), 'No statement of the open transaction ran on the new connection.' );
		$this->assert_nothing_saved( $start );
		$this->assertNull( ScanBatchLock::instance()->read_lock(), 'The lock is released.' );
	}

	/**
	 * The batch's connection killed in the middle of the save (genuine
	 * connection loss): $wpdb does not reconnect, nothing is saved, no hook
	 * fires, nothing is scheduled, and the scan is left as the batch's start
	 * left it, for stall recovery.
	 */
	public function test_killed_connection_during_the_save_fails_closed() {
		global $wpdb;
		$start      = self::start();
		$this->drop = 'kill';

		$suppressed = $wpdb->suppress_errors( true );
		try {
			UsedWhereScanner::instance()->run_tick_batch( 0 );
		} catch ( WPDieException $e ) {
			// WordPress ends the request on a lost connection it may not
			// reconnect; in tests wp_die() throws instead.
			unset( $e );
		}
		$wpdb->suppress_errors( $suppressed );

		$this->assertNotSame( 0, $this->dropped, 'Precondition: the connection was killed during the save.' );
		$this->assertSame( [], array_diff( $this->after_drop, [ $this->dropped ] ), 'No statement of the open transaction ran on another connection.' );
		$this->assert_nothing_saved( $start, false );
	}

	/**
	 * Nothing of the batch saved: no records, fingerprints, parents, hooks
	 * or scheduled batch; the status as the batch's start left it; the
	 * reconnect setting restored.
	 *
	 * @param array $start   Status after the scan's start.
	 * @param bool  $running Whether the scan must still be running (false:
	 *                       it may also have been marked as failed).
	 */
	private function assert_nothing_saved( array $start, bool $running = true ) {
		global $wpdb;
		wp_cache_flush();
		$status = self::stored_status();
		if ( $running ) {
			$this->assertSame( 'running', $status['state'] );
		} else {
			$this->assertContains( $status['state'], [ 'running', 'error' ] );
		}
		$this->assertSame( 0, (int) $status['next_offset'] );
		$this->assertSame( $start['scan_id'], $status['scan_id'] );
		$this->assertNotSame( '', $status['last_tick_at'], 'Stall recovery can re-arm it.' );
		$this->assertSame( [], self::meta_rows( UsedWhereScanner::META_KEY ) );
		$this->assertSame( [], self::meta_rows( self::FINGERPRINT ) );
		$this->assertSame( 0, $this->attached() );
		$this->assertArrayNotHasKey( 'added_post_meta', $this->fired );
		$this->assertArrayNotHasKey( 'attachment_updated', $this->fired );
		$this->assertSame( [], self::ticks() );
		$this->assertSame( 7, (int) $wpdb->reconnect_retries );
		$this->assertFalse( BatchTransaction::instance()->is_active() );
	}
}
