<?php
/**
 * Provider request/response tests for AiApi, driven by fake HTTP responses.
 *
 * @package TinySolutions\mlt
 */

use PHPUnit\Framework\TestCase;
use TinySolutions\mlt\Controllers\AI\AiApi;

/**
 * Covers request shape, incomplete-response detection and retired-model
 * fallbacks for the OpenAI, Claude and Gemini integrations.
 */
class AiApiProviderTest extends TestCase {

	/**
	 * Reset the fake transport and pinned date before each test.
	 */
	protected function setUp(): void {
		tsmlt_test_reset();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Queue a fake HTTP response.
	 *
	 * @param int   $code HTTP status.
	 * @param mixed $body Array (JSON-encoded) or raw string body.
	 */
	private function queue( int $code, $body ): void {
		$GLOBALS['tsmlt_test_http']['responses'][] = [
			'code' => $code,
			'body' => is_string( $body ) ? $body : json_encode( $body ),
		];
	}

	/**
	 * Return the most recent recorded request.
	 *
	 * @return array{url: string, args: array, body: array}
	 */
	private function last_request(): array {
		return end( $GLOBALS['tsmlt_test_http']['requests'] );
	}

	/**
	 * Call the private dispatcher with a provider and saved model.
	 *
	 * @param string $provider Provider key.
	 * @param string $model    Saved model ID.
	 *
	 * @return string Provider text.
	 */
	private function dispatch( string $provider, string $model = '' ): string {
		$method = new ReflectionMethod( AiApi::class, 'dispatch_to_provider' );
		// Required before PHP 8.1 (the plugin supports 7.4); deprecated in 8.5.
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Keys sit in tsmlt_settings as plaintext, as saved by earlier versions,
		// so these tests also cover the pre-migration (backward-compatible) path.
		$settings = [
			'ai_chatgpt_key'   => 'sk-test',
			'ai_claude_key'    => 'sk-ant-test',
			'ai_gemini_key'    => 'AIza-test',
			'ai_chatgpt_model' => $model,
			'ai_claude_model'  => $model,
			'ai_gemini_model'  => $model,
		];
		$GLOBALS['tsmlt_test_options']['tsmlt_settings'] = $settings;

		return $method->invoke( new AiApi(), $provider, 'prompt', $settings );
	}

	/**
	 * Assert that dispatching throws an exception whose message contains a string.
	 *
	 * @param string $provider Provider key.
	 * @param string $needle   Expected message fragment.
	 * @param string $model    Saved model ID.
	 */
	private function assert_dispatch_fails( string $provider, string $needle, string $model = '' ): void {
		// The assertion stays outside the try: PHPUnit's own failure is an
		// Exception too, and catching it here would hide a missing error.
		$message = null;
		try {
			$this->dispatch( $provider, $model );
		} catch ( \Exception $e ) {
			$message = $e->getMessage();
		}

		$this->assertNotNull( $message, 'Expected an exception containing: ' . $needle );
		$this->assertStringContainsString( $needle, $message );
	}

	/**
	 * Build an OpenAI Chat Completions reply.
	 *
	 * @param string|null $content Message content.
	 * @param string      $finish  Finish reason.
	 * @param string|null $refusal Refusal text.
	 *
	 * @return array
	 */
	private function openai_reply( ?string $content, string $finish = 'stop', ?string $refusal = null ): array {
		return [
			'choices' => [
				[
					'message'       => [
						'content' => $content,
						'refusal' => $refusal,
					],
					'finish_reason' => $finish,
				],
			],
		];
	}

	// -------------------------------------------------------------------------
	// OpenAI
	// -------------------------------------------------------------------------

	/**
	 * Each model gets the right reasoning effort and token ceiling.
	 *
	 * @dataProvider openai_request_cases
	 *
	 * @param string      $model      Saved model ID.
	 * @param string|null $effort     Expected reasoning_effort, null when omitted.
	 * @param int         $max_tokens Expected max_completion_tokens.
	 */
	public function test_openai_request_shape( string $model, ?string $effort, int $max_tokens ): void {
		$GLOBALS['tsmlt_test_today'] = '2026-10-12';
		$this->queue( 200, $this->openai_reply( '1. A title' ) );

		$this->dispatch( 'chatgpt', $model );
		$body = $this->last_request()['body'];

		$this->assertSame( $effort, $body['reasoning_effort'] ?? null );
		$this->assertSame( $max_tokens, $body['max_completion_tokens'] );
		$this->assertArrayNotHasKey( 'max_tokens', $body );
	}

	/**
	 * Cases for test_openai_request_shape.
	 *
	 * @return array
	 */
	public function openai_request_cases(): array {
		return [
			'default model'          => [ '', 'none', 2000 ],
			'gpt-6-astra'            => [ 'gpt-6-astra', 'low', 8000 ],
			'gpt-6.1-sol'            => [ 'gpt-6.1-sol', 'low', 8000 ],
			'gpt-5.5 snapshot'       => [ 'gpt-5.5-2026-04-23', 'none', 2000 ],
			'gpt-5.1 legacy'         => [ 'gpt-5.1', 'none', 2000 ],
			'gpt-5 legacy'           => [ 'gpt-5', 'minimal', 8000 ],
			'o4-mini undocumented'   => [ 'o4-mini', null, 8000 ],
			'gpt-5-mini undocumented' => [ 'gpt-5-mini', null, 8000 ],
			'gpt-4o-mini'            => [ 'gpt-4o-mini', null, 2000 ],
			'unknown id'             => [ 'my-custom-model', null, 2000 ],
		];
	}

	/**
	 * A complete reply is returned unchanged.
	 */
	public function test_openai_normal_reply(): void {
		$this->queue( 200, $this->openai_reply( '1. A title' ) );
		$this->assertSame( '1. A title', $this->dispatch( 'chatgpt', 'gpt-4o' ) );
	}

	/**
	 * Truncated, refused, filtered, empty and malformed replies all fail.
	 *
	 * @dataProvider openai_failure_cases
	 *
	 * @param int    $code   HTTP status.
	 * @param mixed  $body   Response body.
	 * @param string $needle Expected message fragment.
	 */
	public function test_openai_failures( int $code, $body, string $needle ): void {
		$this->queue( $code, $body );
		$this->assert_dispatch_fails( 'chatgpt', $needle, 'gpt-6-luna' );
	}

	/**
	 * Cases for test_openai_failures.
	 *
	 * @return array
	 */
	public function openai_failure_cases(): array {
		return [
			'truncated'      => [ 200, $this->openai_reply( '1. A tit', 'length' ), 'token limit reached' ],
			'content filter' => [ 200, $this->openai_reply( null, 'content_filter' ), 'content filter' ],
			'refusal'        => [ 200, $this->openai_reply( null, 'stop', 'I cannot help' ), 'declined the request: I cannot help' ],
			'null content'   => [ 200, $this->openai_reply( null ), 'Empty response from OpenAI' ],
			'malformed body' => [ 200, '<html>bad gateway</html>', 'Empty response from OpenAI' ],
			'unknown model'  => [ 404, [ 'error' => [ 'message' => 'The model `x` does not exist' ] ], 'OpenAI HTTP 404: The model `x` does not exist' ],
			'rate limit'     => [ 429, [ 'error' => [ 'message' => 'Rate limit reached' ] ], 'OpenAI HTTP 429: Rate limit reached' ],
		];
	}

	/**
	 * A transport error (e.g. timeout) surfaces its message.
	 */
	public function test_openai_transport_error(): void {
		$GLOBALS['tsmlt_test_http']['responses'][] = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		$this->assert_dispatch_fails( 'chatgpt', 'Operation timed out', 'gpt-4o' );
	}

	/**
	 * Retired models switch exactly on their shutdown date (UTC), not before.
	 *
	 * @dataProvider openai_fallback_cases
	 *
	 * @param string $today    Pinned UTC date.
	 * @param string $saved    Saved model ID.
	 * @param string $expected Model ID expected in the request.
	 */
	public function test_openai_retired_model_fallback( string $today, string $saved, string $expected ): void {
		$GLOBALS['tsmlt_test_today'] = $today;
		$this->queue( 200, $this->openai_reply( 'ok' ) );

		$this->dispatch( 'chatgpt', $saved );

		$this->assertSame( $expected, $this->last_request()['body']['model'] );
	}

	/**
	 * Cases for test_openai_retired_model_fallback.
	 *
	 * @return array
	 */
	public function openai_fallback_cases(): array {
		return [
			'o4-mini day before shutdown'  => [ '2026-10-22', 'o4-mini', 'o4-mini' ],
			'o4-mini on shutdown date'     => [ '2026-10-23', 'o4-mini', 'gpt-5.6-terra' ],
			'o3-mini on shutdown date'     => [ '2026-10-23', 'o3-mini', 'gpt-5.6-sol' ],
			'gpt-4.1-nano on shutdown'     => [ '2026-10-23', 'gpt-4.1-nano', 'gpt-5.6-luna' ],
			'gpt-5-mini before shutdown'   => [ '2026-12-10', 'gpt-5-mini', 'gpt-5-mini' ],
			'gpt-5-mini on shutdown date'  => [ '2026-12-11', 'gpt-5-mini', 'gpt-5.6-terra' ],
			'gpt-5.1 still served'         => [ '2026-10-12', 'gpt-5.1', 'gpt-5.1' ],
			'gpt-5.1 after shutdown'       => [ '2027-04-01', 'gpt-5.1', 'gpt-6-sol' ],
			'o1-mini already shut down'    => [ '2026-10-12', 'o1-mini', 'gpt-5.6-terra' ],
			'search preview shut down'     => [ '2026-10-12', 'gpt-4o-search-preview', 'gpt-5.6-terra' ],
			'current model untouched'      => [ '2030-01-01', 'gpt-4o', 'gpt-4o' ],
			'unknown model untouched'      => [ '2030-01-01', 'gpt-unknown', 'gpt-unknown' ],
			'empty setting uses default'   => [ '2026-10-12', '', 'gpt-6-luna' ],
		];
	}

	// -------------------------------------------------------------------------
	// Claude
	// -------------------------------------------------------------------------

	/**
	 * The request carries the 2,000-token ceiling and the answer is joined from
	 * every text block, skipping non-text blocks.
	 */
	public function test_claude_joins_text_blocks(): void {
		$this->queue(
			200,
			[
				'stop_reason' => 'end_turn',
				'content'     => [
					[
						'type'     => 'thinking',
						'thinking' => '',
					],
					[
						'type' => 'text',
						'text' => "1. First\n",
					],
					[
						'type' => 'text',
						'text' => '2. Second',
					],
				],
			]
		);

		$this->assertSame( "1. First\n2. Second", $this->dispatch( 'claude', 'claude-sonnet-4-6' ) );
		$this->assertSame( 2000, $this->last_request()['body']['max_tokens'] );
	}

	/**
	 * Truncated, refused, empty and error replies fail.
	 *
	 * @dataProvider claude_failure_cases
	 *
	 * @param int    $code   HTTP status.
	 * @param mixed  $body   Response body.
	 * @param string $needle Expected message fragment.
	 */
	public function test_claude_failures( int $code, $body, string $needle ): void {
		$this->queue( $code, $body );
		$this->assert_dispatch_fails( 'claude', $needle, 'claude-haiku-4-5-20251001' );
	}

	/**
	 * Cases for test_claude_failures.
	 *
	 * @return array
	 */
	public function claude_failure_cases(): array {
		$error = static function ( string $type, string $message ): array {
			return [
				'type'  => 'error',
				'error' => [
					'type'    => $type,
					'message' => $message,
				],
			];
		};

		return [
			'truncated'      => [
				200,
				[
					'stop_reason' => 'max_tokens',
					'content'     => [
						[
							'type' => 'text',
							'text' => '{"title":"A tr',
						],
					],
				],
				'token limit reached',
			],
			'refusal'        => [
				200,
				[
					'stop_reason' => 'refusal',
					'content'     => [],
				],
				'Claude declined the request',
			],
			'no text blocks' => [
				200,
				[
					'stop_reason' => 'end_turn',
					'content'     => [],
				],
				'Empty response from Claude',
			],
			'malformed body' => [ 200, 'not json', 'Empty response from Claude' ],
			'rate limit'     => [ 429, $error( 'rate_limit_error', 'Number of requests has exceeded your rate limit' ), 'Claude HTTP 429: Number of requests' ],
			'overloaded'     => [ 529, $error( 'overloaded_error', 'Overloaded' ), 'Claude HTTP 529: Overloaded' ],
		];
	}

	/**
	 * Sonnet 4.5 moves to Sonnet 4.6 only from its retirement date.
	 *
	 * @dataProvider claude_fallback_cases
	 *
	 * @param string $today    Pinned UTC date.
	 * @param string $saved    Saved model ID.
	 * @param string $expected Model ID expected in the request.
	 */
	public function test_claude_retired_model_fallback( string $today, string $saved, string $expected ): void {
		$GLOBALS['tsmlt_test_today'] = $today;
		$this->queue(
			200,
			[
				'stop_reason' => 'end_turn',
				'content'     => [
					[
						'type' => 'text',
						'text' => 'ok',
					],
				],
			]
		);

		$this->dispatch( 'claude', $saved );

		$this->assertSame( $expected, $this->last_request()['body']['model'] );
	}

	/**
	 * Cases for test_claude_retired_model_fallback.
	 *
	 * @return array
	 */
	public function claude_fallback_cases(): array {
		return [
			'sonnet 4.5 before retirement' => [ '2026-11-29', 'claude-sonnet-4-5', 'claude-sonnet-4-5' ],
			'sonnet 4.5 on retirement'     => [ '2026-11-30', 'claude-sonnet-4-5', 'claude-sonnet-5-5' ],
			'sonnet 4.5 snapshot retired'  => [ '2026-12-01', 'claude-sonnet-4-5-20250929', 'claude-sonnet-5-5' ],
			'empty setting uses default'   => [ '2030-01-01', '', 'claude-haiku-5-5' ],
			'haiku 4.5 kept when saved'    => [ '2030-01-01', 'claude-haiku-4-5-20251001', 'claude-haiku-4-5-20251001' ],
			'sonnet 4.6 kept when saved'   => [ '2030-01-01', 'claude-sonnet-4-6', 'claude-sonnet-4-6' ],
			'unknown model untouched'      => [ '2030-01-01', 'claude-custom-test', 'claude-custom-test' ],
		];
	}

	// -------------------------------------------------------------------------
	// Gemini
	// -------------------------------------------------------------------------

	/**
	 * The key travels in a header, never the URL, no output cap is set, and the
	 * answer is joined from text parts while thought parts are skipped.
	 */
	public function test_gemini_request_and_text_parts(): void {
		$this->queue(
			200,
			[
				'candidates' => [
					[
						'finishReason' => 'STOP',
						'content'      => [
							'parts' => [
								[
									'text'    => 'thinking...',
									'thought' => true,
								],
								[ 'text' => '1. First ' ],
								[ 'text' => 'part' ],
								[ 'thoughtSignature' => 'abc' ],
							],
						],
					],
				],
			]
		);

		$this->assertSame( '1. First part', $this->dispatch( 'gemini', 'gemini-3.5-flash-lite' ) );

		$request = $this->last_request();
		$this->assertStringNotContainsString( 'AIza-test', $request['url'] );
		$this->assertStringNotContainsString( 'key=', $request['url'] );
		$this->assertSame( 'AIza-test', $request['args']['headers']['x-goog-api-key'] );
		$this->assertArrayNotHasKey( 'generationConfig', $request['body'] );
	}

	/**
	 * Truncated, blocked, empty and error replies fail.
	 *
	 * @dataProvider gemini_failure_cases
	 *
	 * @param int    $code   HTTP status.
	 * @param mixed  $body   Response body.
	 * @param string $needle Expected message fragment.
	 */
	public function test_gemini_failures( int $code, $body, string $needle ): void {
		$this->queue( $code, $body );
		$this->assert_dispatch_fails( 'gemini', $needle, 'gemini-2.5-flash' );
	}

	/**
	 * Cases for test_gemini_failures.
	 *
	 * @return array
	 */
	public function gemini_failure_cases(): array {
		$candidate = static function ( string $finish, array $parts ): array {
			return [
				'candidates' => [
					[
						'finishReason' => $finish,
						'content'      => [ 'parts' => $parts ],
					],
				],
			];
		};

		return [
			'truncated'      => [ 200, $candidate( 'MAX_TOKENS', [ [ 'text' => '1. A tit' ] ] ), 'token limit reached' ],
			'safety stop'    => [ 200, $candidate( 'SAFETY', [] ), 'did not complete the response (SAFETY)' ],
			'recitation'     => [ 200, $candidate( 'RECITATION', [ [ 'text' => 'partial' ] ] ), '(RECITATION)' ],
			'prompt blocked' => [ 200, [ 'promptFeedback' => [ 'blockReason' => 'PROHIBITED_CONTENT' ] ], 'blocked the request (PROHIBITED_CONTENT)' ],
			'no candidates'  => [ 200, [ 'candidates' => [] ], 'Empty response from Gemini' ],
			'only thoughts'  => [
				200,
				$candidate(
					'STOP',
					[
						[
							'text'    => 'hmm',
							'thought' => true,
						],
					]
				),
				'Empty response from Gemini',
			],
			'malformed body' => [ 200, '<html>', 'Empty response from Gemini' ],
			'rate limit'     => [ 429, [ 'error' => [ 'message' => 'Resource has been exhausted' ] ], 'Gemini HTTP 429: Resource has been exhausted' ],
		];
	}

	// -------------------------------------------------------------------------
	// Claude and Gemini per-model request settings
	// -------------------------------------------------------------------------

	/**
	 * Claude 5.5 models get the thinking setting each one accepts; Claude 4
	 * models get none, as before.
	 *
	 * @dataProvider claude_request_cases
	 *
	 * @param string     $model      Saved model ID.
	 * @param array|null $thinking   Expected thinking field.
	 * @param array|null $output     Expected output_config field.
	 * @param int        $max_tokens Expected max_tokens.
	 */
	public function test_claude_request_settings( string $model, ?array $thinking, ?array $output, int $max_tokens ): void {
		$this->queue(
			200,
			[
				'stop_reason' => 'end_turn',
				'content'     => [
					[
						'type' => 'text',
						'text' => 'ok',
					],
				],
			]
		);

		$this->dispatch( 'claude', $model );
		$body = $this->last_request()['body'];

		$this->assertSame( $thinking, $body['thinking'] ?? null );
		$this->assertSame( $output, $body['output_config'] ?? null );
		$this->assertSame( $max_tokens, $body['max_tokens'] );
		$this->assertArrayNotHasKey( 'extra_tokens', $body );
		$this->assertArrayNotHasKey( 'temperature', $body );
	}

	/**
	 * Cases for test_claude_request_settings.
	 *
	 * @return array
	 */
	public function claude_request_cases(): array {
		return [
			'haiku 5.5 (default)' => [ '', [ 'type' => 'disabled' ], null, 2000 ],
			'sonnet 5.5'          => [ 'claude-sonnet-5-5', [ 'type' => 'between_tools' ], null, 2000 ],
			'opus 5.5'            => [ 'claude-opus-5-5', null, [ 'effort' => 'low' ], 8000 ],
			'haiku 4.5'           => [ 'claude-haiku-4-5-20251001', null, null, 2000 ],
			'sonnet 4.6'          => [ 'claude-sonnet-4-6', null, null, 2000 ],
			'opus 4.6'            => [ 'claude-opus-4-6', null, null, 2000 ],
			'unknown model'       => [ 'claude-custom-test', null, null, 2000 ],
		];
	}

	/**
	 * After its retirement, Sonnet 4.5 is sent as Sonnet 5.5 with Sonnet 5.5's
	 * thinking setting, so the request stays valid.
	 */
	public function test_retired_sonnet_uses_replacement_request_settings(): void {
		$GLOBALS['tsmlt_test_today'] = '2026-11-30';
		$this->queue(
			200,
			[
				'stop_reason' => 'end_turn',
				'content'     => [
					[
						'type' => 'text',
						'text' => 'ok',
					],
				],
			]
		);

		$this->dispatch( 'claude', 'claude-sonnet-4-5' );
		$body = $this->last_request()['body'];

		$this->assertSame( 'claude-sonnet-5-5', $body['model'] );
		$this->assertSame( [ 'type' => 'between_tools' ], $body['thinking'] );
	}

	/**
	 * Gemini models resolve to the right model and thinking level.
	 *
	 * @dataProvider gemini_model_cases
	 *
	 * @param string      $today    Pinned UTC date.
	 * @param string      $saved    Saved model ID.
	 * @param string      $expected Model ID expected in the URL.
	 * @param string|null $thinking Expected thinkingLevel, null when omitted.
	 */
	public function test_gemini_model_resolution( string $today, string $saved, string $expected, ?string $thinking ): void {
		$GLOBALS['tsmlt_test_today'] = $today;
		$this->queue(
			200,
			[
				'candidates' => [
					[
						'finishReason' => 'STOP',
						'content'      => [ 'parts' => [ [ 'text' => 'ok' ] ] ],
					],
				],
			]
		);

		$this->dispatch( 'gemini', $saved );
		$request = $this->last_request();

		$this->assertStringEndsWith( '/models/' . $expected . ':generateContent', $request['url'] );
		$this->assertSame( $thinking, $request['body']['generationConfig']['thinkingConfig']['thinkingLevel'] ?? null );
	}

	/**
	 * Cases for test_gemini_model_resolution.
	 *
	 * @return array
	 */
	public function gemini_model_cases(): array {
		return [
			'empty setting uses default'      => [ '2026-10-12', '', 'gemini-3.5-flash-lite', null ],
			'3.6 flash at minimal thinking'   => [ '2026-10-12', 'gemini-3.6-flash', 'gemini-3.6-flash', 'MINIMAL' ],
			'3.8 flash at low thinking'       => [ '2026-10-12', 'gemini-3.8-flash', 'gemini-3.8-flash', 'LOW' ],
			'2.5 flash kept, no thinkingLevel' => [ '2026-10-12', 'gemini-2.5-flash', 'gemini-2.5-flash', null ],
			'2.5 pro kept, no thinkingLevel'  => [ '2026-10-12', 'gemini-2.5-pro', 'gemini-2.5-pro', null ],
			'3.5 flash before deprecation'    => [ '2026-10-07', 'gemini-3.5-flash', 'gemini-3.5-flash', null ],
			'3.5 flash routed to 3.6'         => [ '2026-10-08', 'gemini-3.5-flash', 'gemini-3.6-flash', 'MINIMAL' ],
			'3.7 flash routed to 3.8'         => [ '2026-10-12', 'gemini-3.7-flash', 'gemini-3.8-flash', 'LOW' ],
			'retired 2.0 flash mapping kept'  => [ '2026-10-12', 'gemini-2.0-flash', 'gemini-2.5-flash', null ],
			'retired 1.5 pro mapping kept'    => [ '2026-10-12', 'gemini-1.5-pro', 'gemini-2.5-pro', null ],
			'unknown model untouched'         => [ '2026-10-12', 'gemini-custom-test', 'gemini-custom-test', null ],
		];
	}

	// -------------------------------------------------------------------------
	// Bulk JSON parsing (shared by every provider)
	// -------------------------------------------------------------------------

	/**
	 * Call the private bulk parser.
	 *
	 * @param string   $text   Raw provider text.
	 * @param string[] $fields Requested fields.
	 *
	 * @return array
	 */
	private function parse_bulk( string $text, array $fields ): array {
		$method = new ReflectionMethod( AiApi::class, 'parse_bulk_response' );
		// Required before PHP 8.1 (the plugin supports 7.4); deprecated in 8.5.
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( new AiApi(), $text, $fields );
	}

	/**
	 * Valid JSON yields only requested, non-empty fields.
	 */
	public function test_bulk_parse_valid(): void {
		$result = $this->parse_bulk( '```json {"title":"Red Barn","alt_text":"","extra":"x"} ```', [ 'title', 'alt_text' ] );
		$this->assertSame( [ 'title' => 'Red Barn' ], $result );
	}

	/**
	 * Truncated or valueless JSON is rejected, never returned as empty metadata.
	 *
	 * @dataProvider bulk_invalid_cases
	 *
	 * @param string $text Raw provider text.
	 */
	public function test_bulk_parse_rejects_invalid( string $text ): void {
		$this->expectException( \Exception::class );
		$this->parse_bulk( $text, [ 'title', 'caption' ] );
	}

	/**
	 * Cases for test_bulk_parse_rejects_invalid.
	 *
	 * @return array
	 */
	public function bulk_invalid_cases(): array {
		return [
			'truncated json'  => [ '{"title":"Red Barn","caption":"A red' ],
			'all empty'       => [ '{"title":"","caption":"  "}' ],
			'no json'         => [ 'Sorry, I cannot see the image.' ],
			'wrong keys only' => [ '{"name":"Red Barn"}' ],
		];
	}
}
