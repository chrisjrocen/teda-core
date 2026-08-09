<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use Teda_Core\Support\Dates;
use WP_Block;

/**
 * teda/countdown — the dual-state event countdown (D7, SPEC §10.2).
 *
 * PHP renders BOTH states — the live timer and the post-event message — and marks
 * the correct one visible from the server clock. The client script (teda-child
 * blocks-b.js) re-decides from the data-end epoch and ticks the numbers, so:
 *  - with a warm page cache it still flips (the decision is client-side on load);
 *  - with JavaScript disabled the server-rendered snapshot is already a correct,
 *    readable state;
 *  - it never shows zeros or negatives for a passed event — it shows the post-event
 *    block, which is the exact failure SPEC §10.2 warns against.
 */
final class Countdown extends Block_Renderer {

	private const UNITS = array( 'days', 'hours', 'minutes', 'seconds' );

	public function name(): string {
		return 'teda/countdown';
	}

	protected function is_empty( array $attributes, WP_Block $block ): bool {
		[ $start, $end ] = $this->window( $attributes );
		return 0 === $start && 0 === $end;
	}

	protected function render_empty( array $attributes, WP_Block $block ): string {
		return '';
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		[ $start, $end ]  = $this->window( $attributes );
		$now              = Dates::now();
		$deadline         = $end > 0 ? $end : $start;
		$expired          = $deadline > 0 && $now >= $deadline;
		$remaining        = $start > 0 ? $start - $now : 0;

		// Inner class must NOT be `teda-countdown` — that collides with the block
		// wrapper's class, so the ticker would bind to both (the wrapper has no
		// data-* and would zero the timer). Use a distinct class for the JS hook.
		$out = '<div class="teda-countdown__clock" data-teda-start="' . esc_attr( (string) $start ) . '" data-teda-end="' . esc_attr( (string) $deadline ) . '">';

		// Live timer — hidden by the server when already expired.
		$out .= '<div class="teda-countdown__live"' . ( $expired ? ' hidden' : '' ) . '>';
		$out .= '<div class="teda-countdown__timer" role="timer" aria-label="' . esc_attr__( 'Time until this event starts', 'teda-core' ) . '">';
		$out .= $this->timer_boxes( $remaining );
		$out .= '</div></div>';

		// Post-event message — hidden by the server until expired.
		$out .= '<div class="teda-countdown__post"' . ( $expired ? '' : ' hidden' ) . '>';
		$out .= $this->post_event( $attributes );
		$out .= '</div>';

		$out .= '</div>';

		return $out;
	}

	/**
	 * The four zero-padded unit boxes, computed from the remaining seconds (clamped
	 * at zero by Dates::breakdown so nothing ever goes negative).
	 */
	private function timer_boxes( int $remaining ): string {
		$parts  = Dates::breakdown( $remaining );
		$labels = array(
			'days'    => __( 'Days', 'teda-core' ),
			'hours'   => __( 'Hours', 'teda-core' ),
			'minutes' => __( 'Minutes', 'teda-core' ),
			'seconds' => __( 'Seconds', 'teda-core' ),
		);

		$boxes = '';
		foreach ( self::UNITS as $unit ) {
			$boxes .= '<div class="teda-countdown__unit">'
				. '<b data-teda-unit="' . esc_attr( $unit ) . '">' . esc_html( str_pad( (string) $parts[ $unit ], 2, '0', STR_PAD_LEFT ) ) . '</b>'
				. '<span>' . esc_html( $labels[ $unit ] ) . '</span>'
				. '</div>';
		}

		return $boxes;
	}

	/**
	 * The post-event block. Never zeros/negatives — a plain message + optional recap
	 * link (SPEC §10.2).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function post_event( array $attributes ): string {
		$out = '<div class="teda-postev">';
		$out .= '<b>' . esc_html__( 'This event has taken place — see the highlights', 'teda-core' ) . '</b>';
		$out .= '<p>' . esc_html__( 'Thank you to everyone who came. The summary and photos are published on this page.', 'teda-core' ) . '</p>';

		$url = $this->str_attr( $attributes, 'recap_url' );
		if ( '' !== $url ) {
			$label = $this->str_attr( $attributes, 'recap_label', __( 'See the highlights', 'teda-core' ) );
			$out  .= '<a class="teda-postev__link" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}

		return $out . '</div>';
	}

	/**
	 * Resolve [start, end] timestamps from the chosen event (0 = current post).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array{0:int, 1:int}
	 */
	private function window( array $attributes ): array {
		$id    = $this->int_attr( $attributes, 'event_id', 0, 0, PHP_INT_MAX );
		$id    = $id > 0 ? $id : (int) get_the_ID();
		$start = teda_field_timestamp( 'teda_start_datetime', $id );
		$end   = teda_field_timestamp( 'teda_end_datetime', $id );

		return array( null !== $start ? $start : 0, null !== $end ? $end : 0 );
	}
}
