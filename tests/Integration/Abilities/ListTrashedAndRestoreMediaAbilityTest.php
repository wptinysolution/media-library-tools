<?php
/**
 * Integration tests for tsmlt/list-trashed-media and tsmlt/restore-media:
 * permissions at both levels, closed and bounded input, per-attachment
 * read_post / delete_post, trash times and purge dates, pagination,
 * native restore through wp_untrash_post(), pre_untrash_post
 * short-circuits, exact per-item and summary results, idempotency and the
 * trashed-media cache flush.
 *
 * Every attachment is a generated fixture. Outgoing HTTP is blocked.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\ListTrashedMedia;
use TinySolutions\mlt\Abilities\Media\RestoreMedia;
use TinySolutions\mlt\Modules\Trash\MediaTrash;

/**
 * ListTrashedAndRestoreMediaAbilityTest
 */
class ListTrashedAndRestoreMediaAbilityTest extends WP_UnitTestCase {

	/**
	 * List ability.
	 *
	 * @var string
	 */
	const LIST = 'tsmlt/list-trashed-media';

	/**
	 * Restore ability.
	 *
	 * @var string
	 */
	const RESTORE = 'tsmlt/restore-media';

	/**
	 * Post IDs denied read_post.
	 *
	 * @var int[]
	 */
	private $no_read = [];

	/**
	 * Post IDs denied delete_post.
	 *
	 * @var int[]
	 */
	private $no_delete = [];

	/**
	 * Post IDs whose restore a pre_untrash_post filter stops.
	 *
	 * @var int[]
	 */
	private $blocked = [];

	/**
	 * Statements other than reads sent while observing.
	 *
	 * @var string[]
	 */
	private $writes = [];

	/**
	 * Administrator; filters for denied capabilities and blocked restores.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( self::LIST ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'map_meta_cap', [ $this, 'deny_caps' ], 10, 4 );
		add_filter( 'pre_untrash_post', [ $this, 'block_restore' ], 10, 2 );
		$this->no_read   = [];
		$this->no_delete = [];
		$this->blocked   = [];
		$this->writes    = [];
	}

	/**
	 * Remove filters.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		remove_filter( 'map_meta_cap', [ $this, 'deny_caps' ], 10 );
		remove_filter( 'pre_untrash_post', [ $this, 'block_restore' ], 10 );
		remove_filter( 'query', [ $this, 'record_query' ] );
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
	 * Deny read_post / delete_post on the listed posts.
	 *
	 * @param string[] $caps    Capabilities.
	 * @param string   $cap     Capability checked.
	 * @param int      $user_id User ID.
	 * @param array    $args    Arguments.
	 *
	 * @return string[]
	 */
	public function deny_caps( $caps, $cap, $user_id, $args ) {
		$id = (int) ( $args[0] ?? 0 );
		if ( ( 'read_post' === $cap && in_array( $id, $this->no_read, true ) ) || ( 'delete_post' === $cap && in_array( $id, $this->no_delete, true ) ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
	}

	/**
	 * Stop the restore of the posts in $this->blocked.
	 *
	 * @param mixed   $check Decision so far.
	 * @param WP_Post $post  Post.
	 *
	 * @return mixed
	 */
	public function block_restore( $check, $post ) {
		return in_array( (int) $post->ID, $this->blocked, true ) ? false : $check;
	}

	/**
	 * Record every statement that is not a read.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function record_query( $query ) {
		if ( ! preg_match( '/^\s*(SELECT|SHOW)\b/i', $query ) ) {
			$this->writes[] = $query;
		}
		return $query;
	}

	/**
	 * A generated attachment.
	 *
	 * @param bool $trashed Whether to trash it (natively).
	 *
	 * @return int
	 */
	private function attachment( bool $trashed = false ): int {
		static $n = 0;
		++$n;
		$id = (int) self::factory()->attachment->create(
			[
				'file'           => 'trash/image-' . $n . '.jpg',
				'post_mime_type' => 'image/jpeg',
			]
		);
		if ( $trashed ) {
			wp_trash_post( $id );
		}
		return $id;
	}

	/**
	 * A post's stored status, read fresh (get_post_status() reports a
	 * parentless attachment as publish).
	 *
	 * @param int $id Post ID.
	 *
	 * @return string
	 */
	private static function status( int $id ): string {
		clean_post_cache( $id );
		return (string) get_post( $id )->post_status;
	}

	/**
	 * Run an ability through the Abilities API.
	 *
	 * @param string     $name  Ability.
	 * @param array|null $input Input.
	 *
	 * @return mixed
	 */
	private static function call( string $name, $input = null ) {
		return wp_get_ability( $name )->execute( $input );
	}

	/**
	 * Only users with manage_options: refused through the permission
	 * callback and by a direct call, logged out or not; nothing restored.
	 */
	public function test_requires_manage_options() {
		$id = $this->attachment( true );
		foreach ( [ 'editor', 'author' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertSame( 'ability_invalid_permissions', self::call( self::LIST )->get_error_code(), $role );
			$this->assertSame( AbilityGuard::ERROR_FORBIDDEN, ListTrashedMedia::instance()->execute()->get_error_code(), $role );
			$this->assertSame( 'ability_invalid_permissions', self::call( self::RESTORE, [ 'ids' => [ $id ] ] )->get_error_code(), $role );
			$this->assertSame( AbilityGuard::ERROR_FORBIDDEN, RestoreMedia::instance()->execute( [ 'ids' => [ $id ] ] )->get_error_code(), $role );
		}
		wp_set_current_user( 0 );
		$this->assertSame( AbilityGuard::ERROR_UNAUTHENTICATED, ListTrashedMedia::instance()->execute()->get_error_code() );
		$this->assertSame( AbilityGuard::ERROR_UNAUTHENTICATED, RestoreMedia::instance()->execute( [ 'ids' => [ $id ] ] )->get_error_code() );
		$this->assertSame( 'trash', self::status( $id ) );
	}

	/**
	 * List input is closed and bounded, through the schema and directly.
	 */
	public function test_list_input_is_closed_and_bounded() {
		foreach ( [ [ 'x' => 1 ], [ 'page' => 0 ], [ 'page' => 10001 ], [ 'per_page' => 0 ], [ 'per_page' => 21 ] ] as $input ) {
			$this->assertSame( 'ability_invalid_input', self::call( self::LIST, $input )->get_error_code(), wp_json_encode( $input ) );
			$this->assertSame( AbilityGuard::ERROR_INVALID_INPUT, ListTrashedMedia::instance()->execute( $input )->get_error_code(), wp_json_encode( $input ) );
		}
		$this->assertSame( AbilityGuard::ERROR_INVALID_INPUT, self::call( self::LIST, [ 'page' => '2' ] )->get_error_code() );
		$result = self::call( self::LIST );
		$this->assertSame( 1, $result['page'] );
		$this->assertSame( 10, $result['per_page'] );
		$this->assertSame( [], self::call( self::LIST, [ 'page' => 10000 ] )['items'] );
	}

	/**
	 * Restore input is closed and bounded: missing, empty, oversized,
	 * duplicate and malformed IDs are refused before anything changes.
	 */
	public function test_restore_input_is_closed_and_bounded() {
		$id      = $this->attachment( true );
		$invalid = [
			[],
			[ 'ids' => [] ],
			[ 'ids' => range( 1, 21 ) ],
			[ 'ids' => [ $id, $id ] ],
			[ 'ids' => [ 0 ] ],
			[ 'ids' => [ -1 ] ],
			[
				'ids' => [ $id ],
				'x'   => 1,
			],
		];
		foreach ( $invalid as $input ) {
			$this->assertSame( 'ability_invalid_input', self::call( self::RESTORE, $input )->get_error_code(), wp_json_encode( $input ) );
			$this->assertSame( AbilityGuard::ERROR_INVALID_INPUT, RestoreMedia::instance()->execute( $input )->get_error_code(), wp_json_encode( $input ) );
		}
		foreach ( [ [ 'ids' => [ (string) $id ] ], [ 'ids' => [ 1.5 ] ], null ] as $input ) {
			$this->assertInstanceOf( WP_Error::class, self::call( self::RESTORE, $input ), wp_json_encode( $input ) );
			$this->assertSame( AbilityGuard::ERROR_INVALID_INPUT, RestoreMedia::instance()->execute( $input )->get_error_code(), wp_json_encode( $input ) );
		}
		$this->assertSame( 'trash', self::status( $id ) );
	}

	/**
	 * Only trashed attachments are listed, with when they were trashed and
	 * when WordPress may purge them (EMPTY_TRASH_DAYS later), and nothing
	 * beyond the summary fields.
	 */
	public function test_list_shows_trashed_media_with_times() {
		$this->attachment();
		$trashed = $this->attachment( true );
		$post    = self::factory()->post->create();
		wp_trash_post( $post );
		update_post_meta( $trashed, '_wp_trash_meta_time', 1700000000 );
		$untimed = $this->attachment( true );
		delete_post_meta( $untimed, '_wp_trash_meta_time' );

		$result = self::call( self::LIST );
		$items  = array_column( $result['items'], null, 'id' );

		$this->assertEqualsCanonicalizing( [ $trashed, $untimed ], array_keys( $items ) );
		$this->assertSame( 2, $result['total'] );
		$this->assertSame( 1, $result['total_pages'] );
		$this->assertSame( 0, $result['hidden'] );
		$this->assertSame( gmdate( DATE_ATOM, 1700000000 ), $items[ $trashed ]['trashed_at'] );
		$this->assertSame( gmdate( DATE_ATOM, 1700000000 + EMPTY_TRASH_DAYS * DAY_IN_SECONDS ), $items[ $trashed ]['purge_at'] );
		$this->assertSame( '', $items[ $untimed ]['trashed_at'] );
		$this->assertSame( '', $items[ $untimed ]['purge_at'] );
		$this->assertSame( [ 'id', 'title', 'alt_text', 'caption', 'mime_type', 'url', 'date', 'trashed_at', 'purge_at' ], array_keys( $items[ $trashed ] ) );
		$this->assertSame( MediaTrash::settings(), $result['trash'] );
		$this->assertTrue( $result['trash']['enabled'] );
	}

	/**
	 * Purge dates follow EMPTY_TRASH_DAYS, not the filter that only turns
	 * MCP trash off.
	 */
	public function test_purge_date_calculation() {
		$id = $this->attachment( true );
		update_post_meta( $id, '_wp_trash_meta_time', 1600000000 );
		$this->assertSame(
			[
				'trashed' => 1600000000,
				'purge'   => 1600000000 + EMPTY_TRASH_DAYS * DAY_IN_SECONDS,
			],
			MediaTrash::trash_times( $id )
		);
		add_filter( MediaTrash::DAYS_FILTER, '__return_zero' );
		$this->assertSame( 1600000000 + EMPTY_TRASH_DAYS * DAY_IN_SECONDS, MediaTrash::trash_times( $id )['purge'] );
		remove_filter( MediaTrash::DAYS_FILTER, '__return_zero' );
	}

	/**
	 * Trashed media the user cannot read is counted in hidden only.
	 */
	public function test_list_hides_unreadable_media() {
		$readable      = $this->attachment( true );
		$this->no_read = [ $this->attachment( true ) ];

		$result = self::call( self::LIST );

		$this->assertSame( [ $readable ], array_column( $result['items'], 'id' ) );
		$this->assertSame( 1, $result['hidden'] );
		$this->assertSame( 2, $result['total'] );
	}

	/**
	 * Pages split the trash without overlap.
	 */
	public function test_list_pagination() {
		$ids = [ $this->attachment( true ), $this->attachment( true ), $this->attachment( true ) ];

		$first  = self::call( self::LIST, [ 'per_page' => 2 ] );
		$second = self::call(
			self::LIST,
			[
				'per_page' => 2,
				'page'     => 2,
			]
		);

		$this->assertSame( 3, $first['total'] );
		$this->assertSame( 2, $first['total_pages'] );
		$this->assertCount( 2, $first['items'] );
		$this->assertCount( 1, $second['items'] );
		$this->assertEqualsCanonicalizing( $ids, array_merge( array_column( $first['items'], 'id' ), array_column( $second['items'], 'id' ) ) );
	}

	/**
	 * A restore goes through wp_untrash_post(): the attachment is back to
	 * inherit, WordPress's trash records are removed, and the
	 * trashed-media cache behind the front-end suppression is flushed.
	 */
	public function test_restore_is_native() {
		$id = $this->attachment( true );
		set_transient( 'tsmlt_trashed_attachments_count', '1', DAY_IN_SECONDS );

		$result = self::call( self::RESTORE, [ 'ids' => [ $id ] ] );

		$this->assertSame(
			[
				[
					'id'     => $id,
					'result' => 'restored',
					'reason' => '',
				],
			],
			$result['items']
		);
		$this->assertSame( 'inherit', self::status( $id ) );
		$this->assertSame( '', get_post_meta( $id, '_wp_trash_meta_status', true ) );
		$this->assertSame( '', get_post_meta( $id, '_wp_trash_meta_time', true ) );
		$this->assertFalse( get_transient( 'tsmlt_trashed_attachments_count' ) );
	}

	/**
	 * A mixed batch: each ID is handled and reported on its own, in order;
	 * missing, non-media and not-deletable IDs are refused alike (no hint of
	 * which exists); a stopped restore is blocked; nothing else changes.
	 */
	public function test_restore_mixed_batch() {
		$restored        = $this->attachment( true );
		$library         = $this->attachment();
		$post            = self::factory()->post->create();
		$denied          = $this->attachment( true );
		$stopped         = $this->attachment( true );
		$this->no_delete = [ $denied ];
		$this->blocked   = [ $stopped ];
		wp_trash_post( $post );

		$result = self::call( self::RESTORE, [ 'ids' => [ $restored, $library, 999999, $post, $denied, $stopped ] ] );

		$refused = [
			'result' => 'refused',
			'reason' => MediaTrash::REASON_NOT_AVAILABLE,
		];
		$this->assertSame(
			[
				[
					'id'     => $restored,
					'result' => 'restored',
					'reason' => '',
				],
				[
					'id'     => $library,
					'result' => 'not_trashed',
					'reason' => MediaTrash::REASON_NOT_TRASHED,
				],
				[ 'id' => 999999 ] + $refused,
				[ 'id' => $post ] + $refused,
				[ 'id' => $denied ] + $refused,
				[
					'id'     => $stopped,
					'result' => 'blocked',
					'reason' => MediaTrash::REASON_BLOCKED,
				],
			],
			$result['items']
		);
		$this->assertSame(
			[
				'total'       => 6,
				'restored'    => 1,
				'not_trashed' => 1,
				'refused'     => 3,
				'blocked'     => 1,
				'failed'      => 0,
			],
			$result['summary']
		);
		$this->assertSame( 'inherit', self::status( $restored ) );
		$this->assertSame( 'inherit', self::status( $library ) );
		$this->assertSame( 'trash', self::status( $post ) );
		$this->assertSame( 'trash', self::status( $denied ) );
		$this->assertSame( 'trash', self::status( $stopped ) );
	}

	/**
	 * Restoring again changes nothing: not_trashed, no write.
	 */
	public function test_restore_is_idempotent() {
		$id = $this->attachment( true );
		self::call( self::RESTORE, [ 'ids' => [ $id ] ] );
		add_filter( 'query', [ $this, 'record_query' ] );

		$again = self::call( self::RESTORE, [ 'ids' => [ $id ] ] );

		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertSame( 'not_trashed', $again['items'][0]['result'] );
		$this->assertSame( [], $this->writes );
		$this->assertSame( 'inherit', self::status( $id ) );
	}

	/**
	 * Listing writes nothing.
	 */
	public function test_list_is_read_only() {
		$this->attachment( true );
		add_filter( 'query', [ $this, 'record_query' ] );

		self::call( self::LIST );

		remove_filter( 'query', [ $this, 'record_query' ] );
		$this->assertSame( [], $this->writes );
	}

	/**
	 * Annotations and closed schemas as registered.
	 */
	public function test_annotations_and_schemas() {
		$list    = wp_get_ability( self::LIST );
		$restore = wp_get_ability( self::RESTORE );

		$this->assertSame(
			[
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			array_intersect_key( $list->get_meta()['annotations'], array_flip( [ 'readonly', 'destructive', 'idempotent' ] ) )
		);
		$this->assertSame(
			[
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			],
			array_intersect_key( $restore->get_meta()['annotations'], array_flip( [ 'readonly', 'destructive', 'idempotent' ] ) )
		);
		foreach ( [ $list, $restore ] as $ability ) {
			$this->assertFalse( $ability->get_input_schema()['additionalProperties'] );
			$this->assertFalse( $ability->get_output_schema()['additionalProperties'] );
			$this->assertFalse( $ability->get_meta()['mcp']['public'] );
			$this->assertFalse( $ability->get_meta()['show_in_rest'] );
		}
	}
}
