<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Taxonomies;

use Teda_Core\Support\Bootable;

/**
 * Registers every TEDA taxonomy on `init` (after post types) and seeds the
 * fixed default terms on version upgrade / activation.
 */
final class Registry implements Bootable {

	/**
	 * @var array<int, class-string<Abstract_Taxonomy>>
	 */
	private const TAXONOMIES = array(
		Event_Type::class,
		Role_Type::class,
		Space_Topic::class,
		Pub_Type::class,
		Gallery_Album::class,
	);

	public function register(): void {
		// Priority 11: after post types (Registry runs at 10) so object types exist.
		add_action( 'init', array( $this, 'register_taxonomies' ), 11 );
		add_action( 'teda_core/upgrade', array( $this, 'seed_terms' ) );
	}

	public function register_taxonomies(): void {
		foreach ( self::TAXONOMIES as $class ) {
			( new $class() )->register();
		}
	}

	/**
	 * Seed default terms for taxonomies that declare them (Event_Type, Role_Type).
	 * Requires the taxonomies to be registered first; on the upgrade/activation
	 * path they are (activation calls register_subsystems before run_migrations
	 * fires this action... see note below).
	 */
	public function seed_terms(): void {
		foreach ( self::TAXONOMIES as $class ) {
			$taxonomy = new $class();
			// Guard: only seed once the taxonomy is registered, else wp_insert_term
			// would fail silently.
			if ( taxonomy_exists( $taxonomy->key() ) ) {
				$taxonomy->seed_terms();
			}
		}
	}
}
