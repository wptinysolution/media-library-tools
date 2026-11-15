<?php
/**
 * The admin trash actions (Used Where page `tsmlt_used_where_trash`, media
 * table bulk `tsmlt_bulk_submit` types `trash` and `inherit`) use
 * WordPress's own trash through AdminTrash: native trash records and
 * hooks, the trashed-media cache refreshed, refused with nothing changed
 * while trash is off or misconfigured, non-media and not-deletable IDs
 * skipped, partial results reported, never a permanent deletion. The
 * labelled permanent-delete actions are unchanged.
 *
 * Every post and attachment is a generated fixture. Outgoing HTTP is
 * blocked.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Trash\AdminTrash;

/**
 * AdminTrashAjaxTest
 */
class AdminTrashAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Post IDs denied delete_post.
	 *
	 * @var int[]
	 */
	private $no_delete = [];

	/**
	 * Calls of observed hooks, by hook.
	 *
	 * @var array<string, int>
	 */
	private $calls = [];

	/**
	 * Value the days filter returns in days_value().
	 *
	 * @var mixed
	 */
	private $days_value = null;

	/**
	 * Attachment a simulated plugin tries to delete permanently while it is
	 * being trashed (0 for none).
	 *
	 * @var int
	 */
	private $delete_during_trash = 0;

	/**
	 * Administrator; filters for the scenarios.
	 */
	public function set_up() {
		parent::set_up();
		// _handleAjax() fires admin_init; keep core's update checks off the network.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'map_meta_cap', [ $this, 'deny_delete' ], 10, 4 );
		$this->no_delete           = [];
		$this->calls               = [];
		$this->days_value          = null;
		$this->delete_during_trash = 0;
	}

	/**
	 * Remove filters.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		remove_filter( 'map_meta_cap', [ $this, 'deny_delete' ], 10 );
		remove_filter( AdminTrash::DAYS_FILTER, [ $this, 'days_value' ] );
		remove_action( 'wp_trash_post', [ $this, 'try_permanent_delete' ] );
		$this->stop_observing();
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
	 * Deny delete_post on the posts in $this->no_delete.
	 *
	 * @param string[] $caps    Capabilities.
	 * @param string   $cap     Capability checked.
	 * @param int      $user_id User ID.
	 * @param array    $args    Arguments.
	 *
	 * @return string[]
	 */
	public function deny_delete( $caps, $cap, $user_id, $args ) {
		if ( 'delete_post' === $cap && in_array( (int) ( $args[0] ?? 0 ), $this->no_delete, true ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
	}

	/**
	 * Days filter: $this->days_value.
	 *
	 * @return mixed
	 */
	public function days_value() {
		return $this->days_value;
	}

	/**
	 * Count a call of an observed hook.
	 *
	 * @param mixed $value Filtered value, returned unchanged.
	 *
	 * @return mixed
	 */
	public function count_call( $value = null ) {
		$this->calls[ current_filter() ] = ( $this->calls[ current_filter() ] ?? 0 ) + 1;
		return $value;
	}

	/**
	 * A plugin that tries to delete the attachment permanently while it is
	 * being trashed.
	 *
	 * @param int $post_id Post ID.
	 */
	public function try_permanent_delete( $post_id ) {
		if ( (int) $post_id === $this->delete_during_trash ) {
			wp_delete_attachment( $post_id, true );
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Hooks that show a trash, restore or deletion.
	 *
	 * @return string[]
	 */
	private static function observed_hooks(): array {
		return [ 'pre_trash_post', 'wp_trash_post', 'trashed_post', 'untrashed_post', 'pre_delete_post', 'pre_delete_attachment', 'delete_attachment', 'before_delete_post', 'delete_post' ];
	}

	/**
	 * Start counting observed hooks.
	 */
	private function observe() {
		$this->calls = [];
		foreach ( self::observed_hooks() as $hook ) {
			add_filter( $hook, [ $this, 'count_call' ] );
		}
	}

	/**
	 * Stop counting observed hooks.
	 */
	private function stop_observing() {
		foreach ( self::observed_hooks() as $hook ) {
			remove_filter( $hook, [ $this, 'count_call' ] );
		}
	}

	/**
	 * A generated attachment.
	 *
	 * @param bool $trashed Whether to trash it natively.
	 *
	 * @return int
	 */
	private function attachment( bool $trashed = false ): int {
		static $n = 0;
		++$n;
		$id = (int) self::factory()->attachment->create(
			[
				'file'           => 'admin-trash/image-' . $n . '.jpg',
				'post_mime_type' => 'image/jpeg',
			]
		);
		if ( $trashed ) {
			wp_trash_post( $id );
		}
		return $id;
	}

	/**
	 * A post's stored status, read fresh, or '' when it is gone.
	 *
	 * @param int $id Post ID.
	 *
	 * @return string
	 */
	private static function status( int $id ): string {
		clean_post_cache( $id );
		$post = get_post( $id );
		return $post ? (string) $post->post_status : '';
	}

	/**
	 * Call an admin-ajax action.
	 *
	 * @param string $action      Action.
	 * @param array  $params      Request params.
	 * @param bool   $valid_nonce Whether to send a valid nonce.
	 *
	 * @return array Decoded response (`success`, `data`) and raw body.
	 */
	private function ajax( string $action, array $params, bool $valid_nonce = true ): array {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['nonce']            = $valid_nonce ? wp_create_nonce( Fns::NONCE_ID ) : 'invalid';
		$_POST['params']           = wp_slash( wp_json_encode( $params ) );
		$this->_last_response      = '';
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = $e->getMessage();
		}
		$decoded = json_decode( $this->_last_response, true );
		return [
			'success' => is_array( $decoded ) ? ( $decoded['success'] ?? null ) : null,
			'data'    => is_array( $decoded ) ? ( $decoded['data'] ?? null ) : null,
			'raw'     => $this->_last_response,
		];
	}

	/**
	 * Run the media table's bulk action.
	 *
	 * @param string $type Bulk type.
	 * @param int[]  $ids  IDs.
	 *
	 * @return array{updated: bool, message: string}
	 */
	private function bulk( string $type, array $ids ): array {
		return $this->ajax(
			'tsmlt_bulk_submit',
			[
				'type' => $type,
				'ids'  => $ids,
			]
		)['data'];
	}

	/**
	 * Used Where Trash uses WordPress's trash: records of the earlier
	 * status and time, the trash hooks, the trashed-media cache refreshed,
	 * the response shape kept, and no deletion.
	 */
	public function test_used_where_trash_is_native() {
		$id = $this->attachment();
		set_transient( 'tsmlt_trashed_attachments_count', '0', DAY_IN_SECONDS );
		$before = time();
		$this->observe();

		$result = $this->ajax( 'tsmlt_used_where_trash', [ 'ids' => [ $id ] ] );

		$this->stop_observing();
		$this->assertTrue( $result['success'] );
		$this->assertSame( [ 'trashed' => [ $id ] ], $result['data'] );
		$this->assertSame( 'trash', self::status( $id ) );
		$this->assertSame( 'inherit', get_post_meta( $id, '_wp_trash_meta_status', true ) );
		$this->assertGreaterThanOrEqual( $before, (int) get_post_meta( $id, '_wp_trash_meta_time', true ) );
		$this->assertSame( 1, $this->calls['wp_trash_post'] ?? 0 );
		$this->assertSame( 1, $this->calls['trashed_post'] ?? 0 );
		$this->assertFalse( get_transient( 'tsmlt_trashed_attachments_count' ), 'The trashed-media cache was refreshed.' );
		foreach ( [ 'pre_delete_post', 'pre_delete_attachment', 'delete_attachment', 'before_delete_post', 'delete_post' ] as $hook ) {
			$this->assertArrayNotHasKey( $hook, $this->calls, $hook );
		}
	}

	/**
	 * The bulk Move to Trash uses WordPress's trash too (it used to write
	 * the status directly, with no trash records or hooks).
	 */
	public function test_bulk_trash_is_native() {
		$ids = [ $this->attachment(), $this->attachment() ];
		set_transient( 'tsmlt_trashed_attachments_count', '0', DAY_IN_SECONDS );
		$this->observe();

		$result = $this->bulk( 'trash', $ids );

		$this->stop_observing();
		$this->assertSame(
			[
				'updated' => true,
				'message' => 'Done. Be happy.',
			],
			$result
		);
		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', self::status( $id ) );
			$this->assertSame( 'inherit', get_post_meta( $id, '_wp_trash_meta_status', true ) );
			$this->assertNotSame( '', get_post_meta( $id, '_wp_trash_meta_time', true ) );
		}
		$this->assertSame( 2, $this->calls['trashed_post'] ?? 0 );
		$this->assertArrayNotHasKey( 'delete_attachment', $this->calls );
		$this->assertFalse( get_transient( 'tsmlt_trashed_attachments_count' ) );
	}

	/**
	 * Trash days of 0, negative or not a whole number turn both Trash
	 * actions off: a clear error, nothing changed, no trash or deletion
	 * hook.
	 */
	public function test_trash_off_or_misconfigured_is_refused() {
		$id = $this->attachment();
		add_filter( AdminTrash::DAYS_FILTER, [ $this, 'days_value' ] );

		foreach ( [ 0, -1, null, false, '', 'thirty', 1.5, [] ] as $value ) {
			$this->days_value = $value;
			$label            = wp_json_encode( $value );
			$this->observe();

			$used = $this->ajax( 'tsmlt_used_where_trash', [ 'ids' => [ $id ] ] );
			$bulk = $this->bulk( 'trash', [ $id ] );

			$this->stop_observing();
			$this->assertFalse( $used['success'], $label );
			$this->assertStringContainsString( 'Trash is disabled on this site', $used['data']['message'], $label );
			$this->assertFalse( $bulk['updated'], $label );
			$this->assertStringContainsString( 'Trash is disabled on this site', $bulk['message'], $label );
			$this->assertSame( [], $this->calls, $label . ': no trash or deletion hook.' );
			$this->assertSame( 'inherit', self::status( $id ), $label );
		}
	}

	/**
	 * The filter can lower the trash days but never raise them; a whole
	 * number given as text counts.
	 */
	public function test_filter_lowers_but_never_raises() {
		add_filter( AdminTrash::DAYS_FILTER, [ $this, 'days_value' ] );

		$this->days_value = EMPTY_TRASH_DAYS + 100;
		$this->assertSame( EMPTY_TRASH_DAYS, AdminTrash::trash_days() );
		$this->days_value = '5';
		$this->assertSame( 5, AdminTrash::trash_days() );
		$this->assertTrue( AdminTrash::enabled() );
	}

	/**
	 * Without a valid nonce, or for a user without manage_options, nothing
	 * is trashed or restored.
	 */
	public function test_nonce_and_capability_checks() {
		$id      = $this->attachment();
		$trashed = $this->attachment( true );

		$this->assertSame( '-1', $this->ajax( 'tsmlt_used_where_trash', [ 'ids' => [ $id ] ], false )['raw'] );
		$this->assertSame(
			'-1',
			$this->ajax(
				'tsmlt_bulk_submit',
				[
					'type' => 'trash',
					'ids'  => [ $id ],
				],
				false
			)['raw']
		);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$used = $this->ajax( 'tsmlt_used_where_trash', [ 'ids' => [ $id ] ] );
		$bulk = $this->ajax(
			'tsmlt_bulk_submit',
			[
				'type' => 'inherit',
				'ids'  => [ $trashed ],
			]
		);

		$this->assertFalse( $used['success'] );
		$this->assertSame( 'Unauthorized.', $used['data']['message'] );
		$this->assertFalse( $bulk['success'] );
		$this->assertSame( 'inherit', self::status( $id ) );
		$this->assertSame( 'trash', self::status( $trashed ) );
	}

	/**
	 * Non-media IDs, missing IDs, attachments the user may not delete and
	 * already-trashed ones are skipped, and partial results say so.
	 */
	public function test_skips_and_partial_trash() {
		$ok              = $this->attachment();
		$denied          = $this->attachment();
		$already         = $this->attachment( true );
		$post            = self::factory()->post->create();
		$this->no_delete = [ $denied ];

		$used = $this->ajax( 'tsmlt_used_where_trash', [ 'ids' => [ $ok, $denied, $already, $post, 999999 ] ] );

		$this->assertSame( [ 'trashed' => [ $ok ] ], $used['data'] );
		$this->assertSame( 'inherit', self::status( $denied ) );
		$this->assertSame( 'publish', self::status( $post ), 'A post is never trashed through a media action.' );

		$other = $this->attachment();
		$bulk  = $this->bulk( 'trash', [ $other, $denied, $post ] );

		$this->assertFalse( $bulk['updated'] );
		$this->assertSame( 'Moved 1 of 3 items to the trash. The others were skipped: not media, already in the trash, or you may not delete them.', $bulk['message'] );
		$this->assertSame( 'trash', self::status( $other ) );
		$this->assertSame( 'inherit', self::status( $denied ) );
		$this->assertSame( 'publish', self::status( $post ) );
	}

	/**
	 * Both Trash actions refuse a permanent deletion of the attachment
	 * being trashed, whoever asks; it ends up in the trash.
	 */
	public function test_trash_never_deletes_permanently() {
		add_action( 'wp_trash_post', [ $this, 'try_permanent_delete' ] );

		$this->delete_during_trash = $this->attachment();
		$used                      = $this->ajax( 'tsmlt_used_where_trash', [ 'ids' => [ $this->delete_during_trash ] ] );
		$this->assertSame( [ $this->delete_during_trash ], $used['data']['trashed'] );
		$this->assertSame( 'trash', self::status( $this->delete_during_trash ) );

		$this->delete_during_trash = $this->attachment();
		$this->assertTrue( $this->bulk( 'trash', [ $this->delete_during_trash ] )['updated'] );
		$this->assertSame( 'trash', self::status( $this->delete_during_trash ) );

		$this->assertFalse( has_filter( 'pre_delete_attachment', [ AdminTrash::class, 'refuse_permanent_delete' ] ), 'The guard is removed after the call.' );
	}

	/**
	 * Bulk Restore uses wp_untrash_post(): back to inherit, WordPress's
	 * trash records removed, the restore hook and the cache refresh; items
	 * not in the trash, not deletable or not media are skipped and the
	 * partial result reported.
	 */
	public function test_bulk_restore_is_native() {
		$ok              = $this->attachment( true );
		$library         = $this->attachment();
		$denied          = $this->attachment( true );
		$this->no_delete = [ $denied ];
		set_transient( 'tsmlt_trashed_attachments_count', '1', DAY_IN_SECONDS );
		$this->observe();

		$partial = $this->bulk( 'inherit', [ $ok, $library, $denied ] );

		$this->stop_observing();
		$this->assertFalse( $partial['updated'] );
		$this->assertSame( 'Restored 1 of 3 items. The others were skipped: not media, not in the trash, or you may not restore them.', $partial['message'] );
		$this->assertSame( 'inherit', self::status( $ok ) );
		$this->assertSame( '', get_post_meta( $ok, '_wp_trash_meta_status', true ) );
		$this->assertSame( '', get_post_meta( $ok, '_wp_trash_meta_time', true ) );
		$this->assertSame( 1, $this->calls['untrashed_post'] ?? 0 );
		$this->assertFalse( get_transient( 'tsmlt_trashed_attachments_count' ) );
		$this->assertSame( 'trash', self::status( $denied ) );

		$this->no_delete = [];
		$this->assertSame(
			[
				'updated' => true,
				'message' => 'Done. Be happy.',
			],
			$this->bulk( 'inherit', [ $denied ] )
		);
		$this->assertSame( 'inherit', self::status( $denied ) );
	}

	/**
	 * Media put in the trash by the old direct status write (no trash
	 * records) is restored to the library too.
	 */
	public function test_restore_of_media_trashed_without_records() {
		global $wpdb;
		$id = $this->attachment();
		$wpdb->update( $wpdb->posts, [ 'post_status' => 'trash' ], [ 'ID' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture: the old direct write.

		$this->assertTrue( $this->bulk( 'inherit', [ $id ] )['updated'] );
		$this->assertSame( 'inherit', self::status( $id ) );
	}

	/**
	 * The labelled permanent-delete actions still delete permanently.
	 */
	public function test_permanent_delete_actions_are_unchanged() {
		$bulk = $this->attachment();
		$used = $this->attachment();

		$this->assertSame(
			[
				'updated' => true,
				'message' => 'Deleted. Be happy.',
			],
			$this->bulk( 'delete', [ $bulk ] )
		);
		$this->assertSame( '', self::status( $bulk ) );

		$result = $this->ajax( 'tsmlt_used_where_bulk_delete', [ 'ids' => [ $used ] ] );
		$this->assertSame( [ 'deleted' => 1 ], $result['data'] );
		$this->assertSame( '', self::status( $used ) );
	}
}
