<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;

/**
 * teda/cta-band — a colour-blocked call-to-action band (SPEC §4 item 9). Heading,
 * a sentence and up to two buttons, in one of three brand variants. Button styling
 * flips with the band so contrast holds: light buttons on the dark bands, dark
 * buttons on the sand band (SPEC §3.1).
 */
final class Cta_Band extends Block_Renderer {

	private const VARIANTS = array( 'brown', 'blue', 'sand' );

	public function name(): string {
		return 'teda/cta-band';
	}

	protected function is_empty( array $attributes, WP_Block $block ): bool {
		return '' === $this->str_attr( $attributes, 'heading' )
			&& '' === $this->str_attr( $attributes, 'body' );
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$variant = $this->variant( $this->str_attr( $attributes, 'variant', 'brown' ) );
		$heading = $this->str_attr( $attributes, 'heading' );
		$body    = $this->str_attr( $attributes, 'body' );

		$out = '<div class="teda-cta-band teda-cta-band--' . $variant . '"><div class="teda-cta-band__inner">';

		if ( '' !== $heading ) {
			$out .= '<h2 class="teda-display teda-cta-band__title">' . esc_html( $heading ) . '</h2>';
		}
		if ( '' !== $body ) {
			$out .= '<p class="teda-cta-band__body">' . esc_html( $body ) . '</p>';
		}
		$out .= $this->render_acts( $attributes, $variant );

		$out .= '</div></div>';

		return $out;
	}

	/**
	 * Up to two buttons, styled for contrast against the chosen band.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function render_acts( array $attributes, string $variant ): string {
		$light = 'sand' !== $variant;
		// On dark bands: white fill + ghost-white. On sand: brown fill + ghost-brown.
		$primary   = $light ? 'teda-btn--white' : 'teda-btn--brown';
		$secondary = $light ? 'teda-btn--ghost' : 'teda-btn--ghost-b';

		$buttons = '';
		$label   = $this->str_attr( $attributes, 'cta_label' );
		if ( '' !== $label ) {
			$buttons .= sprintf(
				'<a class="teda-btn teda-btn--lg %s" href="%s">%s</a>',
				$primary,
				esc_url( $this->url( $attributes, 'cta_url' ) ),
				esc_html( $label )
			);
		}
		$label2 = $this->str_attr( $attributes, 'cta2_label' );
		if ( '' !== $label2 ) {
			$buttons .= sprintf(
				'<a class="teda-btn teda-btn--lg %s" href="%s">%s</a>',
				$secondary,
				esc_url( $this->url( $attributes, 'cta2_url' ) ),
				esc_html( $label2 )
			);
		}

		return '' === $buttons ? '' : '<div class="teda-cta-band__acts">' . $buttons . '</div>';
	}

	/**
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function url( array $attributes, string $key ): string {
		$url = $this->str_attr( $attributes, $key );
		return '' !== $url ? $url : '#';
	}

	private function variant( string $value ): string {
		return in_array( $value, self::VARIANTS, true ) ? $value : 'brown';
	}
}
