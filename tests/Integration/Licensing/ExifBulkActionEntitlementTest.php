<?php
/**
 * Regression tests for the Pro EXIF bulk actions dispatched by Free (audit
 * finding C-2).
 *
 * Path: admin-ajax `tsmlt_bulk_submit` → Ajax::media_submit_bulk_action()
 * (POST, nonce, manage_options) → Api::media_submit_bulk_action(). For the
 * types `bulk_edit_exif` / `bulk_remove_exif` it applies the filter
 * `tsmlt_bulk_exif_action`, and for `exif_to_caption` the filter
 * `tsmlt_exif_to_caption` — only when tsmlt()->has_pro() is true. It used to
 * delegate whenever `tsmltpro()` merely existed, so an installed but
 * unlicensed Pro received the request. (Pro's handlers now also refuse when
 * unlicensed; that defence lives in the Pro repository.)
 *
 * Pro is a synthetic `tsmltpro()` / `TSMLTPRO_VERSION`; the real Pro plugin
 * is never loaded. The filters are observed by a test double that writes
 * nothing, so no EXIF writer runs. Each test runs in its own process because
 * the synthetic function cannot be undefined.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Helpers\Fns;

/**
 * Synthetic stand-in for the object the Pro accessor returns.
 */
class ExifBulkLicenceProbe {

	/**
	 * Whether the synthetic licence is valid.
	 *
	 * @var bool
	 */
	public static $licensed = false;

	/**
	 * Mirrors the Pro licence check has_pro() calls.
	 *
	 * @return bool
	 */
	public function user_can_use_tsmltpro() {
		return self::$licensed;
	}
}

/**
 * ExifBulkActionEntitlementTest
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ExifBulkActionEntitlementTest extends WP_Ajax_UnitTestCase {

	/**
	 * Fixture attachment ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Calls that reached the Pro delegation filters.
	 *
	 * @var array<int, array>
	 */
	private $delegated = [];

	/**
	 * Fixture with EXIF-related meta, and recorders on the delegation filters.
	 */
	public function set_up() {
		parent::set_up();
		$this->id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
				'post_title'     => 'EXIF probe',
				'post_excerpt'   => 'Original caption',
				'file'           => '2026/10/exif-probe.jpg',
			]
		);
		update_post_meta( $this->id, '_tsmlt_exif_camera', 'Synthetic camera' );
		wp_update_attachment_metadata(
			$this->id,
			[
				'width'      => 800,
				'height'     => 600,
				'file'       => '2026/10/exif-probe.jpg',
				'image_meta' => [ 'camera' => 'Synthetic camera' ],
			]
		);

		$this->delegated = [];
		add_filter(
			'tsmlt_bulk_exif_action',
			function ( $result, $type, $ids ) {
				$this->delegated[] = [ 'tsmlt_bulk_exif_action', $type, $ids ];
				return self::double_result();
			},
			10,
			3
		);
		add_filter(
			'tsmlt_exif_to_caption',
			function ( $result, $ids ) {
				$this->delegated[] = [ 'tsmlt_exif_to_caption', 'exif_to_caption', $ids ];
				return self::double_result();
			},
			10,
			2
		);
	}

	/**
	 * What the test double returns in place of a real Pro handler.
	 *
	 * @return array
	 */
	private static function double_result(): array {
		return [
			'updated' => true,
			'message' => 'Handled by the test double.',
		];
	}

	/**
	 * Define the synthetic Pro accessor (inside a method, so the parent
	 * PHPUnit process never defines it).
	 *
	 * @param bool $licensed Synthetic licence state.
	 */
	private static function install_pro( bool $licensed ) {
		define( 'TSMLTPRO_VERSION', '1.3.3' );
		ExifBulkLicenceProbe::$licensed = $licensed;
		if ( ! function_exists( 'tsmltpro' ) ) {
			/**
			 * Synthetic Pro accessor.
			 *
			 * @return ExifBulkLicenceProbe
			 */
			function tsmltpro() {
				return new ExifBulkLicenceProbe();
			}
		}
	}

	/**
	 * Everything the EXIF actions could change on the fixture.
	 *
	 * @return array
	 */
	private function snapshot(): array {
		clean_post_cache( $this->id );
		wp_cache_delete( $this->id, 'post_meta' );
		$post = get_post( $this->id );
		$meta = get_post_meta( $this->id );
		ksort( $meta );
		return [
			'post'     => [ $post->post_title, $post->post_excerpt, $post->post_content, $post->post_name ],
			'file'     => get_attached_file( $this->id ),
			'metadata' => wp_get_attachment_metadata( $this->id ),
			'meta'     => $meta,
		];
	}

	/**
	 * Call `tsmlt_bulk_submit` through admin-ajax.
	 *
	 * @param array $params    Request params.
	 * @param bool  $use_nonce Whether to send a valid nonce.
	 *
	 * @return array{response: array|null, died: string}
	 */
	private function call_bulk_submit( array $params, bool $use_nonce = true ): array {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['nonce']            = $use_nonce ? wp_create_nonce( Fns::NONCE_ID ) : 'invalid-nonce';
		$_POST['params']           = wp_slash( wp_json_encode( $params ) );
		$this->_last_response      = '';
		$died                      = '';
		try {
			$this->_handleAjax( 'tsmlt_bulk_submit' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			$died = $e->getMessage();
		}
		return [
			'response' => json_decode( $this->_last_response, true ),
			'died'     => $died,
		];
	}

	/**
	 * @return array
	 */
	public function data_types(): array {
		return [
			'bulk_edit_exif'   => [ 'bulk_edit_exif' ],
			'bulk_remove_exif' => [ 'bulk_remove_exif' ],
			'exif_to_caption'  => [ 'exif_to_caption' ],
		];
	}

	/**
	 * Pro absent: Free refuses the action and never delegates.
	 *
	 * @dataProvider data_types
	 *
	 * @param string $type Bulk action type.
	 */
	public function test_pro_absent_does_not_delegate( string $type ) {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->snapshot();

		$result = $this->call_bulk_submit(
			[
				'type' => $type,
				'ids'  => [ $this->id ],
			]
		);

		$this->assertTrue( $result['response']['success'] );
		$this->assertFalse( $result['response']['data']['updated'] );
		$this->assertSame( 'This feature requires the Pro plugin.', $result['response']['data']['message'] );
		$this->assertSame( [], $this->delegated );
		$this->assertSame( $before, $this->snapshot() );
	}

	/**
	 * Pro present but unlicensed: Free refuses the action, never delegates
	 * to Pro, and nothing changes.
	 *
	 * @dataProvider data_types
	 *
	 * @param string $type Bulk action type.
	 */
	public function test_unlicensed_pro_is_not_delegated( string $type ) {
		self::install_pro( false );
		$this->assertFalse( tsmlt()->has_pro(), 'Precondition: Pro is present but unlicensed.' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->snapshot();

		$result = $this->call_bulk_submit(
			[
				'type' => $type,
				'ids'  => [ $this->id ],
			]
		);

		$this->assertTrue( $result['response']['success'] );
		$this->assertFalse( $result['response']['data']['updated'] );
		$this->assertSame( 'This feature requires an active Pro license.', $result['response']['data']['message'] );
		$this->assertSame( [], $this->delegated, 'The Pro handler must not be reached.' );
		$this->assertSame( $before, $this->snapshot() );
	}

	/**
	 * Pro present and licensed: Free delegates to the Pro handler and returns
	 * its result, as before.
	 *
	 * @dataProvider data_types
	 *
	 * @param string $type Bulk action type.
	 */
	public function test_licensed_pro_is_delegated( string $type ) {
		self::install_pro( true );
		$this->assertTrue( tsmlt()->has_pro(), 'Precondition: Pro is present and licensed.' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->snapshot();

		$result = $this->call_bulk_submit(
			[
				'type' => $type,
				'ids'  => [ $this->id ],
			]
		);

		$this->assertTrue( $result['response']['success'] );
		$this->assertSame( [ [ 'exif_to_caption' === $type ? 'tsmlt_exif_to_caption' : 'tsmlt_bulk_exif_action', $type, [ $this->id ] ] ], $this->delegated );
		$this->assertSame( self::double_result(), $result['response']['data'] );
		// The test double writes nothing.
		$this->assertSame( $before, $this->snapshot() );
	}

	/**
	 * A user without manage_options is refused before dispatch, even with Pro
	 * present.
	 *
	 * @dataProvider data_types
	 *
	 * @param string $type Bulk action type.
	 */
	public function test_user_without_manage_options_is_refused( string $type ) {
		self::install_pro( true );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$before = $this->snapshot();

		$result = $this->call_bulk_submit(
			[
				'type' => $type,
				'ids'  => [ $this->id ],
			]
		);

		$this->assertFalse( $result['response']['success'] );
		$this->assertSame( [], $this->delegated );
		$this->assertSame( $before, $this->snapshot() );
	}

	/**
	 * An administrator without a valid nonce is refused before dispatch.
	 *
	 * @dataProvider data_types
	 *
	 * @param string $type Bulk action type.
	 */
	public function test_invalid_nonce_is_refused( string $type ) {
		self::install_pro( true );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->snapshot();

		$result = $this->call_bulk_submit(
			[
				'type' => $type,
				'ids'  => [ $this->id ],
			],
			false
		);

		$this->assertSame( '-1', $result['died'] );
		$this->assertSame( [], $this->delegated );
		$this->assertSame( $before, $this->snapshot() );
	}
}
