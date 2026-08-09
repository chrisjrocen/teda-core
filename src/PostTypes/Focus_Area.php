<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\PostTypes;

/**
 * Focus Areas (SPEC §5.1). Archive at /focus-areas/, singles at
 * /focus-areas/<slug>/ (§4.1). Six entries at launch, ordered by an `order`
 * meta field (P03).
 */
final class Focus_Area extends Abstract_Post_Type {

	public function key(): string {
		return 'teda_focus_area';
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Focus Area', 'teda-core' ),
			'plural'   => __( 'Focus Areas', 'teda-core' ),
		);
	}

	protected function args(): array {
		return array(
			'menu_icon'     => 'dashicons-screenoptions',
			'menu_position' => 22,
			'has_archive'   => 'focus-areas',
			'rewrite'       => array(
				'slug'       => 'focus-areas',
				'with_front' => false,
			),
		);
	}
}
