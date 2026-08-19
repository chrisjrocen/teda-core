<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `POST /teda/v1/donations` — creates a donation record and a matching Pesapal
 * order, and returns the hosted-checkout redirect URL. This is the only route
 * `Donate.php`'s live_route() ever calls; everything else about the live path
 * (recurring vs. pledge, currency, focus area) is decided here so the block
 * stays a thin form.
 */
final class Rest_Controller {

	private const MAX_AMOUNT = array( 'USD' => 10000, 'UGX' => 40000000 ); // fat-finger / bot ceiling, not a business limit.
	private const THROTTLE_MAX    = 5;
	private const THROTTLE_WINDOW = 600; // 10 minutes.

	public function register_routes(): void {
		register_rest_route(
			'teda/v1',
			'/donations',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'check_nonce' ),
			)
		);
	}

	/**
	 * Lightweight CSRF/bot friction, not authentication — the donation flow is
	 * necessarily reachable while logged out.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function check_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'teda_donation_bad_nonce', __( 'Could not verify the request. Please reload the page and try again.', 'teda-core' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function create( WP_REST_Request $request ) {
		if ( ! Config::is_configured() ) {
			return new WP_Error( 'teda_donation_not_configured', __( 'Online donations are not available right now. Please use one of the offline routes on this page.', 'teda-core' ), array( 'status' => 503 ) );
		}

		$throttled = $this->throttle_check();
		if ( is_wp_error( $throttled ) ) {
			return $throttled;
		}

		$validated = $this->validate( $request );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		list( $amount, $currency, $frequency, $donor_name, $donor_email, $donor_phone, $focus_area_id ) = $validated;

		$repository = new Repository();
		$reference  = $this->generate_reference();

		// Only card/USD "monthly" can be a real Pesapal subscription (Pesapal
		// recurring is Visa/Mastercard only). Everything else that's "monthly" is
		// a mobile-money pledge we remind manually — never auto-charged.
		$is_recurring = Record::FREQUENCY_MONTHLY === $frequency && Record::CURRENCY_USD === $currency;
		$is_pledge    = Record::FREQUENCY_MONTHLY === $frequency && ! $is_recurring;

		$id = $repository->create(
			array(
				'reference'      => $reference,
				'donor_name'     => $donor_name,
				'donor_email'    => $donor_email,
				'donor_phone'    => $donor_phone,
				'amount'         => $amount,
				'currency'       => $currency,
				'focus_area_id'  => $focus_area_id,
				'frequency'      => $frequency,
				'is_recurring'   => $is_recurring ? 1 : 0,
				'pledge_active'  => $is_pledge ? 1 : 0,
				'pledge_token'   => $is_pledge ? Token::generate( 0 ) : '', // placeholder, corrected below once $id is known.
			)
		);

		if ( $is_pledge ) {
			// The token embeds the row id, which only exists after insert — reissue
			// it now and persist the corrected value.
			$token = Token::generate( $id );
			$repository->update_status( $id, Record::STATUS_PENDING, array( 'pledge_token' => $token ) );
		}

		$order = $this->build_order( $reference, $id, $amount, $currency, $donor_name, $donor_email, $donor_phone, $focus_area_id, $is_recurring );

		try {
			$response = ( new Pesapal_Client() )->submit_order( $order );
		} catch ( RuntimeException $e ) {
			$repository->update_status( $id, Record::STATUS_FAILED );
			return new WP_Error(
				'teda_donation_gateway_error',
				sprintf(
					/* translators: %s: donation reference. */
					__( 'We could not start checkout. Please try again, or use an offline donation route. Reference: %s', 'teda-core' ),
					$reference
				),
				array( 'status' => 502 )
			);
		}

		$redirect_url = (string) ( $response['redirect_url'] ?? '' );
		if ( '' === $redirect_url ) {
			$repository->update_status( $id, Record::STATUS_FAILED );
			return new WP_Error(
				'teda_donation_no_redirect',
				sprintf(
					/* translators: %s: donation reference. */
					__( 'We could not start checkout. Please try again, or use an offline donation route. Reference: %s', 'teda-core' ),
					$reference
				),
				array( 'status' => 502 )
			);
		}

		$repository->update_status(
			$id,
			Record::STATUS_PENDING,
			array( 'pesapal_order_tracking_id' => (string) ( $response['order_tracking_id'] ?? '' ) )
		);

		return new WP_REST_Response( array( 'redirect_url' => $redirect_url, 'reference' => $reference ), 200 );
	}

	/**
	 * @return WP_Error|array{0:float,1:string,2:string,3:string,4:string,5:string,6:?int}
	 */
	private function validate( WP_REST_Request $request ) {
		$amount   = (float) $request->get_param( 'amount' );
		$currency = strtoupper( (string) $request->get_param( 'currency' ) );
		$frequency = (string) $request->get_param( 'frequency' );
		$donor_name  = sanitize_text_field( (string) $request->get_param( 'donor_name' ) );
		$donor_email = sanitize_email( (string) $request->get_param( 'donor_email' ) );
		$donor_phone = sanitize_text_field( (string) $request->get_param( 'donor_phone' ) );
		$focus_area_param = $request->get_param( 'focus_area_id' );

		if ( ! in_array( $currency, array( Record::CURRENCY_USD, Record::CURRENCY_UGX ), true ) ) {
			return new WP_Error( 'teda_donation_bad_currency', __( 'Choose USD or UGX.', 'teda-core' ), array( 'status' => 400 ) );
		}
		if ( $amount <= 0 || $amount > self::MAX_AMOUNT[ $currency ] ) {
			return new WP_Error( 'teda_donation_bad_amount', __( 'Enter a valid amount.', 'teda-core' ), array( 'status' => 400 ) );
		}
		if ( ! in_array( $frequency, array( Record::FREQUENCY_ONCE, Record::FREQUENCY_MONTHLY ), true ) ) {
			return new WP_Error( 'teda_donation_bad_frequency', __( 'Choose once or monthly.', 'teda-core' ), array( 'status' => 400 ) );
		}
		if ( '' === $donor_name ) {
			return new WP_Error( 'teda_donation_missing_name', __( 'Enter your name.', 'teda-core' ), array( 'status' => 400 ) );
		}
		if ( ! is_email( $donor_email ) ) {
			return new WP_Error( 'teda_donation_bad_email', __( 'Enter a valid email address.', 'teda-core' ), array( 'status' => 400 ) );
		}

		$focus_area_id = null;
		if ( null !== $focus_area_param && '' !== $focus_area_param ) {
			$candidate = (int) $focus_area_param;
			if ( $candidate > 0 && 'teda_focus_area' === get_post_type( $candidate ) && 'publish' === get_post_status( $candidate ) ) {
				$focus_area_id = $candidate;
			}
		}

		return array( $amount, $currency, $frequency, $donor_name, $donor_email, $donor_phone, $focus_area_id );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_order( string $reference, int $donation_id, float $amount, string $currency, string $donor_name, string $donor_email, string $donor_phone, ?int $focus_area_id, bool $is_recurring ): array {
		$name_parts = preg_split( '/\s+/', trim( $donor_name ), 2 );
		$first_name = $name_parts[0] ?? $donor_name;
		$last_name  = $name_parts[1] ?? '';

		$description = null !== $focus_area_id
			? sprintf(
				/* translators: %s: focus area title. */
				__( 'Donation to %s', 'teda-core' ),
				get_the_title( $focus_area_id )
			)
			: __( 'Donation to TEDA', 'teda-core' );

		$order = array(
			'id'              => $reference,
			'currency'        => $currency,
			'amount'          => $amount,
			'description'     => substr( $description, 0, 100 ),
			'callback_url'    => home_url( '/donate/thank-you/?ref=' . rawurlencode( $reference ) ),
			'notification_id' => Config::ipn_id(),
			'account_number'  => (string) $donation_id,
			'billing_address' => array(
				'email_address' => $donor_email,
				'phone_number'  => $donor_phone,
				'first_name'    => $first_name,
				'last_name'     => $last_name,
			),
		);

		if ( $is_recurring ) {
			$order['subscription_details'] = array(
				'start_date' => gmdate( 'd-m-Y' ),
				'end_date'   => gmdate( 'd-m-Y', strtotime( '+5 years' ) ),
				'frequency'  => 'MONTHLY',
			);
		}

		return $order;
	}

	private function generate_reference(): string {
		return sprintf( 'TEDA-%d-%s', time(), strtoupper( wp_generate_password( 6, false, false ) ) );
	}

	/**
	 * A cheap per-IP transient throttle. Not a defense against a determined
	 * attacker — no money moves without Pesapal's own checks on the actual
	 * payment step — just friction against casual abuse of a necessarily
	 * unauthenticated endpoint.
	 *
	 * @return true|WP_Error
	 */
	private function throttle_check() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'teda_donate_throttle_' . md5( $ip );

		$count = (int) get_transient( $key );
		if ( $count >= self::THROTTLE_MAX ) {
			return new WP_Error( 'teda_donation_rate_limited', __( 'Too many attempts. Please wait a few minutes and try again.', 'teda-core' ), array( 'status' => 429 ) );
		}

		set_transient( $key, $count + 1, self::THROTTLE_WINDOW );
		return true;
	}
}
