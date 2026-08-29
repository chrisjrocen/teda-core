<?php
/**
 * Plugin Name:       TEDA Core
 * Plugin URI:        https://tedauganda.org
 * Description:       The TEDA content model — custom post types, taxonomies, meta fields, dynamic blocks, cron and redirects. Lives in a plugin (not the theme) so content survives a theme change. See SPEC.md §5 and PROMPTS.md D2/C10.
 * Version:           0.4.0
 * Requires PHP:      8.1
 * Requires at least: 6.7
 * Author:            TEDA
 * Text Domain:       teda-core
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Teda_Core
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constants. Every later prompt relies on these.
 */
define( 'TEDA_CORE_VERSION', '0.4.0' );
define( 'TEDA_CORE_FILE', __FILE__ );
define( 'TEDA_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TEDA_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * The canonical production host (SPEC §4.1, P16). Every canonical URL and Open
 * Graph tag points here, regardless of the host WordPress currently runs on, so
 * search engines are never told about localhost or a staging domain.
 *
 * ⚠ Blocker B1: `tedauganda.org` is NOT registered yet. This is the ONE edit for
 * cutover — change it here (and see docs/CUTOVER.md) once the domain resolves.
 * Set to '' to fall back to WordPress's own home URL (useful on staging).
 */
if ( ! defined( 'TEDA_CANONICAL_HOST' ) ) {
	define( 'TEDA_CANONICAL_HOST', 'https://tedauganda.org' );
}

/**
 * Minimum runtime. If unmet we never boot — we self-deactivate with an
 * explanatory notice instead of fataling (house rule 13, P01 task 7).
 */
define( 'TEDA_CORE_MIN_PHP', '8.1' );
define( 'TEDA_CORE_MIN_WP', '6.7' );

/**
 * PSR-4 autoloader for the Teda_Core\ namespace → src/.
 *
 * Deliberately a closure, not a class: it must load before any class exists,
 * and it has no Composer runtime dependency (P01 task 2). Any class in a fresh
 * subdirectory resolves without registration, e.g.
 * Teda_Core\Support\Env → src/Support/Env.php.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix   = 'Teda_Core\\';
		$base_dir = TEDA_CORE_PATH . 'src/';

		$len = strlen( $prefix );
		if ( 0 !== strncmp( $prefix, $class, $len ) ) {
			return;
		}

		$relative = substr( $class, $len );
		$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

/**
 * Runtime guard. Returns an error string if the environment is unsupported,
 * or null if all good. Kept procedural so it works even if autoloading were to
 * fail for any reason.
 */
function teda_core_runtime_error(): ?string {
	if ( version_compare( PHP_VERSION, TEDA_CORE_MIN_PHP, '<' ) ) {
		return sprintf(
			/* translators: 1: required PHP version, 2: current PHP version. */
			__( 'TEDA Core requires PHP %1$s or newer. This server runs PHP %2$s.', 'teda-core' ),
			TEDA_CORE_MIN_PHP,
			PHP_VERSION
		);
	}

	global $wp_version;
	if ( version_compare( (string) $wp_version, TEDA_CORE_MIN_WP, '<' ) ) {
		return sprintf(
			/* translators: 1: required WordPress version, 2: current WordPress version. */
			__( 'TEDA Core requires WordPress %1$s or newer. This site runs WordPress %2$s.', 'teda-core' ),
			TEDA_CORE_MIN_WP,
			(string) $wp_version
		);
	}

	return null;
}

/**
 * Boot. If the runtime is unsupported, self-deactivate cleanly and show why.
 */
$teda_core_error = teda_core_runtime_error();

if ( null !== $teda_core_error ) {
	add_action(
		'admin_init',
		static function () {
			deactivate_plugins( plugin_basename( TEDA_CORE_FILE ) );
			unset( $_GET['activate'] ); // Suppress the "Plugin activated" notice. phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	);
	add_action(
		'admin_notices',
		static function () use ( $teda_core_error ) {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'TEDA Core could not start.', 'teda-core' ),
				esc_html( $teda_core_error )
			);
		}
	);
	return;
}

// Global template/block helpers (teda_field, etc.). Functions are not
// autoloaded, so require the file directly.
require_once TEDA_CORE_PATH . 'src/helpers.php';

// Activation / deactivation lifecycle (never deletes content — P01 task 4).
register_activation_hook( __FILE__, array( \Teda_Core\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Teda_Core\Plugin::class, 'deactivate' ) );

// Go.
\Teda_Core\Plugin::instance()->boot();
