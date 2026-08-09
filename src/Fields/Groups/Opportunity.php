<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields\Groups;

/**
 * Opportunity fields (SPEC §5.1). Role type is a taxonomy (P02); the rest is
 * meta. Auto-closes at the deadline via cron (P14) by flipping teda_is_open.
 */
final class Opportunity {

	/**
	 * @return array<string, mixed>
	 */
	public static function definition(): array {
		return array(
			'id'           => 'teda_opportunity_details',
			'title'        => __( 'Opportunity details', 'teda-core' ),
			'post_types'   => array( 'teda_opportunity' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'show_in_rest' => true,
			'fields'       => array(
				array(
					'name'         => __( 'Location', 'teda-core' ),
					'id'           => 'teda_location',
					'type'         => 'text',
					'show_in_rest' => true,
					'desc'         => __( 'Where is this role based? e.g. Soroti, or “Remote”.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Time commitment', 'teda-core' ),
					'id'           => 'teda_commitment',
					'type'         => 'text',
					'show_in_rest' => true,
					'desc'         => __( 'Roughly how much time it takes. e.g. 5 hours a week.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Application deadline', 'teda-core' ),
					'id'           => 'teda_deadline',
					'type'         => 'date',
					'timestamp'    => true,
					'show_in_rest' => true,
					'desc'         => __( 'The last day to apply. After this date the role closes on its own and moves to “Recently closed”.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Open for applications', 'teda-core' ),
					'id'           => 'teda_is_open',
					'type'         => 'switch',
					'style'        => 'rounded',
					'std'          => 1,
					'show_in_rest' => true,
					'desc'         => __( 'On means people can apply now. This turns off automatically once the deadline passes.', 'teda-core' ),
				),
			),
		);
	}
}
