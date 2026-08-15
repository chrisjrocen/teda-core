<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields;

/**
 * Reusable field definitions shared across groups, so the verification switch
 * and the palette-limited colour picker read identically everywhere.
 */
final class Presets {

	/**
	 * The verification switch (D13). Default off — nothing is trusted until a
	 * human confirms it.
	 *
	 * @return array<string, mixed>
	 */
	public static function verified(): array {
		return array(
			'name'         => __( 'Verified for publishing', 'teda-core' ),
			'id'           => 'teda_verified',
			'type'         => 'switch',
			'style'        => 'rounded',
			'std'          => 0,
			'show_in_rest' => true,
			'desc'         => __( 'Turn this on only after someone has checked that the details here are true and can be backed up. Unverified items are kept off public figures like the homepage numbers.', 'teda-core' ),
		);
	}

	/**
	 * Accent colour limited to TEDA palette tokens — a select, never a colour
	 * picker (P03 task 3, SPEC §3.1: colour is never the only signal).
	 *
	 * @return array<string, mixed>
	 */
	public static function accent_color(): array {
		return array(
			'name'         => __( 'Accent colour', 'teda-core' ),
			'id'           => 'teda_accent_color',
			'type'         => 'select',
			'placeholder'  => __( 'Choose a colour', 'teda-core' ),
			'options'      => array(
				'brown' => __( 'Earth brown', 'teda-core' ),
				'blue'  => __( 'Teso blue', 'teda-core' ),
				'green' => __( 'Olive green', 'teda-core' ),
			),
			'std'          => 'green',
			'show_in_rest' => true,
			'desc'         => __( 'Pick the colour used to highlight this card. Only TEDA’s three brand colours are offered so the site stays consistent.', 'teda-core' ),
		);
	}
}
