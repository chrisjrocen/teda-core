<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Taxonomies;

/**
 * Role Type for opportunities — Volunteer, Committee, Chapter Lead, Internship
 * (SPEC §5.1).
 */
final class Role_Type extends Abstract_Taxonomy {

	public function key(): string {
		return 'role_type';
	}

	protected function object_types(): array {
		return array( 'teda_opportunity' );
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Role Type', 'teda-core' ),
			'plural'   => __( 'Role Types', 'teda-core' ),
		);
	}

	public function default_terms(): array {
		return array( 'Volunteer', 'Committee', 'Chapter Lead', 'Internship' );
	}
}
