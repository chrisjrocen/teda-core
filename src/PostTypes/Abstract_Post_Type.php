<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\PostTypes;

use Teda_Core\Support\Registrable;

/**
 * Base for TEDA custom post types. Subclasses declare the post-type key, their
 * plain-English labels, and any argument overrides; this class assembles the
 * full label set and default args, applies the per-type filter, and registers.
 *
 * Every registration is filterable via `teda_core/post_type/{key}/args` where
 * {key} is the post-type key (e.g. `teda_event`).
 */
abstract class Abstract_Post_Type implements Registrable {

	/**
	 * The post-type key, e.g. `teda_event`.
	 */
	abstract public function key(): string;

	/**
	 * Plain-English names: ['singular' => 'Event', 'plural' => 'Events'].
	 * Labels read as "Events", never "Teda Events" (P02 task).
	 *
	 * @return array{singular:string, plural:string}
	 */
	abstract protected function names(): array;

	/**
	 * Type-specific argument overrides merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function args(): array;

	public function register(): void {
		$args           = array_replace( $this->default_args(), $this->args() );
		$args['labels'] = $this->build_labels();

		/**
		 * Filter the registration args for a TEDA post type.
		 *
		 * @param array<string, mixed> $args Registration args.
		 */
		$args = apply_filters( "teda_core/post_type/{$this->key()}/args", $args );

		register_post_type( $this->key(), $args );
	}

	/**
	 * Sensible defaults shared by every TEDA CPT. Subclasses override per §4.1.
	 *
	 * @return array<string, mixed>
	 */
	protected function default_args(): array {
		return array(
			'public'             => true,
			'show_in_rest'       => true, // Gutenberg + block meta reads (P03/P07).
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-admin-post',
			'rewrite'            => array(
				'slug'       => $this->key(),
				'with_front' => false,
			),
			'show_in_nav_menus'  => true,
			'publicly_queryable' => true,
		);
	}

	/**
	 * Full label set generated from the singular/plural names. Kept plain and
	 * translatable (house rule 5).
	 *
	 * @return array<string, string>
	 */
	protected function build_labels(): array {
		$names    = $this->names();
		$singular = $names['singular'];
		$plural   = $names['plural'];

		return array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			'menu_name'             => $plural,
			'name_admin_bar'        => $singular,
			/* translators: %s: singular post type name. */
			'add_new'               => __( 'Add New', 'teda-core' ),
			/* translators: %s: singular post type name. */
			'add_new_item'          => sprintf( __( 'Add New %s', 'teda-core' ), $singular ),
			/* translators: %s: singular post type name. */
			'edit_item'             => sprintf( __( 'Edit %s', 'teda-core' ), $singular ),
			/* translators: %s: singular post type name. */
			'new_item'              => sprintf( __( 'New %s', 'teda-core' ), $singular ),
			/* translators: %s: singular post type name. */
			'view_item'             => sprintf( __( 'View %s', 'teda-core' ), $singular ),
			/* translators: %s: plural post type name. */
			'view_items'            => sprintf( __( 'View %s', 'teda-core' ), $plural ),
			/* translators: %s: plural post type name. */
			'search_items'          => sprintf( __( 'Search %s', 'teda-core' ), $plural ),
			/* translators: %s: plural post type name, lowercased. */
			'not_found'             => sprintf( __( 'No %s found', 'teda-core' ), strtolower( $plural ) ),
			/* translators: %s: plural post type name, lowercased. */
			'not_found_in_trash'    => sprintf( __( 'No %s found in Trash', 'teda-core' ), strtolower( $plural ) ),
			/* translators: %s: plural post type name. */
			'all_items'             => sprintf( __( 'All %s', 'teda-core' ), $plural ),
			/* translators: %s: plural post type name, lowercased. */
			'archives'              => sprintf( __( '%s Archives', 'teda-core' ), $singular ),
			'featured_image'        => __( 'Featured image', 'teda-core' ),
			'set_featured_image'    => __( 'Set featured image', 'teda-core' ),
			'remove_featured_image' => __( 'Remove featured image', 'teda-core' ),
			'use_featured_image'    => __( 'Use as featured image', 'teda-core' ),
		);
	}
}
