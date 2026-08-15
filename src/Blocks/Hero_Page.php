<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;

/**
 * teda/hero-page — the inner-page hero (breadcrumb + title + optional lead).
 *
 * Decision (P08, recorded in the commit body): the prompt asks to prefer extending
 * Blocksy's page-title hero via `blocksy:hero:element:render`. I read
 * blocksy/inc/components/hero/elements.php: that hero is global theme chrome driven
 * by Customizer hero-element config and rendered above the entry — it cannot carry
 * per-page in-content lead copy, which our templateLock:contentOnly pages (P11) need
 * editors to set inline. So the title + lead live in this in-content block, but the
 * genuinely reusable, stateful part — the breadcrumb trail — is delegated to
 * Blocksy's own \Blocksy\BreadcrumbsBuilder rather than parallel-built here. When
 * the parent is inactive the block degrades to a minimal Home → Title trail (D2).
 */
final class Hero_Page extends Block_Renderer {

	public function name(): string {
		return 'teda/hero-page';
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$variant = $this->str_attr( $attributes, 'variant', 'green' );
		$variant = in_array( $variant, array( 'blue', 'brown' ), true ) ? $variant : 'green';
		$title   = $this->str_attr( $attributes, 'title' );
		if ( '' === $title ) {
			$title = (string) get_the_title();
		}
		$lead = $this->str_attr( $attributes, 'lead' );

		$out = '<div class="teda-hero-page teda-hero-page--' . $variant . '"><div class="teda-hero-page__inner">';

		if ( $this->bool_attr( $attributes, 'show_breadcrumb', true ) ) {
			$out .= $this->breadcrumb( $title );
		}

		if ( '' !== $title ) {
			$out .= '<h1 class="teda-display teda-hero-page__title">' . esc_html( $title ) . '</h1>';
		}

		if ( '' !== $lead ) {
			$out .= '<p class="teda-hero-page__lead">' . esc_html( $lead ) . '</p>';
		}

		$out .= '</div></div>';

		return $out;
	}

	/**
	 * Delegate to Blocksy's breadcrumb builder when present; otherwise a minimal
	 * Home → current-page trail so the block never depends on the parent (D2).
	 */
	private function breadcrumb( string $title ): string {
		if ( class_exists( '\Blocksy\BreadcrumbsBuilder' ) ) {
			$builder = new \Blocksy\BreadcrumbsBuilder();
			$html    = $builder->render( array( 'class' => 'teda-hero-page__crumb' ) );
			if ( is_string( $html ) && '' !== trim( $html ) ) {
				return $html;
			}
		}

		return '<nav class="teda-hero-page__crumb" aria-label="' . esc_attr__( 'Breadcrumb', 'teda-core' ) . '">'
			. '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'teda-core' ) . '</a>'
			. ( '' !== $title ? ' <span aria-hidden="true">›</span> ' . esc_html( $title ) : '' )
			. '</nav>';
	}
}
