<?php
/**
 * Keeps the settings UI model lists consistent with AiApi.
 *
 * @package TinySolutions\mlt
 */

use PHPUnit\Framework\TestCase;
use TinySolutions\mlt\Controllers\AI\AiApi;

/**
 * Reads MODEL_CATALOGUES from AiSettings.tsx and checks it against the
 * defaults and retirement tables in AiApi.
 */
class ModelCatalogueTest extends TestCase {

	/**
	 * Parsed catalogues: provider => [ default, offered[], legacy[id => note] ].
	 *
	 * @var array
	 */
	private static $catalogues;

	/**
	 * Parse the catalogue once from the TSX source.
	 */
	public static function setUpBeforeClass(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/js/Component/Settings/AiSettings.tsx' );
		$start  = strpos( $source, 'const MODEL_CATALOGUES' );
		$end    = strpos( $source, "\n};", $start );
		$block  = substr( $source, $start, $end - $start );

		$sections = preg_split( '/^\s{4}(chatgpt|gemini|claude): \{$/m', $block, -1, PREG_SPLIT_DELIM_CAPTURE );
		$parsed   = [];
		for ( $i = 1; $i < count( $sections ); $i += 2 ) {
			$body = $sections[ $i + 1 ];
			preg_match( "/defaultModel: '([^']+)'/", $body, $default );
			preg_match_all( "/\{ id: '([^']+)'/", $body, $ids );
			$legacy = [];
			if ( preg_match( '/legacy: \{(.*?)\n\s{8}\}/s', $body, $legacy_block ) ) {
				preg_match_all( "/'([^']+)': '([^']+)'/", $legacy_block[1], $pairs, PREG_SET_ORDER );
				foreach ( $pairs as $pair ) {
					$legacy[ $pair[1] ] = $pair[2];
				}
			}
			$parsed[ $sections[ $i ] ] = [
				'default' => $default[1] ?? '',
				'offered' => $ids[1],
				'legacy'  => $legacy,
			];
		}
		self::$catalogues = $parsed;
	}

	/**
	 * Read a private AiApi constant.
	 *
	 * @param string $name Constant name.
	 *
	 * @return array
	 */
	private function constant( string $name ): array {
		return ( new ReflectionClass( AiApi::class ) )->getConstant( $name );
	}

	/**
	 * Every provider is parsed and the UI default matches the backend default.
	 */
	public function test_ui_defaults_match_backend(): void {
		$this->assertSame( [ 'chatgpt', 'gemini', 'claude' ], array_keys( self::$catalogues ) );
		foreach ( AiApi::DEFAULT_MODELS as $provider => $model ) {
			$this->assertSame( $model, self::$catalogues[ $provider ]['default'], "UI default for $provider" );
			$this->assertContains( $model, self::$catalogues[ $provider ]['offered'], "$provider default is offered" );
		}
	}

	/**
	 * The exact Claude and Gemini choices offered to new users.
	 */
	public function test_claude_and_gemini_choices(): void {
		$this->assertSame(
			[ 'claude-haiku-5-5', 'claude-sonnet-5-5', 'claude-opus-5-5', 'claude-haiku-4-5-20251001', 'claude-sonnet-4-6', 'claude-opus-4-6' ],
			self::$catalogues['claude']['offered']
		);
		$this->assertSame(
			[ 'gemini-3.5-flash-lite', 'gemini-3.6-flash', 'gemini-3.8-flash', 'gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.5-pro' ],
			self::$catalogues['gemini']['offered']
		);
	}

	/**
	 * The OpenAI list is unchanged by this update.
	 */
	public function test_openai_choices_unchanged(): void {
		$this->assertSame(
			[ 'gpt-6-astra', 'gpt-6.1-sol', 'gpt-6-luna', 'gpt-5.6-sol', 'gpt-5.6-terra', 'gpt-5.6-luna', 'gpt-5.5', 'gpt-4o-mini', 'gpt-4o', 'gpt-4.1', 'gpt-4.1-mini' ],
			self::$catalogues['chatgpt']['offered']
		);
	}

	/**
	 * No retired, retiring or routed model is offered to new users.
	 */
	public function test_retired_models_are_not_offered(): void {
		$retired = [
			'chatgpt' => array_keys( $this->constant( 'RETIRED_OPENAI_MODELS' ) ),
			'claude'  => array_keys( $this->constant( 'RETIRED_CLAUDE_MODELS' ) ),
			'gemini'  => array_keys( $this->constant( 'ROUTED_GEMINI_MODELS' ) ),
		];
		foreach ( $retired as $provider => $models ) {
			$this->assertSame( [], array_values( array_intersect( $models, self::$catalogues[ $provider ]['offered'] ) ), $provider );
		}
	}

	/**
	 * Each legacy note in the UI names the replacement the backend really uses.
	 */
	public function test_legacy_notes_match_backend_fallbacks(): void {
		$tables = [
			'claude' => $this->constant( 'RETIRED_CLAUDE_MODELS' ),
			'gemini' => $this->constant( 'ROUTED_GEMINI_MODELS' ),
		];
		foreach ( $tables as $provider => $table ) {
			$this->assertNotEmpty( self::$catalogues[ $provider ]['legacy'], "$provider has legacy notes" );
			foreach ( self::$catalogues[ $provider ]['legacy'] as $model => $note ) {
				$this->assertArrayHasKey( $model, $table, "$model is handled by the backend" );
				$this->assertStringEndsWith( 'uses ' . $table[ $model ][1], $note, "$model note" );
			}
		}
	}

	/**
	 * Offered IDs are plain API model identifiers, without display text.
	 */
	public function test_offered_ids_are_well_formed(): void {
		foreach ( self::$catalogues as $provider => $catalogue ) {
			foreach ( $catalogue['offered'] as $model ) {
				$this->assertMatchesRegularExpression( '/^[a-z0-9][a-z0-9.\-]*$/', $model, $provider );
			}
			$this->assertSame( count( $catalogue['offered'] ), count( array_unique( $catalogue['offered'] ) ), "$provider has no duplicates" );
		}
	}
}
