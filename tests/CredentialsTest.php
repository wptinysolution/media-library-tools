<?php
/**
 * Tests for encrypted API key storage, migration and admin exposure.
 *
 * @package TinySolutions\mlt
 */

use PHPUnit\Framework\TestCase;
use TinySolutions\mlt\Controllers\Admin\Api;
use TinySolutions\mlt\Controllers\AI\AiApi;
use TinySolutions\mlt\Controllers\AI\CredentialHooks;
use TinySolutions\mlt\Controllers\AI\Credentials;
use TinySolutions\mlt\Helpers\Fns;

/**
 * Covers encryption, tamper and salt-change handling, the plaintext
 * migration, stale-write protection, the admin endpoints and uninstall.
 */
class CredentialsTest extends TestCase {

	/**
	 * Fake keys; never real credentials.
	 */
	const OPENAI_KEY = 'sk-test-openai-0000000000000000';
	const GEMINI_KEY = 'AIza-test-gemini-000000000000000';
	const CLAUDE_KEY = 'sk-ant-test-claude-00000000000000';

	/**
	 * Debug log file for this test.
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * Reset fake WordPress state, register the credential hooks, and route
	 * debug logging to a temporary file.
	 */
	protected function setUp(): void {
		tsmlt_test_reset();
		Credentials::flush_cache();

		// Register the real hooks (pre_update_option filter etc.) on the fresh registry.
		$constructor = new ReflectionMethod( CredentialHooks::class, '__construct' );
		if ( PHP_VERSION_ID < 80100 ) {
			$constructor->setAccessible( true );
		}
		$constructor->invoke( CredentialHooks::instance() );

		$this->log_file = tempnam( sys_get_temp_dir(), 'tsmlt-log' );
		ini_set( 'error_log', $this->log_file ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- test-only log capture.
	}

	/**
	 * Remove the temporary log file.
	 */
	protected function tearDown(): void {
		if ( is_file( $this->log_file ) ) {
			unlink( $this->log_file );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Store plaintext keys the way earlier plugin versions did.
	 *
	 * @param array $keys Field => key.
	 */
	private function legacy_keys( array $keys ): void {
		$settings = $GLOBALS['tsmlt_test_options']['tsmlt_settings'] ?? [ 'ai_provider' => 'chatgpt' ];
		foreach ( $keys as $field => $key ) {
			$settings[ $field ] = $key;
		}
		// Written directly, as an older plugin version (without the filter) would.
		$GLOBALS['tsmlt_test_options']['tsmlt_settings'] = $settings;
	}

	/**
	 * Current encrypted records.
	 *
	 * @return array
	 */
	private function records(): array {
		return $GLOBALS['tsmlt_test_options'][ Credentials::OPTION ] ?? [];
	}

	/**
	 * Current plaintext field value in tsmlt_settings.
	 *
	 * @param string $field Field name.
	 *
	 * @return string|null
	 */
	private function plaintext( string $field ) {
		return $GLOBALS['tsmlt_test_options']['tsmlt_settings'][ $field ] ?? null;
	}

	/**
	 * Change the site's salt, as a rotation or migration would.
	 *
	 * @param string $salt New salt.
	 */
	private function change_salt( string $salt ): void {
		$GLOBALS['tsmlt_test_salt'] = $salt;
		Credentials::flush_cache();
	}

	/**
	 * Run AiApi's provider dispatch for OpenAI with a fake success reply.
	 *
	 * @return string Provider text.
	 */
	private function dispatch_openai(): string {
		$GLOBALS['tsmlt_test_http']['responses'][] = [
			'code' => 200,
			'body' => json_encode(
				[
					'choices' => [
						[
							'message'       => [ 'content' => 'ok' ],
							'finish_reason' => 'stop',
						],
					],
				]
			),
		];

		$method = new ReflectionMethod( AiApi::class, 'dispatch_to_provider' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( new AiApi(), 'chatgpt', 'prompt', get_option( 'tsmlt_settings', [] ) );
	}

	/**
	 * Authorization header of the last HTTP request.
	 *
	 * @return string
	 */
	private function last_auth_header(): string {
		$request = end( $GLOBALS['tsmlt_test_http']['requests'] );
		return $request['args']['headers']['Authorization'] ?? '';
	}

	/**
	 * Assert a string contains none of the fake keys or any stored ciphertext.
	 *
	 * @param string $haystack Text to check.
	 */
	private function assert_no_secrets( string $haystack ): void {
		foreach ( [ self::OPENAI_KEY, self::GEMINI_KEY, self::CLAUDE_KEY ] as $key ) {
			$this->assertStringNotContainsString( $key, $haystack );
		}
		foreach ( $this->records() as $record ) {
			if ( ! empty( $record['ct'] ) ) {
				$this->assertStringNotContainsString( $record['ct'], $haystack );
				$this->assertStringNotContainsString( str_replace( '/', '\/', $record['ct'] ), $haystack );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Encryption
	// -------------------------------------------------------------------------

	/**
	 * A saved key round-trips, is stored only encrypted, and autoload is off.
	 */
	public function test_set_encrypts_and_round_trips(): void {
		$this->assertTrue( Credentials::set( 'chatgpt', self::OPENAI_KEY ) );

		$resolved = Credentials::resolve( 'chatgpt' );
		$this->assertSame( self::OPENAI_KEY, $resolved['key'] );
		$this->assertSame( Credentials::STATUS_SAVED, $resolved['status'] );

		$record = $this->records()['chatgpt'];
		$this->assertSame( 1, $record['v'] );
		$this->assertSame( 16, strlen( $record['kid'] ) );
		$this->assertSame( 24, strlen( base64_decode( $record['nonce'] ) ) );
		$this->assertStringNotContainsString( self::OPENAI_KEY, serialize( $GLOBALS['tsmlt_test_options'] ) );
		$this->assertFalse( $GLOBALS['tsmlt_test_autoload'][ Credentials::OPTION ] );
	}

	/**
	 * Every save uses a fresh nonce.
	 */
	public function test_nonces_are_unique(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );
		$first = $this->records()['chatgpt'];
		Credentials::set( 'chatgpt', self::OPENAI_KEY );
		$second = $this->records()['chatgpt'];

		$this->assertNotSame( $first['nonce'], $second['nonce'] );
		$this->assertNotSame( $first['ct'], $second['ct'] );
	}

	/**
	 * A changed ciphertext is rejected as damaged and kept, not deleted.
	 */
	public function test_tampered_ciphertext_is_unreadable_and_preserved(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );
		$ct      = base64_decode( $this->records()['chatgpt']['ct'] );
		$ct[0]   = chr( ord( $ct[0] ) ^ 1 );
		$GLOBALS['tsmlt_test_options'][ Credentials::OPTION ]['chatgpt']['ct'] = base64_encode( $ct );
		$tampered = $this->records();

		$resolved = Credentials::resolve( 'chatgpt' );
		$this->assertSame( '', $resolved['key'] );
		$this->assertSame( Credentials::STATUS_UNREADABLE, $resolved['status'] );
		$this->assertSame( Credentials::REASON_DAMAGED, $resolved['reason'] );

		Credentials::maybe_migrate();
		$this->assertSame( $tampered, $this->records() );
	}

	/**
	 * A ciphertext moved to another provider's slot fails authentication.
	 */
	public function test_ciphertext_is_bound_to_its_provider(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );
		$GLOBALS['tsmlt_test_options'][ Credentials::OPTION ]['gemini'] = $this->records()['chatgpt'];

		$resolved = Credentials::resolve( 'gemini' );
		$this->assertSame( '', $resolved['key'] );
		$this->assertSame( Credentials::STATUS_UNREADABLE, $resolved['status'] );
	}

	/**
	 * An unknown record version is never decrypted.
	 */
	public function test_unknown_format_version_is_unreadable(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );
		$GLOBALS['tsmlt_test_options'][ Credentials::OPTION ]['chatgpt']['v'] = 2;

		$this->assertSame( Credentials::STATUS_UNREADABLE, Credentials::resolve( 'chatgpt' )['status'] );
	}

	/**
	 * Ciphertext written here decrypts with WordPress 6.5's pure-PHP
	 * sodium_compat, using an independent re-implementation of the derivation.
	 */
	public function test_interoperates_with_wordpress_sodium_compat(): void {
		$autoload = $this->find_sodium_compat();
		if ( '' === $autoload ) {
			$this->markTestSkipped( 'No WordPress sodium_compat found; set TSMLT_WP_DIR to a WordPress install.' );
		}
		require_once $autoload;

		Credentials::set( 'claude', self::CLAUDE_KEY );
		$record = $this->records()['claude'];

		$master = ParagonIE_Sodium_Crypto::generichash( $GLOBALS['tsmlt_test_salt'], '', 32 );
		$key    = ParagonIE_Sodium_Compat::crypto_kdf_derive_from_key( 32, 1, 'tsmltcrd', $master );
		$plain  = ParagonIE_Sodium_Crypto::aead_xchacha20poly1305_ietf_decrypt(
			base64_decode( $record['ct'] ),
			'tsmlt-ai-credential|v1|claude',
			base64_decode( $record['nonce'] ),
			$key
		);

		$this->assertSame( self::CLAUDE_KEY, $plain );
	}

	/**
	 * Locate a WordPress sodium_compat autoloader for the interop test.
	 *
	 * @return string Path, or '' when none is available.
	 */
	private function find_sodium_compat(): string {
		$dirs = array_filter(
			[
				(string) getenv( 'TSMLT_WP_DIR' ),
				( getenv( 'HOME' ) ?: '' ) . '/Local Sites/plugindev/app/public',
			]
		);
		foreach ( $dirs as $dir ) {
			$file = rtrim( $dir, '/' ) . '/wp-includes/sodium_compat/autoload.php';
			if ( is_readable( $file ) ) {
				return $file;
			}
		}
		return '';
	}

	// -------------------------------------------------------------------------
	// Salt changes
	// -------------------------------------------------------------------------

	/**
	 * After a salt change the key is unreadable, nothing is sent, the record
	 * is kept, and restoring the salt makes it work again.
	 */
	public function test_salt_change_blocks_requests_and_recovers_on_restore(): void {
		$original = $GLOBALS['tsmlt_test_salt'];
		Credentials::set( 'chatgpt', self::OPENAI_KEY );
		$stored = $this->records();

		$this->change_salt( 'rotated-salt-by-security-plugin' );

		$resolved = Credentials::resolve( 'chatgpt' );
		$this->assertSame( Credentials::STATUS_UNREADABLE, $resolved['status'] );
		$this->assertSame( Credentials::REASON_KEY_CHANGED, $resolved['reason'] );

		try {
			$this->dispatch_openai();
			$this->fail( 'Expected an exception for an unreadable key.' );
		} catch ( \PHPUnit\Framework\AssertionFailedError $e ) {
			throw $e;
		} catch ( \Exception $e ) {
			$this->assertStringContainsString( "can't be read", $e->getMessage() );
		}
		$this->assertSame( [], $GLOBALS['tsmlt_test_http']['requests'], 'No request may be sent with an unreadable key.' );

		Credentials::maybe_migrate();
		$this->assertSame( $stored, $this->records(), 'The unreadable record must be preserved.' );

		$this->change_salt( $original );
		$this->assertSame( self::OPENAI_KEY, Credentials::resolve( 'chatgpt' )['key'] );
	}

	/**
	 * Re-entering the key after a salt change recovers it.
	 */
	public function test_replacing_an_unreadable_key_recovers(): void {
		Credentials::set( 'gemini', self::GEMINI_KEY );
		$this->change_salt( 'new-host-salt' );

		$this->assertTrue( Credentials::set( 'gemini', self::GEMINI_KEY ) );
		$this->assertSame( Credentials::STATUS_SAVED, Credentials::resolve( 'gemini' )['status'] );
		$this->assertSame( [], Credentials::unreadable() );
	}

	/**
	 * A plaintext key next to an unreadable record (e.g. a downgrade after a
	 * salt change) keeps working, and the unreadable record is not overwritten.
	 */
	public function test_migration_never_overwrites_an_unreadable_record(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );
		$this->change_salt( 'staging-clone-salt' );
		$unreadable = $this->records();

		$this->legacy_keys( [ 'ai_chatgpt_key' => 'sk-test-entered-in-older-version' ] );
		$this->assertFalse( Credentials::migrate() );

		$this->assertSame( $unreadable, $this->records() );
		$this->assertSame( 'sk-test-entered-in-older-version', $this->plaintext( 'ai_chatgpt_key' ) );

		$resolved = Credentials::resolve( 'chatgpt' );
		$this->assertSame( 'sk-test-entered-in-older-version', $resolved['key'] );
		$this->assertSame( Credentials::STATUS_UNREADABLE, $resolved['status'] );
	}

	/**
	 * A different salt yields a different fingerprint.
	 */
	public function test_fingerprint_changes_with_salt(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );
		$kid = $this->records()['chatgpt']['kid'];

		$this->change_salt( 'another-salt' );
		Credentials::set( 'claude', self::CLAUDE_KEY );

		$this->assertNotSame( $kid, $this->records()['claude']['kid'] );
	}

	// -------------------------------------------------------------------------
	// Migration
	// -------------------------------------------------------------------------

	/**
	 * Plaintext keys for all providers migrate, then the plaintext is removed.
	 */
	public function test_migrates_all_plaintext_keys(): void {
		$this->legacy_keys(
			[
				'ai_chatgpt_key' => self::OPENAI_KEY,
				'ai_gemini_key'  => self::GEMINI_KEY,
				'ai_claude_key'  => self::CLAUDE_KEY,
			]
		);

		Credentials::maybe_migrate();

		$this->assertSame( self::OPENAI_KEY, Credentials::resolve( 'chatgpt' )['key'] );
		$this->assertSame( self::GEMINI_KEY, Credentials::resolve( 'gemini' )['key'] );
		$this->assertSame( self::CLAUDE_KEY, Credentials::resolve( 'claude' )['key'] );
		$this->assertNull( $this->plaintext( 'ai_chatgpt_key' ) );
		$this->assertNull( $this->plaintext( 'ai_gemini_key' ) );
		$this->assertNull( $this->plaintext( 'ai_claude_key' ) );
		$this->assertSame( 'chatgpt', $GLOBALS['tsmlt_test_options']['tsmlt_settings']['ai_provider'], 'Other settings are untouched.' );
		$this->assertSame( '1', get_option( Credentials::VERSION_OPTION ) );

		// The database now holds only ciphertext, never a plaintext key.
		$dump = serialize( $GLOBALS['tsmlt_test_options'] );
		foreach ( [ self::OPENAI_KEY, self::GEMINI_KEY, self::CLAUDE_KEY ] as $key ) {
			$this->assertStringNotContainsString( $key, $dump );
		}
	}

	/**
	 * A failed credential write keeps the plaintext, AI keeps working, and a
	 * retry is scheduled.
	 */
	public function test_failed_write_preserves_plaintext_and_ai_keeps_working(): void {
		$this->legacy_keys( [ 'ai_chatgpt_key' => self::OPENAI_KEY ] );
		$GLOBALS['tsmlt_test_fail_writes'][ Credentials::OPTION ] = true;

		Credentials::maybe_migrate();

		$this->assertSame( self::OPENAI_KEY, $this->plaintext( 'ai_chatgpt_key' ) );
		$this->assertSame( [], $this->records() );
		$this->assertNotFalse( get_transient( Credentials::RETRY_TRANSIENT ) );
		$this->assertFalse( get_option( Credentials::VERSION_OPTION ) );

		$this->dispatch_openai();
		$this->assertSame( 'Bearer ' . self::OPENAI_KEY, $this->last_auth_header() );
	}

	/**
	 * A write that reads back wrong is rolled back and the plaintext kept.
	 */
	public function test_unverifiable_write_is_rolled_back(): void {
		$this->legacy_keys( [ 'ai_gemini_key' => self::GEMINI_KEY ] );
		$GLOBALS['tsmlt_test_corrupt_writes'][ Credentials::OPTION ] = static function ( $records ) {
			if ( isset( $records['gemini']['ct'] ) ) {
				$records['gemini']['ct'] = base64_encode( 'corrupted-by-the-database' );
			}
			return $records;
		};

		$this->assertFalse( Credentials::migrate() );
		$this->assertArrayNotHasKey( 'gemini', $this->records() );
		$this->assertSame( self::GEMINI_KEY, $this->plaintext( 'ai_gemini_key' ) );
	}

	/**
	 * One provider failing does not block the others.
	 */
	public function test_partial_migration(): void {
		$this->legacy_keys(
			[
				'ai_chatgpt_key' => self::OPENAI_KEY,
				'ai_gemini_key'  => self::GEMINI_KEY,
			]
		);
		$GLOBALS['tsmlt_test_corrupt_writes'][ Credentials::OPTION ] = static function ( $records ) {
			if ( isset( $records['gemini']['ct'] ) && empty( $records['gemini']['corrupted'] ) ) {
				$records['gemini']['ct']        = base64_encode( 'bad' );
				$records['gemini']['corrupted'] = true;
			}
			return $records;
		};

		$this->assertFalse( Credentials::migrate() );
		$this->assertNull( $this->plaintext( 'ai_chatgpt_key' ) );
		$this->assertSame( self::OPENAI_KEY, Credentials::resolve( 'chatgpt' )['key'] );
		$this->assertSame( self::GEMINI_KEY, $this->plaintext( 'ai_gemini_key' ) );
		$this->assertSame( self::GEMINI_KEY, Credentials::resolve( 'gemini' )['key'] );
	}

	/**
	 * After a failure, retries wait for the retry window.
	 */
	public function test_retry_is_throttled(): void {
		$this->legacy_keys( [ 'ai_claude_key' => self::CLAUDE_KEY ] );
		$GLOBALS['tsmlt_test_fail_writes'][ Credentials::OPTION ] = true;
		Credentials::maybe_migrate();

		$GLOBALS['tsmlt_test_fail_writes'] = [];
		Credentials::maybe_migrate();
		$this->assertSame( self::CLAUDE_KEY, $this->plaintext( 'ai_claude_key' ), 'Still waiting for the retry window.' );

		unset( $GLOBALS['tsmlt_test_transients'][ Credentials::RETRY_TRANSIENT ] );
		Credentials::maybe_migrate();
		$this->assertNull( $this->plaintext( 'ai_claude_key' ) );
		$this->assertSame( self::CLAUDE_KEY, Credentials::resolve( 'claude' )['key'] );
	}

	/**
	 * Running the migration again changes nothing.
	 */
	public function test_migration_is_idempotent(): void {
		$this->legacy_keys( [ 'ai_chatgpt_key' => self::OPENAI_KEY ] );
		Credentials::maybe_migrate();
		$records = $this->records();

		$GLOBALS['tsmlt_test_writes'] = [];
		Credentials::maybe_migrate();

		$this->assertSame( $records, $this->records() );
		$this->assertSame( [], $GLOBALS['tsmlt_test_writes'] );
	}

	/**
	 * A key entered in an older plugin version (after a downgrade) wins over
	 * the older encrypted copy and is migrated.
	 */
	public function test_downgrade_then_upgrade_keeps_the_newer_key(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );
		$this->legacy_keys( [ 'ai_chatgpt_key' => 'sk-test-newer-key-from-old-version' ] );

		$this->assertSame( 'sk-test-newer-key-from-old-version', Credentials::resolve( 'chatgpt' )['key'] );

		Credentials::maybe_migrate();
		$this->assertNull( $this->plaintext( 'ai_chatgpt_key' ) );
		$this->assertSame( 'sk-test-newer-key-from-old-version', Credentials::resolve( 'chatgpt' )['key'] );
	}

	// -------------------------------------------------------------------------
	// Settings writes cannot restore plaintext or touch credentials
	// -------------------------------------------------------------------------

	/**
	 * A settings save that read the options before the migration cannot
	 * write the plaintext key back.
	 */
	public function test_stale_save_after_migration_is_stripped(): void {
		$this->legacy_keys( [ 'ai_chatgpt_key' => self::OPENAI_KEY ] );
		$stale = get_option( 'tsmlt_settings' );

		Credentials::maybe_migrate();
		update_option( 'tsmlt_settings', $stale );

		$this->assertNull( $this->plaintext( 'ai_chatgpt_key' ) );
		$this->assertSame( self::OPENAI_KEY, Credentials::resolve( 'chatgpt' )['key'] );
	}

	/**
	 * A stale save after the admin replaced the key cannot revert it.
	 */
	public function test_stale_save_after_replace_cannot_revert_the_key(): void {
		$this->legacy_keys( [ 'ai_gemini_key' => self::GEMINI_KEY ] );
		$stale = get_option( 'tsmlt_settings' );

		Credentials::set( 'gemini', 'AIza-test-replacement-key-000000' );
		update_option( 'tsmlt_settings', $stale );

		// The write itself must drop the stale value, not just a later migration.
		$this->assertNull( $this->plaintext( 'ai_gemini_key' ) );

		Credentials::maybe_migrate();

		$this->assertNull( $this->plaintext( 'ai_gemini_key' ) );
		$this->assertSame( 'AIza-test-replacement-key-000000', Credentials::resolve( 'gemini' )['key'] );
	}

	/**
	 * A stale save after the admin removed the key cannot bring it back.
	 */
	public function test_stale_save_after_remove_cannot_resurrect_the_key(): void {
		$this->legacy_keys( [ 'ai_claude_key' => self::CLAUDE_KEY ] );
		$stale = get_option( 'tsmlt_settings' );

		Credentials::remove( 'claude' );
		update_option( 'tsmlt_settings', $stale );

		// The write itself must drop the stale value, not just a later migration.
		$this->assertNull( $this->plaintext( 'ai_claude_key' ) );

		Credentials::maybe_migrate();

		$this->assertNull( $this->plaintext( 'ai_claude_key' ) );
		$this->assertSame( Credentials::STATUS_EMPTY, Credentials::resolve( 'claude' )['status'] );
	}

	/**
	 * Unrelated settings saves (compression, conversion, general) never write
	 * the credentials option.
	 */
	public function test_unrelated_settings_saves_leave_credentials_alone(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );
		$records = $this->records();
		$GLOBALS['tsmlt_test_writes'] = [];

		$settings                     = get_option( 'tsmlt_settings', [] );
		$settings['compression_mode'] = 'aggressive';
		update_option( 'tsmlt_settings', $settings );

		$this->assertNotContains( Credentials::OPTION, $GLOBALS['tsmlt_test_writes'] );
		$this->assertSame( $records, $this->records() );
		$this->assertSame( self::OPENAI_KEY, Credentials::resolve( 'chatgpt' )['key'] );
	}

	// -------------------------------------------------------------------------
	// Admin endpoints
	// -------------------------------------------------------------------------

	/**
	 * The options response never carries keys or ciphertext, before or after
	 * migration, and reports a status per provider.
	 */
	public function test_get_options_never_exposes_keys(): void {
		$this->legacy_keys( [ 'ai_gemini_key' => self::GEMINI_KEY ] );
		Credentials::set( 'chatgpt', self::OPENAI_KEY );

		$json    = Api::instance()->get_options();
		$options = json_decode( $json, true );

		$this->assert_no_secrets( $json );
		$this->assertStringNotContainsString( 'tsmlt_ai_credentials', $json );
		$this->assertSame( '', $options['ai_chatgpt_key'] );
		$this->assertSame( '', $options['ai_gemini_key'] );
		$this->assertFalse( $options['ai_claude_key_remove'] );
		$this->assertSame(
			[
				'chatgpt' => 'saved',
				'gemini'  => 'saved',
				'claude'  => 'empty',
			],
			$options['ai_key_status']
		);
	}

	/**
	 * Server-side callers of Fns::get_options() never receive keys.
	 */
	public function test_fns_get_options_omits_keys(): void {
		$this->legacy_keys( [ 'ai_claude_key' => self::CLAUDE_KEY ] );
		$this->assertArrayNotHasKey( 'ai_claude_key', Fns::get_options() );
	}

	/**
	 * Saving with an empty key field keeps the saved key.
	 */
	public function test_save_with_empty_field_keeps_key(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );

		$result = Api::instance()->update_option( $this->form( [ 'ai_chatgpt_key' => '' ] ) );

		$this->assertTrue( $result['updated'] );
		$this->assertSame( self::OPENAI_KEY, Credentials::resolve( 'chatgpt' )['key'] );
	}

	/**
	 * Saving a new key replaces the saved one, encrypted.
	 */
	public function test_save_with_new_key_replaces(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );

		Api::instance()->update_option( $this->form( [ 'ai_chatgpt_key' => 'sk-test-brand-new-key-00000000' ] ) );

		$this->assertSame( 'sk-test-brand-new-key-00000000', Credentials::resolve( 'chatgpt' )['key'] );
		$this->assertNull( $this->plaintext( 'ai_chatgpt_key' ) );
	}

	/**
	 * Saving a new key over a legacy plaintext key removes the plaintext at once.
	 */
	public function test_save_over_legacy_plaintext_removes_it(): void {
		$this->legacy_keys( [ 'ai_chatgpt_key' => self::OPENAI_KEY ] );

		Api::instance()->update_option( $this->form( [ 'ai_chatgpt_key' => 'sk-test-brand-new-key-00000000' ] ) );

		$this->assertNull( $this->plaintext( 'ai_chatgpt_key' ) );
		$this->assertSame( 'sk-test-brand-new-key-00000000', Credentials::resolve( 'chatgpt' )['key'] );
	}

	/**
	 * Removing a key that only exists as legacy plaintext removes it.
	 */
	public function test_remove_legacy_plaintext_key(): void {
		$this->legacy_keys( [ 'ai_gemini_key' => self::GEMINI_KEY ] );

		Api::instance()->update_option( $this->form( [ 'ai_gemini_key_remove' => true ] ) );

		$this->assertNull( $this->plaintext( 'ai_gemini_key' ) );
		$this->assertSame( Credentials::STATUS_EMPTY, Credentials::resolve( 'gemini' )['status'] );
	}

	/**
	 * The explicit remove flag deletes the key.
	 */
	public function test_save_with_remove_flag_deletes(): void {
		Credentials::set( 'claude', self::CLAUDE_KEY );

		Api::instance()->update_option( $this->form( [ 'ai_claude_key_remove' => true ] ) );

		$this->assertSame( Credentials::STATUS_EMPTY, Credentials::resolve( 'claude' )['status'] );
		$this->assertArrayNotHasKey( 'claude', $this->records() );
	}

	/**
	 * A key that cannot be stored securely is reported, not silently lost.
	 */
	public function test_save_failure_is_reported(): void {
		$GLOBALS['tsmlt_test_fail_writes'][ Credentials::OPTION ] = true;

		$result = Api::instance()->update_option( $this->form( [ 'ai_gemini_key' => self::GEMINI_KEY ] ) );

		$this->assertFalse( $result['updated'] );
		$this->assertStringContainsString( 'Gemini', $result['message'] );
		$this->assert_no_secrets( $result['message'] );
	}

	/**
	 * A minimal settings form submission.
	 *
	 * @param array $overrides Fields to set.
	 *
	 * @return array
	 */
	private function form( array $overrides ): array {
		return array_merge(
			[
				'ai_provider'      => 'chatgpt',
				'ai_chatgpt_model' => 'gpt-6-luna',
			],
			$overrides
		);
	}

	// -------------------------------------------------------------------------
	// Encryption unavailable
	// -------------------------------------------------------------------------

	/**
	 * Simulate a server where no sodium implementation can be loaded, by
	 * caching "unavailable" for the current salt.
	 */
	private function make_encryption_unavailable(): void {
		$keys = new ReflectionProperty( Credentials::class, 'keys' );
		if ( PHP_VERSION_ID < 80100 ) {
			$keys->setAccessible( true );
		}
		$keys->setValue( null, [ hash( 'sha256', $GLOBALS['tsmlt_test_salt'] ) => false ] );
	}

	/**
	 * Without encryption a new key is rejected, never stored in plaintext, and
	 * the existing key is left exactly as it was.
	 */
	public function test_unavailable_encryption_rejects_new_keys(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );
		$records = $this->records();
		$this->make_encryption_unavailable();

		$this->assertFalse( Credentials::is_available() );
		$this->assertFalse( Credentials::set( 'chatgpt', 'sk-test-should-not-be-stored-000' ) );

		$this->assertSame( $records, $this->records() );
		$this->assertNull( $this->plaintext( 'ai_chatgpt_key' ) );
		$this->assertStringNotContainsString( 'sk-test-should-not-be-stored-000', serialize( $GLOBALS['tsmlt_test_options'] ) );
	}

	/**
	 * The settings save reports why the key was rejected, still saves the
	 * other settings, and keeps a legacy plaintext key working.
	 */
	public function test_unavailable_encryption_save_reports_error_and_keeps_other_settings(): void {
		$this->legacy_keys( [ 'ai_gemini_key' => self::GEMINI_KEY ] );
		$this->make_encryption_unavailable();

		$result = Api::instance()->update_option(
			$this->form(
				[
					'ai_gemini_key'    => 'AIza-test-should-not-be-stored-00',
					'ai_chatgpt_model' => 'gpt-4o',
				]
			)
		);

		$this->assertFalse( $result['updated'] );
		$this->assertStringContainsString( 'cannot encrypt', $result['message'] );
		$this->assertStringContainsString( 'Gemini', $result['message'] );
		$this->assertSame( 'gpt-4o', get_option( 'tsmlt_settings' )['ai_chatgpt_model'] );
		$this->assertSame( self::GEMINI_KEY, $this->plaintext( 'ai_gemini_key' ) );
		$this->assertSame( self::GEMINI_KEY, Credentials::resolve( 'gemini' )['key'] );
		$this->assertStringNotContainsString( 'AIza-test-should-not-be-stored-00', serialize( $GLOBALS['tsmlt_test_options'] ) );

		// The migration leaves the legacy key alone rather than lose it.
		$this->assertFalse( Credentials::migrate() );
		$this->assertSame( self::GEMINI_KEY, $this->plaintext( 'ai_gemini_key' ) );
	}

	// -------------------------------------------------------------------------
	// Notice, Site Health, AI regression, logging, uninstall
	// -------------------------------------------------------------------------

	/**
	 * The recovery notice names the provider, explains recovery, and leaks
	 * nothing; it is silent when every key is readable.
	 */
	public function test_recovery_notice(): void {
		Credentials::set( 'gemini', self::GEMINI_KEY );

		ob_start();
		CredentialHooks::instance()->render_notice();
		$this->assertSame( '', trim( (string) ob_get_clean() ) );

		$this->change_salt( 'migrated-host-salt' );
		ob_start();
		CredentialHooks::instance()->render_notice();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Gemini', $html );
		$this->assertStringContainsString( 'security keys', $html );
		$this->assertStringContainsString( 're-enter the key', $html );
		$this->assert_no_secrets( $html );
	}

	/**
	 * AI generation uses the decrypted key.
	 */
	public function test_ai_request_uses_decrypted_key(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );

		$this->assertSame( 'ok', $this->dispatch_openai() );
		$this->assertSame( 'Bearer ' . self::OPENAI_KEY, $this->last_auth_header() );
	}

	/**
	 * Debug logging (WP_DEBUG on) never contains keys or ciphertext.
	 */
	public function test_debug_log_contains_no_secrets(): void {
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}
		$this->legacy_keys( [ 'ai_gemini_key' => self::GEMINI_KEY ] );
		$GLOBALS['tsmlt_test_corrupt_writes'][ Credentials::OPTION ] = static function ( $records ) {
			$records['gemini']['ct'] = base64_encode( 'x' );
			return $records;
		};

		Credentials::migrate();

		$log = (string) file_get_contents( $this->log_file );
		$this->assertStringContainsString( 'gemini', $log, 'The failure is logged.' );
		$this->assert_no_secrets( $log );
	}

	/**
	 * Uninstall removes the credential options when data deletion is enabled.
	 */
	public function test_uninstall_removes_credentials(): void {
		Credentials::set( 'chatgpt', self::OPENAI_KEY );
		$GLOBALS['tsmlt_test_options']['tsmlt_settings']['delete_data_on_uninstall'] = 1;
		update_option( Credentials::VERSION_OPTION, '1' );

		$GLOBALS['wpdb'] = new class() {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public $prefix = 'wp_';

			/**
			 * Options table.
			 *
			 * @var string
			 */
			public $options = 'wp_options';

			/**
			 * Ignore queries.
			 *
			 * @return int
			 */
			public function query() {
				return 0;
			}
		};
		foreach ( [ 'wp_clear_scheduled_hook', 'wp_unschedule_hook', 'delete_post_meta_by_key' ] as $function ) {
			if ( ! function_exists( $function ) ) {
				eval( 'function ' . $function . '() { return true; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test-only stubs.
			}
		}

		\TinySolutions\mlt\Controllers\Installation::uninstall();

		$this->assertArrayNotHasKey( Credentials::OPTION, $GLOBALS['tsmlt_test_options'] );
		$this->assertArrayNotHasKey( Credentials::VERSION_OPTION, $GLOBALS['tsmlt_test_options'] );
	}
}
