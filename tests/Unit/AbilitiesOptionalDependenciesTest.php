<?php
/**
 * Tests that the MCP integration is a safe no-op when its optional
 * dependencies — the WordPress Abilities API and the MCP Adapter plugin — are
 * missing, as they are in this WordPress-free unit suite.
 *
 * @package TinySolutions\mlt
 */

use PHPUnit\Framework\TestCase;
use TinySolutions\mlt\Abilities\AbilitiesInit;
use TinySolutions\mlt\Abilities\McpServerRegistration;

/**
 * AbilitiesOptionalDependenciesTest
 */
class AbilitiesOptionalDependenciesTest extends TestCase {

	/**
	 * Hooks the integration would register if its dependencies were present.
	 *
	 * @var string[]
	 */
	const INTEGRATION_HOOKS = [
		'wp_abilities_api_categories_init',
		'wp_abilities_api_init',
		'mcp_adapter_init',
	];

	/**
	 * Reset the fake hook registry.
	 */
	protected function setUp(): void {
		tsmlt_test_reset();
	}

	/**
	 * Re-run a singleton's private constructor on a clean hook registry, the
	 * same way the credential tests re-register CredentialHooks, so only the
	 * hooks that constructor adds are recorded.
	 *
	 * @param object $instance Singleton instance.
	 */
	private function construct_again( $instance ): void {
		// instance() may have just run the constructor; start from a clean registry.
		tsmlt_test_reset();
		$constructor = new ReflectionMethod( get_class( $instance ), '__construct' );
		if ( PHP_VERSION_ID < 80100 ) {
			$constructor->setAccessible( true );
		}
		$constructor->invoke( $instance );
	}

	/**
	 * Hook names recorded in the fake registry.
	 *
	 * @return string[]
	 */
	private function registered_hooks(): array {
		return array_keys( $GLOBALS['tsmlt_test_filters'] );
	}

	/**
	 * A fake adapter that records whether create_server() was called.
	 *
	 * @return object
	 */
	private function recording_adapter() {
		return new class() {
			/**
			 * Calls received.
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
	}

	/**
	 * Without the Abilities API the plugin reports it as unsupported and
	 * registers nothing: no ability hooks and no MCP server.
	 */
	public function test_abilities_api_missing_registers_nothing() {
		$this->assertFalse( function_exists( 'wp_register_ability' ), 'Precondition: the unit suite has no Abilities API.' );
		$this->assertFalse( function_exists( 'wp_register_ability_category' ), 'Precondition: the unit suite has no Abilities API.' );
		$this->assertFalse( AbilitiesInit::is_supported() );

		$this->construct_again( AbilitiesInit::instance() );

		$this->assertSame( [], array_intersect( self::INTEGRATION_HOOKS, $this->registered_hooks() ) );
	}

	/**
	 * Listing ability names needs no Abilities API, so the scoped server's
	 * tool list is always the Free abilities.
	 */
	public function test_ability_names_without_abilities_api() {
		$this->assertSame(
			[ 'tsmlt/search-media', 'tsmlt/get-media-details', 'tsmlt/update-media-metadata', 'tsmlt/get-exif-fields', 'tsmlt/list-media-groups', 'tsmlt/set-media-groups' ],
			AbilitiesInit::instance()->get_ability_names()
		);
	}

	/**
	 * The server registration only waits on the adapter's own init hook, so
	 * nothing happens unless the adapter is loaded and fires it.
	 */
	public function test_mcp_registration_only_hooks_adapter_init() {
		$this->assertFalse( class_exists( '\WP\MCP\Core\McpAdapter' ), 'Precondition: the unit suite has no MCP Adapter.' );

		$this->construct_again( McpServerRegistration::instance() );

		$this->assertSame( [ 'mcp_adapter_init' ], $this->registered_hooks() );
		$this->assertSame(
			[ [ McpServerRegistration::instance(), 'register_server' ] ],
			$GLOBALS['tsmlt_test_filters']['mcp_adapter_init']
		);
	}

	/**
	 * Values that are not an adapter are ignored without errors.
	 *
	 * @dataProvider data_invalid_adapters
	 *
	 * @param mixed $adapter Value passed in place of the adapter.
	 */
	public function test_invalid_adapter_is_ignored( $adapter ) {
		McpServerRegistration::instance()->register_server( $adapter );

		$this->assertSame( [], $this->registered_hooks() );
	}

	/**
	 * @return array
	 */
	public function data_invalid_adapters(): array {
		return [
			'null'                       => [ null ],
			'false'                      => [ false ],
			'string'                     => [ 'WP\MCP\Core\McpAdapter' ],
			'integer'                    => [ 42 ],
			'array'                      => [ [ 'create_server' => true ] ],
			'object without the method'  => [ new stdClass() ],
		];
	}

	/**
	 * An object with create_server() is still not called when the adapter's
	 * HTTP transport class is missing.
	 */
	public function test_adapter_without_transport_is_not_called() {
		$this->assertFalse( class_exists( '\WP\MCP\Transport\HttpTransport' ), 'Precondition: the unit suite has no MCP Adapter.' );

		$adapter = $this->recording_adapter();
		McpServerRegistration::instance()->register_server( $adapter );

		$this->assertSame( [], $adapter->calls );
	}
}
