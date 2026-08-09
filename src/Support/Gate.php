<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Support;

/**
 * The verified-content gate (D13, SPEC §11). When ON, blocks that surface public
 * claims (news, and anything reading teda_verified) exclude unverified items.
 *
 * The switch is a single option so it can be flipped site-wide at launch; P15's
 * `wp teda verify-gate` owns turning it on once content is confirmed. During the
 * build it stays OFF so unverified migrated content is still visible to editors.
 * Filterable for tests and for per-context overrides.
 */
final class Gate {

	private const OPTION = 'teda_verified_gate';

	/**
	 * Whether the verified gate is currently on.
	 */
	public static function is_on(): bool {
		$on = (bool) get_option( self::OPTION, false );

		/**
		 * Filter the verified-content gate.
		 *
		 * @param bool $on Whether unverified content is hidden from the public.
		 */
		return (bool) apply_filters( 'teda_core/verified_gate', $on );
	}

	/**
	 * A meta_query clause that keeps only verified posts, or an empty array when the
	 * gate is off. Spread into a block's WP_Query meta_query.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function meta_query_clause(): array {
		if ( ! self::is_on() ) {
			return array();
		}

		return array(
			array(
				'key'     => 'teda_verified',
				'value'   => '1',
				'compare' => '=',
			),
		);
	}
}
