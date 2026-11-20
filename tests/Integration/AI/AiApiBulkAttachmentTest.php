<?php
/**
 * AiApi::generate_bulk() (used by Pro's bulk job for each item when its
 * batch runs) only works on library attachments, checked before the image
 * hook runs or any provider request is made. Runs with a licensed Pro
 * stand-in, in its own process.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Controllers\AI\AiApi;
use TinySolutions\mlt\Controllers\AI\Credentials;

require_once dirname( __DIR__, 2 ) . '/Support/AiRequestRecorder.php';
require_once dirname( __DIR__, 2 ) . '/Support/TsmltProCompressionStub.php';

/**
 * AiApiBulkAttachmentTest
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AiApiBulkAttachmentTest extends WP_UnitTestCase {

	/**
	 * Recorder.
	 *
	 * @var TsmltAiRequestRecorder
	 */
	private $recorder;

	/**
	 * Licensed Pro stand-in, no current user (as in a background batch),
	 * Gemini with a test key.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! defined( 'TSMLTPRO_VERSION' ) ) {
			define( 'TSMLTPRO_VERSION', '1.7.0' );
		}
		if ( ! function_exists( 'tsmltpro' ) ) {
			/**
			 * Stub of the Pro plugin accessor.
			 *
			 * @return TsmltProCompressionStub
			 */
			function tsmltpro() {
				return new TsmltProCompressionStub();
			}
		}
		TsmltProCompressionStub::$licensed = true;
		update_option( 'tsmlt_settings', [ 'ai_provider' => 'gemini' ] );
		if ( ! Credentials::set( 'gemini', 'test-key-123' ) ) {
			$this->markTestSkipped( 'Encrypted key storage is not available.' );
		}
		wp_set_current_user( 0 );
		$this->recorder        = new TsmltAiRequestRecorder();
		$this->recorder->reply = '{"alt_text": "Bulk alt"}';
		$this->recorder->start();
	}

	/**
	 * Stop recording.
	 */
	public function tear_down() {
		$this->recorder->stop();
		Credentials::remove( 'gemini' );
		parent::tear_down();
	}

	/**
	 * Bulk generation for an ID, returning the exception message or the result.
	 *
	 * @param int $id ID.
	 *
	 * @return array|string
	 */
	private static function bulk( int $id ) {
		try {
			return ( new AiApi() )->generate_bulk( $id, [ 'alt_text' ] );
		} catch ( \Exception $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * A library attachment is generated for; anything else is refused before
	 * the image hook or a provider request.
	 */
	public function test_bulk_validates_each_item() {
		$valid = (int) self::factory()->attachment->create_object(
			[
				'file'           => '2026/11/bulk.jpg',
				'post_mime_type' => 'image/jpeg',
			]
		);
		$this->assertSame( [ 'alt_text' => 'Bulk alt' ], self::bulk( $valid ) );
		$this->assertCount( 1, $this->recorder->requests );

		$this->recorder->requests = [];
		$this->recorder->prepared = [];
		$trashed                  = (int) self::factory()->attachment->create_object(
			[
				'file'           => '2026/11/bulk-trash.jpg',
				'post_mime_type' => 'image/jpeg',
			]
		);
		wp_trash_post( $trashed );
		$deleted = (int) self::factory()->attachment->create_object(
			[
				'file'           => '2026/11/bulk-gone.jpg',
				'post_mime_type' => 'image/jpeg',
			]
		);
		wp_delete_attachment( $deleted, true );

		foreach ( [ $trashed, $deleted, 999999, (int) self::factory()->post->create( [ 'post_status' => 'private' ] ) ] as $id ) {
			$this->assertSame( 'Media item not found.', self::bulk( $id ), (string) $id );
		}
		$this->assertSame( [], $this->recorder->requests, 'No provider request.' );
		$this->assertSame( [], $this->recorder->prepared, 'The image hook did not run.' );
	}
}
