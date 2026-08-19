<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

use WP_REST_Request;

/**
 * `GET /teda/v1/donations/unsubscribe` — the self-service magic link a mobile-
 * money pledge reminder carries. Only reachable for pledges (Pesapal manages
 * card/USD recurring cancellation itself; there's nothing for this route to do
 * there). Redirects to a normal themed page rather than returning raw REST
 * HTML, so the confirmation is on-brand.
 */
final class Unsubscribe_Controller {

	public function register_routes(): void {
		register_rest_route(
			'teda/v1',
			'/donations/unsubscribe',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle( WP_REST_Request $request ) {
		$token = (string) $request->get_param( 'token' );
		$id    = Token::verify( $token );

		if ( null === $id ) {
			wp_safe_redirect( home_url( '/donate/unsubscribe/?ok=0' ) );
			exit;
		}

		$repository = new Repository();
		$record     = $repository->find( $id );

		if ( null !== $record && $record->pledge_active ) {
			$repository->set_pledge_active( $id, false );
		}

		wp_safe_redirect( home_url( '/donate/unsubscribe/?ok=1' ) );
		exit;
	}
}
