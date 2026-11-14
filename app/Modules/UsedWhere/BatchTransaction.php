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
 * WordPress caches are only cleared while the transaction is open (never
 * filled with data that may be rolled back), and the meta and attachment
 * hooks fire after COMMIT, so nothing reports a change that was not saved.
 *
 * Narrow raw-SQL exceptions, both because the query builder cannot express
 * them: START TRANSACTION, COMMIT and ROLLBACK are sent with $wpdb->query(),
 * and the storage-engine check reads information_schema (the query builder
 * prefixes every table name). Every data read and write uses Fns::DB().
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
		$this->changes = [];
		$started       = $wpdb->query( self::BEGIN ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control (a constant); the query builder has none.
		$this->active  = false !== $started && '' === (string) $wpdb->last_error;
		return $this->active;
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
		$committed = $wpdb->query( self::COMMIT ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control (a constant); the query builder has none.
		if ( false === $committed || '' !== (string) $wpdb->last_error ) {
			$this->rollback();
			return false;
		}

		$this->active  = false;
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
	 * @return void
	 */
	public function rollback(): void {
		global $wpdb;
		$wpdb->query( self::ROLLBACK ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Transaction control (a constant); the query builder has none.
		$this->active  = false;
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
	}

	/**
	 * A post meta value, read from the database (first row, like
	 * get_post_meta() with $single).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 *
	 * @return mixed The value, or '' when there is none.
	 *
	 * @throws \RuntimeException On a database error.
	 */
	public function get_meta( int $post_id, string $key ) {
		$rows = Fns::DB()->select( 'meta_value' )
			->from( 'postmeta' )
			->where( 'post_id', '=', $post_id )
			->andWhere( 'meta_key', '=', $key )
			->orderBy( 'meta_id', 'ASC' )
			->limit( 1 )
			->get();
		self::check();
		return isset( $rows[0]['meta_value'] ) ? maybe_unserialize( $rows[0]['meta_value'] ) : '';
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

		if ( empty( $rows ) ) {
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
			$ids    = array_map( 'intval', array_column( $rows, 'meta_id' ) );
			$action = 'updated';
		}

		wp_cache_delete( $post_id, 'post_meta' );
		$this->changes[] = [
			'type'    => 'meta',
			'action'  => $action,
			'post_id' => $post_id,
			'key'     => $key,
			'ids'     => $ids,
			'value'   => $value,
		];
	}

	/**
	 * Delete every row of a post meta key, like delete_post_meta().
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException On a database error.
	 */
	public function delete_meta( int $post_id, string $key ): void {
		$rows = Fns::DB()->select( 'meta_id' )
			->from( 'postmeta' )
			->where( 'post_id', '=', $post_id )
			->andWhere( 'meta_key', '=', $key )
			->get();
		self::check();
		if ( empty( $rows ) ) {
			return;
		}

		Fns::DB()->delete( 'postmeta' )
			->where( 'post_id', '=', $post_id )
			->andWhere( 'meta_key', '=', $key )
			->execute();
		self::check();

		wp_cache_delete( $post_id, 'post_meta' );
		$this->changes[] = [
			'type'    => 'meta',
			'action'  => 'deleted',
			'post_id' => $post_id,
			'key'     => $key,
			'ids'     => array_map( 'intval', array_column( $rows, 'meta_id' ) ),
			'value'   => '',
		];
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

		wp_cache_delete( $attachment_id, 'posts' );
		$this->changes[] = [
			'type'          => 'parent',
			'attachment_id' => $attachment_id,
			'before'        => $before,
		];
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
	 * Fail on a database error from the last query (for reads made in the
	 * transaction outside this class).
	 *
	 * @return void
	 *
	 * @throws \RuntimeException On a database error.
	 */
	public static function check(): void {
		global $wpdb;
		if ( '' !== (string) $wpdb->last_error ) {
			// The message is shown to users: no SQL details.
			throw new \RuntimeException( esc_html__( 'Database error while saving a usage scan batch.', 'media-library-tools' ), (int) self::DATABASE_ERROR );
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
	 * Fire the hooks WordPress fires after a committed change.
	 *
	 * @param array $change Change.
	 *
	 * @return void
	 */
	private static function fire_hooks( array $change ): void {
		if ( 'meta' === $change['type'] ) {
			if ( 'deleted' === $change['action'] ) {
				do_action( 'deleted_post_meta', $change['ids'], $change['post_id'], $change['key'], $change['value'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's meta hook.
				return;
			}
			foreach ( $change['ids'] as $meta_id ) {
				do_action( "{$change['action']}_post_meta", $meta_id, $change['post_id'], $change['key'], $change['value'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's meta hook.
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
