<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\PostTypes;

/**
 * News uses the native `post` type with a fixed set of programme categories
 * (SPEC §5.1) — no News CPT. This seeds the six categories idempotently on
 * activation and on version upgrade, so a fresh install has them without a
 * volunteer creating them by hand.
 */
final class News_Categories {

	/**
	 * The six programme categories, in display order.
	 *
	 * @var array<int, string>
	 */
	private const CATEGORIES = array(
		'Education',
		'Climate',
		'Health',
		'Entrepreneurship',
		'Leadership',
		'Culture',
	);

	/**
	 * Ensure each category exists. Safe to run repeatedly.
	 */
	public static function seed(): void {
		foreach ( self::CATEGORIES as $name ) {
			if ( ! term_exists( $name, 'category' ) ) {
				wp_insert_term( $name, 'category' );
			}
		}
	}
}
