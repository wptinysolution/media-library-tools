<?php
/**
 * Regenerating thumbnails is not an upload: while an image is rebuilt the
 * on-upload automation settings Pro's upload hooks read (compress, convert
 * and remove GPS on upload) are off, and they are on again for a normal
 * upload. Runs with a licensed Pro stand-in, in its own process.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Modules\Compress\CompressionSettings;
use TinySolutions\mlt\Modules\Compress\Conversion\ConversionSettings;
use TinySolutions\mlt\Modules\Regenerate\RegenerateThumbnails;

require_once dirname( __DIR__, 2 ) . '/Support/TsmltProCompressionStub.php';

/**
 * RegenerateUploadContextTest
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RegenerateUploadContextTest extends WP_UnitTestCase {

	/**
	 * What the metadata hook saw, one entry per call.
	 *
	 * @var array[]
	 */
	private $seen = [];

	/**
	 * Licensed Pro stand-in with every on-upload automation on.
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
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		update_option(
			'tsmlt_settings',
			[
				'compression_auto_on_upload' => 1,
				'conversion_auto_on_upload'  => 1,
				'auto_process_exif'          => 1,
				'auto_remove_gps_on_upload'  => 1,
			]
		);
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'record' ], 999, 2 );
	}

	/**
	 * Remove the recorder.
	 */
	public function tear_down() {
		remove_filter( 'wp_generate_attachment_metadata', [ $this, 'record' ], 999 );
		parent::tear_down();
	}

	/**
	 * Record what an upload hook at Pro's priority would read.
	 *
	 * @param array $metadata      Metadata.
	 * @param int   $attachment_id Attachment ID.
	 *
	 * @return array
	 */
	public function record( $metadata, $attachment_id ) {
		$this->seen[] = [
			'regenerating' => RegenerateThumbnails::is_regenerating( (int) $attachment_id ),
			'compress'     => CompressionSettings::instance()->get_settings()['auto_compress_on_upload'],
			'convert'      => ConversionSettings::instance()->get_settings()['auto_on_upload'],
			'gps'          => (bool) Fns::get_options()['auto_remove_gps_on_upload'],
		];
		return $metadata;
	}

	/**
	 * On upload the automation is on; during regeneration it is off, and on
	 * again afterwards.
	 */
	public function test_on_upload_automation_is_off_only_during_regeneration() {
		$id = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertSame(
			[
				'regenerating' => false,
				'compress'     => true,
				'convert'      => true,
				'gps'          => true,
			],
			end( $this->seen ),
			'A normal upload keeps its automation.'
		);

		$this->seen = [];
		$result     = RegenerateThumbnails::instance()->regenerate_attachment( $id, get_current_user_id() );

		$this->assertSame( 'regenerated', $result['status'], wp_json_encode( $result ) );
		$this->assertSame(
			[
				[
					'regenerating' => true,
					'compress'     => false,
					'convert'      => false,
					'gps'          => false,
				],
			],
			$this->seen,
			'The metadata hook still runs, with the automation off.'
		);
		$this->assertFalse( RegenerateThumbnails::is_regenerating() );
		$this->assertTrue( CompressionSettings::instance()->get_settings()['auto_compress_on_upload'], 'On again afterwards.' );
		wp_delete_attachment( $id, true );
	}
}
