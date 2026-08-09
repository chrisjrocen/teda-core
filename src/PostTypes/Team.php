<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\PostTypes;

/**
 * Team — Leadership & governance (SPEC §5.1). Funder-facing but NOT standalone
 * pages: surfaced on the About page only. No archive, not publicly queryable,
 * and excluded from search (SPEC §10.3).
 */
final class Team extends Abstract_Post_Type {

	public function key(): string {
		return 'teda_team';
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Team Member', 'teda-core' ),
			'plural'   => __( 'Team', 'teda-core' ),
		);
	}

	protected function args(): array {
		return array(
			'menu_icon'          => 'dashicons-groups',
			'menu_position'      => 24,
			'has_archive'        => false,
			'publicly_queryable' => false, // No single /team/<slug>/ pages.
			'exclude_from_search' => true, // Never in site search (SPEC §10.3).
			'show_in_nav_menus'  => false,
			'rewrite'            => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'revisions' ),
		);
	}
}
