<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Taxonomies;

/**
 * Shared taxonomy linking Focus Areas and Publications (feedback: attach
 * publications to corresponding focus areas as a sidebar). Registered on both
 * `teda_focus_area` and `teda_publication` so editors tag a publication with the
 * focus area(s) it belongs to, and the focus-area single page queries
 * publications sharing the same terms.
 *
 * Hierarchical (checkbox UI) for volunteer ease. Seeds the six standard
 * focus-area names so the terms match across the two post types from day one.
 */
final class Focus_Area_Pub extends Abstract_Taxonomy {

	public function key(): string {
		return 'focus_area_pub';
	}

	protected function object_types(): array {
		return array( 'teda_focus_area', 'teda_publication' );
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Focus Area Tag', 'teda-core' ),
			'plural'   => __( 'Focus Area Tags', 'teda-core' ),
		);
	}

	/**
	 * Registration overrides: the taxonomy is public so tax_query works on
	 * front-end queries, but has no public archive, no nav menu visibility, and
	 * no tag cloud — it exists only to link publications to focus areas.
	 */
	protected function args(): array {
		return array(
			'publicly_queryable' => false,
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'rewrite'            => false,
		);
	}

	/**
	 * Seed the six focus-area names so editors can tag immediately and the
	 * terms match the focus-area post titles. Rename terms freely — they are
	 * linked by term, not by hardcoded text.
	 *
	 * @return array<int, string>
	 */
	public function default_terms(): array {
		return array(
			'Education',
			'Climate',
			'Health',
			'Entrepreneurship',
			'Leadership',
			'Culture',
		);
	}
}
