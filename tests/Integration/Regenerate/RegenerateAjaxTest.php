<?php
/**
 * The Regenerate page's AJAX actions keep their responses: start, progress,
 * cancel, and a second start re-attaching to the running job instead of
 * restarting it.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;

/**
 * RegenerateAjaxTest
 */
class RegenerateAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator; no job.
	 */
	public function set_up() {
		parent::set_up();
		// _handleAjax() fires admin_init; keep core's update checks off the network.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( RegenerateThumbnails::STATE_OPTION );
		wp_clear_scheduled_hook( RegenerateThumbnails::TICK_HOOK );
	}

	/**
	 * Remove the job.
	 */
	public function tear_down() {
		delete_option( RegenerateThumbnails::STATE_OPTION );
		wp_clear_scheduled_hook( RegenerateThumbnails::TICK_HOOK );
		parent::tear_down();
	}

	/**
	 * Call an action and return the decoded response.
	 *
	 * @param string $action Action.
	 *
	 * @return array|null
	 */
	private function call( string $action ) {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['nonce']            = wp_create_nonce( Fns::NONCE_ID );
		$_POST['params']           = wp_slash( wp_json_encode( [] ) );
		$this->_last_response      = '';
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}
		return json_decode( $this->_last_response, true );
	}

	/**
	 * Start, re-attach, progress and cancel keep the page's response shape.
	 */
	public function test_page_actions() {
		self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'file'           => '2026/11/regen-ajax.jpg',
			]
		);

		$start = $this->call( 'tsmlt_regenerate_start' );
		$this->assertTrue( $start['success'] );
		foreach ( [ 'status', 'offset', 'total', 'started_at', 'updated_at', 'errors_count', 'success_count', 'deleted_total', 'recent_errors', 'recent_done', 'tick_scheduled' ] as $key ) {
			$this->assertArrayHasKey( $key, $start['data'], $key );
		}
		$this->assertSame( 'running', $start['data']['status'] );
		$this->assertSame( 1, $start['data']['total'] );
		$this->assertArrayNotHasKey( 'queue', $start['data'] );
		$this->assertArrayNotHasKey( 'user_id', $start['data'] );

		$again = $this->call( 'tsmlt_regenerate_start' );
		$this->assertTrue( $again['success'] );
		$this->assertSame( $start['data']['job_id'], $again['data']['job_id'], 'The running job is not restarted.' );

		$progress = $this->call( 'tsmlt_regenerate_get_progress' );
		$this->assertSame( 'running', $progress['data']['status'] );

		$cancel = $this->call( 'tsmlt_regenerate_cancel' );
		$this->assertSame( 'cancelled', $cancel['data']['status'] );
	}
}
