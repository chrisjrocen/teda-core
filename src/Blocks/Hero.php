<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;

/**
 * teda/hero — the homepage hero carousel (C1/C2, SPEC §3.3, §4 item 1).
 *
 * Up to three slides authored as flat attributes (so the shared editor gives each
 * an image picker + text fields with no per-block JS). Rendering rules that keep
 * the C2 budget:
 *  - slide 1 is eager with fetchpriority="high"; slides 2–3 are loading="lazy";
 *  - no autoplay — reduced-motion is satisfied by construction (house rule 10);
 *  - one slide is valid and renders with no controls.
 * The slider behaviour + keyboard live in teda-child/assets/js/hero.js; the markup
 * shows slide 1 correctly even with JS disabled (it carries the is-on class).
 */
final class Hero extends Block_Renderer {

	private const SLIDES = 3;

	public function name(): string {
		return 'teda/hero';
	}

	protected function is_empty( array $attributes, WP_Block $block ): bool {
		return array() === $this->slides( $attributes );
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$slides = $this->slides( $attributes );
		$count  = count( $slides );

		$html = '<div class="teda-hero__slider" role="group" aria-roledescription="carousel" aria-label="'
			. esc_attr__( 'TEDA highlights', 'teda-core' ) . '">';

		foreach ( $slides as $idx => $slide ) {
			$html .= $this->render_slide( $slide, $idx, $count );
		}

		if ( $count > 1 ) {
			$html .= $this->render_controls( $count );
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * One slide.
	 *
	 * @param array<string, mixed> $slide Normalised slide data.
	 */
	private function render_slide( array $slide, int $idx, int $count ): string {
		$first  = 0 === $idx;
		$label  = sprintf(
			/* translators: 1: slide number, 2: total slides. */
			__( 'Slide %1$d of %2$d', 'teda-core' ),
			$idx + 1,
			$count
		);

		$out = '<div class="teda-hero__slide' . ( $first ? ' is-on' : '' ) . '"'
			. ' role="group" aria-roledescription="slide"'
			. ' aria-hidden="' . ( $first ? 'false' : 'true' ) . '"'
			. ' aria-label="' . esc_attr( $label ) . '">';

		$out .= $this->render_image( (int) $slide['image'], $first, (string) $slide['heading'] );

		$out .= '<div class="teda-hero__shell">';

		if ( '' !== $slide['eyebrow'] ) {
			$out .= '<span class="teda-hero__eyebrow">' . esc_html( $slide['eyebrow'] ) . '</span>';
		}

		if ( '' !== $slide['heading'] ) {
			$tag  = $first ? 'h1' : 'p';
			$out .= '<' . $tag . ' class="teda-display teda-hero__title">' . esc_html( $slide['heading'] ) . '</' . $tag . '>';
		}

		if ( '' !== $slide['body'] ) {
			$out .= '<p class="teda-hero__body">' . esc_html( $slide['body'] ) . '</p>';
		}

		$out .= $this->render_acts( $slide );
		$out .= '</div></div>';

		return $out;
	}

	/**
	 * The slide background image. Slide 1 loads eager + high priority (C2); the rest
	 * are lazy. wp_get_attachment_image emits width/height so there is no layout
	 * shift. With no image the brown slider background shows through.
	 */
	private function render_image( int $id, bool $first, string $heading ): string {
		if ( $id <= 0 ) {
			return '';
		}

		$attr = array(
			'class'    => 'teda-hero__bg',
			'decoding' => 'async',
			'loading'  => $first ? 'eager' : 'lazy',
			// The hero is a full-bleed background behind a dark scrim, so it does not
			// need a pixel-perfect image at high DPR. Bias the responsive pick towards
			// a lighter breakpoint (P17, metered-data audience / LCP budget).
			'sizes'    => '(max-width: 782px) 75vw, 100vw',
		);
		if ( $first ) {
			$attr['fetchpriority'] = 'high';
		}
		// Fall back to the heading for alt text only when the media has none.
		if ( '' !== $heading ) {
			$attr['alt'] = $heading;
		}

		$img = wp_get_attachment_image( $id, 'full', false, $attr );

		return is_string( $img ) ? $img : '';
	}

	/**
	 * Up to two calls to action for a slide.
	 *
	 * @param array<string, mixed> $slide Slide data.
	 */
	private function render_acts( array $slide ): string {
		$buttons = '';

		if ( '' !== $slide['cta_label'] ) {
			$buttons .= sprintf(
				'<a class="teda-btn teda-btn--white teda-btn--lg" href="%s">%s</a>',
				esc_url( '' !== $slide['cta_url'] ? $slide['cta_url'] : '#' ),
				esc_html( $slide['cta_label'] )
			);
		}
		if ( '' !== $slide['cta2_label'] ) {
			$buttons .= sprintf(
				'<a class="teda-btn teda-btn--ghost teda-btn--lg" href="%s">%s</a>',
				esc_url( '' !== $slide['cta2_url'] ? $slide['cta2_url'] : '#' ),
				esc_html( $slide['cta2_label'] )
			);
		}

		return '' === $buttons ? '' : '<div class="teda-hero__acts">' . $buttons . '</div>';
	}

	/**
	 * Prev/next + a dots tablist. Only rendered for a multi-slide hero.
	 */
	private function render_controls( int $count ): string {
		$prev = '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>';
		$next = '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>';

		$dots = '';
		for ( $i = 0; $i < $count; $i++ ) {
			$dots .= sprintf(
				'<button class="teda-hero__dot" type="button" role="tab" aria-selected="%s" tabindex="%s" aria-label="%s"><i></i></button>',
				0 === $i ? 'true' : 'false',
				0 === $i ? '0' : '-1',
				esc_attr( sprintf( /* translators: %d: slide number. */ __( 'Slide %d', 'teda-core' ), $i + 1 ) )
			);
		}

		return '<div class="teda-hero__ctl"><div class="teda-hero__ctl-inner">'
			. '<button class="teda-hero__nav" type="button" data-teda-hdir="-1" aria-label="' . esc_attr__( 'Previous slide', 'teda-core' ) . '">' . $prev . '</button>'
			. '<button class="teda-hero__nav" type="button" data-teda-hdir="1" aria-label="' . esc_attr__( 'Next slide', 'teda-core' ) . '">' . $next . '</button>'
			. '<div class="teda-hero__dots" role="tablist" aria-label="' . esc_attr__( 'Choose slide', 'teda-core' ) . '">' . $dots . '</div>'
			. '</div></div>';
	}

	/**
	 * Normalise the flat s{n}_* attributes into a list of present slides. A slide
	 * counts as present when it has a heading, an image or body copy.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<int, array<string, mixed>>
	 */
	private function slides( array $attributes ): array {
		$slides = array();

		for ( $n = 1; $n <= self::SLIDES; $n++ ) {
			$slide = array(
				'image'      => $this->int_attr( $attributes, "s{$n}_image", 0, 0, PHP_INT_MAX ),
				'eyebrow'    => $this->str_attr( $attributes, "s{$n}_eyebrow" ),
				'heading'    => $this->str_attr( $attributes, "s{$n}_heading" ),
				'body'       => $this->str_attr( $attributes, "s{$n}_body" ),
				'cta_label'  => $this->str_attr( $attributes, "s{$n}_cta_label" ),
				'cta_url'    => $this->str_attr( $attributes, "s{$n}_cta_url" ),
				'cta2_label' => $this->str_attr( $attributes, "s{$n}_cta2_label" ),
				'cta2_url'   => $this->str_attr( $attributes, "s{$n}_cta2_url" ),
			);

			if ( '' !== $slide['heading'] || $slide['image'] > 0 || '' !== $slide['body'] ) {
				$slides[] = $slide;
			}
		}

		return $slides;
	}
}
