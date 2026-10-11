<?php
/**
 * Encrypted storage for AI provider API keys.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Controllers\AI;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Stores AI provider API keys encrypted at rest, separate from `tsmlt_settings`.
 *
 * Keys are encrypted with XChaCha20-Poly1305 under a key derived from the
 * site's `LOGGED_IN` salts, so no configuration is needed and a database-only
 * leak does not reveal them. Each record stores a fingerprint of the key that
 * encrypted it, used only to word recovery messages: every unreadable record is
 * handled identically (never sent, never deleted automatically).
 *
 * Plaintext keys left in `tsmlt_settings` by earlier versions keep working
 * until they are migrated, and are removed only after the encrypted copy has
 * been written and read back successfully.
 */
final class Credentials {

	/**
	 * Option holding the encrypted records (autoload off).
	 */
	const OPTION = 'tsmlt_ai_credentials';

	/**
	 * Option set once every plaintext key has been migrated.
	 */
	const VERSION_OPTION = 'tsmlt_credentials_version';

	/**
	 * Transient that delays a retry after a failed migration.
	 */
	const RETRY_TRANSIENT = 'tsmlt_credentials_migration_retry';

	/**
	 * Record format version; part of the authenticated data.
	 */
	const FORMAT_VERSION = 1;

	/**
	 * Provider => legacy plaintext field in `tsmlt_settings`.
	 */
	const PROVIDERS = [
		'chatgpt' => 'ai_chatgpt_key',
		'gemini'  => 'ai_gemini_key',
		'claude'  => 'ai_claude_key',
	];

	/**
	 * Key statuses reported to the admin UI.
	 */
	const STATUS_EMPTY      = 'empty';
	const STATUS_SAVED      = 'saved';
	const STATUS_UNREADABLE = 'unreadable';

	/**
	 * Why a record could not be decrypted.
	 */
	const REASON_KEY_CHANGED = 'key_changed';
	const REASON_DAMAGED     = 'damaged';
	const REASON_UNAVAILABLE = 'unavailable';

	/**
	 * KDF context (exactly 8 bytes) and subkey IDs.
	 */
	const KDF_CONTEXT     = 'tsmltcrd';
	const SUBKEY_ENCRYPT  = 1;
	const SUBKEY_KEY_ID   = 2;
	const SUBKEY_SUPERSED = 3;

	/**
	 * Derived keys for this request, keyed by salt hash; false when unavailable.
	 *
	 * @var array<string, array{enc: string, kid: string, mac: string}|false>
	 */
	private static $keys = [];

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Resolve the API key to use for a provider and its admin-facing status.
	 *
	 * A plaintext key that differs from the encrypted copy was written by an
	 * older plugin version (after a downgrade), so it is treated as newer —
	 * unless the encrypted record says it superseded exactly that value.
	 *
	 * @param string $provider Provider key (chatgpt, gemini, claude).
	 *
	 * @return array{key: string, status: string, reason: string}
	 */
	public static function resolve( string $provider ): array {
		$plain  = self::plaintext( $provider );
		$record = self::records()[ $provider ] ?? null;

		if ( ! is_array( $record ) ) {
			return self::result( $plain, '' !== $plain ? self::STATUS_SAVED : self::STATUS_EMPTY );
		}

		if ( ! empty( $record['removed'] ) ) {
			$stale = '' === $plain || self::supersedes( $record, $provider, $plain );
			return $stale ? self::result( '', self::STATUS_EMPTY ) : self::result( $plain, self::STATUS_SAVED );
		}

		$reason    = '';
		$decrypted = self::decrypt_record( $provider, $record, $reason );

		if ( null === $decrypted ) {
			// Never discard the unreadable record. A plaintext copy, if any, is a
			// genuine key the admin entered and still works.
			return self::result( $plain, self::STATUS_UNREADABLE, $reason );
		}

		if ( '' === $plain || hash_equals( $decrypted, $plain ) || self::supersedes( $record, $provider, $plain ) ) {
			return self::result( $decrypted, self::STATUS_SAVED );
		}

		return self::result( $plain, self::STATUS_SAVED );
	}

	/**
	 * Status of every provider's key, for the admin UI.
	 *
	 * @return array<string, string> Provider => status.
	 */
	public static function statuses(): array {
		$statuses = [];
		foreach ( array_keys( self::PROVIDERS ) as $provider ) {
			$statuses[ $provider ] = self::resolve( $provider )['status'];
		}

		return $statuses;
	}

	/**
	 * Providers whose stored key cannot be decrypted, with the reason.
	 *
	 * @return array<string, string> Provider => reason.
	 */
	public static function unreadable(): array {
		$unreadable = [];
		foreach ( array_keys( self::PROVIDERS ) as $provider ) {
			$resolved = self::resolve( $provider );
			if ( self::STATUS_UNREADABLE === $resolved['status'] ) {
				$unreadable[ $provider ] = $resolved['reason'];
			}
		}

		return $unreadable;
	}

	/**
	 * Save a new API key for a provider, replacing any existing one.
	 *
	 * @param string $provider Provider key.
	 * @param string $key      New API key.
	 *
	 * @return bool True when the key was stored and verified.
	 */
	public static function set( string $provider, string $key ): bool {
		$key = trim( $key );
		if ( ! isset( self::PROVIDERS[ $provider ] ) || '' === $key ) {
			return false;
		}

		$plain = self::plaintext( $provider );

		if ( ! self::is_available() ) {
			// Never fall back to storing the key unencrypted. Existing keys and
			// records are left exactly as they are.
			return false;
		}

		// Record which plaintext this key replaces, so a stale copy written
		// back by a concurrent settings save is recognised and dropped.
		$supersedes = ( '' !== $plain && ! hash_equals( $plain, $key ) ) ? self::mac( $provider, $plain ) : '';

		if ( ! self::store_verified( $provider, self::encrypt( $provider, $key, $supersedes ) ) ) {
			return false;
		}

		return '' === $plain || self::remove_plaintext( $provider );
	}

	/**
	 * Remove a provider's API key at the administrator's request.
	 *
	 * This is the only path, besides set(), that discards an unreadable record.
	 *
	 * @param string $provider Provider key.
	 *
	 * @return bool True when no key remains.
	 */
	public static function remove( string $provider ): bool {
		if ( ! isset( self::PROVIDERS[ $provider ] ) ) {
			return false;
		}

		$plain   = self::plaintext( $provider );
		$records = self::records();

		if ( '' !== $plain && false !== self::keys() ) {
			// Leave a tombstone naming the removed plaintext, so a stale copy
			// restored by a concurrent save cannot bring the key back.
			$records[ $provider ] = self::tombstone( $provider, $plain );
		} else {
			unset( $records[ $provider ] );
		}

		update_option( self::OPTION, $records, false );

		return '' === $plain || self::remove_plaintext( $provider );
	}

	/**
	 * Migrate every legacy plaintext key that is safe to migrate.
	 *
	 * @return bool True when no plaintext key is left to migrate.
	 */
	public static function migrate(): bool {
		$complete = true;
		foreach ( array_keys( self::PROVIDERS ) as $provider ) {
			if ( ! self::migrate_provider( $provider ) ) {
				$complete = false;
			}
		}

		return $complete;
	}

	/**
	 * Run the migration when plaintext keys remain, at most once an hour after
	 * a failure.
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		if ( ! self::has_plaintext() ) {
			if ( (string) self::FORMAT_VERSION !== (string) get_option( self::VERSION_OPTION ) ) {
				update_option( self::VERSION_OPTION, (string) self::FORMAT_VERSION, false );
			}
			return;
		}

		if ( get_transient( self::RETRY_TRANSIENT ) ) {
			return;
		}

		if ( self::migrate() ) {
			update_option( self::VERSION_OPTION, (string) self::FORMAT_VERSION, false );
			return;
		}

		set_transient( self::RETRY_TRANSIENT, 1, HOUR_IN_SECONDS );
	}

	/**
	 * Drop stale plaintext keys from a `tsmlt_settings` write.
	 *
	 * A save that read the settings before a migration (or before the admin
	 * replaced or removed a key) would otherwise write the old plaintext back.
	 * A plaintext value is dropped only when the encrypted store already holds
	 * it or recorded superseding it; anything else is left for the migration.
	 *
	 * @param mixed $value Settings about to be saved.
	 *
	 * @return mixed
	 */
	public static function strip_stale_plaintext( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$records = self::records();
		foreach ( self::PROVIDERS as $provider => $field ) {
			$plain  = trim( (string) ( $value[ $field ] ?? '' ) );
			$record = $records[ $provider ] ?? null;
			if ( '' === $plain || ! is_array( $record ) ) {
				continue;
			}

			if ( self::supersedes( $record, $provider, $plain ) ) {
				unset( $value[ $field ] );
				continue;
			}

			if ( empty( $record['removed'] ) ) {
				$reason    = '';
				$decrypted = self::decrypt_record( $provider, $record, $reason );
				if ( null !== $decrypted && hash_equals( $decrypted, $plain ) ) {
					unset( $value[ $field ] );
				}
			}
		}

		return $value;
	}

	/**
	 * Whether keys can be encrypted on this server.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return false !== self::keys();
	}

	/**
	 * Whether the site's LOGGED_IN salts live in wp-config.php rather than the
	 * database. When WordPress falls back to database-stored salts, encryption
	 * cannot protect against a database-only leak.
	 *
	 * @return bool
	 */
	public static function salts_in_config(): bool {
		foreach ( [ 'LOGGED_IN_KEY', 'LOGGED_IN_SALT' ] as $constant ) {
			if ( ! defined( $constant ) ) {
				return false;
			}
			$value = (string) constant( $constant );
			if ( '' === $value || 'put your unique phrase here' === $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Forget derived keys cached for this request (used after salts change).
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$keys = [];
	}

	// -------------------------------------------------------------------------
	// Migration
	// -------------------------------------------------------------------------

	/**
	 * Migrate one provider's plaintext key, preserving it on any failure.
	 *
	 * @param string $provider Provider key.
	 *
	 * @return bool True when nothing is left to migrate for this provider.
	 */
	private static function migrate_provider( string $provider ): bool {
		$plain = self::plaintext( $provider );
		if ( '' === $plain ) {
			return true;
		}

		if ( false === self::keys() ) {
			return false;
		}

		$record = self::records()[ $provider ] ?? null;

		if ( is_array( $record ) ) {
			if ( self::supersedes( $record, $provider, $plain ) ) {
				return self::remove_plaintext( $provider );
			}

			if ( empty( $record['removed'] ) ) {
				$reason    = '';
				$decrypted = self::decrypt_record( $provider, $record, $reason );

				if ( null === $decrypted ) {
					// Never overwrite an unreadable record automatically; the
					// plaintext keeps working and the admin is notified.
					return false;
				}

				if ( hash_equals( $decrypted, $plain ) ) {
					return self::remove_plaintext( $provider );
				}
			}
		}

		// No record, a tombstone, or an older key: the plaintext is current.
		if ( ! self::store_verified( $provider, self::encrypt( $provider, $plain, '' ) ) ) {
			return false;
		}

		return self::remove_plaintext( $provider );
	}

	/**
	 * Write one provider's record and confirm it decrypts from the database.
	 *
	 * On failure the previous records are restored, so an unverified write
	 * never replaces what was there.
	 *
	 * @param string $provider Provider key.
	 * @param array  $record   Encrypted record.
	 *
	 * @return bool
	 */
	private static function store_verified( string $provider, array $record ): bool {
		$expected = self::decrypt_with_current_key( $provider, $record );
		if ( null === $expected ) {
			return false;
		}

		$previous             = self::records();
		$records              = $previous;
		$records[ $provider ] = $record;

		update_option( self::OPTION, $records, false );
		wp_cache_delete( self::OPTION, 'options' );

		$stored = self::records()[ $provider ] ?? null;
		$reason = '';
		$actual = is_array( $stored ) ? self::decrypt_record( $provider, $stored, $reason ) : null;

		if ( null !== $actual && hash_equals( $expected, $actual ) ) {
			return true;
		}

		update_option( self::OPTION, $previous, false );
		wp_cache_delete( self::OPTION, 'options' );
		self::log( 'Could not verify the encrypted ' . $provider . ' API key after saving it.' );

		return false;
	}

	/**
	 * Remove a provider's plaintext field from `tsmlt_settings`.
	 *
	 * @param string $provider Provider key.
	 *
	 * @return bool True when the field is gone.
	 */
	private static function remove_plaintext( string $provider ): bool {
		$field    = self::PROVIDERS[ $provider ];
		$settings = get_option( 'tsmlt_settings', [] );

		if ( ! is_array( $settings ) || ! array_key_exists( $field, $settings ) ) {
			return true;
		}

		unset( $settings[ $field ] );
		update_option( 'tsmlt_settings', $settings );

		return '' === self::plaintext( $provider );
	}

	// -------------------------------------------------------------------------
	// Records
	// -------------------------------------------------------------------------

	/**
	 * All stored records.
	 *
	 * @return array<string, array>
	 */
	private static function records(): array {
		$records = get_option( self::OPTION, [] );

		return is_array( $records ) ? $records : [];
	}

	/**
	 * A provider's legacy plaintext key, or an empty string.
	 *
	 * @param string $provider Provider key.
	 *
	 * @return string
	 */
	private static function plaintext( string $provider ): string {
		$settings = get_option( 'tsmlt_settings', [] );

		return is_array( $settings ) ? trim( (string) ( $settings[ self::PROVIDERS[ $provider ] ] ?? '' ) ) : '';
	}

	/**
	 * Whether any provider still has a plaintext key in `tsmlt_settings`.
	 *
	 * @return bool
	 */
	private static function has_plaintext(): bool {
		foreach ( array_keys( self::PROVIDERS ) as $provider ) {
			if ( '' !== self::plaintext( $provider ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a resolve() result.
	 *
	 * @param string $key    API key to use ('' for none).
	 * @param string $status Admin-facing status.
	 * @param string $reason Unreadable reason, if any.
	 *
	 * @return array{key: string, status: string, reason: string}
	 */
	private static function result( string $key, string $status, string $reason = '' ): array {
		return [
			'key'    => $key,
			'status' => $status,
			'reason' => $reason,
		];
	}

	// -------------------------------------------------------------------------
	// Cryptography
	// -------------------------------------------------------------------------

	/**
	 * Encrypt a key into a storable record.
	 *
	 * @param string $provider   Provider key (bound into the authenticated data).
	 * @param string $key        API key.
	 * @param string $supersedes MAC of the plaintext this key replaces, or ''.
	 *
	 * @return array
	 */
	private static function encrypt( string $provider, string $key, string $supersedes ): array {
		$keys  = self::keys();
		$nonce = random_bytes( 24 );

		return [
			'v'          => self::FORMAT_VERSION,
			'kid'        => $keys['kid'],
			'nonce'      => base64_encode( $nonce ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary-safe storage of ciphertext.
			'ct'         => base64_encode( self::aead_encrypt( $key, self::associated_data( $provider ), $nonce, $keys['enc'] ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary-safe storage of ciphertext.
			'supersedes' => $supersedes,
			'updated'    => time(),
		];
	}

	/**
	 * Build a tombstone recording that a plaintext key was removed.
	 *
	 * @param string $provider Provider key.
	 * @param string $plain    Removed plaintext key.
	 *
	 * @return array
	 */
	private static function tombstone( string $provider, string $plain ): array {
		return [
			'v'          => self::FORMAT_VERSION,
			'removed'    => true,
			'kid'        => self::keys()['kid'],
			'supersedes' => self::mac( $provider, $plain ),
			'updated'    => time(),
		];
	}

	/**
	 * Decrypt a record, reporting why it failed.
	 *
	 * The fingerprint only selects the wording of the reason. A mismatch
	 * means the salts changed or the fingerprint itself was altered; a match
	 * with a failed decryption means the record was altered (a 64-bit
	 * fingerprint collision between different salts is negligible). Callers
	 * treat every failure the same way.
	 *
	 * @param string $provider Provider key.
	 * @param array  $record   Stored record.
	 * @param string $reason   Set to a REASON_* constant on failure.
	 *
	 * @return string|null Plaintext key, or null when unreadable.
	 */
	private static function decrypt_record( string $provider, array $record, string &$reason ) {
		$keys = self::keys();
		if ( false === $keys ) {
			$reason = self::REASON_UNAVAILABLE;
			return null;
		}

		if ( ! hash_equals( $keys['kid'], (string) ( $record['kid'] ?? '' ) ) ) {
			$reason = self::REASON_KEY_CHANGED;
			return null;
		}

		$plain = self::decrypt_with_current_key( $provider, $record );
		if ( null === $plain ) {
			$reason = self::REASON_DAMAGED;
		}

		return $plain;
	}

	/**
	 * Decrypt a record with the current key, ignoring the fingerprint.
	 *
	 * @param string $provider Provider key.
	 * @param array  $record   Stored record.
	 *
	 * @return string|null
	 */
	private static function decrypt_with_current_key( string $provider, array $record ) {
		$keys = self::keys();
		if ( false === $keys || self::FORMAT_VERSION !== (int) ( $record['v'] ?? 0 ) ) {
			return null;
		}

		$nonce      = base64_decode( (string) ( $record['nonce'] ?? '' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary-safe storage of ciphertext.
		$ciphertext = base64_decode( (string) ( $record['ct'] ?? '' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary-safe storage of ciphertext.

		if ( false === $nonce || false === $ciphertext || 24 !== strlen( $nonce ) ) {
			return null;
		}

		$plain = self::aead_decrypt( $ciphertext, self::associated_data( $provider ), $nonce, $keys['enc'] );

		return ( is_string( $plain ) && '' !== $plain ) ? $plain : null;
	}

	/**
	 * Whether a record says it replaced exactly this plaintext value.
	 *
	 * @param array  $record   Stored record or tombstone.
	 * @param string $provider Provider key.
	 * @param string $plain    Plaintext candidate.
	 *
	 * @return bool
	 */
	private static function supersedes( array $record, string $provider, string $plain ): bool {
		$supersedes = (string) ( $record['supersedes'] ?? '' );
		$keys       = self::keys();

		if ( '' === $supersedes || false === $keys || ! hash_equals( $keys['kid'], (string) ( $record['kid'] ?? '' ) ) ) {
			return false;
		}

		return hash_equals( $supersedes, self::mac( $provider, $plain ) );
	}

	/**
	 * Keyed hash of a plaintext key; reveals nothing without the salts.
	 *
	 * @param string $provider Provider key.
	 * @param string $plain    Plaintext key.
	 *
	 * @return string Hex digest.
	 */
	private static function mac( string $provider, string $plain ): string {
		return bin2hex( self::generichash( 'tsmlt-supersedes|v1|' . $provider . '|' . $plain, self::keys()['mac'], 16 ) );
	}

	/**
	 * Authenticated data binding a ciphertext to its provider and format.
	 *
	 * @param string $provider Provider key.
	 *
	 * @return string
	 */
	private static function associated_data( string $provider ): string {
		return 'tsmlt-ai-credential|v' . self::FORMAT_VERSION . '|' . $provider;
	}

	/**
	 * Derive this site's keys from its LOGGED_IN salts.
	 *
	 * BLAKE2b condenses the salt into a 32-byte master key, from which
	 * independent subkeys are derived for encryption, the fingerprint and the
	 * supersede MAC. The WordPress salt itself is never used directly.
	 *
	 * @return array{enc: string, kid: string, mac: string}|false False when no cipher is available.
	 */
	private static function keys() {
		if ( ! function_exists( 'wp_salt' ) ) {
			return false;
		}

		$salt  = (string) wp_salt( 'logged_in' );
		$cache = hash( 'sha256', $salt );

		if ( isset( self::$keys[ $cache ] ) ) {
			return self::$keys[ $cache ];
		}

		if ( ! self::load_sodium() ) {
			self::log( 'API key encryption is unavailable: no sodium implementation was found.' );
			self::$keys[ $cache ] = false;
			return false;
		}

		try {
			$master = self::generichash( $salt, '', 32 );

			self::$keys[ $cache ] = [
				'enc' => self::kdf( 32, self::SUBKEY_ENCRYPT, $master ),
				'kid' => bin2hex( substr( self::kdf( 16, self::SUBKEY_KEY_ID, $master ), 0, 8 ) ),
				'mac' => self::kdf( 32, self::SUBKEY_SUPERSED, $master ),
			];
		} catch ( \Throwable $e ) {
			self::log( 'API key encryption is unavailable: ' . $e->getMessage() );
			self::$keys[ $cache ] = false;
		}

		return self::$keys[ $cache ];
	}

	/**
	 * Make sure a sodium implementation is loaded.
	 *
	 * WordPress loads its sodium_compat polyfill only when PHP lacks
	 * sodium_crypto_box(). An older ext/sodium can have that function but
	 * not XChaCha20-Poly1305 or the KDF, so the polyfill is loaded here
	 * whenever those are missing; its ParagonIE_Sodium_Compat class is
	 * always available afterwards.
	 *
	 * @return bool
	 */
	private static function load_sodium(): bool {
		if ( self::has_native_sodium() || class_exists( 'ParagonIE_Sodium_Compat' ) ) {
			return true;
		}

		if ( defined( 'ABSPATH' ) && defined( 'WPINC' ) && is_readable( ABSPATH . WPINC . '/sodium_compat/autoload.php' ) ) {
			require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
		}

		return class_exists( 'ParagonIE_Sodium_Compat' );
	}

	/**
	 * Whether every native function used here exists.
	 *
	 * @return bool
	 */
	private static function has_native_sodium(): bool {
		return function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' )
			&& function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' )
			&& function_exists( 'sodium_crypto_kdf_derive_from_key' )
			&& function_exists( 'sodium_crypto_generichash' );
	}

	/**
	 * BLAKE2b hash.
	 *
	 * @param string $message Message.
	 * @param string $key     Optional key (16-64 bytes).
	 * @param int    $length  Output length.
	 *
	 * @return string
	 */
	private static function generichash( string $message, string $key, int $length ): string {
		if ( function_exists( 'sodium_crypto_generichash' ) ) {
			return sodium_crypto_generichash( $message, $key, $length );
		}

		return \ParagonIE_Sodium_Compat::crypto_generichash( $message, $key, $length );
	}

	/**
	 * Derive a subkey from the master key.
	 *
	 * @param int    $length    Subkey length.
	 * @param int    $subkey_id Subkey ID.
	 * @param string $master    32-byte master key.
	 *
	 * @return string
	 */
	private static function kdf( int $length, int $subkey_id, string $master ): string {
		if ( function_exists( 'sodium_crypto_kdf_derive_from_key' ) ) {
			return sodium_crypto_kdf_derive_from_key( $length, $subkey_id, self::KDF_CONTEXT, $master );
		}

		return \ParagonIE_Sodium_Compat::crypto_kdf_derive_from_key( $length, $subkey_id, self::KDF_CONTEXT, $master );
	}

	/**
	 * XChaCha20-Poly1305 encryption.
	 *
	 * @param string $plain Plaintext.
	 * @param string $ad    Authenticated data.
	 * @param string $nonce 24-byte nonce.
	 * @param string $key   32-byte key.
	 *
	 * @return string
	 */
	private static function aead_encrypt( string $plain, string $ad, string $nonce, string $key ): string {
		if ( function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ) {
			return sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $plain, $ad, $nonce, $key );
		}

		return \ParagonIE_Sodium_Compat::crypto_aead_xchacha20poly1305_ietf_encrypt( $plain, $ad, $nonce, $key );
	}

	/**
	 * XChaCha20-Poly1305 decryption. Native sodium returns false on a bad tag;
	 * sodium_compat throws. Both become false here.
	 *
	 * @param string $ciphertext Ciphertext with tag.
	 * @param string $ad         Authenticated data.
	 * @param string $nonce      24-byte nonce.
	 * @param string $key        32-byte key.
	 *
	 * @return string|false
	 */
	private static function aead_decrypt( string $ciphertext, string $ad, string $nonce, string $key ) {
		try {
			if ( function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' ) ) {
				return sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( $ciphertext, $ad, $nonce, $key );
			}

			return \ParagonIE_Sodium_Compat::crypto_aead_xchacha20poly1305_ietf_decrypt( $ciphertext, $ad, $nonce, $key );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Log a credential event when debugging is on. Never includes key material.
	 *
	 * @param string $message Message.
	 *
	 * @return void
	 */
	private static function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Media Library Tools: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only diagnostics without secrets.
		}
	}
}
