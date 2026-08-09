<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields;

/**
 * A gentle nudge — not a hard block — when an Event, News post or Space is
 * published without a featured image (SPEC §5.2 is explicit: a notice, not a
 * block, so a draft always saves). Names the exact post with an edit link.
 */
final class Featured_Image_Nudge {

	private const POST_TYPES = array( 'teda_event', 'post', 'teda_space' );

	public function register(): void {
		add_action( 'save_post', array( $this, 'check' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * After a save, remember any published post of these types that has no
	 * featured image, for the current user.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function check( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, self::POST_TYPES, true ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}

		$key     = $this->transient_key();
		$pending = get_transient( $key );
		$pending = is_array( $pending ) ? $pending : array();

		$pending[ $post_id ] = $post->post_title;
		set_transient( $key, $pending, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Show, then clear, the queued nudges.
	 */
	public function render(): void {
		$key     = $this->transient_key();
		$pending = get_transient( $key );

		if ( ! is_array( $pending ) || array() === $pending ) {
			return;
		}

		delete_transient( $key );

		$links = array();
		foreach ( $pending as $post_id => $title ) {
			$edit = get_edit_post_link( (int) $post_id );
			if ( null === $edit ) {
				continue;
			}
			$label   = '' !== $title ? $title : __( '(untitled)', 'teda-core' );
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html( $label ) );
		}

		if ( array() === $links ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s %s</p></div>',
			esc_html__( 'TEDA:', 'teda-core' ),
			esc_html__( 'These were published without a featured image, which they need to look right on the site:', 'teda-core' ),
			wp_kses_post( implode( ', ', $links ) )
		);
	}

	private function transient_key(): string {
		return 'teda_missing_image_' . get_current_user_id();
	}
}
