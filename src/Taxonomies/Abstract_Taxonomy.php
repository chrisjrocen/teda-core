<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Taxonomies;

use Teda_Core\Support\Registrable;

/**
 * Base for TEDA taxonomies. Subclasses declare the key, the object types they
 * attach to, plain-English names and any overrides; this assembles labels and
 * defaults, applies the per-taxonomy filter, and registers.
 *
 * Filterable via `teda_core/taxonomy/{key}/args`.
 */
abstract class Abstract_Taxonomy implements Registrable {

	/**
	 * Taxonomy key, e.g. `event_type`.
	 */
	abstract public function key(): string;

	/**
	 * Object types this taxonomy is attached to.
	 *
	 * @return array<int, string>
	 */
	abstract protected function object_types(): array;

	/**
	 * @return array{singular:string, plural:string}
	 */
	abstract protected function names(): array;

	/**
	 * Argument overrides merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	protected function args(): array {
		return array();
	}

	/**
	 * Default terms to seed idempotently (SPEC §5.1 lists fixed sets for some
	 * taxonomies). Empty means "let volunteers add their own".
	 *
	 * @return array<int, string>
	 */
	public function default_terms(): array {
		return array();
	}

	/**
	 * Ensure the declared default terms exist. Safe to run repeatedly.
	 */
	public function seed_terms(): void {
		foreach ( $this->default_terms() as $term ) {
			if ( ! term_exists( $term, $this->key() ) ) {
				wp_insert_term( $term, $this->key() );
			}
		}
	}

	public function register(): void {
		$args           = array_replace( $this->default_args(), $this->args() );
		$args['labels'] = $this->build_labels();

		/**
		 * Filter the registration args for a TEDA taxonomy.
		 *
		 * @param array<string, mixed> $args Registration args.
		 */
		$args = apply_filters( "teda_core/taxonomy/{$this->key()}/args", $args );

		register_taxonomy( $this->key(), $this->object_types(), $args );
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function default_args(): array {
		return array(
			'public'            => true,
			'show_in_rest'      => true,
			'hierarchical'      => true, // Checkbox UI — simpler for volunteers.
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'rewrite'           => array(
				'slug'       => str_replace( '_', '-', $this->key() ),
				'with_front' => false,
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	protected function build_labels(): array {
		$names    = $this->names();
		$singular = $names['singular'];
		$plural   = $names['plural'];

		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
			/* translators: %s: plural taxonomy name. */
			'all_items'     => sprintf( __( 'All %s', 'teda-core' ), $plural ),
			/* translators: %s: singular taxonomy name. */
			'edit_item'     => sprintf( __( 'Edit %s', 'teda-core' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'view_item'     => sprintf( __( 'View %s', 'teda-core' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'add_new_item'  => sprintf( __( 'Add New %s', 'teda-core' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'new_item_name' => sprintf( __( 'New %s Name', 'teda-core' ), $singular ),
			/* translators: %s: plural taxonomy name. */
			'search_items'  => sprintf( __( 'Search %s', 'teda-core' ), $plural ),
			/* translators: %s: plural taxonomy name, lowercased. */
			'not_found'     => sprintf( __( 'No %s found', 'teda-core' ), strtolower( $plural ) ),
		);
	}
}
