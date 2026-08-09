<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;
use WP_Post;

/**
 * teda/donate — the phase-1 donation page (SPEC §7, D11). A complete donation
 * experience with the transaction routed OFFLINE: mobile money + bank + prefilled
 * WhatsApp/email, driven by an amount selector whose choice carries into the
 * prefilled message.
 *
 * Mode comes from the Customizer setting `teda_donate_mode` (offline|live, default
 * offline). Live mode only renders a real payment path when a gateway is configured
 * (a filter that is false until P20); until then the public always sees the offline
 * page and admins additionally see a "not configured" notice. It is therefore
 * impossible to expose a broken payment path to the public.
 *
 * The impact tiers, project cards and trust strip render identically in both modes.
 * Every amount states its currency explicitly — never inferred (§7 edge cases).
 */
final class Donate extends Block_Renderer {

	private const TIERS = 5;

	public function name(): string {
		return 'teda/donate';
	}

	protected function render_content( array $attributes, string $content, WP_Block $block ): string {
		$rate     = max( 1, $this->int_attr( $attributes, 'usd_rate', 3800, 1, PHP_INT_MAX ) );
		$tiers    = $this->tiers( $attributes, $rate );
		$default  = $tiers[1] ?? $tiers[0]; // 2nd tier is the pre-selected amount.

		$out  = '<div class="teda-donate" id="teda-donate">';
		$out .= '<div class="teda-donate__grid">';

		// Main column.
		$out .= '<div class="teda-donate__main">';
		$lead = $this->str_attr( $attributes, 'lead' );
		if ( '' !== $lead ) {
			$out .= '<p class="teda-donate__lead">' . esc_html( $lead ) . '</p>';
		}
		$out .= $this->impact_tiers( $tiers );
		$out .= $this->project_cards();
		$out .= $this->antifraud( $attributes );
		$out .= $this->recent_updates();
		$out .= '</div>';

		// Sticky panel.
		$out .= $this->panel( $attributes, $tiers, $default, $rate );

		$out .= '</div>'; // grid

		$out .= $this->trust_strip( $attributes );
		$out .= $this->other_ways( $attributes );

		$out .= '</div>';

		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Panel (mode-aware)                                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array<string, mixed>              $attributes Attributes.
	 * @param array<int, array<string, mixed>>  $tiers      Tiers.
	 * @param array<string, mixed>              $default    Pre-selected tier.
	 */
	private function panel( array $attributes, array $tiers, array $default, int $rate ): string {
		$mode       = (string) get_theme_mod( 'teda_donate_mode', 'offline' );
		$configured = (bool) apply_filters( 'teda_core/donate/live_configured', false );

		$out = '<aside class="teda-donate__panel" id="teda-donate-panel">';

		// Admin-only warning when live mode is on but no gateway exists.
		if ( 'live' === $mode && ! $configured && current_user_can( 'manage_options' ) ) {
			$out .= '<div class="teda-donate__adminnotice"><strong>' . esc_html__( 'Live mode is on but no payment gateway is configured.', 'teda-core' )
				. '</strong> ' . esc_html__( 'Visitors are seeing the offline donation route. Configure a gateway (phase 2) before switching this on for real.', 'teda-core' ) . '</div>';
		}

		$out .= '<h2 class="teda-donate__paneltitle">' . esc_html__( 'Make a donation', 'teda-core' ) . '</h2>';
		$out .= '<p class="teda-donate__panelsub">' . esc_html__( 'Choose an amount, then send it by mobile money or bank transfer.', 'teda-core' ) . '</p>';

		$out .= $this->selector( $tiers, $default, $rate );

		// Phase 1 always renders the offline route to the public (live path is only
		// shown when a gateway is actually configured — never in phase 1).
		$out .= ( 'live' === $mode && $configured )
			? $this->live_route( $attributes, $default )
			: $this->offline_route( $attributes, $default );

		$out .= '</aside>';

		return $out;
	}

	/**
	 * The amount selector: frequency, currency, preset amounts + custom. Data
	 * attributes let donate.js keep the offline CTAs in sync.
	 *
	 * @param array<int, array<string, mixed>> $tiers   Tiers.
	 * @param array<string, mixed>             $default Pre-selected tier.
	 */
	private function selector( array $tiers, array $default, int $rate ): string {
		$out = '<div class="teda-donate__selector" data-teda-rate="' . esc_attr( (string) $rate ) . '" data-teda-amount="' . esc_attr( (string) $default['amount'] ) . '" data-teda-freq="once" data-teda-cur="UGX">';

		$out .= '<div class="teda-donate__freq" role="group" aria-label="' . esc_attr__( 'Giving frequency', 'teda-core' ) . '">'
			. '<button type="button" class="teda-chip is-on" data-teda-freq="once" aria-pressed="true">' . esc_html__( 'Give once', 'teda-core' ) . '</button>'
			. '<button type="button" class="teda-chip" data-teda-freq="monthly" aria-pressed="false">' . esc_html__( 'Give monthly', 'teda-core' ) . '</button>'
			. '</div>';

		$out .= '<div class="teda-donate__cur" role="group" aria-label="' . esc_attr__( 'Currency', 'teda-core' ) . '">'
			. '<button type="button" class="teda-chip is-on" data-teda-cur="UGX" aria-pressed="true">UGX</button>'
			. '<button type="button" class="teda-chip" data-teda-cur="USD" aria-pressed="false">USD</button>'
			. '</div>';

		$out .= '<div class="teda-donate__amounts" role="group" aria-label="' . esc_attr__( 'Amount', 'teda-core' ) . '">';
		foreach ( $tiers as $tier ) {
			$on   = $tier['amount'] === $default['amount'];
			$out .= '<button type="button" class="teda-donate__amt' . ( $on ? ' is-on' : '' ) . '" data-teda-set="' . esc_attr( (string) $tier['amount'] ) . '" aria-pressed="' . ( $on ? 'true' : 'false' ) . '">'
				. '<span class="teda-donate__amt-ugx">UGX ' . esc_html( number_format( $tier['amount'] ) ) . '</span>'
				. '<small class="teda-donate__amt-usd">' . esc_html( sprintf( /* translators: %s: USD amount. */ __( '≈ USD %s', 'teda-core' ), number_format( $tier['usd'] ) ) ) . '</small>'
				. '</button>';
		}
		$out .= '<button type="button" class="teda-donate__amt" data-teda-set="custom" aria-pressed="false">' . esc_html__( 'Other', 'teda-core' ) . '</button>';
		$out .= '</div>';

		$out .= '<label class="teda-donate__customwrap" hidden><span class="teda-donate__customlabel">' . esc_html__( 'Amount in UGX', 'teda-core' ) . '</span>'
			. '<input type="number" min="1000" step="1000" class="teda-donate__custom" inputmode="numeric"></label>';

		return $out . '</div>';
	}

	/**
	 * The OFFLINE route (primary in phase 1): mobile money lines + prefilled WhatsApp
	 * and email CTAs. The server-rendered hrefs already carry the default amount and
	 * currency, so they work without JavaScript (§10.3); donate.js updates them.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param array<string, mixed> $default    Pre-selected tier.
	 */
	private function offline_route( array $attributes, array $default ): string {
		$whatsapp = preg_replace( '/\D+/', '', $this->str_attr( $attributes, 'whatsapp', '256700000000' ) );
		$email    = $this->str_attr( $attributes, 'email', 'tedayouthteso@gmail.com' );
		$mtn      = $this->str_attr( $attributes, 'mtn' );
		$airtel   = $this->str_attr( $attributes, 'airtel' );

		$msg      = $this->message( $default['amount'], $default['usd'] );
		$wa_href  = 'https://wa.me/' . rawurlencode( (string) $whatsapp ) . '?text=' . rawurlencode( $msg );
		$mail_sub = __( 'Donation to TEDA', 'teda-core' );
		$mail_href = 'mailto:' . rawurlencode( $email ) . '?subject=' . rawurlencode( $mail_sub ) . '&body=' . rawurlencode( $msg );

		$out = '<div class="teda-donate__offline">';

		$out .= '<div class="teda-way teda-way--accent"><h3>' . esc_html__( 'Mobile money', 'teda-core' ) . '</h3>';
		$out .= '<p>' . esc_html__( 'Send', 'teda-core' ) . ' <b data-teda-amount-text>UGX ' . esc_html( number_format( $default['amount'] ) ) . '</b> '
			. esc_html__( 'to our registered organization line, then tell us so we can record it.', 'teda-core' ) . '</p>';
		if ( '' !== $mtn ) {
			$out .= '<code>' . esc_html( $mtn ) . '</code>';
		}
		if ( '' !== $airtel ) {
			$out .= '<code>' . esc_html( $airtel ) . '</code>';
		}
		$out .= '</div>';

		$out .= '<a class="teda-btn teda-btn--green teda-btn--lg teda-donate__cta" data-teda-whatsapp href="' . esc_url( $wa_href ) . '" data-teda-base="https://wa.me/' . esc_attr( (string) $whatsapp ) . '?text=">'
			. esc_html__( 'Tell us on WhatsApp', 'teda-core' ) . '</a>';
		$out .= '<a class="teda-btn teda-btn--ghost-b teda-donate__cta" data-teda-email href="' . esc_url( $mail_href ) . '" data-teda-base="mailto:' . esc_attr( $email ) . '?subject=' . esc_attr( rawurlencode( $mail_sub ) ) . '&amp;body=">'
			. esc_html__( 'Email us', 'teda-core' ) . '</a>';

		$out .= '<p class="teda-donate__note">' . esc_html__( 'Live card and mobile money checkout arrives in phase 2, once payment gateway registration is complete.', 'teda-core' ) . '</p>';

		return $out . '</div>';
	}

	/**
	 * The LIVE route scaffold. Only reached when a gateway is configured (never in
	 * phase 1). Kept minimal — P20 fills in the real form.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param array<string, mixed> $default    Pre-selected tier.
	 */
	private function live_route( array $attributes, array $default ): string {
		return '<div class="teda-donate__live"><p>' . esc_html__( 'Secure checkout — mobile money or card. The currency you will be charged is confirmed on the next step, never inferred.', 'teda-core' ) . '</p>'
			. '<a class="teda-btn teda-btn--brown teda-btn--lg" href="#" data-teda-donate-submit>' . esc_html( sprintf( /* translators: %s: amount. */ __( 'Donate UGX %s', 'teda-core' ), number_format( $default['amount'] ) ) ) . '</a></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Mode-independent sections (D11: identical in both modes)            */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array<int, array<string, mixed>> $tiers Tiers.
	 */
	private function impact_tiers( array $tiers ): string {
		$out = '<div class="teda-donate__section"><span class="teda-eyebrow">' . esc_html__( 'Your impact', 'teda-core' ) . '</span>'
			. '<h2 class="teda-display">' . esc_html__( 'What your gift does', 'teda-core' ) . '</h2>'
			. '<div class="teda-donate__tiers">';
		foreach ( $tiers as $tier ) {
			$out .= '<div class="teda-tier"><b class="teda-tier__amount">UGX ' . esc_html( number_format( $tier['amount'] ) )
				. '<small>' . esc_html( sprintf( /* translators: %s: USD amount. */ __( '≈ USD %s', 'teda-core' ), number_format( $tier['usd'] ) ) ) . '</small></b>'
				. '<div class="teda-tier__body"><p class="teda-tier__desc">' . esc_html( $tier['desc'] ) . '</p></div></div>';
		}
		return $out . '</div></div>';
	}

	/**
	 * Project cards, one per published focus area (choose where the gift goes). Each
	 * links to the donation panel. Rendered only when focus areas exist.
	 */
	private function project_cards(): string {
		$query = Query::get(
			array(
				'post_type'      => 'teda_focus_area',
				'posts_per_page' => 6,
			)
		);
		if ( ! $query->have_posts() ) {
			return '';
		}

		$out = '<div class="teda-donate__section"><span class="teda-eyebrow">' . esc_html__( 'Choose a cause', 'teda-core' ) . '</span>'
			. '<h2 class="teda-display">' . esc_html__( 'Where your gift goes', 'teda-core' ) . '</h2><div class="teda-donate__projects">';
		foreach ( $query->posts as $post ) {
			$summary = (string) teda_field( 'teda_summary', $post->ID, '' );
			if ( '' === $summary ) {
				$summary = wp_strip_all_tags( get_the_excerpt( $post ) );
			}
			$out .= '<div class="teda-project"><h3>' . esc_html( get_the_title( $post ) ) . '</h3>';
			if ( '' !== $summary ) {
				$out .= '<p>' . esc_html( $summary ) . '</p>';
			}
			$out .= '<a class="teda-btn teda-btn--brown" href="#teda-donate-panel">' . esc_html__( 'Give to this', 'teda-core' ) . '</a></div>';
		}
		wp_reset_postdata();

		return $out . '</div></div>';
	}

	/**
	 * The anti-fraud safety note — retained verbatim and kept prominent (§7). Not
	 * softened, and shown on every screen size.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function antifraud( array $attributes ): string {
		$text = $this->str_attr( $attributes, 'antifraud' );
		if ( '' === $text ) {
			return '';
		}
		return '<div class="teda-donate__warn" role="note"><h3>' . esc_html__( 'A note on safety', 'teda-core' ) . '</h3><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Recent updates — the latest News (GlobalGiving's project-reports pattern, §7).
	 */
	private function recent_updates(): string {
		$news = render_block(
			array(
				'blockName'    => 'teda/news',
				'attrs'        => array( 'eyebrow' => __( 'Recent updates', 'teda-core' ), 'heading' => __( 'What your gift has done', 'teda-core' ), 'count' => 3 ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		return '<div class="teda-donate__section teda-donate__updates">' . $news . '</div>';
	}

	/**
	 * Trust strip: registration, accountability, a named contact, and the anti-fraud
	 * commitment (§7 item 5).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function trust_strip( array $attributes ): string {
		$registration   = $this->str_attr( $attributes, 'registration' );
		$accountability = $this->str_attr( $attributes, 'accountability' );

		$out = '<div class="teda-donate__trust"><div class="teda-trust">';
		$out .= '<div><h3>' . esc_html__( 'Registered organization', 'teda-core' ) . '</h3><p>' . esc_html( $registration ) . '</p></div>';
		$out .= '<div><h3>' . esc_html__( 'Where the money goes', 'teda-core' ) . '</h3><p>' . esc_html( $accountability ) . '</p></div>';
		$out .= '<div><h3>' . esc_html__( 'Talk to a person', 'teda-core' ) . '</h3><p>' . esc_html__( 'Every donation has a named contact at TEDA. Reach us on WhatsApp or email any time.', 'teda-core' ) . '</p></div>';
		$out .= '<div><h3>' . esc_html__( 'Only these channels', 'teda-core' ) . '</h3><p>' . esc_html__( 'TEDA collects donations only through the channels on this page. Anything else is not us.', 'teda-core' ) . '</p></div>';
		return $out . '</div></div>';
	}

	/**
	 * Other ways to give (§7 item 7).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function other_ways( array $attributes ): string {
		$mtn   = $this->str_attr( $attributes, 'mtn' );
		$bank  = $this->str_attr( $attributes, 'bank' );
		$email = $this->str_attr( $attributes, 'email', 'tedayouthteso@gmail.com' );

		$out = '<div class="teda-donate__section"><span class="teda-eyebrow">' . esc_html__( 'Other ways to give', 'teda-core' ) . '</span>'
			. '<h2 class="teda-display">' . esc_html__( 'Prefer another route?', 'teda-core' ) . '</h2><div class="teda-donate__ways">';
		$out .= '<div class="teda-way"><h3>' . esc_html__( 'Mobile money', 'teda-core' ) . '</h3><p>' . esc_html__( 'Send directly to our registered organization line.', 'teda-core' ) . '</p>' . ( '' !== $mtn ? '<code>' . esc_html( $mtn ) . '</code>' : '' ) . '</div>';
		$out .= '<div class="teda-way"><h3>' . esc_html__( 'Bank transfer', 'teda-core' ) . '</h3><p>' . esc_html( $bank ) . '</p></div>';
		$out .= '<div class="teda-way"><h3>' . esc_html__( 'In kind &amp; time', 'teda-core' ) . '</h3><p>' . esc_html__( 'Books, seedlings, equipment, or your skills as a volunteer mentor.', 'teda-core' ) . '</p><code>' . esc_html( $email ) . '</code></div>';
		return $out . '</div></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * The prefilled message for WhatsApp/email, naming the currency explicitly.
	 */
	private function message( int $ugx, int $usd ): string {
		return sprintf(
			/* translators: 1: UGX amount, 2: USD amount. */
			__( 'Hello TEDA, I would like to donate UGX %1$s (about USD %2$s) as a one-off gift. Please tell me how to complete it.', 'teda-core' ),
			number_format( $ugx ),
			number_format( $usd )
		);
	}

	/**
	 * Normalise the five tiers with computed USD approximations.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<int, array{amount:int, usd:int, desc:string}>
	 */
	private function tiers( array $attributes, int $rate ): array {
		$tiers = array();
		for ( $n = 1; $n <= self::TIERS; $n++ ) {
			$amount = $this->int_attr( $attributes, "t{$n}_amount", 0, 0, PHP_INT_MAX );
			if ( $amount <= 0 ) {
				continue;
			}
			$tiers[] = array(
				'amount' => $amount,
				'usd'    => (int) round( $amount / $rate ),
				'desc'   => $this->str_attr( $attributes, "t{$n}_desc" ),
			);
		}
		return $tiers;
	}
}
