<?php
/**
 * The marker of a running post usage rescan (PostRescanMarker) and the
 * deferred rescan around it: the marker exists exactly while the rescan
 * runs and is removed however it ends; only its owner removes it; expired
 * markers are ignored and pruned; a rescan that cannot be marked is not run
 * but scheduled again; saving a post never leaves a moment with no rescan
 * pending; a failed read of the markers is reported as unknown.
 *
 * Every post and attachment is a generated fixture. Outgoing HTTP is
 * blocked.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\UsedWhere\PostRescanMarker;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * PostRescanMarkerTest
 */
class PostRescanMarkerTest extends WP_UnitTestCase {

	/**
	 * Cron hook of the deferred rescan.
	 *
	 * @var string
	 */
	const HOOK = 'tsmlt_scan_post_usage';

	/**
	 * Marker state seen during the rescan (null until seen).
	 *
	 * @var bool|null
	 */
	private $seen = null;

	/**
	 * What the query filter does: 'observe', 'throw', 'fail_insert' or
	 * 'fail_read' ('' for nothing).
	 *
	 * @var string
	 */
	private $mode = '';

	/**
	 * Whether the query filter is running (no re-entry).
	 *
	 * @var bool
	 */
	private $inside = false;

	/**
	 * Calls of observed schedule hooks, by hook.
	 *
	 * @var array<string, int>
	 */
	private $calls = [];

	/**
	 * Administrator; no markers; nothing scheduled.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		self::clear();
		$this->seen   = null;
		$this->mode   = '';
		$this->inside = false;
		$this->calls  = [];
	}

	/**
	 * Remove filters, markers and scheduled rescans.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		remove_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		remove_filter( 'query', [ $this, 'on_query' ] );
		remove_filter( 'pre_clear_scheduled_hook', [ $this, 'count_rescan_call' ] );
		remove_filter( 'pre_unschedule_event', [ $this, 'count_rescan_call' ] );
		self::clear();
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
	 * Count a clear or unschedule of the rescan hook (core clears other
	 * hooks, such as publish_future_post, on every save).
	 *
	 * @param mixed $pre  Pre-filtered value, returned unchanged.
	 * @param mixed $when Timestamp (pre_unschedule_event) or hook name
	 *                    (pre_clear_scheduled_hook).
	 * @param mixed $hook Hook name (pre_unschedule_event) or arguments.
	 *
	 * @return mixed
	 */
	public function count_rescan_call( $pre, $when = null, $hook = null ) {
		if ( self::HOOK === $when || self::HOOK === $hook ) {
			$this->calls[ current_filter() ] = ( $this->calls[ current_filter() ] ?? 0 ) + 1;
		}
		return $pre;
	}

	/**
	 * Query filter for the scenarios in $this->mode: note the marker state
	 * on the rescan's first post meta read, throw there once, or make the
	 * marker's INSERT or SELECT fail.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 *
	 * @throws RuntimeException In the 'throw' mode.
	 */
	public function on_query( $query ) {
		global $wpdb;
		if ( $this->inside ) {
			return $query;
		}
		$marker = false !== strpos( $query, 'rescan' );
		if ( 'fail_insert' === $this->mode && $marker && 0 === strpos( $query, 'INSERT' ) ) {
			return 'SELECT * FROM tsmlt_no_such_table';
		}
		if ( 'fail_read' === $this->mode && $marker && 0 === strpos( $query, 'SELECT' ) ) {
			return 'SELECT * FROM tsmlt_no_such_table';
		}
		if ( ! $marker && false !== strpos( $query, $wpdb->postmeta ) && in_array( $this->mode, [ 'observe', 'throw' ], true ) ) {
			if ( 'throw' === $this->mode ) {
				$this->mode = '';
				throw new RuntimeException( 'Rescan failed.' );
			}
			$this->inside = true;
			$this->seen   = $this->seen ?? PostRescanMarker::running();
			$this->inside = false;
		}
		return $query;
	}

	/**
	 * Remove every marker and scheduled rescan.
	 */
	private static function clear() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( PostRescanMarker::PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test cleanup.
		wp_unschedule_hook( self::HOOK );
	}

	/**
	 * Marker rows in the database.
	 *
	 * @return string[] Option names.
	 */
	private static function markers(): array {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( PostRescanMarker::PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test read.
	}

	/**
	 * A post showing a new attachment, with nothing pending.
	 *
	 * @return int[] Post ID and attachment ID.
	 */
	private function post_with_image(): array {
		$image = (int) self::factory()->attachment->create(
			[
				'file'           => 'rescan/image.jpg',
				'post_mime_type' => 'image/jpeg',
			]
		);
		$post  = (int) self::factory()->post->create( [ 'post_content' => '<img src="' . wp_get_attachment_url( $image ) . '" class="wp-image-' . $image . '"/>' ] );
		wp_unschedule_hook( self::HOOK );
		return [ $post, $image ];
	}

	/**
	 * A marker exists from start() to finish(); markers are separate rows,
	 * and only the owner's name removes one.
	 */
	public function test_marker_lifecycle_and_ownership() {
		$this->assertFalse( PostRescanMarker::running() );
		$one = PostRescanMarker::start();
		$two = PostRescanMarker::start();

		$this->assertMatchesRegularExpression( '/^tsmlt_post_rescan_running_[a-f0-9]{32}$/', $one );
		$this->assertNotSame( $one, $two );
		$this->assertTrue( PostRescanMarker::running() );
		PostRescanMarker::finish( $one );
		PostRescanMarker::finish( 'site_icon' );
		$this->assertTrue( PostRescanMarker::running(), 'The other run\'s marker is kept.' );
		$this->assertSame( [ $two ], self::markers() );
		PostRescanMarker::finish( $two );
		$this->assertFalse( PostRescanMarker::running() );
		$this->assertNotFalse( get_option( 'siteurl' ), 'Only marker names are removed.' );
	}

	/**
	 * A marker older than TTL (a run that died) or unreadable is ignored,
	 * and the next run prunes it.
	 */
	public function test_expired_markers_are_ignored_and_pruned() {
		global $wpdb;
		$rows = [
			'died'    => (string) ( time() - PostRescanMarker::TTL - 5 ),
			'garbage' => 'x',
		];
		foreach ( $rows as $suffix => $value ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test fixture.
				$wpdb->options,
				[
					'option_name'  => PostRescanMarker::PREFIX . $suffix,
					'option_value' => $value,
					'autoload'     => 'off',
				]
			);
		}

		$this->assertFalse( PostRescanMarker::running() );
		$name = PostRescanMarker::start();
		$this->assertSame( [ $name ], self::markers() );
		PostRescanMarker::finish( $name );
	}

	/**
	 * The deferred rescan runs marked: the marker is there while it reads
	 * the post, gone afterwards, and the usage is recorded.
	 */
	public function test_rescan_is_marked_while_it_runs() {
		list( $post, $image ) = $this->post_with_image();
		$this->mode           = 'observe';
		add_filter( 'query', [ $this, 'on_query' ] );

		do_action( self::HOOK, $post );

		remove_filter( 'query', [ $this, 'on_query' ] );
		$this->assertTrue( $this->seen, 'Marked while running.' );
		$this->assertSame( [], self::markers(), 'Removed afterwards.' );
		$this->assertContains( $post, array_map( 'intval', array_column( (array) get_post_meta( $image, UsedWhereScanner::META_KEY, true ), 'post_id' ) ) );
	}

	/**
	 * A rescan that throws still removes its marker.
	 */
	public function test_marker_is_removed_when_the_rescan_throws() {
		list( $post ) = $this->post_with_image();
		$this->mode   = 'throw';
		add_filter( 'query', [ $this, 'on_query' ] );

		try {
			do_action( self::HOOK, $post );
			$this->fail( 'The rescan was expected to throw.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'Rescan failed.', $e->getMessage() );
		}

		remove_filter( 'query', [ $this, 'on_query' ] );
		$this->assertSame( [], self::markers() );
	}

	/**
	 * When the marker cannot be stored, the rescan is not run unmarked: the
	 * post's usage records stay as they are and the rescan is scheduled
	 * again, so it stays pending.
	 */
	public function test_rescan_is_not_run_unmarked() {
		global $wpdb;
		list( $post, $image ) = $this->post_with_image();
		$records              = [
			[
				'post_id'    => $post,
				'post_title' => 'Earlier',
				'post_type'  => 'post',
				'usage_type' => 'content',
			],
		];
		update_post_meta( $image, UsedWhereScanner::META_KEY, $records );
		$this->mode = 'fail_insert';
		add_filter( 'query', [ $this, 'on_query' ] );
		$suppressed = $wpdb->suppress_errors( true );

		do_action( self::HOOK, $post );

		$wpdb->suppress_errors( $suppressed );
		remove_filter( 'query', [ $this, 'on_query' ] );
		$this->assertSame( $records, get_post_meta( $image, UsedWhereScanner::META_KEY, true ), 'Not scanned.' );
		$this->assertGreaterThan( time(), (int) wp_next_scheduled( self::HOOK, [ $post ] ), 'Scheduled again.' );
		$this->assertSame( [], self::markers() );
	}

	/**
	 * Saving a post that already has a rescan pending keeps that rescan:
	 * nothing is cleared or unscheduled, so a rescan is pending throughout.
	 */
	public function test_saving_keeps_the_pending_rescan() {
		$post = (int) self::factory()->post->create();
		$when = wp_next_scheduled( self::HOOK, [ $post ] );
		$this->assertNotFalse( $when );
		add_filter( 'pre_clear_scheduled_hook', [ $this, 'count_rescan_call' ], 10, 3 );
		add_filter( 'pre_unschedule_event', [ $this, 'count_rescan_call' ], 10, 3 );

		wp_update_post(
			[
				'ID'           => $post,
				'post_content' => 'Edited again.',
			]
		);

		$this->assertSame( [], $this->calls, 'Nothing was cleared or unscheduled.' );
		$this->assertSame( $when, wp_next_scheduled( self::HOOK, [ $post ] ) );
	}

	/**
	 * A failed read of the markers is unknown (null), never "not running".
	 */
	public function test_failed_read_is_unknown() {
		global $wpdb;
		$this->mode = 'fail_read';
		add_filter( 'query', [ $this, 'on_query' ] );
		$suppressed = $wpdb->suppress_errors( true );

		$running = PostRescanMarker::running();

		$wpdb->suppress_errors( $suppressed );
		$this->assertNull( $running );
	}
}
