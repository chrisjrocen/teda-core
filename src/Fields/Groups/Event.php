<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields\Groups;

use Teda_Core\Fields\Presets;

/**
 * Event fields (SPEC §5.1 + C7 capacity + agenda). Every field carries a
 * plain-English label and one-line description written for a volunteer who has
 * never used a CMS. Nothing is required at the database level, so a half-finished
 * draft always saves (P03 constraint).
 */
final class Event {

	/**
	 * @return array<string, mixed>
	 */
	public static function definition(): array {
		return array(
			'id'           => 'teda_event_details',
			'title'        => __( 'Event details', 'teda-core' ),
			'post_types'   => array( 'teda_event' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'show_in_rest' => true,
			'fields'       => array(
				array(
					'name'       => __( 'Start date and time', 'teda-core' ),
					'id'         => 'teda_start_datetime',
					'type'       => 'datetime',
					'timestamp'  => true,
					'js_options' => array(
						'stepMinute'      => 15,
						'showTimepicker'  => true,
						'controlType'     => 'select',
						'oneLine'         => true,
					),
					'desc'       => __( 'When does the event start? Use the calendar to pick a date and time.', 'teda-core' ),
				),
				array(
					'name'       => __( 'End date and time', 'teda-core' ),
					'id'         => 'teda_end_datetime',
					'type'       => 'datetime',
					'timestamp'  => true,
					'js_options' => array(
						'stepMinute'     => 15,
						'showTimepicker' => true,
						'controlType'    => 'select',
						'oneLine'        => true,
					),
					'desc'       => __( 'When does it finish? The event moves to “Past events” automatically after this time.', 'teda-core' ),
				),
				array(
					'name' => __( 'Venue name', 'teda-core' ),
					'id'   => 'teda_venue_name',
					'type' => 'text',
					'desc' => __( 'Name of the place. e.g. Soroti Youth Centre', 'teda-core' ),
				),
				array(
					'name' => __( 'Location', 'teda-core' ),
					'id'   => 'teda_location',
					'type' => 'text',
					'desc' => __( 'Town or area. e.g. Soroti City', 'teda-core' ),
				),
				array(
					'name' => __( 'District', 'teda-core' ),
					'id'   => 'teda_district',
					'type' => 'text',
					'desc' => __( 'Which district is this in? e.g. Soroti', 'teda-core' ),
				),
				array(
					'name'  => __( 'Agenda', 'teda-core' ),
					'id'    => 'teda_agenda',
					'type'  => 'key_value',
					'desc'  => __( 'Optional running order. Put the time on the left and what happens on the right. e.g. 09:00 → Registration', 'teda-core' ),
				),
				array(
					'name'  => __( 'Feature on the homepage', 'teda-core' ),
					'id'    => 'teda_is_featured',
					'type'  => 'switch',
					'style' => 'rounded',
					'std'   => 0,
					'desc'  => __( 'Turn on to highlight this event on the homepage. Also used to fill the events section when there is nothing upcoming.', 'teda-core' ),
				),
				array(
					'name'  => __( 'Registration open', 'teda-core' ),
					'id'    => 'teda_registration_open',
					'type'  => 'switch',
					'style' => 'rounded',
					'std'   => 0,
					'desc'  => __( 'Turn on to show the registration form on this event’s page.', 'teda-core' ),
				),
				array(
					'name' => __( 'Number of places', 'teda-core' ),
					'id'   => 'teda_registration_capacity',
					'type' => 'number',
					'min'  => 0,
					'desc' => __( 'How many people can attend? Leave blank for no limit. When it fills up, the form becomes a waiting-list sign-up.', 'teda-core' ),
				),
				array(
					'name' => __( 'External registration link', 'teda-core' ),
					'id'   => 'teda_external_link',
					'type' => 'url',
					'desc' => __( 'Optional. If people register somewhere else (e.g. a Google Form), paste that link here.', 'teda-core' ),
				),
				Presets::verified(),
			),
		);
	}
}
