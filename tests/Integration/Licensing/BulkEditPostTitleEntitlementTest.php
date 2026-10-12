<?php
/**
 * Regression tests for "Bulk Edit by Post Title" (audit finding C-3).
 *
 * The admin UI lists `bulkEditPostTitle` as a Pro-only bulk action
 * (src/js/Component/ListTable/TheHeader.tsx, `proOnlyActions`, gated on
 * `tsmltParams.hasExtended`, which AssetsController sets from has_pro()).
 * The UI then calls `tsmlt_update_single_media` once per item with
 * `{ ID, bulkEditPostTitle: [ fields ] }`.
 *
 * Server path: admin-ajax `tsmlt_update_single_media` →
 * Ajax::update_single_media() (POST, nonce, manage_options) →
 * RenameModule::update_single_media() → handle_bulk_edit(), which copies the
 * parent post's title into the chosen fields. The handler is in Free.
 * update_single_media() now refuses the action unless tsmlt()->has_pro() is
 * true, before anything is read or written; previously the UI gate was the
 * only restriction, so an administrator could apply it with a crafted
 * request on a Free-only site.
 *
 * Pro is a synthetic `tsmltpro()` / `TSMLTPRO_VERSION`; the real Pro plugin
 * is never loaded. No file is renamed: the fixture has no file on disk, and
 * the tests assert the stored file path and slug are unchanged. Each test
 * runs in its own process because the synthetic function cannot be undefined.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Helpers\Fns;

/**
 * Synthetic stand-in for the object the Pro accessor returns.
 */
class BulkEditLicenceProbe {

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
 * BulkEditPostTitleEntitlementTest
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class BulkEditPostTitleEntitlementTest extends WP_Ajax_UnitTestCase {

	/**
	 * Title of the fixture's parent post — the value the action copies.
	 *
	 * @var string
	 */
	const PARENT_TITLE = 'Parent post title for C3';

	/**
	 * Every field the action can write.
	 *
	 * @var string[]
	 */
	const ALL_FIELDS = [ 'post_title', 'alt_text', 'caption', 'post_description' ];

	/**
	 * Fixture attachment ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Create an attachment attached to a published post.
	 */
	public function set_up() {
		parent::set_up();
		$parent_id = self::factory()->post->create(
			[
				'post_title'  => self::PARENT_TITLE,
				'post_status' => 'publish',
			]
		);
		$this->id  = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
				'post_title'     => 'Original title',
				'post_excerpt'   => 'Original caption',
				'post_content'   => 'Original description',
				'post_name'      => 'c3-probe',
				'post_parent'    => $parent_id,
				'file'           => '2026/10/c3-probe.jpg',
			]
		);
		update_post_meta( $this->id, '_wp_attachment_image_alt', 'Original alt' );
		wp_update_attachment_metadata(
			$this->id,
			[
				'width'  => 800,
				'height' => 600,
				'file'   => '2026/10/c3-probe.jpg',
			]
		);
	}

	/**
	 * Define the synthetic Pro accessor (inside a method, so the parent
	 * PHPUnit process never defines it).
	 *
	 * @param bool $licensed Synthetic licence state.
	 */
	private static function install_pro( bool $licensed ) {
		define( 'TSMLTPRO_VERSION', '1.3.3' );
		BulkEditLicenceProbe::$licensed = $licensed;
		if ( ! function_exists( 'tsmltpro' ) ) {
			/**
			 * Synthetic Pro accessor.
			 *
			 * @return BulkEditLicenceProbe
			 */
			function tsmltpro() {
				return new BulkEditLicenceProbe();
			}
		}
	}

	/**
	 * The four text fields.
	 *
	 * @return array<string, string>
	 */
	private function fields(): array {
		clean_post_cache( $this->id );
		wp_cache_delete( $this->id, 'post_meta' );
		$post = get_post( $this->id );
		return [
			'post_title'       => $post->post_title,
			'alt_text'         => (string) get_post_meta( $this->id, '_wp_attachment_image_alt', true ),
			'caption'          => $post->post_excerpt,
			'post_description' => $post->post_content,
		];
	}

	/**
	 * What must never change: slug, file, metadata, status, parent.
	 *
	 * @return array
	 */
	private function file_state(): array {
		clean_post_cache( $this->id );
		$post = get_post( $this->id );
		return [
			'post_name'     => $post->post_name,
			'post_status'   => $post->post_status,
			'post_parent'   => $post->post_parent,
			'guid'          => $post->guid,
			'attached_file' => get_attached_file( $this->id ),
			'metadata'      => wp_get_attachment_metadata( $this->id ),
		];
	}

	/**
	 * Call `tsmlt_update_single_media` through admin-ajax, as the UI does.
	 *
	 * @param array $fields    Fields to fill from the parent title.
	 * @param bool  $use_nonce Whether to send a valid nonce.
	 *
	 * @return array{response: array|null, died: string}
	 */
	private function call_bulk_edit( array $fields, bool $use_nonce = true ): array {
		return $this->call_ajax(
			'tsmlt_update_single_media',
			[
				'ID'                => $this->id,
				'bulkEditPostTitle' => $fields,
			],
			$use_nonce
		);
	}

	/**
	 * Call one of the plugin's admin-ajax actions with a JSON params payload.
	 *
	 * @param string $action    AJAX action.
	 * @param array  $params    Params payload.
	 * @param bool   $use_nonce Whether to send a valid nonce.
	 *
	 * @return array{response: array|null, died: string}
	 */
	private function call_ajax( string $action, array $params, bool $use_nonce = true ): array {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['nonce']            = $use_nonce ? wp_create_nonce( Fns::NONCE_ID ) : 'invalid-nonce';
		$_POST['params']           = wp_slash( wp_json_encode( $params ) );
		$this->_last_response      = '';
		$died                      = '';
		try {
			$this->_handleAjax( $action );
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
	 * Expected fields after copying the parent title into some of them.
	 *
	 * @param array $before  Fields before.
	 * @param array $written Fields written.
	 *
	 * @return array
	 */
	private static function with_parent_title( array $before, array $written ): array {
		foreach ( $written as $field ) {
			$before[ $field ] = self::PARENT_TITLE;
		}
		return $before;
	}

	/**
	 * Entitlement states.
	 *
	 * @return array
	 */
	public function data_entitlement_states(): array {
		return [
			'Pro absent'              => [ 'absent' ],
			'Pro present, unlicensed' => [ 'unlicensed' ],
			'Pro present, licensed'   => [ 'licensed' ],
		];
	}

	/**
	 * @return array
	 */
	public function data_unentitled_states(): array {
		return [
			'Pro absent'              => [ 'absent', 'This feature requires the Pro plugin.' ],
			'Pro present, unlicensed' => [ 'unlicensed', 'This feature requires an active Pro license.' ],
		];
	}

	/**
	 * Set up an entitlement state and check it.
	 *
	 * @param string $state absent, unlicensed or licensed.
	 */
	private function enter_state( string $state ) {
		if ( 'absent' !== $state ) {
			self::install_pro( 'licensed' === $state );
		}
		$this->assertSame( 'licensed' === $state, tsmlt()->has_pro(), 'Precondition: entitlement state.' );
	}

	/**
	 * Without a licensed Pro, an administrator's request — sent without the
	 * UI — is refused with an explicit message and changes nothing.
	 *
	 * @dataProvider data_unentitled_states
	 *
	 * @param string $state   Entitlement state.
	 * @param string $message Expected refusal message.
	 */
	public function test_admin_request_is_refused_without_licensed_pro( string $state, string $message ) {
		$this->enter_state( $state );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->fields();
		$files  = $this->file_state();

		$result = $this->call_bulk_edit( self::ALL_FIELDS );

		$this->assertTrue( $result['response']['success'], wp_json_encode( $result ) );
		$this->assertFalse( $result['response']['data']['updated'] );
		$this->assertSame( $message, $result['response']['data']['message'] );
		$this->assertSame( $before, $this->fields() );
		$this->assertSame( $files, $this->file_state() );
	}

	/**
	 * With a licensed Pro the action still works: the parent title is copied
	 * into every requested field, and the slug, file and metadata are never
	 * touched (this action does not rename).
	 */
	public function test_admin_request_is_applied_with_licensed_pro() {
		$this->enter_state( 'licensed' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->fields();
		$files  = $this->file_state();

		$result = $this->call_bulk_edit( self::ALL_FIELDS );

		$this->assertTrue( $result['response']['success'], wp_json_encode( $result ) );
		$this->assertNotEmpty( $result['response']['data']['updated'] );
		$this->assertSame( self::with_parent_title( $before, self::ALL_FIELDS ), $this->fields() );
		$this->assertSame( $files, $this->file_state() );
	}

	/**
	 * With a licensed Pro, only the requested fields are written.
	 */
	public function test_only_requested_fields_are_written() {
		$this->enter_state( 'licensed' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->fields();

		$this->call_bulk_edit( [ 'caption' ] );

		$this->assertSame( self::with_parent_title( $before, [ 'caption' ] ), $this->fields() );
	}

	/**
	 * Crafted payloads cannot get past the server-side gate on a Free-only
	 * site: client-supplied "Pro" flags are ignored, and other keys sent
	 * alongside bulkEditPostTitle are not applied either.
	 *
	 * @dataProvider data_crafted_payloads
	 *
	 * @param array $extra Keys added to the bulkEditPostTitle request.
	 */
	public function test_crafted_requests_cannot_bypass_the_gate( array $extra ) {
		$this->enter_state( 'absent' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->fields();
		$files  = $this->file_state();

		$result = $this->call_ajax(
			'tsmlt_update_single_media',
			array_merge(
				[
					'ID'                => $this->id,
					'bulkEditPostTitle' => self::ALL_FIELDS,
				],
				$extra
			)
		);

		$this->assertFalse( $result['response']['data']['updated'] );
		$this->assertSame( 'This feature requires the Pro plugin.', $result['response']['data']['message'] );
		$this->assertFalse( tsmlt()->has_pro(), 'A request must not be able to change entitlement.' );
		$this->assertSame( $before, $this->fields() );
		$this->assertSame( $files, $this->file_state() );
	}

	/**
	 * @return array
	 */
	public function data_crafted_payloads(): array {
		return [
			'client Pro flags'        => [
				[
					'hasExtended' => true,
					'has_pro'     => true,
					'isPro'       => true,
					'proVersion'  => '9.9.9',
				],
			],
			'single field as string'  => [ [ 'bulkEditPostTitle' => 'post_title' ] ],
			'with a direct title too' => [ [ 'title' => 'Smuggled title' ] ],
			'with groups too'         => [ [ 'post_categories' => [ 1 ] ] ],
		];
	}

	/**
	 * A user without manage_options is refused, with or without Pro, and
	 * nothing changes.
	 *
	 * @dataProvider data_entitlement_states
	 *
	 * @param string $state Entitlement state.
	 */
	public function test_user_without_manage_options_is_refused( string $state ) {
		if ( 'absent' !== $state ) {
			self::install_pro( 'licensed' === $state );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$before = $this->fields();
		$files  = $this->file_state();

		$result = $this->call_bulk_edit( self::ALL_FIELDS );

		$this->assertFalse( $result['response']['success'] );
		$this->assertSame( $before, $this->fields() );
		$this->assertSame( $files, $this->file_state() );
	}

	/**
	 * An administrator without a valid nonce is refused and nothing changes.
	 */
	public function test_invalid_nonce_is_refused() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->fields();
		$files  = $this->file_state();

		$result = $this->call_bulk_edit( self::ALL_FIELDS, false );

		$this->assertSame( '-1', $result['died'] );
		$this->assertSame( $before, $this->fields() );
		$this->assertSame( $files, $this->file_state() );
	}

	/**
	 * Without a valid parent post there is no title to copy, so nothing is
	 * written.
	 */
	public function test_no_valid_parent_writes_nothing() {
		$this->enter_state( 'licensed' );
		wp_update_post(
			[
				'ID'          => $this->id,
				'post_parent' => 0,
			]
		);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->fields();

		$result = $this->call_bulk_edit( self::ALL_FIELDS );

		$this->assertTrue( $result['response']['success'] );
		$this->assertFalse( $result['response']['data']['updated'] );
		$this->assertSame( $before, $this->fields() );
	}

	/**
	 * Ordinary Free single-field edits through the same endpoint still work
	 * on a Free-only site.
	 */
	public function test_free_single_field_update_still_works() {
		$this->enter_state( 'absent' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->fields();

		$result = $this->call_ajax(
			'tsmlt_update_single_media',
			[
				'ID'      => $this->id,
				'caption' => 'Free caption edit',
			]
		);

		$this->assertTrue( $result['response']['success'] );
		$this->assertNotEmpty( $result['response']['data']['updated'] );
		$before['caption'] = 'Free caption edit';
		$this->assertSame( $before, $this->fields() );
	}

	/**
	 * The ordinary Free bulk edit (`tsmlt_bulk_submit`, type `bulkedit`) still
	 * works on a Free-only site.
	 */
	public function test_free_bulk_edit_still_works() {
		$this->enter_state( 'absent' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->fields();
		$files  = $this->file_state();

		$result = $this->call_ajax(
			'tsmlt_bulk_submit',
			[
				'type' => 'bulkedit',
				'ids'  => [ $this->id ],
				'data' => [
					'post_title' => 'Free bulk title',
					'alt_text'   => 'Free bulk alt',
				],
			]
		);

		$this->assertTrue( $result['response']['success'] );
		$this->assertTrue( $result['response']['data']['updated'] );
		$before['post_title'] = 'Free bulk title';
		$before['alt_text']   = 'Free bulk alt';
		$this->assertSame( $before, $this->fields() );
		$this->assertSame( $files, $this->file_state() );
	}
}
