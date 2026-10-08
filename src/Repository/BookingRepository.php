<?php
/**
 * Booking persistence. Creation runs in a transaction that locks the departure row,
 * so two simultaneous requests can never both take the last seat.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Repository;

use Suntourz\Core\Domain\BookingCode;
use Suntourz\Core\Domain\BookingStatus;
use Suntourz\Core\Install\Schema;

defined( 'ABSPATH' ) || exit;

final class BookingRepository {

	/**
	 * Locks the departure, runs $guard (return an error code to abort), then stores the booking and its first log entry.
	 *
	 * @param int                  $departure_id Departure to lock.
	 * @param array<string, mixed> $row          Booking columns (no id/code/status/timestamps).
	 * @param callable(): ?string  $guard        Runs under the lock; a non-null result aborts and is returned as the error code.
	 * @return array{code: string, id: int}|string Created booking, or the error code from $guard / "db_error".
	 */
	public function create_locked( int $departure_id, array $row, callable $guard ): array|string {
		global $wpdb;

		$departures = Schema::departures();

		$wpdb->query( 'START TRANSACTION' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
		$locked = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$departures} WHERE id = %d FOR UPDATE", $departure_id ) );
		if ( null === $locked ) {
			$wpdb->query( 'ROLLBACK' );

			return 'departure_not_found';
		}

		$error = $guard();
		if ( null !== $error ) {
			$wpdb->query( 'ROLLBACK' );

			return $error;
		}

		$code = $this->unique_code();
		$now  = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			Schema::bookings(),
			$row + array(
				'code'       => $code,
				'status'     => BookingStatus::NEW,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);

		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );

			return 'db_error';
		}

		$id = (int) $wpdb->insert_id;
		$wpdb->insert(
			Schema::booking_log(),
			array(
				'booking_id'  => $id,
				'from_status' => null,
				'to_status'   => BookingStatus::NEW,
				'user_id'     => null,
				'note'        => 'Booking request received',
				'created_at'  => $now,
			)
		);

		$wpdb->query( 'COMMIT' );

		return array(
			'code' => $code,
			'id'   => $id,
		);
	}

	/**
	 * A booking by code, or null.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find_by_code( string $code ): ?array {
		global $wpdb;

		$table = Schema::bookings();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s", $code ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	private function unique_code(): string {
		do {
			$code = BookingCode::generate();
		} while ( null !== $this->find_by_code( $code ) );

		return $code;
	}
}
