<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;

/**
 * teda/filter-chips — the reusable client-side filter (SPEC §3.4; matches the
 * prototype's initFilters). It renders a chip per taxonomy term plus an "All" chip,
 * and a hidden empty state. The chips share a `group_key` with a list block below
 * (events, news, …): each list item carries data-teda-cat and the list wrapper
 * data-teda-filtergroup, so teda-child blocks-b.js can show/hide items, keep
 * aria-pressed correct, count matches and reveal the empty state at zero results.
 *
 * No server round-trip and no query — filtering is presentational over already
 * rendered items, so it is fast and works within a page cache.
 */
final class Filter_Chips extends Block_Renderer {

	/**
	 * Taxonomies this control may filter (guards the attribute).
	 */
	private const ALLOWED = array( 'event_type', 'category', 'space_topic', 'pub_type', 'gallery_album' );

	public function name(): string {
		return 'teda/filter-chips';
	}

	protected function is_empty( array $attributes, WP_Block $block ): bool {
		return '' === $this->group( $attributes ) || array() === $this->terms( $attributes );
	}

	protected function render_empty( array $attributes, WP_Block $block ): string {
		return '';
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$group     = $this->group( $attributes );
		$label     = $this->str_attr( $attributes, 'label', __( 'Filter', 'teda-core' ) );
		$all_label = $this->str_attr( $attributes, 'all_label', __( 'All', 'teda-core' ) );

		$chips  = '<button class="teda-chip is-on" type="button" data-teda-f="all" aria-pressed="true">' . esc_html( $all_label ) . '</button>';
		foreach ( $this->terms( $attributes ) as $term ) {
			$chips .= '<button class="teda-chip" type="button" data-teda-f="' . esc_attr( $term->slug ) . '" aria-pressed="false">' . esc_html( $term->name ) . '</button>';
		}

		$out = '<div class="teda-filter-chips" role="group" aria-label="' . esc_attr( $label ) . '" data-teda-filterset="' . esc_attr( $group ) . '">' . $chips . '</div>';

		// The designed empty state, revealed by the script at zero matches.
		$out .= '<div class="teda-empty teda-filter-chips__empty" data-teda-filterempty="' . esc_attr( $group ) . '" hidden>'
			. '<h3>' . esc_html__( 'Nothing of that type', 'teda-core' ) . '</h3>'
			. '<p>' . esc_html__( 'Try another filter to see more.', 'teda-core' ) . '</p></div>';

		return $out;
	}

	/**
	 * The non-empty terms for the chosen taxonomy.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<int, \WP_Term>
	 */
	private function terms( array $attributes ): array {
		$taxonomy = $this->str_attr( $attributes, 'taxonomy', 'event_type' );
		if ( ! in_array( $taxonomy, self::ALLOWED, true ) || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => Query::MAX,
			)
		);

		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function group( array $attributes ): string {
		return $this->str_attr( $attributes, 'group_key' );
	}
}
