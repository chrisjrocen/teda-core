<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Support;

/**
 * A subsystem the Plugin boots. Each registers its own hooks in register().
 * Keeping this contract lets Plugin hold a single ordered list (SPEC §5 pipeline:
 * post types → taxonomies → fields → blocks → cron → redirects → admin) that
 * later prompts extend by dropping in a class — no edits to the boot loop.
 */
interface Bootable {

	/**
	 * Register WordPress hooks for this subsystem. Called once, on every request,
	 * after the autoloader and constants are ready.
	 */
	public function register(): void;
}
