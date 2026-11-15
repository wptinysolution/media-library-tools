<?php
/**
 * Integration tests for tsmlt/preview-trash-media and tsmlt/trash-media:
 * permissions at both levels, closed input, per-attachment delete_post,
 * the strict usage rule (used, unused, unknown, incomplete or changed
 * scan), site icon and logo protection, the EMPTY_TRASH_DAYS gate and the
 * permanent-deletion guard, the confirmation token (missing, unknown,
 * expired, wrong user, wrong IDs, reused, and an interleaved second
 * consumer), re-checks at execution, native trash records, the
 * trashed-media cache flush and exact per-item and summary results.
 *
 * Concurrency is simulated in one process by interleaving a second
 * consumer at the point a parallel request would race; it is not a
 * parallel test. Every attachment is a generated fixture. Outgoing HTTP is
 * blocked.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilityGuard;
use TinySolutions\mlt\Abilities\Media\PreviewTrashMedia;
use TinySolutions\mlt\Abilities\Media\TrashMedia;
use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Modules\Trash\MediaTrash;
use TinySolutions\mlt\Modules\Trash\TrashClaim;
use TinySolutions\mlt\Modules\UsedWhere\PostRescanMarker;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * TrashMediaAbilityTest
 */
class TrashMediaAbilityTest extends WP_UnitTestCase {

	/**
	 * Preview ability.
	 *
	 * @var string
	 */
	const PREVIEW = 'tsmlt/preview-trash-media';

	/**
	 * Trash ability.
	 *
	 * @var string
	 */
	const TRASH = 'tsmlt/trash-media';

	/**
	 * Post IDs denied delete_post.
	 *
	 * @var int[]
	 */
	private $no_delete = [];

	/**
	 * Post IDs whose trashing a pre_trash_post filter stops.
	 *
	 * @var int[]
	 */
	private $blocked = [];

	/**
	 * Post IDs moved to `private` right after they are trashed.
	 *
	 * @var int[]
	 */
	private $diverted = [];

	/**
	 * Calls of observed hooks, by hook.
	 *
	 * @var array<string, int>
	 */
	private $calls = [];

	/**
	 * Whether the batch read of postmeta fails.
	 *
	 * @var bool
	 */
	private $fail_meta = false;

	/**
	 * Token option a simulated parallel request removes first.
	 *
	 * @var string
	 */
	private $race_option = '';

	/**
	 * Attachment a simulated plugin tries to delete permanently while it is
	 * being trashed.
	 *
	 * @var int
	 */
	private $delete_during_trash = 0;

	/**
	 * Post ID whose UPDATE of wp_posts fails (0 for none).
	 *
	 * @var int
	 */
	private $fail_update = 0;

	/**
	 * Post saved (so its usage rescan is scheduled) once an attachment is
	 * trashed (0 for none).
	 *
	 * @var int
	 */
	private $save_on_trash = 0;

	/**
	 * Which read to make fail: 'cron' or 'markers' ('' for none).
	 *
	 * @var string
	 */
	private $fail_read = '';

	/**
	 * Attachment whose claim a simulated request takes over while this one
	 * holds it (0 for none).
	 *
	 * @var int
	 */
	private $steal_claim = 0;

	/**
	 * What a simulated admin does to the attachment during the call:
	 * 'trash_first' or 'restore_after' ('' for nothing).
	 *
	 * @var string
	 */
	private $interfere = '';

	/**
	 * Value the days filter returns in days_value().
	 *
	 * @var mixed
	 */
	private $days_value = null;

	/**
	 * Administrator; a verified usage scan; filters for the scenarios.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( self::TRASH ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		self::verified_scan();
		wp_unschedule_hook( MediaTrash::POST_RESCAN_HOOK );
		delete_option( 'site_icon' );
		delete_option( 'site_logo' );
		remove_theme_mod( 'custom_logo' );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'map_meta_cap', [ $this, 'deny_delete' ], 10, 4 );
		add_filter( 'pre_trash_post', [ $this, 'block_trash' ], 10, 2 );
		add_action( 'trashed_post', [ $this, 'divert' ] );
		add_filter( 'query', [ $this, 'break_meta_read' ] );
		$this->no_delete           = [];
		$this->blocked             = [];
		$this->diverted            = [];
		$this->calls               = [];
		$this->fail_meta           = false;
		$this->race_option         = '';
		$this->delete_during_trash = 0;
	}

	/**
	 * Remove filters.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		remove_filter( 'map_meta_cap', [ $this, 'deny_delete' ], 10 );
		remove_filter( 'pre_trash_post', [ $this, 'block_trash' ], 10 );
		remove_action( 'trashed_post', [ $this, 'divert' ] );
		remove_filter( 'query', [ $this, 'break_meta_read' ] );
		remove_action( 'delete_option', [ $this, 'race_delete' ] );
		remove_action( 'wp_trash_post', [ $this, 'try_permanent_delete' ] );
		remove_action( 'trashed_post', [ $this, 'change_scan' ] );
		remove_filter( MediaTrash::DAYS_FILTER, '__return_zero' );
		remove_filter( MediaTrash::DAYS_FILTER, [ $this, 'negative_days' ] );
		remove_filter( MediaTrash::DAYS_FILTER, [ $this, 'more_days' ] );
		remove_filter( MediaTrash::DAYS_FILTER, [ $this, 'days_value' ] );
		remove_filter( 'query', [ $this, 'break_post_update' ] );
		remove_action( 'trashed_post', [ $this, 'save_a_post' ] );
		remove_filter( 'query', [ $this, 'break_rescan_state_read' ] );
		remove_filter( 'pre_get_scheduled_event', [ $this, 'other_cron_backend' ] );
		remove_filter( 'map_meta_cap', [ $this, 'take_over_claim' ], 20 );
		remove_filter( 'pre_trash_post', [ $this, 'admin_trashes_first' ], 5 );
		remove_action( 'trashed_post', [ $this, 'admin_restores_after' ], 5 );
		wp_unschedule_hook( MediaTrash::POST_RESCAN_HOOK );
		self::clear_markers();
		foreach ( self::observed_hooks() as $hook ) {
			remove_filter( $hook, [ $this, 'count_call' ] );
		}
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
	 * Stop the trashing of the posts in $this->blocked.
	 *
	 * @param mixed   $check Decision so far.
	 * @param WP_Post $post  Post.
	 *
	 * @return mixed
	 */
	public function block_trash( $check, $post ) {
		return in_array( (int) $post->ID, $this->blocked, true ) ? false : $check;
	}

	/**
	 * Move a just-trashed post in $this->diverted to `private`, as a plugin
	 * might.
	 *
	 * @param int $post_id Post ID.
	 */
	public function divert( $post_id ) {
		global $wpdb;
		if ( in_array( (int) $post_id, $this->diverted, true ) ) {
			$wpdb->update( $wpdb->posts, [ 'post_status' => 'private' ], [ 'ID' => $post_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test fixture.
		}
	}

	/**
	 * Start another usage scan once a post is trashed.
	 */
	public function change_scan() {
		remove_action( 'trashed_post', [ $this, 'change_scan' ] );
		self::verified_scan();
	}

	/**
	 * Make the batch read of post meta fail.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function break_meta_read( $query ) {
		global $wpdb;
		if ( $this->fail_meta && false !== strpos( $query, "FROM {$wpdb->postmeta} WHERE post_id IN" ) ) {
			return 'SELECT * FROM tsmlt_no_such_table';
		}
		return $query;
	}

	/**
	 * Simulate a parallel request that consumes the same token first: on
	 * delete_option() of the token (after it was read and checked, right
	 * before its DELETE), remove the row.
	 *
	 * @param string $option Option being deleted.
	 */
	public function race_delete( $option ) {
		global $wpdb;
		if ( $option === $this->race_option ) {
			remove_action( 'delete_option', [ $this, 'race_delete' ] );
			$wpdb->delete( $wpdb->options, [ 'option_name' => $option ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Simulated parallel request.
		}
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
	 * Days filter: negative.
	 *
	 * @return int
	 */
	public function negative_days() {
		return -1;
	}

	/**
	 * Days filter: more than EMPTY_TRASH_DAYS.
	 *
	 * @return int
	 */
	public function more_days() {
		return EMPTY_TRASH_DAYS + 60;
	}

	/**
	 * Make WordPress's UPDATE of $this->fail_update in wp_posts fail.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function break_post_update( $query ) {
		global $wpdb;
		if ( $this->fail_update > 0 && 0 === strpos( $query, "UPDATE `{$wpdb->posts}` SET" ) && false !== strpos( $query, "WHERE `ID` = {$this->fail_update}" ) ) {
			return 'SELECT * FROM tsmlt_no_such_table';
		}
		return $query;
	}

	/**
	 * Save $this->save_on_trash once a post is trashed, which schedules that
	 * post's usage rescan.
	 */
	public function save_a_post() {
		remove_action( 'trashed_post', [ $this, 'save_a_post' ] );
		wp_update_post(
			[
				'ID'           => $this->save_on_trash,
				'post_content' => 'Edited during the batch.',
			]
		);
	}

	/**
	 * Make the read of the `cron` option or of the rescan markers fail.
	 *
	 * @param string $query SQL.
	 *
	 * @return string
	 */
	public function break_rescan_state_read( $query ) {
		$cron    = 'cron' === $this->fail_read && 0 === strpos( $query, 'SELECT' ) && false !== strpos( $query, "= 'cron'" );
		$markers = 'markers' === $this->fail_read && 0 === strpos( $query, 'SELECT' ) && false !== strpos( $query, ' LIKE ' ) && false !== strpos( $query, 'rescan' );
		return $cron || $markers ? 'SELECT * FROM tsmlt_no_such_table' : $query;
	}

	/**
	 * A plugin that keeps WordPress's events elsewhere.
	 *
	 * @param mixed $pre Pre-filtered value.
	 *
	 * @return mixed
	 */
	public function other_cron_backend( $pre ) {
		return $pre;
	}

	/**
	 * A request that takes over $this->steal_claim's claim while this one
	 * holds it (on the delete_post check made under the claim).
	 *
	 * @param string[] $caps Capabilities.
	 * @param string   $cap  Capability checked.
	 *
	 * @return string[]
	 */
	public function take_over_claim( $caps, $cap ) {
		global $wpdb;
		$option = TrashClaim::option( $this->steal_claim );
		if ( 'delete_post' === $cap && $this->steal_claim > 0 && null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Simulated request.
			$this->steal_claim = 0;
			$wpdb->update( $wpdb->options, [ 'option_value' => ( time() + 60 ) . ':other-request' ], [ 'option_name' => $option ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Simulated request.
		}
		return $caps;
	}

	/**
	 * An admin who trashes the attachment just before this call does (the
	 * call then finds nothing to do).
	 *
	 * @param mixed   $check Decision so far.
	 * @param WP_Post $post  Post.
	 *
	 * @return mixed
	 */
	public function admin_trashes_first( $check, $post ) {
		global $wpdb;
		if ( 'trash_first' === $this->interfere ) {
			$wpdb->update( $wpdb->posts, [ 'post_status' => 'trash' ], [ 'ID' => $post->ID ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Simulated admin.
			return false;
		}
		return $check;
	}

	/**
	 * An admin who restores the attachment right after this call trashed
	 * it.
	 *
	 * @param int $post_id Post ID.
	 */
	public function admin_restores_after( $post_id ) {
		global $wpdb;
		if ( 'restore_after' === $this->interfere ) {
			$wpdb->update( $wpdb->posts, [ 'post_status' => 'inherit' ], [ 'ID' => $post_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Simulated admin.
		}
	}

	/**
	 * Remove every rescan marker.
	 */
	private static function clear_markers() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( PostRescanMarker::PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test cleanup.
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
	 * Hooks that show a trash or deletion was attempted.
	 *
	 * @return string[]
	 */
	private static function observed_hooks(): array {
		return [ 'pre_trash_post', 'wp_trash_post', 'trashed_post', 'pre_delete_post', 'pre_delete_attachment', 'delete_attachment', 'before_delete_post', 'delete_post' ];
	}

	/**
	 * Start counting observed hooks.
	 */
	private function observe() {
		foreach ( self::observed_hooks() as $hook ) {
			add_filter( $hook, [ $this, 'count_call' ] );
		}
	}

	/**
	 * A complete, verified usage scan that covered every fixture.
	 *
	 * @param array $fields Fields to change.
	 */
	private static function verified_scan( array $fields = [] ) {
		update_option(
			UsageScanStatus::OPTION,
			array_merge(
				[
					'state'            => 'complete',
					'complete'         => true,
					'processed'        => 1,
					'total'            => 1,
					'scan_id'          => wp_generate_uuid4(),
					'started_at'       => '2099-01-01 00:00:00',
					'coverage_version' => UsedWhereScanner::COVERAGE_VERSION,
					'coverage_gaps'    => [],
				],
				$fields
			),
			false
		);
	}

	/**
	 * A generated attachment.
	 *
	 * @param array $args Post arguments.
	 *
	 * @return int
	 */
	private function attachment( array $args = [] ): int {
		static $n = 0;
		++$n;
		return (int) self::factory()->attachment->create(
			array_merge(
				[
					'file'           => 'trash/image-' . $n . '.jpg',
					'post_mime_type' => 'image/jpeg',
					'post_date'      => '2020-01-01 00:00:00',
				],
				$args
			)
		);
	}

	/**
	 * Record a usage of an attachment in a new post, as a completed rescan
	 * of that post would (nothing left pending).
	 *
	 * @param int $id Attachment ID.
	 */
	private function use_it( int $id ) {
		$post = self::factory()->post->create();
		wp_unschedule_hook( MediaTrash::POST_RESCAN_HOOK );
		update_post_meta(
			$id,
			UsedWhereScanner::META_KEY,
			[
				[
					'post_id'    => $post,
					'post_title' => 'Uses it',
					'post_type'  => 'post',
					'usage_type' => 'content',
				],
			]
		);
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
	 * Preview IDs and return the token.
	 *
	 * @param int[] $ids IDs.
	 *
	 * @return string
	 */
	private function token( array $ids ): string {
		$preview = self::call( self::PREVIEW, [ 'ids' => $ids ] );
		$this->assertIsString( $preview['confirmation_token'], wp_json_encode( $preview ) );
		return $preview['confirmation_token'];
	}

	/**
	 * Trash with a token.
	 *
	 * @param int[]  $ids   IDs.
	 * @param string $token Token.
	 *
	 * @return mixed
	 */
	private static function trash( array $ids, string $token ) {
		return self::call(
			self::TRASH,
			[
				'ids'                => $ids,
				'confirmation_token' => $token,
			]
		);
	}

	/**
	 * Stored payload of a token, read from the database.
	 *
	 * @param string $token Token.
	 *
	 * @return array|null
	 */
	private static function stored( string $token ): ?array {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", MediaTrash::TOKEN_OPTION_PREFIX . hash( 'sha256', $token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test read.
		return null === $value ? null : maybe_unserialize( $value );
	}

	/**
	 * Only users with manage_options: refused through the permission
	 * callback and by a direct call, logged out or not; nothing trashed and
	 * no token issued.
	 */
	public function test_requires_manage_options() {
		$id    = $this->attachment();
		$token = $this->token( [ $id ] );
		foreach ( [ 'editor', 'author' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertSame( 'ability_invalid_permissions', self::call( self::PREVIEW, [ 'ids' => [ $id ] ] )->get_error_code(), $role );
			$this->assertSame( AbilityGuard::ERROR_FORBIDDEN, PreviewTrashMedia::instance()->execute( [ 'ids' => [ $id ] ] )->get_error_code(), $role );
			$this->assertSame( 'ability_invalid_permissions', self::trash( [ $id ], $token )->get_error_code(), $role );
			$input = [
				'ids'                => [ $id ],
				'confirmation_token' => $token,
			];
			$this->assertSame( AbilityGuard::ERROR_FORBIDDEN, TrashMedia::instance()->execute( $input )->get_error_code(), $role );
		}
		wp_set_current_user( 0 );
		$this->assertSame( AbilityGuard::ERROR_UNAUTHENTICATED, PreviewTrashMedia::instance()->execute( [ 'ids' => [ $id ] ] )->get_error_code() );
		$this->assertSame(
			AbilityGuard::ERROR_UNAUTHENTICATED,
			TrashMedia::instance()->execute(
				[
					'ids'                => [ $id ],
					'confirmation_token' => $token,
				]
			)->get_error_code()
		);
		$this->assertSame( 'inherit', self::status( $id ) );
		$this->assertIsArray( self::stored( $token ), 'The token was not used up.' );
	}

	/**
	 * Closed input: missing, empty, oversized, duplicate and malformed IDs,
	 * missing or malformed tokens and unknown keys are refused, through the
	 * schema and by a direct call.
	 */
	public function test_input_is_closed_and_bounded() {
		$id      = $this->attachment();
		$token   = str_repeat( 'a', 64 );
		$invalid = [ [], [ 'ids' => [] ], [ 'ids' => range( 1, 21 ) ], [ 'ids' => [ $id, $id ] ], [ 'ids' => [ 0 ] ], [ 'ids' => [ -1 ] ] ];
		foreach ( $invalid as $input ) {
			$this->assertSame( 'ability_invalid_input', self::call( self::PREVIEW, $input )->get_error_code(), wp_json_encode( $input ) );
			$this->assertSame( AbilityGuard::ERROR_INVALID_INPUT, PreviewTrashMedia::instance()->execute( $input )->get_error_code(), wp_json_encode( $input ) );
			$input['confirmation_token'] = $token;
			$this->assertSame( 'ability_invalid_input', self::call( self::TRASH, $input )->get_error_code(), wp_json_encode( $input ) );
			$this->assertSame( AbilityGuard::ERROR_INVALID_INPUT, TrashMedia::instance()->execute( $input )->get_error_code(), wp_json_encode( $input ) );
		}
		$malformed = [
			[ 'ids' => [ (string) $id ] ],
			[
				'ids' => [ $id ],
				'x'   => 1,
			],
			null,
		];
		foreach ( $malformed as $input ) {
			$this->assertInstanceOf( WP_Error::class, self::call( self::PREVIEW, $input ), wp_json_encode( $input ) );
			$this->assertSame( AbilityGuard::ERROR_INVALID_INPUT, PreviewTrashMedia::instance()->execute( $input )->get_error_code(), wp_json_encode( $input ) );
		}
		$bad_tokens = [
			[ 'ids' => [ $id ] ],
			[
				'ids'                => [ $id ],
				'confirmation_token' => 'abc',
			],
			[
				'ids'                => [ $id ],
				'confirmation_token' => strtoupper( $token ),
			],
		];
		foreach ( $bad_tokens as $input ) {
			$this->assertSame( 'ability_invalid_input', self::call( self::TRASH, $input )->get_error_code(), wp_json_encode( $input ) );
		}
		$this->assertSame(
			MediaTrash::ERROR_TOKEN_INVALID,
			TrashMedia::instance()->execute(
				[
					'ids'                => [ $id ],
					'confirmation_token' => 'abc',
				]
			)->get_error_code()
		);
		$this->assertSame( 'inherit', self::status( $id ) );
	}

	/**
	 * An unused attachment under a verified scan is eligible; the token is
	 * random, stored hashed in a non-autoloaded option, bound to the user,
	 * the sorted IDs, the scan and a 10-minute expiry. Nothing else changes.
	 */
	public function test_preview_of_unused_media_issues_a_bound_token() {
		global $wpdb;
		$a = $this->attachment();
		$b = $this->attachment();

		$before  = time();
		$preview = self::call( self::PREVIEW, [ 'ids' => [ $b, $a ] ] );
		$token   = $preview['confirmation_token'];

		$this->assertSame( 2, $preview['eligible_count'] );
		$this->assertSame(
			[
				'id'              => $b,
				'eligible'        => true,
				'reason'          => '',
				'usage'           => 'unused',
				'usage_count'     => 0,
				'scan_verified'   => true,
				'frontend_impact' => 'none_recorded',
			],
			array_diff_key( $preview['items'][0], [ 'media' => 1 ] )
		);
		$this->assertSame( $b, $preview['items'][0]['media']['id'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $token );
		$this->assertNotSame( $token, $this->token( [ $a, $b ] ), 'Each preview issues a new token.' );
		$expires = strtotime( $preview['expires_at'] );
		$this->assertGreaterThanOrEqual( $before + 600, $expires );
		$this->assertLessThanOrEqual( time() + 600, $expires );

		$option  = MediaTrash::TOKEN_OPTION_PREFIX . hash( 'sha256', $token );
		$row     = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $option ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test read.
		$payload = maybe_unserialize( $row['option_value'] );
		$this->assertNotContains( $row['autoload'], wp_autoload_values_to_autoload() );
		$this->assertStringNotContainsString( $token, $row['option_value'], 'The token itself is not stored.' );
		$this->assertSame( get_current_user_id(), $payload['user_id'] );
		$this->assertSame( [ min( $a, $b ), max( $a, $b ) ], $payload['ids'] );
		$this->assertSame( get_option( UsageScanStatus::OPTION )['scan_id'], $payload['scan_id'] );
		$this->assertSame( $expires, $payload['expires'] );
		$this->assertSame( 'inherit', self::status( $a ) );
		$this->assertSame( 'inherit', self::status( $b ) );
	}

	/**
	 * Strict usage: only media a complete, verified scan found unused is
	 * eligible. Used, uploaded after the scan started, no scan, a running
	 * scan, a scan with coverage gaps and a stalled one are all refused, and
	 * no token is issued.
	 */
	public function test_strict_usage_rule() {
		$used   = $this->attachment();
		$unused = $this->attachment();
		$newer  = $this->attachment( [ 'post_date' => '2100-01-01 00:00:00' ] );
		$this->use_it( $used );

		$preview = self::call( self::PREVIEW, [ 'ids' => [ $used, $unused, $newer ] ] );
		$items   = array_column( $preview['items'], null, 'id' );
		$this->assertSame( [ false, MediaTrash::REASON_USED, 'used', 1, 'breaks_recorded_usage' ], [ $items[ $used ]['eligible'], $items[ $used ]['reason'], $items[ $used ]['usage'], $items[ $used ]['usage_count'], $items[ $used ]['frontend_impact'] ] );
		$this->assertTrue( $items[ $unused ]['eligible'] );
		$this->assertSame( [ false, MediaTrash::REASON_USAGE_UNKNOWN, 'unknown', false, 'unknown' ], [ $items[ $newer ]['eligible'], $items[ $newer ]['reason'], $items[ $newer ]['usage'], $items[ $newer ]['scan_verified'], $items[ $newer ]['frontend_impact'] ] );
		$this->assertNull( $preview['confirmation_token'] );
		$this->assertNull( $preview['expires_at'] );

		$scans = [
			'no scan' => null,
			'running' => [
				'state'    => 'running',
				'complete' => false,
			],
			'gaps'    => [ 'coverage_gaps' => [ 'woocommerce_inactive' ] ],
			'legacy'  => [ 'coverage_version' => 0 ],
			'no id'   => [ 'scan_id' => '' ],
			'stalled' => [
				'state'        => 'running',
				'complete'     => false,
				'last_tick_at' => '2000-01-01 00:00:00',
			],
		];
		foreach ( $scans as $label => $fields ) {
			if ( null === $fields ) {
				delete_option( UsageScanStatus::OPTION );
			} else {
				self::verified_scan( $fields );
			}
			$preview = self::call( self::PREVIEW, [ 'ids' => [ $unused ] ] );
			$this->assertSame( MediaTrash::REASON_USAGE_UNKNOWN, $preview['items'][0]['reason'], $label );
			$this->assertFalse( $preview['items'][0]['scan_verified'], $label );
			$this->assertNull( $preview['confirmation_token'], $label );
		}
	}

	/**
	 * The site icon and the site logo (custom_logo, site_logo) are refused.
	 */
	public function test_site_icon_and_logo_are_protected() {
		$icon  = $this->attachment();
		$logo  = $this->attachment();
		$block = $this->attachment();
		update_option( 'site_icon', $icon );
		set_theme_mod( 'custom_logo', $logo );
		update_option( 'site_logo', $block );

		$preview = self::call( self::PREVIEW, [ 'ids' => [ $icon, $logo, $block ] ] );

		$this->assertSame( array_fill( 0, 3, MediaTrash::REASON_PROTECTED ), array_column( $preview['items'], 'reason' ) );
		$this->assertNull( $preview['confirmation_token'] );
	}

	/**
	 * Missing, non-media and not-deletable IDs are refused alike, revealing
	 * nothing; trashed and other statuses are refused by status.
	 */
	public function test_availability_and_status_refusals() {
		$post            = self::factory()->post->create();
		$denied          = $this->attachment();
		$trashed         = $this->attachment();
		$private         = $this->attachment( [ 'post_status' => 'private' ] );
		$this->no_delete = [ $denied ];
		wp_unschedule_hook( MediaTrash::POST_RESCAN_HOOK );
		wp_trash_post( $trashed );

		$items = self::call( self::PREVIEW, [ 'ids' => [ 999999, $post, $denied, $trashed, $private ] ] )['items'];

		foreach ( [ 0, 1, 2 ] as $index ) {
			$this->assertSame(
				[
					'eligible'        => false,
					'reason'          => MediaTrash::REASON_NOT_AVAILABLE,
					'usage'           => 'unknown',
					'usage_count'     => 0,
					'scan_verified'   => false,
					'frontend_impact' => 'unknown',
					'media'           => null,
				],
				array_diff_key( $items[ $index ], [ 'id' => 1 ] ),
				(string) $index
			);
		}
		$this->assertSame( MediaTrash::REASON_ALREADY_TRASHED, $items[3]['reason'] );
		$this->assertSame( MediaTrash::REASON_INVALID_STATUS, $items[4]['reason'] );
	}

	/**
	 * A trash goes through wp_trash_post(): the attachment is in the trash
	 * with WordPress's records of its earlier status and time, the token is
	 * used up, and the trashed-media cache is flushed.
	 */
	public function test_trash_is_native() {
		$id    = $this->attachment();
		$token = $this->token( [ $id ] );
		set_transient( 'tsmlt_trashed_attachments_count', '0', DAY_IN_SECONDS );
		$before = time();

		$result = self::trash( [ $id ], $token );

		$this->assertSame(
			[
				[
					'id'     => $id,
					'result' => 'trashed',
					'reason' => '',
				],
			],
			$result['items']
		);
		$this->assertSame(
			[
				'total'           => 1,
				'trashed'         => 1,
				'already_trashed' => 0,
				'refused'         => 0,
				'blocked'         => 0,
				'failed'          => 0,
			],
			$result['summary']
		);
		$this->assertSame( 'trash', self::status( $id ) );
		$this->assertSame( 'inherit', get_post_meta( $id, '_wp_trash_meta_status', true ) );
		$this->assertGreaterThanOrEqual( $before, (int) get_post_meta( $id, '_wp_trash_meta_time', true ) );
		$this->assertFalse( get_transient( 'tsmlt_trashed_attachments_count' ) );
		$this->assertNull( self::stored( $token ) );
	}

	/**
	 * Trash days of 0 or below block everything before any write: the
	 * preview issues no token, and a trash with a valid token neither uses
	 * it nor reaches wp_trash_post() or any deletion. The filter can lower
	 * the days but never raise them.
	 */
	public function test_trash_days_gate() {
		$id    = $this->attachment();
		$token = $this->token( [ $id ] );

		foreach ( [ '__return_zero', [ $this, 'negative_days' ] ] as $filter ) {
			add_filter( MediaTrash::DAYS_FILTER, $filter );
			$this->observe();

			$preview = self::call( self::PREVIEW, [ 'ids' => [ $id ] ] );
			$result  = self::trash( [ $id ], $token );

			foreach ( self::observed_hooks() as $hook ) {
				remove_filter( $hook, [ $this, 'count_call' ] );
			}
			remove_filter( MediaTrash::DAYS_FILTER, $filter );
			$this->assertSame( MediaTrash::REASON_TRASH_DISABLED, $preview['items'][0]['reason'] );
			$this->assertFalse( $preview['trash']['enabled'] );
			$this->assertNull( $preview['confirmation_token'] );
			$this->assertSame( MediaTrash::ERROR_TRASH_DISABLED, $result->get_error_code() );
			$this->assertSame( [], $this->calls, 'No trash or deletion was attempted.' );
			$this->assertSame( 'inherit', self::status( $id ) );
			$this->assertIsArray( self::stored( $token ), 'The token was not used.' );
		}

		add_filter( MediaTrash::DAYS_FILTER, [ $this, 'more_days' ] );
		$this->assertSame( EMPTY_TRASH_DAYS, MediaTrash::trash_days() );
	}

	/**
	 * A trash days value that is undefined, empty or not a whole number
	 * counts as trash off. In a loaded WordPress EMPTY_TRASH_DAYS is always
	 * defined (core sets 30 unless wp-config.php sets it), so the undefined
	 * case is checked through the same resolver with a constant that does
	 * not exist; the other values are checked as constants and, end to end,
	 * as what the days filter returns.
	 */
	public function test_unusable_trash_days_count_as_off() {
		$this->assertSame( 0, MediaTrash::days_from( 'TSMLT_TEST_NO_SUCH_TRASH_DAYS' ) );
		$values = [
			'NULL'       => [ null, 0 ],
			'FALSE'      => [ false, 0 ],
			'EMPTY'      => [ '', 0 ],
			'TEXT'       => [ 'thirty', 0 ],
			'FLOAT'      => [ 1.5, 0 ],
			'FLOAT_TEXT' => [ '1.5', 0 ],
			'NEGATIVE'   => [ -3, -3 ],
			'TEXT_INT'   => [ '7', 7 ],
			'INT'        => [ 7, 7 ],
		];
		foreach ( $values as $name => $case ) {
			if ( ! defined( 'TSMLT_TEST_TRASH_DAYS_' . $name ) ) {
				define( 'TSMLT_TEST_TRASH_DAYS_' . $name, $case[0] );
			}
			$this->assertSame( $case[1], MediaTrash::days_from( 'TSMLT_TEST_TRASH_DAYS_' . $name ), $name );
		}

		$id    = $this->attachment();
		$token = $this->token( [ $id ] );
		add_filter( MediaTrash::DAYS_FILTER, [ $this, 'days_value' ] );
		foreach ( [ null, false, '', 'thirty', 1.5, [] ] as $value ) {
			$this->days_value = $value;
			$this->calls      = [];
			$this->observe();

			$preview = self::call( self::PREVIEW, [ 'ids' => [ $id ] ] );
			$result  = self::trash( [ $id ], $token );

			foreach ( self::observed_hooks() as $hook ) {
				remove_filter( $hook, [ $this, 'count_call' ] );
			}
			$label = wp_json_encode( $value );
			$this->assertSame( 0, MediaTrash::trash_days(), $label );
			$this->assertSame( MediaTrash::REASON_TRASH_DISABLED, $preview['items'][0]['reason'], $label );
			$this->assertNull( $preview['confirmation_token'], $label );
			$this->assertSame( MediaTrash::ERROR_TRASH_DISABLED, $result->get_error_code(), $label );
			$this->assertSame( [], $this->calls, $label . ': no trash or deletion was attempted.' );
			$this->assertSame( 'inherit', self::status( $id ), $label );
			$this->assertIsArray( self::stored( $token ), $label . ': the token was not used.' );
		}
		$this->days_value = '5';
		$this->assertSame( 5, MediaTrash::trash_days(), 'A whole number given as text still lowers the days.' );
	}

	/**
	 * Whether a post usage rescan is scheduled.
	 *
	 * @return bool
	 */
	private static function rescan_scheduled(): bool {
		foreach ( (array) _get_cron_array() as $hooks ) {
			if ( ! empty( $hooks[ MediaTrash::POST_RESCAN_HOOK ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A post is edited to show an unused image and its usage rescan has not
	 * run yet: the image is unknown, not unused, so the preview issues no
	 * token and a token issued before the edit cannot trash it. Once the
	 * rescan has run the image is used.
	 */
	public function test_pending_post_rescan_blocks_trash() {
		$image = $this->attachment();
		$post  = self::factory()->post->create( [ 'post_content' => 'Nothing yet.' ] );
		wp_unschedule_hook( MediaTrash::POST_RESCAN_HOOK );
		$token = $this->token( [ $image ] );

		wp_update_post(
			[
				'ID'           => $post,
				'post_content' => '<!-- wp:image {"id":' . $image . '} --><figure class="wp-block-image"><img src="' . wp_get_attachment_url( $image ) . '" class="wp-image-' . $image . '"/></figure><!-- /wp:image -->',
			]
		);
		$this->assertTrue( self::rescan_scheduled(), 'Saving the post scheduled its rescan.' );
		$this->assertSame( '', get_post_meta( $image, UsedWhereScanner::META_KEY, true ), 'The new usage is not recorded yet.' );

		$preview = self::call( self::PREVIEW, [ 'ids' => [ $image ] ] );
		$this->assertSame( [ false, MediaTrash::REASON_USAGE_UNKNOWN, 'unknown', false ], [ $preview['items'][0]['eligible'], $preview['items'][0]['reason'], $preview['items'][0]['usage'], $preview['items'][0]['scan_verified'] ] );
		$this->assertNull( $preview['confirmation_token'] );

		$result = self::trash( [ $image ], $token );
		$this->assertSame( [ 'refused', MediaTrash::REASON_USAGE_UNKNOWN ], [ $result['items'][0]['result'], $result['items'][0]['reason'] ] );
		$this->assertSame( 'inherit', self::status( $image ) );

		do_action( MediaTrash::POST_RESCAN_HOOK, $post );
		wp_unschedule_hook( MediaTrash::POST_RESCAN_HOOK );
		$after = self::call( self::PREVIEW, [ 'ids' => [ $image ] ] );
		$this->assertSame( [ MediaTrash::REASON_USED, 'used' ], [ $after['items'][0]['reason'], $after['items'][0]['usage'] ] );
	}

	/**
	 * A post saved during the batch schedules its rescan: the items not yet
	 * trashed are unknown and refused.
	 */
	public function test_post_rescan_scheduled_during_the_batch() {
		$a                   = $this->attachment();
		$b                   = $this->attachment();
		$this->save_on_trash = self::factory()->post->create();
		wp_unschedule_hook( MediaTrash::POST_RESCAN_HOOK );
		$token = $this->token( [ $a, $b ] );
		add_action( 'trashed_post', [ $this, 'save_a_post' ] );

		$result = self::trash( [ $a, $b ], $token );

		$this->assertSame(
			[
				[ $a, 'trashed', '' ],
				[ $b, 'refused', MediaTrash::REASON_USAGE_UNKNOWN ],
			],
			array_map( 'array_values', $result['items'] )
		);
		$this->assertSame( 'inherit', self::status( $b ) );
	}

	/**
	 * When WordPress cannot update the attachment (no filter stopped it),
	 * the item is failed with update_failed, not blocked.
	 */
	public function test_failed_update_is_not_reported_as_blocked() {
		global $wpdb;
		$id                = $this->attachment();
		$token             = $this->token( [ $id ] );
		$this->fail_update = $id;
		add_filter( 'query', [ $this, 'break_post_update' ] );
		$suppressed = $wpdb->suppress_errors( true );

		$result = self::trash( [ $id ], $token );

		$wpdb->suppress_errors( $suppressed );
		$this->assertSame( [ 'failed', MediaTrash::REASON_UPDATE_FAILED ], [ $result['items'][0]['result'], $result['items'][0]['reason'] ] );
		$this->assertSame( 'inherit', self::status( $id ) );
	}

	/**
	 * A claim is held for its whole lease, not 60 seconds: one taken minutes
	 * ago whose lease is still running is refused as in_progress and kept;
	 * one whose lease ran out (a request that died) is taken over; this
	 * request's claim is released after the call.
	 */
	public function test_claim_lease_and_stale_takeover() {
		$held   = $this->attachment();
		$stale  = $this->attachment();
		$token  = $this->token( [ $held, $stale ] );
		$values = [
			$held  => ( time() + TrashClaim::LEASE - 600 ) . ':long-running-request',
			$stale => ( time() - 1 ) . ':died',
		];
		foreach ( $values as $id => $value ) {
			add_option( TrashClaim::option( $id ), $value, '', false );
		}

		$result = self::trash( [ $held, $stale ], $token );

		$this->assertSame(
			[
				[ $held, 'refused', MediaTrash::REASON_IN_PROGRESS ],
				[ $stale, 'trashed', '' ],
			],
			array_map( 'array_values', $result['items'] )
		);
		$this->assertSame( 'inherit', self::status( $held ) );
		$this->assertSame( $values[ $held ], get_option( TrashClaim::option( $held ) ), 'The long-running request keeps its claim.' );
		$this->assertFalse( get_option( TrashClaim::option( $stale ) ), 'This request\'s claim was released.' );
	}

	/**
	 * A new claim's lease is LEASE (an hour), far beyond 60 seconds; the
	 * owner renews it, and the old value is then no longer the owner's.
	 */
	public function test_claim_renewal() {
		$id    = $this->attachment();
		$claim = TrashClaim::acquire( $id );

		$this->assertGreaterThanOrEqual( time() + TrashClaim::LEASE - 1, TrashClaim::held_until( $claim ) );
		$this->assertNull( TrashClaim::acquire( $id ) );
		$renewed = TrashClaim::renew( $id, $claim );
		$this->assertIsString( $renewed );
		$this->assertNull( TrashClaim::renew( $id, $claim ), 'The replaced value cannot renew.' );
		$this->assertSame( 0, TrashClaim::held_until( 'garbage' ) );
		TrashClaim::release( $id, $renewed );
		$this->assertFalse( get_option( TrashClaim::option( $id ) ) );
	}

	/**
	 * A claim taken over while this request held it is not trashed through:
	 * renewal fails, the item is refused as in_progress and the other
	 * request's claim is kept.
	 */
	public function test_lost_claim_is_not_trashed() {
		$id                = $this->attachment();
		$token             = $this->token( [ $id ] );
		$this->steal_claim = $id;
		add_filter( 'map_meta_cap', [ $this, 'take_over_claim' ], 20, 2 );

		$result = self::trash( [ $id ], $token );

		$this->assertSame( [ 'refused', MediaTrash::REASON_IN_PROGRESS ], [ $result['items'][0]['result'], $result['items'][0]['reason'] ] );
		$this->assertSame( 'inherit', self::status( $id ) );
		$this->assertStringEndsWith( ':other-request', (string) get_option( TrashClaim::option( $id ) ) );
	}

	/**
	 * Something else changing the attachment during the call is never
	 * reported as this call's success: trashed by an admin first is
	 * already_trashed; restored by an admin right after this call trashed
	 * it is failed, changed_during_call.
	 */
	public function test_concurrent_admin_changes_are_reported_accurately() {
		$first = $this->attachment();
		$after = $this->attachment();
		$token = $this->token( [ $first ] );
		add_filter( 'pre_trash_post', [ $this, 'admin_trashes_first' ], 5, 2 );
		add_action( 'trashed_post', [ $this, 'admin_restores_after' ], 5 );

		$this->interfere = 'trash_first';
		$one             = self::trash( [ $first ], $token );

		$this->assertSame( [ 'already_trashed', MediaTrash::REASON_ALREADY_TRASHED ], [ $one['items'][0]['result'], $one['items'][0]['reason'] ] );
		$this->assertSame( 'trash', self::status( $first ) );

		$token           = $this->token( [ $after ] );
		$this->interfere = 'restore_after';
		$two             = self::trash( [ $after ], $token );

		$this->assertSame( [ 'failed', MediaTrash::REASON_CHANGED_DURING_CALL ], [ $two['items'][0]['result'], $two['items'][0]['reason'] ] );
		$this->assertSame( 'inherit', self::status( $after ) );
	}

	/**
	 * A running usage rescan (its marker present) makes media without a
	 * recorded usage unknown: no token, and a token issued before is
	 * refused per item. Once the rescan finished, it is eligible again; an
	 * expired marker (a rescan that died) is ignored.
	 */
	public function test_running_rescan_blocks_preview_and_trash() {
		global $wpdb;
		$id     = $this->attachment();
		$token  = $this->token( [ $id ] );
		$marker = PostRescanMarker::start();

		$preview = self::call( self::PREVIEW, [ 'ids' => [ $id ] ] );
		$result  = self::trash( [ $id ], $token );

		$this->assertSame( MediaTrash::REASON_USAGE_UNKNOWN, $preview['items'][0]['reason'] );
		$this->assertNull( $preview['confirmation_token'] );
		$this->assertSame( [ 'refused', MediaTrash::REASON_USAGE_UNKNOWN ], [ $result['items'][0]['result'], $result['items'][0]['reason'] ] );
		$this->assertSame( 'inherit', self::status( $id ) );

		PostRescanMarker::finish( $marker );
		$this->assertTrue( self::call( self::PREVIEW, [ 'ids' => [ $id ] ] )['items'][0]['eligible'] );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test fixture.
			$wpdb->options,
			[
				'option_name'  => PostRescanMarker::PREFIX . 'died',
				'option_value' => (string) ( time() - PostRescanMarker::TTL - 5 ),
				'autoload'     => 'off',
			]
		);
		$this->assertTrue( self::call( self::PREVIEW, [ 'ids' => [ $id ] ] )['items'][0]['eligible'], 'An expired marker is ignored.' );
	}

	/**
	 * When the schedule or the rescan markers cannot be read, or another
	 * plugin handles WordPress's event storage, the rescan state cannot be
	 * verified: unrecorded media is unknown and nothing is trashed.
	 */
	public function test_unverifiable_rescan_state_fails_closed() {
		global $wpdb;
		$id    = $this->attachment();
		$token = $this->token( [ $id ] );
		add_filter( 'query', [ $this, 'break_rescan_state_read' ] );

		foreach ( [ 'cron', 'markers', 'backend' ] as $case ) {
			$this->fail_read = 'backend' === $case ? '' : $case;
			if ( 'backend' === $case ) {
				add_filter( 'pre_get_scheduled_event', [ $this, 'other_cron_backend' ] );
			}
			$suppressed = $wpdb->suppress_errors( true );

			$this->assertTrue( MediaTrash::post_rescan_pending(), $case );
			$preview = self::call( self::PREVIEW, [ 'ids' => [ $id ] ] );

			$wpdb->suppress_errors( $suppressed );
			$this->assertSame( MediaTrash::REASON_USAGE_UNKNOWN, $preview['items'][0]['reason'], $case );
			$this->assertNull( $preview['confirmation_token'], $case );
		}
		$suppressed = $wpdb->suppress_errors( true );
		$result     = self::trash( [ $id ], $token );
		$wpdb->suppress_errors( $suppressed );
		$this->assertSame( [ 'refused', MediaTrash::REASON_USAGE_UNKNOWN ], [ $result['items'][0]['result'], $result['items'][0]['reason'] ] );
		$this->assertSame( 'inherit', self::status( $id ) );

		$this->fail_read = '';
		remove_filter( 'pre_get_scheduled_event', [ $this, 'other_cron_backend' ] );
		$this->assertFalse( MediaTrash::post_rescan_pending(), 'Verifiable again.' );
	}

	/**
	 * Two consumers of one claim: only the first gets it, until it is
	 * released (sequential, in one process).
	 */
	public function test_claim_is_exclusive() {
		$id    = $this->attachment();
		$first = TrashClaim::acquire( $id );

		$this->assertIsString( $first );
		$this->assertNull( TrashClaim::acquire( $id ) );
		TrashClaim::release( $id, 'not-the-owner' );
		$this->assertNull( TrashClaim::acquire( $id ), 'Only the owner can release it.' );
		TrashClaim::release( $id, $first );
		$second = TrashClaim::acquire( $id );
		$this->assertIsString( $second );
		TrashClaim::release( $id, $second );
	}

	/**
	 * One issued token removes every expired token, oldest first, even
	 * when there are more than one batch of them, and stops at the first
	 * live one, which is kept (tokens expire in the order they are stored).
	 */
	public function test_many_expired_tokens_are_pruned() {
		global $wpdb;
		$payload = [
			'user_id' => 1,
			'ids'     => [ 1 ],
			'scan_id' => 'x',
			'expires' => time() - 1,
		];
		for ( $i = 0; $i < 150; $i++ ) {
			add_option( MediaTrash::TOKEN_OPTION_PREFIX . 'expired' . $i, $payload, '', false );
		}
		$live = MediaTrash::TOKEN_OPTION_PREFIX . 'live';
		add_option( $live, array_merge( $payload, [ 'expires' => time() + 600 ] ), '', false );

		$this->token( [ $this->attachment() ] );

		$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( MediaTrash::TOKEN_OPTION_PREFIX . 'expired' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test read.
		$this->assertSame( 0, $left );
		$this->assertIsArray( get_option( $live ) );
	}

	/**
	 * While an attachment is being trashed, its permanent deletion is
	 * refused, whoever asks; afterwards deletion works as usual again.
	 */
	public function test_permanent_deletion_is_refused_during_trash() {
		$id                        = $this->attachment();
		$token                     = $this->token( [ $id ] );
		$this->delete_during_trash = $id;
		add_action( 'wp_trash_post', [ $this, 'try_permanent_delete' ] );

		$result = self::trash( [ $id ], $token );

		$this->assertSame( 'trashed', $result['items'][0]['result'] );
		$this->assertSame( 'trash', self::status( $id ) );
		$this->assertFalse( has_filter( 'pre_delete_attachment', [ MediaTrash::class, 'refuse_permanent_delete' ] ) );
		$this->assertFalse( has_filter( 'pre_delete_post', [ MediaTrash::class, 'refuse_permanent_delete' ] ) );
		$other = $this->attachment();
		wp_delete_attachment( $other, true );
		$this->assertSame( '', self::status( $other ), 'The guard is gone after the call.' );
	}

	/**
	 * Token checks: unknown, expired, another user's, another set of IDs,
	 * and reuse are all refused with nothing changed. A mismatch does not
	 * use the token up; an expired one is removed.
	 */
	public function test_token_checks() {
		$id    = $this->attachment();
		$other = $this->attachment();
		$token = $this->token( [ $id ] );

		$this->assertSame( MediaTrash::ERROR_TOKEN_INVALID, self::trash( [ $id ], str_repeat( 'b', 64 ) )->get_error_code(), 'Unknown.' );
		$this->assertSame( MediaTrash::ERROR_TOKEN_INVALID, self::trash( [ $id, $other ], $token )->get_error_code(), 'More IDs.' );
		$this->assertSame( MediaTrash::ERROR_TOKEN_INVALID, self::trash( [ $other ], $token )->get_error_code(), 'Other IDs.' );
		$owner = get_current_user_id();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( MediaTrash::ERROR_TOKEN_INVALID, self::trash( [ $id ], $token )->get_error_code(), 'Other user.' );
		wp_set_current_user( $owner );
		$this->assertSame( 'inherit', self::status( $id ) );
		$this->assertIsArray( self::stored( $token ) );

		$this->assertSame( 'trashed', self::trash( [ $id ], $token )['items'][0]['result'] );
		$this->assertSame( MediaTrash::ERROR_TOKEN_INVALID, self::trash( [ $id ], $token )->get_error_code(), 'Reused.' );

		$expired = $this->token( [ $other ] );
		$option  = MediaTrash::TOKEN_OPTION_PREFIX . hash( 'sha256', $expired );
		update_option( $option, array_merge( self::stored( $expired ), [ 'expires' => time() - 1 ] ), false );
		$this->assertSame( MediaTrash::ERROR_TOKEN_INVALID, self::trash( [ $other ], $expired )->get_error_code(), 'Expired.' );
		$this->assertNull( self::stored( $expired ) );
		$this->assertSame( 'inherit', self::status( $other ) );
	}

	/**
	 * Two consumers of one token: only the first gets it, and a request that
	 * loses the race between its read and its delete is refused (a second
	 * consumer is interleaved at that point; simulated, not parallel).
	 */
	public function test_token_is_single_use_under_interleaving() {
		$id    = $this->attachment();
		$token = $this->token( [ $id ] );
		$this->assertIsArray( MediaTrash::consume_token( $token, [ $id ] ) );
		$this->assertIsString( MediaTrash::consume_token( $token, [ $id ] ) );

		$token             = $this->token( [ $id ] );
		$this->race_option = MediaTrash::TOKEN_OPTION_PREFIX . hash( 'sha256', $token );
		add_action( 'delete_option', [ $this, 'race_delete' ] );

		$result = self::trash( [ $id ], $token );

		$this->assertSame( MediaTrash::ERROR_TOKEN_INVALID, $result->get_error_code() );
		$this->assertSame( 'inherit', self::status( $id ) );
		$this->assertNull( self::stored( $token ) );
	}

	/**
	 * A new usage scan after the preview refuses the whole call; the token
	 * is used up and nothing is trashed.
	 */
	public function test_scan_changed_after_preview() {
		$id    = $this->attachment();
		$token = $this->token( [ $id ] );
		self::verified_scan();

		$this->assertSame( MediaTrash::ERROR_SCAN_CHANGED, self::trash( [ $id ], $token )->get_error_code() );
		$this->assertSame( 'inherit', self::status( $id ) );
		$this->assertNull( self::stored( $token ) );
	}

	/**
	 * A new usage scan during the call refuses the attachments not yet
	 * trashed.
	 */
	public function test_scan_changed_during_the_batch() {
		$a     = $this->attachment();
		$b     = $this->attachment();
		$token = $this->token( [ $a, $b ] );
		add_action( 'trashed_post', [ $this, 'change_scan' ] );

		$result = self::trash( [ $a, $b ], $token );

		$this->assertSame( [ 'trashed', 'refused' ], array_column( $result['items'], 'result' ) );
		$this->assertSame( MediaTrash::REASON_SCAN_CHANGED, $result['items'][1]['reason'] );
		$this->assertSame( 'inherit', self::status( $b ) );
	}

	/**
	 * Every rule is checked again at execution, and each attachment is
	 * reported as it ends up: trashed, refused (now used, now the site icon,
	 * no longer deletable), already trashed, blocked by pre_trash_post, or
	 * failed (moved elsewhere by another plugin). Nothing is rolled back.
	 */
	public function test_mixed_batch_after_preview() {
		$ids   = [];
		$names = [ 'ok', 'used', 'icon', 'denied', 'trashed', 'blocked', 'diverted' ];
		foreach ( $names as $name ) {
			$ids[ $name ] = $this->attachment();
		}
		$token = $this->token( array_values( $ids ) );
		$this->use_it( $ids['used'] );
		update_option( 'site_icon', $ids['icon'] );
		$this->no_delete = [ $ids['denied'] ];
		wp_trash_post( $ids['trashed'] );
		$this->blocked  = [ $ids['blocked'] ];
		$this->diverted = [ $ids['diverted'] ];

		$result = self::trash( array_values( $ids ), $token );

		$this->assertSame(
			[
				[ $ids['ok'], 'trashed', '' ],
				[ $ids['used'], 'refused', MediaTrash::REASON_USED ],
				[ $ids['icon'], 'refused', MediaTrash::REASON_PROTECTED ],
				[ $ids['denied'], 'refused', MediaTrash::REASON_NOT_AVAILABLE ],
				[ $ids['trashed'], 'already_trashed', MediaTrash::REASON_ALREADY_TRASHED ],
				[ $ids['blocked'], 'blocked', MediaTrash::REASON_BLOCKED ],
				[ $ids['diverted'], 'failed', MediaTrash::REASON_UNEXPECTED_STATUS ],
			],
			array_map( 'array_values', $result['items'] )
		);
		$this->assertSame(
			[
				'total'           => 7,
				'trashed'         => 1,
				'already_trashed' => 1,
				'refused'         => 3,
				'blocked'         => 1,
				'failed'          => 1,
			],
			$result['summary']
		);
		$this->assertSame( 'trash', self::status( $ids['ok'] ) );
		foreach ( [ 'used', 'icon', 'denied', 'blocked' ] as $name ) {
			$this->assertSame( 'inherit', self::status( $ids[ $name ] ), $name );
		}
		$this->assertSame( 'private', self::status( $ids['diverted'] ) );
	}

	/**
	 * A failed read of the usage records fails closed: no token, nothing
	 * trashed.
	 */
	public function test_failed_usage_read_fails_closed() {
		global $wpdb;
		$id              = $this->attachment();
		$token           = $this->token( [ $id ] );
		$this->fail_meta = true;
		$suppressed      = $wpdb->suppress_errors( true );

		$preview = self::call( self::PREVIEW, [ 'ids' => [ $id ] ] );
		$trash   = self::trash( [ $id ], $token );

		$wpdb->suppress_errors( $suppressed );
		$this->assertSame( MediaTrash::ERROR_READ_FAILED, $preview->get_error_code() );
		$this->assertSame( MediaTrash::ERROR_READ_FAILED, $trash->get_error_code() );
		$this->assertSame( 'inherit', self::status( $id ) );
	}

	/**
	 * Issuing a token removes expired ones.
	 */
	public function test_expired_tokens_are_pruned() {
		$id  = $this->attachment();
		$old = $this->token( [ $id ] );
		update_option( MediaTrash::TOKEN_OPTION_PREFIX . hash( 'sha256', $old ), array_merge( self::stored( $old ), [ 'expires' => time() - 1 ] ), false );
		$live = $this->token( [ $id ] );

		$this->token( [ $id ] );

		$this->assertNull( self::stored( $old ) );
		$this->assertIsArray( self::stored( $live ) );
	}

	/**
	 * Annotations and closed schemas as registered; neither is public on the
	 * default gateway.
	 */
	public function test_annotations_and_schemas() {
		$keys    = array_flip( [ 'readonly', 'destructive', 'idempotent' ] );
		$preview = wp_get_ability( self::PREVIEW );
		$trash   = wp_get_ability( self::TRASH );

		$this->assertSame(
			[
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			],
			array_intersect_key( $preview->get_meta()['annotations'], $keys )
		);
		$this->assertSame(
			[
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			],
			array_intersect_key( $trash->get_meta()['annotations'], $keys )
		);
		$this->assertSame( [ 'ids', 'confirmation_token' ], $trash->get_input_schema()['required'] );
		foreach ( [ $preview, $trash ] as $ability ) {
			$this->assertFalse( $ability->get_input_schema()['additionalProperties'] );
			$this->assertFalse( $ability->get_output_schema()['additionalProperties'] );
			$this->assertFalse( $ability->get_meta()['mcp']['public'] );
		}
	}
}
