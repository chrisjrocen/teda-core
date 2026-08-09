<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Forms;

use Teda_Core\Support\Bootable;

/**
 * The Forms subsystem (P13). Boots the spam guard and the entry-access lock, keeps
 * the stored event id honest and server-validated, enforces soft capacity at the
 * server as well as in the UI, and registers `wp teda import-forms`.
 *
 * Fluent Forms coupling is confined to {@see Fluent_Adapter}; everything here works
 * through hook names and that adapter, so a Fluent Forms change is still one file
 * to fix (D6).
 */
final class Registry implements Bootable {

	private Spam $spam;
	private Access $access;

	public function __construct() {
		$this->spam   = new Spam();
		$this->access = new Access();
	}

	public function register(): void {
		$this->spam->register();
		$this->access->register();

		// Keep the stored event id authoritative: overwrite whatever the browser
		// sent with the id of the post the form was embedded in.
		add_filter( 'fluentform/insert_response_data', array( $this, 'force_event_id' ), 10, 3 );

		// Server-side integrity + soft capacity for event registrations.
		add_filter( 'fluentform/validation_errors', array( $this, 'validate_event' ), 30, 4 );

		// [teda_form slug="join|contact"] — resolves a TEDA form by slug so page
		// content never hardcodes an install-specific Fluent Forms id.
		add_shortcode( 'teda_form', array( $this, 'shortcode' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'teda import-forms', array( $this, 'cli_import_forms' ) );
		}
	}

	/**
	 * Overwrite the submitted event id with the server-known embedding post id, so
	 * a tampered hidden field cannot register against the wrong event or inflate a
	 * count. Only touches the event-registration form.
	 *
	 * @param array<string, mixed> $data          Data about to be stored.
	 * @param int                  $form_id       Fluent Forms form id.
	 * @param mixed                $input_configs Field configs (unused).
	 * @return array<string, mixed>
	 */
	public function force_event_id( $data, $form_id, $input_configs = null ): array {
		$data = is_array( $data ) ? $data : array();
		if ( ! array_key_exists( Fluent_Adapter::EVENT_FIELD, $data ) ) {
			return $data; // Not the event form.
		}

		$post_id = isset( $_REQUEST['__fluent_form_embded_post_id'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? absint( wp_unslash( $_REQUEST['__fluent_form_embded_post_id'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
			: 0;

		if ( $post_id > 0 && 'teda_event' === get_post_type( $post_id ) ) {
			$data[ Fluent_Adapter::EVENT_FIELD ] = (string) $post_id;
		}

		return $data;
	}

	/**
	 * Reject an event registration whose event is not a published event, or that
	 * arrives after the event has filled up (defence behind the UI waitlist swap).
	 * Capacity here is soft: if the adapter cannot count (null), we do NOT block.
	 *
	 * @param array<string, mixed> $errors   Existing errors.
	 * @param array<string, mixed> $formData Submitted data.
	 * @param object               $form     Form object.
	 * @param mixed                $fields   Field configs.
	 * @return array<string, mixed>
	 */
	public function validate_event( $errors, $formData, $form = null, $fields = null ): array {
		$errors = is_array( $errors ) ? $errors : array();
		if ( ! is_array( $formData ) || ! array_key_exists( Fluent_Adapter::EVENT_FIELD, $formData ) ) {
			return $errors; // Not the event form.
		}

		$post_id = isset( $_REQUEST['__fluent_form_embded_post_id'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? absint( wp_unslash( $_REQUEST['__fluent_form_embded_post_id'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
			: (int) $formData[ Fluent_Adapter::EVENT_FIELD ];

		if ( $post_id <= 0 || 'teda_event' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			$errors[ Fluent_Adapter::EVENT_FIELD ] = array( __( 'This event could not be found. Please reload the event page and try again.', 'teda-core' ) );
			return $errors;
		}

		$capacity  = function_exists( 'teda_field_int' ) ? teda_field_int( 'teda_registration_capacity', $post_id, 0 ) : 0;
		$remaining = ( new Fluent_Adapter() )->places_remaining( $post_id, $capacity );
		if ( null !== $remaining && 0 === $remaining ) {
			$errors[ Fluent_Adapter::EVENT_FIELD ] = array( __( 'This event is now full. Please join the waiting list instead.', 'teda-core' ) );
		}

		return $errors;
	}

	/**
	 * Render a TEDA form by slug. Resolves the install-specific Fluent Forms id at
	 * render, so page content and patterns stay portable. Degrades to a short,
	 * honest notice when the form has not been imported or Fluent Forms is down —
	 * never a fatal, never a broken shortcode string on the page.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'slug' => '' ), is_array( $atts ) ? $atts : array(), 'teda_form' );
		$slug = sanitize_key( (string) $atts['slug'] );
		if ( '' === $slug ) {
			return '';
		}

		$id = ( new Fluent_Adapter() )->form_id( $slug );
		if ( null === $id ) {
			return current_user_can( 'manage_options' )
				? '<p class="teda-notice"><em>' . esc_html( sprintf( /* translators: %s: form slug. */ __( 'The “%s” form is not set up yet — run: wp teda import-forms', 'teda-core' ), $slug ) ) . '</em></p>'
				: '';
		}

		return do_shortcode( '[fluentform id="' . $id . '"]' );
	}

	/**
	 * `wp teda import-forms` — (re)create the three forms from forms/*.json.
	 * Idempotent; safe to run on every deploy.
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Flags (unused).
	 */
	public function cli_import_forms( $args, $assoc_args ): void {
		if ( ! ( new Fluent_Adapter() )->available() ) {
			\WP_CLI::error( 'Fluent Forms is not active — cannot import forms.' );
		}

		$report = ( new Importer() )->import_all();
		if ( array() === $report ) {
			\WP_CLI::warning( 'No blueprints found in forms/.' );
			return;
		}

		foreach ( $report as $slug => $row ) {
			\WP_CLI::log( sprintf( '  %-20s %s (form #%d)', $slug, $row['status'], $row['form_id'] ) );
		}
		\WP_CLI::success( sprintf( 'Imported %d form(s).', count( $report ) ) );
	}
}
