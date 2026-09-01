<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Taxonomies;

/**
 * Groups team members (SPEC: Leadership / Internal / International Support) so
 * the teda/team block can render them as separate sections. A member can carry
 * more than one term (e.g. a leader who's also international support).
 *
 * Purely a grouping mechanism — no public archive or nav presence, same as
 * Focus_Area_Pub. Seeds the three fixed category names so editors can assign
 * them immediately.
 */
final class Team_Category extends Abstract_Taxonomy {

	public function key(): string {
		return 'team_category';
	}

	protected function object_types(): array {
		return array( 'teda_team' );
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Team Category', 'teda-core' ),
			'plural'   => __( 'Team Categories', 'teda-core' ),
		);
	}

	/**
	 * Registration overrides: no public archive, nav presence, or tag cloud —
	 * this taxonomy exists only to group members into sections on the
	 * teda/team block.
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
	 * Seed the three fixed category names so editors can assign them
	 * immediately. Rename terms freely — they are linked by term, not
	 * hardcoded text.
	 *
	 * @return array<int, string>
	 */
	public function default_terms(): array {
		return array(
			'Leadership Team',
			'Internal Team',
			'International Support Team',
		);
	}
}
