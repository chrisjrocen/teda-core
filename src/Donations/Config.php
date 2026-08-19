<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

/**
 * Pesapal configuration: reads credentials from options set on the Donations →
 * Settings admin screen (Admin\Donations_Screen), plus the stored IPN id.
 * `is_configured()` is what `teda_core/donate/live_configured` is wired to —
 * the donate block only ever shows a live checkout path when this is true
 * (Donate.php's admin-safety guarantee is unchanged by this class).
 */
final class Config {

	public const IPN_ID_OPTION        = 'teda_pesapal_ipn_id';
	public const CONSUMER_KEY_OPTION  = 'teda_pesapal_consumer_key';
	public const CONSUMER_SECRET_OPTION = 'teda_pesapal_consumer_secret';
	public const ENV_OPTION            = 'teda_pesapal_env';

	/**
	 * Whether Pesapal is fully wired: credentials present, an environment chosen,
	 * and an IPN already registered (submitting an order without a real
	 * notification_id would silently produce transactions we can never confirm).
	 */
	public static function is_configured(): bool {
		return self::has_credentials() && '' !== self::ipn_id();
	}

	/**
	 * Credentials + environment present, independent of IPN registration — used
	 * by the admin settings screen to decide whether the "Register IPN" button
	 * can be shown at all.
	 */
	public static function has_credentials(): bool {
		return '' !== self::consumer_key() && '' !== self::consumer_secret() && in_array( self::env(), array( 'sandbox', 'live' ), true );
	}

	public static function consumer_key(): string {
		return (string) get_option( self::CONSUMER_KEY_OPTION, '' );
	}

	public static function consumer_secret(): string {
		return (string) get_option( self::CONSUMER_SECRET_OPTION, '' );
	}

	public static function env(): string {
		return (string) get_option( self::ENV_OPTION, '' );
	}

	public static function base_url(): string {
		return 'live' === self::env() ? 'https://pay.pesapal.com/v3' : 'https://cybqa.pesapal.com/pesapalv3';
	}

	public static function ipn_id(): string {
		return (string) get_option( self::IPN_ID_OPTION, '' );
	}

	/**
	 * A secret used to sign pledge-unsubscribe tokens (Token.php). Combines a
	 * WordPress salt with the Pesapal consumer secret so it rotates if either
	 * does, without needing a dedicated option.
	 */
	public static function token_secret(): string {
		return wp_salt( 'auth' ) . self::consumer_secret();
	}
}
