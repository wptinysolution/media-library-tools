<?php
/**
 * Marker for a saved post's usage rescan while it runs.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Modules\UsedWhere;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;

/**
 * Records that a saved post's deferred usage rescan
 * (ActionHooks::run_scheduled_post_scan()) is running. The rescan first
 * removes the post's usage records and then rebuilds them, so while it runs
 * an image used only by that post has no record; readers that must not take
 * "no record" as "unused" (the MCP trash abilities) check this marker.
 *
 * Each run inserts its own options row, named with a random suffix, so runs
 * never share or overwrite a row and only the owner (which knows the name)
 * removes it. A row older than TTL (a run that died) is ignored and pruned
 * by the next run. Markers only ever make usage unknown; none can make
 * media count as unused.
 */
class PostRescanMarker {

	/**
	 * Prefix of a marker option, followed by a random suffix.
	 *
	 * @var string
	 */
	const PREFIX = 'tsmlt_post_rescan_running_';

	/**
	 * Seconds after which a marker is taken as left behind by a run that
	 * died (a fatal error), and ignored.
	 *
	 * @var int
	 */
	const TTL = 1800;

	/**
	 * Most markers read or pruned at once.
	 *
	 * @var int
	 */
	const LIMIT = 100;

	/**
	 * Autoload value of a marker row.
	 *
	 * @var string
	 */
	const AUTOLOAD = 'off';

	/**
	 * Mark a rescan as running: prune expired markers, then insert this
	 * run's own.
	 *
	 * @return string|null The marker's option name, or null when it could
	 *                     not be stored.
	 */
	public static function start(): ?string {
		self::prune();
		$name     = self::PREFIX . bin2hex( random_bytes( 16 ) );
		$inserted = self::marker_query(
			static function () use ( $name ) {
				return Fns::DB()->insert(
					'options',
					[
						[
							'option_name'  => $name,
							'option_value' => (string) time(),
							'autoload'     => self::AUTOLOAD,
						],
					]
				)->execute();
			}
		);
		return 1 === $inserted ? $name : null;
	}

	/**
	 * Remove this run's marker.
	 *
	 * @param string $name Option name returned by start().
	 *
	 * @return void
	 */
	public static function finish( string $name ): void {
		if ( 0 !== strpos( $name, self::PREFIX ) ) {
			return;
		}
		self::marker_query(
			static function () use ( $name ) {
				return Fns::DB()->delete( 'options' )->where( 'option_name', '=', $name )->execute();
			}
		);
	}

	/**
	 * Whether a rescan is running: a marker younger than TTL exists. Read
	 * from the database, newest first.
	 *
	 * @return bool|null Null when the markers could not be read.
	 */
	public static function running(): ?bool {
		$rows = self::rows( 'DESC' );
		if ( null === $rows ) {
			return null;
		}
		foreach ( $rows as $row ) {
			if ( ! self::expired( $row ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Remove expired markers, oldest first; a marker is removed only while
	 * it still holds the time read.
	 *
	 * @return void
	 */
	private static function prune(): void {
		foreach ( self::rows( 'ASC' ) ?? [] as $row ) {
			if ( ! self::expired( $row ) ) {
				return;
			}
			self::marker_query(
				static function () use ( $row ) {
					return Fns::DB()->delete( 'options' )
						->where( 'option_name', '=', (string) $row['option_name'] )
						->andWhere( 'option_value', '=', (string) $row['option_value'] )
						->execute();
				}
			);
		}
	}

	/**
	 * Whether a marker row is older than TTL (or unreadable).
	 *
	 * @param array $row Row (`option_value`).
	 *
	 * @return bool
	 */
	private static function expired( array $row ): bool {
		$started = (string) ( $row['option_value'] ?? '' );
		return ! ctype_digit( $started ) || ( time() - (int) $started ) > self::TTL;
	}

	/**
	 * Marker rows, at most LIMIT, in insertion order or newest first.
	 *
	 * @param string $order ASC or DESC.
	 *
	 * @return array[]|null Rows; null on a database error.
	 */
	private static function rows( string $order ): ?array {
		global $wpdb;
		$rows = self::marker_query(
			static function () use ( $wpdb, $order ) {
				return Fns::DB()->select( 'option_name', 'option_value' )->from( 'options' )
					->where( 'option_name', 'LIKE', $wpdb->esc_like( self::PREFIX ) . '%' )
					->orderBy( 'option_id', $order )
					->limit( self::LIMIT )->get();
			}
		);
		return '' === (string) $wpdb->last_error && is_array( $rows ) ? $rows : null;
	}

	/**
	 * Run a marker query with database errors hidden; an exception counts
	 * as a failure.
	 *
	 * @param callable $query Query.
	 *
	 * @return mixed The query's result, or false.
	 */
	private static function marker_query( callable $query ) {
		global $wpdb;
		$suppressed = $wpdb->suppress_errors( true );
		try {
			$result = $query();
		} catch ( \Throwable $e ) {
			$wpdb->last_error = $e->getMessage();
			$result           = false;
		}
		$wpdb->suppress_errors( $suppressed );
		return $result;
	}
}
