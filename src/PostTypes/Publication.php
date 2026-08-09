<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\PostTypes;

/**
 * Publications — reports & annual accountability (SPEC §5.1, phase 3).
 * Registered from the start; kept out of nav and sitemap until populated
 * (Support\Visibility). Archive at /publications/.
 */
final class Publication extends Abstract_Post_Type {

	public function key(): string {
		return 'teda_publication';
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Publication', 'teda-core' ),
			'plural'   => __( 'Publications', 'teda-core' ),
		);
	}

	protected function args(): array {
		return array(
			'menu_icon'     => 'dashicons-media-document',
			'menu_position' => 26,
			'has_archive'   => 'publications',
			'rewrite'       => array(
				'slug'       => 'publications',
				'with_front' => false,
			),
		);
	}
}
