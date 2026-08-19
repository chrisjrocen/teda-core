<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

/**
 * The donor's plain-text thank-you receipt (§7: no tax-deductibility language,
 * confirmed in the interview) and a companion notice to the site admin email so
 * the named donation-support owner (Blocker B6, docs/RUNBOOK.md) sees every
 * gift as it lands.
 */
final class Receipts {

	public static function send( Record $record ): void {
		$subject = __( 'Thank you for your donation to TEDA', 'teda-core' );

		$for = '';
		if ( null !== $record->focus_area_id ) {
			$title = get_the_title( $record->focus_area_id );
			if ( '' !== $title ) {
				$for = ' ' . sprintf(
					/* translators: %s: focus area title. */
					__( 'for %s', 'teda-core' ),
					$title
				);
			}
		}

		$body = sprintf(
			/* translators: 1: donor name, 2: currency, 3: amount, 4: "for X" or empty, 5: reference. */
			__( "Hi %1\$s,\n\nThank you for your gift of %2\$s %3\$s to TEDA%4\$s.\n\nReference: %5\$s\n\nThis email is your receipt.\n\n— TEDA", 'teda-core' ),
			$record->donor_name,
			$record->currency,
			number_format( $record->amount, 2 ),
			$for,
			$record->reference
		);

		if ( '' !== $record->donor_email ) {
			wp_mail( $record->donor_email, $subject, $body );
		}

		wp_mail(
			(string) get_option( 'admin_email' ),
			sprintf(
				/* translators: %s: donation reference. */
				__( '[TEDA] New donation received: %s', 'teda-core' ),
				$record->reference
			),
			$body
		);
	}
}
