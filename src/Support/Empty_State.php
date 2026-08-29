<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Support;

/**
 * The single empty-state system (SPEC §10.1, D10). One place owns the copy for
 * every archive that can launch empty, so there are never five ad-hoc "nothing
 * found" messages. Every state has a heading, a sentence AND an action — never a
 * bare dead end.
 *
 * Markup ships here (semantic, works with the child theme inactive); styling lives
 * in teda-child (.teda-empty*). Action URLs are filterable so P11/P13 can point them
 * at the real pages/forms once those exist, without touching this copy.
 */
final class Empty_State {

	/**
	 * Render the empty state for a context: events | news | spaces | opportunities
	 * | publications | gallery | team | donate | default. Unknown contexts fall back to
	 * `default`.
	 *
	 * @param string               $context One of the known contexts.
	 * @param array<string, mixed> $args    Optional overrides (heading, text, actions).
	 */
	public static function render( string $context, array $args = array() ): string {
		$config = array_merge( self::config( $context ), $args );

		$actions = '';
		foreach ( (array) ( $config['actions'] ?? array() ) as $action ) {
			$label = (string) ( $action['label'] ?? '' );
			$url   = (string) ( $action['url'] ?? '' );
			if ( '' === $label ) {
				continue;
			}
			$class = 'teda-btn ' . ( ! empty( $action['primary'] ) ? 'teda-btn--brown' : 'teda-btn--ghost-b' );
			$actions .= '<a class="' . esc_attr( $class ) . '" href="' . esc_url( '' !== $url ? $url : '#' ) . '">' . esc_html( $label ) . '</a>';
		}

		$out = '<div class="teda-empty teda-empty--' . esc_attr( $context ) . '">';
		$out .= '<h2 class="teda-empty__title">' . esc_html( (string) $config['heading'] ) . '</h2>';
		$out .= '<p class="teda-empty__text">' . esc_html( (string) $config['text'] ) . '</p>';
		if ( '' !== $actions ) {
			$out .= '<div class="teda-empty__actions">' . $actions . '</div>';
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * The copy + actions for a context (SPEC §10.1). Each context is filterable via
	 * `teda_core/empty_state/{context}` so links can be wired to real pages later.
	 *
	 * @return array{heading:string, text:string, actions:array<int, array<string, mixed>>}
	 */
	private static function config( string $context ): array {
		$archive = static function ( string $post_type ): string {
			$url = get_post_type_archive_link( $post_type );
			return is_string( $url ) ? $url : home_url( '/' );
		};

		switch ( $context ) {
			case 'events':
				$config = array(
					'heading' => __( 'No events scheduled right now', 'teda-core' ),
					'text'    => __( 'Follow us on WhatsApp to hear about the next one first.', 'teda-core' ),
					'actions' => array(
						array( 'label' => __( 'Follow on WhatsApp', 'teda-core' ), 'url' => self::link( 'whatsapp' ), 'primary' => true ),
						array( 'label' => __( 'See past events', 'teda-core' ), 'url' => add_query_arg( 'show', 'past', $archive( 'teda_event' ) ) ),
					),
				);
				break;

			case 'news':
				$config = array(
					'heading' => __( 'We’re just getting started', 'teda-core' ),
					'text'    => __( 'Our first stories are coming soon.', 'teda-core' ),
					'actions' => array(
						array( 'label' => __( 'Join TEDA', 'teda-core' ), 'url' => self::link( 'join' ), 'primary' => true ),
					),
				);
				break;

			case 'spaces':
				$config = array(
					'heading' => __( 'Our first Space is coming', 'teda-core' ),
					'text'    => __( 'Follow @tedayouthteso on X to join live.', 'teda-core' ),
					'actions' => array(
						array( 'label' => __( 'Follow on X', 'teda-core' ), 'url' => self::link( 'x' ), 'primary' => true ),
					),
				);
				break;

			case 'opportunities':
				$config = array(
					'heading' => __( 'No open roles at the moment', 'teda-core' ),
					'text'    => __( 'Register your interest and we’ll contact you when something opens.', 'teda-core' ),
					'actions' => array(
						array( 'label' => __( 'Register your interest', 'teda-core' ), 'url' => self::link( 'register_interest' ), 'primary' => true ),
					),
				);
				break;

			case 'publications':
				$config = array(
					'heading' => __( 'Our reports are on the way', 'teda-core' ),
					'text'    => __( 'TEDA publishes what it does and what it spends. The first documents will appear here as they are released.', 'teda-core' ),
					'actions' => array(
						array( 'label' => __( 'About TEDA', 'teda-core' ), 'url' => home_url( '/about/' ), 'primary' => true ),
						array( 'label' => __( 'Ask us a question', 'teda-core' ), 'url' => home_url( '/contact/' ) ),
					),
				);
				break;

			case 'donate':
				$config = array(
					'heading' => __( "Donations aren't open right now", 'teda-core' ),
					'text'    => __( 'Please check back soon, or contact us directly if you would like to give.', 'teda-core' ),
					'actions' => array(
						array( 'label' => __( 'Contact us', 'teda-core' ), 'url' => home_url( '/contact/' ), 'primary' => true ),
					),
				);
				break;

			case 'team':
				$config = array(
					'heading' => __( 'Meet the team', 'teda-core' ),
					'text'    => __( 'Leadership bios are on their way — check back soon.', 'teda-core' ),
					'actions' => array(
						array( 'label' => __( 'About TEDA', 'teda-core' ), 'url' => home_url( '/about/' ), 'primary' => true ),
					),
				);
				break;

			case 'gallery':
				$config = array(
					'heading' => __( 'Our gallery is coming soon', 'teda-core' ),
					'text'    => __( 'We’re collecting photos from our work across Teso. Please check back soon.', 'teda-core' ),
					'actions' => array(
						array( 'label' => __( 'Back to home', 'teda-core' ), 'url' => home_url( '/' ) ),
					),
				);
				break;

			default:
				$config = array(
					'heading' => __( 'Nothing here yet', 'teda-core' ),
					'text'    => __( 'This section is still being filled in. Please check back soon.', 'teda-core' ),
					'actions' => array(
						array( 'label' => __( 'Back to home', 'teda-core' ), 'url' => home_url( '/' ) ),
					),
				);
		}

		/**
		 * Filter an empty-state's copy and actions.
		 *
		 * @param array  $config  Heading, text and actions.
		 * @param string $context The context key.
		 */
		return apply_filters( "teda_core/empty_state/{$context}", $config, $context );
	}

	/**
	 * A named external/interest link, filterable and stored as an option so it can be
	 * set once without code. Defaults keep the site honest (a real X handle; '#' for
	 * the not-yet-built pages).
	 */
	private static function link( string $key ): string {
		$defaults = array(
			'whatsapp'          => (string) get_option( 'teda_whatsapp_url', '#' ),
			'x'                 => (string) get_option( 'teda_x_url', 'https://x.com/tedayouthteso' ),
			'join'              => (string) get_option( 'teda_join_url', '#' ),
			'register_interest' => (string) get_option( 'teda_register_interest_url', '#' ),
		);

		$url = $defaults[ $key ] ?? '#';

		/**
		 * Filter a named empty-state link.
		 *
		 * @param string $url The URL.
		 * @param string $key The link key.
		 */
		return (string) apply_filters( 'teda_core/empty_state/link', $url, $key );
	}
}
