<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields\Groups;

/**
 * Space fields (SPEC §5.1, consumed by P19). Defined now so the data model is
 * version-controlled from the start. Speakers and key points are free cloned
 * single fields (Meta Box free has no cloneable groups — C3 note). The summary
 * and key points render server-side outside the X embed, so the page is useful
 * even if the embed fails (SPEC §5.1, §10.3).
 */
final class Space {

	/**
	 * @return array<string, mixed>
	 */
	public static function definition(): array {
		return array(
			'id'           => 'teda_space_details',
			'title'        => __( 'Space details', 'teda-core' ),
			'post_types'   => array( 'teda_space' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'show_in_rest' => true,
			'fields'       => array(
				array(
					'name'         => __( 'Link to the Space on X', 'teda-core' ),
					'id'           => 'teda_space_url',
					'type'         => 'url',
					'show_in_rest' => true,
					'desc'         => __( 'Paste the web address of the Space on X (Twitter). This is used to embed and link to it.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Date', 'teda-core' ),
					'id'           => 'teda_space_date',
					'type'         => 'date',
					'timestamp'    => true,
					'show_in_rest' => true,
					'desc'         => __( 'When the Space took place.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Duration', 'teda-core' ),
					'id'           => 'teda_duration',
					'type'         => 'text',
					'show_in_rest' => true,
					'desc'         => __( 'How long it ran. e.g. 1 hour 20 minutes.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Speakers', 'teda-core' ),
					'id'           => 'teda_speakers',
					'type'         => 'text',
					'clone'        => true,
					'show_in_rest' => true,
					'desc'         => __( 'Add each speaker on their own line. Click “Add more” for another.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Key points', 'teda-core' ),
					'id'           => 'teda_key_points',
					'type'         => 'textarea',
					'rows'         => 2,
					'clone'        => true,
					'show_in_rest' => true,
					'desc'         => __( 'The main things discussed. Add each point on its own — these show on the page even if the X embed does not load.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Summary', 'teda-core' ),
					'id'           => 'teda_summary',
					'type'         => 'textarea',
					'rows'         => 4,
					'show_in_rest' => true,
					'desc'         => __( 'A short write-up of the Space, so people (and search engines) can read it without listening.', 'teda-core' ),
				),
				array(
					'name'         => __( 'What TEDA is doing about it', 'teda-core' ),
					'id'           => 'teda_space_action',
					'type'         => 'textarea',
					'rows'         => 3,
					'show_in_rest' => true,
					'desc'         => __( 'Optional. The action TEDA is taking on the issues raised — this turns a conversation into accountability. Shows on the page even if the X embed does not load.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Audio recording (fallback)', 'teda-core' ),
					'id'           => 'teda_audio_file',
					'type'         => 'file_input',
					'show_in_rest' => true,
					'desc'         => __( 'Optional. A link to an MP3 recording, used if the X embed is unavailable.', 'teda-core' ),
				),
			),
		);
	}
}
