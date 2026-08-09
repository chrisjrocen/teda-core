<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use Teda_Core\Support\Gate;
use WP_Block;
use WP_Post;

/**
 * teda/news — the latest posts, three-up (SPEC §4, §10.2). Optional category filter
 * and a "last updated" date. When the verified gate is on (D13), unverified posts
 * are excluded via Gate's meta_query clause. Uses the native `post` type (§5.1).
 */
final class News extends Block_Renderer {

	/**
	 * @var array<string, array<int, WP_Post>>
	 */
	private array $cache = array();

	public function name(): string {
		return 'teda/news';
	}

	protected function is_empty( array $attributes, WP_Block $block ): bool {
		return array() === $this->posts( $attributes );
	}

	protected function render_empty( array $attributes, WP_Block $block ): string {
		return $this->header( $attributes )
			. '<div class="teda-empty"><h3>' . esc_html__( 'No news yet', 'teda-core' ) . '</h3>'
			. '<p>' . esc_html__( 'We publish updates here as our work happens. Please check back soon.', 'teda-core' ) . '</p></div>';
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$updated = $this->bool_attr( $attributes, 'show_updated', true );
		$group   = $this->str_attr( $attributes, 'filter_group' );

		$cards = '';
		foreach ( $this->posts( $attributes ) as $post ) {
			$cards .= $this->card( $post, $updated );
		}

		$open = '' !== $group
			? '<div class="teda-news__grid" data-teda-filtergroup="' . esc_attr( $group ) . '">'
			: '<div class="teda-news__grid">';

		return $this->header( $attributes ) . $open . $cards . '</div>';
	}

	/**
	 * One news card.
	 */
	private function card( WP_Post $post, bool $updated ): string {
		$cat = $this->primary_category( $post );

		$out = '<a class="teda-news-card" href="' . esc_url( (string) get_permalink( $post ) ) . '"'
			. ( null !== $cat ? ' data-teda-cat="' . esc_attr( $cat['slug'] ) . '"' : '' ) . '>';

		$out .= '<span class="teda-news-card__ph">';
		if ( has_post_thumbnail( $post ) ) {
			$out .= get_the_post_thumbnail( $post, 'medium_large', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => esc_attr( get_the_title( $post ) ) ) );
		}
		$out .= '</span>';

		$out .= '<span class="teda-news-card__bd">';
		if ( null !== $cat ) {
			$out .= '<span class="teda-tag">' . esc_html( $cat['name'] ) . '</span>';
		}
		$out .= '<h3 class="teda-news-card__title">' . esc_html( get_the_title( $post ) ) . '</h3>';
		$out .= '<span class="teda-news-card__meta">' . esc_html( $this->meta( $post, $updated ) ) . '</span>';
		$out .= '</span></a>';

		return $out;
	}

	/**
	 * "June 2026 · Author", with an "Updated <date>" prefix when the post was edited
	 * meaningfully after publishing (SPEC §10.2 last-updated).
	 */
	private function meta( WP_Post $post, bool $updated ): string {
		$published = (int) get_post_timestamp( $post, 'published' );
		$modified  = (int) get_post_timestamp( $post, 'modified' );
		$author    = get_the_author_meta( 'display_name', (int) $post->post_author );

		$date = wp_date( 'F Y', $published );
		if ( $updated && $modified - $published > DAY_IN_SECONDS ) {
			$date = sprintf(
				/* translators: %s: a month and year. */
				__( 'Updated %s', 'teda-core' ),
				wp_date( 'F Y', $modified )
			);
		}

		return '' !== $author ? $date . ' · ' . $author : $date;
	}

	/**
	 * @return array{slug: string, name: string}|null
	 */
	private function primary_category( WP_Post $post ): ?array {
		$cats = get_the_category( $post->ID );
		if ( array() === $cats ) {
			return null;
		}
		return array( 'slug' => $cats[0]->slug, 'name' => $cats[0]->name );
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
		$out = '<div class="teda-news__head">';
		if ( '' !== $eyebrow ) {
			$out .= '<span class="teda-eyebrow">' . esc_html( $eyebrow ) . '</span>';
		}
		if ( '' !== $heading ) {
			$out .= '<h2 class="teda-display">' . esc_html( $heading ) . '</h2>';
		}
		return $out . '</div>';
	}

	/**
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<int, WP_Post>
	 */
	private function posts( array $attributes ): array {
		$count = $this->int_attr( $attributes, 'count', 3, 1, Query::MAX );
		$cat   = $this->str_attr( $attributes, 'category' );
		$key   = $count . '|' . $cat . '|' . ( Gate::is_on() ? '1' : '0' ) . '|' . wp_cache_get_last_changed( 'posts' );
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$args = array(
			'post_type'      => 'post',
			'posts_per_page' => $count,
		);
		if ( '' !== $cat ) {
			$args['category_name'] = $cat;
		}
		$gate = Gate::meta_query_clause();
		if ( array() !== $gate ) {
			$args['meta_query'] = $gate;
		}

		$this->cache[ $key ] = Query::get( $args )->posts;

		return $this->cache[ $key ];
	}
}
