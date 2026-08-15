<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;

/**
 * teda/focus-grid — the six focus areas (SPEC §4 item 3), drawn live from
 * teda_focus_area posts and ordered by the `teda_order` field. Cards are
 * rendered by Blocksy's own [blocksy_posts] shortcode so that column count,
 * image ratio, card style and read-more wording all come from the
 * Customizer's "Focus Areas" post-type settings instead of hardcoded PHP/CSS.
 * The accent-color bar and SVG icon fallback (SPEC §3.1: icon and label
 * always accompany colour) are layered on top via teda-child's
 * blocksy:loop:card:end hook and a post_class filter. The block still
 * declares its own designed empty state.
 */
final class Focus_Grid extends Block_Renderer {

	public function name(): string {
		return 'teda/focus-grid';
	}

	protected function is_empty( array $attributes, WP_Block $block ): bool {
		return array() === Query::get(
			array(
				'post_type'      => 'teda_focus_area',
				'posts_per_page' => 1,
			)
		)->posts;
	}

	protected function render_empty( array $attributes, WP_Block $block ): string {
		return $this->header( $attributes )
			. '<div class="teda-empty"><h3>' . esc_html__( 'Focus areas are on their way', 'teda-core' ) . '</h3>'
			. '<p>' . esc_html__( 'We are publishing our areas of work. Please check back soon.', 'teda-core' ) . '</p></div>';
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$count = $this->int_attr( $attributes, 'count', 6, 1, Query::MAX );

		$shortcode = '[blocksy_posts post_type="teda_focus_area" limit="' . $count
			. '" orderby="meta_value_num" meta_key="teda_order" order="ASC" has_pagination="no" no_results="skip"]';

		return $this->header( $attributes ) . do_shortcode( $shortcode );
	}

	/**
	 * The optional section header (eyebrow + heading + intro).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function header( array $attributes ): string {
		$eyebrow = $this->str_attr( $attributes, 'eyebrow' );
		$heading = $this->str_attr( $attributes, 'heading' );
		$intro   = $this->str_attr( $attributes, 'intro' );

		if ( '' === $eyebrow && '' === $heading && '' === $intro ) {
			return '';
		}

		$out = '<div class="teda-focus-grid__head">';
		if ( '' !== $eyebrow ) {
			$out .= '<span class="teda-eyebrow">' . esc_html( $eyebrow ) . '</span>';
		}
		if ( '' !== $heading ) {
			$out .= '<h2 class="teda-display">' . esc_html( $heading ) . '</h2>';
		}
		if ( '' !== $intro ) {
			$out .= '<p class="teda-focus-grid__intro">' . esc_html( $intro ) . '</p>';
		}
		$out .= '</div>';

		return $out;
	}

}
