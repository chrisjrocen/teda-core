<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

/**
 * Schema for `{$wpdb->prefix}teda_donations`. A dedicated table rather than a
 * CPT: donation rows are transactional PII (name, email, phone, amounts) with a
 * fixed, narrow shape and no editorial workflow — forcing them into
 * wp_posts/wp_postmeta would mean EAV-style meta lookups for every
 * reconciliation query and CSV export, and would surface PII in the standard
 * post list UI unless separately hardened. This is a deliberate, one-off
 * exception to teda-core's otherwise CPT-heavy convention.
 *
 * Hooked to the plugin's existing `teda_core/upgrade` action (Plugin::run_migrations,
 * fired on activation and on every version bump), the same idempotent-migration
 * mechanism every other subsystem uses — dbDelta() itself is safe to run
 * repeatedly, so no separate version gate is needed here.
 */
final class Migrations {

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'teda_donations';
	}

	public static function run(): void {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			reference VARCHAR(50) NOT NULL,
			donor_name VARCHAR(191) NOT NULL DEFAULT '',
			donor_email VARCHAR(191) NOT NULL DEFAULT '',
			donor_phone VARCHAR(32) NOT NULL DEFAULT '',
			amount DECIMAL(14,2) NOT NULL,
			currency VARCHAR(3) NOT NULL,
			focus_area_id BIGINT UNSIGNED NULL,
			goal_label VARCHAR(191) NULL,
			frequency VARCHAR(10) NOT NULL DEFAULT 'once',
			method VARCHAR(20) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			pesapal_order_tracking_id VARCHAR(64) NOT NULL DEFAULT '',
			pesapal_merchant_reference VARCHAR(64) NOT NULL DEFAULT '',
			is_recurring TINYINT(1) NOT NULL DEFAULT 0,
			subscription_end_date DATE NULL,
			pledge_active TINYINT(1) NOT NULL DEFAULT 0,
			pledge_token VARCHAR(64) NOT NULL DEFAULT '',
			last_reminder_sent_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reference (reference),
			KEY status_idx (status),
			KEY pledge_idx (pledge_active, frequency, method),
			KEY tracking_idx (pesapal_order_tracking_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
