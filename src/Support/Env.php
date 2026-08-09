<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Support;

use Teda_Core\Admin\Notices;

/**
 * Environment / dependency checks. Each capability question is a boolean method
 * usable anywhere in the plugin (so a template or block can degrade gracefully),
 * plus — in admin only — a dismissible notice naming exactly what is missing and
 * how to fix it (P01 task 6, house rule 13).
 */
final class Env implements Bootable {

	public function register(): void {
		// Evaluate on admin_init: by now every subsystem (including the Notices
		// bag) has registered, and this fires before admin_notices renders.
		add_action( 'admin_init', array( $this, 'evaluate' ) );
	}

	/**
	 * Is Meta Box active? Field groups (P03) depend on it.
	 */
	public function is_meta_box_active(): bool {
		return function_exists( 'rwmb_meta' ) || class_exists( 'RWMB_Loader' );
	}

	/**
	 * Is Fluent Forms active? Forms and soft capacity (P13) depend on it.
	 */
	public function is_fluent_forms_active(): bool {
		return function_exists( 'wpFluentForm' ) || defined( 'FLUENTFORM_VERSION' );
	}

	/**
	 * Is the teda-child theme active? It renders this plugin's content (D2).
	 * Not fatal if absent — Blocksy still renders core content.
	 */
	public function is_child_theme_active(): bool {
		return 'teda-child' === get_stylesheet();
	}

	/**
	 * Queue an admin notice for each missing dependency. Kept to `warning` (not
	 * `error`) because the plugin degrades rather than fails.
	 */
	public function evaluate(): void {
		if ( ! $this->is_meta_box_active() ) {
			Notices::add(
				__( 'The Meta Box plugin is not active, so event, opportunity and other custom fields will not appear. Install and activate “Meta Box” (free) from Plugins → Add New.', 'teda-core' ),
				'warning'
			);
		}

		if ( ! $this->is_fluent_forms_active() ) {
			Notices::add(
				__( 'The Fluent Forms plugin is not active, so the Join, Event registration and Contact forms — and event capacity counts — will not work. Install and activate “Fluent Forms” from Plugins → Add New.', 'teda-core' ),
				'warning'
			);
		}

		if ( ! $this->is_child_theme_active() ) {
			Notices::add(
				__( 'The TEDA Child theme is not active, so the site will not use TEDA’s colours, fonts and layouts. Activate “TEDA Child” under Appearance → Themes.', 'teda-core' ),
				'info'
			);
		}
	}
}
