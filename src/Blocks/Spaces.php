<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use Teda_Core\Support\Empty_State;
use WP_Block;
use WP_Post;

/**
 * teda/spaces — the homepage Spaces section (SPEC §5.1, §10.3). Lists recent
 * published Spaces newest first, each showing its title, date, topic and a short
 * summary so the section is meaningful to a crawler and a slow connection without
 * any X request (the whole point of the Spaces feature — see Spaces\Presenter).
 *
 * With no Spaces yet it renders the shared "Our first Space is coming" empty state
 * (SPEC §10.1) rather than a gap or a "Loading…" spinner.
 */
final class Spaces extends Block_Renderer {

	/**
	 * Memoised query result keyed by "count|last_changed".
	 *
	 * @var array<string, array<int, WP_Post>>
	 */
	private array $cache = array();

	public function name(): string {
		return 'teda/spaces';
	}

	protected function is_empty( array $attributes, WP_Block $block ): bool {
		return array() === $this->spaces( $attributes );
	}

	protected function render_empty( array $attributes, WP_Block $block ): string {
		return $this->header( $attributes ) . Empty_State::render( 'spaces' );
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$out  = $this->header( $attributes );
		$out .= '<div class="teda-spaces__list">';
		foreach ( $this->spaces( $attributes ) as $post ) {
			$out .= $this->card( $post );
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * One Space card: topic tag, title link, date, summary excerpt, a listen link.
	 */
	private function card( WP_Post $post ): string {
		$id      = (int) $post->ID;
		$url     = (string) get_permalink( $post );
		$date    = teda_field_timestamp( 'teda_space_date', $id );
		$summary = trim( (string) teda_field( 'teda_summary', $id, '' ) );
		$topic   = $this->primary_topic( $post );

		$card = '<article class="teda-space-card">';

		if ( null !== $topic ) {
			$card .= '<span class="teda-tag">' . esc_html( $topic ) . '</span>';
		}

		$card .= '<h3 class="teda-space-card__title"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>';

		if ( null !== $date ) {
			$card .= '<p class="teda-space-card__date">' . esc_html( wp_date( (string) get_option( 'date_format', 'j F Y' ), $date ) ) . '</p>';
		}

		if ( '' !== $summary ) {
			$card .= '<p class="teda-space-card__summary">' . esc_html( wp_trim_words( $summary, 28 ) ) . '</p>';
		}

		$card .= '<a class="teda-space-card__go" href="' . esc_url( $url ) . '">'
			. esc_html__( 'Read the summary', 'teda-core' )
			. '<span class="screen-reader-text"> ' . esc_html( get_the_title( $post ) ) . '</span></a>';

		$card .= '</article>';

		return $card;
	}

	/**
	 * The Space's first space_topic term name, or null.
	 */
	private function primary_topic( WP_Post $post ): ?string {
		$terms = get_the_terms( $post, 'space_topic' );
		if ( ! is_array( $terms ) || array() === $terms ) {
			return null;
		}
		return $terms[0]->name;
	}

	/**
	 * The optional section header.
	 *
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

		$out = '<div class="teda-spaces__head">';
		if ( '' !== $eyebrow ) {
			$out .= '<span class="teda-eyebrow">' . esc_html( $eyebrow ) . '</span>';
		}
		if ( '' !== $heading ) {
			$out .= '<h2 class="teda-display">' . esc_html( $heading ) . '</h2>';
		}
		return $out . '</div>';
	}

	/**
	 * Recent published Spaces, newest first. Orders by post date (robust — a Space
	 * without the optional date meta is still listed, unlike a meta_value sort).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<int, WP_Post>
	 */
	private function spaces( array $attributes ): array {
		$count = $this->int_attr( $attributes, 'count', 3, 1, Query::MAX );
		$key   = $count . '|' . wp_cache_get_last_changed( 'posts' );
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$this->cache[ $key ] = Query::get(
			array(
				'post_type'      => 'teda_space',
				'posts_per_page' => $count,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		)->posts;

		return $this->cache[ $key ];
	}
}
