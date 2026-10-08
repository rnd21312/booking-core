<?php
/**
 * Seat availability rules.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Domain;

defined( 'ABSPATH' ) || exit;

final class Seats {

	/**
	 * Seats left = capacity − pax of seat-holding bookings (never below zero).
	 */
	public static function left( int $capacity, int $booked_pax ): int {
		return max( 0, $capacity - max( 0, $booked_pax ) );
	}

	/**
	 * A departure is bookable when it is open, starts in the future and has enough free seats.
	 *
	 * @param string $status     Departure status.
	 * @param string $start_date Y-m-d.
	 * @param string $today      Y-m-d in the site timezone.
	 * @param int    $seats_left Seats currently free.
	 * @param int    $pax        Travellers requested.
	 */
	public static function is_bookable( string $status, string $start_date, string $today, int $seats_left, int $pax = 1 ): bool {
		return DepartureStatus::OPEN === $status
			&& $start_date > $today
			&& $pax >= 1
			&& $seats_left >= $pax;
	}
}
