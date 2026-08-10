<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Seo;

use Teda_Core\Support\Bootable;

/**
 * SEO configuration (P16): canonical host, sitemap hygiene and Event structured
 * data. Integrates with Rank Math where it is active (so there is one JSON-LD graph
 * and one sitemap, never duplicates), and degrades to self-emitted tags when it is
 * not — teda-core never hard-depends on an SEO plugin (D2).
 */
final class Config implements Bootable {

	/** Post types kept out of the sitemap until they have published content (§10.1). */
	private const GATED_TYPES = array( 'teda_space', 'teda_publication' );

	/** Set once the Event JSON-LD has been emitted this request, to dedupe. */
	private bool $event_emitted = false;

	public function register(): void {
		// Canonical + Open Graph host: always the production domain (B1).
		add_filter( 'rank_math/frontend/canonical', array( $this, 'force_host' ) );
		add_filter( 'get_canonical_url', array( $this, 'force_host' ) );

		// Sitemap: drop Spaces/Publications while empty; they return automatically.
		add_filter( 'rank_math/sitemap/exclude_post_type', array( $this, 'exclude_empty_type' ), 10, 2 );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'core_sitemap_post_types' ) );

		// Event structured data on single events. We self-emit in wp_head (Rank Math's
		// JSON-LD module is not always enabled) AND hook Rank Math's graph — whichever
		// runs first sets $event_emitted, so the Event node appears exactly once.
		add_action( 'wp_head', array( $this, 'emit_event_head' ), 9 );
		add_filter( 'rank_math/json_ld', array( $this, 'event_json_ld' ), 20, 2 );

		// Canonical fallback only when Rank Math is not producing one.
		if ( ! $this->rank_math_active() ) {
			add_action( 'wp_head', array( $this, 'fallback_canonical_head' ), 1 );
		}
	}

	/* --------------------------------------------------------------------- */
	/* Canonical host                                                        */
	/* --------------------------------------------------------------------- */

	/**
	 * Rewrite a URL's scheme+host to the canonical production host (TEDA_CANONICAL_HOST),
	 * keeping the path and query. Empty constant → leave the URL untouched (staging).
	 *
	 * @param string $url URL to canonicalise.
	 */
	public function force_host( $url ) {
		$host = defined( 'TEDA_CANONICAL_HOST' ) ? (string) TEDA_CANONICAL_HOST : '';
		if ( '' === $host || ! is_string( $url ) || '' === $url ) {
			return $url;
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$qs   = (string) wp_parse_url( $url, PHP_URL_QUERY );

		return rtrim( $host, '/' ) . $path . ( '' !== $qs ? '?' . $qs : '' );
	}

	/* --------------------------------------------------------------------- */
	/* Sitemap                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Exclude a gated post type from the Rank Math sitemap while it has no published
	 * content, so the sitemap never advertises an empty archive (§10.1). Once the
	 * first item is published the type reappears with no code change.
	 *
	 * @param bool   $excluded  Current decision.
	 * @param string $post_type Post type being considered.
	 * @return bool
	 */
	public function exclude_empty_type( $excluded, $post_type ): bool {
		if ( in_array( $post_type, self::GATED_TYPES, true ) && ! $this->has_published( $post_type ) ) {
			return true;
		}
		return (bool) $excluded;
	}

	/**
	 * Same gate for WordPress core sitemaps (the fallback when Rank Math is off).
	 *
	 * @param array<string, \WP_Post_Type> $post_types
	 * @return array<string, \WP_Post_Type>
	 */
	public function core_sitemap_post_types( $post_types ) {
		if ( ! is_array( $post_types ) ) {
			return $post_types;
		}
		foreach ( self::GATED_TYPES as $type ) {
			if ( isset( $post_types[ $type ] ) && ! $this->has_published( $type ) ) {
				unset( $post_types[ $type ] );
			}
		}
		return $post_types;
	}

	/* --------------------------------------------------------------------- */
	/* Event schema                                                          */
	/* --------------------------------------------------------------------- */

	/**
	 * Add a schema.org/Event node on single events, and drop Rank Math's default
	 * Article/BlogPosting for that view so the two never collide. SPEC dates use the
	 * site timezone.
	 *
	 * @param array<string, mixed> $data   Rank Math JSON-LD pieces.
	 * @param mixed                $jsonld Rank Math JsonLD instance (unused).
	 * @return array<string, mixed>
	 */
	public function event_json_ld( $data, $jsonld = null ): array {
		if ( ! is_array( $data ) || ! is_singular( 'teda_event' ) ) {
			return is_array( $data ) ? $data : array();
		}

		// Remove default article-ish nodes to avoid duplicate/competing schema.
		foreach ( array( 'richSnippet', 'article', 'BlogPosting', 'primaryImage' ) as $key ) {
			unset( $data[ $key ] );
		}

		// If wp_head already emitted the standalone Event, don't add it again here.
		if ( ! $this->event_emitted ) {
			$data['event']       = $this->event_node( (int) get_the_ID() );
			$this->event_emitted = true;
		}

		return $data;
	}

	/**
	 * Build the Event node.
	 *
	 * @return array<string, mixed>
	 */
	private function event_node( int $id ): array {
		$start = function_exists( 'teda_field_timestamp' ) ? teda_field_timestamp( 'teda_start_datetime', $id ) : null;
		$end   = function_exists( 'teda_field_timestamp' ) ? teda_field_timestamp( 'teda_end_datetime', $id ) : null;

		$node = array(
			'@type'               => 'Event',
			'name'                => wp_strip_all_tags( get_the_title( $id ) ),
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'url'                 => $this->force_host( (string) get_permalink( $id ) ),
			'organizer'           => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => $this->force_host( home_url( '/' ) ),
			),
		);

		if ( null !== $start ) {
			$node['startDate'] = wp_date( 'c', $start );
		}
		if ( null !== $end ) {
			$node['endDate'] = wp_date( 'c', $end );
		}

		$desc = wp_strip_all_tags( get_the_excerpt( $id ) );
		if ( '' !== $desc ) {
			$node['description'] = $desc;
		}

		$img = get_the_post_thumbnail_url( $id, 'large' );
		if ( is_string( $img ) && '' !== $img ) {
			$node['image'] = $img;
		}

		// Location: venue + address parts if present, else a place named for the org.
		$venue    = function_exists( 'teda_field' ) ? (string) teda_field( 'teda_venue_name', $id, '' ) : '';
		$location = function_exists( 'teda_field' ) ? (string) teda_field( 'teda_location', $id, '' ) : '';
		$district = function_exists( 'teda_field' ) ? (string) teda_field( 'teda_district', $id, '' ) : '';
		$address  = trim( implode( ', ', array_filter( array( $location, $district ) ) ) );
		$node['location'] = array(
			'@type' => 'Place',
			'name'  => '' !== $venue ? $venue : ( '' !== $address ? $address : __( 'Teso sub-region, Uganda', 'teda-core' ) ),
		);
		if ( '' !== $address ) {
			$node['location']['address'] = $address;
		}

		return $node;
	}

	/**
	 * Emit the Event JSON-LD in wp_head for single events. Runs before Rank Math's
	 * JSON-LD (priority 9) and marks $event_emitted so the rank_math/json_ld filter
	 * does not add a second Event node.
	 */
	public function emit_event_head(): void {
		if ( $this->event_emitted || ! is_singular( 'teda_event' ) ) {
			return;
		}
		$node = array_merge( array( '@context' => 'https://schema.org' ), $this->event_node( (int) get_the_ID() ) );
		echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $node ) . '</script>' . "\n";
		$this->event_emitted = true;
	}

	/**
	 * Canonical fallback when Rank Math is not active.
	 */
	public function fallback_canonical_head(): void {
		if ( is_singular() || is_front_page() || is_home() || is_archive() ) {
			$url = is_singular() ? (string) get_permalink() : home_url( add_query_arg( array() ) );
			$url = $this->force_host( $url );
			if ( '' !== $url ) {
				echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
			}
		}
	}

	/* --------------------------------------------------------------------- */
	/* Helpers                                                               */
	/* --------------------------------------------------------------------- */

	private function has_published( string $type ): bool {
		$counts = wp_count_posts( $type );
		return isset( $counts->publish ) && (int) $counts->publish > 0;
	}

	private function rank_math_active(): bool {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath\Helper' );
	}
}
