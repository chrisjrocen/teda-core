<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields;

/**
 * The single seam between templates/blocks and Meta Box.
 *
 * No template ever calls rwmb_meta() directly (P03 task 6): everything goes
 * through here, so if Meta Box is ever replaced there is exactly one file to
 * change. Also the safety layer — when Meta Box is inactive, every getter
 * returns a sane default instead of fataling (house rule 13).
 */
final class Accessor {

	/**
	 * Raw value for a field, or $default when absent / Meta Box inactive.
	 *
	 * @param string   $key     Meta key (teda_ prefixed).
	 * @param int|null $post_id Defaults to the current post.
	 * @param mixed    $default Returned when empty or unavailable.
	 * @return mixed
	 */
	public static function get( string $key, ?int $post_id = null, $default = null ) {
		if ( ! function_exists( 'rwmb_meta' ) ) {
			return $default;
		}

		if ( null === $post_id ) {
			$current = get_the_ID();
			$post_id = false !== $current ? (int) $current : null;
		}

		if ( null === $post_id ) {
			return $default;
		}

		$value = rwmb_meta( $key, array(), $post_id );

		if ( null === $value || '' === $value || array() === $value ) {
			return $default;
		}

		return $value;
	}

	/**
	 * Boolean (switch/checkbox) value.
	 */
	public static function get_bool( string $key, ?int $post_id = null, bool $default = false ): bool {
		$value = self::get( $key, $post_id, null );

		return null === $value ? $default : (bool) $value;
	}

	/**
	 * Integer value.
	 */
	public static function get_int( string $key, ?int $post_id = null, int $default = 0 ): int {
		$value = self::get( $key, $post_id, null );

		return null === $value ? $default : (int) $value;
	}

	/**
	 * Unix timestamp for a date / datetime field (stored with timestamp => true),
	 * or null when unset. All TEDA date logic uses this, never date() (house rule 9).
	 */
	public static function get_timestamp( string $key, ?int $post_id = null ): ?int {
		$value = self::get( $key, $post_id, null );

		if ( null === $value || '' === $value ) {
			return null;
		}

		return is_numeric( $value ) ? (int) $value : ( strtotime( (string) $value ) ?: null );
	}

	/**
	 * URL value, escaped for safe output.
	 */
	public static function get_url( string $key, ?int $post_id = null, string $default = '' ): string {
		$value = self::get( $key, $post_id, $default );

		return is_string( $value ) ? esc_url_raw( $value ) : $default;
	}

	/**
	 * Always-an-array value (clone fields, key-value fields). Never null.
	 *
	 * @return array<int|string, mixed>
	 */
	public static function get_list( string $key, ?int $post_id = null ): array {
		$value = self::get( $key, $post_id, array() );

		if ( is_array( $value ) ) {
			return $value;
		}

		return ( '' === $value || null === $value ) ? array() : array( $value );
	}
}
