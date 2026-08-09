<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\PostTypes;

/**
 * Opportunities — TEDA volunteer & leadership roles (SPEC §5.1). Archive nested
 * under Get Involved at /get-involved/opportunities/ (§4.1). Auto-closes at its
 * deadline via cron (P14).
 */
final class Opportunity extends Abstract_Post_Type {

	public function key(): string {
		return 'teda_opportunity';
	}

	protected function names(): array {
		return array(
			'singular' => __( 'Opportunity', 'teda-core' ),
			'plural'   => __( 'Opportunities', 'teda-core' ),
		);
	}

	protected function args(): array {
		return array(
			'menu_icon'     => 'dashicons-megaphone',
			'menu_position' => 23,
			'has_archive'   => 'get-involved/opportunities',
			'rewrite'       => array(
				'slug'       => 'get-involved/opportunities',
				'with_front' => false,
			),
		);
	}
}
