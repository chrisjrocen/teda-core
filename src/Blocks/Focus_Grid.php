<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use Teda_Core\Support\Icons;
use WP_Block;
use WP_Post;

/**
 * teda/focus-grid — the six focus areas (SPEC §4 item 3), drawn live from
 * teda_focus_area posts and ordered by the `teda_order` field. Each card carries an
 * icon, title, summary and a palette accent; the icon and label are always present
 * so colour is never the only signal (SPEC §3.1). The grid is balanced at any count
 * (3-up / 2-up / 1-up in teda-child CSS), and declares a designed empty state.
 */
final class Focus_Grid extends Block_Renderer {

	private const ACCENTS = array( 'brown', 'blue', 'green' );

	/**
	 * Per-render memo so is_empty() and render_content() share one query.
	 *
	 * @var array<string, array<int, WP_Post>>
	 */
	private array $cache = array();

	public function name(): string {
		return 'teda/focus-grid';
	}

	protected function is_empty( array $attributes, WP_Block $block ): bool {
		return array() === $this->focus_areas( $attributes );
	}

	protected function render_empty( array $attributes, WP_Block $block ): string {
		return $this->header( $attributes )
			. '<div class="teda-empty"><h3>' . esc_html__( 'Focus areas are on their way', 'teda-core' ) . '</h3>'
			. '<p>' . esc_html__( 'We are publishing our areas of work. Please check back soon.', 'teda-core' ) . '</p></div>';
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$cards = '';
		foreach ( $this->focus_areas( $attributes ) as $post ) {
			$cards .= $this->card( $post );
		}

		return $this->header( $attributes ) . '<div class="teda-focus-grid__grid">' . $cards . '</div>';
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

	/**
	 * One focus-area card.
	 *
	 * The card shows a featured image when the editor has set one; when none is
	 * set it falls back to the SVG icon chosen in the teda_icon field, so the
	 * card never renders without a pictorial element (SPEC §3.1: an icon and a
	 * label always accompany colour).
	 */
	private function card( WP_Post $post ): string {
		$accent  = $this->accent( (string) teda_field( 'teda_accent_color', $post->ID, 'green' ) );
		$summary = (string) teda_field( 'teda_summary', $post->ID, '' );
		if ( '' === $summary ) {
			$summary = wp_strip_all_tags( get_the_excerpt( $post ) );
		}

		$out = '<a class="teda-focus-card teda-focus-card--' . $accent . '" href="' . esc_url( (string) get_permalink( $post ) ) . '">';
		$out .= '<span class="teda-focus-card__bar" aria-hidden="true"></span>';

		// Featured image replaces the SVG icon when set; fall back to the
		// SVG icon (or nothing) when no image is attached.
		$image = $this->card_image( $post );
		if ( '' !== $image ) {
			$out .= '<span class="teda-focus-card__icon teda-focus-card__icon--image">' . $image . '</span>';
		} else {
			$icon = Icons::focus( (string) teda_field( 'teda_icon', $post->ID, '' ) );
			if ( '' !== $icon ) {
				$out .= '<span class="teda-focus-card__icon">' . $icon . '</span>';
			}
		}

		$out .= '<h3 class="teda-focus-card__title">' . esc_html( get_the_title( $post ) ) . '</h3>';
		if ( '' !== $summary ) {
			$out .= '<p class="teda-focus-card__summary">' . esc_html( $summary ) . '</p>';
		}
		$out .= '<span class="teda-focus-card__more">' . esc_html__( 'Learn more →', 'teda-core' ) . '</span>';
		$out .= '</a>';

		return $out;
	}

	/**
	 * The focus area's featured image, sized for the full-width card banner
	 * (140px tall, ~2.4:1) with a 2× fallback for high-DPI screens. Returns ''
	 * when no image is set — the caller then renders the SVG icon instead.
	 *
	 * @param WP_Post $post The focus area post.
	 */
	private function card_image( WP_Post $post ): string {
		if ( ! has_post_thumbnail( $post->ID ) ) {
			return '';
		}

		$img = wp_get_attachment_image(
			(int) get_post_thumbnail_id( $post->ID ),
			array( 672, 280 ),
			false,
			array(
				'class'   => 'teda-focus-card__img',
				'alt'     => '',
				'loading' => 'lazy',
			)
		);

		return is_string( $img ) ? $img : '';
	}

	/**
	 * Fetch (and memoise) the focus areas, ordered by teda_order then title. Posts
	 * missing the order field sort last so nothing is silently dropped.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<int, WP_Post>
	 */
	private function focus_areas( array $attributes ): array {
		$count = $this->int_attr( $attributes, 'count', 6, 1, Query::MAX );
		$key   = $count . '|' . wp_cache_get_last_changed( 'posts' );
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$query = Query::get(
			array(
				'post_type'      => 'teda_focus_area',
				'posts_per_page' => $count,
			)
		);

		$posts = $query->posts;
		usort(
			$posts,
			static function ( WP_Post $a, WP_Post $b ): int {
				$oa = (int) teda_field( 'teda_order', $a->ID, 999 );
				$ob = (int) teda_field( 'teda_order', $b->ID, 999 );
				if ( $oa === $ob ) {
					return strcasecmp( get_the_title( $a ), get_the_title( $b ) );
				}
				return $oa <=> $ob;
			}
		);

		$this->cache[ $key ] = $posts;

		return $posts;
	}

	/**
	 * Constrain an accent to the three brand colours.
	 */
	private function accent( string $value ): string {
		return in_array( $value, self::ACCENTS, true ) ? $value : 'green';
	}
}
