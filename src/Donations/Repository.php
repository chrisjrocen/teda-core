<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Donations;

/**
 * All reads/writes to `{$wpdb->prefix}teda_donations`. Every query is prepared;
 * every write stamps `updated_at`.
 */
final class Repository {

	private function table(): string {
		return Migrations::table_name();
	}

	/**
	 * @param array<string, mixed> $data Column => value for every insertable column.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$row = wp_parse_args(
			$data,
			array(
				'donor_name'                 => '',
				'donor_phone'                => '',
				'focus_area_id'              => null,
				'frequency'                  => Record::FREQUENCY_ONCE,
				'method'                     => '',
				'status'                     => Record::STATUS_PENDING,
				'pesapal_order_tracking_id'  => '',
				'pesapal_merchant_reference' => '',
				'is_recurring'               => 0,
				'subscription_end_date'      => null,
				'pledge_active'              => 0,
				'pledge_token'               => '',
			)
		);
		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		$wpdb->insert( $this->table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return (int) $wpdb->insert_id;
	}

	public function find( int $id ): ?Record {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? Record::from_row( $row ) : null;
	}

	public function find_by_reference( string $reference ): ?Record {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE reference = %s", $reference ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? Record::from_row( $row ) : null;
	}

	public function find_by_tracking_id( string $tracking_id ): ?Record {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE pesapal_order_tracking_id = %s", $tracking_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? Record::from_row( $row ) : null;
	}

	public function find_by_pledge_token( string $token ): ?Record {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE pledge_token = %s", $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? Record::from_row( $row ) : null;
	}

	/**
	 * @param array<string, mixed> $extra Additional columns to update alongside status.
	 */
	public function update_status( int $id, string $status, array $extra = array() ): void {
		global $wpdb;

		$data          = $extra;
		$data['status'] = $status;
		$data['updated_at'] = current_time( 'mysql', true );

		$wpdb->update( $this->table(), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function set_pledge_active( int $id, bool $active ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array( 'pledge_active' => $active ? 1 : 0, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $id )
		);
	}

	public function touch_reminder( int $id ): void {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array( 'last_reminder_sent_at' => $now, 'updated_at' => $now ),
			array( 'id' => $id )
		);
	}

	/**
	 * Active mobile-money/UGX pledges due a reminder — every row with
	 * pledge_active = 1 (Pledge_Reminders decides frequency of sending).
	 *
	 * @return array<int, Record>
	 */
	public function active_pledges(): array {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$this->table()} WHERE pledge_active = 1", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return array_map( array( Record::class, 'from_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * @param array{status?:string, currency?:string, frequency?:string, from?:string, to?:string} $filters
	 * @return array<int, Record>
	 */
	public function all_for_export( array $filters = array() ): array {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $filters['status'];
		}
		if ( ! empty( $filters['currency'] ) ) {
			$where[]  = 'currency = %s';
			$params[] = $filters['currency'];
		}
		if ( ! empty( $filters['frequency'] ) ) {
			$where[]  = 'frequency = %s';
			$params[] = $filters['frequency'];
		}
		if ( ! empty( $filters['from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $filters['from'];
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $filters['to'];
		}

		$sql = "SELECT * FROM {$this->table()} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC';
		if ( array() !== $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return array_map( array( Record::class, 'from_row' ), is_array( $rows ) ? $rows : array() );
	}
}
