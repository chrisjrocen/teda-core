<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

use Teda_Core\Admin\Notices;
use Teda_Core\Support\Bootable;

/**
 * Boots the donations subsystem: REST routes, the `teda_core/donate/live_configured`
 * filter that decides whether Donate.php shows a live checkout path, schema
 * migration, and the monthly pledge-reminder cron. The only class Plugin.php
 * touches for this subsystem (Admin\Donations_Screen is registered separately,
 * alongside the other Admin\* subsystems, since it is admin-only UI).
 */
final class Registry implements Bootable {

	public const CRON_HOOK = 'teda_core/donations/monthly_pledge_reminder';

	public function register(): void {
		add_action( 'teda_core/upgrade', array( Migrations::class, 'run' ) );

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		add_filter( 'teda_core/donate/live_configured', static fn(): bool => Config::is_configured() );

		add_action( 'admin_init', array( $this, 'evaluate_config_notice' ) );

		add_filter( 'cron_schedules', array( $this, 'add_monthly_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
		add_action( self::CRON_HOOK, array( $this, 'send_pledge_reminders' ) );

		add_shortcode( 'teda_donation_status', array( $this, 'shortcode_status' ) );
		add_shortcode( 'teda_donation_unsubscribed', array( $this, 'shortcode_unsubscribed' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'teda send-pledge-reminders', array( $this, 'cli_send_pledge_reminders' ) );
		}
	}

	public function register_routes(): void {
		( new Rest_Controller() )->register_routes();
		( new Ipn_Controller() )->register_routes();
		( new Unsubscribe_Controller() )->register_routes();
	}

	/**
	 * Site-wide admin notice (distinct from Donate.php's own on-page notice) so
	 * an administrator sees the gap from any /wp-admin screen, not only while
	 * editing the donate page. Mirrors Support\Env's capability-check pattern.
	 */
	public function evaluate_config_notice(): void {
		$mode = (string) get_theme_mod( 'teda_donate_mode', 'offline' );
		if ( 'live' !== $mode || Config::is_configured() ) {
			return;
		}

		Notices::add(
			__( 'Donation mode is set to "Live" but Pesapal is not fully configured yet — visitors still see the offline donation route. Save your Pesapal credentials and register the IPN URL under Donations → Settings.', 'teda-core' ),
			'warning'
		);
	}

	/**
	 * @param array<string, array{interval:int, display:string}> $schedules
	 * @return array<string, array{interval:int, display:string}>
	 */
	public function add_monthly_schedule( array $schedules ): array {
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once monthly', 'teda-core' ),
			);
		}
		return $schedules;
	}

	public function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( strtotime( 'first day of next month 08:00' ), 'monthly', self::CRON_HOOK );
		}
	}

	public function send_pledge_reminders(): void {
		( new Pledge_Reminders() )->send_all();
	}

	/**
	 * `wp teda send-pledge-reminders` — run the monthly reminder pass on demand,
	 * so it can be verified without waiting for the real schedule.
	 *
	 * @param array<int, string>    $args       Unused.
	 * @param array<string, string> $assoc_args Unused.
	 */
	public function cli_send_pledge_reminders( $args, $assoc_args ): void {
		$sent = ( new Pledge_Reminders() )->send_all();
		\WP_CLI::success( sprintf( 'Sent %d pledge reminder(s).', $sent ) );
	}

	/**
	 * `[teda_donation_status]` — the thank-you page (§7: explicit, never an
	 * ambiguous spinner). Reads `?ref=` from the URL; if the record is still
	 * pending, re-verifies once against GetTransactionStatus before rendering
	 * (closing the gap where the donor's redirect lands before Pesapal's IPN).
	 */
	public function shortcode_status(): string {
		$reference = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $reference ) {
			return '<div class="teda-donate-status teda-donate-status--pending"><p>' . esc_html__( 'No donation reference was given.', 'teda-core' ) . '</p></div>';
		}

		$repository = new Repository();
		$record     = $repository->find_by_reference( $reference );

		if ( null === $record ) {
			return '<div class="teda-donate-status teda-donate-status--pending"><p>' . esc_html(
				sprintf(
					/* translators: %s: donation reference. */
					__( "We couldn't find a donation with reference %s. If you completed a payment, contact us with this reference and do not send a second payment.", 'teda-core' ),
					$reference
				)
			) . '</p></div>';
		}

		if ( Record::STATUS_PENDING === $record->status ) {
			$record = ( new Ipn_Controller() )->refresh( $record );
		}

		$support_email = (string) get_option( 'admin_email' );

		switch ( $record->status ) {
			case Record::STATUS_COMPLETED:
				return '<div class="teda-donate-status teda-donate-status--completed"><p>' . esc_html(
					sprintf(
						/* translators: 1: currency, 2: amount, 3: reference. */
						__( 'Thank you — your gift of %1$s %2$s was received. A receipt has been emailed to you. Reference: %3$s.', 'teda-core' ),
						$record->currency,
						number_format( $record->amount, 2 ),
						$record->reference
					)
				) . '</p></div>';

			case Record::STATUS_FAILED:
			case Record::STATUS_CANCELLED:
				return '<div class="teda-donate-status teda-donate-status--failed"><p>' . esc_html(
					sprintf(
						/* translators: 1: currency, 2: amount, 3: reference. */
						__( "That payment didn't go through. Your amount (%1\$s %2\$s) is below — you can try again, or use one of the offline donation routes. Reference: %3\$s.", 'teda-core' ),
						$record->currency,
						number_format( $record->amount, 2 ),
						$record->reference
					)
				) . '</p><p><a class="teda-btn teda-btn--brown" href="' . esc_url( home_url( '/donate/#teda-donate-panel' ) ) . '">' . esc_html__( 'Try again', 'teda-core' ) . '</a></p></div>';

			default: // Still pending after the refresh attempt.
				return '<div class="teda-donate-status teda-donate-status--pending"><p>' . esc_html(
					sprintf(
						/* translators: 1: reference, 2: support email. */
						__( "We're confirming your payment now — this can take a minute, and we'll email your receipt as soon as it's confirmed. Reference: %1\$s. If you don't hear from us within 24 hours, contact %2\$s with this reference — please don't send a second payment.", 'teda-core' ),
						$record->reference,
						$support_email
					)
				) . '</p></div>';
		}
	}

	/**
	 * `[teda_donation_unsubscribed]` — the confirmation page Unsubscribe_Controller
	 * redirects to after a pledge-reminder magic link is used.
	 */
	public function shortcode_unsubscribed(): string {
		$ok = isset( $_GET['ok'] ) && '1' === $_GET['ok']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return $ok
			? '<div class="teda-donate-status teda-donate-status--completed"><p>' . esc_html__( "You won't receive any more reminders for that monthly gift. Thank you for everything you've already given.", 'teda-core' ) . '</p></div>'
			: '<div class="teda-donate-status teda-donate-status--pending"><p>' . esc_html__( 'That unsubscribe link is invalid or has expired. Contact us if you would like reminders stopped.', 'teda-core' ) . '</p></div>';
	}
}
