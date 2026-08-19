<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

/**
 * Monthly reminder emails for mobile-money/UGX "monthly" pledges — the honest
 * alternative to auto-billing, since Pesapal cannot recur mobile money. Not
 * charged automatically; each email prompts the donor to give again and carries
 * their tokenized unsubscribe link (Token.php). WhatsApp appears only as a
 * `wa.me` deep-link in the email body — no automated WhatsApp send exists in
 * this codebase.
 */
final class Pledge_Reminders {

	public function send_all(): int {
		$repository = new Repository();
		$sent       = 0;

		foreach ( $repository->active_pledges() as $record ) {
			if ( '' === $record->donor_email ) {
				continue;
			}

			$link = home_url( '/donate/unsubscribe/?token=' . rawurlencode( $record->pledge_token ) );

			$subject = __( 'Your monthly gift to TEDA', 'teda-core' );
			$body    = sprintf(
				/* translators: 1: donor name, 2: currency, 3: amount, 4: donate URL, 5: unsubscribe URL. */
				__(
					"Hi %1\$s,\n\nJust a friendly reminder that you chose to give %2\$s %3\$s monthly to TEDA. This isn't charged automatically — please send it again this month via mobile money or the link below.\n\nDonate again: %4\$s\n\nNo longer want reminders? %5\$s\n\n— TEDA",
					'teda-core'
				),
				$record->donor_name,
				$record->currency,
				number_format( $record->amount, 2 ),
				home_url( '/donate/' ),
				$link
			);

			wp_mail( $record->donor_email, $subject, $body );
			$repository->touch_reminder( $record->id );
			++$sent;
		}

		return $sent;
	}
}
