<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `GET|POST /teda/v1/donations/ipn` — Pesapal's IPN callback. Registered for
 * both methods since RegisterIPNURL fixes the method at registration time (the
 * admin "Register IPN" action registers GET; this route accepts POST too in
 * case that ever changes).
 *
 * Security model: Pesapal documents no signature on the callback itself, so the
 * payload is never trusted directly — every hit re-fetches authoritative status
 * via GetTransactionStatus. That re-fetch, keyed on our own stored status, is
 * also what makes duplicate IPN delivery harmless (a second hit for an already-
 * completed row is a no-op) — the SPEC §7 "duplicate charges" edge case still
 * needs the separate weekly reconciliation process for duplicate charges made
 * on Pesapal's side, which this cannot see.
 */
final class Ipn_Controller {

	public function register_routes(): void {
		register_rest_route(
			'teda/v1',
			'/donations/ipn',
			array(
				'methods'             => 'GET,POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle( WP_REST_Request $request ) {
		$tracking_id = (string) $request->get_param( 'OrderTrackingId' );
		$merchant_ref = (string) $request->get_param( 'OrderMerchantReference' );
		$notification_type = (string) $request->get_param( 'OrderNotificationType' );

		$repository = new Repository();
		$record     = '' !== $tracking_id ? $repository->find_by_tracking_id( $tracking_id ) : null;
		if ( null === $record && '' !== $merchant_ref ) {
			$record = $repository->find_by_reference( $merchant_ref );
		}

		if ( null === $record ) {
			// Unknown reference — ack anyway so Pesapal doesn't retry forever; log
			// for investigation rather than erroring loudly at a public endpoint.
			error_log( '[teda-core] IPN for unknown donation: ' . $tracking_id . ' / ' . $merchant_ref ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return $this->ack( $notification_type, $tracking_id, $merchant_ref );
		}

		if ( Record::STATUS_COMPLETED === $record->status ) {
			// Already processed — duplicate delivery, safe no-op.
			return $this->ack( $notification_type, $tracking_id, $merchant_ref );
		}

		try {
			$status = ( new Pesapal_Client() )->get_transaction_status( $record->pesapal_order_tracking_id );
		} catch ( RuntimeException $e ) {
			error_log( '[teda-core] GetTransactionStatus failed for ' . $record->reference . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return $this->ack( $notification_type, $tracking_id, $merchant_ref );
		}

		$this->apply_status( $repository, $record, $status );

		return $this->ack( $notification_type, $tracking_id, $merchant_ref );
	}

	/**
	 * Re-verify a still-pending record against GetTransactionStatus right now,
	 * rather than waiting for the IPN. Used by the thank-you page: the donor's
	 * redirect back can arrive before Pesapal's own IPN does, and this is the
	 * fallback that closes that gap (and the one for a genuinely missed IPN).
	 * Returns the freshest known record either way.
	 */
	public function refresh( Record $record ): Record {
		if ( Record::STATUS_COMPLETED === $record->status || '' === $record->pesapal_order_tracking_id ) {
			return $record;
		}

		$repository = new Repository();

		try {
			$status = ( new Pesapal_Client() )->get_transaction_status( $record->pesapal_order_tracking_id );
		} catch ( RuntimeException $e ) {
			return $record;
		}

		$this->apply_status( $repository, $record, $status );

		return $repository->find( $record->id ) ?? $record;
	}

	/**
	 * @param array<string, mixed> $status Pesapal's GetTransactionStatus response.
	 */
	private function apply_status( Repository $repository, Record $record, array $status ): void {
		$description = strtoupper( (string) ( $status['payment_status_description'] ?? '' ) );
		$method_raw  = strtolower( (string) ( $status['payment_method'] ?? '' ) );

		$method = '';
		if ( str_contains( $method_raw, 'card' ) || str_contains( $method_raw, 'visa' ) || str_contains( $method_raw, 'master' ) ) {
			$method = Record::METHOD_CARD;
		} elseif ( '' !== $method_raw ) {
			$method = Record::METHOD_MOBILE_MONEY;
		}

		$new_status = null;
		if ( 'COMPLETED' === $description ) {
			$new_status = Record::STATUS_COMPLETED;
		} elseif ( in_array( $description, array( 'FAILED', 'INVALID' ), true ) ) {
			$new_status = Record::STATUS_FAILED;
		} elseif ( 'REVERSED' === $description ) {
			$new_status = Record::STATUS_CANCELLED;
		}

		if ( null === $new_status ) {
			return; // Still pending on Pesapal's side — nothing to change yet.
		}

		$extra = array();
		if ( '' !== $method ) {
			$extra['method'] = $method;
		}

		$repository->update_status( $record->id, $new_status, $extra );

		if ( Record::STATUS_COMPLETED === $new_status ) {
			$updated = $repository->find( $record->id );
			if ( null !== $updated ) {
				Receipts::send( $updated );
			}
		}
	}

	/**
	 * The acknowledgement Pesapal expects back. The exact required shape is not
	 * fully documented — this mirrors the fields Pesapal sent us, which is the
	 * commonly-used ack pattern; confirm against sandbox retry behaviour during
	 * integration testing and adjust if Pesapal's dashboard shows repeated
	 * redelivery for this endpoint.
	 */
	private function ack( string $notification_type, string $tracking_id, string $merchant_ref ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'orderNotificationType'  => $notification_type,
				'orderTrackingId'        => $tracking_id,
				'orderMerchantReference' => $merchant_ref,
				'status'                 => 200,
			),
			200
		);
	}
}
