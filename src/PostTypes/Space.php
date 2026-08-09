<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\PostTypes;

/**
 * Spaces — X Spaces / audio (SPEC §5.1, phase 2). Registered from the start so
 * routes and Customizer panels exist, but kept out of nav (P06) and the sitemap
 * (P16) until a Space is published (Support\Visibility). Archive at /spaces/.
 */
final class Space extends Abstract_Post_Type {

	public function key(): string {
		return 'teda_space';
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Space', 'teda-core' ),
			'plural'   => __( 'Spaces', 'teda-core' ),
		);
	}

	protected function args(): array {
		return array(
			'menu_icon'     => 'dashicons-microphone',
			'menu_position' => 25,
			'has_archive'   => 'spaces',
			'rewrite'       => array(
				'slug'       => 'spaces',
				'with_front' => false,
			),
		);
	}
}
