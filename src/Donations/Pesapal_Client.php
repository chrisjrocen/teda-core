<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

use RuntimeException;

/**
 * A thin REST client over Pesapal API v3 (JSON), using wp_remote_* only — the
 * plugin has no HTTP client dependency and none is warranted for four endpoints.
 *
 * Every call re-authenticates via a cached bearer token (get_token()); Pesapal
 * tokens are short-lived so the token is cached in a transient and refreshed
 * with a safety margin rather than trusted for a fixed TTL.
 *
 * Callers must always treat GetTransactionStatus, not the IPN payload, as the
 * source of truth for payment status (Pesapal documents no signature on the IPN
 * callback itself).
 */
final class Pesapal_Client {

	private const TOKEN_TRANSIENT = 'teda_pesapal_token';

	/**
	 * @throws RuntimeException When authentication fails.
	 */
	public function get_token(): string {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$response = $this->request(
			'POST',
			'/api/Auth/RequestToken',
			array(
				'consumer_key'    => Config::consumer_key(),
				'consumer_secret' => Config::consumer_secret(),
			),
			false
		);

		$token = (string) ( $response['token'] ?? '' );
		if ( '' === $token ) {
			throw new RuntimeException( 'Pesapal did not return a token: ' . wp_json_encode( $response ) );
		}

		// expiryDate is provided by Pesapal; fall back to a conservative 4 minutes
		// (tokens are documented as ~5 minutes) minus a safety margin.
		$ttl = 240;
		set_transient( self::TOKEN_TRANSIENT, $token, $ttl );

		return $token;
	}

	/**
	 * Register (or re-register) the merchant's IPN URL. Returns Pesapal's decoded
	 * response, including `ipn_id` — callers are responsible for storing it.
	 *
	 * @return array<string, mixed>
	 */
	public function register_ipn( string $url, string $type = 'GET' ): array {
		return $this->request(
			'POST',
			'/api/URLSetup/RegisterIPN',
			array(
				'url'                  => $url,
				'ipn_notification_type' => strtoupper( $type ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $order The SubmitOrderRequest body.
	 * @return array<string, mixed>
	 */
	public function submit_order( array $order ): array {
		return $this->request( 'POST', '/api/Transactions/SubmitOrderRequest', $order );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_transaction_status( string $order_tracking_id ): array {
		return $this->request( 'GET', '/api/Transactions/GetTransactionStatus?orderTrackingId=' . rawurlencode( $order_tracking_id ), null );
	}

	/**
	 * Shared request helper. Throws on transport failure, non-2xx, or malformed
	 * JSON so callers can catch one exception type rather than checking booleans.
	 *
	 * @param array<string, mixed>|null $body
	 * @return array<string, mixed>
	 * @throws RuntimeException
	 */
	private function request( string $method, string $path, ?array $body, bool $auth = true ): array {
		$headers = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' );
		if ( $auth ) {
			$headers['Authorization'] = 'Bearer ' . $this->get_token();
		}

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 20,
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$url      = Config::base_url() . $path;
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( sprintf( 'Pesapal request to %s failed: %s', $url, $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			throw new RuntimeException( sprintf( 'Pesapal returned a non-JSON response from %s (HTTP %d): %s', $url, $code, substr( wp_strip_all_tags( $raw ), 0, 200 ) ) );
		}

		if ( $code >= 400 ) {
			throw new RuntimeException( sprintf( 'Pesapal returned HTTP %d from %s: %s', $code, $url, wp_json_encode( $data ) ) );
		}

		return $data;
	}
}
