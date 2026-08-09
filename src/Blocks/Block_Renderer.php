<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;

/**
 * Base for every TEDA dynamic block (D4). Provides:
 *  - a render() contract that wraps output with get_block_wrapper_attributes();
 *  - the empty-state contract (house rule 8): is_empty() + render_empty(), so
 *    every block declares what it shows with no data;
 *  - escaping helpers;
 *  - an optional front-end render cache keyed on the queried post type's
 *    last_changed, so archives-in-blocks don't run unbounded queries.
 *
 * Markup lives here (semantic first, works with the child theme inactive);
 * presentation lives in teda-child CSS (D2).
 */
abstract class Block_Renderer {

	/**
	 * Fully-qualified block name, e.g. 'teda/hero'.
	 */
	abstract public function name(): string;

	/**
	 * Render the block's content when it HAS data. Return inner HTML only — the
	 * base wraps it. Must be safe with the child theme inactive.
	 *
	 * @param array<string, mixed> $attributes Parsed attributes (block.json defaults applied by WP).
	 */
	abstract protected function render_content( array $attributes, string $content, WP_Block $block ): string;

	/**
	 * Whether the block has no data to show. Default: never empty. Blocks that can
	 * be empty (events, news, …) override this together with render_empty().
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	protected function is_empty( array $attributes, WP_Block $block ): bool {
		return false;
	}

	/**
	 * The designed empty state (house rule 8). Default: render nothing. Override
	 * to return a heading + sentence + action.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	protected function render_empty( array $attributes, WP_Block $block ): string {
		return '';
	}

	/**
	 * The render_callback WP calls. Wraps content or the empty state uniformly.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $content    Inner block content.
	 * @param WP_Block             $block      Block instance.
	 */
	public function render( $attributes, $content, $block ): string {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$content    = is_string( $content ) ? $content : '';

		$is_empty = $this->is_empty( $attributes, $block );
		$inner    = $is_empty
			? $this->render_empty( $attributes, $block )
			: $this->render_content( $attributes, $content, $block );

		if ( '' === trim( $inner ) ) {
			return '';
		}

		$classes = array( 'teda-block', 'teda-' . $this->slug() );
		if ( $is_empty ) {
			$classes[] = 'teda-block--empty';
		}

		$wrapper = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );

		return '<div ' . $wrapper . '>' . $inner . '</div>';
	}

	/**
	 * The last path segment of the block name, e.g. 'hero'.
	 */
	protected function slug(): string {
		$parts = explode( '/', $this->name() );
		return end( $parts );
	}

	/* --------------------------------------------------------------------- */
	/* Helpers                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Coerce an attribute to a bounded int.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	protected function int_attr( array $attributes, string $key, int $default, int $min, int $max ): int {
		$value = isset( $attributes[ $key ] ) ? (int) $attributes[ $key ] : $default;
		return max( $min, min( $max, $value ) );
	}

	/**
	 * Coerce an attribute to a trimmed string.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	protected function str_attr( array $attributes, string $key, string $default = '' ): string {
		return isset( $attributes[ $key ] ) && is_string( $attributes[ $key ] ) ? trim( $attributes[ $key ] ) : $default;
	}

	/**
	 * Coerce an attribute to bool.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	protected function bool_attr( array $attributes, string $key, bool $default = false ): bool {
		return isset( $attributes[ $key ] ) ? (bool) $attributes[ $key ] : $default;
	}

	/**
	 * Cache a front-end render keyed on the given post type's last_changed, so a
	 * block that queries posts recomputes only when content changes (also see
	 * Registry's save_post flush). Never caches in the editor / REST preview.
	 *
	 * @param string   $post_type Post type whose last_changed keys the cache.
	 * @param string   $key       Stable key for this render (attrs hash, etc.).
	 * @param callable $render    Produces the HTML.
	 */
	protected function remember( string $post_type, string $key, callable $render ): string {
		$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
		if ( is_admin() || $is_rest || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return (string) $render();
		}

		$last      = wp_cache_get_last_changed( 'posts' );
		$cache_key = 'teda_block_' . md5( $this->name() . '|' . $post_type . '|' . $key . '|' . $last );

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (string) $cached;
		}

		$html = (string) $render();
		set_transient( $cache_key, $html, HOUR_IN_SECONDS );
		return $html;
	}
}
