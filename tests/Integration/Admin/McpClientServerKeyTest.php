<?php
/**
 * The site-specific MCP client configuration keys suggested on the MCP Docs
 * page (`McpDocsPage::get_client_server_key()`): "mlt-", a site part from the
 * site address, then the endpoint identifier — and their use in the page's
 * client snippets.
 *
 * @package TinySolutions\mlt
 */

use TinySolutions\mlt\Controllers\Admin\McpDocsPage;
use TinySolutions\mlt\Modules\Mcp\McpConnection;

/**
 * McpClientServerKeyTest
 */
class McpClientServerKeyTest extends WP_UnitTestCase {

	/**
	 * Remove the override filters.
	 */
	public function tear_down() {
		remove_all_filters( McpDocsPage::CLIENT_KEY_FILTER );
		parent::tear_down();
	}

	/**
	 * Suggested key for an address and endpoint.
	 *
	 * @param string $url      Site address.
	 * @param string $endpoint Endpoint identifier.
	 *
	 * @return string
	 */
	private function key( string $url, string $endpoint = McpDocsPage::CLIENT_KEY_DEDICATED ): string {
		return McpDocsPage::instance()->get_client_server_key( $endpoint, $url );
	}

	/**
	 * The endpoint identifiers.
	 */
	public function test_endpoint_identifiers() {
		$this->assertSame( 'mcp-adapter-default-server', McpDocsPage::CLIENT_KEY_DEFAULT_SERVER );
		$this->assertSame( 'media-library-tools', McpDocsPage::CLIENT_KEY_DEDICATED );
	}

	/**
	 * Addresses and the site part they give, for both endpoints.
	 *
	 * @dataProvider data_site_parts
	 *
	 * @param string $url  Site address.
	 * @param string $site Expected site part.
	 */
	public function test_keys_for_both_endpoints( string $url, string $site ) {
		$this->assertSame( 'mlt-' . $site . '-mcp-adapter-default-server', $this->key( $url, McpDocsPage::CLIENT_KEY_DEFAULT_SERVER ) );
		$this->assertSame( 'mlt-' . $site . '-media-library-tools', $this->key( $url, McpDocsPage::CLIENT_KEY_DEDICATED ) );
	}

	/**
	 * Data for test_keys_for_both_endpoints().
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function data_site_parts(): array {
		return [
			'local domain'                => [ 'http://plugindev.local', 'plugindev-local' ],
			'production domain'           => [ 'https://example.com', 'example-com' ],
			'trailing slash'              => [ 'https://example.com/', 'example-com' ],
			'upper-case host'             => [ 'https://Example.COM', 'example-com' ],
			'www kept'                    => [ 'https://www.example.com', 'www-example-com' ],
			'subdomain'                   => [ 'https://staging.example.com', 'staging-example-com' ],
			'hyphenated domain'           => [ 'https://client-site.com', 'client-site-com' ],
			'port and subdirectory'       => [ 'https://staging.example.com:8443/blog', 'staging-example-com-8443-blog' ],
			'https default port dropped'  => [ 'https://example.com:443', 'example-com' ],
			'http default port dropped'   => [ 'http://example.com:80', 'example-com' ],
			'http on 443 is not default'  => [ 'http://example.com:443', 'example-com-443' ],
			'local port'                  => [ 'http://localhost:10003', 'localhost-10003' ],
			'subdirectory install'        => [ 'https://example.com/blog', 'example-com-blog' ],
			'nested subdirectory'         => [ 'https://example.com/sites/client-a/', 'example-com-sites-client-a' ],
			'multisite subdomain'         => [ 'https://site2.network.example', 'site2-network-example' ],
			'separators collapsed'        => [ 'https://my--site.example.com/a_b//c', 'my-site-example-com-a-b-c' ],
			'query and fragment ignored'  => [ 'https://example.com/blog?x=1#top', 'example-com-blog' ],
		];
	}

	/**
	 * Multisite subdirectory sites on one host get different keys.
	 */
	public function test_subdirectory_sites_differ() {
		$this->assertNotSame( $this->key( 'https://network.example/site-a' ), $this->key( 'https://network.example/site-b' ) );
		$this->assertNotSame( $this->key( 'https://network.example' ), $this->key( 'https://network.example/site-a' ) );
	}

	/**
	 * Long addresses: the key is cut to the maximum, the site part ends in a
	 * hash, the endpoint part is kept; addresses that differ only after the
	 * cut get different keys, the same address the same key.
	 */
	public function test_long_addresses_are_cut_with_a_hash() {
		$base = 'https://a-very-long-subdomain-name.client-company-example.com/some/deep/path/';

		foreach ( [ McpDocsPage::CLIENT_KEY_DEFAULT_SERVER, McpDocsPage::CLIENT_KEY_DEDICATED ] as $endpoint ) {
			$first = $this->key( $base . 'one', $endpoint );
			$other = $this->key( $base . 'two', $endpoint );

			$this->assertLessThanOrEqual( McpDocsPage::CLIENT_KEY_MAX_LENGTH, strlen( $first ), $endpoint );
			$this->assertMatchesRegularExpression( '/^mlt-a-very-long-[a-z0-9-]*-[0-9a-f]{8}-' . preg_quote( $endpoint, '/' ) . '$/', $first );
			$this->assertStringNotContainsString( '--', $first );
			$this->assertNotSame( $first, $other, 'Hash keeps cut keys unique.' );
			$this->assertSame( $first, $this->key( $base . 'one', $endpoint ), 'The same address always gives the same key.' );
			$this->assertSame( 1, preg_match( McpDocsPage::CLIENT_KEY_PATTERN, $first ) );
		}

		// Same site: both endpoints share the hash.
		preg_match( '/-([0-9a-f]{8})-mcp-adapter-default-server$/', $this->key( $base . 'one', McpDocsPage::CLIENT_KEY_DEFAULT_SERVER ), $default_hash );
		preg_match( '/-([0-9a-f]{8})-media-library-tools$/', $this->key( $base . 'one', McpDocsPage::CLIENT_KEY_DEDICATED ), $dedicated_hash );
		$this->assertSame( $default_hash[1], $dedicated_hash[1] );
	}

	/**
	 * A key exactly at the maximum is not cut; one character more is.
	 */
	public function test_key_at_the_maximum_is_kept() {
		$suffix = '-' . McpDocsPage::CLIENT_KEY_DEFAULT_SERVER;
		$room   = McpDocsPage::CLIENT_KEY_MAX_LENGTH - strlen( McpDocsPage::CLIENT_KEY_PREFIX ) - strlen( $suffix );
		$host   = str_repeat( 'a', $room - 4 ) . '.com';

		$key = $this->key( 'https://' . $host, McpDocsPage::CLIENT_KEY_DEFAULT_SERVER );
		$this->assertSame( McpDocsPage::CLIENT_KEY_MAX_LENGTH, strlen( $key ) );
		$this->assertSame( 'mlt-' . str_replace( '.', '-', $host ) . $suffix, $key );

		$longer = $this->key( 'https://a' . $host, McpDocsPage::CLIENT_KEY_DEFAULT_SERVER );
		$this->assertLessThanOrEqual( McpDocsPage::CLIENT_KEY_MAX_LENGTH, strlen( $longer ) );
		$this->assertMatchesRegularExpression( '/-[0-9a-f]{8}-mcp-adapter-default-server$/', $longer );
	}

	/**
	 * International domain names use their ASCII form when the intl
	 * extension is available; otherwise the site part is transliterated and
	 * gets a hash, so it stays unique.
	 */
	public function test_idn_domain() {
		$key = $this->key( 'https://bücher.example' );
		if ( function_exists( 'idn_to_ascii' ) && defined( 'INTL_IDNA_VARIANT_UTS46' ) ) {
			$this->assertSame( 'mlt-xn-bcher-kva-example-media-library-tools', $key );
			$this->assertSame( $key, $this->key( 'https://BÜCHER.example' ), 'Case-folded like other hosts.' );
		} else {
			$this->assertMatchesRegularExpression( '/^mlt-bucher-example-[0-9a-f]{8}-media-library-tools$/', $key );
		}
		$this->assertSame( 1, preg_match( McpDocsPage::CLIENT_KEY_PATTERN, $key ) );
	}

	/**
	 * A path with characters the key cannot keep gets a hash, so it does not
	 * collide with the plain-ASCII path.
	 */
	public function test_lossy_path_gets_a_hash() {
		$key = $this->key( 'https://example.com/café' );

		$this->assertMatchesRegularExpression( '/^mlt-example-com-cafe-[0-9a-f]{8}-media-library-tools$/', $key );
		$this->assertNotSame( $this->key( 'https://example.com/cafe' ), $key );
		$this->assertSame( $key, $this->key( 'https://example.com/caf%C3%A9' ), 'Encoded and raw paths agree.' );
	}

	/**
	 * Unusable addresses still give a valid key.
	 *
	 * @dataProvider data_unusable_addresses
	 *
	 * @param string $url Address.
	 */
	public function test_unusable_address( string $url ) {
		$key = $this->key( $url );
		$this->assertMatchesRegularExpression( '/^mlt-(?:[a-z0-9-]+-)?[0-9a-f]{8}-media-library-tools$/', $key, 'Without a host, the key always gets a hash.' );
		$this->assertSame( 1, preg_match( McpDocsPage::CLIENT_KEY_PATTERN, $key ) );
	}

	/**
	 * Data for test_unusable_address().
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_unusable_addresses(): array {
		return [
			'no host'  => [ 'not a url' ],
			'bad port' => [ 'http://:80' ],
		];
	}

	/**
	 * Without an address, home_url() is used.
	 */
	public function test_defaults_to_home_url() {
		update_option( 'home', 'http://plugindev.local' );
		$page = McpDocsPage::instance();

		$this->assertSame( 'mlt-plugindev-local-mcp-adapter-default-server', $page->get_client_server_key( McpDocsPage::CLIENT_KEY_DEFAULT_SERVER ) );
		$this->assertSame( 'mlt-plugindev-local-media-library-tools', $page->get_client_server_key( McpDocsPage::CLIENT_KEY_DEDICATED ) );
	}

	/**
	 * Every key holds only safe characters, whatever the address, and never
	 * credentials from it.
	 */
	public function test_keys_are_shell_safe() {
		foreach ( [ 'https://ex"ample.com', "https://example.com/'; rm -rf /", 'https://example.com/$(id)', 'https://user:pass@example.com' ] as $url ) {
			$this->assertSame( 1, preg_match( '/^mlt-[a-z0-9-]+$/', $this->key( $url ) ), $url );
		}
		$key = $this->key( 'https://user:secret@example.com' );
		$this->assertStringNotContainsString( 'user', $key );
		$this->assertStringNotContainsString( 'secret', $key );
	}

	/**
	 * The filter can replace the key and gets the endpoint and address.
	 */
	public function test_filter_overrides_the_key() {
		$received = [];
		add_filter(
			McpDocsPage::CLIENT_KEY_FILTER,
			static function ( $key, $endpoint, $url ) use ( &$received ) {
				$received[] = [ $key, $endpoint, $url ];
				return McpDocsPage::CLIENT_KEY_DEFAULT_SERVER === $endpoint ? 'Client_A-all' : 'Client_A-media';
			},
			10,
			3
		);

		$this->assertSame( 'Client_A-all', $this->key( 'https://example.com', McpDocsPage::CLIENT_KEY_DEFAULT_SERVER ) );
		$this->assertSame( 'Client_A-media', $this->key( 'https://example.com', McpDocsPage::CLIENT_KEY_DEDICATED ) );
		$this->assertSame(
			[
				[ 'mlt-example-com-mcp-adapter-default-server', 'mcp-adapter-default-server', 'https://example.com' ],
				[ 'mlt-example-com-media-library-tools', 'media-library-tools', 'https://example.com' ],
			],
			$received
		);
	}

	/**
	 * Unusable filter values are ignored with a notice.
	 *
	 * @dataProvider data_invalid_overrides
	 *
	 * @param mixed $value Filter return value.
	 */
	public function test_invalid_override_is_ignored( $value ) {
		add_filter(
			McpDocsPage::CLIENT_KEY_FILTER,
			static function () use ( $value ) {
				return $value;
			}
		);
		$this->setExpectedIncorrectUsage( McpDocsPage::CLIENT_KEY_FILTER );

		$this->assertSame( 'mlt-example-com-media-library-tools', $this->key( 'https://example.com' ) );
	}

	/**
	 * Data for test_invalid_override_is_ignored().
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function data_invalid_overrides(): array {
		return [
			'empty'        => [ '' ],
			'space'        => [ 'my site' ],
			'shell'        => [ 'x; rm -rf /' ],
			'quote'        => [ 'x"y' ],
			'dot'          => [ 'example.com' ],
			'too long'     => [ str_repeat( 'a', 65 ) ],
			'not a string' => [ [ 'mlt-x' ] ],
			'null'         => [ null ],
		];
	}

	/**
	 * The Connection tab's configurations (built in the browser from the
	 * page settings and the created credentials) use the default server's
	 * key; the Documentation tab no longer shows configurations.
	 */
	public function test_connection_uses_the_default_server_key() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		update_option( 'home', 'http://plugindev.local' );
		$this->assertSame( 'mlt-plugindev-local-mcp-adapter-default-server', McpConnection::instance()->settings()['serverName'] );
		$this->assertSame( 'mlt-plugindev-local-media-library-tools', McpDocsPage::instance()->get_client_server_key( McpDocsPage::CLIENT_KEY_DEDICATED ) );

		ob_start();
		try {
			McpDocsPage::instance()->render();
		} finally {
			$html = (string) ob_get_clean();
		}
		$this->assertStringNotContainsString( 'mlt-plugindev-local-', $html );
	}
}
