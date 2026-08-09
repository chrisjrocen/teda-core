<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields\Groups;

use Teda_Core\Fields\Presets;

/**
 * Focus Area fields (SPEC §5.1). Icon + accent colour + summary + order.
 * Colour is never the only signal — the icon and label always show (SPEC §3.1).
 */
final class Focus_Area {

	/**
	 * @return array<string, mixed>
	 */
	public static function definition(): array {
		return array(
			'id'           => 'teda_focus_area_details',
			'title'        => __( 'Focus area details', 'teda-core' ),
			'post_types'   => array( 'teda_focus_area' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'show_in_rest' => true,
			'fields'       => array(
				array(
					'name'         => __( 'Icon', 'teda-core' ),
					'id'           => 'teda_icon',
					'type'         => 'select',
					'placeholder'  => __( 'Choose an icon', 'teda-core' ),
					'options'      => array(
						'education'        => __( 'Education (book)', 'teda-core' ),
						'climate'          => __( 'Climate (leaf)', 'teda-core' ),
						'health'           => __( 'Health (heart)', 'teda-core' ),
						'entrepreneurship' => __( 'Entrepreneurship (lightbulb)', 'teda-core' ),
						'leadership'       => __( 'Leadership (people)', 'teda-core' ),
						'culture'          => __( 'Culture (shield)', 'teda-core' ),
					),
					'show_in_rest' => true,
					'desc'         => __( 'Pick the icon that best matches this focus area. It appears on the card next to the title.', 'teda-core' ),
				),
				Presets::accent_color(),
				array(
					'name'         => __( 'Short summary', 'teda-core' ),
					'id'           => 'teda_summary',
					'type'         => 'textarea',
					'rows'         => 3,
					'show_in_rest' => true,
					'desc'         => __( 'One or two sentences describing this focus area. Shown on the card and at the top of the focus-area page.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Order', 'teda-core' ),
					'id'           => 'teda_order',
					'type'         => 'number',
					'min'          => 1,
					'std'          => 10,
					'show_in_rest' => true,
					'desc'         => __( 'Where this appears in the list. 1 shows first, then 2, 3, and so on.', 'teda-core' ),
				),
			),
		);
	}
}
