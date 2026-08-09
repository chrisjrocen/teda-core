<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Forms;

use Teda_Core\Support\Bootable;

/**
 * Locks Fluent Forms — its admin pages, its entries, its REST endpoints — to
 * Administrators only (SPEC §8.2: "form entries visible to Administrators only.
 * Volunteers with the Editor role must not see the membership list").
 *
 * Fluent Forms resolves the current user's capability through
 * `fluentform/current_user_capability`; whatever it would otherwise grant a
 * non-admin (via the _fluentform_form_permission option, which a future plugin
 * update or a careless click could widen), we clamp to `false` for anyone who
 * cannot `manage_options`. Administrators are unaffected. This is belt-and-braces
 * over Fluent Forms' own default, so the membership list cannot leak to Editors
 * even if the permission option changes.
 */
final class Access implements Bootable {

	public function register(): void {
		add_filter( 'fluentform/current_user_capability', array( $this, 'clamp_capability' ) );
	}

	/**
	 * Deny the Fluent Forms capability to any user who is not an Administrator.
	 *
	 * @param mixed $capability The capability Fluent Forms resolved (string) or false.
	 * @return mixed
	 */
	public function clamp_capability( $capability ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return $capability;
	}
}
