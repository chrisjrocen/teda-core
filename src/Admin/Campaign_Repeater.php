<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Admin;

use Teda_Core\Support\Bootable;
use WP_Post;

/**
 * Amount tiers and donation goals for a Campaign. Meta Box's free edition has
 * no cloneable *group* fields (only single cloned fields), so this pair of
 * repeatable, multi-field lists can't be expressed as a Meta Box group — this
 * class hand-rolls one small meta box instead: plain PHP-rendered rows, a
 * `<template>` for client-side add/remove (admin/campaign-repeater.js, no
 * build step), and its own save handler storing each list as ONE structured
 * post-meta array (`teda_tiers` / `teda_goals`) rather than one meta row per
 * sub-field — that keeps a row's fields atomic, so there is no way for e.g. an
 * amount and its description to fall out of sync the way parallel Meta Box
 * clone fields could.
 *
 * Not itself a Bootable subsystem — instantiated from Donations\Registry
 * alongside the rest of the donations subsystem, the same way that class
 * already instantiates Rest_Controller/Ipn_Controller/Unsubscribe_Controller.
 */
final class Campaign_Repeater implements Bootable {

	private const POST_TYPE = 'teda_campaign';
	private const NONCE_ACTION = 'teda_campaign_repeater_save';
	private const NONCE_FIELD = 'teda_campaign_repeater_nonce';

	public function register(): void {
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'teda_campaign_repeater',
			__( 'Amount tiers & donation goals', 'teda-core' ),
			array( $this, 'render' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'teda-campaign-repeater',
			TEDA_CORE_URL . 'admin/campaign-repeater.js',
			array(),
			TEDA_CORE_VERSION,
			true
		);
	}

	/**
	 * @return array<int, array{currency:string, amount:int, description:string}>
	 */
	public static function tiers( int $post_id ): array {
		$stored = get_post_meta( $post_id, 'teda_tiers', true );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @return array<int, array{label:string, description:string, image:int}>
	 */
	public static function goals( int $post_id ): array {
		$stored = get_post_meta( $post_id, 'teda_goals', true );
		return is_array( $stored ) ? $stored : array();
	}

	public function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		echo '<h3>' . esc_html__( 'Amount tiers', 'teda-core' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Suggested amounts shown in the donation panel and impact section, per currency.', 'teda-core' ) . '</p>';
		$this->render_repeater(
			'teda-tiers-repeater',
			'teda_tiers',
			self::tiers( $post->ID ),
			array( $this, 'render_tier_row' ),
			__( '+ Add amount tier', 'teda-core' )
		);

		echo '<h3>' . esc_html__( 'Donation goals', 'teda-core' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Optional causes a donor can choose to give to, shown as cards with a "Give to this" button.', 'teda-core' ) . '</p>';
		$this->render_repeater(
			'teda-goals-repeater',
			'teda_goals',
			self::goals( $post->ID ),
			array( $this, 'render_goal_row' ),
			__( '+ Add donation goal', 'teda-core' )
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 */
	private function render_repeater( string $id, string $field_prefix, array $rows, callable $row_renderer, string $add_label ): void {
		echo '<div class="teda-repeater" id="' . esc_attr( $id ) . '" data-field-prefix="' . esc_attr( $field_prefix ) . '">';
		echo '<div class="teda-repeater__rows">';
		foreach ( $rows as $row ) {
			call_user_func( $row_renderer, $row );
		}
		echo '</div>';
		echo '<template data-row-template>';
		call_user_func( $row_renderer, array() );
		echo '</template>';
		echo '<p><button type="button" class="button" data-add-row>' . esc_html( $add_label ) . '</button></p>';
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function render_tier_row( array $row ): void {
		$currency = isset( $row['currency'] ) && 'USD' === $row['currency'] ? 'USD' : 'UGX';
		$amount   = isset( $row['amount'] ) ? (int) $row['amount'] : '';
		$desc     = isset( $row['description'] ) ? (string) $row['description'] : '';

		echo '<div class="teda-repeater__row" data-row>';
		echo '<select name="teda_tiers[][currency]">';
		echo '<option value="UGX"' . selected( $currency, 'UGX', false ) . '>UGX</option>';
		echo '<option value="USD"' . selected( $currency, 'USD', false ) . '>USD</option>';
		echo '</select> ';
		echo '<input type="number" min="1" step="1" name="teda_tiers[][amount]" value="' . esc_attr( (string) $amount ) . '" placeholder="' . esc_attr__( 'Amount', 'teda-core' ) . '" class="small-text"> ';
		echo '<input type="text" name="teda_tiers[][description]" value="' . esc_attr( $desc ) . '" placeholder="' . esc_attr__( 'What this covers', 'teda-core' ) . '" class="regular-text"> ';
		echo '<button type="button" class="button-link teda-repeater__remove" data-remove-row>' . esc_html__( 'Remove', 'teda-core' ) . '</button>';
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function render_goal_row( array $row ): void {
		$label = isset( $row['label'] ) ? (string) $row['label'] : '';
		$desc  = isset( $row['description'] ) ? (string) $row['description'] : '';
		$image = isset( $row['image'] ) ? (int) $row['image'] : 0;

		echo '<div class="teda-repeater__row teda-repeater__row--goal" data-row>';
		echo '<p><input type="text" name="teda_goals[][label]" value="' . esc_attr( $label ) . '" placeholder="' . esc_attr__( 'Goal name', 'teda-core' ) . '" class="regular-text"></p>';
		echo '<p><textarea name="teda_goals[][description]" rows="2" placeholder="' . esc_attr__( 'Short description', 'teda-core' ) . '" class="large-text">' . esc_textarea( $desc ) . '</textarea></p>';
		echo '<p class="teda-repeater__image">';
		echo '<input type="hidden" name="teda_goals[][image]" value="' . esc_attr( (string) $image ) . '" data-image-input>';
		echo '<button type="button" class="button" data-select-image>' . esc_html__( 'Select image', 'teda-core' ) . '</button> ';
		echo '<span data-image-preview>' . ( $image > 0 ? wp_get_attachment_image( $image, 'thumbnail' ) : '' ) . '</span> ';
		echo '<button type="button" class="button-link" data-remove-image' . ( $image > 0 ? '' : ' hidden' ) . '>' . esc_html__( 'Remove image', 'teda-core' ) . '</button>';
		echo '</p>';
		echo '<p><button type="button" class="button-link teda-repeater__remove" data-remove-row>' . esc_html__( 'Remove goal', 'teda-core' ) . '</button></p>';
		echo '</div>';
	}

	public function save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, 'teda_tiers', $this->sanitize_tiers( $_POST['teda_tiers'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_post_meta( $post_id, 'teda_goals', $this->sanitize_goals( $_POST['teda_goals'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$this->enforce_single_default( $post_id );
	}

	/**
	 * @param mixed $raw
	 * @return array<int, array{currency:string, amount:int, description:string}>
	 */
	private function sanitize_tiers( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$amount = isset( $row['amount'] ) ? max( 0, (int) $row['amount'] ) : 0;
			if ( $amount <= 0 ) {
				continue; // Blank template rows, or a row left empty, are dropped.
			}
			$currency = isset( $row['currency'] ) && 'USD' === $row['currency'] ? 'USD' : 'UGX';
			$clean[]  = array(
				'currency'    => $currency,
				'amount'      => $amount,
				'description' => isset( $row['description'] ) ? sanitize_text_field( wp_unslash( (string) $row['description'] ) ) : '',
			);
		}
		return $clean;
	}

	/**
	 * @param mixed $raw
	 * @return array<int, array{label:string, description:string, image:int}>
	 */
	private function sanitize_goals( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? trim( sanitize_text_field( wp_unslash( (string) $row['label'] ) ) ) : '';
			if ( '' === $label ) {
				continue; // A goal with no label can't be selected/targeted — skip it.
			}
			$image = isset( $row['image'] ) ? max( 0, (int) $row['image'] ) : 0;
			if ( $image > 0 && 'attachment' !== get_post_type( $image ) ) {
				$image = 0;
			}
			$clean[] = array(
				'label'       => $label,
				'description' => isset( $row['description'] ) ? sanitize_textarea_field( wp_unslash( (string) $row['description'] ) ) : '',
				'image'       => $image,
			);
		}
		return $clean;
	}

	/**
	 * Exactly one campaign may be the default. Read straight from $_POST (rather
	 * than the Meta Box-saved meta) so this doesn't depend on save_post callback
	 * ordering between Meta Box's own save and this one.
	 */
	private function enforce_single_default( int $post_id ): void {
		$is_default = ! empty( $_POST['teda_is_default'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $is_default ) {
			return;
		}

		$others = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'exclude'        => array( $post_id ),
				'fields'         => 'ids',
			)
		);
		foreach ( $others as $other_id ) {
			update_post_meta( (int) $other_id, 'teda_is_default', 0 );
		}
	}
}
