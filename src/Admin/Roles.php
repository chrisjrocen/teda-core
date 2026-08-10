<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Admin;

use Teda_Core\Support\Bootable;
use WP_Role;

/**
 * Editor-role lockdown (P18, SPEC §5.2 and §8.2).
 *
 * TEDA's maintainers are non-technical volunteers on the Editor role. Two things
 * keep them safe and keep the site intact:
 *
 *  1. **Capabilities** — an Editor must never be able to install plugins, edit
 *     theme or plugin files, change site options, or reach the Customizer (whose
 *     structural panels could dismantle the header/footer built in P06). WordPress
 *     already withholds most of these from the Editor role; we strip them
 *     explicitly so the intent is recorded and survives a plugin that tries to
 *     widen the role. Removing `edit_theme_options` is what closes the Customizer
 *     to Editors entirely — there is no "structural panels only" capability, so
 *     the whole Customizer is the correct boundary for a content volunteer.
 *
 *  2. **Admin menus** — hide the top-level menus a content volunteer never needs
 *     (comments are off; SEO, forms, image optimisation and backups are
 *     Administrator tasks). Fluent Forms in particular is hidden here so the
 *     membership list is unreachable from the menu, complementing the capability
 *     clamp in Forms\Access (SPEC §8.2: "form entries visible to Administrators
 *     only").
 *
 * Capability changes are written to the stored Editor role once, on the
 * `teda_core/upgrade` hook (fired on activation and on every version bump), so
 * they are not re-applied on every request. The menu hiding runs per-request on
 * `admin_menu` because menus are registered per-request by their plugins.
 */
final class Roles implements Bootable {

	/**
	 * Capabilities an Editor must not hold. Most are already absent on a stock
	 * Editor role; stripping them explicitly documents the boundary and undoes any
	 * plugin that granted them.
	 *
	 * @var array<int, string>
	 */
	private const DENIED_EDITOR_CAPS = array(
		// Plugins.
		'install_plugins',
		'activate_plugins',
		'update_plugins',
		'delete_plugins',
		'edit_plugins',
		// Themes + the Customizer (edit_theme_options gates the whole Customizer).
		'install_themes',
		'switch_themes',
		'update_themes',
		'delete_themes',
		'edit_themes',
		'edit_theme_options',
		// Files + core.
		'edit_files',
		'update_core',
		// Site-wide settings and the tools that move content wholesale.
		'manage_options',
		'export',
		'import',
	);

	/**
	 * Top-level admin-menu slug prefixes to hide from anyone who is not an
	 * Administrator. Matched as a prefix so a plugin that varies its slug (Rank
	 * Math shows `rank-math` once set up, `rank-math-registration` before) is
	 * hidden in every state. A denylist (not an allowlist) so the CPT menus TEDA
	 * volunteers DO need — Events, News, Team, Opportunities, Gallery — are never
	 * accidentally hidden.
	 *
	 * @var array<int, string>
	 */
	private const EDITOR_HIDDEN_MENUS = array(
		'edit-comments.php',            // Comments are disabled site-wide.
		'fluent_forms',                 // Forms builder + entries — Administrators only (SPEC §8.2).
		'rank-math',                    // SEO is site-wide config, an Administrator task.
		'ewww-image-optimizer',         // Bulk WebP is an Administrator task.
		'updraftplus',                  // Backups are an Administrator task.
		'limit-login-attempts',         // Security settings.
	);

	public function register(): void {
		add_action( 'teda_core/upgrade', array( $this, 'harden_editor_role' ) );
		add_action( 'admin_menu', array( $this, 'hide_editor_menus' ), 999 );
	}

	/**
	 * Strip the denied capabilities from the stored Editor role. Idempotent:
	 * removing a capability the role does not have is a no-op.
	 */
	public function harden_editor_role(): void {
		$editor = get_role( 'editor' );
		if ( ! $editor instanceof WP_Role ) {
			return;
		}

		foreach ( self::DENIED_EDITOR_CAPS as $cap ) {
			if ( $editor->has_cap( $cap ) ) {
				$editor->remove_cap( $cap );
			}
		}
	}

	/**
	 * Hide the top-level menus a content volunteer does not need. Administrators
	 * (manage_options) see everything; everyone else gets the trimmed menu. Slugs
	 * are matched as prefixes against the live menu so plugins that vary their slug
	 * are removed whatever state they are in.
	 */
	public function hide_editor_menus(): void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		global $menu;
		if ( ! is_array( $menu ) ) {
			return;
		}

		foreach ( $menu as $item ) {
			$slug = $item[2] ?? '';
			if ( ! is_string( $slug ) || '' === $slug ) {
				continue;
			}
			foreach ( self::EDITOR_HIDDEN_MENUS as $prefix ) {
				if ( 0 === strpos( $slug, $prefix ) ) {
					remove_menu_page( $slug );
					break;
				}
			}
		}
	}
}
