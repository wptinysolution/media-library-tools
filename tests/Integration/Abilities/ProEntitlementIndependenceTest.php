<?php
/**
 * Tests that the three Free MCP tools behave identically whatever the Pro
 * plugin's state: absent, absent with data left behind by a previous Pro
 * install, present and licensed, and present but unlicensed.
 *
 * Pro is represented by a stub of `tsmltpro()` and `TSMLTPRO_VERSION`, the two
 * things Tsmlt::has_pro() reads; the real Pro plugin is never loaded. A PHP
 * function cannot be undefined, so the test runs in its own process to keep
 * the stub away from every other test, and walks the states in the only
 * order a single process allows (absent first).
 *
 * Limitation: abilities are registered once, at plugin load, while Pro is
 * absent. Registration does not consult Pro (AbilitiesInit), so the
 * registered definitions are compared as they are; a Pro plugin that was
 * present at load time is not simulated.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilitiesInit;

/**
 * Stand-in for the object tsmltpro() returns, with a switchable licence.
 */
class TsmltProEntitlementStub {

	/**
	 * Whether the licence is valid.
	 *
	 * @var bool
	 */
	public static $licensed = false;

	/**
	 * Mirrors the Pro plugin's licence check used by Tsmlt::has_pro().
	 *
	 * @return bool
	 */
	public function user_can_use_tsmltpro() {
		return self::$licensed;
	}
}

/**
 * ProEntitlementIndependenceTest
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ProEntitlementIndependenceTest extends WP_UnitTestCase {

	/**
	 * Ability names of the Free tools.
	 *
	 * @var string[]
	 */
	const FREE_ABILITIES = [ 'tsmlt/create-media-group', 'tsmlt/get-exif-fields', 'tsmlt/get-media-details', 'tsmlt/list-media-groups', 'tsmlt/search-media', 'tsmlt/set-media-groups', 'tsmlt/update-media-metadata' ];

	/**
	 * Marker written as leftover Pro data; must never appear in any output.
	 *
	 * @var string
	 */
	const LEFTOVER = 'PRO-LEFTOVER-MARKER';

	/**
	 * Fixture attachment ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Fixture parent post ID.
	 *
	 * @var int
	 */
	private $parent_id;

	/**
	 * Define the Pro stub. Declared inside a method so that loading this file
	 * in the parent PHPUnit process never defines tsmltpro().
	 */
	private static function install_pro_stub() {
		if ( ! defined( 'TSMLTPRO_VERSION' ) ) {
			define( 'TSMLTPRO_VERSION', '1.3.3' );
		}
		if ( ! function_exists( 'tsmltpro' ) ) {
			/**
			 * Stub of the Pro plugin accessor.
			 *
			 * @return TsmltProEntitlementStub
			 */
			function tsmltpro() {
				return new TsmltProEntitlementStub();
			}
		}
	}

	/**
	 * Restore the fixture's caption, so each state's update writes the same
	 * change from the same starting point.
	 */
	private function reset_fixture() {
		wp_update_post(
			[
				'ID'           => $this->id,
				'post_excerpt' => 'Original caption',
			]
		);
		clean_post_cache( $this->id );
	}

	/**
	 * Everything an MCP client sees from the Free tools in the current state:
	 * the tool list, each tool's definition, and the output of every tool.
	 *
	 * @return array
	 */
	private function observe(): array {
		$this->reset_fixture();

		$names = array_values(
			array_filter(
				array_keys( wp_get_abilities() ),
				static function ( $name ) {
					return 0 === strpos( $name, 'tsmlt/' );
				}
			)
		);
		sort( $names );

		$definitions = [];
		foreach ( $names as $name ) {
			$ability              = wp_get_ability( $name );
			$definitions[ $name ] = [
				'label'         => $ability->get_label(),
				'description'   => $ability->get_description(),
				'category'      => $ability->get_category(),
				'input_schema'  => $ability->get_input_schema(),
				'output_schema' => $ability->get_output_schema(),
				'meta'          => $ability->get_meta(),
			];
		}

		$outputs = [
			'search'        => wp_get_ability( 'tsmlt/search-media' )->execute( [ 'search' => 'Entitlement fixture' ] ),
			'details'       => wp_get_ability( 'tsmlt/get-media-details' )->execute( [ 'id' => $this->id ] ),
			'update'        => wp_get_ability( 'tsmlt/update-media-metadata' )->execute(
				[
					'id'      => $this->id,
					'caption' => 'Updated caption',
				]
			),
			'after_update'  => wp_get_ability( 'tsmlt/get-media-details' )->execute( [ 'id' => $this->id ] ),
			'pro_style_arg' => wp_get_ability( 'tsmlt/update-media-metadata' )->execute(
				[
					'id'                => $this->id,
					'caption'           => 'Pro-style probe',
					'bulkEditPostTitle' => [ 'post_title' ],
				]
			),
		];

		foreach ( $outputs as $key => $output ) {
			if ( is_wp_error( $output ) ) {
				$outputs[ $key ] = [ 'error' => $output->get_error_code() ];
			}
		}

		return [
			'names'         => $names,
			'scoped_names'  => AbilitiesInit::instance()->get_ability_names(),
			'definitions'   => $definitions,
			'outputs'       => $outputs,
			'final_caption' => get_post( $this->id )->post_excerpt,
		];
	}

	/**
	 * The Free tools are the same, and return the same results, in every Pro
	 * state.
	 */
	public function test_free_tools_identical_in_every_pro_state() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->parent_id = self::factory()->post->create( [ 'post_title' => 'Entitlement parent' ] );
		$this->id        = self::factory()->attachment->create(
			[
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
				'post_title'     => 'Entitlement fixture',
				'post_excerpt'   => 'Original caption',
				'post_content'   => 'Original description',
				'post_parent'    => $this->parent_id,
				'post_date'      => '2026-01-02 03:04:05',
				'post_date_gmt'  => '2026-01-02 03:04:05',
				'file'           => '2026/10/entitlement.jpg',
			]
		);
		update_post_meta( $this->id, '_wp_attachment_image_alt', 'Original alt' );
		wp_update_attachment_metadata(
			$this->id,
			[
				'width'  => 800,
				'height' => 600,
				'file'   => '2026/10/entitlement.jpg',
			]
		);

		// A. Pro absent.
		$this->assertFalse( function_exists( 'tsmltpro' ), 'Precondition: Pro must be absent.' );
		$this->assertFalse( defined( 'TINY_DEBUG_TSMLT_PRO_1_2_3' ), 'Precondition: no licence override.' );
		$this->assertFalse( tsmlt()->has_pro() );
		$absent = $this->observe();

		$this->assertSame( self::FREE_ABILITIES, $absent['names'] );
		$this->assertSame( [ 'tsmlt/search-media', 'tsmlt/get-media-details', 'tsmlt/update-media-metadata', 'tsmlt/get-exif-fields', 'tsmlt/list-media-groups', 'tsmlt/set-media-groups', 'tsmlt/create-media-group' ], $absent['scoped_names'] );
		$this->assertSame( [ 'caption' ], $absent['outputs']['update']['updated_fields'] );
		$this->assertSame( [ 'error' => 'ability_invalid_input' ], $absent['outputs']['pro_style_arg'] );
		$this->assertSame( 'Updated caption', $absent['final_caption'] );

		// D. Pro deactivated after use: its data stays behind.
		update_post_meta( $this->id, '_tsmlt_exif_camera', self::LEFTOVER );
		update_post_meta( $this->id, 'custom_meta_from_pro_import', self::LEFTOVER );
		$settings                          = (array) get_option( 'tsmlt_settings', [] );
		$settings['ai_custom_instruction'] = self::LEFTOVER;
		update_option( 'tsmlt_settings', $settings );
		$this->assertFalse( tsmlt()->has_pro() );
		$this->assertSame( $absent, $this->observe(), 'Pro absent, with leftover Pro data' );

		// B. Pro present and licensed.
		self::install_pro_stub();
		TsmltProEntitlementStub::$licensed = true;
		$this->assertTrue( tsmlt()->has_pro(), 'Precondition: the stub must report a valid licence.' );
		$this->assertSame( $absent, $this->observe(), 'Pro present and licensed' );

		// C. Pro present but unlicensed.
		TsmltProEntitlementStub::$licensed = false;
		$this->assertFalse( tsmlt()->has_pro(), 'Precondition: the stub must report no licence.' );
		$unlicensed = $this->observe();
		$this->assertSame( $absent, $unlicensed, 'Pro present but unlicensed' );

		// The leftover Pro data never reached a tool definition or output.
		$this->assertStringNotContainsString( self::LEFTOVER, wp_json_encode( $unlicensed ) );
	}
}
