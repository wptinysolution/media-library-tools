<?php
/**
 * PHPUnit bootstrap: minimal WordPress stubs so AiApi can run without
 * WordPress, plus a fake HTTP transport that records requests and returns
 * queued provider responses. No real network calls are made.
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

	$GLOBALS['tsmlt_test_http'] = [
		'responses' => [],
		'requests'  => [],
	];

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
	 * Stub of apply_filters(): returns the value unchanged.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 *
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		return $value;
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

	require dirname( __DIR__ ) . '/app/Controllers/AI/AiApi.php';
}
