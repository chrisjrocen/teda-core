<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

/**
 * Signs and verifies the pledge-unsubscribe magic link. This is the one place
 * in the donations flow where WE own cancellation (card/USD recurring is
 * managed entirely by Pesapal's own donor email/dashboard — no token needed
 * there), so a stateless HMAC token is appropriate: no expiry, since a pledge
 * should stay cancellable for as long as it stays active.
 */
final class Token {

	public static function generate( int $donation_id ): string {
		$payload = $donation_id . '.' . time();
		$sig     = hash_hmac( 'sha256', $payload, Config::token_secret() );

		return rawurlencode( base64_encode( $payload . '.' . $sig ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Returns the donation id the token was issued for, or null if the token is
	 * malformed or its signature doesn't match.
	 */
	public static function verify( string $token ): ?int {
		$decoded = base64_decode( rawurldecode( $token ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded ) {
			return null;
		}

		$parts = explode( '.', $decoded );
		if ( 3 !== count( $parts ) ) {
			return null;
		}
		list( $id, $timestamp, $sig ) = $parts;

		if ( ! ctype_digit( $id ) || ! ctype_digit( $timestamp ) ) {
			return null;
		}

		$expected = hash_hmac( 'sha256', "{$id}.{$timestamp}", Config::token_secret() );
		if ( ! hash_equals( $expected, $sig ) ) {
			return null;
		}

		return (int) $id;
	}
}
