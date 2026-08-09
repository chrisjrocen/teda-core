<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Taxonomies;

/**
 * Event Type — Forum, Bootcamp, Community Drive, Training (SPEC §5.1).
 */
final class Event_Type extends Abstract_Taxonomy {

	public function key(): string {
		return 'event_type';
	}

	protected function object_types(): array {
		return array( 'teda_event' );
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Event Type', 'teda-core' ),
			'plural'   => __( 'Event Types', 'teda-core' ),
		);
	}

	public function default_terms(): array {
		return array( 'Forum', 'Bootcamp', 'Community Drive', 'Training' );
	}
}
