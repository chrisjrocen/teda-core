<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Admin;

use Teda_Core\Support\Bootable;

/**
 * A tiny admin-notice bag. Subsystems queue notices; this renders them once on
 * `admin_notices`. Centralising it means one consistent, escaped, dismissible
 * presentation instead of every subsystem hand-rolling markup.
 */
final class Notices implements Bootable {

	/**
	 * Queued notices.
	 *
	 * @var array<int, array{message:string, type:string, dismissible:bool}>
	 */
	private array $notices = array();

	private static ?Notices $current = null;

	public function register(): void {
		self::$current = $this;
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Queue a notice from anywhere in the plugin.
	 *
	 * @param string $message     Already-translated, plain text (HTML is escaped).
	 * @param string $type        info|warning|error|success.
	 * @param bool   $dismissible Whether to show the dismiss button.
	 */
	public static function add( string $message, string $type = 'warning', bool $dismissible = true ): void {
		if ( null === self::$current ) {
			return;
		}
		self::$current->notices[] = array(
			'message'     => $message,
			'type'        => $type,
			'dismissible' => $dismissible,
		);
	}

	/**
	 * Render queued notices. Escaped on output (house rule 6).
	 */
	public function render(): void {
		foreach ( $this->notices as $notice ) {
			$classes = array( 'notice', 'notice-' . $notice['type'] );
			if ( $notice['dismissible'] ) {
				$classes[] = 'is-dismissible';
			}

			printf(
				'<div class="%1$s"><p><strong>%2$s</strong> %3$s</p></div>',
				esc_attr( implode( ' ', $classes ) ),
				esc_html__( 'TEDA Core:', 'teda-core' ),
				esc_html( $notice['message'] )
			);
		}
	}
}
