<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Taxonomies;

/**
 * Publication Type (SPEC §5.1) — e.g. Annual Report, Financial Statement,
 * Policy Brief. Terms added by editors; drives the Publications archive filter
 * (P21).
 */
final class Pub_Type extends Abstract_Taxonomy {

	public function key(): string {
		return 'pub_type';
	}

	protected function object_types(): array {
		return array( 'teda_publication' );
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Publication Type', 'teda-core' ),
			'plural'   => __( 'Publication Types', 'teda-core' ),
		);
	}
}
