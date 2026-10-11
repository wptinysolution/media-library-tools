<?php
/**
 * Unit-suite PHPUnit bootstrap (phpunit-unit.xml.dist): minimal WordPress stubs so the plugin's AI and credential
 * code runs without WordPress. Provides a fake HTTP transport, an in-memory
 * options table that runs `pre_update_option_*` filters like WordPress, a
 * swappable wp_salt(), and switches to simulate failed or corrupted writes.
 * No real network calls are made and no real API keys are used.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Controllers\AI {

	/**
	 * Namespaced override of gmdate() so tests can pin "today" for the
	 * retirement-date fallbacks. AiApi calls gmdate() unqualified, so PHP
	 * resolves this function before the global one.
	 *
	 * @param string   $format    Date format.
	 * @param int|null $timestamp Optional timestamp.
	 *
	 * @return string
	 */
	function gmdate( string $format, ?int $timestamp = null ): string {
		if ( null === $timestamp && 'Y-m-d' === $format && isset( $GLOBALS['tsmlt_test_today'] ) ) {
			return $GLOBALS['tsmlt_test_today'];
		}
		return \gmdate( $format, $timestamp ?? time() );
	}
}

namespace {

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'TSMLT_VERSION', 'test' );

	/**
	 * Reset all fake WordPress state between tests.
	 *
	 * @return void
	 */
	function tsmlt_test_reset() {
		$GLOBALS['tsmlt_test_http']    = [
			'responses' => [],
			'requests'  => [],
		];
		$GLOBALS['tsmlt_test_options'] = [];
		$GLOBALS['tsmlt_test_autoload'] = [];
		$GLOBALS['tsmlt_test_transients'] = [];
		$GLOBALS['tsmlt_test_filters'] = [];
		$GLOBALS['tsmlt_test_salt']    = 'test-logged-in-key-and-salt-0123456789';
		$GLOBALS['tsmlt_test_writes']  = [];
		$GLOBALS['tsmlt_test_fail_writes']    = [];
		$GLOBALS['tsmlt_test_corrupt_writes'] = [];
		unset( $GLOBALS['tsmlt_test_today'] );
	}
	tsmlt_test_reset();

	/**
	 * Minimal stand-in for WordPress's WP_Error.
	 */
	class WP_Error {
		/**
		 * Error message.
		 *
		 * @var string
		 */
		private $message;

		/**
		 * Store the error message.
		 *
		 * @param string $code    Error code (unused).
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->message = $message;
		}

		/**
		 * Return the error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}

	// -------------------------------------------------------------------------
	// Hooks
	// -------------------------------------------------------------------------

	/**
	 * Stub of add_filter(): records the callback.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 *
	 * @return bool
	 */
	function add_filter( $hook, $callback ) {
		$GLOBALS['tsmlt_test_filters'][ $hook ][] = $callback;
		return true;
	}

	/**
	 * Stub of add_action().
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 *
	 * @return bool
	 */
	function add_action( $hook, $callback ) {
		return add_filter( $hook, $callback );
	}

	/**
	 * Stub of apply_filters(): runs recorded callbacks in order.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra arguments.
	 *
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['tsmlt_test_filters'][ $hook ] ?? [] as $callback ) {
			$value = $callback( $value, ...$args );
		}
		return $value;
	}

	/**
	 * Stub of do_action().
	 *
	 * @param string $hook    Hook name.
	 * @param mixed  ...$args Arguments.
	 *
	 * @return void
	 */
	function do_action( $hook, ...$args ) {
		foreach ( $GLOBALS['tsmlt_test_filters'][ $hook ] ?? [] as $callback ) {
			$callback( ...$args );
		}
	}

	// -------------------------------------------------------------------------
	// Options, transients, cache, salts
	// -------------------------------------------------------------------------

	/**
	 * Stub of get_option().
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default.
	 *
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['tsmlt_test_options'] ) ? $GLOBALS['tsmlt_test_options'][ $option ] : $default;
	}

	/**
	 * Stub of update_option(): runs `pre_update_option_{$option}` like
	 * WordPress, records autoload, and honours simulated write failures.
	 *
	 * @param string    $option   Option name.
	 * @param mixed     $value    New value.
	 * @param bool|null $autoload Autoload flag.
	 *
	 * @return bool
	 */
	function update_option( $option, $value, $autoload = null ) {
		$old   = get_option( $option );
		$value = apply_filters( "pre_update_option_{$option}", $value, $old, $option );

		$GLOBALS['tsmlt_test_writes'][] = $option;

		if ( ! empty( $GLOBALS['tsmlt_test_fail_writes'][ $option ] ) ) {
			return false;
		}
		if ( ! empty( $GLOBALS['tsmlt_test_corrupt_writes'][ $option ] ) ) {
			$value = call_user_func( $GLOBALS['tsmlt_test_corrupt_writes'][ $option ], $value );
		}
		if ( $old === $value ) {
			return false;
		}

		$GLOBALS['tsmlt_test_options'][ $option ] = $value;
		if ( null !== $autoload ) {
			$GLOBALS['tsmlt_test_autoload'][ $option ] = $autoload;
		}
		return true;
	}

	/**
	 * Stub of delete_option().
	 *
	 * @param string $option Option name.
	 *
	 * @return bool
	 */
	function delete_option( $option ) {
		unset( $GLOBALS['tsmlt_test_options'][ $option ] );
		return true;
	}

	/**
	 * Stub of wp_cache_delete().
	 *
	 * @return bool
	 */
	function wp_cache_delete() {
		return true;
	}

	/**
	 * Stub of get_transient().
	 *
	 * @param string $name Transient name.
	 *
	 * @return mixed
	 */
	function get_transient( $name ) {
		return $GLOBALS['tsmlt_test_transients'][ $name ] ?? false;
	}

	/**
	 * Stub of set_transient().
	 *
	 * @param string $name  Transient name.
	 * @param mixed  $value Value.
	 *
	 * @return bool
	 */
	function set_transient( $name, $value ) {
		$GLOBALS['tsmlt_test_transients'][ $name ] = $value;
		return true;
	}

	/**
	 * Stub of wp_salt(): returns the swappable test salt.
	 *
	 * @param string $scheme Salt scheme (unused).
	 *
	 * @return string
	 */
	function wp_salt( $scheme = 'auth' ) {
		return $GLOBALS['tsmlt_test_salt'];
	}

	// -------------------------------------------------------------------------
	// HTTP
	// -------------------------------------------------------------------------

	/**
	 * Record the request and return the next queued fake response.
	 *
	 * @param string $url  Request URL.
	 * @param array  $args Request arguments.
	 *
	 * @return array|WP_Error
	 */
	function wp_remote_post( $url, $args ) {
		$GLOBALS['tsmlt_test_http']['requests'][] = [
			'url'  => $url,
			'args' => $args,
			'body' => json_decode( $args['body'], true ),
		];
		return array_shift( $GLOBALS['tsmlt_test_http']['responses'] );
	}

	/**
	 * Stub of is_wp_error().
	 *
	 * @param mixed $thing Value to check.
	 *
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}

	/**
	 * Stub of wp_remote_retrieve_response_code().
	 *
	 * @param array $response Fake response.
	 *
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return $response['code'];
	}

	/**
	 * Stub of wp_remote_retrieve_body().
	 *
	 * @param array $response Fake response.
	 *
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		return $response['body'];
	}

	// -------------------------------------------------------------------------
	// Formatting and i18n
	// -------------------------------------------------------------------------

	/**
	 * Stub of esc_html().
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	function esc_html( $text ) {
		return (string) $text;
	}

	/**
	 * Stub of esc_html__().
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	function esc_html__( $text ) {
		return (string) $text;
	}

	/**
	 * Stub of _n().
	 *
	 * @param string $single Singular text.
	 * @param string $plural Plural text.
	 * @param int    $number Count.
	 *
	 * @return string
	 */
	function _n( $single, $plural, $number ) {
		return 1 === (int) $number ? $single : $plural;
	}

	/**
	 * Stub of esc_html_e().
	 *
	 * @param string $text Text.
	 *
	 * @return void
	 */
	function esc_html_e( $text ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
	}

	/**
	 * Stub of esc_url().
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	function esc_url( $url ) {
		return (string) $url;
	}

	/**
	 * Stub of admin_url().
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . $path;
	}

	/**
	 * Stub of __().
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	function __( $text ) {
		return (string) $text;
	}

	/**
	 * Stub of sanitize_text_field().
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	function sanitize_text_field( $text ) {
		return trim( (string) $text );
	}

	/**
	 * Stub of sanitize_key().
	 *
	 * @param string $key Key.
	 *
	 * @return string
	 */
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}

	/**
	 * Stub of absint().
	 *
	 * @param mixed $value Value.
	 *
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}

	/**
	 * Stub of wp_parse_args().
	 *
	 * @param array $args     Arguments.
	 * @param array $defaults Defaults.
	 *
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = [] ) {
		return array_merge( $defaults, (array) $args );
	}

	/**
	 * Stub of wp_kses_post().
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	function wp_kses_post( $text ) {
		return (string) $text;
	}

	/**
	 * Stub of wp_json_encode().
	 *
	 * @param mixed $data Data.
	 *
	 * @return string
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}

	/**
	 * Stub of the plugin's tsmlt() accessor (Free, no Pro).
	 *
	 * @return object
	 */
	function tsmlt() {
		return new class() {
			/**
			 * Whether Pro is active.
			 *
			 * @return bool
			 */
			public function has_pro() {
				return false;
			}
		};
	}

	require dirname( __DIR__, 2 ) . '/autoload.php';
}
