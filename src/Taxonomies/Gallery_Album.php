<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Taxonomies;

/**
 * Gallery Album (C5) — a taxonomy on `attachment`, so the gallery is native
 * media + album filter chips + Blocksy's lightbox, with no gallery CPT and no
 * custom lightbox. Albums group photos; chips filter them (P10).
 */
final class Gallery_Album extends Abstract_Taxonomy {

	public function key(): string {
		return 'gallery_album';
	}

	protected function object_types(): array {
		return array( 'attachment' );
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Album', 'teda-core' ),
			'plural'   => __( 'Albums', 'teda-core' ),
		);
	}

	protected function args(): array {
		return array(
			'rewrite' => array(
				'slug'       => 'album',
				'with_front' => false,
			),
		);
	}
}
