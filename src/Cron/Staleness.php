<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Cron;

/**
 * The staleness data source (SPEC §10.2) — the operational half of TEDA's
 * quarterly content review, computed once and shared by both the dashboard widget
 * and `wp teda staleness-report` so the two can never disagree.
 *
 * Each check answers one question a volunteer would otherwise have to remember to
 * ask: is the news going stale, are there any events to show, did an opportunity
 * outlive its deadline, is unverified content sitting on the public site, are any
 * team members still awaiting confirmation. Every row carries a link to the place
 * you fix it.
 */
final class Staleness {

	/** A news feed with nothing newer than this many days is flagged. */
	private const NEWS_MAX_AGE_DAYS = 90;

	/**
	 * Build the report. Returns an ordered list of rows:
	 *   [ key, label, ok (bool), count (int), detail (string), url (string), action (string) ]
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function report(): array {
		return array(
			self::news_row(),
			self::events_row(),
			self::open_expired_row(),
			self::unverified_row(),
			self::team_row(),
		);
	}

	/**
	 * Newest published news post older than the threshold.
	 *
	 * @return array<string, mixed>
	 */
	private static function news_row(): array {
		$latest = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		$url = admin_url( 'post-new.php' );
		if ( array() === $latest ) {
			return self::row( 'news', __( 'News freshness', 'teda-core' ), false, 0, __( 'No news posts published yet.', 'teda-core' ), $url, __( 'Write a post', 'teda-core' ) );
		}

		$post = $latest[0];
		$age  = (int) floor( ( time() - (int) get_post_timestamp( $post ) ) / DAY_IN_SECONDS );
		$ok   = $age <= self::NEWS_MAX_AGE_DAYS;
		$detail = $ok
			/* translators: %d: days since the newest post. */
			? sprintf( __( 'Newest post is %d days old.', 'teda-core' ), $age )
			/* translators: 1: days old, 2: threshold. */
			: sprintf( __( 'Newest post is %1$d days old (over %2$d). Time for an update.', 'teda-core' ), $age, self::NEWS_MAX_AGE_DAYS );

		return self::row( 'news', __( 'News freshness', 'teda-core' ), $ok, $ok ? 0 : 1, $detail, get_edit_post_link( $post->ID, 'raw' ) ?: $url, __( 'Post an update', 'teda-core' ) );
	}

	/**
	 * Whether any upcoming events exist (same definition as the archive).
	 *
	 * @return array<string, mixed>
	 */
	private static function events_row(): array {
		$upcoming = get_posts(
			array(
				'post_type'      => 'teda_event',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => 'teda_start_datetime',
				'meta_query'     => array(
					array( 'key' => 'teda_start_datetime', 'value' => time(), 'compare' => '>=', 'type' => 'NUMERIC' ),
				),
			)
		);

		$ok  = array() !== $upcoming;
		$url = admin_url( 'edit.php?post_type=teda_event' );

		return self::row(
			'events',
			__( 'Upcoming events', 'teda-core' ),
			$ok,
			$ok ? 0 : 1,
			$ok ? __( 'At least one upcoming event is scheduled.', 'teda-core' ) : __( 'No upcoming events. The homepage is showing past highlights instead.', 'teda-core' ),
			$ok ? $url : admin_url( 'post-new.php?post_type=teda_event' ),
			$ok ? __( 'Manage events', 'teda-core' ) : __( 'Add an event', 'teda-core' )
		);
	}

	/**
	 * Opportunities still flagged open although their deadline has passed. After the
	 * daily reconciliation this should be zero; a non-zero count means the cron has
	 * not run (see the runbook's WP-Cron note).
	 *
	 * @return array<string, mixed>
	 */
	private static function open_expired_row(): array {
		$ids = self::expired_open_opportunity_ids();
		$n   = count( $ids );
		$ok  = 0 === $n;

		return self::row(
			'opportunities',
			__( 'Expired open roles', 'teda-core' ),
			$ok,
			$n,
			$ok
				? __( 'No open roles are past their deadline.', 'teda-core' )
				/* translators: %d: number of roles. */
				: sprintf( __( '%d open role(s) are past their deadline. Run: wp teda close-expired', 'teda-core' ), $n ),
			admin_url( 'edit.php?post_type=teda_opportunity' ),
			__( 'Review roles', 'teda-core' )
		);
	}

	/**
	 * Published-but-unverified content across the types that carry the D13 flag
	 * (news and events; team is reported on its own row below).
	 *
	 * @return array<string, mixed>
	 */
	private static function unverified_row(): array {
		$types = array( 'post', 'teda_event' );
		$n     = 0;
		foreach ( $types as $type ) {
			$n += self::count_unverified_published( $type );
		}
		$ok = 0 === $n;

		return self::row(
			'unverified',
			__( 'Unverified published content', 'teda-core' ),
			$ok,
			$n,
			$ok
				? __( 'All published news and events are verified.', 'teda-core' )
				/* translators: %d: number of items. */
				: sprintf( __( '%d published item(s) are not yet verified (D13). Confirm the details, then switch on “Verified for publishing”.', 'teda-core' ), $n ),
			admin_url( 'edit.php' ),
			__( 'Review content', 'teda-core' )
		);
	}

	/**
	 * Team members awaiting confirmation (published, not verified).
	 *
	 * @return array<string, mixed>
	 */
	private static function team_row(): array {
		$n  = self::count_unverified_published( 'teda_team' );
		$ok = 0 === $n;

		return self::row(
			'team',
			__( 'Team members to confirm', 'teda-core' ),
			$ok,
			$n,
			$ok
				? __( 'All published team members are confirmed.', 'teda-core' )
				/* translators: %d: number of members. */
				: sprintf( __( '%d team member(s) need their name and consent confirmed before they stay public.', 'teda-core' ), $n ),
			admin_url( 'edit.php?post_type=teda_team' ),
			__( 'Review team', 'teda-core' )
		);
	}

	/* --------------------------------------------------------------------- */
	/* Shared queries                                                        */
	/* --------------------------------------------------------------------- */

	/**
	 * IDs of opportunities that are open (explicitly, or by unset default) yet past
	 * their deadline. Shared by the widget and the reconciliation cron so both agree
	 * on exactly which posts are affected.
	 *
	 * @return array<int, int>
	 */
	public static function expired_open_opportunity_ids(): array {
		$ids = get_posts(
			array(
				'post_type'      => 'teda_opportunity',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'teda_deadline',
						'value'   => time(),
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
					array(
						'relation' => 'OR',
						array( 'key' => 'teda_is_open', 'compare' => 'NOT EXISTS' ),
						array( 'key' => 'teda_is_open', 'value' => '1', 'compare' => '=' ),
					),
				),
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Count published posts of a type whose teda_verified flag is off or unset.
	 */
	private static function count_unverified_published( string $type ): int {
		$ids = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'OR',
					array( 'key' => 'teda_verified', 'compare' => 'NOT EXISTS' ),
					array( 'key' => 'teda_verified', 'value' => '1', 'compare' => '!=' ),
				),
			)
		);

		return count( (array) $ids );
	}

	/**
	 * Assemble a report row.
	 *
	 * @return array<string, mixed>
	 */
	private static function row( string $key, string $label, bool $ok, int $count, string $detail, string $url, string $action ): array {
		return array(
			'key'    => $key,
			'label'  => $label,
			'ok'     => $ok,
			'count'  => $count,
			'detail' => $detail,
			'url'    => $url,
			'action' => $action,
		);
	}
}
