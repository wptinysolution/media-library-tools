<?php
/**
 * The database transaction a Used Where scan batch saves its results in.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\UsedWhere;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;

/**
 * One transaction per scan batch.
 *
 * The batch's status write is the transaction's first statement, so the
 * status row stays locked until COMMIT: a Cancel, Clear Results or Re-scan,
 * which all write that row, waits until the batch has saved everything or
 * nothing. Usage records, attachment parents, permalink fingerprints and
 * the option-scan cache are then written through this class, every write
 * checked; a database error rolls everything back.
 *
 * No WordPress cache is touched while the transaction is open (so no cache
 * holds data that may be rolled back, and no cache round trip happens while
 * rows are locked): the caches of what changed are cleared after COMMIT or
 * ROLLBACK, and the meta and attachment hooks fire after COMMIT, so nothing
 * reports a change that was not saved. The conditional meta writes can also
 * be used outside a transaction, where they take effect at once.
 *
 * The transaction belongs to one database connection. While it is open,
 * $wpdb may not reconnect (it would silently run the rest of the batch on a
 * new connection, outside the transaction): a lost connection then ends the
 * request the way WordPress handles any lost connection, and the server
 * discards the open transaction. Every statement is also checked to run on
 * the connection that started the transaction; on any other, nothing more
 * is sent and the batch fails.
 *
 * Narrow raw-SQL exceptions, all because the query builder cannot express
 * them: START TRANSACTION, COMMIT and ROLLBACK are sent with $wpdb->query();
 * the storage-engine check reads information_schema (the query builder
 * prefixes every table name); and the conditional meta writes compare the
 * stored value byte for byte (CAST AS BINARY: the column's collation ignores
 * case and accents), in prepared statements that find the row by its
 * primary key. Every other data read and write uses Fns::DB().
 *
 * Meta writes keep core's metadata hooks: the short-circuit filters
 * (`add_`/`update_`/`delete_post_metadata`, whose answer is then final),
 * the actions before the write, and the actions after it (after COMMIT).
 */
class BatchTransaction {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Statement that starts the transaction. The comment identifies it.
	 *
	 * @var string
	 */
	const BEGIN = 'START TRANSACTION /* tsmlt_used_where_batch */';

	/**
	 * Statement that commits the transaction.
	 *
	 * @var string
	 */
	const COMMIT = 'COMMIT /* tsmlt_used_where_batch */';

	/**
	 * Statement that rolls the transaction back.
	 *
	 * @var string
	 */
	const ROLLBACK = 'ROLLBACK /* tsmlt_used_where_batch */';

	/**
	 * Exception code of a database error inside the transaction.
	 *
	 * @var int
	 */
	const DATABASE_ERROR = 1001;

	/**
	 * Exception code of a row that another request changed since it was
	 * read in the transaction (roll back and read it again).
	 *
	 * @var int
	 */
	const CONFLICT = 1002;

	/**
	 * Whether a transaction is open.
	 *
	 * @var bool
	 */
	private $active = false;

	/**
	 * Changes made in the open transaction, in order, for the hooks fired
	 * after COMMIT.
	 *
	 * @var array[]
	 */
	private $changes = [];

	/**
	 * Thread ID of the connection the open transaction runs on, or 0.
	 *
	 * @var int
	 */
	private $connection = 0;

	/**
	 * $wpdb->reconnect_retries before the transaction disabled it, or null.
	 *
	 * @var int|null
	 */
	private $reconnect_retries = null;

	/**
	 * Construct
	 */
	private function __construct() {}

	/**
	 * Start a transaction.
	 *
	 * @return bool Whether it started.
	 */
	public function begin(): bool {
		global $wpdb;
		if ( $this->active ) {
			// A second START TRANSACTION would commit the open one.
			return false;
		}

		$this->changes           = [];
		$this->reconnect_retries = (int) $wpdb->reconnect_retries;
		$wpdb->reconnect_retries = 0;
		$this->connection        = self::connection_id();
		if ( 0 === $this->connection ) {
			// The connection cannot be identified: no transaction.
			$this->end();
			return false;
		}
		add_filter( 'query', [ $this, 'guard_query' ], PHP_INT_MAX );

		try {
			$started = $wpdb->query( self::BEGIN ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control (a constant); the query builder has none.
			$started = false !== $started && '' === (string) $wpdb->last_error && $this->same_connection();
		} catch ( \Throwable $e ) {
			$started = false;
		}
		if ( ! $started ) {
			$this->end();
			return false;
		}

		$this->active = true;
		return true;
	}

	/**
	 * Whether a transaction is open.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->active;
	}

	/**
	 * Commit the transaction, then clear the caches of what changed and fire
	 * its hooks. A failed COMMIT is rolled back.
	 *
	 * @return bool Whether it was committed.
	 */
	public function commit(): bool {
		global $wpdb;
		if ( ! $this->active ) {
			return false;
		}

		// COMMIT is sent only on the connection that started the transaction.
		$committed = false;
		if ( $this->same_connection() ) {
			try {
				$committed = $wpdb->query( self::COMMIT ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control (a constant); the query builder has none.
				$committed = false !== $committed && '' === (string) $wpdb->last_error;
			} catch ( \Throwable $e ) {
				$committed = false;
			}
		}
		if ( ! $committed ) {
			$this->rollback();
			return false;
		}

		$this->end();
		$changes       = $this->changes;
		$this->changes = [];
		$this->forget_cached( $changes );
		foreach ( $changes as $change ) {
			self::fire_hooks( $change );
		}
		return true;
	}

	/**
	 * Roll the transaction back and clear the caches of what it changed.
	 *
	 * ROLLBACK is sent only on the connection that started the transaction.
	 * When that connection was lost, nothing is sent (a replacement
	 * connection has no transaction to roll back): the server discards the
	 * lost connection's transaction, which this request cannot confirm.
	 *
	 * @return bool Whether ROLLBACK ran on the transaction's connection.
	 */
	public function rollback(): bool {
		global $wpdb;
		$rolled_back = false;
		if ( $this->active && $this->same_connection() ) {
			try {
				$rolled_back = $wpdb->query( self::ROLLBACK ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control (a constant); the query builder has none.
				$rolled_back = false !== $rolled_back && '' === (string) $wpdb->last_error;
			} catch ( \Throwable $e ) {
				$rolled_back = false;
			}
		}

		$this->end();
		$changes       = $this->changes;
		$this->changes = [];
		$this->forget_cached( $changes );
		foreach ( $changes as $change ) {
			// A transient may be held by the object cache, outside the
			// transaction: drop it (it is only a cache).
			if ( 'transient' === $change['type'] ) {
				delete_transient( $change['name'] );
			}
		}
		return $rolled_back;
	}

	/**
	 * Block a statement that would run on another connection than the open
	 * transaction's (after a reconnect): it is not sent, and check() then
	 * fails the batch.
	 *
	 * @param string $query SQL.
	 *
	 * @return string The statement, or '' to send nothing.
	 */
	public function guard_query( $query ) {
		if ( 0 !== $this->connection && ! $this->same_connection() ) {
			return '';
		}
		return $query;
	}

	/**
	 * A post meta row as stored, read from the database, not the cache (the
	 * first row of the key, like get_post_meta() with $single).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 *
	 * @return array|null `meta_id` and the stored (serialized) `raw` value;
	 *                    null when there is no row.
	 *
	 * @throws \RuntimeException On a database error.
	 */
	public function get_meta_row( int $post_id, string $key ): ?array {
		$rows = Fns::DB()->select( 'meta_id', 'meta_value' )
			->from( 'postmeta' )
			->where( 'post_id', '=', $post_id )
			->andWhere( 'meta_key', '=', $key )
			->orderBy( 'meta_id', 'ASC' )
			->limit( 1 )
			->get();
		self::check();
		if ( ! isset( $rows[0]['meta_id'] ) ) {
			return null;
		}
		return [
			'meta_id' => (int) $rows[0]['meta_id'],
			'raw'     => (string) $rows[0]['meta_value'],
		];
	}

	/**
	 * Replace one post meta row's value, only if it still holds the value
	 * read: a write made by another request since the read is never
	 * overwritten. The row is addressed by its meta_id (primary key), so
	 * only that row is locked.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $post_id Post ID (for hooks and caches).
	 * @param string $key     Meta key.
	 * @param string $raw     Stored value read.
	 * @param mixed  $value   New value (unslashed, as update_post_meta() takes it).
	 *
	 * @return bool Whether the row now holds the new value (false: it changed
	 *              since the read, or is gone).
	 *
	 * @throws \RuntimeException On a database error.
	 */
	public function replace_meta_if( int $meta_id, int $post_id, string $key, string $raw, $value ): bool {
		global $wpdb;
		$value  = sanitize_meta( $key, wp_unslash( $value ), 'post' );
		$stored = maybe_serialize( $value );
		if ( $stored === $raw ) {
			return true;
		}
		if ( null !== self::short_circuit( 'update', $post_id, $key, $value ) ) {
			// A filter stores this meta its own way, as with update_post_meta().
			return true;
		}
		self::before_write( 'update', [ $meta_id ], $post_id, $key, $value, $stored );

		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Byte-exact conditional write; the query builder compares by collation.
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_id = %d AND meta_key = %s AND CAST(meta_value AS BINARY) = CAST(%s AS BINARY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
				$stored,
				$meta_id,
				$key,
				$raw
			)
		);
		self::check();
		if ( 1 !== (int) $updated ) {
			return false;
		}

		$this->record_change(
			[
				'type'    => 'meta',
				'action'  => 'updated',
				'post_id' => $post_id,
				'key'     => $key,
				'ids'     => [ $meta_id ],
				'value'   => $value,
				'stored'  => $stored,
			]
		);
		return true;
	}

	/**
	 * Delete one post meta row, only if it still holds the value read.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $post_id Post ID (for hooks and caches).
	 * @param string $key     Meta key.
	 * @param string $raw     Stored value read.
	 *
	 * @return bool Whether the row was deleted.
	 *
	 * @throws \RuntimeException On a database error.
	 */
	public function delete_meta_if( int $meta_id, int $post_id, string $key, string $raw ): bool {
		global $wpdb;
		if ( null !== self::short_circuit( 'delete', $post_id, $key, '' ) ) {
			// A filter stores this meta its own way, as with delete_post_meta().
			return true;
		}
		self::before_write( 'delete', [ $meta_id ], $post_id, $key, '', '' );

		$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Byte-exact conditional write; the query builder compares by collation.
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_id = %d AND meta_key = %s AND CAST(meta_value AS BINARY) = CAST(%s AS BINARY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
				$meta_id,
				$key,
				$raw
			)
		);
		self::check();
		if ( 1 !== (int) $deleted ) {
			return false;
		}

		$this->record_change(
			[
				'type'    => 'meta',
				'action'  => 'deleted',
				'post_id' => $post_id,
				'key'     => $key,
				'ids'     => [ $meta_id ],
				'value'   => '',
			]
		);
		return true;
	}

	/**
	 * Save a post meta value, like update_post_meta(): every row of the key
	 * is updated, or one is added.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value (unslashed, as update_post_meta() takes it).
	 *
	 * @return void
	 *
	 * @throws \RuntimeException On a database error.
	 */
	public function put_meta( int $post_id, string $key, $value ): void {
		global $wpdb;
		$value  = sanitize_meta( $key, wp_unslash( $value ), 'post' );
		$stored = maybe_serialize( $value );

		$rows = Fns::DB()->select( 'meta_id' )
			->from( 'postmeta' )
			->where( 'post_id', '=', $post_id )
			->andWhere( 'meta_key', '=', $key )
			->get();
		self::check();
		$ids    = array_map( 'intval', array_column( (array) $rows, 'meta_id' ) );
		$action = empty( $ids ) ? 'add' : 'update';
		if ( null !== self::short_circuit( $action, $post_id, $key, $value ) ) {
			// A filter stores this meta its own way, as with update_post_meta().
			return;
		}
		self::before_write( $action, $ids, $post_id, $key, $value, $stored );

		if ( empty( $ids ) ) {
			Fns::DB()->insert(
				'postmeta',
				[
					[
						'post_id'    => $post_id,
						'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Insert, not a query.
						'meta_value' => $stored, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Insert, not a query.
					],
				]
			)->execute();
			self::check();
			$ids    = [ (int) $wpdb->insert_id ];
			$action = 'added';
		} else {
			Fns::DB()->update( 'postmeta', [ 'meta_value' => $stored ] ) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Update data, not a query.
				->where( 'post_id', '=', $post_id )
				->andWhere( 'meta_key', '=', $key )
				->execute();
			self::check();
			$action = 'updated';
		}

		$this->record_change(
			[
				'type'    => 'meta',
				'action'  => $action,
				'post_id' => $post_id,
				'key'     => $key,
				'ids'     => $ids,
				'value'   => $value,
				'stored'  => $stored,
			]
		);
	}

	/**
	 * Set an attachment's parent, only while it has none. Like
	 * wp_update_post(), the modified date is updated, and the attachment
	 * update hooks fire after COMMIT.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $post_id       Parent post ID.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException On a database error.
	 */
	public function set_parent( int $attachment_id, int $post_id ): void {
		global $wpdb;
		$before = get_post( $attachment_id );

		Fns::DB()->update(
			'posts',
			[
				'post_parent'       => $post_id,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', true ),
			]
		)
			->where( 'ID', '=', $attachment_id )
			->andWhere( 'post_parent', '=', 0 )
			->execute();
		self::check();
		if ( 1 !== (int) $wpdb->rows_affected ) {
			return;
		}

		$this->record_change(
			[
				'type'          => 'parent',
				'attachment_id' => $attachment_id,
				'before'        => $before,
			]
		);
	}

	/**
	 * Set a transient in the transaction. With a persistent object cache the
	 * transient lives outside the database; it is deleted on rollback.
	 *
	 * @param string $name       Transient name.
	 * @param mixed  $value      Value.
	 * @param int    $expiration Seconds until it expires.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException On a database error.
	 */
	public function set_transient( string $name, $value, int $expiration ): void {
		$this->changes[] = [
			'type' => 'transient',
			'name' => $name,
		];
		set_transient( $name, $value, $expiration );
		self::check();
	}

	/**
	 * Tables a transaction writes to that are not InnoDB (whose transactions
	 * and row locks this design needs).
	 *
	 * @return string[]|null Table names; null when they cannot be read.
	 */
	public static function unsupported_tables(): ?array {
		global $wpdb;
		$tables = [ $wpdb->options, $wpdb->postmeta, $wpdb->posts ];
		$sql    = $wpdb->prepare(
			'SELECT TABLE_NAME AS table_name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME IN (%s, %s, %s)',
			$wpdb->dbname,
			$tables[0],
			$tables[1],
			$tables[2]
		);
		$rows   = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Storage engines (prepared above); information_schema is out of the query builder's reach.
		if ( '' !== (string) $wpdb->last_error || ! is_array( $rows ) ) {
			return null;
		}

		$engines = array_column( $rows, 'engine', 'table_name' );
		$tables  = array_filter(
			$tables,
			static function ( $table ) use ( $engines ) {
				return 'innodb' !== strtolower( (string) ( $engines[ $table ] ?? '' ) );
			}
		);
		return array_values( $tables );
	}

	/**
	 * Whether an exception is a database error raised in the transaction.
	 *
	 * @param \Throwable $e Exception.
	 *
	 * @return bool
	 */
	public static function is_database_error( \Throwable $e ): bool {
		return $e instanceof \RuntimeException && self::DATABASE_ERROR === $e->getCode();
	}

	/**
	 * Whether an exception reports a row changed by another request since it
	 * was read in the transaction.
	 *
	 * @param \Throwable $e Exception.
	 *
	 * @return bool
	 */
	public static function is_conflict( \Throwable $e ): bool {
		return $e instanceof \RuntimeException && self::CONFLICT === $e->getCode();
	}

	/**
	 * Fail the transaction because a row changed since it was read in it.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException Always.
	 */
	public static function conflict(): void {
		throw new \RuntimeException( esc_html__( 'A usage record changed while the scan was saving it.', 'media-library-tools' ), (int) self::CONFLICT );
	}

	/**
	 * Fail on a database error from the last query (for reads made in the
	 * transaction outside this class).
	 *
	 * @return void
	 *
	 * @throws \RuntimeException On a database error.
	 */
	public static function check(): void {
		global $wpdb;
		$transaction = self::instance();
		if ( '' !== (string) $wpdb->last_error || ( $transaction->active && ! $transaction->same_connection() ) ) {
			// The message is shown to users: no SQL details.
			throw new \RuntimeException( esc_html__( 'Database error while saving a usage scan batch.', 'media-library-tools' ), (int) self::DATABASE_ERROR );
		}
	}

	/**
	 * Note a saved change. In the open transaction it waits for COMMIT (its
	 * caches are cleared and its hooks fire then, or its caches are cleared
	 * on rollback); outside a transaction it is final, so both happen now.
	 * No cache is touched while the transaction holds row locks.
	 *
	 * @param array $change Change.
	 *
	 * @return void
	 */
	private function record_change( array $change ): void {
		if ( $this->active ) {
			$this->changes[] = $change;
			return;
		}
		$this->forget_cached( [ $change ] );
		self::fire_hooks( $change );
	}

	/**
	 * End the transaction state: restore $wpdb's reconnect setting and stop
	 * guarding statements.
	 *
	 * @return void
	 */
	private function end(): void {
		global $wpdb;
		remove_filter( 'query', [ $this, 'guard_query' ], PHP_INT_MAX );
		if ( null !== $this->reconnect_retries ) {
			$wpdb->reconnect_retries = $this->reconnect_retries;
		}
		$this->reconnect_retries = null;
		$this->connection        = 0;
		$this->active            = false;
	}

	/**
	 * Whether $wpdb still uses the connection the transaction started on.
	 *
	 * @return bool
	 */
	private function same_connection(): bool {
		return 0 !== $this->connection && self::connection_id() === $this->connection;
	}

	/**
	 * Thread ID of $wpdb's current MySQL connection (no query is sent), or 0
	 * when there is no usable mysqli connection.
	 *
	 * @return int
	 */
	private static function connection_id(): int {
		global $wpdb;
		try {
			$dbh = $wpdb->dbh;
			return $dbh instanceof \mysqli ? (int) mysqli_thread_id( $dbh ) : 0; // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_thread_id -- Reads the connection's ID without a query; $wpdb has no API for it.
		} catch ( \Throwable $e ) {
			return 0;
		}
	}

	/**
	 * Clear the caches of what a transaction changed.
	 *
	 * @param array[] $changes Changes.
	 *
	 * @return void
	 */
	private function forget_cached( array $changes ): void {
		foreach ( $changes as $change ) {
			if ( 'meta' === $change['type'] ) {
				wp_cache_delete( $change['post_id'], 'post_meta' );
			} elseif ( 'parent' === $change['type'] ) {
				clean_post_cache( $change['attachment_id'] );
			}
		}
	}

	/**
	 * Core's short-circuit filter for a post meta write, as add_metadata(),
	 * update_metadata() and delete_metadata() apply it.
	 *
	 * @param string $action 'add', 'update' or 'delete'.
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 *
	 * @return bool|null Null to write; otherwise the filter's answer (the
	 *                   filter handled the write).
	 */
	private static function short_circuit( string $action, int $post_id, string $key, $value ): ?bool {
		$check = apply_filters( "{$action}_post_metadata", null, $post_id, $key, $value, 'update' === $action ? '' : false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's meta filter.
		return null === $check ? null : (bool) $check;
	}

	/**
	 * The actions core fires just before a post meta write, with core's
	 * arguments.
	 *
	 * @param string $action  'add', 'update' or 'delete'.
	 * @param int[]  $ids     Meta IDs written (none for 'add').
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 * @param string $stored  Value as stored (serialized).
	 *
	 * @return void
	 */
	private static function before_write( string $action, array $ids, int $post_id, string $key, $value, string $stored ): void {
		if ( 'add' === $action ) {
			do_action( 'add_post_meta', $post_id, $key, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's meta hook.
			return;
		}
		if ( 'delete' === $action ) {
			do_action( 'delete_post_meta', $ids, $post_id, $key, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's meta hook.
			do_action( 'delete_postmeta', $ids ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's meta hook.
			return;
		}
		foreach ( $ids as $meta_id ) {
			do_action( 'update_post_meta', $meta_id, $post_id, $key, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's meta hook.
			do_action( 'update_postmeta', $meta_id, $post_id, $key, $stored ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's meta hook.
		}
	}

	/**
	 * Fire the hooks WordPress fires after a committed change.
	 *
	 * @param array $change Change.
	 *
	 * @return void
	 */
	private static function fire_hooks( array $change ): void {
		if ( 'meta' === $change['type'] ) {
			// The actions core fires after the write, with core's arguments.
			if ( 'deleted' === $change['action'] ) {
				do_action( 'deleted_post_meta', $change['ids'], $change['post_id'], $change['key'], $change['value'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's meta hook.
				do_action( 'deleted_postmeta', $change['ids'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's meta hook.
				return;
			}
			foreach ( $change['ids'] as $meta_id ) {
				do_action( "{$change['action']}_post_meta", $meta_id, $change['post_id'], $change['key'], $change['value'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's meta hook.
				if ( 'updated' === $change['action'] ) {
					do_action( 'updated_postmeta', $meta_id, $change['post_id'], $change['key'], $change['stored'] ?? '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's meta hook.
				}
			}
			return;
		}
		if ( 'parent' === $change['type'] ) {
			$id = $change['attachment_id'];
			do_action( 'edit_attachment', $id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's attachment hook.
			do_action( 'attachment_updated', $id, get_post( $id ), $change['before'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's attachment hook.
		}
	}
}
