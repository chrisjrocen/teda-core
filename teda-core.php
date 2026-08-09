<?php
/**
 * Plugin Name:       TEDA Core
 * Plugin URI:        https://tedauganda.org
 * Description:       The TEDA content model — custom post types, taxonomies, meta fields, dynamic blocks, cron and redirects. Lives in a plugin (not the theme) so content survives a theme change. See SPEC.md §5 and PROMPTS.md D2/C10.
 * Version:           0.0.1
 * Requires PHP:      8.1
 * Requires at least: 6.7
 * Author:            TEDA
 * Text Domain:       teda-core
 * License:           GPL-2.0-or-later
 *
 * @package Teda_Core
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// P01 replaces this stub with the plugin header constants, PSR-4 autoloader and
// the Plugin singleton that boots the subsystems. P00 ships an inert plugin so it
// is visible in `wp plugin list` and can be activated without side effects.
