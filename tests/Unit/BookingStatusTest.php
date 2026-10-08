<?php

declare(strict_types=1);

namespace Suntourz\Core\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Suntourz\Core\Domain\BookingStatus;

final class BookingStatusTest extends TestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function allowed(): array {
		return array(
			'new → contacted'       => array( 'new', 'contacted' ),
			'new → confirmed'       => array( 'new', 'confirmed' ),
			'new → cancelled'       => array( 'new', 'cancelled' ),
			'contacted → confirmed' => array( 'contacted', 'confirmed' ),
			'contacted → cancelled' => array( 'contacted', 'cancelled' ),
			'confirmed → paid'      => array( 'confirmed', 'paid' ),
			'confirmed → no_show'   => array( 'confirmed', 'no_show' ),
			'paid → completed'      => array( 'paid', 'completed' ),
			'paid → cancelled'      => array( 'paid', 'cancelled' ),
		);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function forbidden(): array {
		return array(
			'new → paid (skips confirmation)' => array( 'new', 'paid' ),
			'new → completed'                 => array( 'new', 'completed' ),
			'contacted → paid'                => array( 'contacted', 'paid' ),
			'confirmed → new (backwards)'     => array( 'confirmed', 'new' ),
			'paid → confirmed (backwards)'    => array( 'paid', 'confirmed' ),
			'completed → cancelled'           => array( 'completed', 'cancelled' ),
			'cancelled → new (terminal)'      => array( 'cancelled', 'new' ),
			'no_show → paid (terminal)'       => array( 'no_show', 'paid' ),
			'same status'                     => array( 'new', 'new' ),
			'unknown from'                    => array( 'bogus', 'paid' ),
			'unknown to'                      => array( 'new', 'bogus' ),
		);
	}

	#[DataProvider( 'allowed' )]
	public function test_allowed_transitions( string $from, string $to ): void {
		$this->assertTrue( BookingStatus::can_transition( $from, $to ) );
	}

	#[DataProvider( 'forbidden' )]
	public function test_forbidden_transitions( string $from, string $to ): void {
		$this->assertFalse( BookingStatus::can_transition( $from, $to ) );
	}

	public function test_terminal_statuses_have_no_exits(): void {
		foreach ( array( 'completed', 'cancelled', 'no_show' ) as $status ) {
			$this->assertSame( array(), BookingStatus::allowed_from( $status ) );
		}
	}

	public function test_every_status_is_valid_and_unknown_is_not(): void {
		foreach ( BookingStatus::all() as $status ) {
			$this->assertTrue( BookingStatus::is_valid( $status ) );
		}
		$this->assertFalse( BookingStatus::is_valid( 'bogus' ) );
	}

	public function test_seat_holding_statuses_with_pending_holding(): void {
		$this->assertEqualsCanonicalizing(
			array( 'new', 'contacted', 'confirmed', 'paid', 'completed' ),
			BookingStatus::seat_holding( true )
		);
		$this->assertTrue( BookingStatus::holds_seat( 'new', true ) );
		$this->assertFalse( BookingStatus::holds_seat( 'cancelled', true ) );
		$this->assertFalse( BookingStatus::holds_seat( 'no_show', true ) );
	}

	public function test_pending_bookings_do_not_hold_seats_when_disabled(): void {
		$this->assertFalse( BookingStatus::holds_seat( 'new', false ) );
		$this->assertFalse( BookingStatus::holds_seat( 'contacted', false ) );
		$this->assertTrue( BookingStatus::holds_seat( 'confirmed', false ) );
		$this->assertTrue( BookingStatus::holds_seat( 'paid', false ) );
	}
}
