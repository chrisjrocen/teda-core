<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Query;

/**
 * The one bounded query helper every block uses (P07 task 6). Enforces a hard cap
 * of 24, `no_found_rows` unless the caller needs pagination, an explicit
 * posts_per_page, and ignore_sticky_posts — so no block can accidentally run an
 * unbounded or expensive query.
 */
final class Query {

	/**
	 * Hard cap on how many posts any block may request.
	 */
	public const MAX = 24;

	/**
	 * Run a bounded query.
	 *
	 * @param array<string, mixed> $args WP_Query args. posts_per_page is capped
	 *                                   at MAX; -1 (all) is treated as MAX.
	 */
	public static function get( array $args ): WP_Query {
		$defaults = array(
			'post_status'         => 'publish',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true, // Callers needing pagination set false.
			'posts_per_page'      => 6,
		);

		$args = array_merge( $defaults, $args );

		$requested = (int) $args['posts_per_page'];
		if ( $requested < 0 ) {
			$requested = self::MAX; // -1 means "all" — still capped.
		}
		$args['posts_per_page'] = min( $requested, self::MAX );

		return new WP_Query( $args );
	}
}
