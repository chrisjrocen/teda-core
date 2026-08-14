<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;

/**
 * teda/stat-band — four headline figures (SPEC §4 item 2, §11, D13).
 *
 * Honesty-by-construction: only figures ticked `verified` are rendered. If fewer
 * than three verified figures exist, the band is replaced with a single qualitative
 * statement ("Working across four districts of Teso") rather than showing a thin,
 * lopsided band. When any figure shows, the method note is appended. Numbers use a
 * tabular display face (teda-child CSS).
 *
 * Each numeric value carries data-teda-stat-count + data-teda-stat-suffix
 * attributes so the child theme's JS can animate the figure upward from zero.
 */
final class Stat_Band extends Block_Renderer {

	private const SLOTS         = 4;
	private const MIN_TO_RENDER = 3;

	public function name(): string {
		return 'teda/stat-band';
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$stats = $this->verified_stats( $attributes );

		if ( count( $stats ) < self::MIN_TO_RENDER ) {
			return $this->render_qualitative( $attributes );
		}

		$cells = '';
		foreach ( $stats as $stat ) {
			$value = $stat['value'];
			// Parse "500+" → count=500, suffix="+" so JS can animate the number
			// and re-append the suffix. Non-numeric values get no data attrs and
			// render statically.
			$count = $this->stat_count( $value );
			$suffix = $this->stat_suffix( $value );

			$attrs = '';
			if ( null !== $count ) {
				$attrs = ' data-teda-stat-count="' . esc_attr( (string) $count ) . '"';
				if ( '' !== $suffix ) {
					$attrs .= ' data-teda-stat-suffix="' . esc_attr( $suffix ) . '"';
				}
			}

			$cells .= '<div class="teda-stat">'
				. '<b class="teda-display teda-stat__value"' . $attrs . '>' . esc_html( $value ) . '</b>'
				. '<span class="teda-stat__label">' . esc_html( $stat['label'] ) . '</span>'
				. '</div>';
		}

		$out = '<div class="teda-stat-band__row" role="list">' . $cells . '</div>';
		$out .= $this->render_note( $attributes );

		return $out;
	}

	/**
	 * The qualitative fallback (SPEC §11) — always something honest, never an empty
	 * or thin band. This is why the block declares no separate empty state.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function render_qualitative( array $attributes ): string {
		$text = $this->str_attr( $attributes, 'fallback', 'Working across four districts of Teso' );
		if ( '' === $text ) {
			return '';
		}

		return '<p class="teda-stat-band__qualitative">' . esc_html( $text ) . '</p>';
	}

	/**
	 * The method note, shown only when figures are (SPEC §11).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function render_note( array $attributes ): string {
		$as_of = $this->str_attr( $attributes, 'as_of' );
		$text  = '' !== $as_of
			? sprintf(
				/* translators: %s: a month and year, e.g. "July 2026". */
				__( 'Figures as at %s, based on programme records.', 'teda-core' ),
				$as_of
			)
			: __( 'Figures based on programme records.', 'teda-core' );

		return '<p class="teda-stat-band__note">' . esc_html( $text ) . '</p>';
	}

	/**
	 * The verified figures, in order. A figure counts only when it is ticked
	 * verified AND has both a value and a label.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<int, array{value: string, label: string}>
	 */
	private function verified_stats( array $attributes ): array {
		$stats = array();

		for ( $n = 1; $n <= self::SLOTS; $n++ ) {
			if ( ! $this->bool_attr( $attributes, "s{$n}_verified" ) ) {
				continue;
			}
			$value = $this->str_attr( $attributes, "s{$n}_value" );
			$label = $this->str_attr( $attributes, "s{$n}_label" );
			if ( '' === $value || '' === $label ) {
				continue;
			}
			$stats[] = array(
				'value' => $value,
				'label' => $label,
			);
		}

		return $stats;
	}

	/**
	 * Extract the numeric portion of a stat value (e.g. "500+" → 500),
	 * stripping commas. Returns null when the value has no leading digits
	 * (e.g. "N/A"), in which case the stat renders statically.
	 *
	 * @param string $value The raw stat value from attributes.
	 */
	private function stat_count( string $value ): ?int {
		if ( preg_match( '/^([\d,]+)/', $value, $m ) ) {
			return (int) str_replace( ',', '', $m[1] );
		}
		return null;
	}

	/**
	 * The non-numeric suffix of a stat value (e.g. "500+" → "+").
	 * Returns '' when there is no suffix or no numeric prefix.
	 *
	 * @param string $value The raw stat value from attributes.
	 */
	private function stat_suffix( string $value ): string {
		if ( preg_match( '/^\d[\d,]*(.*)$/', $value, $m ) ) {
			return $m[1];
		}
		return '';
	}
}
