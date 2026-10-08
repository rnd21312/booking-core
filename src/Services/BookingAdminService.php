<?php
/**
 * Admin-side booking management: list/filter, details with history, status changes
 * (validated by BookingStatus), payment info, notes and CSV export.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Services;

use Suntourz\Core\Domain\BookingStatus;
use Suntourz\Core\Install\Schema;
use Suntourz\Core\PostTypes\TourMeta;
use Suntourz\Core\Settings\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class BookingAdminService {

	public const PAYMENT_METHODS = array( 'cash', 'bank_transfer', 'card', 'promptpay', 'other' );

	public function __construct( private Settings $settings ) {}

	/**
	 * @param array<string, mixed> $filters status, tour_id, departure_id, date_from, date_to, search, page, per_page.
	 * @return array{items: array<int, array<string, mixed>>, total: int, total_pages: int, page: int, per_page: int, counts: array<string, int>}
	 */
	public function list( array $filters ): array {
		global $wpdb;

		[ $where, $params ] = $this->where( $filters );
		$table              = Schema::bookings();
		$per_page           = max( 1, min( 100, (int) ( $filters['per_page'] ?? 20 ) ) );
		$page               = max( 1, (int) ( $filters['page'] ?? 1 ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table name from Schema; $where is built from fixed fragments with placeholders.
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) : "SELECT COUNT(*) FROM {$table} WHERE {$where}" );

		$sql  = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A );

		$counts = array();
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A ) as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['n'];
		}
		// phpcs:enable

		return array(
			'items'       => array_map( array( $this, 'payload' ), is_array( $rows ) ? $rows : array() ),
			'total'       => $total,
			'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
			'page'        => $page,
			'per_page'    => $per_page,
			'counts'      => $counts,
		);
	}

	/**
	 * One booking with its status history.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		$row = $this->row( $id );
		if ( null === $row ) {
			return null;
		}

		return $this->payload( $row ) + array( 'history' => $this->history( $id ) );
	}

	/**
	 * Applies a change set. Status moves must be allowed by BookingStatus; every change is logged.
	 *
	 * @param array<string, mixed> $patch status?, note?, admin_note?, amount_paid?, payment_method?.
	 * @return array<string, mixed>|WP_Error The updated booking.
	 */
	public function update( int $id, array $patch ): array|WP_Error {
		global $wpdb;

		$row = $this->row( $id );
		if ( null === $row ) {
			return new WP_Error( 'stz_booking_not_found', __( 'Booking not found.', 'suntourz' ), array( 'status' => 404 ) );
		}

		$errors  = array();
		$changes = array();
		$logs    = array();
		$note    = isset( $patch['note'] ) ? mb_substr( sanitize_text_field( (string) $patch['note'] ), 0, 500 ) : '';
		$user_id = get_current_user_id();

		if ( isset( $patch['status'] ) && (string) $patch['status'] !== $row['status'] ) {
			$to = (string) $patch['status'];
			if ( ! BookingStatus::can_transition( (string) $row['status'], $to ) ) {
				$errors['status'] = sprintf(
					/* translators: 1: current status, 2: requested status. */
					__( 'A booking cannot move from "%1$s" to "%2$s".', 'suntourz' ),
					$row['status'],
					$to
				);
			} else {
				$changes['status'] = $to;
				$logs[]            = array( (string) $row['status'], $to, '' !== $note ? $note : '' );
			}
		}

		if ( array_key_exists( 'admin_note', $patch ) ) {
			$changes['admin_note'] = mb_substr( sanitize_textarea_field( (string) $patch['admin_note'] ), 0, 4000 );
		}

		$paid_changed = false;
		if ( array_key_exists( 'amount_paid', $patch ) ) {
			$amount = $patch['amount_paid'];
			if ( ! is_numeric( $amount ) || (int) $amount < 0 ) {
				$errors['amount_paid'] = __( 'Enter a valid amount.', 'suntourz' );
			} else {
				$changes['amount_paid'] = (int) $amount;
				$paid_changed           = (int) $amount !== (int) $row['amount_paid'];
			}
		}

		if ( array_key_exists( 'payment_method', $patch ) ) {
			$method = sanitize_key( (string) $patch['payment_method'] );
			if ( '' !== $method && ! in_array( $method, self::PAYMENT_METHODS, true ) ) {
				$errors['payment_method'] = __( 'Choose a valid payment method.', 'suntourz' );
			} else {
				$changes['payment_method'] = '' === $method ? null : $method;
				$paid_changed              = $paid_changed || ( (string) $method !== (string) $row['payment_method'] );
			}
		}

		if ( array() !== $errors ) {
			return new WP_Error( 'stz_invalid_booking_update', __( 'Some fields need your attention.', 'suntourz' ), array( 'status' => 422, 'errors' => $errors ) );
		}

		if ( array() === $changes ) {
			return $this->get( $id ) ?? array();
		}

		$changes['updated_at'] = current_time( 'mysql', true );
		$wpdb->update( Schema::bookings(), $changes, array( 'id' => $id ) );

		foreach ( $logs as [ $from, $to, $log_note ] ) {
			$this->log( $id, $from, $to, $user_id, $log_note );
		}
		if ( $paid_changed ) {
			$status = (string) ( $changes['status'] ?? $row['status'] );
			$this->log(
				$id,
				$status,
				$status,
				$user_id,
				sprintf(
					/* translators: 1: amount paid, 2: payment method. */
					__( 'Payment updated: %1$s paid via %2$s', 'suntourz' ),
					$this->money( (int) ( $changes['amount_paid'] ?? $row['amount_paid'] ) ),
					str_replace( '_', ' ', (string) ( ( $changes['payment_method'] ?? $row['payment_method'] ) ?: __( 'unspecified method', 'suntourz' ) ) )
				)
			);
		}

		return $this->get( $id ) ?? array();
	}

	/**
	 * Number of bookings waiting for a first reply (menu bubble).
	 */
	public function new_count(): int {
		global $wpdb;

		$table = Schema::bookings();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", BookingStatus::NEW ) );
	}

	/**
	 * CSV (UTF-8 with BOM so Excel opens Thai text correctly) of the filtered bookings.
	 *
	 * @param array<string, mixed> $filters Same filters as list().
	 */
	public function csv( array $filters ): string {
		$filters['per_page'] = 100;
		$page                = 1;
		$handle              = fopen( 'php://temp', 'r+' );

		fwrite( $handle, "\xEF\xBB\xBF" );
		fputcsv(
			$handle,
			array( 'Code', 'Created', 'Status', 'Tour', 'Departure start', 'Departure end', 'Plan', 'Travellers', 'Total', 'Paid', 'Currency', 'Payment method', 'Name', 'Email', 'Phone', 'Contact channel', 'Contact handle', 'Message', 'Admin note' )
		);

		do {
			$filters['page'] = $page;
			$result          = $this->list( $filters );
			foreach ( $result['items'] as $b ) {
				fputcsv(
					$handle,
					array_map(
						static fn ( $v ): string => self::csv_safe( (string) $v ),
						array(
							$b['code'],
							$b['created_at'],
							$b['status'],
							$b['tour']['title'],
							$b['departure']['start_date'],
							$b['departure']['end_date'],
							$b['plan_label'],
							$b['pax'],
							$b['total_amount'] / $this->settings->currency()['minor_unit'],
							$b['amount_paid'] / $this->settings->currency()['minor_unit'],
							$b['currency'],
							$b['payment_method'],
							$b['customer']['name'],
							$b['customer']['email'],
							$b['customer']['phone'],
							$b['contact']['channel'],
							$b['contact']['handle'],
							$b['message'],
							$b['admin_note'],
						)
					)
				);
			}
			++$page;
		} while ( $page <= $result['total_pages'] );

		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle );

		return $csv;
	}

	private function money( int $minor ): string {
		$c     = $this->settings->currency();
		$value = number_format( $minor / (int) $c['minor_unit'], (int) $c['decimals'], (string) $c['decimal_separator'], (string) $c['thousand_separator'] );

		return 'after' === $c['position'] ? $value . ' ' . $c['symbol'] : $c['symbol'] . $value;
	}

	/** Neutralises spreadsheet formula injection in exported cells. */
	private static function csv_safe( string $value ): string {
		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) && ! is_numeric( $value ) ? "'" . $value : $value;
	}

	/**
	 * @param array<string, mixed> $filters Filters.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function where( array $filters ): array {
		global $wpdb;

		$clauses = array( '1=1' );
		$params  = array();

		$status = (string) ( $filters['status'] ?? '' );
		if ( '' !== $status && BookingStatus::is_valid( $status ) ) {
			$clauses[] = 'status = %s';
			$params[]  = $status;
		}
		foreach ( array( 'tour_id', 'departure_id' ) as $key ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$clauses[] = "{$key} = %d";
				$params[]  = (int) $filters[ $key ];
			}
		}
		foreach ( array(
			'date_from' => 'created_at >= %s',
			'date_to'   => 'created_at <= %s',
		) as $key => $clause ) {
			$date = (string) ( $filters[ $key ] ?? '' );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				$clauses[] = $clause;
				$params[]  = $date . ( 'date_to' === $key ? ' 23:59:59' : ' 00:00:00' );
			}
		}

		$search = trim( (string) ( $filters['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$clauses[] = '(code LIKE %s OR customer_name LIKE %s OR customer_phone LIKE %s OR customer_email LIKE %s)';
			array_push( $params, $like, $like, $like, $like );
		}

		return array( implode( ' AND ', $clauses ), $params );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function row( int $id ): ?array {
		global $wpdb;

		$table = Schema::bookings();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function history( int $id ): array {
		global $wpdb;

		$table = Schema::booking_log();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE booking_id = %d ORDER BY id DESC", $id ), ARRAY_A );

		return array_map(
			static function ( array $log ): array {
				$user = $log['user_id'] ? get_userdata( (int) $log['user_id'] ) : false;

				return array(
					'from'    => $log['from_status'],
					'to'      => $log['to_status'],
					'by'      => $user ? $user->display_name : __( 'Guest / system', 'suntourz' ),
					'note'    => (string) $log['note'],
					'at'      => (string) $log['created_at'],
				);
			},
			is_array( $rows ) ? $rows : array()
		);
	}

	private function log( int $booking_id, string $from, string $to, int $user_id, string $note ): void {
		global $wpdb;

		$wpdb->insert(
			Schema::booking_log(),
			array(
				'booking_id'  => $booking_id,
				'from_status' => $from,
				'to_status'   => $to,
				'user_id'     => $user_id > 0 ? $user_id : null,
				'note'        => '' === $note ? null : $note,
				'created_at'  => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $row Booking row.
	 * @return array<string, mixed>
	 */
	private function payload( array $row ): array {
		$tour_id   = (int) $row['tour_id'];
		$departure = $this->departure( (int) $row['departure_id'] );
		$meta      = TourMeta::get( $tour_id, $this->settings );
		$plan      = '';
		foreach ( $meta['pricing']['plans'] as $candidate ) {
			if ( $candidate['id'] === $row['plan_id'] ) {
				$plan = $candidate['label'];
			}
		}
		$extras = json_decode( (string) ( $row['extras'] ?? '' ), true );

		return array(
			'id'              => (int) $row['id'],
			'code'            => (string) $row['code'],
			'status'          => (string) $row['status'],
			'allowed'         => BookingStatus::allowed_from( (string) $row['status'] ),
			'tour'            => array(
				'id'    => $tour_id,
				'title' => html_entity_decode( get_the_title( $tour_id ), ENT_QUOTES, 'UTF-8' ) ?: __( '(deleted tour)', 'suntourz' ),
				'edit'  => (string) get_edit_post_link( $tour_id, 'raw' ),
			),
			'departure'       => $departure,
			'plan_id'         => (string) $row['plan_id'],
			'plan_label'      => $plan ?: (string) $row['plan_id'],
			'pax'             => (int) $row['pax'],
			'extras'          => is_array( $extras ) ? $extras : array(),
			'total_amount'    => (int) $row['total_amount'],
			'amount_paid'     => (int) $row['amount_paid'],
			'currency'        => (string) $row['currency'],
			'payment_method'  => (string) ( $row['payment_method'] ?? '' ),
			'customer'        => array(
				'name'  => (string) $row['customer_name'],
				'email' => (string) $row['customer_email'],
				'phone' => (string) $row['customer_phone'],
			),
			'contact'         => array(
				'channel' => (string) $row['contact_channel'],
				'handle'  => (string) ( $row['contact_handle'] ?? '' ),
			),
			'message'         => (string) ( $row['message'] ?? '' ),
			'admin_note'      => (string) ( $row['admin_note'] ?? '' ),
			'locale'          => (string) ( $row['locale'] ?? '' ),
			'created_at'      => (string) $row['created_at'],
			'updated_at'      => (string) $row['updated_at'],
		);
	}

	/**
	 * @return array{id: int, start_date: string, end_date: string}
	 */
	private function departure( int $id ): array {
		global $wpdb;

		$table = Schema::departures();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT start_date, end_date FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return array(
			'id'         => $id,
			'start_date' => (string) ( $row['start_date'] ?? '' ),
			'end_date'   => (string) ( $row['end_date'] ?? '' ),
		);
	}
}
