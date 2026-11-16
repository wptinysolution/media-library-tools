<?php
/**
 * The `tsmlt_mcp_server_resources` extension point: how other code (the Pro
 * plugin) adds MCP App view resources to the dedicated Media Library Tools
 * MCP server, and how Free validates what it is given. Free registers no
 * resource of its own; tools and transport are unchanged.
 *
 * Stand-in abilities are registered in the plugin's category for the test;
 * no Pro code is loaded.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\McpServerRegistration;

/**
 * McpServerResourcesTest
 */
class McpServerResourcesTest extends WP_UnitTestCase {

	/**
	 * Stand-in MCP App view (as Pro would register).
	 *
	 * @var string
	 */
	const VIEW = 'tsmlt/test-app-view';

	/**
	 * Stand-ins that are not MCP App views, by name: [ uri, mimeType ].
	 *
	 * @var array<string, array>
	 */
	const NOT_VIEWS = [
		'tsmlt/test-http-resource'  => [ 'https://example.com/view.html', 'text/html;profile=mcp-app' ],
		'tsmlt/test-plain-html'     => [ 'ui://media-library-tools/plain.html', 'text/html' ],
		'tsmlt/test-no-uri'         => [ null, 'text/html;profile=mcp-app' ],
		'tsmlt/test-file-resource'  => [ 'file:///etc/passwd', 'text/html;profile=mcp-app' ],
		'tsmlt/test-uri-not-string' => [ [ 'ui://x' ], 'text/html;profile=mcp-app' ],
	];

	/**
	 * Stand-in view outside the plugin's category.
	 *
	 * @var string
	 */
	const OTHER_CATEGORY_VIEW = 'tsmlt/test-other-category-view';

	/**
	 * Test-only ability category that is not the plugin's.
	 *
	 * @var string
	 */
	const OTHER_CATEGORY = 'tsmlt-test-resources-other-category';

	/**
	 * Register the stand-ins.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available.' );
		}
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
		$this->register( self::VIEW, AbilitiesInit::CATEGORY, 'ui://media-library-tools/test-view.html', McpServerRegistration::APP_MIME_TYPE );
		$this->register( self::OTHER_CATEGORY_VIEW, self::OTHER_CATEGORY, 'ui://other/view.html', McpServerRegistration::APP_MIME_TYPE );
		foreach ( self::NOT_VIEWS as $name => $meta ) {
			$this->register( $name, AbilitiesInit::CATEGORY, $meta[0], $meta[1] );
		}
	}

	/**
	 * Remove the stand-ins.
	 */
	public function tear_down() {
		$registry = WP_Abilities_Registry::get_instance();
		foreach ( array_merge( [ self::VIEW, self::OTHER_CATEGORY_VIEW ], array_keys( self::NOT_VIEWS ) ) as $name ) {
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
	 * Register a stand-in resource ability that denies everyone.
	 *
	 * @param string $name      Ability name.
	 * @param string $category  Category slug.
	 * @param mixed  $uri       meta.mcp.uri (null: none).
	 * @param string $mime_type meta.mcp.mimeType.
	 */
	private function register( string $name, string $category, $uri, string $mime_type ) {
		$registry = WP_Abilities_Registry::get_instance();
		if ( $registry->is_registered( $name ) ) {
			return;
		}
		$mcp = [
			'public'   => false,
			'type'     => 'resource',
			'mimeType' => $mime_type,
		];
		if ( null !== $uri ) {
			$mcp['uri'] = $uri;
		}
		$registry->register(
			$name,
			[
				'label'               => 'Test view',
				'description'         => 'Stand-in for a view another plugin registers.',
				'category'            => $category,
				'execute_callback'    => '__return_empty_string',
				'permission_callback' => '__return_false',
				'meta'                => [ 'mcp' => $mcp ],
			]
		);
	}

	/**
	 * Resource names with a filter returning the given value.
	 *
	 * @param mixed $returned Filter return value.
	 *
	 * @return string[]
	 */
	private function resource_names_with( $returned ): array {
		$filter = static function () use ( $returned ) {
			return $returned;
		};
		add_filter( McpServerRegistration::RESOURCES_FILTER, $filter );
		try {
			return McpServerRegistration::instance()->get_resource_names();
		} finally {
			remove_filter( McpServerRegistration::RESOURCES_FILTER, $filter );
		}
	}

	/**
	 * With nothing hooked, the server offers no resources.
	 */
	public function test_no_resources_by_default() {
		$this->assertSame( [], McpServerRegistration::instance()->get_resource_names() );
	}

	/**
	 * A registered MCP App view of the plugin's category is added, once.
	 */
	public function test_an_app_view_is_added_once() {
		$this->assertSame( [ self::VIEW ], $this->resource_names_with( [ self::VIEW, self::VIEW ] ) );
	}

	/**
	 * Anything that is not a registered MCP App view of the plugin's
	 * category is skipped with a notice; valid entries beside it are kept.
	 *
	 * @dataProvider data_refused
	 *
	 * @param mixed $entry Filter entry.
	 */
	public function test_other_entries_are_refused( $entry ) {
		$this->setExpectedIncorrectUsage( McpServerRegistration::RESOURCES_FILTER );
		$this->assertSame( [ self::VIEW ], $this->resource_names_with( [ $entry, self::VIEW ] ) );
	}

	/**
	 * Refused entries.
	 *
	 * @return array
	 */
	public function data_refused(): array {
		$cases = [
			'not a string'        => [ 42 ],
			'invalid name'        => [ 'Not A Name' ],
			'unregistered'        => [ 'tsmlt/not-registered' ],
			'other category'      => [ self::OTHER_CATEGORY_VIEW ],
			'free ability (tool)' => [ 'tsmlt/search-media' ],
		];
		foreach ( array_keys( self::NOT_VIEWS ) as $name ) {
			$cases[ $name ] = [ $name ];
		}
		return $cases;
	}

	/**
	 * A filter that does not return an array adds nothing.
	 */
	public function test_non_array_return_is_ignored() {
		$this->setExpectedIncorrectUsage( McpServerRegistration::RESOURCES_FILTER );
		$this->assertSame( [], $this->resource_names_with( 'tsmlt/test-app-view' ) );
	}

	/**
	 * Resources do not change the tool list or the transport permission.
	 */
	public function test_tools_and_transport_are_unchanged() {
		$before = McpServerRegistration::instance()->get_tool_names();
		$filter = static function ( $names ) {
			$names[] = self::VIEW;
			return $names;
		};
		add_filter( McpServerRegistration::RESOURCES_FILTER, $filter );
		$this->assertSame( $before, McpServerRegistration::instance()->get_tool_names() );
		$this->assertNotContains( self::VIEW, McpServerRegistration::instance()->get_tool_names() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertFalse( McpServerRegistration::instance()->check_transport_permission() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertTrue( McpServerRegistration::instance()->check_transport_permission() );
		remove_filter( McpServerRegistration::RESOURCES_FILTER, $filter );
	}

	/**
	 * The adapter is given the validated resource list, beside the
	 * unchanged tool list and permission callback.
	 */
	public function test_register_server_passes_the_resources_to_the_adapter() {
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
		$tools   = McpServerRegistration::instance()->get_tool_names();

		McpServerRegistration::instance()->register_server( $adapter );
		$this->assertSame( [], $adapter->calls[0][10], 'No resources without the filter.' );

		$filter = static function ( $names ) {
			$names[] = self::VIEW;
			$names[] = 'tsmlt/not-registered';
			return $names;
		};
		add_filter( McpServerRegistration::RESOURCES_FILTER, $filter );
		$this->setExpectedIncorrectUsage( McpServerRegistration::RESOURCES_FILTER );
		McpServerRegistration::instance()->register_server( $adapter );
		remove_filter( McpServerRegistration::RESOURCES_FILTER, $filter );

		// create_server( id, namespace, route, name, description, version, transports, error handler, observability, tools, resources, prompts, permission ).
		$args = $adapter->calls[1];
		$this->assertSame( McpServerRegistration::SERVER_ID, $args[0] );
		$this->assertSame( $tools, $args[9] );
		$this->assertSame( [ self::VIEW ], $args[10] );
		$this->assertSame( [], $args[11] );
		$this->assertSame( [ McpServerRegistration::instance(), 'check_transport_permission' ], $args[12] );
	}
}
