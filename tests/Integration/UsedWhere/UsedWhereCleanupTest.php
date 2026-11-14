<?php
/**
 * The Used Where completion cleanup runs after the last posts, while the
 * scan stays running: one `reclassify` step, then `reconcile` chunks of
 * UsedWhereScanner::CLEANUP_CHUNK usage records, each committed in its own
 * short transaction that claims the scan's status (stage and cursor) first
 * and writes each record only over the value it read. The scan completes
 * with the last chunk.
 *
 * Transactions are real here (autocommit on, the bootstrap's savepoint
 * filter removed; fixtures are committed and deleted in tear_down()). A
 * `query` filter follows the cleanup's transactions (1: reclassify, 2…:
 * reconcile chunks) and acts at a point of one: when it starts (after the
 * step read what it changes), once it holds the status claim, or at its
 * first record write. Competing requests run there either in this process,
 * through the plugin's own code (a page view, a post save), or on a second
 * MySQL connection — synchronously, or asynchronously while the cleanup
 * holds its locks, to check that the statement waits. The second connection
 * is real database concurrency, not parallel PHP. Outgoing HTTP is blocked.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\UsedWhere\BatchTransaction;
use TinySolutions\mlt\Modules\UsedWhere\ScanBatchLock;
use TinySolutions\mlt\Modules\UsedWhere\UsageClassifier;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereCleanupTest
 */
class UsedWhereCleanupTest extends WP_UnitTestCase {

	/**
	 * Status option.
	 *
	 * @var string
	 */
	const STATUS = 'tsmlt_used_where_scan_status';

	/**
	 * Extra usage records for multi-chunk tests.
	 *
	 * @var int
	 */
	const EXTRA = 1200;

	/**
	 * Posts and attachments created by the test.
	 *
	 * @var int[]
	 */
	private $created = [];

	/**
	 * Published posts.
	 *
	 * @var int[]
	 */
	private $posts = [];

	/**
	 * Images: 'pervasive', 'mixed', 'owned', 'unused'.
	 *
	 * @var int[]
	 */
	private $images = [];

	/**
	 * Second database connection.
	 *
	 * @var mysqli|null
	 */
	private $link = null;

	/**
	 * Cleanup transactions started.
	 *
	 * @var int
	 */
	private $txn = 0;

	/**
	 * Whether a cleanup transaction is open, and whether it holds the claim.
	 *
	 * @var array
	 */
	private $open = [
		'open'    => false,
		'claimed' => false,
	];

	/**
	 * Record writes per cleanup transaction.
	 *
	 * @var int[]
	 */
	private $row_writes = [];

	/**
	 * What to do: transaction number, point ('begin', 'claimed', 'row'),
	 * action, and its SQL.
	 *
	 * @var array
	 */
	private $act = [];

	/**
	 * Whether the action ran, and whether its asynchronous statement was
	 * still waiting one second later.
	 *
	 * @var array
	 */
	private $acted = [
		'ran'    => false,
		'waited' => false,
	];

	/**
	 * Whether the test is running its own queries from the filter.
	 *
	 * @var bool
	 */
	private $in_filter = false;

	/**
	 * Status writes: state, stage and cursor of each.
	 *
	 * @var array[]
	 */
	private $writes = [];

	/**
	 * Image whose next usage-record write outside a transaction gets a page
	 * view just before it (0: none).
	 *
	 * @var int
	 */
	private $view_before_write = 0;

	/**
	 * $wpdb->reconnect_retries before the test.
	 *
	 * @var int
	 */
	private $retries = 5;

	/**
	 * Real transactions; 10 posts and four images.
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		remove_filter( 'query', 'tsmlt_tests_batch_transaction_as_savepoint' );
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- End the test-case transaction.
		$wpdb->query( 'SET autocommit = 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Real transactions in this class.
		$this->retries = (int) $wpdb->reconnect_retries;

		self::clean_scan_state();
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );

		for ( $i = 0; $i < 10; $i++ ) {
			$this->posts[]   = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
			$this->created[] = end( $this->posts );
		}
		foreach ( [ 'pervasive', 'mixed', 'owned', 'unused' ] as $name ) {
			$this->images[ $name ] = (int) self::factory()->attachment->create(
				[
					'file'           => 'cleanup/' . $name . '.jpg',
					'post_mime_type' => 'image/jpeg',
					'post_date'      => '2020-01-01 00:00:00',
				]
			);
			$this->created[]       = $this->images[ $name ];
		}
		add_filter( 'query', [ $this, 'track' ] );
		add_action( 'update_option_' . self::STATUS, [ $this, 'record_write' ], 10, 2 );
	}

	/**
	 * Delete the committed fixtures and scan state.
	 */
	public function tear_down() {
		global $wpdb;
		remove_filter( 'query', [ $this, 'track' ] );
		remove_action( 'update_option_' . self::STATUS, [ $this, 'record_write' ], 10 );
		if ( $this->link ) {
			$this->link->close();
			$this->link = null;
		}
		$wpdb->reconnect_retries = $this->retries;
		$wpdb->check_connection( false );
		foreach ( $this->created as $id ) {
			wp_delete_post( $id, true );
		}
		self::clean_scan_state();
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		remove_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		add_filter( 'query', 'tsmlt_tests_batch_transaction_as_savepoint' );
		parent::tear_down();
	}

	/**
	 * No status, lock, scheduled batch or usage records.
	 */
	private static function clean_scan_state() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Test clean-up.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)", self::STATUS, ScanBatchLock::OPTION ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s)", UsedWhereScanner::META_KEY, '_tsmlt_permalink_fp', '_tsmlt_usage_tracked' ) );
		// phpcs:enable
		delete_transient( UsedWhereScanner::OPTION_SCAN_CACHE_KEY );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		wp_cache_flush();
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
	 * Record a status write.
	 *
	 * @param mixed $old Previous value.
	 * @param mixed $new New value.
	 */
	public function record_write( $old, $new ) {
		$this->writes[] = [
			'state'  => $new['state'] ?? '',
			'stage'  => $new['cleanup_stage'] ?? '',
			'cursor' => (int) ( $new['cleanup_cursor'] ?? 0 ),
		];
	}

	/**
	 * Follow the cleanup's transactions and act at the planned point.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function track( $query ) {
		global $wpdb;
		if ( $this->in_filter ) {
			return $query;
		}
		if ( $this->view_before_write && ! $this->open['open'] && preg_match( '/^\s*(UPDATE|DELETE)\b/i', $query )
			&& false !== strpos( $query, $wpdb->postmeta ) && false !== strpos( $query, UsedWhereScanner::META_KEY ) ) {
			// A page view of the same image lands between the read and this write.
			$this->in_filter         = true;
			$this->acted['ran']      = true;
			UsedWhereScanner::instance()->record_frontend_usage( $this->view_before_write, $this->posts[7], 'content' );
			$this->view_before_write = 0;
			$this->in_filter         = false;
			return $query;
		}
		if ( BatchTransaction::BEGIN === $query ) {
			++$this->txn;
			$this->open = [
				'open'    => true,
				'claimed' => false,
			];
			return $this->act_at( 'begin', $query );
		}
		if ( BatchTransaction::COMMIT === $query || BatchTransaction::ROLLBACK === $query ) {
			$this->open['open'] = false;
			if ( BatchTransaction::COMMIT === $query && $this->planned( 'commit' ) ) {
				$this->acted['ran'] = true;
				return 'COMMIT tsmlt_invalid';
			}
			return $query;
		}
		if ( ! $this->open['open'] ) {
			return $query;
		}
		if ( ! $this->open['claimed'] ) {
			$this->open['claimed'] = true;
			return $query;
		}
		$query = $this->act_at( 'claimed', $query );
		if ( preg_match( '/^\s*(UPDATE|DELETE)\b/i', $query ) && preg_match( '/\bmeta_id`?\s*=/', $query ) ) {
			$this->row_writes[ $this->txn ] = ( $this->row_writes[ $this->txn ] ?? 0 ) + 1;
			return $this->act_at( 'row', $query );
		}
		return $query;
	}

	/**
	 * Whether the planned action is due now (and has not run).
	 *
	 * @param string $point Point.
	 *
	 * @return bool
	 */
	private function planned( string $point ): bool {
		return ! $this->acted['ran'] && ( $this->act['txn'] ?? 0 ) === $this->txn && ( $this->act['point'] ?? '' ) === $point;
	}

	/**
	 * Run the planned action if it is due at this point.
	 *
	 * @param string $point Point.
	 * @param string $query SQL about to run.
	 *
	 * @return string The SQL to run.
	 */
	private function act_at( string $point, string $query ): string {
		global $wpdb;
		if ( ! $this->planned( $point ) ) {
			return $query;
		}
		$this->acted['ran'] = true;
		$this->in_filter    = true;
		switch ( $this->act['what'] ) {
			case 'break':
				$query = 'UPDATE tsmlt_no_such_table SET a = 1';
				break;
			case 'sql':
				$this->link()->query( $this->sql() );
				break;
			case 'async':
				$this->link()->query( $this->sql(), MYSQLI_ASYNC );
				$this->acted['waited'] = ! self::finished( $this->link, 1.0 );
				break;
			case 'kill':
				$id = (int) mysqli_thread_id( $wpdb->dbh );
				$this->link()->query( 'KILL CONNECTION ' . $id );
				for ( $i = 0; $i < 50; $i++ ) {
					$left = $this->link()->query( 'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID = ' . $id )->fetch_row();
					if ( '0' === (string) $left[0] ) {
						break;
					}
					usleep( 100000 );
				}
				break;
			case 'page_view':
				UsedWhereScanner::instance()->record_frontend_usage( $this->act['image'], $this->act['post'], 'content' );
				break;
			case 'post_save':
				UsedWhereScanner::instance()->scan_single_post( $this->act['post'] );
				break;
		}
		$this->in_filter = false;
		return $query;
	}

	/**
	 * The planned SQL, with `{status}` replaced by the status value the
	 * second connection reads now.
	 *
	 * @return string
	 */
	private function sql(): string {
		global $wpdb;
		$sql = $this->act['sql'];
		if ( false !== strpos( $sql, '{status}' ) ) {
			$row = $this->link()->query( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::STATUS ) )->fetch_row();
			$sql = str_replace( '{status}', "'" . $this->link->real_escape_string( (string) $row[0] ) . "'", $sql );
		}
		return $sql;
	}

	/**
	 * Plan an action.
	 *
	 * @param int    $txn   Cleanup transaction (1: reclassify, 2…: chunks).
	 * @param string $point 'begin', 'claimed', 'row' or 'commit'.
	 * @param string $what  Action.
	 * @param array  $args  `sql`, `image`, `post`.
	 */
	private function plan( int $txn, string $point, string $what, array $args = [] ) {
		$this->act   = $args + [
			'txn'   => $txn,
			'point' => $point,
			'what'  => $what,
			'sql'   => '',
		];
		$this->acted = [
			'ran'    => false,
			'waited' => false,
		];
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
	 * Whether an asynchronous query finished within a time.
	 *
	 * @param mysqli $link    Connection.
	 * @param float  $seconds Seconds.
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
	 * Wait for the asynchronous statement.
	 *
	 * @return int Rows it changed.
	 */
	private function reap(): int {
		$this->assertTrue( self::finished( $this->link, 15.0 ) );
		$this->link->reap_async_query();
		$this->assertSame( '', $this->link->error );
		wp_cache_flush();
		return (int) $this->link->affected_rows;
	}

	/**
	 * Usage entry.
	 *
	 * @param int    $post Post ID (0: site-wide).
	 * @param string $type Usage type.
	 *
	 * @return array
	 */
	private static function usage( int $post, string $type ): array {
		return [
			'post_id'    => $post,
			'post_title' => 'Post ' . $post,
			'post_type'  => 0 === $post ? 'site_settings' : 'post',
			'usage_type' => $type,
		];
	}

	/**
	 * Write a usage record directly (a scan's result).
	 *
	 * @param int   $image  Attachment ID.
	 * @param array $usages Usages.
	 */
	private static function put( int $image, array $usages ) {
		global $wpdb;
		$wpdb->insert( $wpdb->postmeta, [ 'post_id' => $image, 'meta_key' => UsedWhereScanner::META_KEY, 'meta_value' => serialize( $usages ) ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery, WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture.
	}

	/**
	 * Usages of an image, from the database.
	 *
	 * @param int $image Attachment ID.
	 *
	 * @return array|null
	 */
	private static function usages( int $image ): ?array {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $image, UsedWhereScanner::META_KEY ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
		return null === $value ? null : maybe_unserialize( $value );
	}

	/**
	 * Usage types of an image, as "post:type".
	 *
	 * @param int $image Attachment ID.
	 *
	 * @return string[]
	 */
	private static function kinds( int $image ): array {
		$kinds = [];
		foreach ( (array) self::usages( $image ) as $usage ) {
			$kinds[] = $usage['post_id'] . ':' . $usage['usage_type'];
		}
		sort( $kinds );
		return $kinds;
	}

	/**
	 * The status in the database.
	 *
	 * @return mixed
	 */
	private static function stored_status() {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::STATUS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
		return null === $value ? null : maybe_unserialize( $value );
	}

	/**
	 * Extra usage records still holding their incidental usage.
	 *
	 * @return int
	 */
	private static function extra_unreconciled(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id >= %d AND meta_value LIKE %s", UsedWhereScanner::META_KEY, 9000000, '%permalink%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
	}

	/**
	 * A scan whose posts are all processed, its cleanup pending: the four
	 * images' records, and optionally EXTRA more (an owning usage plus an
	 * incidental one each, on attachment IDs from 9000000).
	 *
	 * @param bool $extra Whether to add the extra records.
	 *
	 * @return array Status.
	 */
	private function scan_ready_for_cleanup( bool $extra = false ): array {
		global $wpdb;
		UsedWhereScanner::instance()->start_scheduled_scan();
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );

		$pervasive = [];
		foreach ( array_slice( $this->posts, 0, 6 ) as $post ) {
			$pervasive[] = self::usage( $post, 'permalink' );
		}
		self::put( $this->images['pervasive'], $pervasive );
		self::put( $this->images['mixed'], [ self::usage( $this->posts[0], 'content' ), self::usage( $this->posts[1], 'permalink' ) ] );
		self::put( $this->images['owned'], [ self::usage( $this->posts[2], 'featured' ) ] );
		if ( $extra ) {
			$values = [];
			for ( $i = 0; $i < self::EXTRA; $i++ ) {
				$values[] = $wpdb->prepare( '(%d, %s, %s)', 9000000 + $i, UsedWhereScanner::META_KEY, serialize( [ self::usage( $this->posts[3], 'content' ), self::usage( $this->posts[4], 'permalink' ) ] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture.
			}
			$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ',', $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Prepared rows.
		}

		$status = array_merge(
			get_option( self::STATUS ),
			[
				'state'          => 'running',
				'processed'      => 10,
				'total'          => 10,
				'next_offset'    => 10,
				'cleanup_stage'  => 'reclassify',
				'cleanup_cursor' => 0,
			]
		);
		update_option( self::STATUS, $status, false );
		wp_cache_flush();
		$this->writes = [];
		$this->txn    = 0;
		return self::stored_status();
	}

	/**
	 * Run a cleanup tick.
	 */
	private static function tick() {
		UsedWhereScanner::instance()->run_tick_batch( 10 );
		wp_cache_flush();
	}

	/**
	 * Reclassify, then reconcile: the pervasive image becomes site-wide with
	 * its per-post renders dropped, the mixed image keeps only its owning
	 * usage, the owned one is unchanged; the scan completes only with the
	 * last step, after running through each stage.
	 */
	public function test_reclassify_then_reconcile_completes() {
		$this->scan_ready_for_cleanup();

		self::tick();

		$this->assertSame( [ '0:option_settings' ], self::kinds( $this->images['pervasive'] ) );
		$this->assertSame( [ $this->posts[0] . ':content' ], self::kinds( $this->images['mixed'] ) );
		$this->assertSame( [ $this->posts[2] . ':featured' ], self::kinds( $this->images['owned'] ) );
		$this->assertSame( [ 'running', 'running', 'complete' ], array_column( $this->writes, 'state' ) );
		$this->assertSame( [ 'reclassify', 'reconcile', 'done' ], array_column( $this->writes, 'stage' ), 'Running mark, reclassify, last chunk.' );
		$status = self::stored_status();
		$this->assertTrue( $status['complete'] );
		$this->assertNull( ScanBatchLock::instance()->read_lock() );
	}

	/**
	 * Reconcile runs in chunks of CLEANUP_CHUNK records, each its own
	 * transaction moving the cursor on; every record is reconciled and the
	 * scan completes with the last chunk only.
	 */
	public function test_reconcile_runs_in_chunks() {
		$this->scan_ready_for_cleanup( true );

		self::tick();

		$this->assertSame( 0, self::extra_unreconciled() );
		$this->assertSame( [ 'reclassify', 'reconcile', 'reconcile', 'reconcile', 'done' ], array_column( $this->writes, 'stage' ) );
		$this->assertSame( [ 'running', 'running', 'running', 'running', 'complete' ], array_column( $this->writes, 'state' ) );
		$cursors = array_column( array_slice( $this->writes, 2 ), 'cursor' );
		$this->assertSame( $cursors, array_values( array_unique( $cursors ) ), 'The cursor moves on with each chunk.' );
		$this->assertSame( 4, $this->txn, 'Reclassify and three chunks.' );
		foreach ( $this->row_writes as $writes ) {
			$this->assertLessThanOrEqual( UsedWhereScanner::CLEANUP_CHUNK, $writes );
		}
	}

	/**
	 * No usage records: the cleanup completes the scan at once.
	 */
	public function test_empty_cleanup_completes() {
		global $wpdb;
		$this->scan_ready_for_cleanup();
		$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => UsedWhereScanner::META_KEY ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery -- Test fixture.

		self::tick();

		$this->assertSame( 'complete', self::stored_status()['state'] );
		$this->assertSame( [], $this->row_writes );
	}

	/**
	 * A database error in a chunk rolls that chunk back and stops; its
	 * records and the cursor stay as the last commit left them, and the next
	 * tick repeats the chunk and completes.
	 */
	public function test_failed_chunk_is_retried() {
		global $wpdb;
		$this->scan_ready_for_cleanup( true );
		$this->plan( 3, 'row', 'break' );

		$suppressed = $wpdb->suppress_errors( true );
		self::tick();
		$wpdb->suppress_errors( $suppressed );

		$this->assertTrue( $this->acted['ran'] );
		$status = self::stored_status();
		$this->assertSame( 'running', $status['state'] );
		$this->assertSame( 'reconcile', $status['cleanup_stage'] );
		$this->assertSame( $this->writes[2]['cursor'], (int) $status['cleanup_cursor'], 'The cursor of the first chunk.' );
		$this->assertSame( self::EXTRA - 497, self::extra_unreconciled(), 'Only the first chunk is reconciled.' );

		self::tick();

		$this->assertSame( 'complete', self::stored_status()['state'] );
		$this->assertSame( 0, self::extra_unreconciled() );
	}

	/**
	 * A request ending between chunks (simulated: the next chunk's
	 * transaction cannot start) leaves the committed chunks; the next tick
	 * resumes from the cursor.
	 */
	public function test_interrupted_cleanup_resumes() {
		global $wpdb;
		$this->scan_ready_for_cleanup( true );
		$this->plan( 3, 'begin', 'break' );

		$suppressed = $wpdb->suppress_errors( true );
		self::tick();
		$wpdb->suppress_errors( $suppressed );

		$this->assertSame( 'running', self::stored_status()['state'] );
		$this->assertSame( self::EXTRA - 497, self::extra_unreconciled() );
		$this->assertSame( $this->retries, (int) $wpdb->reconnect_retries, 'The reconnect setting is restored.' );

		self::tick();

		$this->assertSame( 'complete', self::stored_status()['state'] );
		$this->assertSame( 0, self::extra_unreconciled() );
	}

	/**
	 * A chunk whose COMMIT fails moves no cursor and changes no record.
	 */
	public function test_cursor_moves_only_with_the_commit() {
		global $wpdb;
		$this->scan_ready_for_cleanup( true );
		$this->plan( 3, 'commit', 'commit' );

		$suppressed = $wpdb->suppress_errors( true );
		self::tick();
		$wpdb->suppress_errors( $suppressed );

		$this->assertTrue( $this->acted['ran'] );
		$status = self::stored_status();
		$this->assertSame( $this->writes[2]['cursor'], (int) $status['cleanup_cursor'] );
		$this->assertSame( self::EXTRA - 497, self::extra_unreconciled() );
		$this->assertSame( 'running', $status['state'] );
	}

	/**
	 * A duplicate worker's claim of the same chunk, sent on a second
	 * connection while this worker's chunk transaction holds the status
	 * row, waits and then changes nothing.
	 */
	public function test_duplicate_claim_of_a_chunk_waits_and_fails() {
		global $wpdb;
		$this->scan_ready_for_cleanup( true );
		$other = $this->duplicate_status();
		$this->plan(
			2,
			'claimed',
			'async',
			[ 'sql' => $wpdb->remove_placeholder_escape( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = ", maybe_serialize( $other ), self::STATUS ) ) . '{status}' ]
		);

		self::tick();

		$this->assertTrue( $this->acted['waited'], 'The second claim waited for the chunk transaction.' );
		$this->assertSame( 0, $this->reap() );
		$this->assertSame( 'complete', self::stored_status()['state'] );
		$this->assertSame( 0, self::extra_unreconciled() );
	}

	/**
	 * Another worker committed the chunk this worker read: this worker's
	 * claim fails, it reads the status again and goes on from the new
	 * cursor, never writing the other worker's chunk.
	 */
	public function test_worker_skips_a_chunk_another_worker_committed() {
		global $wpdb;
		$status = $this->scan_ready_for_cleanup( true );
		$first  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id LIMIT 499, 1", UsedWhereScanner::META_KEY ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
		$status['cleanup_stage']  = 'reconcile';
		$status['cleanup_cursor'] = $first;
		$this->plan( 2, 'begin', 'sql', [ 'sql' => $wpdb->remove_placeholder_escape( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", maybe_serialize( $status ), self::STATUS ) ) ] );

		self::tick();

		$this->assertTrue( $this->acted['ran'] );
		$this->assertSame( 'complete', self::stored_status()['state'] );
		$this->assertSame( 497, self::extra_unreconciled(), 'The other worker\'s chunk (not really written in this test) was left to it.' );
	}

	/**
	 * A Re-scan stored between chunks: the old worker's next claim fails, it
	 * stops, and the new scan's status is left exactly as written.
	 */
	public function test_stale_worker_stops_after_a_rescan() {
		global $wpdb;
		$status            = $this->scan_ready_for_cleanup( true );
		$new               = $status;
		$new['scan_id']    = wp_generate_uuid4();
		$new['state']      = 'queued';
		$new['next_offset'] = 0;
		unset( $new['cleanup_stage'], $new['cleanup_cursor'] );
		$this->plan( 3, 'begin', 'sql', [ 'sql' => $wpdb->remove_placeholder_escape( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", maybe_serialize( $new ), self::STATUS ) ) ] );

		self::tick();

		$this->assertTrue( $this->acted['ran'] );
		$this->assertSame( $new, self::stored_status() );
		$this->assertSame( self::EXTRA - 497, self::extra_unreconciled(), 'Nothing written after the Re-scan.' );
	}

	/**
	 * Cancel's status write, sent while a chunk transaction holds the status
	 * row, waits for that chunk only; then the cleanup stops, incomplete.
	 */
	public function test_cancel_during_cleanup_waits_for_one_chunk() {
		global $wpdb;
		$status             = $this->scan_ready_for_cleanup( true );
		$cancelled          = $status;
		$cancelled['state'] = 'cancelled';
		$this->plan( 3, 'claimed', 'async', [ 'sql' => $wpdb->remove_placeholder_escape( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", maybe_serialize( $cancelled ), self::STATUS ) ) ] );

		self::tick();

		$this->assertTrue( $this->acted['waited'], 'Cancel waited for the chunk transaction.' );
		$this->assertSame( 1, $this->reap() );
		$this->assertSame( 'cancelled', self::stored_status()['state'] );
		$this->assertSame( self::EXTRA - 997, self::extra_unreconciled(), 'Two chunks committed, none after the cancel.' );
		$this->assertFalse( self::stored_status()['complete'] );
	}

	/**
	 * Clear Results' status delete, sent during a chunk, waits for it; the
	 * rest of Clear then removes every record and the cleanup stops.
	 */
	public function test_clear_during_cleanup() {
		global $wpdb;
		$this->scan_ready_for_cleanup( true );
		$this->plan( 3, 'claimed', 'async', [ 'sql' => $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", self::STATUS ) ] );

		self::tick();

		$this->assertTrue( $this->acted['waited'] );
		$this->assertSame( 1, $this->reap() );
		UsedWhereScanner::instance()->clear_scan();
		self::tick();

		$this->assertNull( self::stored_status() );
		$this->assertNull( self::usages( $this->images['mixed'] ) );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", UsedWhereScanner::META_KEY ) ), 'No usage record left.' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test inspection.
	}

	/**
	 * A Re-scan during a chunk: its first write waits for the chunk; the
	 * rest of the Re-scan wipes the records, and the old cleanup never
	 * touches the new scan.
	 */
	public function test_rescan_during_cleanup() {
		global $wpdb;
		$status             = $this->scan_ready_for_cleanup( true );
		$cancelled          = $status;
		$cancelled['state'] = 'cancelled';
		$this->plan( 3, 'claimed', 'async', [ 'sql' => $wpdb->remove_placeholder_escape( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", maybe_serialize( $cancelled ), self::STATUS ) ) ] );

		self::tick();

		$this->assertTrue( $this->acted['waited'] );
		$this->assertSame( 1, $this->reap() );
		UsedWhereScanner::instance()->start_scheduled_scan();
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		$new = self::stored_status();
		self::tick();

		$this->assertSame( $new, self::stored_status() );
		$this->assertSame( 'queued', $new['state'] );
		$this->assertNull( self::usages( $this->images['mixed'] ) );
	}

	/**
	 * The batch's connection killed during a chunk (genuine connection
	 * loss): the server discards the chunk, the cursor stays, and the next
	 * tick finishes the cleanup.
	 */
	public function test_connection_loss_during_a_chunk() {
		global $wpdb;
		$this->scan_ready_for_cleanup( true );
		$this->plan( 3, 'row', 'kill' );

		$suppressed = $wpdb->suppress_errors( true );
		try {
			self::tick();
		} catch ( WPDieException $e ) {
			// WordPress ends the request on a lost connection it may not
			// reconnect; in tests wp_die() throws instead.
			unset( $e );
		}
		$wpdb->suppress_errors( $suppressed );
		$wpdb->check_connection( false );

		$this->assertTrue( $this->acted['ran'] );
		$status = self::stored_status();
		$this->assertContains( $status['state'], [ 'running', 'error' ] );
		$this->assertSame( 'reconcile', $status['cleanup_stage'] );
		$this->assertSame( self::EXTRA - 497, self::extra_unreconciled(), 'The killed chunk wrote nothing.' );
		$this->assertSame( $this->retries, (int) $wpdb->reconnect_retries );
	}

	/**
	 * A page view recorded on the second connection after the chunk read
	 * the image's record (while the chunk holds the status claim, before
	 * its record writes): the chunk does not overwrite it. The record keeps
	 * the page view and its incidental usage (never wrongly unused).
	 */
	public function test_page_view_during_reconcile_is_kept() {
		global $wpdb;
		$this->scan_ready_for_cleanup();
		$viewed = [ self::usage( $this->posts[0], 'content' ), self::usage( $this->posts[1], 'permalink' ), self::usage( $this->posts[7], 'content' ) ];
		$this->plan( 2, 'claimed', 'sql', [ 'sql' => $wpdb->remove_placeholder_escape( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = %s", serialize( $viewed ), $this->images['mixed'], UsedWhereScanner::META_KEY ) ) ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- The page view's write.

		self::tick();

		$this->assertTrue( $this->acted['ran'] );
		$this->assertContains( $this->posts[7] . ':content', self::kinds( $this->images['mixed'] ), 'The page view is kept.' );
		$this->assertSame( 'complete', self::stored_status()['state'] );
	}

	/**
	 * A page view recorded through record_frontend_usage() after the chunk
	 * read the record (just before the chunk starts): it is kept.
	 */
	public function test_record_frontend_usage_before_the_chunk_writes_is_kept() {
		$this->scan_ready_for_cleanup();
		$this->plan(
			2,
			'begin',
			'page_view',
			[
				'image' => $this->images['mixed'],
				'post'  => $this->posts[7],
			]
		);

		self::tick();

		$this->assertTrue( $this->acted['ran'] );
		$this->assertContains( $this->posts[7] . ':content', self::kinds( $this->images['mixed'] ) );
	}

	/**
	 * A post save (scan_single_post()) after the chunk read the record: the
	 * chunk does not overwrite the post save's result.
	 */
	public function test_post_save_during_reconcile_is_kept() {
		$this->scan_ready_for_cleanup();
		$this->plan( 2, 'begin', 'post_save', [ 'post' => $this->posts[0] ] );

		self::tick();

		$this->assertTrue( $this->acted['ran'] );
		$this->assertNotContains( $this->posts[0] . ':content', self::kinds( $this->images['mixed'] ), 'The post save removed the post\'s usage; the chunk did not bring it back.' );
	}

	/**
	 * A post save and a page view of the same image: the page view lands
	 * after the post save read the image's record and before it writes it.
	 * The post save's write does not erase the page view; the post save's
	 * own change (the saved post's usage removed) is applied too.
	 */
	public function test_post_save_and_page_view_of_the_same_image_both_kept() {
		$this->scan_ready_for_cleanup();
		$this->view_before_write = $this->images['mixed'];

		UsedWhereScanner::instance()->scan_single_post( $this->posts[1] );

		$this->assertTrue( $this->acted['ran'], 'Precondition: the page view landed between the read and the write.' );
		$kinds = self::kinds( $this->images['mixed'] );
		$this->assertContains( $this->posts[7] . ':content', $kinds, 'The page view is kept.' );
		$this->assertNotContains( $this->posts[1] . ':permalink', $kinds, 'The post save removed its post\'s usage.' );
		$this->assertContains( $this->posts[0] . ':content', $kinds );
	}

	/**
	 * Used / Unused / Unknown: while the cleanup runs nothing is unused (the
	 * scan is not complete); afterwards the images with usages are used and
	 * the image without any is unused.
	 */
	public function test_classification_during_and_after_cleanup() {
		$this->scan_ready_for_cleanup();
		$during = UsageClassifier::counts( '', UsageClassifier::context() );

		self::tick();
		$after = UsageClassifier::counts( '', UsageClassifier::context() );

		$this->assertSame( 0, $during['unused'] );
		$this->assertSame( 1, $during['unknown'] );
		$this->assertSame( 3, $after['used'] );
		$this->assertSame( 1, $after['unused'] );
		$this->assertSame( 0, $after['unknown'] );
	}

	/**
	 * The status a duplicate worker would write after reclassify.
	 *
	 * @return array
	 */
	private function duplicate_status(): array {
		$status                   = self::stored_status();
		$status['cleanup_stage']  = 'reconcile';
		$status['cleanup_cursor'] = 1;
		return $status;
	}
}
