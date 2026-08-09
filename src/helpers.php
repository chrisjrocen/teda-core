<?php
/**
 * Global template/block helpers. Thin wrappers over Fields\Accessor so callers
 * write teda_field(...) instead of a namespaced static call. Loaded directly by
 * teda-core.php (functions are not autoloaded).
 *
 * @package Teda_Core
 */

declare(strict_types=1);

use Teda_Core\Fields\Accessor;

if ( ! function_exists( 'teda_field' ) ) {
	/**
	 * Raw field value, or $default when absent / Meta Box inactive.
	 *
	 * @param string   $key     Meta key (teda_ prefixed).
	 * @param int|null $post_id Defaults to the current post.
	 * @param mixed    $default Fallback.
	 * @return mixed
	 */
	function teda_field( string $key, ?int $post_id = null, $default = null ) {
		return Accessor::get( $key, $post_id, $default );
	}
}

if ( ! function_exists( 'teda_field_bool' ) ) {
	function teda_field_bool( string $key, ?int $post_id = null, bool $default = false ): bool {
		return Accessor::get_bool( $key, $post_id, $default );
	}
}

if ( ! function_exists( 'teda_field_int' ) ) {
	function teda_field_int( string $key, ?int $post_id = null, int $default = 0 ): int {
		return Accessor::get_int( $key, $post_id, $default );
	}
}

if ( ! function_exists( 'teda_field_timestamp' ) ) {
	function teda_field_timestamp( string $key, ?int $post_id = null ): ?int {
		return Accessor::get_timestamp( $key, $post_id );
	}
}

if ( ! function_exists( 'teda_field_url' ) ) {
	function teda_field_url( string $key, ?int $post_id = null, string $default = '' ): string {
		return Accessor::get_url( $key, $post_id, $default );
	}
}

if ( ! function_exists( 'teda_field_list' ) ) {
	/**
	 * @return array<int|string, mixed>
	 */
	function teda_field_list( string $key, ?int $post_id = null ): array {
		return Accessor::get_list( $key, $post_id );
	}
}

if ( ! function_exists( 'teda_event_registration_form' ) ) {
	/**
	 * The registration area for an event page (P13): capacity line + the Fluent
	 * Forms registration form, or a waitlist CTA once the event is full. Returns ''
	 * when Fluent Forms is unavailable, so the theme can fall back to its own
	 * external-link button. Keeps Fluent Forms coupling inside teda-core.
	 *
	 * @param int $event_id Event post id.
	 */
	function teda_event_registration_form( int $event_id ): string {
		return \Teda_Core\Forms\Event_Registration::render( $event_id );
	}
}
