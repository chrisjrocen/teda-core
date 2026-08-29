<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

use Teda_Core\Admin\Campaign_Repeater;
use Teda_Core\Fields\Accessor;
use Teda_Core\Support\Dates;

/**
 * The single seam between "campaign data" and any caller — Blocks\Donate,
 * blocks/editor.js's campaign picker (via Blocks\Registry's localized data),
 * and Campaign_Migration. Resolves which campaign a teda/donate block should
 * show (picked → eligible? : default → eligible? : none) and hydrates a
 * campaign into the same flat attribute shape Donate.php's render methods
 * already expect, so those methods don't need to change — only their data
 * source does.
 */
final class Campaigns_Repository {

	private const POST_TYPE = 'teda_campaign';

	/**
	 * Resolve which campaign a block instance should render: the explicitly
	 * picked campaign if it's published and currently eligible; otherwise the
	 * campaign flagged as default, if it is; otherwise null (caller renders the
	 * empty state).
	 *
	 * @return array<string, mixed>|null
	 */
	public function resolve_for_block( int $picked_campaign_id ): ?array {
		if ( $picked_campaign_id > 0 && $this->is_eligible( $picked_campaign_id ) ) {
			return $this->get( $picked_campaign_id );
		}

		$default_id = $this->default_campaign_id();
		if ( null !== $default_id && $this->is_eligible( $default_id ) ) {
			return $this->get( $default_id );
		}

		return null;
	}

	/**
	 * Published, and within its (optional) start/end scheduling window.
	 */
	public function is_eligible( int $campaign_id ): bool {
		if ( self::POST_TYPE !== get_post_type( $campaign_id ) || 'publish' !== get_post_status( $campaign_id ) ) {
			return false;
		}

		$now   = Dates::now();
		$start = Accessor::get_timestamp( 'teda_campaign_start', $campaign_id );
		$end   = Accessor::get_timestamp( 'teda_campaign_end', $campaign_id );

		if ( null !== $start && $now < $start ) {
			return false;
		}
		if ( null !== $end && $now > $end ) {
			return false;
		}

		return true;
	}

	/**
	 * Hydrate one campaign into the flat shape Donate.php's Block_Renderer
	 * helpers (str_attr/bool_attr) already read, plus `tiers` (grouped by
	 * currency) and `goals`.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $campaign_id ): ?array {
		if ( self::POST_TYPE !== get_post_type( $campaign_id ) ) {
			return null;
		}

		$cache_key = 'teda_campaign_' . $campaign_id . '_' . wp_cache_get_last_changed( 'posts' );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$data = array(
			'lead'              => (string) Accessor::get( 'teda_lead', $campaign_id, '' ),
			'currency_default'  => (string) Accessor::get( 'teda_currency_default', $campaign_id, 'UGX' ),
			'show_projects'     => Accessor::get_bool( 'teda_show_projects', $campaign_id, true ),
			'show_antifraud'    => Accessor::get_bool( 'teda_show_antifraud', $campaign_id, true ),
			'show_trust_strip'  => Accessor::get_bool( 'teda_show_trust_strip', $campaign_id, true ),
			'show_other_ways'   => Accessor::get_bool( 'teda_show_other_ways', $campaign_id, true ),
			'whatsapp'          => (string) Accessor::get( 'teda_whatsapp', $campaign_id, '' ),
			'email'             => (string) Accessor::get( 'teda_email', $campaign_id, '' ),
			'mtn'               => (string) Accessor::get( 'teda_mtn', $campaign_id, '' ),
			'airtel'            => (string) Accessor::get( 'teda_airtel', $campaign_id, '' ),
			'bank'              => (string) Accessor::get( 'teda_bank', $campaign_id, '' ),
			'registration'      => (string) Accessor::get( 'teda_registration', $campaign_id, '' ),
			'accountability'    => (string) Accessor::get( 'teda_accountability', $campaign_id, '' ),
			'antifraud'         => (string) Accessor::get( 'teda_antifraud', $campaign_id, '' ),
			'tiers'             => $this->tiers_by_currency( $campaign_id ),
			'goals'             => $this->goals_for( $campaign_id ),
		);

		if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! is_admin() && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			set_transient( $cache_key, $data, HOUR_IN_SECONDS );
		}

		return $data;
	}

	/**
	 * @return array{UGX: array<int, array{amount:int, desc:string}>, USD: array<int, array{amount:int, desc:string}>}
	 */
	private function tiers_by_currency( int $campaign_id ): array {
		$grouped = array( 'UGX' => array(), 'USD' => array() );
		foreach ( Campaign_Repeater::tiers( $campaign_id ) as $tier ) {
			$currency = isset( $tier['currency'] ) && 'USD' === $tier['currency'] ? 'USD' : 'UGX';
			$amount   = isset( $tier['amount'] ) ? max( 0, (int) $tier['amount'] ) : 0;
			if ( $amount <= 0 ) {
				continue;
			}
			$grouped[ $currency ][] = array(
				'amount' => $amount,
				'desc'   => isset( $tier['description'] ) ? (string) $tier['description'] : '',
			);
		}
		return $grouped;
	}

	/**
	 * @return array<int, array{label:string, description:string, image:int}>
	 */
	private function goals_for( int $campaign_id ): array {
		$goals = array();
		foreach ( Campaign_Repeater::goals( $campaign_id ) as $goal ) {
			$label = isset( $goal['label'] ) ? trim( (string) $goal['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$goals[] = array(
				'label'       => $label,
				'description' => isset( $goal['description'] ) ? (string) $goal['description'] : '',
				'image'       => isset( $goal['image'] ) ? max( 0, (int) $goal['image'] ) : 0,
			);
		}
		return $goals;
	}

	/**
	 * The single campaign flagged `teda_is_default`, or null if none is.
	 * Campaign_Repeater::save() keeps this flag unique across published
	 * campaigns, but this still tolerates more than one being set (returns the
	 * first) rather than assuming that invariant always holds.
	 */
	private function default_campaign_id(): ?int {
		$found = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => 'teda_is_default',
				'meta_value'     => '1',
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		return array() !== $found ? (int) $found[0] : null;
	}

	/**
	 * Every published campaign's id/title + a schedule hint, for the block
	 * editor's campaign picker (Blocks\Registry localizes this as
	 * `window.tedaCampaigns`).
	 *
	 * @return array<int, array{id:int, label:string}>
	 */
	public function options_for_editor(): array {
		$campaigns = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array();
		foreach ( $campaigns as $campaign ) {
			$is_default = Accessor::get_bool( 'teda_is_default', $campaign->ID, false );
			$label      = get_the_title( $campaign );
			if ( $is_default ) {
				/* translators: %s: campaign title. */
				$label = sprintf( __( '%s (default)', 'teda-core' ), $label );
			} elseif ( ! $this->is_eligible( $campaign->ID ) ) {
				/* translators: %s: campaign title. */
				$label = sprintf( __( '%s (not currently scheduled)', 'teda-core' ), $label );
			}
			$options[] = array(
				'id'    => $campaign->ID,
				'label' => $label,
			);
		}
		return $options;
	}
}
