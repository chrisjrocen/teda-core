<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Spaces;

use Teda_Core\Support\Bootable;

/**
 * Server-side rendering for a single X Space (P19, SPEC §5.1, §10.3).
 *
 * The reference site (Parliament Watch) shows crawlers and slow connections nothing
 * but "Loading X Spaces…". TEDA must never do that. So the structured content of a
 * Space — summary, key points, speakers, date, duration and what TEDA is doing about
 * it — is rendered here as plain server-side HTML, OUTSIDE the embed. The X embed is
 * only ever an enhancement layered on top, and its failure is a first-class state:
 * when there is no embed, the page still carries a working link out and, where
 * present, the self-hosted MP3.
 *
 * No X API dependency (SPEC §2), and — importantly for a metered-data audience —
 * the PUBLIC page never makes a third-party request. Any oEmbed lookup happens once,
 * at save time, and the result (or its absence) is stored in post meta; rendering
 * only reads that meta. Blocking x.com at the network level therefore cannot break,
 * slow, or empty the page — it simply leaves the designed fallback in place.
 */
final class Presenter implements Bootable {

	/**
	 * Post meta holding the cached oEmbed HTML ('' means "tried, nothing usable").
	 */
	private const EMBED_META = '_teda_space_embed';

	public function register(): void {
		// Resolve the embed once, when the Space is saved — never on the public path.
		add_action( 'save_post_teda_space', array( $this, 'cache_embed' ), 20, 1 );
	}

	/**
	 * On save, attempt an oEmbed for the Space URL and store the result (or '') so
	 * the front end never fetches. Bounded timeout; a failure stores '' and the
	 * fallback renders. Skips autosaves/revisions.
	 *
	 * @param int $post_id Saved Space id.
	 */
	public function cache_embed( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$url = self::field_url( 'teda_space_url', $post_id );
		if ( '' === $url ) {
			delete_post_meta( $post_id, self::EMBED_META );
			return;
		}

		// Keep the save fast and resilient if x.com is slow or unreachable.
		$shorten = static function ( array $args ): array {
			$args['timeout'] = 4;
			return $args;
		};
		add_filter( 'oembed_remote_get_args', $shorten );
		$html = wp_oembed_get( $url );
		remove_filter( 'oembed_remote_get_args', $shorten );

		update_post_meta( $post_id, self::EMBED_META, is_string( $html ) ? $html : '' );
	}

	/**
	 * The structured, embed-independent body of a Space. Always safe to output and
	 * fully indexable: this is the content, not decoration.
	 */
	public static function details_html( int $post_id ): string {
		$date     = self::field( 'teda_space_date', $post_id );
		$duration = trim( (string) self::field( 'teda_duration', $post_id, '' ) );
		$speakers = self::field_list( 'teda_speakers', $post_id );
		$summary  = trim( (string) self::field( 'teda_summary', $post_id, '' ) );
		$points   = self::field_list( 'teda_key_points', $post_id );
		$action   = trim( (string) self::field( 'teda_space_action', $post_id, '' ) );

		$out = '<div class="teda-space">';

		// Meta line: date · duration · speakers count.
		$meta = array();
		if ( is_numeric( $date ) ) {
			$meta[] = esc_html( wp_date( (string) get_option( 'date_format', 'j F Y' ), (int) $date ) );
		}
		if ( '' !== $duration ) {
			$meta[] = esc_html( $duration );
		}
		if ( array() !== $meta ) {
			$out .= '<p class="teda-space__meta">' . implode( ' · ', $meta ) . '</p>';
		}

		if ( '' !== $summary ) {
			$out .= '<div class="teda-space__section"><h2 class="teda-space__h">' . esc_html__( 'Summary', 'teda-core' ) . '</h2>';
			$out .= wpautop( esc_html( $summary ) ) . '</div>';
		}

		if ( array() !== $points ) {
			$out .= '<div class="teda-space__section"><h2 class="teda-space__h">' . esc_html__( 'Key points', 'teda-core' ) . '</h2><ul class="teda-space__points">';
			foreach ( $points as $point ) {
				$point = trim( (string) $point );
				if ( '' !== $point ) {
					$out .= '<li>' . esc_html( $point ) . '</li>';
				}
			}
			$out .= '</ul></div>';
		}

		if ( array() !== $speakers ) {
			$names = array();
			foreach ( $speakers as $speaker ) {
				$speaker = trim( (string) $speaker );
				if ( '' !== $speaker ) {
					$names[] = '<li>' . esc_html( $speaker ) . '</li>';
				}
			}
			if ( array() !== $names ) {
				$out .= '<div class="teda-space__section"><h2 class="teda-space__h">' . esc_html__( 'Speakers', 'teda-core' ) . '</h2><ul class="teda-space__speakers">' . implode( '', $names ) . '</ul></div>';
			}
		}

		if ( '' !== $action ) {
			$out .= '<div class="teda-space__section teda-space__action"><h2 class="teda-space__h">' . esc_html__( 'What TEDA is doing about it', 'teda-core' ) . '</h2>';
			$out .= wpautop( esc_html( $action ) ) . '</div>';
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * The embed slot. Either the cached oEmbed (an enhancement) or, when there is
	 * none, the designed fallback: a direct link to the Space and the self-hosted
	 * MP3 where present. Returns '' only when there is neither a URL nor audio —
	 * i.e. nothing to link to at all.
	 */
	public static function embed_html( int $post_id ): string {
		$url   = self::field_url( 'teda_space_url', $post_id );
		$audio = self::field_url( 'teda_audio_file', $post_id );
		$embed = (string) get_post_meta( $post_id, self::EMBED_META, true );

		// Enhancement: a real, previously-resolved embed. Keep a link out beneath it
		// so a viewer whose network blocks the embed's own assets is never stuck.
		if ( '' !== $embed ) {
			$out  = '<div class="teda-space-embed" data-teda-space-embed="1">' . $embed . '</div>';
			if ( '' !== $url ) {
				$out .= '<p class="teda-space-embed__out"><a href="' . esc_url( $url ) . '" rel="noopener noreferrer external" target="_blank">'
					. esc_html__( 'Trouble viewing? Open this Space on X', 'teda-core' ) . '</a></p>';
			}
			return $out;
		}

		// Fallback (first-class): link out + MP3. This is what shows whenever X is
		// blocked, gone, or never resolved — the page stays useful.
		if ( '' === $url && '' === $audio ) {
			return '';
		}

		$out = '<div class="teda-space-fallback">';
		$out .= '<p class="teda-space-fallback__lead">' . esc_html__( 'This conversation happened on an X Space.', 'teda-core' ) . '</p>';

		if ( '' !== $audio ) {
			$out .= '<figure class="teda-space-fallback__audio"><figcaption>' . esc_html__( 'Listen to the recording', 'teda-core' ) . '</figcaption>';
			$out .= '<audio controls preload="none" src="' . esc_url( $audio ) . '">'
				. esc_html__( 'Your browser cannot play this audio. Use the download link below.', 'teda-core' ) . '</audio>';
			$out .= '<p><a href="' . esc_url( $audio ) . '" download>' . esc_html__( 'Download the MP3', 'teda-core' ) . '</a></p></figure>';
		}

		if ( '' !== $url ) {
			$out .= '<a class="teda-btn teda-btn--brown teda-btn--lg" href="' . esc_url( $url ) . '" rel="noopener noreferrer external" target="_blank">'
				. esc_html__( 'Open this Space on X', 'teda-core' ) . '</a>';
		}

		$out .= '</div>';

		return $out;
	}

	/* --------------------------------------------------------------------- */
	/* Field access (through the teda-core accessors when present)           */
	/* --------------------------------------------------------------------- */

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	private static function field( string $key, int $post_id, $default = null ) {
		return function_exists( 'teda_field' ) ? teda_field( $key, $post_id, $default ) : $default;
	}

	private static function field_url( string $key, int $post_id ): string {
		return function_exists( 'teda_field_url' ) ? teda_field_url( $key, $post_id, '' ) : '';
	}

	/**
	 * @return array<int|string, mixed>
	 */
	private static function field_list( string $key, int $post_id ): array {
		return function_exists( 'teda_field_list' ) ? teda_field_list( $key, $post_id ) : array();
	}
}
