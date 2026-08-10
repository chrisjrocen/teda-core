<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields;

use Teda_Core\Support\Bootable;
use Teda_Core\Fields\Groups;

/**
 * Registers every Meta Box field group in PHP (never through a UI — so field
 * definitions are version-controlled and survive a database restore, P03 task 1)
 * and boots the editor-experience helpers (image nudge, admin columns).
 */
final class Registry implements Bootable {

	/**
	 * Group classes, each exposing a static definition(): array.
	 *
	 * @var array<int, class-string>
	 */
	private const GROUPS = array(
		Groups\Event::class,
		Groups\Focus_Area::class,
		Groups\Opportunity::class,
		Groups\Team::class,
		Groups\Space::class,
		Groups\News::class,
		Groups\Publication::class,
	);

	public function register(): void {
		add_filter( 'rwmb_meta_boxes', array( $this, 'add_meta_boxes' ) );

		( new Featured_Image_Nudge() )->register();
		( new Admin_Columns() )->register();
	}

	/**
	 * Merge TEDA field groups into Meta Box's list. If Meta Box is inactive this
	 * filter never fires, so there is nothing to guard here.
	 *
	 * @param array<int, array<string, mixed>> $meta_boxes Existing boxes.
	 * @return array<int, array<string, mixed>>
	 */
	public function add_meta_boxes( array $meta_boxes ): array {
		foreach ( self::GROUPS as $group ) {
			$meta_boxes[] = $group::definition();
		}

		return $meta_boxes;
	}
}
