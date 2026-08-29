<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Admin;

use RuntimeException;
use Teda_Core\Donations\Config;
use Teda_Core\Donations\Pesapal_Client;
use Teda_Core\Donations\Repository;
use Teda_Core\Support\Bootable;

/**
 * The Donations admin menu. Donation records and Pesapal credentials are
 * Administrators-only (`manage_options`, checked independently of menu
 * visibility since donation records are PII); the Campaigns submenu (fundraising
 * content — lead copy, tiers, goals) is `edit_posts`, so Editors can run
 * campaigns without needing full admin access — see add_menu().
 *
 * Surfaces: a list of donations (the weekly reconciliation starting point,
 * SPEC §7), a CSV export of that list (the actual reconciliation artifact), the
 * Campaigns CPT list/edit screens (native WP, registered here but rendered by
 * core — see Admin\Campaign_Repeater for the tiers/goals meta box on each
 * Campaign's edit screen), and a payment settings panel showing config status
 * plus the one-time "Register IPN" action.
 */
final class Donations_Screen implements Bootable {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_teda_donations_export_csv', array( $this, 'export_csv' ) );
		add_action( 'admin_post_teda_pesapal_register_ipn', array( $this, 'register_ipn_action' ) );
		add_action( 'admin_post_teda_pesapal_save_settings', array( $this, 'save_settings_action' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'Donations', 'teda-core' ),
			__( 'Donations', 'teda-core' ),
			'manage_options',
			'teda-donations',
			array( $this, 'render_list' ),
			'dashicons-heart',
			30
		);
		add_submenu_page(
			'teda-donations',
			__( 'Donations', 'teda-core' ),
			__( 'All Donations', 'teda-core' ),
			'manage_options',
			'teda-donations',
			array( $this, 'render_list' )
		);
		// Campaigns is `edit_posts` — Editors manage fundraising content (lead
		// copy, tiers, goals) without needing `manage_options`, which stays
		// reserved for donation records (PII) and Pesapal credentials below.
		// Pointing straight at the native CPT list table means capability
		// enforcement on the actual edit/create screens comes from WordPress
		// core (teda_campaign's capability_type), not custom code here.
		add_submenu_page(
			'teda-donations',
			__( 'Campaigns', 'teda-core' ),
			__( 'Campaigns', 'teda-core' ),
			'edit_posts',
			'edit.php?post_type=teda_campaign'
		);
		add_submenu_page(
			'teda-donations',
			__( 'Payment Settings', 'teda-core' ),
			__( 'Payment Settings', 'teda-core' ),
			'manage_options',
			'teda-donations-settings',
			array( $this, 'render_settings' )
		);
	}

	public function render_list(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$records    = ( new Repository() )->all_for_export();
		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=teda_donations_export_csv' ), 'teda_donations_export_csv' );

		echo '<div class="wrap"><h1>' . esc_html__( 'Donations', 'teda-core' ) . '</h1>';
		echo '<p><a href="' . esc_url( $export_url ) . '" class="button">' . esc_html__( 'Export CSV (for weekly reconciliation)', 'teda-core' ) . '</a></p>';

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Date', 'Reference', 'Donor', 'Amount', 'Goal', 'Frequency', 'Method', 'Status', 'Tracking ID' ) as $col ) {
			echo '<th>' . esc_html( $col ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( array() === $records ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No donations yet.', 'teda-core' ) . '</td></tr>';
		}

		foreach ( $records as $record ) {
			echo '<tr>';
			echo '<td>' . esc_html( $record->created_at ) . '</td>';
			echo '<td>' . esc_html( $record->reference ) . '</td>';
			echo '<td>' . esc_html( $record->donor_name . ' <' . $record->donor_email . '>' ) . '</td>';
			echo '<td>' . esc_html( $record->currency . ' ' . number_format( $record->amount, 2 ) ) . '</td>';
			echo '<td>' . esc_html( '' !== ( $record->goal_label ?? '' ) ? $record->goal_label : '—' ) . '</td>';
			echo '<td>' . esc_html( $record->frequency ) . '</td>';
			echo '<td>' . esc_html( '' !== $record->method ? $record->method : '—' ) . '</td>';
			echo '<td>' . esc_html( $record->status ) . '</td>';
			echo '<td>' . esc_html( '' !== $record->pesapal_order_tracking_id ? $record->pesapal_order_tracking_id : '—' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$has_credentials = Config::has_credentials();
		$configured       = Config::is_configured();
		$register_url    = wp_nonce_url( admin_url( 'admin-post.php?action=teda_pesapal_register_ipn' ), 'teda_pesapal_register_ipn' );
		$env             = Config::env();

		echo '<div class="wrap"><h1>' . esc_html__( 'Donation Settings', 'teda-core' ) . '</h1>';

		$this->render_settings_result_notice();
		$this->render_ipn_result_notice();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="teda_pesapal_save_settings">';
		wp_nonce_field( 'teda_pesapal_save_settings' );

		echo '<table class="form-table"><tbody>';

		echo '<tr><th><label for="teda_pesapal_env">' . esc_html__( 'Environment', 'teda-core' ) . '</label></th><td>';
		echo '<select id="teda_pesapal_env" name="teda_pesapal_env">';
		echo '<option value="sandbox"' . selected( $env, 'sandbox', false ) . '>' . esc_html__( 'Sandbox', 'teda-core' ) . '</option>';
		echo '<option value="live"' . selected( $env, 'live', false ) . '>' . esc_html__( 'Live', 'teda-core' ) . '</option>';
		echo '</select></td></tr>';

		echo '<tr><th><label for="teda_pesapal_consumer_key">' . esc_html__( 'Consumer key', 'teda-core' ) . '</label></th><td>';
		echo '<input type="text" id="teda_pesapal_consumer_key" name="teda_pesapal_consumer_key" class="regular-text" autocomplete="off" value="' . esc_attr( Config::consumer_key() ) . '"></td></tr>';

		echo '<tr><th><label for="teda_pesapal_consumer_secret">' . esc_html__( 'Consumer secret', 'teda-core' ) . '</label></th><td>';
		echo '<input type="password" id="teda_pesapal_consumer_secret" name="teda_pesapal_consumer_secret" class="regular-text" autocomplete="off" placeholder="' . ( '' !== Config::consumer_secret() ? esc_attr__( '•••••••• (saved — leave blank to keep it)', 'teda-core' ) : '' ) . '">';
		echo '<p class="description">' . esc_html__( 'Never shown once saved. Leave blank to keep the current secret; type a new value to replace it.', 'teda-core' ) . '</p></td></tr>';

		echo '<tr><th>' . esc_html__( 'IPN registered', 'teda-core' ) . '</th><td>' . ( '' !== Config::ipn_id() ? esc_html( Config::ipn_id() ) : esc_html__( 'Not yet registered', 'teda-core' ) ) . '</td></tr>';

		$live_mode = 'live' === get_theme_mod( 'teda_donate_mode', 'offline' );
		echo '<tr><th><label for="teda_donate_live">' . esc_html__( 'Live checkout', 'teda-core' ) . '</label></th><td>';
		echo '<label><input type="checkbox" id="teda_donate_live" name="teda_donate_live" value="1"' . checked( $live_mode, true, false ) . '> ' . esc_html__( 'Show visitors the Pesapal checkout instead of the offline donation route', 'teda-core' ) . '</label>';
		if ( $live_mode && ! $configured ) {
			echo '<p class="description">' . esc_html__( 'This is on, but visitors still see the offline route — Pesapal isn\'t fully configured yet (credentials and/or IPN registration are missing above).', 'teda-core' ) . '</p>';
		} elseif ( ! $live_mode ) {
			echo '<p class="description">' . esc_html__( 'Off — visitors see the offline donation route (mobile money, bank, WhatsApp) regardless of configuration below.', 'teda-core' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'On, and fully configured — visitors see the live Pesapal checkout.', 'teda-core' ) . '</p>';
		}
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Save settings', 'teda-core' ) );
		echo '</form>';

		if ( $has_credentials ) {
			echo '<p><a href="' . esc_url( $register_url ) . '" class="button button-primary">' . esc_html__( 'Register IPN URL', 'teda-core' ) . '</a></p>';
			echo '<p class="description">' . esc_html__( 'Run this once per environment (sandbox/live), and again any time the site\'s public URL changes — e.g. every time a local dev tunnel (Local Live Link / ngrok) rotates.', 'teda-core' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * save_settings_action() redirects here after running — Admin\Notices is a
	 * per-request memory bag that can't survive that redirect, so the result is
	 * passed via query args and rendered directly instead.
	 */
	private function render_settings_result_notice(): void {
		$saved = isset( $_GET['teda_settings'] ) ? sanitize_key( wp_unslash( $_GET['teda_settings'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'saved' === $saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Donation settings saved.', 'teda-core' ) . '</p></div>';
		}
	}

	/**
	 * register_ipn_action() redirects here after running — Admin\Notices is a
	 * per-request memory bag that can't survive that redirect, so the result is
	 * passed via query args and rendered directly instead.
	 */
	private function render_ipn_result_notice(): void {
		$ipn = isset( $_GET['teda_ipn'] ) ? sanitize_key( wp_unslash( $_GET['teda_ipn'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'registered' === $ipn ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'IPN URL registered with Pesapal.', 'teda-core' ) . '</p></div>';
		} elseif ( 'error' === $ipn ) {
			$message = isset( $_GET['teda_ipn_message'] ) ? sanitize_text_field( wp_unslash( $_GET['teda_ipn_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Could not register the IPN URL with Pesapal.', 'teda-core' ) . ( '' !== $message ? ' ' . esc_html( $message ) : '' ) . '</p></div>';
		}
	}

	public function export_csv(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'teda_donations_export_csv' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'teda-core' ) );
		}

		$records = ( new Repository() )->all_for_export();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="teda-donations-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'Date', 'Reference', 'Donor name', 'Donor email', 'Amount', 'Currency', 'Focus area', 'Goal', 'Frequency', 'Method', 'Status', 'Tracking ID' ) );

		foreach ( $records as $record ) {
			fputcsv(
				$out,
				array(
					$record->created_at,
					$record->reference,
					$record->donor_name,
					$record->donor_email,
					number_format( $record->amount, 2, '.', '' ),
					$record->currency,
					null !== $record->focus_area_id ? get_the_title( $record->focus_area_id ) : '',
					$record->goal_label ?? '',
					$record->frequency,
					$record->method,
					$record->status,
					$record->pesapal_order_tracking_id,
				)
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public function save_settings_action(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'teda_pesapal_save_settings' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'teda-core' ) );
		}

		$env = isset( $_POST['teda_pesapal_env'] ) ? sanitize_key( wp_unslash( $_POST['teda_pesapal_env'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $env, array( 'sandbox', 'live' ), true ) ) {
			$env = 'sandbox';
		}
		update_option( Config::ENV_OPTION, $env );

		$key = isset( $_POST['teda_pesapal_consumer_key'] ) ? sanitize_text_field( wp_unslash( $_POST['teda_pesapal_consumer_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_option( Config::CONSUMER_KEY_OPTION, $key );

		// The secret field is never pre-filled with the real value, so a blank
		// submission means "leave it as it is" — only a non-empty value overwrites it.
		$secret = isset( $_POST['teda_pesapal_consumer_secret'] ) ? (string) wp_unslash( $_POST['teda_pesapal_consumer_secret'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( '' !== $secret ) {
			update_option( Config::CONSUMER_SECRET_OPTION, $secret );
		}

		$live = isset( $_POST['teda_donate_live'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		set_theme_mod( 'teda_donate_mode', $live ? 'live' : 'offline' );

		wp_safe_redirect( admin_url( 'admin.php?page=teda-donations-settings&teda_settings=saved' ) );
		exit;
	}

	public function register_ipn_action(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'teda_pesapal_register_ipn' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'teda-core' ) );
		}

		if ( ! Config::has_credentials() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=teda-donations-settings&teda_ipn=error&teda_ipn_message=' . rawurlencode( __( 'Save your Pesapal environment, consumer key and consumer secret first.', 'teda-core' ) ) ) );
			exit;
		}

		try {
			$response = ( new Pesapal_Client() )->register_ipn( rest_url( 'teda/v1/donations/ipn' ), 'GET' );
			$ipn_id   = (string) ( $response['ipn_id'] ?? '' );

			if ( '' === $ipn_id ) {
				throw new RuntimeException( 'Pesapal did not return an ipn_id: ' . wp_json_encode( $response ) );
			}

			update_option( Config::IPN_ID_OPTION, $ipn_id, false );
			wp_safe_redirect( admin_url( 'admin.php?page=teda-donations-settings&teda_ipn=registered' ) );
		} catch ( RuntimeException $e ) {
			wp_safe_redirect( admin_url( 'admin.php?page=teda-donations-settings&teda_ipn=error&teda_ipn_message=' . rawurlencode( $e->getMessage() ) ) );
		}

		exit;
	}
}
