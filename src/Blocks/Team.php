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
 * teda/team — published team members, grouped into three fixed sections by the
 * `team_category` taxonomy (Internal Team, Leadership Team, International
 * Support Team), each ordered by teda_order (SPEC §5.1). A member is live the
 * moment their post is published; draft is the "not ready yet" state, there is
 * no separate verified flag to flip (Query::get() already restricts to
 * post_status=publish). A member with no team_category term assigned is simply
 * omitted from every section — categorising members is a manual admin task,
 * not something this block infers. Each card links to the member's single page
 * (see single-teda_team.php) for their full, untruncated bio. With no
 * categorised team member published yet it renders the shared "team" empty
 * state rather than a gap.
 *
 * avatar_html() and social_links_html() are public so the theme's
 * single-teda_team.php can reuse the exact same card markup for a member's own
 * page without duplicating it.
 */
final class Team extends Block_Renderer {

	/**
	 * Fixed section order — Internal Team first, then Leadership Team, then
	 * International Support Team. Looked up by term name (not slug) at query
	 * time so a renamed term still resolves.
	 *
	 * @var array<int, string>
	 */
	private const SECTIONS = array(
		'Internal Team',
		'Leadership Team',
		'International Support Team',
	);

	/**
	 * Memoised query result keyed by "count|gate|last_changed".
	 *
	 * @var array<string, array<int, array{label: string, posts: array<int, WP_Post>}>>
	 */
	private array $cache = array();

	/**
	 * Recognised social platforms, matched against a lower-cased, trimmed label.
	 * Each maps to an inline SVG (no icon font / build step) and a class suffix.
	 *
	 * @var array<string, string>
	 */
	private const ICONS = array(
		'linkedin'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.94 8.5H3.56V20h3.38V8.5ZM5.25 3a1.96 1.96 0 1 0 0 3.92A1.96 1.96 0 0 0 5.25 3ZM20.44 20h-3.37v-5.9c0-1.41-.03-3.22-1.96-3.22-1.97 0-2.27 1.54-2.27 3.12V20H9.47V8.5h3.24v1.57h.05c.45-.86 1.56-1.76 3.21-1.76 3.43 0 4.47 2.26 4.47 5.19V20Z"/></svg>',
		'x'         => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m13.9 10.7 6.4-7.4h-1.9l-5.5 6.4-4.5-6.4H3l6.8 9.7L3 20.7h1.9l5.9-6.8 4.8 6.8H21l-7.1-10Zm-2 2.4-.7-1-5.5-7.8h2.1l4.4 6.3.7 1 5.7 8.2h-2.1l-4.6-6.7Z"/></svg>',
		'facebook'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 21v-7.6h2.6l.4-3h-3v-1.9c0-.87.24-1.46 1.5-1.46h1.6V4.3c-.28-.04-1.23-.12-2.34-.12-2.32 0-3.9 1.4-3.9 4V10.4H7.75v3h2.61V21h3.14Z"/></svg>',
		'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.2c2.7 0 3 0 4.1.06 1.07.05 1.8.22 2.4.46.65.25 1.2.6 1.75 1.15.55.55.9 1.1 1.15 1.75.24.6.4 1.34.46 2.4.06 1.1.06 1.4.06 4.1s0 3-.06 4.1c-.05 1.07-.22 1.8-.46 2.4a4.6 4.6 0 0 1-1.15 1.75 4.6 4.6 0 0 1-1.75 1.15c-.6.24-1.34.4-2.4.46-1.1.06-1.4.06-4.1.06s-3 0-4.1-.06c-1.07-.05-1.8-.22-2.4-.46a4.6 4.6 0 0 1-1.75-1.15 4.6 4.6 0 0 1-1.15-1.75c-.24-.6-.4-1.34-.46-2.4C2.2 15 2.2 14.7 2.2 12s0-3 .06-4.1c.05-1.07.22-1.8.46-2.4.25-.65.6-1.2 1.15-1.75A4.6 4.6 0 0 1 5.6 2.6c.6-.24 1.34-.4 2.4-.46C9.1 2.2 9.4 2.2 12 2.2Zm0 1.8c-2.66 0-2.98 0-4.03.06-.87.04-1.34.18-1.65.3-.42.16-.71.36-1.02.67-.31.31-.5.6-.67 1.02-.12.31-.26.78-.3 1.65C4.28 9.02 4.28 9.34 4.28 12s0 2.98.06 4.03c.04.87.18 1.34.3 1.65.16.42.36.71.67 1.02.31.31.6.5 1.02.67.31.12.78.26 1.65.3 1.05.05 1.37.06 4.03.06s2.98 0 4.03-.06c.87-.04 1.34-.18 1.65-.3.42-.16.71-.36 1.02-.67.31-.31.5-.6.67-1.02.12-.31.26-.78.3-1.65.05-1.05.06-1.37.06-4.03s0-2.98-.06-4.03c-.04-.87-.18-1.34-.3-1.65a2.8 2.8 0 0 0-.67-1.02 2.8 2.8 0 0 0-1.02-.67c-.31-.12-.78-.26-1.65-.3C14.98 4 14.66 4 12 4Zm0 3.4a4.6 4.6 0 1 1 0 9.2 4.6 4.6 0 0 1 0-9.2Zm0 1.8a2.8 2.8 0 1 0 0 5.6 2.8 2.8 0 0 0 0-5.6Zm4.8-2a1.08 1.08 0 1 1 0 2.16 1.08 1.08 0 0 1 0-2.16Z"/></svg>',
		'email'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm1.7 2 7.3 5.5L19.3 7H4.7ZM4 8.4V17h16V8.4l-8 6-8-6Z"/></svg>',
		'website'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.93 8h-3.05a15.4 15.4 0 0 0-1.31-5.35A8.03 8.03 0 0 1 18.93 10ZM12 4c.62 0 1.75 1.7 2.24 6H9.76C10.25 5.7 11.38 4 12 4ZM9.43 4.65A15.4 15.4 0 0 0 8.12 10H5.07a8.03 8.03 0 0 1 4.36-5.35ZM5.07 12h3.05c.1 1.9.53 3.75 1.31 5.35A8.03 8.03 0 0 1 5.07 12Zm6.93 8c-.62 0-1.75-1.7-2.24-6h4.48c-.49 4.3-1.62 6-2.24 6Zm2.57-2.65c.78-1.6 1.21-3.45 1.31-5.35h3.05a8.03 8.03 0 0 1-4.36 5.35Z"/></svg>',
	);

	public function name(): string {
		return 'teda/team';
	}

	protected function is_empty( array $attributes, WP_Block $block ): bool {
		foreach ( $this->members_by_category( $attributes ) as $section ) {
			if ( array() !== $section['posts'] ) {
				return false;
			}
		}

		return true;
	}

	protected function render_empty( array $attributes, WP_Block $block ): string {
		return $this->header( $attributes ) . Empty_State::render( 'team' );
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$out = $this->header( $attributes );

		foreach ( $this->members_by_category( $attributes ) as $section ) {
			if ( array() === $section['posts'] ) {
				continue;
			}

			$out .= '<div class="teda-team__section">';
			$out .= '<h3 class="teda-team__section-heading">' . esc_html( $section['label'] ) . '</h3>';
			$out .= '<div class="teda-team__list">';
			foreach ( $section['posts'] as $post ) {
				$out .= $this->card( $post );
			}
			$out .= '</div>';
			$out .= '</div>';
		}

		return $out;
	}

	/**
	 * One team member card: photo/avatar, name (linked to their single page), role,
	 * a truncated bio, and social links.
	 */
	private function card( WP_Post $post ): string {
		$id   = (int) $post->ID;
		$url  = (string) get_permalink( $post );
		$role = trim( (string) teda_field( 'teda_role_title', $id, '' ) );
		$bio  = trim( (string) teda_field( 'teda_bio', $id, '' ) );

		$card = '<article class="teda-team-card">';

		$card .= self::avatar_html( $post );

		$card .= '<h3 class="teda-team-card__name"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>';

		if ( '' !== $role ) {
			$card .= '<p class="teda-team-card__role">' . esc_html( $role ) . '</p>';
		}

		if ( '' !== $bio ) {
			$card .= '<p class="teda-team-card__bio">' . esc_html( wp_trim_words( $bio, 20 ) ) . '</p>';
		}

		$card .= self::social_links_html( $id );

		$card .= '</article>';

		return $card;
	}

	/**
	 * The member's featured image, or an initials-avatar fallback so a card (or the
	 * single page) never looks broken without a photo. Public: reused by
	 * single-teda_team.php.
	 */
	public static function avatar_html( WP_Post $post ): string {
		if ( has_post_thumbnail( $post ) ) {
			return '<span class="teda-team-card__avatar">'
				. get_the_post_thumbnail( $post, 'medium', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => esc_attr( get_the_title( $post ) ) ) )
				. '</span>';
		}

		return '<span class="teda-team-card__avatar teda-team-card__avatar--initials" aria-hidden="true">'
			. esc_html( self::initials( get_the_title( $post ) ) )
			. '</span>';
	}

	/**
	 * Up to the first two words' first letters, uppercased, e.g. "Jane Doe" -> "JD".
	 */
	private static function initials( string $name ): string {
		$words = array_filter( preg_split( '/\s+/', trim( $name ) ) ?: array() );
		$words = array_slice( $words, 0, 2 );

		$initials = '';
		foreach ( $words as $word ) {
			$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
		}

		return $initials;
	}

	/**
	 * The member's social links: an icon for recognised platforms (or a mailto for
	 * an email-shaped value), a plain text link for anything else — the field is
	 * free text, so unrecognised labels must still work. Public: reused by
	 * single-teda_team.php.
	 */
	public static function social_links_html( int $post_id ): string {
		$links = teda_field_list( 'teda_social_links', $post_id );
		if ( array() === $links ) {
			return '';
		}

		$out = '<ul class="teda-team-card__socials">';
		foreach ( $links as $pair ) {
			// Meta Box's key_value field stores each row as a plain [0 => key, 1 =>
			// value] pair (it's a clone field under the hood), not platform => url.
			$platform = trim( (string) ( is_array( $pair ) ? ( $pair[0] ?? '' ) : '' ) );
			$value    = trim( (string) ( is_array( $pair ) ? ( $pair[1] ?? '' ) : '' ) );
			if ( '' === $platform || '' === $value ) {
				continue;
			}

			$slug = self::icon_slug( $platform, $value );
			$href = self::href_for( $slug, $value );

			$out .= '<li class="teda-social-icon teda-social-icon--' . esc_attr( '' !== $slug ? $slug : 'other' ) . '">';
			$out .= '<a href="' . esc_url( $href ) . '">';
			if ( '' !== $slug ) {
				$out .= self::ICONS[ $slug ];
				$out .= '<span class="screen-reader-text">' . esc_html( $platform ) . '</span>';
			} else {
				$out .= esc_html( $platform );
			}
			$out .= '</a></li>';
		}
		$out .= '</ul>';

		return $out;
	}

	/**
	 * Match a typed platform label (or an email-shaped value) to a known icon key,
	 * or '' when unrecognised.
	 */
	private static function icon_slug( string $platform, string $value ): string {
		$label = strtolower( $platform );

		if ( str_contains( $label, 'linkedin' ) ) {
			return 'linkedin';
		}
		if ( 'x' === $label || str_contains( $label, 'twitter' ) ) {
			return 'x';
		}
		if ( str_contains( $label, 'facebook' ) || 'fb' === $label ) {
			return 'facebook';
		}
		if ( str_contains( $label, 'instagram' ) || 'ig' === $label ) {
			return 'instagram';
		}
		if ( str_contains( $label, 'website' ) || str_contains( $label, 'web' ) ) {
			return 'website';
		}
		if ( str_contains( $label, 'email' ) || str_contains( $label, 'mail' ) || is_email( $value ) ) {
			return 'email';
		}

		return '';
	}

	/**
	 * The href for a social link — a mailto: for an email icon whose value is a bare
	 * address, otherwise the value as typed.
	 */
	private static function href_for( string $slug, string $value ): string {
		if ( 'email' === $slug && is_email( $value ) ) {
			return 'mailto:' . $value;
		}

		return $value;
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

		$out = '<div class="teda-team__head">';
		if ( '' !== $eyebrow ) {
			$out .= '<span class="teda-eyebrow">' . esc_html( $eyebrow ) . '</span>';
		}
		if ( '' !== $heading ) {
			$out .= '<h2 class="teda-display">' . esc_html( $heading ) . '</h2>';
		}
		return $out . '</div>';
	}

	/**
	 * Published team members for each of the three fixed sections, ordered by
	 * teda_order then title, capped per section at $count (default 12, hard
	 * cap Query::MAX). A member with no matching term is omitted; a section
	 * whose term doesn't exist (e.g. taxonomy not yet seeded) is simply empty.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<int, array{label: string, posts: array<int, WP_Post>}>
	 */
	private function members_by_category( array $attributes ): array {
		$count = $this->int_attr( $attributes, 'count', 12, 1, Query::MAX );
		$key   = $count . '|' . wp_cache_get_last_changed( 'posts' ) . '|' . wp_cache_get_last_changed( 'terms' );
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$sections = array();
		foreach ( self::SECTIONS as $label ) {
			$term  = get_term_by( 'name', $label, 'team_category' );
			$posts = false !== $term
				? Query::get(
					array(
						'post_type'      => 'teda_team',
						'posts_per_page' => $count,
						'meta_key'       => 'teda_order',
						'orderby'        => array(
							'meta_value_num' => 'ASC',
							'title'          => 'ASC',
						),
						'tax_query'      => array(
							array( 'taxonomy' => 'team_category', 'field' => 'term_id', 'terms' => $term->term_id ),
						),
					)
				)->posts
				: array();

			$sections[] = array(
				'label' => $label,
				'posts' => $posts,
			);
		}

		$this->cache[ $key ] = $sections;

		return $this->cache[ $key ];
	}
}
