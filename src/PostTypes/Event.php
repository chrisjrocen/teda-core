<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\PostTypes;

/**
 * Events (SPEC §5.1). Archive at /events/, singles at /events/<slug>/ (§4.1).
 */
final class Event extends Abstract_Post_Type {

	public function key(): string {
		return 'teda_event';
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Event', 'teda-core' ),
			'plural'   => __( 'Events', 'teda-core' ),
		);
	}

	protected function args(): array {
		return array(
			'menu_icon'     => 'dashicons-calendar-alt',
			'menu_position' => 21,
			'has_archive'   => 'events',
			'rewrite'       => array(
				'slug'       => 'events',
				'with_front' => false,
			),
		);
	}
}
