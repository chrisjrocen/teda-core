<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Forms;

/**
 * Renders the registration area on an event page: the capacity line, and then
 * either the Fluent Forms registration form or — once the event is full — a
 * waitlist call to action (P13 task 6, D6).
 *
 * Capacity is deliberately *soft*. When {@see Fluent_Adapter} cannot determine how
 * many places are left (Fluent Forms missing, or its schema changed), the number
 * is shown as information only and the form stays open. A library glitch must never
 * be the reason a willing young person is turned away.
 */
final class Event_Registration {

	/**
	 * HTML for the event page, or '' when there is nothing to show (registration
	 * closed, or Fluent Forms not available so the caller can fall back to its own
	 * external-link button).
	 *
	 * @param int $event_id Event post id.
	 */
	public static function render( int $event_id ): string {
		$adapter = new Fluent_Adapter();
		$form_id = $adapter->form_id( 'event-registration' );
		if ( null === $form_id ) {
			return ''; // No form imported / Fluent Forms down — caller falls back.
		}

		$capacity  = function_exists( 'teda_field_int' ) ? teda_field_int( 'teda_registration_capacity', $event_id, 0 ) : 0;
		$remaining = $adapter->places_remaining( $event_id, $capacity );

		$out = '<div class="teda-eventreg" id="register">';
		$out .= self::capacity_line( $capacity, $remaining );

		if ( null !== $remaining && 0 === $remaining ) {
			// Full: replace the form with a waitlist route.
			$out .= self::waitlist( $event_id );
		} else {
			$out .= '<div class="teda-eventreg__form">' . do_shortcode( '[fluentform id="' . $form_id . '"]' ) . '</div>';
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * The "X of Y places left" line. When capacity is uncapped, or unknown because
	 * the adapter returned null, we show either nothing or an informational count —
	 * never a claim we cannot stand behind.
	 */
	private static function capacity_line( int $capacity, ?int $remaining ): string {
		if ( $capacity <= 0 ) {
			return ''; // Uncapped: no places line.
		}

		if ( null === $remaining ) {
			// Capacity is set but we could not count registrations (Fluent Forms
			// unavailable). Show it as information only; registration stays open.
			return '<p class="teda-eventreg__places teda-eventreg__places--info">'
				. sprintf(
					/* translators: %d: total number of places. */
					esc_html__( '%d places', 'teda-core' ),
					$capacity
				)
				. '</p>';
		}

		$class = $remaining <= 3 ? ' teda-eventreg__places--low' : '';
		return '<p class="teda-eventreg__places' . $class . '">'
			. sprintf(
				/* translators: 1: places left, 2: total places. */
				esc_html__( '%1$d of %2$d places left', 'teda-core' ),
				$remaining,
				$capacity
			)
			. '</p>';
	}

	/**
	 * Waitlist CTA shown when the event is full. Points at the published WhatsApp /
	 * email contact so a keen attendee can still put their name down.
	 */
	private static function waitlist( int $event_id ): string {
		$email = (string) apply_filters( 'teda_core/forms/waitlist_email', get_option( 'admin_email' ), $event_id );
		$title = get_the_title( $event_id );
		$body  = rawurlencode(
			sprintf(
				/* translators: %s: event title. */
				__( 'Hello TEDA, the event "%s" is full. Please add me to the waiting list.', 'teda-core' ),
				$title
			)
		);

		return '<div class="teda-eventreg__waitlist teda-postev">'
			. '<b>' . esc_html__( 'This event is full', 'teda-core' ) . '</b>'
			. '<p>' . esc_html__( 'All places are taken. Join the waiting list and we will contact you if a place opens up.', 'teda-core' ) . '</p>'
			. '<a class="teda-btn teda-btn--brown" href="' . esc_url( 'mailto:' . $email . '?subject=' . rawurlencode( __( 'Waiting list', 'teda-core' ) ) . '&body=' . $body ) . '">'
			. esc_html__( 'Join the waiting list', 'teda-core' )
			. '</a>'
			. '</div>';
	}
}
