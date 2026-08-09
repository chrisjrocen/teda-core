<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;

/**
 * teda/impact-tiers — donation tiers (SPEC §7). Each tier is an amount, a currency
 * approximation and a description of what it buys, with an optional progress bar.
 * The bar uses green AND carries a text label plus the percentage, so colour is
 * never the only signal (SPEC §3.1). Authored as flat attributes (up to five).
 */
final class Impact_Tiers extends Block_Renderer {

	private const SLOTS = 5;

	public function name(): string {
		return 'teda/impact-tiers';
	}

	protected function is_empty( array $attributes, WP_Block $block ): bool {
		return array() === $this->tiers( $attributes );
	}

	protected function render_empty( array $attributes, WP_Block $block ): string {
		return '';
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$out = $this->header( $attributes );
		$out .= '<div class="teda-impact-tiers__list">';
		foreach ( $this->tiers( $attributes ) as $tier ) {
			$out .= $this->tier( $tier );
		}
		return $out . '</div>';
	}

	/**
	 * One tier row.
	 *
	 * @param array{amount:string, usd:string, desc:string, progress:int, progress_label:string} $tier Tier.
	 */
	private function tier( array $tier ): string {
		$out = '<div class="teda-tier">';
		$out .= '<b class="teda-tier__amount">' . esc_html( $tier['amount'] );
		if ( '' !== $tier['usd'] ) {
			$out .= '<small>' . esc_html( $tier['usd'] ) . '</small>';
		}
		$out .= '</b>';

		$out .= '<div class="teda-tier__body">';
		if ( '' !== $tier['desc'] ) {
			$out .= '<p class="teda-tier__desc">' . esc_html( $tier['desc'] ) . '</p>';
		}
		$out .= $this->progress( $tier );
		$out .= '</div></div>';

		return $out;
	}

	/**
	 * The optional progress bar. Rendered only when there is a label or a non-zero
	 * value; the label text always states the value so it reads without colour.
	 *
	 * @param array{progress:int, progress_label:string} $tier Tier.
	 */
	private function progress( array $tier ): string {
		$pct   = max( 0, min( 100, $tier['progress'] ) );
		$label = $tier['progress_label'];
		if ( '' === $label && 0 === $pct ) {
			return '';
		}

		$text = '' !== $label
			? sprintf(
				/* translators: 1: caption e.g. "34 of 50 funded", 2: percent. */
				__( '%1$s (%2$d%%)', 'teda-core' ),
				$label,
				$pct
			)
			: sprintf( /* translators: %d: percent funded. */ __( '%d%% funded', 'teda-core' ), $pct );

		return '<div class="teda-progress">'
			. '<div class="teda-progress__label">' . esc_html( $text ) . '</div>'
			. '<div class="teda-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( (string) $pct ) . '" aria-label="' . esc_attr( '' !== $label ? $label : __( 'Funding progress', 'teda-core' ) ) . '">'
			. '<div class="teda-progress__fill" style="width:' . esc_attr( (string) $pct ) . '%"></div>'
			. '</div></div>';
	}

	/**
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function header( array $attributes ): string {
		if ( ! $this->bool_attr( $attributes, 'show_header', true ) ) {
			return '';
		}
		$eyebrow = $this->str_attr( $attributes, 'eyebrow' );
		$heading = $this->str_attr( $attributes, 'heading' );
		if ( '' === $eyebrow && '' === $heading ) {
			return '';
		}
		$out = '<div class="teda-impact-tiers__head">';
		if ( '' !== $eyebrow ) {
			$out .= '<span class="teda-eyebrow">' . esc_html( $eyebrow ) . '</span>';
		}
		if ( '' !== $heading ) {
			$out .= '<h2 class="teda-display">' . esc_html( $heading ) . '</h2>';
		}
		return $out . '</div>';
	}

	/**
	 * Present tiers (those with an amount and a description).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<int, array{amount:string, usd:string, desc:string, progress:int, progress_label:string}>
	 */
	private function tiers( array $attributes ): array {
		$tiers = array();
		for ( $n = 1; $n <= self::SLOTS; $n++ ) {
			$amount = $this->str_attr( $attributes, "tier{$n}_amount" );
			$desc   = $this->str_attr( $attributes, "tier{$n}_desc" );
			if ( '' === $amount || '' === $desc ) {
				continue;
			}
			$tiers[] = array(
				'amount'         => $amount,
				'usd'            => $this->str_attr( $attributes, "tier{$n}_usd" ),
				'desc'           => $desc,
				'progress'       => $this->int_attr( $attributes, "tier{$n}_progress", 0, 0, 100 ),
				'progress_label' => $this->str_attr( $attributes, "tier{$n}_progress_label" ),
			);
		}

		return $tiers;
	}
}
