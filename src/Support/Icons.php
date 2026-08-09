<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Support;

/**
 * Inline SVG icons for the six focus areas (SPEC §3.1: an icon and a label always
 * accompany colour — colour is never the only signal). Paths are traced from the
 * design prototype. Icons are decorative (aria-hidden); the card's heading carries
 * the meaning. Sizing/colour come from teda-child CSS via currentColor.
 */
final class Icons {

	/**
	 * The <path> geometry for each focus-area icon key. Keys match the
	 * teda_icon select in Fields\Groups\Focus_Area.
	 *
	 * @var array<string, string>
	 */
	private const PATHS = array(
		'education'        => '<path d="M4 4h6a2.5 2.5 0 0 1 2 1.2A2.5 2.5 0 0 1 14 4h6v14h-6a2.5 2.5 0 0 0-2 1.2A2.5 2.5 0 0 0 10 18H4z"/><path d="M12 5.2V19"/>',
		'climate'          => '<path d="M20 4c.5 8-4.6 14-12 14-2 0-3.5-.4-3.5-.4C4 10.6 10.5 4.6 20 4z"/><path d="M5 20c1.5-5 5-8.5 9.5-11"/>',
		'health'           => '<path d="M12 20.5S4.5 15.6 4.5 10.4A3.9 3.9 0 0 1 12 8.5a3.9 3.9 0 0 1 7.5 1.9c0 5.2-7.5 10.1-7.5 10.1z"/><path d="M5 13h3l1.6-3 2.4 5.4L14 13h5"/>',
		'entrepreneurship' => '<path d="M9.5 18h5"/><path d="M10 21h4"/><path d="M12 3a6 6 0 0 0-3.8 10.6c.8.7 1.2 1.5 1.3 2.4h5c.1-.9.5-1.7 1.3-2.4A6 6 0 0 0 12 3z"/>',
		'leadership'       => '<path d="M3 21h18"/><path d="M5 21V9.5L12 4l7 5.5V21"/><path d="M9.5 21v-6h5v6"/>',
		'culture'          => '<path d="M6.5 8h11l-1 11a3 3 0 0 1-3 2.6h-3a3 3 0 0 1-3-2.6z"/><path d="M6.5 8c0-1.9 2.5-3 5.5-3s5.5 1.1 5.5 3"/><path d="M8 11l8 6M16 11l-8 6"/>',
	);

	/**
	 * Return the inline SVG for a focus-area icon key, or '' if unknown. The markup
	 * is a fixed string of trusted geometry — safe to echo, but callers should still
	 * treat it as HTML (not attribute) context.
	 */
	public static function focus( string $key ): string {
		$paths = self::PATHS[ $key ] ?? '';
		if ( '' === $paths ) {
			return '';
		}

		return '<svg class="teda-icon" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" '
			. 'fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
			. $paths
			. '</svg>';
	}

	/**
	 * Whether a focus-area icon key is known.
	 */
	public static function has_focus( string $key ): bool {
		return isset( self::PATHS[ $key ] );
	}
}
