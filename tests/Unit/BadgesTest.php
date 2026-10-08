<?php

declare(strict_types=1);

namespace Suntourz\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Suntourz\Core\Domain\Badges;

final class BadgesTest extends TestCase {

	/**
	 * @param array<string, mixed> $override Inputs to override.
	 * @return string[] Badge types.
	 */
	private function types( array $override = array() ): array {
		$badges = Badges::compute(
			$override + array(
				'has_discount'        => false,
				'special_offer'       => false,
				'has_bookable'        => true,
				'next_start'          => '2030-03-01',
				'next_seats_left'     => 10,
				'today'               => '2030-01-01',
				'last_minute_days'    => 14,
				'few_seats_threshold' => 4,
			)
		);

		return array_column( $badges, 'type' );
	}

	public function test_plain_tour_has_no_badges(): void {
		$this->assertSame( array(), $this->types() );
	}

	public function test_discount_and_special_offer(): void {
		$this->assertSame( array( 'discount', 'special_offer' ), $this->types( array( 'has_discount' => true, 'special_offer' => true ) ) );
	}

	public function test_last_minute_inside_window(): void {
		$this->assertContains( 'last_minute', $this->types( array( 'next_start' => '2030-01-15' ) ) );
		$this->assertContains( 'last_minute', $this->types( array( 'next_start' => '2030-01-08' ) ) );
	}

	public function test_not_last_minute_outside_window(): void {
		$this->assertNotContains( 'last_minute', $this->types( array( 'next_start' => '2030-01-16' ) ) );
	}

	public function test_few_seats_carries_the_seat_count(): void {
		$badges = Badges::compute(
			array(
				'has_discount'        => false,
				'special_offer'       => false,
				'has_bookable'        => true,
				'next_start'          => '2030-03-01',
				'next_seats_left'     => 3,
				'today'               => '2030-01-01',
				'last_minute_days'    => 14,
				'few_seats_threshold' => 4,
			)
		);

		$this->assertSame( array( array( 'type' => 'few_seats', 'seats' => 3 ) ), $badges );
	}

	public function test_threshold_is_inclusive_and_plenty_of_seats_is_not_few(): void {
		$this->assertContains( 'few_seats', $this->types( array( 'next_seats_left' => 4 ) ) );
		$this->assertNotContains( 'few_seats', $this->types( array( 'next_seats_left' => 5 ) ) );
	}

	public function test_sold_out_hides_urgency_badges(): void {
		$types = $this->types(
			array(
				'has_bookable'    => false,
				'next_start'      => null,
				'next_seats_left' => null,
			)
		);

		$this->assertSame( array( 'sold_out' ), $types );
	}

	public function test_sold_out_can_still_show_discount(): void {
		$this->assertSame(
			array( 'discount', 'sold_out' ),
			$this->types( array( 'has_discount' => true, 'has_bookable' => false, 'next_start' => null, 'next_seats_left' => null ) )
		);
	}

	public function test_days_between(): void {
		$this->assertSame( 14, Badges::days_between( '2030-01-01', '2030-01-15' ) );
		$this->assertSame( -1, Badges::days_between( '2030-01-02', '2030-01-01' ) );
	}
}
