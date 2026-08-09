<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Forms;

use Teda_Core\Support\Bootable;

/**
 * Spam handling without a CAPTCHA (SPEC §8.1). reCAPTCHA is a heavy third-party
 * load that fails on exactly the poor connections TEDA's audience is on, so it is
 * ruled out. Instead, three cheap server-side gates that cost a genuine user
 * nothing:
 *
 *   1. Honeypot — a field humans never see (hidden by CSS) but bots dutifully fill.
 *   2. Time-trap — a field stamped at render; a submission that arrives implausibly
 *      fast, or replays a stale render, is a script, not a person.
 *   3. Rate limit — a per-IP ceiling over a short window, to blunt floods.
 *
 * All three run on Fluent Forms' server-side validation filter, so they cannot be
 * bypassed by disabling JavaScript, and all three are scoped to TEDA forms only
 * (detected by the presence of our stamped fields) so they never touch a form we
 * did not build.
 */
final class Spam implements Bootable {

	/** Minimum seconds a human plausibly needs before submitting. */
	private const MIN_SECONDS = 3;

	/** A render older than this (seconds) is treated as a stale replay. */
	private const MAX_AGE = 6 * HOUR_IN_SECONDS;

	/** Rate limit: max submissions per IP per window. */
	private const RATE_MAX    = 5;
	private const RATE_WINDOW = 10 * MINUTE_IN_SECONDS;

	public function register(): void {
		// Stamp the time-trap field with render time (returns the form).
		add_filter( 'fluentform/rendering_form', array( $this, 'stamp_form' ) );

		// The single server-side gate; priority 20 so field-level rules run first.
		add_filter( 'fluentform/validation_errors', array( $this, 'validate' ), 20, 4 );
	}

	/**
	 * Set the time-trap field's value to now, at render, for our forms. Fluent
	 * Forms renders server-side, so this is a trustworthy render timestamp with no
	 * client JavaScript involved.
	 *
	 * @param object $form Fluent Forms form object (has ->fields['fields']).
	 * @return object
	 */
	public function stamp_form( $form ) {
		if ( ! is_object( $form ) || empty( $form->fields['fields'] ) || ! is_array( $form->fields['fields'] ) ) {
			return $form;
		}
		foreach ( $form->fields['fields'] as &$field ) {
			$name = $field['attributes']['name'] ?? '';
			if ( Importer::TIMESTAMP_FIELD === $name ) {
				$field['attributes']['value'] = (string) time();
			}
		}
		unset( $field );

		return $form;
	}

	/**
	 * Server-side spam gate. Adds errors (which block the submission) for a filled
	 * honeypot, an implausible time-trap, or a tripped rate limit. Returns $errors
	 * untouched for forms that are not ours.
	 *
	 * @param array<string, mixed> $errors   Existing validation errors, keyed by field.
	 * @param array<string, mixed> $formData Submitted data.
	 * @param object               $form     Form object.
	 * @param mixed                $fields   Field configs.
	 * @return array<string, mixed>
	 */
	public function validate( $errors, $formData, $form = null, $fields = null ): array {
		$errors = is_array( $errors ) ? $errors : array();

		// Only our forms carry the time-trap field; skip everything else.
		if ( ! is_array( $formData ) || ! array_key_exists( Importer::TIMESTAMP_FIELD, $formData ) ) {
			return $errors;
		}

		// 1. Honeypot — any content means a bot. Reject silently against the hidden
		// field, where a human would never see the message anyway.
		$hp = (string) ( $formData[ Importer::HONEYPOT_FIELD ] ?? '' );
		if ( '' !== trim( $hp ) ) {
			$errors[ Importer::HONEYPOT_FIELD ] = array( __( 'Rejected.', 'teda-core' ) );
			return $errors; // No point running the rest.
		}

		// 2. Time-trap — too fast, missing, or a stale replay.
		$ts      = (int) ( $formData[ Importer::TIMESTAMP_FIELD ] ?? 0 );
		$elapsed = time() - $ts;
		if ( $ts <= 0 || $elapsed < self::MIN_SECONDS || $elapsed > self::MAX_AGE ) {
			$errors[ Importer::TIMESTAMP_FIELD ] = array( __( 'This form was submitted too quickly or has expired. Please reload the page and try again.', 'teda-core' ) );
			return $errors;
		}

		// 3. Rate limit — per IP, short window.
		if ( $this->rate_limited() ) {
			$errors['teda_rate_limit'] = array( __( 'Too many submissions from this connection. Please wait a few minutes and try again.', 'teda-core' ) );
			return $errors;
		}

		return $errors;
	}

	/**
	 * Increment and test the per-IP submission counter. Uses a transient so it
	 * needs no schema and expires on its own. Returns true when the caller should
	 * be blocked. A missing/opaque IP degrades to "not limited" rather than locking
	 * out a shared gateway.
	 */
	private function rate_limited(): bool {
		$ip = $this->client_ip();
		if ( '' === $ip ) {
			return false;
		}
		$key   = 'teda_ffrl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_MAX ) {
			return true;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );

		return false;
	}

	/**
	 * The client IP, best effort. Deliberately ignores forwarded headers (spoofable)
	 * unless behind a known proxy is configured elsewhere; REMOTE_ADDR is enough for
	 * a soft rate limit.
	 */
	private function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ip = filter_var( $ip, FILTER_VALIDATE_IP );

		return is_string( $ip ) ? $ip : '';
	}
}
