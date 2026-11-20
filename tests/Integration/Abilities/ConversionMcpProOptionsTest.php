<?php
/**
 * Pro-only conversion options through MCP: accepted with a valid Pro
 * licence (AVIF, custom quality, generated sizes) and refused when the
 * licence lapses between preview and start. Runs with a licensed Pro
 * stand-in, in its own process.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Modules\Compress\CompressionJob;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionCapabilities;
use TinySolutions\mlt\Modules\Compress\Conversion\McpConversion;

require_once dirname( __DIR__, 2 ) . '/Support/TsmltProCompressionStub.php';

/**
 * ConversionMcpProOptionsTest
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConversionMcpProOptionsTest extends WP_UnitTestCase {

	/**
	 * Attachment.
	 *
	 * @var int
	 */
	private $id = 0;

	/**
	 * Licensed Pro stand-in, administrator, a JPEG.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( 'tsmlt/preview-media-conversion' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
		if ( ! ConversionCapabilities::instance()->supports_format( 'avif' ) || ! ConversionCapabilities::instance()->supports_format( 'webp' ) ) {
			$this->markTestSkipped( 'This server cannot write WebP and AVIF.' );
		}
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
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( CompressionJob::STATE_OPTION );
		$this->id = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
	}

	/**
	 * Remove the attachment and job.
	 */
	public function tear_down() {
		delete_option( CompressionJob::STATE_OPTION );
		wp_clear_scheduled_hook( CompressionJob::TICK_HOOK );
		wp_delete_attachment( $this->id, true );
		parent::tear_down();
	}

	/**
	 * Preview with every Pro option.
	 *
	 * @return array|WP_Error
	 */
	private function preview() {
		return wp_get_ability( 'tsmlt/preview-media-conversion' )->execute(
			[
				'ids'             => [ $this->id ],
				'formats'         => [ 'webp', 'avif' ],
				'quality'         => [
					'webp' => 70,
					'avif' => 55,
				],
				'generated_sizes' => true,
			]
		);
	}

	/**
	 * Licensed: the options apply exactly as asked and reach the job.
	 */
	public function test_licensed_pro_options_apply() {
		$preview = $this->preview();
		$this->assertIsArray( $preview, wp_json_encode( $preview ) );
		$this->assertTrue( $preview['pro_active'] );
		$this->assertSame(
			[
				'formats'         => [ 'webp', 'avif' ],
				'quality'         => [
					'webp' => 70,
					'avif' => 55,
				],
				'generated_sizes' => true,
			],
			$preview['settings']
		);
		$this->assertSame( 0, $preview['job_limit'] );

		$result = wp_get_ability( 'tsmlt/convert-media' )->execute(
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);
		$this->assertIsArray( $result, wp_json_encode( $result ) );
		$state = CompressionJob::instance()->get_state();
		$this->assertSame( [ 'webp', 'avif' ], $state['settings']['formats'] );
		$this->assertSame( 55, $state['settings']['quality']['avif'] );
		$this->assertTrue( $state['settings']['generated_sizes'] );
	}

	/**
	 * A licence that lapses after the preview refuses the start.
	 */
	public function test_lapsed_licence_refuses_start() {
		$preview = $this->preview();
		$this->assertIsArray( $preview, wp_json_encode( $preview ) );
		TsmltProCompressionStub::$licensed = false;

		$result = wp_get_ability( 'tsmlt/convert-media' )->execute(
			[
				'confirmation_token' => $preview['confirmation_token'],
				'confirm'            => true,
			]
		);

		$this->assertWPError( $result );
		$this->assertSame( McpConversion::ERROR_PRO_REQUIRED, $result->get_error_code() );
		$this->assertFalse( get_option( CompressionJob::STATE_OPTION ), 'Nothing started.' );
	}
}
