<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core;

use Teda_Core\Support\Bootable;

/**
 * The plugin singleton. Boots subsystems in a documented, fixed order and owns
 * the activation / deactivation / upgrade lifecycle.
 */
final class Plugin {

	/**
	 * The name of the daily cron hook. Subsystems (P14) attach to it; this class
	 * only schedules and clears it.
	 */
	public const CRON_DAILY = 'teda_core_daily';

	/**
	 * Option storing the last-booted plugin version, for idempotent upgrades.
	 */
	private const VERSION_OPTION = 'teda_core_version';

	/**
	 * Subsystem boot order (SPEC §5 pipeline). Later prompts append their classes
	 * here; only classes that exist and implement Bootable are booted, so the list
	 * can name the full pipeline before every piece is built.
	 *
	 * Order: post types → taxonomies → fields → blocks → cron → redirects → admin.
	 *
	 * @var array<int, class-string>
	 */
	private const SUBSYSTEMS = array(
		PostTypes\Registry::class,
		Taxonomies\Registry::class,
		// P03: Teda_Core\Fields\Registry::class,
		// P07: Teda_Core\Blocks\Registry::class,
		// P14: Teda_Core\Cron\Scheduler::class,
		// P16: Teda_Core\Redirects\Map::class,
		Support\Env::class,
		Admin\Notices::class,
	);

	/**
	 * Guards register_subsystems() against running twice (it is called on
	 * plugins_loaded and again during activation).
	 */
	private bool $booted = false;

	private static ?Plugin $instance = null;

	/**
	 * Booted subsystem instances, kept so they are not garbage-collected and so
	 * tests can reach them.
	 *
	 * @var array<class-string, Bootable>
	 */
	private array $subsystems = array();

	private function __construct() {}

	/**
	 * Single shared instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register every available subsystem, in order, on `plugins_loaded` so that
	 * dependency plugins (Meta Box, Fluent Forms) have loaded first.
	 */
	public function boot(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'register_subsystems' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );

		// The daily hook must exist on every request or a queued WP-Cron run has
		// nothing to fire. The callback is a no-op until P14 attaches to the action.
		add_action( self::CRON_DAILY, array( $this, 'run_daily' ) );
	}

	/**
	 * Instantiate and register each subsystem that exists and is Bootable.
	 */
	public function register_subsystems(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		foreach ( self::SUBSYSTEMS as $class ) {
			if ( ! class_exists( $class ) || ! is_subclass_of( $class, Bootable::class ) ) {
				continue;
			}

			$subsystem = new $class();
			$subsystem->register();
			$this->subsystems[ $class ] = $subsystem;
		}
	}

	/**
	 * Reach a booted subsystem (used by other subsystems and by tests).
	 *
	 * @param class-string $class Subsystem class name.
	 */
	public function subsystem( string $class ): ?Bootable {
		return $this->subsystems[ $class ] ?? null;
	}

	/**
	 * Load translations. English only, but strings stay extractable (house rule 5).
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'teda-core', false, dirname( plugin_basename( TEDA_CORE_FILE ) ) . '/languages' );
	}

	/**
	 * Fire the shared daily action. No-op until a subsystem hooks it (P14).
	 */
	public function run_daily(): void {
		/**
		 * Daily maintenance hook. P14 attaches opportunity auto-expiry here.
		 */
		do_action( 'teda_core/cron/daily' );
	}

	/* --------------------------------------------------------------------- */
	/* Lifecycle                                                              */
	/* --------------------------------------------------------------------- */

	/**
	 * Activation: schedule cron, record version, flush rewrites LAST so any
	 * post-type rules registered by subsystems (P02) are picked up.
	 *
	 * Never deletes content.
	 */
	public static function activate(): void {
		$plugin = self::instance();

		// During activation the request has already passed `plugins_loaded` and
		// `init`, so nothing has hooked or registered yet. Boot the subsystems
		// (this hooks the term seeders to `teda_core/upgrade`) and register the
		// post types + taxonomies immediately, so both the seeding below and the
		// rewrite flush at the end see them.
		$plugin->register_subsystems();

		$post_types = $plugin->subsystem( PostTypes\Registry::class );
		if ( $post_types instanceof PostTypes\Registry ) {
			$post_types->register_post_types();
		}
		$taxonomies = $plugin->subsystem( Taxonomies\Registry::class );
		if ( $taxonomies instanceof Taxonomies\Registry ) {
			$taxonomies->register_taxonomies();
		}

		// Seed fixed terms + News categories (fires `teda_core/upgrade`).
		$plugin->run_migrations();
		update_option( self::VERSION_OPTION, TEDA_CORE_VERSION );

		if ( ! wp_next_scheduled( self::CRON_DAILY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_DAILY );
		}

		// Post-type rewrite rules now exist, so this captures the archive routes.
		flush_rewrite_rules();
	}

	/**
	 * Deactivation: unschedule cron and flush rewrites. Never deletes content.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( self::CRON_DAILY );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_DAILY );
		}
		wp_clear_scheduled_hook( self::CRON_DAILY );

		flush_rewrite_rules();
	}

	/**
	 * On admin load, run migrations if the stored version is behind. Idempotent.
	 */
	public function maybe_upgrade(): void {
		$stored = (string) get_option( self::VERSION_OPTION, '' );
		if ( TEDA_CORE_VERSION === $stored ) {
			return;
		}

		$this->run_migrations();
		update_option( self::VERSION_OPTION, TEDA_CORE_VERSION );
	}

	/**
	 * Version migrations. Each must be safe to run more than once (idempotent).
	 * Empty at P01 — the mechanism is what matters; later versions add cases.
	 */
	private function run_migrations(): void {
		/**
		 * Fires when teda-core migrates to a new version. Subsystems can hook
		 * their own idempotent data migrations here.
		 *
		 * @param string $to Target version.
		 */
		do_action( 'teda_core/upgrade', TEDA_CORE_VERSION );
	}
}
