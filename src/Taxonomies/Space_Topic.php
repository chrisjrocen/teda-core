<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Taxonomies;

/**
 * Space Topic (SPEC §5.1). Terms added by editors; used for the Spaces archive
 * filter chips (P19).
 */
final class Space_Topic extends Abstract_Taxonomy {

	public function key(): string {
		return 'space_topic';
	}

	protected function object_types(): array {
		return array( 'teda_space' );
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Space Topic', 'teda-core' ),
			'plural'   => __( 'Space Topics', 'teda-core' ),
		);
	}
}
