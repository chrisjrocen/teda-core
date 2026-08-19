<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use WP_Block;
use WP_Post;

/**
 * teda/donate — the donation page (SPEC §7, D11, P20). Phase 1 shipped a
 * complete OFFLINE experience: mobile money + bank + prefilled WhatsApp/email,
 * driven by an amount selector. Phase 2 (this file) adds a real LIVE route via
 * Pesapal (Teda_Core\Donations) for donors who prefer online checkout.
 *
 * Mode comes from the Customizer setting `teda_donate_mode` (offline|live, default
 * offline). Live mode only renders a real payment path when a gateway is configured
 * (`teda_core/donate/live_configured`, wired to Donations\Config::is_configured());
 * until then the public always sees the offline page and admins additionally see a
 * "not configured" notice. It is therefore impossible to expose a broken payment
 * path to the public.
 *
 * Currency is real, not converted: a donor picks USD or UGX and every amount shown
 * — impact tiers, selector, and (when live) the Pesapal charge itself — is in that
 * exact currency. Nothing here derives one currency's amount from the other.
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
		$currency = $this->str_attr( $attributes, 'currency_default', 'UGX' );
		$currency = 'USD' === $currency ? 'USD' : 'UGX';

		$tiers = array(
			'UGX' => $this->tiers( $attributes, 'UGX' ),
			'USD' => $this->tiers( $attributes, 'USD' ),
		);
		$default_set = $tiers[ $currency ];
		$default     = $default_set[1] ?? ( $default_set[0] ?? array( 'amount' => 0, 'desc' => '' ) ); // 2nd tier is the pre-selected amount.

		$out  = '<div class="teda-donate" id="teda-donate">';
		$out .= '<div class="teda-donate__grid">';

		// Main column.
		$out .= '<div class="teda-donate__main">';
		$lead = $this->str_attr( $attributes, 'lead' );
		if ( '' !== $lead ) {
			$out .= '<p class="teda-donate__lead">' . esc_html( $lead ) . '</p>';
		}
		$out .= $this->impact_tiers( $tiers, $currency );
		$out .= $this->project_cards();
		$out .= $this->antifraud( $attributes );
		$out .= $this->recent_updates();
		$out .= '</div>';

		// Sticky panel.
		$out .= $this->panel( $attributes, $tiers, $currency );

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
	 * @param array<string, mixed>                          $attributes Attributes.
	 * @param array<string, array<int, array<string, mixed>>> $tiers    Tiers keyed by currency.
	 */
	private function panel( array $attributes, array $tiers, string $currency ): string {
		$mode       = (string) get_theme_mod( 'teda_donate_mode', 'offline' );
		$configured = (bool) apply_filters( 'teda_core/donate/live_configured', false );

		$default_set = $tiers[ $currency ];
		$default     = $default_set[1] ?? ( $default_set[0] ?? array( 'amount' => 0, 'desc' => '' ) );

		$out = '<aside class="teda-donate__panel" id="teda-donate-panel">';

		// Admin-only warning when live mode is on but no gateway exists.
		if ( 'live' === $mode && ! $configured && current_user_can( 'manage_options' ) ) {
			$out .= '<div class="teda-donate__adminnotice"><strong>' . esc_html__( 'Live mode is on but no payment gateway is configured.', 'teda-core' )
				. '</strong> ' . esc_html__( 'Visitors are seeing the offline donation route. Add Pesapal credentials and register the IPN URL under Donations → Settings.', 'teda-core' ) . '</div>';
		}

		$out .= '<h2 class="teda-donate__paneltitle">' . esc_html__( 'Make a donation', 'teda-core' ) . '</h2>';
		$out .= '<p class="teda-donate__panelsub">' . esc_html__( 'Choose an amount, then send it by mobile money or bank transfer.', 'teda-core' ) . '</p>';

		$out .= $this->selector( $tiers, $default, $currency );

		// Phase 1 always renders the offline route to the public (live path is only
		// shown when a gateway is actually configured — never in phase 1).
		$out .= ( 'live' === $mode && $configured )
			? $this->live_route( $attributes, $default, $currency )
			: $this->offline_route( $attributes );

		$out .= '</aside>';

		return $out;
	}

	/**
	 * The amount selector: frequency, currency, preset amounts + custom. Amounts
	 * for both currencies are rendered up front (one group hidden via CSS) so
	 * donate.js can toggle currency client-side with no re-render — each amount
	 * is a real tier in its own currency, never a computed conversion.
	 *
	 * @param array<string, array<int, array<string, mixed>>> $tiers   Tiers keyed by currency.
	 * @param array<string, mixed>                             $default Pre-selected tier (in $currency).
	 */
	private function selector( array $tiers, array $default, string $currency ): string {
		$out = '<div class="teda-donate__selector" data-teda-amount="' . esc_attr( (string) $default['amount'] ) . '" data-teda-freq="once" data-teda-cur="' . esc_attr( $currency ) . '">';

		$out .= '<div class="teda-donate__freq" role="group" aria-label="' . esc_attr__( 'Giving frequency', 'teda-core' ) . '">'
			. '<button type="button" class="teda-chip is-on" data-teda-freq="once" aria-pressed="true">' . esc_html__( 'Give once', 'teda-core' ) . '</button>'
			. '<button type="button" class="teda-chip" data-teda-freq="monthly" aria-pressed="false">' . esc_html__( 'Give monthly', 'teda-core' ) . '</button>'
			. '</div>';

		$out .= '<div class="teda-donate__cur" role="group" aria-label="' . esc_attr__( 'Currency', 'teda-core' ) . '">'
			. '<button type="button" class="teda-chip' . ( 'UGX' === $currency ? ' is-on' : '' ) . '" data-teda-cur="UGX" aria-pressed="' . ( 'UGX' === $currency ? 'true' : 'false' ) . '">UGX</button>'
			. '<button type="button" class="teda-chip' . ( 'USD' === $currency ? ' is-on' : '' ) . '" data-teda-cur="USD" aria-pressed="' . ( 'USD' === $currency ? 'true' : 'false' ) . '">USD</button>'
			. '</div>';

		$out .= $this->amount_group( 'UGX', $tiers['UGX'], $default, $currency );
		$out .= $this->amount_group( 'USD', $tiers['USD'], $default, $currency );

		$out .= '<label class="teda-donate__customwrap" hidden><span class="teda-donate__customlabel" data-teda-customlabel>' . esc_html( sprintf(
			/* translators: %s: currency code. */
			__( 'Amount in %s', 'teda-core' ),
			$currency
		) ) . '</span>'
			. '<input type="number" min="1" step="1" class="teda-donate__custom" inputmode="numeric"></label>';

		return $out . '</div>';
	}

	/**
	 * One currency's amount-button group. Only the active currency's group is
	 * visible; donate.js toggles `hidden` when the currency chip changes.
	 *
	 * @param array<int, array<string, mixed>> $tiers   This currency's tiers.
	 * @param array<string, mixed>             $default The overall selected tier (may belong to the other currency).
	 */
	private function amount_group( string $group_currency, array $tiers, array $default, string $active_currency ): string {
		$hidden = $group_currency !== $active_currency;

		$out = '<div class="teda-donate__amounts" data-teda-tier-cur="' . esc_attr( $group_currency ) . '"' . ( $hidden ? ' hidden' : '' ) . ' role="group" aria-label="' . esc_attr__( 'Amount', 'teda-core' ) . '">';
		foreach ( $tiers as $tier ) {
			$on = ! $hidden && $tier['amount'] === $default['amount'];
			$out .= '<button type="button" class="teda-donate__amt' . ( $on ? ' is-on' : '' ) . '" data-teda-set="' . esc_attr( (string) $tier['amount'] ) . '" aria-pressed="' . ( $on ? 'true' : 'false' ) . '">'
				. esc_html( $group_currency . ' ' . number_format( $tier['amount'] ) ) . '</button>';
		}
		$out .= '<button type="button" class="teda-donate__amt" data-teda-set="custom" aria-pressed="false">' . esc_html__( 'Other', 'teda-core' ) . '</button>';
		return $out . '</div>';
	}

	/**
	 * The OFFLINE route (mobile money + bank + prefilled WhatsApp/email). These
	 * channels are UGX-denominated in practice (local mobile money, local bank
	 * account), so — unlike the live route — this always quotes the UGX tiers,
	 * regardless of which currency is toggled above; the amount is still always
	 * explicit, never inferred.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private function offline_route( array $attributes ): string {
		$ugx_tiers = $this->tiers( $attributes, 'UGX' );
		$default   = $ugx_tiers[1] ?? ( $ugx_tiers[0] ?? array( 'amount' => 0, 'desc' => '' ) );

		$whatsapp = preg_replace( '/\D+/', '', $this->str_attr( $attributes, 'whatsapp', '256700000000' ) );
		$email    = $this->str_attr( $attributes, 'email', 'tedayouthteso@gmail.com' );
		$mtn      = $this->str_attr( $attributes, 'mtn' );
		$airtel   = $this->str_attr( $attributes, 'airtel' );

		$msg      = $this->message( (int) $default['amount'] );
		$wa_href  = 'https://wa.me/' . rawurlencode( (string) $whatsapp ) . '?text=' . rawurlencode( $msg );
		$mail_sub = __( 'Donation to TEDA', 'teda-core' );
		$mail_href = 'mailto:' . rawurlencode( $email ) . '?subject=' . rawurlencode( $mail_sub ) . '&body=' . rawurlencode( $msg );

		$out = '<div class="teda-donate__offline">';

		$out .= '<div class="teda-way teda-way--accent"><h3>' . esc_html__( 'Mobile money', 'teda-core' ) . '</h3>';
		$out .= '<p>' . esc_html__( 'Send', 'teda-core' ) . ' <b data-teda-amount-text>UGX ' . esc_html( number_format( (int) $default['amount'] ) ) . '</b> '
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

		$out .= '<p class="teda-donate__note">' . esc_html__( 'Prefer USD or card? Turn on live checkout above once it is configured — it charges the exact currency you choose.', 'teda-core' ) . '</p>';

		return $out . '</div>';
	}

	/**
	 * The LIVE route: a small donor-detail form that POSTs to
	 * `teda/v1/donations` (Donations\Rest_Controller) and redirects to Pesapal's
	 * hosted checkout on success. Requires JS — the offline route above remains
	 * the no-JS-safe default whenever live mode isn't actually configured, so
	 * this never becomes the only path a donor can reach.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param array<string, mixed> $default    Pre-selected tier.
	 */
	private function live_route( array $attributes, array $default, string $currency ): string {
		$nonce = wp_create_nonce( 'wp_rest' );

		$out  = '<div class="teda-donate__live" data-teda-live-panel>';
		$out .= '<p>' . esc_html__( 'Secure checkout hosted by Pesapal — mobile money or card. The currency you are charged is confirmed on the next step, never inferred.', 'teda-core' ) . '</p>';

		$out .= '<label>' . esc_html__( 'Full name', 'teda-core' ) . '<input type="text" name="donor_name" required></label>';
		$out .= '<label>' . esc_html__( 'Email', 'teda-core' ) . '<input type="email" name="donor_email" required></label>';
		$out .= '<label>' . esc_html__( 'Phone (for mobile money)', 'teda-core' ) . '<input type="tel" name="donor_phone"></label>';
		$out .= '<input type="hidden" name="focus_area_id" value="">';

		$out .= '<button type="button" class="teda-btn teda-btn--brown teda-btn--lg" data-teda-donate-submit'
			. ' data-teda-rest-nonce="' . esc_attr( $nonce ) . '"'
			. ' data-teda-rest-url="' . esc_url( rest_url( 'teda/v1/donations' ) ) . '">'
			. esc_html( sprintf( /* translators: 1: currency, 2: amount. */ __( 'Donate %1$s %2$s', 'teda-core' ), $currency, number_format( (int) $default['amount'] ) ) )
			. '</button>';

		$out .= '<p class="teda-donate__error" data-teda-donate-error hidden role="alert"></p>';
		$out .= '<p class="teda-donate__note">' . esc_html__( 'A monthly card gift renews automatically via Pesapal, who will email you a link to manage or cancel it. A monthly mobile-money gift is a reminder, not an auto-charge — you send it again each month, and can stop the reminder any time.', 'teda-core' ) . '</p>';

		return $out . '</div>';
	}

	/* ------------------------------------------------------------------ */
	/* Mode-independent sections (D11: identical in both modes)            */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array<string, array<int, array<string, mixed>>> $tiers Tiers keyed by currency.
	 */
	private function impact_tiers( array $tiers, string $currency ): string {
		$out = '<div class="teda-donate__section"><span class="teda-eyebrow">' . esc_html__( 'Your impact', 'teda-core' ) . '</span>'
			. '<h2 class="teda-display">' . esc_html__( 'What your gift does', 'teda-core' ) . '</h2>';

		foreach ( array( 'UGX', 'USD' ) as $group_currency ) {
			$hidden = $group_currency !== $currency;
			$out   .= '<div class="teda-donate__tiers" data-teda-tier-cur="' . esc_attr( $group_currency ) . '"' . ( $hidden ? ' hidden' : '' ) . '>';
			foreach ( $tiers[ $group_currency ] as $tier ) {
				$out .= '<div class="teda-tier"><b class="teda-tier__amount">' . esc_html( $group_currency . ' ' . number_format( $tier['amount'] ) ) . '</b>'
					. '<div class="teda-tier__body"><p class="teda-tier__desc">' . esc_html( $tier['desc'] ) . '</p></div></div>';
			}
			$out .= '</div>';
		}

		return $out . '</div>';
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
			$out .= '<a class="teda-btn teda-btn--brown" href="#teda-donate-panel" data-teda-focus-area="' . esc_attr( (string) $post->ID ) . '">' . esc_html__( 'Give to this', 'teda-core' ) . '</a></div>';
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
	 * The prefilled offline message, always in UGX (§ offline_route note).
	 */
	private function message( int $ugx ): string {
		return sprintf(
			/* translators: %s: UGX amount. */
			__( 'Hello TEDA, I would like to donate UGX %s as a one-off gift. Please tell me how to complete it.', 'teda-core' ),
			number_format( $ugx )
		);
	}

	/**
	 * This currency's five tiers, each a real amount in that currency — never
	 * derived from the other currency.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<int, array{amount:int, desc:string}>
	 */
	private function tiers( array $attributes, string $currency ): array {
		$prefix = 'USD' === $currency ? 'usd' : 'ugx';
		$tiers  = array();
		for ( $n = 1; $n <= self::TIERS; $n++ ) {
			$amount = $this->int_attr( $attributes, "{$prefix}_t{$n}_amount", 0, 0, PHP_INT_MAX );
			if ( $amount <= 0 ) {
				continue;
			}
			$tiers[] = array(
				'amount' => $amount,
				'desc'   => $this->str_attr( $attributes, "{$prefix}_t{$n}_desc" ),
			);
		}
		return $tiers;
	}
}
