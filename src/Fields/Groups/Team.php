<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields\Groups;

/**
 * Team fields (SPEC §5.1). The member’s photo is the post’s Featured image (one
 * obvious place, not a second uploader). Social links use a free key-value field
 * (platform → URL) since Meta Box free has no cloneable groups (C3 note).
 */
final class Team {

	/**
	 * @return array<string, mixed>
	 */
	public static function definition(): array {
		return array(
			'id'           => 'teda_team_details',
			'title'        => __( 'Team member details', 'teda-core' ),
			'post_types'   => array( 'teda_team' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'show_in_rest' => true,
			'fields'       => array(
				array(
					'name'         => __( 'Role or title', 'teda-core' ),
					'id'           => 'teda_role_title',
					'type'         => 'text',
					'show_in_rest' => true,
					'desc'         => __( 'Their position at TEDA. e.g. Chairperson, or Programmes Lead.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Short bio', 'teda-core' ),
					'id'           => 'teda_bio',
					'type'         => 'textarea',
					'rows'         => 4,
					'show_in_rest' => true,
					'desc'         => __( 'A few sentences about this person. Keep it short — it shows on the About page.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Order', 'teda-core' ),
					'id'           => 'teda_order',
					'type'         => 'number',
					'min'          => 1,
					'std'          => 10,
					'show_in_rest' => true,
					'desc'         => __( 'Position in the team list. 1 shows first. Use this to put leadership at the top.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Social links', 'teda-core' ),
					'id'           => 'teda_social_links',
					'type'         => 'key_value',
					'show_in_rest' => true,
					'desc'         => __( 'Optional. Put the platform on the left and the full web address on the right. e.g. LinkedIn → https://…', 'teda-core' ),
				),
			),
		);
	}
}
