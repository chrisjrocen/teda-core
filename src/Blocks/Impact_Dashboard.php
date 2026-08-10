<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;

/**
 * teda/impact-dashboard — funder-facing headline figures (SPEC §13 steps 18–20, D13).
 *
 * The people reading this are deciding whether to fund TEDA, so every number must
 * trace to a published document and none may be an estimate. Honesty by construction,
 * exactly like teda/stat-band: a figure renders ONLY when it is ticked `verified` AND
 * has a value and a label; unverified data never reaches the page. Each rendered
 * figure links to its source publication. Below the minimum count, the whole grid is
 * replaced by an honest fallback statement rather than a thin or unbacked row.
 */
final class Impact_Dashboard extends Block_Renderer {

	private const SLOTS = 6;

	public function name(): string {
		return 'teda/impact-dashboard';
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$figures = $this->verified_figures( $attributes );
		$min     = $this->int_attr( $attributes, 'min_to_render', 1, 1, self::SLOTS );

		$out = $this->header( $attributes );

		if ( count( $figures ) < $min ) {
			return $out . $this->render_fallback( $attributes );
		}

		$cells = '';
		foreach ( $figures as $fig ) {
			$cells .= $this->cell( $fig );
		}

		return $out . '<div class="teda-impact__grid" role="list">' . $cells . '</div>';
	}

	/**
	 * One figure cell. The value + label, then a link to the source document — the
	 * link is what makes the figure checkable (D13).
	 *
	 * @param array{value:string,label:string,source:string,source_label:string} $fig
	 */
	private function cell( array $fig ): string {
		$cell = '<div class="teda-impact__cell" role="listitem">'
			. '<b class="teda-display teda-impact__value">' . esc_html( $fig['value'] ) . '</b>'
			. '<span class="teda-impact__label">' . esc_html( $fig['label'] ) . '</span>';

		if ( '' !== $fig['source'] ) {
			$label = '' !== $fig['source_label'] ? $fig['source_label'] : __( 'Source', 'teda-core' );
			$cell .= '<a class="teda-impact__source" href="' . esc_url( $fig['source'] ) . '">'
				. esc_html( $label )
				. '<span class="screen-reader-text"> ' . esc_html__( '(source document for this figure)', 'teda-core' ) . '</span></a>';
		}

		$cell .= '</div>';

		return $cell;
	}

	/**
	 * The honest fallback when there are too few verified figures. Never an empty or
	 * unbacked grid (SPEC §13). This is why the block declares no separate empty state.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function render_fallback( array $attributes ): string {
		$text = $this->str_attr( $attributes, 'fallback' );
		if ( '' === $text ) {
			return '';
		}
		return '<p class="teda-impact__fallback">' . esc_html( $text ) . '</p>';
	}

	/**
	 * The optional header (eyebrow, heading, intro).
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

		$out = '<div class="teda-impact__head">';
		if ( '' !== $eyebrow ) {
			$out .= '<span class="teda-eyebrow">' . esc_html( $eyebrow ) . '</span>';
		}
		if ( '' !== $heading ) {
			$out .= '<h2 class="teda-display">' . esc_html( $heading ) . '</h2>';
		}
		if ( '' !== $intro ) {
			$out .= '<p class="teda-impact__intro">' . esc_html( $intro ) . '</p>';
		}
		return $out . '</div>';
	}

	/**
	 * The verified figures, in slot order. A figure counts only when ticked verified
	 * AND it has both a value and a label (D13).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<int, array{value:string,label:string,source:string,source_label:string}>
	 */
	private function verified_figures( array $attributes ): array {
		$figures = array();

		for ( $n = 1; $n <= self::SLOTS; $n++ ) {
			if ( ! $this->bool_attr( $attributes, "f{$n}_verified" ) ) {
				continue;
			}
			$value = $this->str_attr( $attributes, "f{$n}_value" );
			$label = $this->str_attr( $attributes, "f{$n}_label" );
			if ( '' === $value || '' === $label ) {
				continue;
			}
			$figures[] = array(
				'value'        => $value,
				'label'        => $label,
				'source'       => $this->str_attr( $attributes, "f{$n}_source" ),
				'source_label' => $this->str_attr( $attributes, "f{$n}_source_label" ),
			);
		}

		return $figures;
	}
}
