<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields\Groups;

/**
 * Campaign fields — everything the old teda/donate block attributes used to
 * carry (lead copy, section toggles, channel details, trust copy) plus
 * scheduling and the "default campaign" flag. Amount tiers and donation goals
 * are NOT here — Meta Box's free edition has no cloneable group fields, so
 * those repeatable lists are a hand-rolled meta box instead
 * (Teda_Core\Admin\Campaign_Repeater), stored as plain post meta arrays
 * (`teda_tiers` / `teda_goals`) read directly by Donations\Campaigns_Repository.
 */
final class Campaign {

	/**
	 * @return array<string, mixed>
	 */
	public static function definition(): array {
		return array(
			'id'           => 'teda_campaign_details',
			'title'        => __( 'Campaign content', 'teda-core' ),
			'post_types'   => array( 'teda_campaign' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'show_in_rest' => true,
			'fields'       => array(
				array(
					'name' => __( 'Lead paragraph', 'teda-core' ),
					'id'   => 'teda_lead',
					'type' => 'textarea',
					'std'  => __( 'Every contribution goes directly into education, health, climate action, and youth leadership across the Teso sub-region.', 'teda-core' ),
					'desc' => __( 'The intro sentence shown at the top of the donate page for this campaign.', 'teda-core' ),
				),
				array(
					'name'    => __( 'Default currency', 'teda-core' ),
					'id'      => 'teda_currency_default',
					'type'    => 'select',
					'options' => array(
						'UGX' => 'UGX',
						'USD' => 'USD',
					),
					'std'     => 'UGX',
					'desc'    => __( 'Which currency is pre-selected when a visitor first sees this campaign.', 'teda-core' ),
				),

				array(
					'name'  => __( 'Show donation goals', 'teda-core' ),
					'id'    => 'teda_show_projects',
					'type'  => 'switch',
					'style' => 'rounded',
					'std'   => 1,
				),
				array(
					'name'  => __( 'Show the anti-fraud note', 'teda-core' ),
					'id'    => 'teda_show_antifraud',
					'type'  => 'switch',
					'style' => 'rounded',
					'std'   => 1,
				),
				array(
					'name'  => __( 'Show the trust strip', 'teda-core' ),
					'id'    => 'teda_show_trust_strip',
					'type'  => 'switch',
					'style' => 'rounded',
					'std'   => 1,
				),
				array(
					'name'  => __( 'Show "other ways to give"', 'teda-core' ),
					'id'    => 'teda_show_other_ways',
					'type'  => 'switch',
					'style' => 'rounded',
					'std'   => 1,
				),

				array(
					'name' => __( 'WhatsApp number', 'teda-core' ),
					'id'   => 'teda_whatsapp',
					'type' => 'text',
					'std'  => '256700000000',
					'desc' => __( 'Digits only, with country code — used to build the "Tell us on WhatsApp" link.', 'teda-core' ),
				),
				array(
					'name' => __( 'Email address', 'teda-core' ),
					'id'   => 'teda_email',
					'type' => 'text',
					'std'  => 'tedayouthteso@gmail.com',
				),
				array(
					'name' => __( 'MTN Mobile Money details', 'teda-core' ),
					'id'   => 'teda_mtn',
					'type' => 'text',
				),
				array(
					'name' => __( 'Airtel Money details', 'teda-core' ),
					'id'   => 'teda_airtel',
					'type' => 'text',
				),
				array(
					'name' => __( 'Bank transfer details', 'teda-core' ),
					'id'   => 'teda_bank',
					'type' => 'textarea',
				),

				array(
					'name' => __( 'Registration statement', 'teda-core' ),
					'id'   => 'teda_registration',
					'type' => 'textarea',
					'desc' => __( 'Trust strip: who TEDA is registered as.', 'teda-core' ),
				),
				array(
					'name' => __( 'Accountability statement', 'teda-core' ),
					'id'   => 'teda_accountability',
					'type' => 'textarea',
					'desc' => __( 'Trust strip: where the money goes.', 'teda-core' ),
				),
				array(
					'name' => __( 'Anti-fraud note', 'teda-core' ),
					'id'   => 'teda_antifraud',
					'type' => 'textarea',
					'desc' => __( 'Shown verbatim in a safety callout — not softened (see Donate.php doc comment).', 'teda-core' ),
				),

				array(
					'name'  => __( 'Default campaign', 'teda-core' ),
					'id'    => 'teda_is_default',
					'type'  => 'switch',
					'style' => 'rounded',
					'std'   => 0,
					'desc'  => __( 'A teda/donate block with no campaign picked — or whose picked campaign is not currently scheduled — falls back to whichever campaign has this on. Turning it on here automatically turns it off for every other campaign.', 'teda-core' ),
				),
				array(
					'name'      => __( 'Starts', 'teda-core' ),
					'id'        => 'teda_campaign_start',
					'type'      => 'date',
					'timestamp' => true,
					'desc'      => __( 'Optional. Leave blank for no start limit — the campaign is eligible immediately.', 'teda-core' ),
				),
				array(
					'name'      => __( 'Ends', 'teda-core' ),
					'id'        => 'teda_campaign_end',
					'type'      => 'date',
					'timestamp' => true,
					'desc'      => __( 'Optional. Leave blank for no end limit.', 'teda-core' ),
				),
			),
		);
	}
}
