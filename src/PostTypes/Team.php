<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\PostTypes;

/**
 * Team — Leadership & governance (SPEC §5.1). Funder-facing but not a browsable
 * section of the site: no archive, excluded from search and nav, and only ever
 * linked to from the teda/team block (SPEC §10.3). Each member has a single page
 * at /about/team/<slug>/ — that's the "full bio" a block card links to. Publishing
 * the post is what makes a member live; there is no separate verified flag.
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
			'menu_icon'           => 'dashicons-groups',
			'menu_position'       => 24,
			'has_archive'         => false, // No /team/ index — only the block lists members.
			'publicly_queryable'  => true, // Enables single member pages; see class doc.
			'exclude_from_search' => true, // Never in site search (SPEC §10.3).
			'show_in_nav_menus'   => false,
			'rewrite'             => array(
				'slug'       => 'about/team',
				'with_front' => false,
			),
			'supports'            => array( 'title', 'editor', 'thumbnail', 'revisions' ),
		);
	}
}
