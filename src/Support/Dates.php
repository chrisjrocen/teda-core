<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Support;

/**
 * Date helpers for the date-aware blocks (P09). All formatting goes through
 * wp_date() so it respects the site timezone (wp_timezone()) — never date(), which
 * uses the server timezone. "Now" is the Unix epoch from time(), which is
 * timezone-agnostic and matches Meta Box's stored `timestamp => true` values, so a
 * block is correct the day after a date passes with nobody touching it.
 */
final class Dates {

	/**
	 * The current moment as a Unix timestamp (UTC epoch).
	 */
	public static function now(): int {
		return time();
	}

	/**
	 * The day-of-month for a timestamp, in the site timezone (e.g. "22").
	 */
	public static function day( int $timestamp ): string {
		return wp_date( 'j', $timestamp );
	}

	/**
	 * The abbreviated month for a timestamp, in the site timezone (e.g. "Aug").
	 */
	public static function month_abbrev( int $timestamp ): string {
		return wp_date( 'M', $timestamp );
	}

	/**
	 * A full, human date+time in the site's configured format.
	 */
	public static function full( int $timestamp ): string {
		return wp_date( (string) get_option( 'date_format', 'j F Y' ), $timestamp );
	}

	/**
	 * Break a positive duration (seconds) into whole days/hours/minutes/seconds,
	 * clamped at zero so a countdown never shows negatives (SPEC §10.2).
	 *
	 * @return array{days:int, hours:int, minutes:int, seconds:int}
	 */
	public static function breakdown( int $seconds ): array {
		$seconds = max( 0, $seconds );

		return array(
			'days'    => (int) floor( $seconds / DAY_IN_SECONDS ),
			'hours'   => (int) floor( ( $seconds % DAY_IN_SECONDS ) / HOUR_IN_SECONDS ),
			'minutes' => (int) floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS ),
			'seconds' => (int) ( $seconds % MINUTE_IN_SECONDS ),
		);
	}
}
