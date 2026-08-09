<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;

/**
 * teda/testimonials — a carousel of member voices (SPEC §8.3). Authored as flat
 * attributes (up to six), each a quote + a FIRST NAME and district only — never a
 * full name beside an identifiable person. Keyboard-operable with no autoplay
 * (teda-child blocks-b.js); the first slide shows with JS disabled.
 */
final class Testimonials extends Block_Renderer {

	private const SLOTS = 6;

	public function name(): string {
		return 'teda/testimonials';
	}

	protected function is_empty( array $attributes, WP_Block $block ): bool {
		return array() === $this->items( $attributes );
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$items = $this->items( $attributes );
		$count = count( $items );

		$out = $this->header( $attributes );
		$out .= '<div class="teda-carousel teda-testimonials__carousel">';
		$out .= '<div class="teda-carousel__track" role="group" aria-live="polite">';

		foreach ( $items as $idx => $item ) {
			$out .= $this->slide( $item, $idx, $count );
		}

		$out .= '</div>';

		if ( $count > 1 ) {
			$out .= $this->controls( $count );
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * One testimonial slide.
	 *
	 * @param array{quote:string, name:string, district:string} $item Item.
	 */
	private function slide( array $item, int $idx, int $count ): string {
		$label = sprintf(
			/* translators: 1: item number, 2: total. */
			__( 'Testimonial %1$d of %2$d', 'teda-core' ),
			$idx + 1,
			$count
		);

		$attribution = $item['name'];
		if ( '' !== $item['district'] ) {
			$attribution = '' !== $attribution ? $attribution . ' — ' . $item['district'] : $item['district'];
		}

		$out = '<div class="teda-carousel__slide" role="tabpanel" aria-label="' . esc_attr( $label ) . '"' . ( 0 === $idx ? '' : ' hidden' ) . '>';
		$out .= '<blockquote class="teda-quote"><p>' . esc_html( $item['quote'] ) . '</p>';
		if ( '' !== $attribution ) {
			$out .= '<cite class="teda-quote__cite">' . esc_html( $attribution ) . '</cite>';
		}
		$out .= '</blockquote></div>';

		return $out;
	}

	/**
	 * Prev/next + a dots tablist.
	 */
	private function controls( int $count ): string {
		$prev = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>';
		$next = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>';

		$dots = '';
		for ( $i = 0; $i < $count; $i++ ) {
			$dots .= sprintf(
				'<button class="teda-carousel__dot" type="button" role="tab" aria-selected="%s" tabindex="%s" aria-label="%s"><i></i></button>',
				0 === $i ? 'true' : 'false',
				0 === $i ? '0' : '-1',
				esc_attr( sprintf( /* translators: %d: item number. */ __( 'Testimonial %d', 'teda-core' ), $i + 1 ) )
			);
		}

		return '<div class="teda-carousel__ctl">'
			. '<button class="teda-carousel__nav" type="button" data-teda-cdir="-1" aria-label="' . esc_attr__( 'Previous testimonial', 'teda-core' ) . '">' . $prev . '</button>'
			. '<button class="teda-carousel__nav" type="button" data-teda-cdir="1" aria-label="' . esc_attr__( 'Next testimonial', 'teda-core' ) . '">' . $next . '</button>'
			. '<div class="teda-carousel__dots" role="tablist" aria-label="' . esc_attr__( 'Choose testimonial', 'teda-core' ) . '">' . $dots . '</div>'
			. '</div>';
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
		$out = '<div class="teda-testimonials__head">';
		if ( '' !== $eyebrow ) {
			$out .= '<span class="teda-eyebrow">' . esc_html( $eyebrow ) . '</span>';
		}
		if ( '' !== $heading ) {
			$out .= '<h2 class="teda-display">' . esc_html( $heading ) . '</h2>';
		}
		return $out . '</div>';
	}

	/**
	 * Present testimonials (those with a quote).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<int, array{quote:string, name:string, district:string}>
	 */
	private function items( array $attributes ): array {
		$items = array();
		for ( $n = 1; $n <= self::SLOTS; $n++ ) {
			$quote = $this->str_attr( $attributes, "t{$n}_quote" );
			if ( '' === $quote ) {
				continue;
			}
			$items[] = array(
				'quote'    => $quote,
				'name'     => $this->str_attr( $attributes, "t{$n}_name" ),
				'district' => $this->str_attr( $attributes, "t{$n}_district" ),
			);
		}

		return $items;
	}
}
