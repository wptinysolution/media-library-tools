<?php
/**
 * Tests that the Free abilities' MCP-facing definitions do not depend on Pro.
 *
 * The unit bootstrap's tsmlt() stub can only report has_pro() as true or
 * false, so this covers the entitlement outcome. Pro absent, licensed and
 * unlicensed, with real execution, are covered by the integration test
 * ProEntitlementIndependenceTest.
 *
 * @package TinySolutions\mlt
 */

use PHPUnit\Framework\TestCase;
use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\Media\GetMediaDetails;
use TinySolutions\mlt\Abilities\Media\SearchMedia;
use TinySolutions\mlt\Abilities\Media\UpdateMediaMetadata;

/**
 * AbilitiesProIndependenceTest
 */
class AbilitiesProIndependenceTest extends TestCase {

	/**
	 * Reset fake WordPress state, including the Pro flag.
	 */
	protected function setUp(): void {
		tsmlt_test_reset();
	}

	/**
	 * Reset the Pro flag for the next test class.
	 */
	protected function tearDown(): void {
		tsmlt_test_reset();
	}

	/**
	 * Everything an MCP client sees about the Free tools, for the current
	 * has_pro() state.
	 *
	 * @return array
	 */
	private function definitions(): array {
		$definitions = [ 'names' => AbilitiesInit::instance()->get_ability_names() ];

		foreach ( [ SearchMedia::instance(), GetMediaDetails::instance(), UpdateMediaMetadata::instance() ] as $ability ) {
			$entry = [];
			foreach ( [ 'get_label', 'get_description', 'get_input_schema', 'get_output_schema', 'get_annotations' ] as $method ) {
				$reflection = new ReflectionMethod( $ability, $method );
				if ( PHP_VERSION_ID < 80100 ) {
					$reflection->setAccessible( true );
				}
				$entry[ $method ] = $reflection->invoke( $ability );
			}
			$definitions[ $ability->get_name() ] = $entry;
		}

		return $definitions;
	}

	/**
	 * Tool names, descriptions, schemas and annotations are identical with
	 * and without Pro.
	 */
	public function test_definitions_identical_with_and_without_pro() {
		$GLOBALS['tsmlt_test_has_pro'] = false;
		$this->assertFalse( tsmlt()->has_pro() );
		$free = $this->definitions();

		$GLOBALS['tsmlt_test_has_pro'] = true;
		$this->assertTrue( tsmlt()->has_pro() );
		$pro = $this->definitions();

		$this->assertSame( $free, $pro );
		$this->assertSame( [ 'tsmlt/search-media', 'tsmlt/get-media-details', 'tsmlt/update-media-metadata', 'tsmlt/get-exif-fields', 'tsmlt/list-media-groups', 'tsmlt/set-media-groups' ], $pro['names'] );
	}

	/**
	 * With Pro reported, input schemas still refuse unknown properties, so no
	 * Pro-style argument becomes acceptable.
	 */
	public function test_input_schemas_stay_closed_with_pro() {
		$GLOBALS['tsmlt_test_has_pro'] = true;

		foreach ( $this->definitions() as $name => $entry ) {
			if ( 'names' === $name ) {
				continue;
			}
			$this->assertFalse( $entry['get_input_schema']['additionalProperties'], $name );
		}
	}
}
