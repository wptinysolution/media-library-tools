<?php
/**
 * AiApi::generate() only works on a library attachment the current user may
 * edit, checked in the method itself (not only by the AJAX guard), before
 * the image hook runs or any provider request is made.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Controllers\AI\AiApi;
use TinySolutions\mlt\Controllers\AI\Credentials;
use TinySolutions\mlt\Helpers\Fns;

require_once dirname( __DIR__, 2 ) . '/Support/AiRequestRecorder.php';

/**
 * AiApiAttachmentSecurityTest
 */
class AiApiAttachmentSecurityTest extends WP_UnitTestCase {

	/**
	 * Recorder.
	 *
	 * @var TsmltAiRequestRecorder
	 */
	private $recorder;

	/**
	 * Attachment IDs whose edit_post is denied.
	 *
	 * @var int[]
	 */
	private $denied = [];

	/**
	 * Administrator, Gemini with a test key, recording on.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		update_option( 'tsmlt_settings', [ 'ai_provider' => 'gemini' ] );
		if ( ! Credentials::set( 'gemini', 'test-key-123' ) ) {
			$this->markTestSkipped( 'Encrypted key storage is not available.' );
		}
		$this->denied   = [];
		$this->recorder = new TsmltAiRequestRecorder();
		$this->recorder->start();
		add_filter( 'map_meta_cap', [ $this, 'deny' ], 10, 4 );
	}

	/**
	 * Stop recording; remove the key.
	 */
	public function tear_down() {
		$this->recorder->stop();
		remove_filter( 'map_meta_cap', [ $this, 'deny' ], 10 );
		Credentials::remove( 'gemini' );
		parent::tear_down();
	}

	/**
	 * Deny edit_post on listed attachments.
	 *
	 * @param string[] $caps    Caps.
	 * @param string   $cap     Meta cap.
	 * @param int      $user_id User.
	 * @param array    $args    Args.
	 *
	 * @return string[]
	 */
	public function deny( $caps, $cap, $user_id, $args ) {
		if ( 'edit_post' === $cap && in_array( (int) ( $args[0] ?? 0 ), $this->denied, true ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
	}

	/**
	 * A library attachment.
	 *
	 * @return int
	 */
	private function attachment(): int {
		return (int) self::factory()->attachment->create_object(
			[
				'file'           => '2026/11/sec.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Secret title',
			]
		);
	}

	/**
	 * Generate, returning the exception message or the result.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return array|string
	 */
	private static function generate( int $id ) {
		try {
			return ( new AiApi() )->generate(
				[
					'attachment_id' => $id,
					'field_type'    => 'alt_text',
				]
			);
		} catch ( \Exception $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * Assert a refusal with a message and no hook or provider call.
	 *
	 * @param array|string $result  Result.
	 * @param string       $message Expected message.
	 */
	private function assert_refused( $result, string $message ) {
		$this->assertSame( $message, $result );
		$this->assertSame( [], $this->recorder->requests, 'No provider request.' );
		$this->assertSame( [], $this->recorder->prepared, 'The image hook did not run.' );
	}

	/**
	 * A library attachment the user may edit is generated for.
	 */
	public function test_valid_attachment_reaches_provider() {
		$id     = $this->attachment();
		$result = self::generate( $id );

		$this->assertSame( [ 'suggestions' => [ 'Suggested text' ] ], $result );
		$this->assertCount( 1, $this->recorder->requests );
		$this->assertSame( [ $id ], $this->recorder->prepared );
	}

	/**
	 * ID zero, a missing ID, and a deleted attachment are refused.
	 */
	public function test_missing_ids() {
		$this->assert_refused( self::generate( 0 ), 'Invalid parameters.' );
		$this->assert_refused( self::generate( 999999 ), 'Media item not found.' );
		$deleted = $this->attachment();
		wp_delete_attachment( $deleted, true );
		$this->assert_refused( self::generate( $deleted ), 'Media item not found.' );
	}

	/**
	 * Posts, pages and private posts are not attachments.
	 */
	public function test_non_attachments() {
		foreach ( [
			self::factory()->post->create(),
			self::factory()->post->create( [ 'post_type' => 'page' ] ),
			self::factory()->post->create(
				[
					'post_status' => 'private',
					'post_title'  => 'Private post',
				]
			),
		] as $id ) {
			$this->assert_refused( self::generate( (int) $id ), 'Media item not found.' );
		}
	}

	/**
	 * A trashed attachment is refused.
	 */
	public function test_trashed_attachment() {
		$id = $this->attachment();
		wp_trash_post( $id );
		$this->assert_refused( self::generate( $id ), 'Media item not found.' );
	}

	/**
	 * A direct call is refused when edit_post is denied for the item, for a
	 * user with manage_options but no edit_post on it, and with no user.
	 */
	public function test_permission_checked_in_the_method() {
		$id           = $this->attachment();
		$this->denied = [ $id ];
		$this->assertTrue( current_user_can( 'manage_options' ), 'Precondition: an administrator.' );
		$this->assert_refused( self::generate( $id ), 'You are not allowed to edit this media item.' );

		$this->denied = [];
		wp_set_current_user( 0 );
		$this->assert_refused( self::generate( $id ), 'You are not allowed to edit this media item.' );
	}
}
