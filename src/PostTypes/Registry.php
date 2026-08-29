<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\PostTypes;

use Teda_Core\Support\Bootable;

/**
 * Registers every TEDA post type on `init`. Adding a CPT later is one new class
 * appended to POST_TYPES — no changes to this loop.
 */
final class Registry implements Bootable {

	/**
	 * @var array<int, class-string<Abstract_Post_Type>>
	 */
	private const POST_TYPES = array(
		Event::class,
		Focus_Area::class,
		Opportunity::class,
		Team::class,
		Space::class,
		Publication::class,
		Campaign::class,
	);

	public function register(): void {
		// Post types register on init, before taxonomies (Taxonomies\Registry
		// boots after this one, so its init callback runs after these exist).
		add_action( 'init', array( $this, 'register_post_types' ), 10 );

		// Native News categories are seeded on version upgrade / activation.
		add_action( 'teda_core/upgrade', array( News_Categories::class, 'seed' ) );
	}

	public function register_post_types(): void {
		foreach ( self::POST_TYPES as $class ) {
			( new $class() )->register();
		}
	}
}
