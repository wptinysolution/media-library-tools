<?php
/**
 * Used Where scans keep attachments' existing parents ("Uploaded to"):
 * starting, clearing and running a scan never clears or replaces a
 * non-zero post_parent. An attachment without a parent gets one only from a
 * post that uses it; site-wide usages never set one. Classification is
 * unchanged.
 *
 * Scans run through the same scanner from the admin page and from
 * tsmlt/start-usage-scan; both are exercised. WP-Cron does not run in tests:
 * batches are run directly. Outgoing HTTP is blocked. Every post and
 * attachment is a generated fixture.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\UsageScanStatus;
use TinySolutions\mlt\Modules\UsedWhere\UsageClassifier;
use TinySolutions\mlt\Modules\UsedWhere\UsedWhereScanner;

/**
 * UsedWhereParentPreservationTest
 */
class UsedWhereParentPreservationTest extends WP_UnitTestCase {

	/**
	 * Administrator; no scan; no HTTP; fast scan.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( UsageScanStatus::OPTION );
		delete_option( 'site_logo' );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
		add_filter( 'pre_http_request', [ $this, 'block_http' ] );
		add_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'block_http' ] );
		remove_filter( 'tsmlt_scan_permalink_enabled_default', '__return_false' );
		wp_unschedule_hook( UsedWhereScanner::SCAN_TICK_HOOK );
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
	 * An image uploaded (attached) to a post, or unattached.
	 *
	 * @param string $file   File.
	 * @param int    $parent Parent post.
	 *
	 * @return int
	 */
	private function image( string $file, int $parent = 0 ): int {
		return (int) self::factory()->attachment->create(
			[
				'file'           => 'parent/' . $file,
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => $parent,
				'post_date'      => '2020-01-01 00:00:00',
				'post_date_gmt'  => '2020-01-01 00:00:00',
			]
		);
	}

	/**
	 * Parent of an attachment, from the database.
	 *
	 * @param int $id Attachment.
	 *
	 * @return int
	 */
	private static function parent_of( int $id ): int {
		clean_post_cache( $id );
		return (int) get_post( $id )->post_parent;
	}

	/**
	 * Run every scheduled batch.
	 */
	private static function run_batches() {
		for ( $i = 0; $i < 50; $i++ ) {
			$tick = null;
			foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
				foreach ( $hooks[ UsedWhereScanner::SCAN_TICK_HOOK ] ?? [] as $event ) {
					$tick = [ $timestamp, (int) $event['args'][0] ];
					break 2;
				}
			}
			if ( ! $tick ) {
				return;
			}
			wp_unschedule_event( $tick[0], UsedWhereScanner::SCAN_TICK_HOOK, [ $tick[1] ] );
			UsedWhereScanner::instance()->run_tick_batch( $tick[1] );
		}
	}

	/**
	 * Fixtures: image A uploaded to post A but used as post B's featured
	 * image (with usage records from an earlier scan), an unattached image
	 * used by post C, an unattached image used only as the site logo, and an
	 * image uploaded to post A and used nowhere.
	 *
	 * @return array<string, int>
	 */
	private function fixtures(): array {
		$f            = [];
		$f['post_a']  = (int) self::factory()->post->create( [ 'post_title' => 'Post A' ] );
		$f['post_b']  = (int) self::factory()->post->create( [ 'post_title' => 'Post B' ] );
		$f['post_c']  = (int) self::factory()->post->create( [ 'post_title' => 'Post C' ] );
		$f['moved']   = $this->image( 'moved.jpg', $f['post_a'] );
		$f['loose']   = $this->image( 'loose.jpg' );
		$f['logo']    = $this->image( 'logo.jpg' );
		$f['unused']  = $this->image( 'unused.jpg', $f['post_a'] );
		set_post_thumbnail( $f['post_b'], $f['moved'] );
		set_post_thumbnail( $f['post_c'], $f['loose'] );
		update_option( 'site_logo', $f['logo'] );
		// Records from an earlier scan, so the wipe has rows to remove.
		foreach ( [ 'moved', 'unused' ] as $key ) {
			update_post_meta( $f[ $key ], UsedWhereScanner::META_KEY, [ [ 'post_id' => $f['post_a'], 'post_title' => 'Post A', 'post_type' => 'post', 'usage_type' => 'content' ] ] );
		}
		return $f;
	}

	/**
	 * Assert the parents after a scan and the classification.
	 *
	 * @param array $f Fixtures.
	 */
	private function assert_after_scan( array $f ) {
		$this->assertSame( 'complete', UsageScanStatus::read()['state'] );

		$this->assertSame( $f['post_a'], self::parent_of( $f['moved'] ), 'Uploaded to post A, used on post B: still post A.' );
		$this->assertSame( [ $f['post_b'] ], array_map( 'intval', wp_list_pluck( get_post_meta( $f['moved'], UsedWhereScanner::META_KEY, true ), 'post_id' ) ) );
		$this->assertSame( $f['post_c'], self::parent_of( $f['loose'] ), 'No parent before: set to the post that uses it.' );
		$this->assertSame( 0, self::parent_of( $f['logo'] ), 'Site-wide usage sets no parent.' );
		$this->assertSame( 0, (int) get_post_meta( $f['logo'], UsedWhereScanner::META_KEY, true )[0]['post_id'], 'Recorded as site-wide.' );
		$this->assertSame( $f['post_a'], self::parent_of( $f['unused'] ), 'No usage: parent kept.' );

		// Classification is unaffected by parents.
		$context = UsageClassifier::context();
		$this->assertTrue( $context['verified'] );
		$this->assertSame( [ 'used' => 3, 'unused' => 1, 'unknown' => 0 ], UsageClassifier::counts( '', $context ) );
	}

	/**
	 * Admin path (Re-scan): the wipe keeps parents, and the scan keeps or
	 * fills them as documented.
	 */
	public function test_admin_scan_keeps_existing_parents() {
		$f = $this->fixtures();

		UsedWhereScanner::instance()->start_scheduled_scan();
		$this->assertSame( $f['post_a'], self::parent_of( $f['moved'] ), 'Kept by the wipe.' );
		$this->assertSame( '', get_post_meta( $f['moved'], UsedWhereScanner::META_KEY, true ), 'Old records wiped.' );

		self::run_batches();

		$this->assert_after_scan( $f );
	}

	/**
	 * MCP path: tsmlt/start-usage-scan behaves the same.
	 */
	public function test_mcp_scan_keeps_existing_parents() {
		$f = $this->fixtures();

		$started = wp_get_ability( 'tsmlt/start-usage-scan' )->execute();
		$this->assertSame( 'started', $started['result'] );
		self::run_batches();

		$this->assert_after_scan( $f );
	}

	/**
	 * Clearing the results (admin "Clear Results") keeps parents too.
	 */
	public function test_clear_results_keeps_parents() {
		$f = $this->fixtures();

		UsedWhereScanner::instance()->clear_scan();

		$this->assertSame( $f['post_a'], self::parent_of( $f['moved'] ) );
		$this->assertSame( $f['post_a'], self::parent_of( $f['unused'] ) );
		$this->assertSame( '', get_post_meta( $f['moved'], UsedWhereScanner::META_KEY, true ) );
	}

	/**
	 * A front-end visit that records a usage keeps an existing parent and
	 * never points one at something that is not a post.
	 */
	public function test_frontend_usage_keeps_parent() {
		$f = $this->fixtures();

		UsedWhereScanner::instance()->record_frontend_usage( $f['moved'], $f['post_c'], 'content' );
		$this->assertSame( $f['post_a'], self::parent_of( $f['moved'] ) );

		$orphan = $this->image( 'orphan.jpg' );
		UsedWhereScanner::instance()->record_frontend_usage( $orphan, $f['post_c'], 'content' );
		$this->assertSame( $f['post_c'], self::parent_of( $orphan ), 'No parent before: set.' );
	}
}
