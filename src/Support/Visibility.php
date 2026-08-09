<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Support;

/**
 * "Does this post type have anything published yet?"
 *
 * SPEC §10.1: do not add a menu item — or a sitemap entry — for a section with
 * no content. Spaces and Publications register from the start (P02) but stay out
 * of navigation (P06) and the sitemap (P16) until they hold a published post.
 * This is the single source of truth for that question.
 */
final class Visibility {

	/**
	 * Per-request cache keyed by post type.
	 *
	 * @var array<string, bool>
	 */
	private static array $cache = array();

	/**
	 * Whether the given post type has at least one published post.
	 */
	public static function has_published( string $post_type ): bool {
		if ( isset( self::$cache[ $post_type ] ) ) {
			return self::$cache[ $post_type ];
		}

		if ( ! post_type_exists( $post_type ) ) {
			return self::$cache[ $post_type ] = false;
		}

		$counts = wp_count_posts( $post_type );
		$result = isset( $counts->publish ) && (int) $counts->publish > 0;

		return self::$cache[ $post_type ] = $result;
	}

	/**
	 * Clear the cache (used after publishing in tests, or on save_post).
	 */
	public static function flush(): void {
		self::$cache = array();
	}
}
