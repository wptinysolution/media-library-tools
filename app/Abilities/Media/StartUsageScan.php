<?php
/**
 * Ability: tsmlt/start-usage-scan.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities\Media;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Abs\Ability;
use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;
use TinySolutions\mlt\Traits\SingletonTrait;
use WP_Error;

/**
 * Start the Used Where scan in the background, the way the Used Where page
 * does (UsedWhereScanner::start_scheduled_scan(), then spawn_cron()). The
 * scan itself runs in WP-Cron batches; this request only queues it.
 *
 * - A scan already queued or running is left alone: its status is returned
 *   as "already_running". (The admin page's button restarts instead.)
 * - A stalled scan (queued or running, no batch for over 3 minutes) is
 *   resumed from where it stopped with the scanner's own recovery, then
 *   reported as "resumed"; the next batch is scheduled only if none is.
 * - Otherwise (never run, complete, cancelled, error) a new scan starts:
 *   the scanner replaces the previous usage records as it goes. It keeps
 *   every attachment's existing parent and sets one only where there is
 *   none (UsedWhereScanner).
 *
 * Two requests at once cannot both start a scan: a short start lock lets one
 * through and the other reports "already_running". The lock is an options
 * row holding the time and a random token: it is taken with a plain INSERT
 * (the table's unique option_name key accepts only one), an abandoned lock
 * is taken over with an UPDATE that must still find the old value, and it is
 * released with a DELETE that must find this request's own value. A
 * database error never counts as holding the lock.
 *
 * Registered with `meta.mcp.public` false: offered only on the dedicated
 * Media Library Tools server.
 */
class StartUsageScan extends Ability {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Option used as the start lock.
	 *
	 * @var string
	 */
	const START_LOCK_OPTION = 'tsmlt_used_where_scan_start_lock';

	/**
	 * Seconds after which a start lock left behind by a failed request is
	 * ignored.
	 *
	 * @var int
	 */
	const START_LOCK_SECONDS = 60;

	/**
	 * Autoload value of the start lock row.
	 *
	 * @var string
	 */
	const START_LOCK_AUTOLOAD = 'off';

	/**
	 * Results.
	 *
	 * @var string[]
	 */
	const RESULTS = [ 'started', 'already_running', 'resumed' ];

	/**
	 * Error code: the scan could not be started.
	 *
	 * @var string
	 */
	const ERROR_START_FAILED = 'tsmlt_scan_start_failed';

	/**
	 * Class Constructor
	 */
	private function __construct() {}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'tsmlt/start-usage-scan';
	}

	/**
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Start usage scan', 'media-library-tools' );
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Start the Used Where scan, which records where each media item is used (featured images, content, page builders, custom fields, logo, menus, widgets, options). It runs in the background in small batches; this call only queues it. If a scan is already queued or running, nothing new starts and result is already_running; a stalled scan is resumed (result resumed). A new scan replaces the previous results as it runs, so until it completes tsmlt/get-media-usage reports items without a recorded usage as unknown. Poll tsmlt/get-usage-scan-status until the state is complete, cancelled or error. Media files are never changed and an image\'s existing attached post (Uploaded to) is kept; the scan only sets the attached post of an image that has none, to a post that uses it.', 'media-library-tools' );
	}

	/**
	 * Not offered on the MCP Adapter's default gateway.
	 *
	 * @return bool
	 */
	protected function is_mcp_public(): bool {
		return false;
	}

	/**
	 * @return array
	 */
	protected function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			// Lets clients call the ability with no arguments at all.
			'default'              => [],
			'properties'           => [],
		];
	}

	/**
	 * @return array
	 */
	protected function get_output_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'result', 'scan' ],
			'properties'           => [
				'result' => [
					'type'        => 'string',
					'enum'        => self::RESULTS,
					'description' => 'started: a new scan was queued. already_running: a scan was already queued or running; nothing changed. resumed: a stalled scan was set to continue.',
				],
				'scan'   => UsageScanStatus::schema(),
			],
		];
	}

	/**
	 * Replaces the previous scan results, so clients should confirm first.
	 *
	 * @return array<string, bool>
	 */
	protected function get_annotations(): array {
		return [
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => false,
		];
	}

	/**
	 * Start (or report) the scan.
	 *
	 * @param mixed $input Ability input, already validated against the schema.
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = null ) {
		// The callback can be reached without the permission callback.
		$base = AbilityGuard::check_base_capability();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		if ( null !== $input && ( ! is_array( $input ) || ! empty( $input ) ) ) {
			return AbilityGuard::error(
				AbilityGuard::ERROR_INVALID_INPUT,
				__( 'This ability takes no input.', 'media-library-tools' ),
				400
			);
		}

		// Another request is starting a scan right now.
		$lock = $this->acquire_lock();
		if ( false === $lock ) {
			return $this->start_failed();
		}
		if ( null === $lock ) {
			return $this->result( 'already_running' );
		}

		try {
			$status = UsageScanStatus::read();
			if ( in_array( $status['state'], UsageScanStatus::ACTIVE_STATES, true ) ) {
				if ( ! $status['stalled'] ) {
					return $this->result( 'already_running' );
				}
				$this->resume();
				return $this->result( 'resumed' );
			}

			UsedWhereScanner::instance()->start_scheduled_scan();
			$this->spawn_cron();
			return $this->result( 'started' );
		} catch ( \Throwable $e ) {
			return $this->start_failed();
		} finally {
			$this->release_lock( $lock );
		}
	}

	/**
	 * The error for a start that could not be attempted or failed.
	 *
	 * @return WP_Error
	 */
	private function start_failed(): WP_Error {
		return AbilityGuard::error(
			self::ERROR_START_FAILED,
			__( 'The usage scan could not be started. Try again, or start it under Media Library Tools > Used Where.', 'media-library-tools' ),
			500
		);
	}

	/**
	 * Take the start lock.
	 *
	 * A plain INSERT takes a free lock: the options table's unique key on
	 * option_name lets exactly one request insert it. A lock older than
	 * START_LOCK_SECONDS is abandoned and is taken over with an UPDATE that
	 * must still find the value just read, so of several requests only one
	 * takes it. add_option() is not used: it checks first and then runs
	 * INSERT ... ON DUPLICATE KEY UPDATE, so two requests could both get the
	 * lock. Lock rows are read from the database, not the option cache.
	 *
	 * @return string|null|false This request's lock value; null when another
	 *                           request holds the lock; false on a database
	 *                           error.
	 */
	private function acquire_lock() {
		$value = time() . ':' . bin2hex( random_bytes( 16 ) );
		if ( $this->insert_lock( $value ) ) {
			return $value;
		}

		$current = $this->read_lock();
		if ( false === $current ) {
			return false;
		}
		if ( null === $current ) {
			// Released after the INSERT failed, or the INSERT failed for
			// another reason: try once more.
			return $this->insert_lock( $value ) ? $value : false;
		}

		$taken = (int) strtok( $current, ':' );
		if ( $taken > 0 && ( time() - $taken ) <= self::START_LOCK_SECONDS ) {
			return null;
		}
		return $this->replace_lock( $current, $value ) ? $value : null;
	}

	/**
	 * Release the start lock, only if it is still this request's. A lock
	 * taken over by another request after it expired is left alone; a lock
	 * that cannot be deleted expires after START_LOCK_SECONDS.
	 *
	 * @param string $value This request's lock value.
	 *
	 * @return void
	 */
	private function release_lock( string $value ): void {
		self::lock_query(
			static function () use ( $value ) {
				return Fns::DB()->delete( 'options' )
					->where( 'option_name', '=', self::START_LOCK_OPTION )
					->andWhere( 'option_value', '=', $value )
					->execute();
			}
		);
		self::forget_cached_lock();
	}

	/**
	 * Insert the lock row.
	 *
	 * @param string $value Lock value.
	 *
	 * @return bool Whether exactly one row was inserted.
	 */
	private function insert_lock( string $value ): bool {
		$inserted = self::lock_query(
			static function () use ( $value ) {
				return Fns::DB()->insert(
					'options',
					[
						[
							'option_name'  => self::START_LOCK_OPTION,
							'option_value' => $value,
							'autoload'     => self::START_LOCK_AUTOLOAD,
						],
					]
				)->execute();
			}
		);
		self::forget_cached_lock();
		return 1 === $inserted;
	}

	/**
	 * Replace an abandoned lock, only if it still holds the value read.
	 *
	 * @param string $old   Value read.
	 * @param string $value New value.
	 *
	 * @return bool Whether this request replaced it.
	 */
	private function replace_lock( string $old, string $value ): bool {
		$updated = self::lock_query(
			static function () use ( $old, $value ) {
				global $wpdb;
				// The query builder's update does not return the row count.
				Fns::DB()->update( 'options', [ 'option_value' => $value ] )
					->where( 'option_name', '=', self::START_LOCK_OPTION )
					->andWhere( 'option_value', '=', $old )
					->execute();
				return '' === (string) $wpdb->last_error ? (int) $wpdb->rows_affected : false;
			}
		);
		self::forget_cached_lock();
		return 1 === $updated;
	}

	/**
	 * The lock value stored in the database.
	 *
	 * @return string|null|false The value; null when there is no lock; false
	 *                           on a database error.
	 */
	private function read_lock() {
		global $wpdb;
		$rows = self::lock_query(
			static function () {
				return Fns::DB()->select( 'option_value' )
					->from( 'options' )
					->where( 'option_name', '=', self::START_LOCK_OPTION )
					->get();
			}
		);
		if ( '' !== (string) $wpdb->last_error || ! is_array( $rows ) ) {
			return false;
		}
		return isset( $rows[0]['option_value'] ) ? (string) $rows[0]['option_value'] : null;
	}

	/**
	 * Run a lock query with database errors hidden (a lost race is an
	 * expected duplicate-key error); an exception counts as a failure.
	 *
	 * @param callable $query Query.
	 *
	 * @return mixed The query's result, or false.
	 */
	private static function lock_query( callable $query ) {
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

	/**
	 * Drop the cached copy of the lock option, so get_option() matches the
	 * database after the direct queries above.
	 *
	 * @return void
	 */
	private static function forget_cached_lock(): void {
		wp_cache_delete( self::START_LOCK_OPTION, 'options' );
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ self::START_LOCK_OPTION ] ) ) {
			unset( $notoptions[ self::START_LOCK_OPTION ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}
	}

	/**
	 * Resume a stalled scan: the scanner's own recovery (which re-schedules
	 * the next batch when batches had run), and the next batch scheduled if
	 * there still is none (a scan whose first batch never ran).
	 *
	 * @return void
	 */
	private function resume(): void {
		UsedWhereScanner::instance()->get_scan_status();

		if ( ! $this->tick_scheduled() ) {
			$status = get_option( UsageScanStatus::OPTION, [] );
			$offset = is_array( $status ) ? absint( $status['next_offset'] ?? 0 ) : 0;
			wp_schedule_single_event( time() + 1, UsedWhereScanner::SCAN_TICK_HOOK, [ $offset ] );
		}
		$this->spawn_cron();
	}

	/**
	 * Whether a scan batch is scheduled.
	 *
	 * @return bool
	 */
	private function tick_scheduled(): bool {
		foreach ( (array) _get_cron_array() as $hooks ) {
			if ( is_array( $hooks ) && ! empty( $hooks[ UsedWhereScanner::SCAN_TICK_HOOK ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ask WP-Cron to run now, as the Used Where page does after starting.
	 *
	 * @return void
	 */
	private function spawn_cron(): void {
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Result with the current status.
	 *
	 * @param string $result One of RESULTS.
	 *
	 * @return array
	 */
	private function result( string $result ): array {
		return [
			'result' => $result,
			'scan'   => UsageScanStatus::read(),
		];
	}
}
