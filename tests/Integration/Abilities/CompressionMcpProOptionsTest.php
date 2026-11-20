<?php
/**
 * Pro-only compression options through MCP: accepted with a valid Pro
 * licence (custom quality, backups, generated sizes, no job limit) and
 * refused when the licence lapses between preview and execution.
 *
 * The Pro plugin is stood in for by tsmltpro() with a switchable licence,
 * defined only in this test's own process.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\Compress\CompressionAccess;
use TinySolutions\mlt\Modules\Compress\CompressionJob;
use TinySolutions\mlt\Modules\Compress\McpCompression;

require_once dirname( __DIR__, 2 ) . '/Support/TsmltProCompressionStub.php';

/**
 * CompressionMcpProOptionsTest
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CompressionMcpProOptionsTest extends WP_UnitTestCase {

	/**
	 * Attachment.
	 *
	 * @var int
	 */
	private $id = 0;

	/**
	 * Administrator, a JPEG, licensed Pro stand-in.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/preview-compression' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		if ( ! CompressionAccess::instance()->is_compression_feature_available() ) {
			$this->markTestSkipped( 'No image compression library is available.' );
		}
		self::install_pro_stub();
		TsmltProCompressionStub::$licensed = true;
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( CompressionJob::STATE_OPTION );
		$this->id = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
	}

	/**
	 * Remove the attachment and the job.
	 */
	public function tear_down() {
		delete_option( CompressionJob::STATE_OPTION );
		wp_clear_scheduled_hook( CompressionJob::TICK_HOOK );
		wp_delete_attachment( $this->id, true );
		parent::tear_down();
	}

	/**
	 * Define the Pro stub, inside a method so the parent PHPUnit process
	 * never defines tsmltpro().
	 */
	private static function install_pro_stub() {
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
	}

	/**
	 * Preview with every Pro option.
	 *
	 * @return array|WP_Error
	 */
	private function preview() {
		return wp_get_ability( 'tsmlt/preview-compression' )->execute(
			[
				'ids'                      => [ $this->id ],
				'quality'                  => 70,
				'backup_originals'         => true,
				'compress_generated_sizes' => true,
			]
		);
	}

	/**
	 * Licensed: the options apply, the run is reversible, there is no job limit.
	 */
	public function test_licensed_pro_options_apply() {
		$this->assertTrue( tsmlt()->has_pro(), 'Precondition: licensed.' );
		$preview = $this->preview();
		$this->assertIsArray( $preview, wp_json_encode( $preview ) );
		$this->assertSame(
			[
				'mode'                     => $preview['settings']['mode'],
				'quality'                  => 70,
				'use_custom_quality'       => true,
				'backup_originals'         => true,
				'compress_generated_sizes' => true,
			],
			$preview['settings']
		);
		$this->assertTrue( $preview['reversible'] );
		$this->assertSame( 0, $preview['job_limit'] );

		$result = wp_get_ability( 'tsmlt/compress-media' )->execute(
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$this->assertTrue( $result['reversible'] );
		$this->assertSame( 0, $result['job_limit'] );
		$state = CompressionJob::instance()->get_state();
		$this->assertTrue( $state['settings']['backup_originals'] );
		$this->assertSame( 70, $state['settings']['quality'] );
	}

	/**
	 * A licence that lapses after the preview refuses the run.
	 */
	public function test_lapsed_licence_refuses_execution() {
		$preview = $this->preview();
		$this->assertIsArray( $preview, wp_json_encode( $preview ) );
		TsmltProCompressionStub::$licensed = false;

		$result = wp_get_ability( 'tsmlt/compress-media' )->execute(
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$this->assertWPError( $result );
		$this->assertSame( McpCompression::ERROR_PRO_REQUIRED, $result->get_error_code() );
		$this->assertFalse( get_option( CompressionJob::STATE_OPTION ), 'Nothing started.' );
	}
}
