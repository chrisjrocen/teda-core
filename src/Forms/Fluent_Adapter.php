<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Forms;

/**
 * The single seam between TEDA and Fluent Forms (D6). Every call into the Fluent
 * Forms database schema or API lives here and nowhere else, so a breaking Fluent
 * Forms update is one file to fix.
 *
 * Design rule: when Fluent Forms is missing or its schema has moved, the read
 * methods return `null` — never a wrong number and never a fatal. Callers treat
 * `null` as "capacity unknown": they show information only and keep registration
 * open (P13 task 6). A hard failure here must never close a form that should be
 * open, nor open one that should be full — but "unknown" errs towards open,
 * because turning willing volunteers away on a library glitch is the worse harm.
 */
final class Fluent_Adapter {

	/**
	 * Meta key (in wp_fluentform_form_meta) tagging a form with its TEDA slug, so
	 * we resolve forms by a stable slug instead of a database id that changes per
	 * install. Written by the Importer.
	 */
	public const SLUG_META = '_teda_form_slug';

	/**
	 * The entry field that carries the event post id on an event registration.
	 * Capacity is counted by matching this field.
	 */
	public const EVENT_FIELD = 'teda_event_id';

	/**
	 * True only when Fluent Forms is active and the schema this adapter relies on
	 * is present. Everything below short-circuits to null when this is false.
	 */
	public function available(): bool {
		if ( ! defined( 'FLUENTFORM' ) || ! function_exists( 'wpFluentForm' ) ) {
			return false;
		}

		global $wpdb;
		static $ok = null;
		if ( null !== $ok ) {
			return $ok;
		}

		$forms   = $wpdb->prefix . 'fluentform_forms';
		$details = $wpdb->prefix . 'fluentform_entry_details';
		$have    = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB
		$ok      = in_array( $forms, $have, true ) && in_array( $details, $have, true );

		return $ok;
	}

	/**
	 * Resolve a TEDA form slug to its Fluent Forms id, or null if not imported yet.
	 */
	public function form_id( string $slug ): ?int {
		if ( ! $this->available() ) {
			return null;
		}

		global $wpdb;
		$meta = $wpdb->prefix . 'fluentform_form_meta';
		$id   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT form_id FROM {$meta} WHERE meta_key = %s AND value = %s ORDER BY form_id ASC LIMIT 1", // phpcs:ignore WordPress.DB
				self::SLUG_META,
				$slug
			)
		);

		return ( null === $id ) ? null : (int) $id;
	}

	/**
	 * How many people have registered for one event. Counts distinct submissions of
	 * the event-registration form whose stored event id matches. Null when Fluent
	 * Forms is unavailable or the form has not been imported.
	 */
	public function count_registrations( int $event_id ): ?int {
		if ( ! $this->available() ) {
			return null;
		}
		$form_id = $this->form_id( 'event-registration' );
		if ( null === $form_id ) {
			return null;
		}

		global $wpdb;
		$details = $wpdb->prefix . 'fluentform_entry_details';
		$count   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT submission_id) FROM {$details} WHERE form_id = %d AND field_name = %s AND field_value = %s", // phpcs:ignore WordPress.DB
				$form_id,
				self::EVENT_FIELD,
				(string) $event_id
			)
		);

		return ( null === $count ) ? null : (int) $count;
	}

	/**
	 * Places left for an event given its capacity. Never negative. Null when
	 * capacity cannot be determined (Fluent Forms unavailable) so the caller keeps
	 * registration open and shows the number as information only.
	 *
	 * @param int $event_id Event post id.
	 * @param int $capacity Configured number of places; 0 or less means uncapped.
	 */
	public function places_remaining( int $event_id, int $capacity ): ?int {
		if ( $capacity <= 0 ) {
			return null; // Uncapped: there is no "remaining".
		}
		$taken = $this->count_registrations( $event_id );
		if ( null === $taken ) {
			return null; // Unknown: caller keeps the form open.
		}

		return (int) max( 0, $capacity - $taken );
	}
}
