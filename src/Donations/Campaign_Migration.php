<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

use Teda_Core\Admin\Notices;

/**
 * One-time migration from the old block-editor-authored teda/donate content
 * (attributes + teda/donate-tier / teda/donate-goal inner blocks) into a
 * single admin-managed "default" Campaign post. Hooked to the plugin's
 * existing `teda_core/upgrade` action (same idempotent-migration mechanism as
 * Donations\Migrations), guarded by a one-time option so it only ever runs
 * once automatically — see run() for the `$force` escape hatch used by the
 * `wp teda migrate-donate-campaigns` CLI command for deliberate re-runs.
 *
 * This is the one part of the whole Campaigns feature that mutates existing
 * page content (it rewrites the source page's teda/donate block down to a
 * bare `{"campaign_id":N}`), so it's worth a staging/backup dry run before
 * this ships to a site with real donate-page content already in place.
 */
final class Campaign_Migration {

	private const DONE_OPTION = 'teda_campaign_migration_done';

	public static function run( bool $force = false ): void {
		if ( ! $force && get_option( self::DONE_OPTION ) ) {
			return;
		}

		$found = self::find_donate_blocks();

		if ( array() === $found ) {
			update_option( self::DONE_OPTION, true );
			return;
		}

		$first          = array_shift( $found );
		$campaign_id    = self::create_campaign_from_block( $first['post'], $first['block'] );
		$source_content = self::rewrite_post_content( $first['post'], $campaign_id );

		wp_update_post(
			array(
				'ID'           => $first['post']->ID,
				'post_content' => $source_content,
			)
		);

		$message = sprintf(
			/* translators: 1: number of tiers, 2: number of goals, 3: source page title. */
			__( 'Migrated %1$d tier(s) and %2$d goal(s) from "%3$s" into a new default campaign — review it under Donations → Campaigns.', 'teda-core' ),
			count( self::extract_inner( $first['block'], 'teda/donate-tier' ) ),
			count( self::extract_inner( $first['block'], 'teda/donate-goal' ) ),
			$first['post']->post_title
		);
		Notices::add( $message, 'success', true );

		if ( array() !== $found ) {
			$titles = wp_list_pluck( wp_list_pluck( $found, 'post' ), 'post_title' );
			Notices::add(
				sprintf(
					/* translators: %s: comma-separated list of page titles. */
					__( 'Additional teda/donate content was found on: %s. Only the first page was migrated automatically — review these manually and create additional campaigns if needed.', 'teda-core' ),
					implode( ', ', $titles )
				),
				'warning',
				true
			);
		}

		update_option( self::DONE_OPTION, true );
	}

	/**
	 * Every published post containing at least one teda/donate block, with that
	 * block's parsed structure. Recurses into inner blocks (group/columns/tabs)
	 * since teda/donate could in principle be nested.
	 *
	 * @return array<int, array{post: \WP_Post, block: array<string, mixed>}>
	 */
	private static function find_donate_blocks(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$found = array();
		foreach ( $posts as $post ) {
			if ( false === strpos( $post->post_content, 'wp:teda/donate' ) ) {
				continue; // Cheap pre-filter before the more expensive parse_blocks() below.
			}
			$block = self::find_in_tree( parse_blocks( $post->post_content ) );
			if ( null !== $block ) {
				$found[] = array(
					'post'  => $post,
					'block' => $block,
				);
			}
		}
		return $found;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks
	 * @return array<string, mixed>|null
	 */
	private static function find_in_tree( array $blocks ): ?array {
		foreach ( $blocks as $block ) {
			if ( 'teda/donate' === ( $block['blockName'] ?? '' ) ) {
				return $block;
			}
			$nested = self::find_in_tree( $block['innerBlocks'] ?? array() );
			if ( null !== $nested ) {
				return $nested;
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $block
	 * @return array<int, array<string, mixed>>
	 */
	private static function extract_inner( array $block, string $block_name ): array {
		$out = array();
		foreach ( $block['innerBlocks'] ?? array() as $inner ) {
			if ( $block_name === ( $inner['blockName'] ?? '' ) ) {
				$out[] = $inner['attrs'] ?? array();
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $block
	 */
	private static function create_campaign_from_block( \WP_Post $source, array $block ): int {
		$attrs = $block['attrs'] ?? array();

		$campaign_id = wp_insert_post(
			array(
				'post_type'   => 'teda_campaign',
				'post_status' => 'publish',
				/* translators: %s: source page title. */
				'post_title'  => sprintf( __( '%s (migrated)', 'teda-core' ), $source->post_title ),
			)
		);
		$campaign_id = (int) $campaign_id;

		$meta_map = array(
			'lead'           => 'teda_lead',
			'currency_default' => 'teda_currency_default',
			'whatsapp'       => 'teda_whatsapp',
			'email'          => 'teda_email',
			'mtn'            => 'teda_mtn',
			'airtel'         => 'teda_airtel',
			'bank'           => 'teda_bank',
			'registration'   => 'teda_registration',
			'accountability' => 'teda_accountability',
			'antifraud'      => 'teda_antifraud',
		);
		foreach ( $meta_map as $attr_key => $meta_key ) {
			if ( array_key_exists( $attr_key, $attrs ) ) {
				update_post_meta( $campaign_id, $meta_key, $attrs[ $attr_key ] );
			}
		}

		// The four show_* booleans need an explicit write, defaulting to true
		// when absent from $attrs — Gutenberg's serializer omits an attribute
		// that equals its block.json default, so "left at default (true)" and
		// "never set" are the same signal here. Accessor::get_bool() treats a
		// genuinely absent switch as off, so skipping the write (like the
		// generic array_key_exists loop above does for other fields) would
		// silently turn these sections off for any page that never touched them.
		foreach ( array( 'show_projects', 'show_antifraud', 'show_trust_strip', 'show_other_ways' ) as $attr_key ) {
			$on = array_key_exists( $attr_key, $attrs ) ? (bool) $attrs[ $attr_key ] : true;
			update_post_meta( $campaign_id, 'teda_' . $attr_key, $on ? 1 : 0 );
		}
		update_post_meta( $campaign_id, 'teda_is_default', 1 );

		$tiers = array();
		foreach ( self::extract_inner( $block, 'teda/donate-tier' ) as $tier_attrs ) {
			$amount = isset( $tier_attrs['amount'] ) ? max( 0, (int) $tier_attrs['amount'] ) : 0;
			if ( $amount <= 0 ) {
				continue;
			}
			$tiers[] = array(
				'currency'    => isset( $tier_attrs['currency'] ) && 'USD' === $tier_attrs['currency'] ? 'USD' : 'UGX',
				'amount'      => $amount,
				'description' => isset( $tier_attrs['description'] ) ? (string) $tier_attrs['description'] : '',
			);
		}
		update_post_meta( $campaign_id, 'teda_tiers', $tiers );

		$goals = array();
		foreach ( self::extract_inner( $block, 'teda/donate-goal' ) as $goal_attrs ) {
			$label = isset( $goal_attrs['label'] ) ? trim( (string) $goal_attrs['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$goals[] = array(
				'label'       => $label,
				'description' => isset( $goal_attrs['description'] ) ? (string) $goal_attrs['description'] : '',
				'image'       => isset( $goal_attrs['image'] ) ? max( 0, (int) $goal_attrs['image'] ) : 0,
			);
		}
		update_post_meta( $campaign_id, 'teda_goals', $goals );

		return $campaign_id;
	}

	/**
	 * Replace the FIRST teda/donate block found in the source post's content
	 * (same "first match" semantics as find_in_tree()) with a bare
	 * `{"campaign_id":N}` block, leaving every other block untouched.
	 */
	private static function rewrite_post_content( \WP_Post $source, int $campaign_id ): string {
		$tree      = parse_blocks( $source->post_content );
		$replaced  = false;
		$rewritten = self::replace_first_donate( $tree, $campaign_id, $replaced );
		return serialize_blocks( $rewritten );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks
	 * @return array<int, array<string, mixed>>
	 */
	private static function replace_first_donate( array $blocks, int $campaign_id, bool &$replaced ): array {
		foreach ( $blocks as $i => $block ) {
			if ( $replaced ) {
				break;
			}
			if ( 'teda/donate' === ( $block['blockName'] ?? '' ) ) {
				$blocks[ $i ] = array(
					'blockName'    => 'teda/donate',
					'attrs'        => array( 'campaign_id' => $campaign_id ),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				);
				$replaced = true;
				continue;
			}
			if ( array() !== ( $block['innerBlocks'] ?? array() ) ) {
				$blocks[ $i ]['innerBlocks'] = self::replace_first_donate( $block['innerBlocks'], $campaign_id, $replaced );
			}
		}
		return $blocks;
	}
}
