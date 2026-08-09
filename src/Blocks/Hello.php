<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;

/**
 * teda/hello — the foundation smoke-test block (P07). Trivial by design: it
 * proves registration, category, SSR preview, attribute coercion and the
 * wrapper. Renders semantic markup that reads fine with the child theme inactive.
 */
final class Hello extends Block_Renderer {

	public function name(): string {
		return 'teda/hello';
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$name = $this->str_attr( $attributes, 'name', 'Teso' );

		return sprintf(
			'<p class="teda-hello">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: a place or name. */
					__( 'Hello, %s — TEDA blocks are live.', 'teda-core' ),
					$name
				)
			)
		);
	}
}
