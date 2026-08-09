<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Admin;

use Teda_Core\Support\Bootable;

/**
 * The verification gate (P15, C8/D13). Real copy is migrated from the old site, but
 * its figures are mixed real and aspirational and funders check them — so nothing
 * is trusted by default. Every verifiable item ships `teda_verified = false` and is
 * held back from public "facts" (the stat band, §11) until a human ticks it.
 *
 * This class is the enforcement half:
 *   - a persistent, NON-dismissible admin notice listing unverified *published*
 *     items with edit links, so the gap is impossible to ignore;
 *   - `wp teda verify-report`, a full listing for review;
 *   - `wp teda verify-gate`, which exits non-zero while any published item is
 *     unverified. That command is the launch gate wired into P18 — and it is
 *     *meant* to fail now.
 *
 * "Verifiable" is the set of post types that carry the teda_verified flag (news,
 * team, events); other content (focus-area descriptions, etc.) is not a funder
 * claim and is not gated. The list is filterable so P-later can widen it.
 */
final class Verification implements Bootable {

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'teda verify-report', array( $this, 'cli_verify_report' ) );
			\WP_CLI::add_command( 'teda verify-gate', array( $this, 'cli_verify_gate' ) );
		}
	}

	/**
	 * Post types that carry the teda_verified flag and are therefore gated.
	 *
	 * @return array<int, string>
	 */
	public static function verifiable_post_types(): array {
		/**
		 * Filter the post types subject to the verification gate.
		 *
		 * @param array<int, string> $types Post type keys.
		 */
		return (array) apply_filters( 'teda_core/verifiable_post_types', array( 'post', 'teda_team', 'teda_event' ) );
	}

	/**
	 * Published items whose teda_verified flag is off or unset.
	 *
	 * @return array<int, array{id:int, type:string, title:string, edit:string}>
	 */
	public static function unverified_published(): array {
		$ids = get_posts(
			array(
				'post_type'      => self::verifiable_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'post_type',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'OR',
					array( 'key' => 'teda_verified', 'compare' => 'NOT EXISTS' ),
					array( 'key' => 'teda_verified', 'value' => '1', 'compare' => '!=' ),
				),
			)
		);

		$items = array();
		foreach ( (array) $ids as $id ) {
			$id      = (int) $id;
			$items[] = array(
				'id'    => $id,
				'type'  => (string) get_post_type( $id ),
				'title' => (string) get_the_title( $id ),
				'edit'  => (string) ( get_edit_post_link( $id, 'raw' ) ?: '' ),
			);
		}

		return $items;
	}

	/* --------------------------------------------------------------------- */
	/* Admin notice                                                          */
	/* --------------------------------------------------------------------- */

	/**
	 * Persistent, non-dismissible notice listing unverified published items with
	 * edit links. Shown to users who can edit content.
	 */
	public function render_notice(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$items = self::unverified_published();
		if ( array() === $items ) {
			return;
		}

		// NOTE: deliberately no `is-dismissible` — this must not be clickable away.
		echo '<div class="notice notice-warning">';
		echo '<p><strong>' . esc_html__( 'TEDA — content awaiting verification', 'teda-core' ) . '</strong></p>';
		echo '<p>' . esc_html(
			sprintf(
				/* translators: %d: number of items. */
				_n(
					'%d published item is not yet verified. Confirm its details are true and can be backed up, then switch on “Verified for publishing”. Unverified figures stay off the homepage until then.',
					'%d published items are not yet verified. Confirm their details are true and can be backed up, then switch on “Verified for publishing”. Unverified figures stay off the homepage until then.',
					count( $items ),
					'teda-core'
				),
				count( $items )
			)
		) . '</p>';

		echo '<ul style="list-style:disc;margin-left:2em">';
		foreach ( array_slice( $items, 0, 25 ) as $item ) {
			$label = '' !== $item['title'] ? $item['title'] : sprintf( '#%d', $item['id'] );
			echo '<li>';
			echo '<em>' . esc_html( $item['type'] ) . '</em> — ';
			if ( '' !== $item['edit'] ) {
				echo '<a href="' . esc_url( $item['edit'] ) . '">' . esc_html( $label ) . '</a>';
			} else {
				echo esc_html( $label );
			}
			echo '</li>';
		}
		echo '</ul>';

		if ( count( $items ) > 25 ) {
			echo '<p>' . esc_html( sprintf( /* translators: %d: remaining count. */ __( '…and %d more.', 'teda-core' ), count( $items ) - 25 ) ) . '</p>';
		}
		echo '</div>';
	}

	/* --------------------------------------------------------------------- */
	/* WP-CLI                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * `wp teda verify-report` — list every published item awaiting confirmation.
	 *
	 * @param array<int, string>    $args       Unused.
	 * @param array<string, string> $assoc_args Unused.
	 */
	public function cli_verify_report( $args, $assoc_args ): void {
		$items = self::unverified_published();
		if ( array() === $items ) {
			\WP_CLI::success( 'Every published item is verified.' );
			return;
		}

		$rows = array();
		foreach ( $items as $item ) {
			$rows[] = array(
				'id'    => $item['id'],
				'type'  => $item['type'],
				'title' => $item['title'],
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'type', 'title' ) );
		\WP_CLI::log( sprintf( '%d item(s) awaiting verification.', count( $items ) ) );
	}

	/**
	 * `wp teda verify-gate` — the launch gate (P18). Exits non-zero while any
	 * published item is unverified. Failing now is the correct state.
	 *
	 * @param array<int, string>    $args       Unused.
	 * @param array<string, string> $assoc_args Unused.
	 */
	public function cli_verify_gate( $args, $assoc_args ): void {
		$items = self::unverified_published();
		if ( array() === $items ) {
			\WP_CLI::success( 'Verification gate passed: all published content is verified.' );
			return;
		}

		foreach ( $items as $item ) {
			\WP_CLI::log( sprintf( '  [unverified] %-14s #%-5d %s', $item['type'], $item['id'], $item['title'] ) );
		}
		\WP_CLI::error( sprintf( 'Verification gate FAILED: %d published item(s) are unverified.', count( $items ) ) );
	}
}
