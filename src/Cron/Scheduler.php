<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Cron;

use Teda_Core\Support\Bootable;

/**
 * Stale-content automation (SPEC §10.2, P14). The site must never show an expired
 * countdown, a closed role marked open, or a date that has silently gone wrong —
 * the named failure of the old site (a dead countdown to 18 July 2026) must not
 * recur.
 *
 * This subsystem owns the one thing that genuinely needs writing on a schedule:
 * reconciling an opportunity's stored `teda_is_open` flag with its deadline. Events
 * need no cron — they transition to "past" at query time (archives/blocks), which
 * is correct the instant the deadline passes even if cron never runs. The daily
 * job is exposed as `wp teda close-expired` so it can also be driven from a real
 * system cron when WP-Cron is unreliable (see docs/RUNBOOK.md).
 */
final class Scheduler implements Bootable {

	/** Option storing the last reconciliation run (timestamp + count), for the report. */
	private const LAST_RUN_OPTION = 'teda_core_close_expired_last';

	public function register(): void {
		// Attach the reconciliation to the shared daily hook (Plugin::run_daily).
		add_action( 'teda_core/cron/daily', array( $this, 'close_expired' ) );

		// Staleness dashboard widget (SPEC §10.2 operational review).
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'teda close-expired', array( $this, 'cli_close_expired' ) );
			\WP_CLI::add_command( 'teda staleness-report', array( $this, 'cli_staleness_report' ) );
		}
	}

	/* --------------------------------------------------------------------- */
	/* Reconciliation                                                        */
	/* --------------------------------------------------------------------- */

	/**
	 * Flip `teda_is_open` to false on every published opportunity past its deadline.
	 * Idempotent — a second run in the same minute finds nothing left to change —
	 * and safe to call from cron, WP-CLI or a system cron. Returns the number of
	 * roles closed on this run.
	 */
	public function close_expired(): int {
		$ids    = Staleness::expired_open_opportunity_ids();
		$closed = 0;
		foreach ( $ids as $id ) {
			// update_post_meta is itself idempotent, but the query already excludes
			// anything already closed, so each write here is a real transition.
			update_post_meta( $id, 'teda_is_open', '0' );
			Closed_At::apply( $id, false );
			++$closed;
		}

		update_option(
			self::LAST_RUN_OPTION,
			array( 'time' => time(), 'closed' => $closed ),
			false
		);

		if ( $closed > 0 ) {
			// A quiet operational log line; never user-facing.
			error_log( sprintf( '[teda-core] close-expired: closed %d opportunity(ies) past deadline.', $closed ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		/**
		 * Fires after the daily reconciliation. P-later subsystems can hook this.
		 *
		 * @param int $closed Number of opportunities closed this run.
		 */
		do_action( 'teda_core/cron/closed_expired', $closed );

		return $closed;
	}

	/* --------------------------------------------------------------------- */
	/* Dashboard widget                                                      */
	/* --------------------------------------------------------------------- */

	/**
	 * Register the staleness widget for users who can edit content.
	 */
	public function register_widget(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'teda_staleness',
			__( 'TEDA — content to review', 'teda-core' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the staleness widget: one row per check, each linking to its fix.
	 */
	public function render_widget(): void {
		$rows = Staleness::report();

		echo '<table class="teda-staleness widefat striped" style="border:0">';
		echo '<tbody>';
		foreach ( $rows as $row ) {
			$ok    = ! empty( $row['ok'] );
			$icon  = $ok ? '✅' : '⚠️';
			$color = $ok ? '#1a7d3c' : '#a15c00';
			echo '<tr>';
			echo '<td style="width:1.5em;vertical-align:top">' . esc_html( $icon ) . '</td>';
			echo '<td>';
			echo '<strong>' . esc_html( (string) $row['label'] ) . '</strong><br>';
			echo '<span style="color:' . esc_attr( $color ) . '">' . esc_html( (string) $row['detail'] ) . '</span>';
			if ( ! $ok && ! empty( $row['url'] ) ) {
				echo ' <a href="' . esc_url( (string) $row['url'] ) . '">' . esc_html( (string) $row['action'] ) . ' →</a>';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		$last = get_option( self::LAST_RUN_OPTION );
		if ( is_array( $last ) && ! empty( $last['time'] ) ) {
			echo '<p class="description" style="margin-top:8px">'
				. esc_html(
					sprintf(
						/* translators: 1: human time diff, 2: number closed. */
						__( 'Auto-close last ran %1$s ago (%2$d closed).', 'teda-core' ),
						human_time_diff( (int) $last['time'] ),
						(int) ( $last['closed'] ?? 0 )
					)
				)
				. '</p>';
		}
	}

	/* --------------------------------------------------------------------- */
	/* WP-CLI                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * `wp teda close-expired` — run the reconciliation now. Safe to run repeatedly
	 * and from a system crontab.
	 *
	 * @param array<int, string>    $args       Unused.
	 * @param array<string, string> $assoc_args Unused.
	 */
	public function cli_close_expired( $args, $assoc_args ): void {
		$closed = $this->close_expired();
		\WP_CLI::success( sprintf( 'Closed %d opportunity(ies) past their deadline.', $closed ) );
	}

	/**
	 * `wp teda staleness-report` — print the same rows as the dashboard widget, so
	 * it can be scheduled and emailed.
	 *
	 * @param array<int, string>    $args       Unused.
	 * @param array<string, string> $assoc_args Unused.
	 */
	public function cli_staleness_report( $args, $assoc_args ): void {
		$rows  = Staleness::report();
		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'check'  => (string) $row['label'],
				'status' => ! empty( $row['ok'] ) ? 'ok' : 'REVIEW',
				'detail' => (string) $row['detail'],
			);
		}
		if ( class_exists( '\WP_CLI\Utils\format_items' ) || function_exists( 'WP_CLI\Utils\format_items' ) ) {
			\WP_CLI\Utils\format_items( 'table', $items, array( 'check', 'status', 'detail' ) );
		} else {
			foreach ( $items as $i ) {
				\WP_CLI::log( sprintf( '%-28s %-7s %s', $i['check'], $i['status'], $i['detail'] ) );
			}
		}

		$flagged = count( array_filter( $rows, static fn( $r ): bool => empty( $r['ok'] ) ) );
		if ( $flagged > 0 ) {
			\WP_CLI::warning( sprintf( '%d check(s) need attention.', $flagged ) );
		} else {
			\WP_CLI::success( 'Everything is fresh.' );
		}
	}
}
