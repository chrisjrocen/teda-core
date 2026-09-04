<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Cron;

use Teda_Core\Fields\Accessor;
use Teda_Core\Support\Bootable;

/**
 * Stamps `teda_closed_at` on an opportunity the moment it closes — whether by
 * an admin flipping "Open for applications" off, or by {@see Scheduler::close_expired()}
 * closing it past its deadline. Clears the stamp on reopen.
 *
 * Needed because a role can close with no `teda_deadline` set (manual close), so
 * "how long has this been closed" cannot be derived from the deadline alone. The
 * archive uses this stamp to drop a role out of "Recently closed" ~30 days after
 * it closes, without touching the post itself (its own URL keeps working).
 */
final class Closed_At implements Bootable {

	private const META_KEY = 'teda_closed_at';

	public function register(): void {
		// Priority 20: after Meta Box's own save (default priority 10), so
		// teda_is_open already reflects this save when we read it.
		add_action( 'save_post_teda_opportunity', array( $this, 'sync' ), 20, 1 );
	}

	/**
	 * On save, stamp or clear `teda_closed_at` to match the current `teda_is_open`
	 * state. Skips autosaves/revisions.
	 *
	 * @param int $post_id Saved Opportunity id.
	 */
	public function sync( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::apply( $post_id, Accessor::get_bool( 'teda_is_open', $post_id, true ) );
	}

	/**
	 * Stamp or clear `teda_closed_at` for one opportunity to match $is_open.
	 * Shared by the save_post hook above and {@see Scheduler::close_expired()},
	 * since a cron-driven update_post_meta() does not fire save_post.
	 */
	public static function apply( int $post_id, bool $is_open ): void {
		if ( $is_open ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		if ( '' === get_post_meta( $post_id, self::META_KEY, true ) ) {
			update_post_meta( $post_id, self::META_KEY, time() );
		}
	}
}
