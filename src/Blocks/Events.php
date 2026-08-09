<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use Teda_Core\Support\Dates;
use WP_Block;
use WP_Post;

/**
 * teda/events — upcoming events ascending from teda_start_datetime (SPEC §4 item 4,
 * §10.2). Three states, in order:
 *  1. upcoming events exist → list them;
 *  2. none upcoming but featured past events exist → a short note + those past
 *     events, so the section is never a gap (SPEC §10.2);
 *  3. neither → the designed empty state with the channel link (SPEC §10.1).
 *
 * All date logic compares Meta Box timestamps against Dates::now() (epoch) and
 * formats via the site timezone, so the list is correct the day after an event
 * passes with nobody touching it.
 */
final class Events extends Block_Renderer {

	/**
	 * Memoised query results, keyed by "count|event_type".
	 *
	 * @var array<string, array{upcoming: array<int, WP_Post>, past: array<int, WP_Post>}>
	 */
	private array $cache = array();

	public function name(): string {
		return 'teda/events';
	}

	protected function is_empty( array $attributes, WP_Block $block ): bool {
		$sets = $this->events( $attributes );
		return array() === $sets['upcoming'] && array() === $sets['past'];
	}

	protected function render_empty( array $attributes, WP_Block $block ): string {
		$channel = $this->str_attr( $attributes, 'channel_url' );
		$label   = $this->str_attr( $attributes, 'channel_label', 'WhatsApp' );
		$past    = $this->str_attr( $attributes, 'past_url' );

		$out = $this->header( $attributes );
		$out .= '<div class="teda-empty"><h3>' . esc_html__( 'No events scheduled right now', 'teda-core' ) . '</h3>';
		$out .= '<p>' . sprintf(
			/* translators: %s: channel name, e.g. WhatsApp. */
			esc_html__( 'Follow us on %s to hear about the next one first.', 'teda-core' ),
			esc_html( $label )
		) . '</p>';
		if ( '' !== $channel ) {
			$out .= '<a class="teda-btn teda-btn--ghost-b" href="' . esc_url( $channel ) . '">'
				. sprintf( /* translators: %s: channel name. */ esc_html__( 'Follow on %s', 'teda-core' ), esc_html( $label ) )
				. '</a>';
		}
		if ( '' !== $past ) {
			$out .= ' <a class="teda-event__pastlink" href="' . esc_url( $past ) . '">' . esc_html__( 'See past events', 'teda-core' ) . '</a>';
		}
		$out .= '</div>';

		return $out;
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$sets     = $this->events( $attributes );
		$upcoming = array() !== $sets['upcoming'];
		$posts    = $upcoming ? $sets['upcoming'] : $sets['past'];

		$out = $this->header( $attributes );

		// Fallback note when we are showing past events in place of upcoming ones.
		if ( ! $upcoming ) {
			$out .= '<p class="teda-events__fallback">' . esc_html__( 'Nothing is scheduled at the moment — here is what we ran most recently.', 'teda-core' ) . '</p>';
		}

		$group = $this->str_attr( $attributes, 'filter_group' );
		$open  = '' !== $group ? '<div class="teda-events__list" data-teda-filtergroup="' . esc_attr( $group ) . '">' : '<div class="teda-events__list">';

		$rows = '';
		foreach ( $posts as $post ) {
			$rows .= $this->row( $post, ! $upcoming );
		}

		return $out . $open . $rows . '</div>';
	}

	/**
	 * One event row: date block | body | action (matches the prototype .evrow).
	 */
	private function row( WP_Post $post, bool $past ): string {
		$start = teda_field_timestamp( 'teda_start_datetime', $post->ID );
		$type  = $this->primary_type( $post );

		$row = '<div class="teda-event' . ( $past ? ' teda-event--past' : '' ) . '"'
			. ( null !== $type ? ' data-teda-cat="' . esc_attr( $type['slug'] ) . '"' : '' ) . '>';

		$row .= '<div class="teda-event__date">';
		if ( null !== $start ) {
			$row .= '<b>' . esc_html( Dates::day( $start ) ) . '</b><span>' . esc_html( Dates::month_abbrev( $start ) ) . '</span>';
		}
		$row .= '</div>';

		$row .= '<div class="teda-event__body">';
		if ( null !== $type || $past ) {
			$tag  = null !== $type ? $type['name'] : '';
			$tag .= $past ? ( '' !== $tag ? ' · ' : '' ) . __( 'Past', 'teda-core' ) : '';
			$row .= '<span class="teda-tag">' . esc_html( $tag ) . '</span>';
		}
		$row .= '<h3 class="teda-event__title"><a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>';
		$meta = $this->meta_line( $post );
		if ( '' !== $meta ) {
			$row .= '<p class="teda-event__meta">' . esc_html( $meta ) . '</p>';
		}
		$row .= '</div>';

		$row .= '<div class="teda-event__go">' . $this->action( $post, $past ) . '</div>';
		$row .= '</div>';

		return $row;
	}

	/**
	 * "Venue · Location · District" from whichever fields are filled.
	 */
	private function meta_line( WP_Post $post ): string {
		$parts = array_filter(
			array(
				(string) teda_field( 'teda_venue_name', $post->ID, '' ),
				(string) teda_field( 'teda_location', $post->ID, '' ),
				(string) teda_field( 'teda_district', $post->ID, '' ),
			),
			static fn( string $v ): bool => '' !== trim( $v )
		);

		return implode( ' · ', $parts );
	}

	/**
	 * The row's action button. Upcoming + registration open → Register; otherwise a
	 * ghost "See highlights" (past) or "Details" link. Always to the event page.
	 */
	private function action( WP_Post $post, bool $past ): string {
		$url = (string) get_permalink( $post );

		if ( $past ) {
			return '<a class="teda-btn teda-btn--ghost-b" href="' . esc_url( $url ) . '">' . esc_html__( 'See highlights', 'teda-core' ) . '</a>';
		}
		if ( teda_field_bool( 'teda_registration_open', $post->ID ) ) {
			return '<a class="teda-btn teda-btn--brown" href="' . esc_url( $url ) . '">' . esc_html__( 'Register', 'teda-core' ) . '</a>';
		}
		return '<a class="teda-btn teda-btn--ghost-b" href="' . esc_url( $url ) . '">' . esc_html__( 'Details', 'teda-core' ) . '</a>';
	}

	/**
	 * The event's first event_type term, or null.
	 *
	 * @return array{slug: string, name: string}|null
	 */
	private function primary_type( WP_Post $post ): ?array {
		$terms = get_the_terms( $post, 'event_type' );
		if ( ! is_array( $terms ) || array() === $terms ) {
			return null;
		}
		$term = $terms[0];
		return array(
			'slug' => $term->slug,
			'name' => $term->name,
		);
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

		$out = '<div class="teda-events__head">';
		if ( '' !== $eyebrow ) {
			$out .= '<span class="teda-eyebrow">' . esc_html( $eyebrow ) . '</span>';
		}
		if ( '' !== $heading ) {
			$out .= '<h2 class="teda-display">' . esc_html( $heading ) . '</h2>';
		}
		return $out . '</div>';
	}

	/**
	 * Fetch (and memoise) upcoming + featured-past events for these attributes.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array{upcoming: array<int, WP_Post>, past: array<int, WP_Post>}
	 */
	private function events( array $attributes ): array {
		$count = $this->int_attr( $attributes, 'count', 3, 1, Query::MAX );
		$type  = $this->str_attr( $attributes, 'event_type' );
		// last_changed keys the memo so it never survives a content change (matters
		// if a block renders after a save in the same request).
		$key   = $count . '|' . $type . '|' . wp_cache_get_last_changed( 'posts' );
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$now = Dates::now();
		$tax = '' !== $type
			? array( array( 'taxonomy' => 'event_type', 'field' => 'slug', 'terms' => $type ) )
			: array();

		$upcoming = Query::get(
			array(
				'post_type'      => 'teda_event',
				'posts_per_page' => $count,
				'meta_key'       => 'teda_start_datetime',
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => array(
					array( 'key' => 'teda_start_datetime', 'value' => $now, 'compare' => '>=', 'type' => 'NUMERIC' ),
				),
				'tax_query'      => $tax,
			)
		)->posts;

		$past = array();
		if ( array() === $upcoming ) {
			$past = Query::get(
				array(
					'post_type'      => 'teda_event',
					'posts_per_page' => $count,
					'meta_key'       => 'teda_start_datetime',
					'orderby'        => 'meta_value_num',
					'order'          => 'DESC',
					'meta_query'     => array(
						'relation' => 'AND',
						array( 'key' => 'teda_start_datetime', 'value' => $now, 'compare' => '<', 'type' => 'NUMERIC' ),
						array( 'key' => 'teda_is_featured', 'value' => '1', 'compare' => '=' ),
					),
					'tax_query'      => $tax,
				)
			)->posts;
		}

		$this->cache[ $key ] = array( 'upcoming' => $upcoming, 'past' => $past );

		return $this->cache[ $key ];
	}
}
