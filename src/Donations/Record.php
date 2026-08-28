<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

/**
 * A donation row, read from `{$wpdb->prefix}teda_donations`. Deliberately a
 * plain data holder (Repository owns all reads/writes) — no behaviour here.
 */
final class Record {

	public const STATUS_PENDING   = 'pending';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_FAILED    = 'failed';
	public const STATUS_CANCELLED = 'cancelled';

	public const METHOD_CARD         = 'card';
	public const METHOD_MOBILE_MONEY = 'mobile_money';

	public const FREQUENCY_ONCE    = 'once';
	public const FREQUENCY_MONTHLY = 'monthly';

	public const CURRENCY_USD = 'USD';
	public const CURRENCY_UGX = 'UGX';

	public int $id;
	public string $reference;
	public string $donor_name;
	public string $donor_email;
	public string $donor_phone;
	public float $amount;
	public string $currency;
	public ?int $focus_area_id;
	public ?string $goal_label;
	public string $frequency;
	public string $method;
	public string $status;
	public string $pesapal_order_tracking_id;
	public string $pesapal_merchant_reference;
	public bool $is_recurring;
	public ?string $subscription_end_date;
	public bool $pledge_active;
	public string $pledge_token;
	public ?string $last_reminder_sent_at;
	public string $created_at;
	public string $updated_at;

	/**
	 * @param array<string, mixed> $row A raw $wpdb row (ARRAY_A).
	 */
	public static function from_row( array $row ): self {
		$record                            = new self();
		$record->id                        = (int) $row['id'];
		$record->reference                 = (string) $row['reference'];
		$record->donor_name                = (string) $row['donor_name'];
		$record->donor_email               = (string) $row['donor_email'];
		$record->donor_phone               = (string) $row['donor_phone'];
		$record->amount                    = (float) $row['amount'];
		$record->currency                  = (string) $row['currency'];
		$record->focus_area_id             = null !== $row['focus_area_id'] ? (int) $row['focus_area_id'] : null;
		$record->goal_label                = isset( $row['goal_label'] ) && null !== $row['goal_label'] ? (string) $row['goal_label'] : null;
		$record->frequency                 = (string) $row['frequency'];
		$record->method                    = (string) $row['method'];
		$record->status                    = (string) $row['status'];
		$record->pesapal_order_tracking_id = (string) $row['pesapal_order_tracking_id'];
		$record->pesapal_merchant_reference = (string) $row['pesapal_merchant_reference'];
		$record->is_recurring               = (bool) $row['is_recurring'];
		$record->subscription_end_date      = $row['subscription_end_date'] ?? null;
		$record->pledge_active              = (bool) $row['pledge_active'];
		$record->pledge_token                = (string) $row['pledge_token'];
		$record->last_reminder_sent_at      = $row['last_reminder_sent_at'] ?? null;
		$record->created_at                = (string) $row['created_at'];
		$record->updated_at                = (string) $row['updated_at'];

		return $record;
	}
}
