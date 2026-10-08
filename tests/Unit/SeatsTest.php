<?php

declare(strict_types=1);

namespace Suntourz\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Suntourz\Core\Domain\Seats;

final class SeatsTest extends TestCase {

	public function test_seats_left_is_capacity_minus_booked(): void {
		$this->assertSame( 6, Seats::left( 10, 4 ) );
		$this->assertSame( 0, Seats::left( 10, 10 ) );
	}

	public function test_seats_left_never_negative(): void {
		$this->assertSame( 0, Seats::left( 10, 14 ) );
		$this->assertSame( 10, Seats::left( 10, -3 ) );
	}

	public function test_open_future_departure_with_seats_is_bookable(): void {
		$this->assertTrue( Seats::is_bookable( 'open', '2030-01-10', '2030-01-01', 3, 2 ) );
	}

	public function test_exact_remaining_seats_is_bookable(): void {
		$this->assertTrue( Seats::is_bookable( 'open', '2030-01-10', '2030-01-01', 2, 2 ) );
	}

	public function test_not_enough_seats_is_not_bookable(): void {
		$this->assertFalse( Seats::is_bookable( 'open', '2030-01-10', '2030-01-01', 1, 2 ) );
		$this->assertFalse( Seats::is_bookable( 'open', '2030-01-10', '2030-01-01', 0 ) );
	}

	public function test_only_open_departures_are_bookable(): void {
		foreach ( array( 'draft', 'closed', 'cancelled' ) as $status ) {
			$this->assertFalse( Seats::is_bookable( $status, '2030-01-10', '2030-01-01', 5 ), $status );
		}
	}

	public function test_today_and_past_departures_are_not_bookable(): void {
		$this->assertFalse( Seats::is_bookable( 'open', '2030-01-01', '2030-01-01', 5 ) );
		$this->assertFalse( Seats::is_bookable( 'open', '2029-12-31', '2030-01-01', 5 ) );
	}

	public function test_zero_pax_is_not_bookable(): void {
		$this->assertFalse( Seats::is_bookable( 'open', '2030-01-10', '2030-01-01', 5, 0 ) );
	}
}
