<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\PostTypes;

/**
 * Donation Campaigns — the admin-managed content behind teda/donate (moved out
 * of the page editor so routine fundraising updates are a wp-admin task, not a
 * page edit). Not a public/browsable post type: no archive, no single page, no
 * search — it only ever exists as data a teda/donate block picks by ID.
 *
 * `show_ui => true` with `public => false` gives editors a real list-table +
 * edit screen without a frontend footprint. `show_in_menu` parents that screen
 * under the existing "Donations" admin menu (Admin\Donations_Screen) instead of
 * adding a new top-level sidebar item.
 */
final class Campaign extends Abstract_Post_Type {

	public function key(): string {
		return 'teda_campaign';
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Campaign', 'teda-core' ),
			'plural'   => __( 'Campaigns', 'teda-core' ),
		);
	}

	protected function args(): array {
		return array(
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => 'teda-donations',
			'show_in_nav_menus'  => false,
			'show_in_admin_bar'  => false,
			'publicly_queryable' => false,
			'exclude_from_search' => true,
			'has_archive'        => false,
			'rewrite'            => false,
			'supports'           => array( 'title' ),
			'menu_icon'          => 'dashicons-megaphone',
		);
	}
}
