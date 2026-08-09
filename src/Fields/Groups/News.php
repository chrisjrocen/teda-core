<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields\Groups;

use Teda_Core\Fields\Presets;

/**
 * News uses the native `post` type (SPEC §5.1). The only meta it needs is the
 * verification switch (D13), so unverified news is excluded where the gate is on.
 */
final class News {

	/**
	 * @return array<string, mixed>
	 */
	public static function definition(): array {
		return array(
			'id'           => 'teda_news_details',
			'title'        => __( 'TEDA publishing check', 'teda-core' ),
			'post_types'   => array( 'post' ),
			'context'      => 'side',
			'priority'     => 'default',
			'show_in_rest' => true,
			'fields'       => array(
				Presets::verified(),
			),
		);
	}
}
