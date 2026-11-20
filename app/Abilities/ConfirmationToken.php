<?php
/**
 * One-time confirmation tokens for MCP tools that preview, then change.
 *
 * @package TinySolutions\mlt
 */

namespace TinySolutions\mlt\Abilities;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

use TinySolutions\mlt\Helpers\Fns;
use WP_Error;

/**
 * A preview stores what it checked under a random token; the matching
 * change tool uses it once. Each token is an option (not autoloaded) named
 * by a per-tool prefix and the token's SHA-256, holding the payload, the
 * user it was issued to and its expiry. Its payload is read from the
 * database, not a cache, and only the request whose delete_option() removed
 * the row may use it, so a token works once.
 */
class ConfirmationToken {

	/**
	 * Expired tokens read per pruning query.
	 *
	 * @var int
	 */
	const PRUNE_LIMIT = 100;

	/**
	 * Most pruning queries per issued token.
	 *
	 * @var int
	 */
	const PRUNE_BATCHES = 10;

	/**
	 * Store a payload under a new token for the current user. Expired tokens
	 * with the same prefix are pruned first.
	 *
	 * @param string $prefix  Option-name prefix of the tool's tokens.
	 * @param array  $payload Data the change tool needs.
	 * @param int    $ttl     Seconds the token stays valid.
	 *
	 * @return array{token: string, expires: int}|null Null when it could not be stored.
	 */
	public static function issue( string $prefix, array $payload, int $ttl ): ?array {
		self::prune( $prefix );
		$token   = bin2hex( random_bytes( 32 ) );
		$expires = time() + $ttl;
		$stored  = add_option(
			self::option( $prefix, $token ),
			array_merge(
				$payload,
				[
					'user_id' => get_current_user_id(),
					'expires' => $expires,
				]
			),
			'',
			false
		);
		return $stored ? [
			'token'   => $token,
			'expires' => $expires,
		] : null;
	}

	/**
	 * Check a token and, when valid, use it up.
	 *
	 * @param string $prefix       Option-name prefix of the tool's tokens.
	 * @param mixed  $token        Token from the client.
	 * @param string $preview_tool Name of the tool that issues the token.
	 * @param string $error_code   Error code for an invalid token.
	 *
	 * @return array|WP_Error The stored payload.
	 */
	public static function consume( string $prefix, $token, string $preview_tool, string $error_code ) {
		/* translators: %s: preview tool name. */
		$again = sprintf( __( 'Run %s again.', 'media-library-tools' ), $preview_tool );
		if ( ! is_string( $token ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			/* translators: %s: preview tool name. */
			return self::error( $error_code, sprintf( __( 'A confirmation_token from %s is required.', 'media-library-tools' ), $preview_tool ) );
		}
		$option  = self::option( $prefix, $token );
		$payload = self::read( $option );
		if ( ! is_array( $payload ) ) {
			return self::error( $error_code, __( 'The confirmation token is unknown or was already used.', 'media-library-tools' ) . ' ' . $again );
		}
		if ( (int) ( $payload['expires'] ?? 0 ) < time() ) {
			delete_option( $option );
			return self::error( $error_code, __( 'The confirmation token expired.', 'media-library-tools' ) . ' ' . $again );
		}
		if ( (int) ( $payload['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return self::error( $error_code, __( 'The confirmation token was issued to another user.', 'media-library-tools' ) );
		}
		if ( ! delete_option( $option ) ) {
			return self::error( $error_code, __( 'The confirmation token is unknown or was already used.', 'media-library-tools' ) . ' ' . $again );
		}
		return $payload;
	}

	/**
	 * Option name of a token.
	 *
	 * @param string $prefix Prefix.
	 * @param string $token  Token.
	 *
	 * @return string
	 */
	public static function option( string $prefix, string $token ): string {
		return $prefix . hash( 'sha256', $token );
	}

	/**
	 * Invalid-token error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 *
	 * @return WP_Error
	 */
	private static function error( string $code, string $message ): WP_Error {
		return AbilityGuard::error( $code, $message, 400 );
	}

	/**
	 * A token's payload, read from the database.
	 *
	 * @param string $option Option name.
	 *
	 * @return array|null
	 */
	private static function read( string $option ): ?array {
		try {
			$rows = Fns::DB()->select( 'option_value' )->from( 'options' )->where( 'option_name', '=', $option )->limit( 1 )->get();
		} catch ( \Throwable $e ) {
			return null;
		}
		if ( ! is_array( $rows ) || ! isset( $rows[0]['option_value'] ) ) {
			return null;
		}
		$payload = maybe_unserialize( $rows[0]['option_value'] );
		return is_array( $payload ) ? $payload : null;
	}

	/**
	 * Delete expired tokens with a prefix, oldest first, stopping at the
	 * first live one.
	 *
	 * @param string $prefix Prefix.
	 *
	 * @return void
	 */
	private static function prune( string $prefix ): void {
		global $wpdb;
		for ( $batch = 0; $batch < self::PRUNE_BATCHES; $batch++ ) {
			try {
				$rows = Fns::DB()->select( 'option_name', 'option_value' )->from( 'options' )
					->where( 'option_name', 'LIKE', $wpdb->esc_like( $prefix ) . '%' )
					->orderBy( 'option_id', 'ASC' )
					->limit( self::PRUNE_LIMIT )->get();
			} catch ( \Throwable $e ) {
				return;
			}
			if ( ! is_array( $rows ) || ! $rows ) {
				return;
			}
			foreach ( $rows as $row ) {
				$payload = maybe_unserialize( $row['option_value'] ?? '' );
				if ( is_array( $payload ) && (int) ( $payload['expires'] ?? 0 ) >= time() ) {
					return;
				}
				delete_option( (string) $row['option_name'] );
			}
			if ( count( $rows ) < self::PRUNE_LIMIT ) {
				return;
			}
		}
	}
}
