<?php
/**
 * Departure data access. Booked pax come from the bookings table in the same query,
 * so "seats left" is always computed from the source of truth.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Repository;

use Suntourz\Core\Domain\BookingStatus;
use Suntourz\Core\Domain\DepartureStatus;
use Suntourz\Core\Domain\Seats;
use Suntourz\Core\Install\Schema;
use Suntourz\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class DepartureRepository {

	public function __construct( private Settings $settings ) {}

	/**
	 * Site-local "today" (Y-m-d) used for all future/past checks.
	 */
	public function today(): string {
		return wp_date( 'Y-m-d' );
	}

	/**
	 * One departure with booked pax and seats left, or null.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		$rows = $this->select( 'd.id = %d', array( $id ) );

		return $rows[0] ?? null;
	}

	/**
	 * Public (open/closed) future departures of several tours, grouped by tour id, soonest first.
	 *
	 * @param int[] $tour_ids Tour ids.
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	public function upcoming_for_tours( array $tour_ids ): array {
		$tour_ids = array_values( array_filter( array_map( 'absint', $tour_ids ) ) );
		if ( array() === $tour_ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $tour_ids ), '%d' ) );
		$statuses     = DepartureStatus::public();
		$status_in    = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$rows = $this->select(
			"d.tour_id IN ({$placeholders}) AND d.status IN ({$status_in}) AND d.start_date > %s",
			array_merge( $tour_ids, $statuses, array( $this->today() ) )
		);

		$grouped = array();
		foreach ( $rows as $row ) {
			$grouped[ $row['tour_id'] ][] = $row;
		}

		return $grouped;
	}

	/**
	 * Core query: departures joined with the pax of seat-holding bookings.
	 *
	 * @param string            $where  WHERE clause on alias `d` (uses %d / %s placeholders).
	 * @param array<int, mixed> $params Placeholder values.
	 * @return array<int, array<string, mixed>>
	 */
	private function select( string $where, array $params ): array {
		global $wpdb;

		$holding   = BookingStatus::seat_holding( $this->settings->pending_holds_seats() );
		$hold_in   = implode( ',', array_fill( 0, count( $holding ), '%s' ) );
		$departure = Schema::departures();
		$booking   = Schema::bookings();

		$sql = "SELECT d.*, COALESCE(SUM(CASE WHEN b.status IN ({$hold_in}) THEN b.pax ELSE 0 END), 0) AS booked_pax, COUNT(b.id) AS bookings_count
			FROM {$departure} d
			LEFT JOIN {$booking} b ON b.departure_id = d.id
			WHERE {$where}
			GROUP BY d.id
			ORDER BY d.start_date ASC, d.id ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table names and placeholder lists are built above from constants.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $holding, $params ) ), ARRAY_A );

		return array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * @param array<string, mixed> $row Raw DB row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$overrides = json_decode( (string) ( $row['price_overrides'] ?? '' ), true );
		$capacity  = (int) $row['capacity'];
		$booked    = (int) $row['booked_pax'];

		return array(
			'id'              => (int) $row['id'],
			'tour_id'         => (int) $row['tour_id'],
			'start_date'      => (string) $row['start_date'],
			'end_date'        => (string) $row['end_date'],
			'capacity'        => $capacity,
			'status'          => (string) $row['status'],
			'price_overrides' => is_array( $overrides ) ? $overrides : array(),
			'note'            => (string) ( $row['note'] ?? '' ),
			'booked_pax'      => $booked,
			'seats_left'      => Seats::left( $capacity, $booked ),
			'bookings_count'  => (int) ( $row['bookings_count'] ?? 0 ),
		);
	}

	/**
	 * Every departure of a tour (all statuses, past and future) — admin use.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all_for_tour( int $tour_id ): array {
		return $this->select( 'd.tour_id = %d', array( $tour_id ) );
	}

	/**
	 * @param array{start_date: string, end_date: string, capacity: int, status: string, price_overrides: array<string, mixed>, note: string} $data Validated values.
	 */
	public function insert( int $tour_id, array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Schema::departures(),
			$this->row( $data ) + array(
				'tour_id'    => $tour_id,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array{start_date: string, end_date: string, capacity: int, status: string, price_overrides: array<string, mixed>, note: string} $data Validated values.
	 */
	public function update( int $id, array $data ): void {
		global $wpdb;

		$wpdb->update(
			Schema::departures(),
			$this->row( $data ) + array( 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $id )
		);
	}

	public function set_status( int $id, string $status ): void {
		global $wpdb;

		$wpdb->update(
			Schema::departures(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id )
		);
	}

	public function delete( int $id ): void {
		global $wpdb;

		$wpdb->delete( Schema::departures(), array( 'id' => $id ) );
	}

	/**
	 * @param array<string, mixed> $data Validated values.
	 * @return array<string, mixed>
	 */
	private function row( array $data ): array {
		return array(
			'start_date'      => $data['start_date'],
			'end_date'        => $data['end_date'],
			'capacity'        => $data['capacity'],
			'status'          => $data['status'],
			'price_overrides' => array() === $data['price_overrides'] ? null : wp_json_encode( $data['price_overrides'] ),
			'note'            => '' === $data['note'] ? null : $data['note'],
		);
	}
}
