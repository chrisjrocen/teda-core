<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Redirects;

use Teda_Core\Support\Bootable;

/**
 * The old-site → new-site redirect map (SPEC §4.1, C9). A plain PHP array applied
 * on `template_redirect` with permanent 301s, so the previous static site's URLs
 * never 404 at cutover. Chosen over the Redirection plugin (C9): version-controlled,
 * dependency-free, and free on normal traffic because it only runs when a request
 * would otherwise miss.
 *
 * Fragments (e.g. `/focus-areas.html#education`) never reach the server — the hash
 * is client-side only — so those are forwarded by a tiny script on the focus-areas
 * archive (assets/js). The mapping is published here as {@see fragment_map()} so the
 * script and this class share one source of truth.
 */
final class Map implements Bootable {

	public function register(): void {
		// Priority 5: ahead of Blocksy/Rank Math canonical redirects, but the handler
		// only acts on paths in the map, so it is inert on real content.
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 5 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'teda check-redirects', array( $this, 'cli_check_redirects' ) );
		}
	}

	/**
	 * Source path (old URL) => target path (new URL). Paths are root-relative and
	 * are resolved against the site home at redirect time. SPEC §4.1, in order.
	 *
	 * @return array<string, string>
	 */
	public static function map(): array {
		return array(
			'/about.html'          => '/about/',
			'/focus-areas.html'    => '/focus-areas/',
			'/join.html'           => '/get-involved/join/',
			'/opportunities.html'  => '/get-involved/opportunities/',
			'/apply.html'          => '/get-involved/opportunities/',
			'/get-involved.html'   => '/get-involved/volunteer/',
			'/events.html'         => '/events/',
			'/youth-forum.html'    => '/teso-youth-forum/',
			'/news.html'           => '/news/',
			'/gallery.html'        => '/gallery/',
			'/donate.html'         => '/donate/',
			'/contact.html'        => '/contact/',
			'/privacy-policy.html' => '/privacy-policy/',
			'/terms.html'          => '/terms/',
		);
	}

	/**
	 * Focus-area fragment => clean sub-page path. Used client-side to forward
	 * `/focus-areas.html#education` (which server-redirects to /focus-areas/ first)
	 * on to /focus-areas/education/.
	 *
	 * @return array<string, string>
	 */
	public static function fragment_map(): array {
		return array(
			'education'        => '/focus-areas/education/',
			'climate'          => '/focus-areas/climate/',
			'health'           => '/focus-areas/health/',
			'entrepreneurship' => '/focus-areas/entrepreneurship/',
			'leadership'       => '/focus-areas/leadership/',
			'culture'          => '/focus-areas/culture/',
		);
	}

	/**
	 * If the current request path is a known old URL, 301 to its new home. Matching
	 * is exact on the path (query string and fragment ignored); a trailing slash on
	 * the source is tolerated.
	 */
	public function maybe_redirect(): void {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( '' === $path ) {
			return;
		}

		$target = $this->resolve( $path );
		if ( null === $target ) {
			return;
		}

		wp_safe_redirect( home_url( $target ), 301 );
		exit;
	}

	/**
	 * Resolve a request path to a redirect target, or null if it is not mapped.
	 * Normalises a trailing slash so `/about.html/` matches `/about.html`.
	 */
	public function resolve( string $path ): ?string {
		$map = self::map();
		if ( isset( $map[ $path ] ) ) {
			return $map[ $path ];
		}
		$trimmed = rtrim( $path, '/' );
		if ( '' !== $trimmed && isset( $map[ $trimmed ] ) ) {
			return $map[ $trimmed ];
		}

		return null;
	}

	/**
	 * `wp teda check-redirects` — assert every source 301s to its target. Makes a
	 * real HTTP request per row (redirects disabled) so it tests the running handler,
	 * not just the array. Exits non-zero on any mismatch; wired into bin/verify.sh.
	 *
	 * @param array<int, string>    $args       Unused.
	 * @param array<string, string> $assoc_args Unused.
	 */
	public function cli_check_redirects( $args, $assoc_args ): void {
		$failures = 0;
		foreach ( self::map() as $source => $target ) {
			$resp = wp_remote_get(
				home_url( $source ),
				array( 'redirection' => 0, 'timeout' => 10, 'sslverify' => false )
			);

			if ( is_wp_error( $resp ) ) {
				\WP_CLI::warning( sprintf( '%s → request error: %s', $source, $resp->get_error_message() ) );
				++$failures;
				continue;
			}

			$code     = (int) wp_remote_retrieve_response_code( $resp );
			$location = (string) wp_remote_retrieve_header( $resp, 'location' );
			$got_path = (string) wp_parse_url( $location, PHP_URL_PATH );
			$want     = home_url( $target );
			$want_path = (string) wp_parse_url( $want, PHP_URL_PATH );

			if ( 301 === $code && untrailingslashit( $got_path ) === untrailingslashit( $want_path ) ) {
				\WP_CLI::log( sprintf( '  ok  %-24s 301 → %s', $source, $target ) );
			} else {
				\WP_CLI::warning( sprintf( '%s → expected 301 %s, got %d %s', $source, $target, $code, $got_path ) );
				++$failures;
			}
		}

		if ( $failures > 0 ) {
			\WP_CLI::error( sprintf( '%d redirect(s) failed.', $failures ) );
		}
		\WP_CLI::success( sprintf( 'All %d redirects resolve with a 301.', count( self::map() ) ) );
	}
}
