<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields;

/**
 * Admin list columns that earn their place (P03 task 7): Events show start date
 * and registration state; Opportunities show deadline and open/closed; News
 * shows verified state. All values read through the Accessor, so they degrade
 * safely if Meta Box is inactive.
 */
final class Admin_Columns {

	public function register(): void {
		add_filter( 'manage_teda_event_posts_columns', array( $this, 'event_columns' ) );
		add_action( 'manage_teda_event_posts_custom_column', array( $this, 'event_column' ), 10, 2 );

		add_filter( 'manage_teda_opportunity_posts_columns', array( $this, 'opportunity_columns' ) );
		add_action( 'manage_teda_opportunity_posts_custom_column', array( $this, 'opportunity_column' ), 10, 2 );

		add_filter( 'manage_post_posts_columns', array( $this, 'news_columns' ) );
		add_action( 'manage_post_posts_custom_column', array( $this, 'news_column' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ */
	/* Events                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function event_columns( array $columns ): array {
		return $this->insert_before(
			$columns,
			'date',
			array(
				'teda_start' => __( 'Starts', 'teda-core' ),
				'teda_reg'   => __( 'Registration', 'teda-core' ),
			)
		);
	}

	public function event_column( string $column, int $post_id ): void {
		if ( 'teda_start' === $column ) {
			echo esc_html( $this->format_date( \teda_field_timestamp( 'teda_start_datetime', $post_id ) ) );
			return;
		}

		if ( 'teda_reg' === $column ) {
			if ( ! \teda_field_bool( 'teda_registration_open', $post_id ) ) {
				echo esc_html__( 'Closed', 'teda-core' );
				return;
			}
			$capacity = \teda_field_int( 'teda_registration_capacity', $post_id, 0 );
			echo $capacity > 0
				? esc_html( sprintf( /* translators: %d: number of places. */ __( 'Open · %d places', 'teda-core' ), $capacity ) )
				: esc_html__( 'Open', 'teda-core' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Opportunities                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function opportunity_columns( array $columns ): array {
		return $this->insert_before(
			$columns,
			'date',
			array(
				'teda_deadline' => __( 'Deadline', 'teda-core' ),
				'teda_status'   => __( 'Status', 'teda-core' ),
			)
		);
	}

	public function opportunity_column( string $column, int $post_id ): void {
		if ( 'teda_deadline' === $column ) {
			echo esc_html( $this->format_date( \teda_field_timestamp( 'teda_deadline', $post_id ) ) );
			return;
		}

		if ( 'teda_status' === $column ) {
			echo \teda_field_bool( 'teda_is_open', $post_id )
				? esc_html__( 'Open', 'teda-core' )
				: esc_html__( 'Closed', 'teda-core' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* News                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function news_columns( array $columns ): array {
		return $this->insert_before(
			$columns,
			'date',
			array( 'teda_verified' => __( 'Verified', 'teda-core' ) )
		);
	}

	public function news_column( string $column, int $post_id ): void {
		if ( 'teda_verified' !== $column ) {
			return;
		}

		echo \teda_field_bool( 'teda_verified', $post_id )
			? esc_html__( 'Verified', 'teda-core' )
			: esc_html__( 'Not yet', 'teda-core' );
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Insert new columns before an existing key, preserving order.
	 *
	 * @param array<string, string> $columns  Existing columns.
	 * @param string                $before   Column key to insert before.
	 * @param array<string, string> $new_cols Columns to insert.
	 * @return array<string, string>
	 */
	private function insert_before( array $columns, string $before, array $new_cols ): array {
		$result = array();
		foreach ( $columns as $key => $label ) {
			if ( $key === $before ) {
				$result = array_merge( $result, $new_cols );
			}
			$result[ $key ] = $label;
		}

		// If the anchor column was absent, append.
		if ( ! isset( $columns[ $before ] ) ) {
			$result = array_merge( $result, $new_cols );
		}

		return $result;
	}

	/**
	 * Format a timestamp in the site timezone, or an em dash when unset.
	 */
	private function format_date( ?int $timestamp ): string {
		if ( null === $timestamp ) {
			return '—';
		}

		return wp_date( (string) get_option( 'date_format', 'j M Y' ), $timestamp );
	}
}
