<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Support;

/**
 * A thing registered on WordPress `init` (a post type or taxonomy). The
 * PostTypes and Taxonomies registries hold ordered lists of these and call
 * register() on each — so adding a CPT later is one new class, no loop edits.
 */
interface Registrable {

	/**
	 * Register the object with WordPress. Called on `init`.
	 */
	public function register(): void;
}
