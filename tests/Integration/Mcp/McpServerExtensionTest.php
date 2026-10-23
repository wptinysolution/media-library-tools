<?php
/**
 * The `tsmlt_mcp_server_abilities` extension point: how other code (the Pro
 * plugin) adds its own abilities to the dedicated Media Library Tools MCP
 * server, and how Free validates what it is given.
 *
 * A stand-in ability is registered in the plugin's category for the test; no
 * Pro code is loaded.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\McpServerRegistration;

/**
 * McpServerExtensionTest
 */
class McpServerExtensionTest extends WP_UnitTestCase {

	/**
	 * Stand-in for an ability the Pro plugin would register.
	 *
	 * @var string
	 */
	const EXTENSION_ABILITY = 'tsmlt/test-extension-ability';

	/**
	 * A registered ability outside the plugin's category.
	 *
	 * @var string
	 */
	const OTHER_CATEGORY_ABILITY = 'tsmlt/test-other-category-ability';

	/**
	 * Test-only ability category that is not the plugin's.
	 *
	 * @var string
	 */
	const OTHER_CATEGORY = 'tsmlt-test-other-category';

	/**
	 * The Free abilities, in order.
	 *
	 * @var string[]
	 */
	const FREE = [ 'tsmlt/search-media', 'tsmlt/get-media-details', 'tsmlt/update-media-metadata', 'tsmlt/get-exif-fields', 'tsmlt/list-media-groups', 'tsmlt/set-media-groups', 'tsmlt/create-media-group', 'tsmlt/update-media-group' ];

	/**
	 * Register the stand-in abilities (and the other test category).
	 */
	public function set_up() {
		parent::set_up();
		// The registries are already initialised, so register directly, as the
		// wp_register_* functions only work during their init actions.
		$categories = WP_Ability_Categories_Registry::get_instance();
		if ( ! $categories->is_registered( self::OTHER_CATEGORY ) ) {
			$categories->register(
				self::OTHER_CATEGORY,
				[
					'label'       => 'Test other category',
					'description' => 'A category that is not Media Library Tools.',
				]
			);
		}
		$this->register_ability( self::EXTENSION_ABILITY, AbilitiesInit::CATEGORY );
		$this->register_ability( self::OTHER_CATEGORY_ABILITY, self::OTHER_CATEGORY );
	}

	/**
	 * Remove the stand-in abilities and the other test category.
	 */
	public function tear_down() {
		$registry = WP_Abilities_Registry::get_instance();
		foreach ( [ self::EXTENSION_ABILITY, self::OTHER_CATEGORY_ABILITY ] as $name ) {
			if ( $registry->is_registered( $name ) ) {
				$registry->unregister( $name );
			}
		}
		$categories = WP_Ability_Categories_Registry::get_instance();
		if ( $categories->is_registered( self::OTHER_CATEGORY ) ) {
			$categories->unregister( self::OTHER_CATEGORY );
		}
		parent::tear_down();
	}

	/**
	 * Register a stand-in ability that does nothing and denies everyone.
	 *
	 * @param string $name     Ability name.
	 * @param string $category Category slug.
	 */
	private function register_ability( string $name, string $category ) {
		$registry = WP_Abilities_Registry::get_instance();
		if ( $registry->is_registered( $name ) ) {
			return;
		}
		$registry->register(
			$name,
			[
				'label'               => 'Test ability',
				'description'         => 'Stand-in for an ability another plugin registers.',
				'category'            => $category,
				'input_schema'        => [
					'type'    => 'object',
					'default' => [],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => '__return_empty_array',
				'permission_callback' => '__return_false',
				'meta'                => [ 'mcp' => [ 'public' => false ] ],
			]
		);
	}

	/**
	 * Tool names with a filter returning the given value.
	 *
	 * @param mixed $returned Filter return value.
	 *
	 * @return string[]
	 */
	private function tool_names_with( $returned ): array {
		$filter = static function () use ( $returned ) {
			return $returned;
		};
		add_filter( McpServerRegistration::TOOLS_FILTER, $filter );
		try {
			return McpServerRegistration::instance()->get_tool_names();
		} finally {
			remove_filter( McpServerRegistration::TOOLS_FILTER, $filter );
		}
	}

	/**
	 * Backward compatibility: with nothing hooked, the server offers exactly
	 * the Free abilities, as before.
	 */
	public function test_default_tools_are_the_free_abilities() {
		$this->assertFalse( has_filter( McpServerRegistration::TOOLS_FILTER ), 'Precondition: nothing hooked.' );

		$this->assertSame( self::FREE, McpServerRegistration::instance()->get_tool_names() );
		$this->assertSame( AbilitiesInit::instance()->get_ability_names(), McpServerRegistration::instance()->get_tool_names() );
	}

	/**
	 * A registered ability in the plugin's category is added after the Free
	 * abilities, and the filter receives the Free names as context.
	 */
	public function test_filter_adds_a_registered_ability() {
		$received = null;
		$filter   = static function ( $extra, $free_names ) use ( &$received ) {
			$received = [ $extra, $free_names ];
			$extra[]  = self::EXTENSION_ABILITY;
			return $extra;
		};
		add_filter( McpServerRegistration::TOOLS_FILTER, $filter, 10, 2 );
		$names = McpServerRegistration::instance()->get_tool_names();
		remove_filter( McpServerRegistration::TOOLS_FILTER, $filter, 10 );

		$this->assertSame( array_merge( self::FREE, [ self::EXTENSION_ABILITY ] ), $names );
		$this->assertSame( [ [], self::FREE ], $received );
	}

	/**
	 * The filter cannot remove the Free abilities: they are always listed
	 * first, whatever it returns.
	 */
	public function test_free_abilities_cannot_be_removed() {
		$this->assertSame( self::FREE, $this->tool_names_with( [] ) );
		$this->assertSame( array_merge( self::FREE, [ self::EXTENSION_ABILITY ] ), $this->tool_names_with( [ self::EXTENSION_ABILITY ] ) );
	}

	/**
	 * Duplicates — of an added ability or of a Free one — are listed once.
	 */
	public function test_duplicates_are_listed_once() {
		$names = $this->tool_names_with( [ self::EXTENSION_ABILITY, self::EXTENSION_ABILITY, 'tsmlt/search-media' ] );

		$this->assertSame( array_merge( self::FREE, [ self::EXTENSION_ABILITY ] ), $names );
		$this->assertSame( array_unique( $names ), $names );
	}

	/**
	 * Invalid entries are skipped with a developer notice; valid ones beside
	 * them are still added.
	 *
	 * @dataProvider data_invalid_entries
	 *
	 * @param mixed $entry Invalid value returned by the filter.
	 */
	public function test_invalid_entries_are_skipped( $entry ) {
		$this->setExpectedIncorrectUsage( McpServerRegistration::TOOLS_FILTER );

		$names = $this->tool_names_with( [ $entry, self::EXTENSION_ABILITY ] );

		$this->assertSame( array_merge( self::FREE, [ self::EXTENSION_ABILITY ] ), $names );
	}

	/**
	 * @return array
	 */
	public function data_invalid_entries(): array {
		return [
			'integer'                  => [ 123 ],
			'null'                     => [ null ],
			'array'                    => [ [ 'tsmlt/search-media' ] ],
			'empty string'             => [ '' ],
			'no namespace'             => [ 'search-media' ],
			'uppercase'                => [ 'TSMLT/Search-Media' ],
			'spaces'                   => [ 'tsmlt/search media' ],
			'MCP tool name, not ability' => [ 'tsmlt-search-media' ],
			'not registered'           => [ 'tsmlt/not-a-registered-ability' ],
			'other category'           => [ self::OTHER_CATEGORY_ABILITY ],
		];
	}

	/**
	 * The "other category" case uses a registered ability, so it is rejected
	 * for its category, not for being unknown.
	 */
	public function test_other_category_fixture_is_a_registered_ability() {
		$this->assertTrue( wp_has_ability( self::OTHER_CATEGORY_ABILITY ) );
		$this->assertNotSame( AbilitiesInit::CATEGORY, wp_get_ability( self::OTHER_CATEGORY_ABILITY )->get_category() );
	}

	/**
	 * A filter that does not return an array is ignored with a notice, and
	 * the Free abilities stay available.
	 *
	 * @dataProvider data_non_array_returns
	 *
	 * @param mixed $returned Filter return value.
	 */
	public function test_non_array_return_is_ignored( $returned ) {
		$this->setExpectedIncorrectUsage( McpServerRegistration::TOOLS_FILTER );

		$this->assertSame( self::FREE, $this->tool_names_with( $returned ) );
	}

	/**
	 * @return array
	 */
	public function data_non_array_returns(): array {
		return [
			'string' => [ self::EXTENSION_ABILITY ],
			'null'   => [ null ],
			'false'  => [ false ],
			'object' => [ new stdClass() ],
		];
	}

	/**
	 * The adapter is given exactly the validated tool list.
	 */
	public function test_register_server_passes_the_tool_list_to_the_adapter() {
		if ( ! class_exists( '\WP\MCP\Transport\HttpTransport' ) ) {
			$this->markTestSkipped( 'MCP Adapter not loaded; set MCP_ADAPTER_DIR to run this test.' );
		}
		$adapter = new class() {
			/**
			 * Arguments of each create_server() call.
			 *
			 * @var array
			 */
			public $calls = [];

			/**
			 * Record a create_server() call.
			 *
			 * @param mixed ...$args Arguments.
			 */
			public function create_server( ...$args ) {
				$this->calls[] = $args;
			}
		};
		$filter = static function ( $extra ) {
			$extra[] = self::EXTENSION_ABILITY;
			$extra[] = 'tsmlt/not-a-registered-ability';
			return $extra;
		};
		add_filter( McpServerRegistration::TOOLS_FILTER, $filter );
		$this->setExpectedIncorrectUsage( McpServerRegistration::TOOLS_FILTER );

		McpServerRegistration::instance()->register_server( $adapter );
		remove_filter( McpServerRegistration::TOOLS_FILTER, $filter );

		$this->assertCount( 1, $adapter->calls );
		$args = $adapter->calls[0];
		$this->assertSame( McpServerRegistration::SERVER_ID, $args[0] );
		// create_server( id, namespace, route, name, description, version, transports, error handler, observability, tools, … ).
		$this->assertSame( array_merge( self::FREE, [ self::EXTENSION_ABILITY ] ), $args[9] );
		$this->assertSame( [ McpServerRegistration::instance(), 'check_transport_permission' ], $args[12] );
	}

	/**
	 * Adding abilities does not loosen who may connect: the transport still
	 * requires manage_options.
	 */
	public function test_transport_permission_is_unchanged_by_extensions() {
		add_filter(
			McpServerRegistration::TOOLS_FILTER,
			static function ( $extra ) {
				$extra[] = self::EXTENSION_ABILITY;
				return $extra;
			}
		);

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertFalse( McpServerRegistration::instance()->check_transport_permission() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertTrue( McpServerRegistration::instance()->check_transport_permission() );
	}
}
