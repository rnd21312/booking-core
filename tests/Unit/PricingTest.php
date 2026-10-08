<?php

declare(strict_types=1);

namespace Suntourz\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Suntourz\Core\Domain\Pricing;

final class PricingTest extends TestCase {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function plans(): array {
		return array(
			array( 'id' => 'single', 'label' => 'Single', 'pax' => 1, 'price' => 1800000, 'sale_price' => null ),
			array( 'id' => 'couple', 'label' => 'Couple', 'pax' => 2, 'price' => 3200000, 'sale_price' => 2900000 ),
			array( 'id' => 'family', 'label' => 'Family', 'pax' => 4, 'price' => 5600000, 'sale_price' => null ),
			array(
				'id'                 => 'family_plus',
				'label'              => 'Family+',
				'pax'                => 5,
				'price'              => 6600000,
				'sale_price'         => null,
				'extra_person_price' => 1100000,
				'max_pax'            => 8,
			),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function extras(): array {
		return array(
			array( 'id' => 'transfer', 'label' => 'Airport transfer', 'price' => 150000, 'unit' => 'per_booking', 'max_qty' => 2 ),
			array( 'id' => 'diving', 'label' => 'Diving add-on', 'price' => 250000, 'unit' => 'per_person', 'max_qty' => null ),
		);
	}

	/**
	 * @param array<int, array{field: string, code: string}> $errors Errors.
	 * @return string[]
	 */
	private function codes( array $errors ): array {
		return array_map( static fn ( array $e ): string => $e['field'] . ':' . $e['code'], $errors );
	}

	/* ---- plan prices ---- */

	public function test_regular_plan_price(): void {
		$prices = Pricing::plan_prices( $this->plans()[0] );

		$this->assertSame( array( 'price' => 1800000, 'sale_price' => null, 'effective' => 1800000 ), $prices );
	}

	public function test_sale_price_becomes_effective(): void {
		$prices = Pricing::plan_prices( $this->plans()[1] );

		$this->assertSame( 2900000, $prices['effective'] );
		$this->assertSame( 3200000, $prices['price'] );
		$this->assertSame( 2900000, $prices['sale_price'] );
	}

	public function test_sale_price_not_below_regular_is_ignored(): void {
		$plan               = $this->plans()[0];
		$plan['sale_price'] = 1800000;

		$this->assertNull( Pricing::plan_prices( $plan )['sale_price'] );
		$this->assertSame( 1800000, Pricing::plan_prices( $plan )['effective'] );
	}

	public function test_departure_override_replaces_price(): void {
		$prices = Pricing::plan_prices( $this->plans()[0], 2000000 );

		$this->assertSame( 2000000, $prices['effective'] );
	}

	public function test_departure_override_can_set_and_clear_sale_price(): void {
		$set = Pricing::plan_prices( $this->plans()[0], array( 'sale_price' => 1500000 ) );
		$this->assertSame( 1500000, $set['effective'] );
		$this->assertSame( 1800000, $set['price'] );

		$clear = Pricing::plan_prices( $this->plans()[1], array( 'sale_price' => null ) );
		$this->assertSame( 3200000, $clear['effective'] );
		$this->assertNull( $clear['sale_price'] );
	}

	public function test_garbage_override_is_ignored(): void {
		$this->assertSame( 1800000, Pricing::plan_prices( $this->plans()[0], 'abc' )['effective'] );
		$this->assertSame( array(), Pricing::normalize_override( new \stdClass() ) );
	}

	public function test_lowest_and_discount_detection(): void {
		$lowest = Pricing::lowest( $this->plans() );
		$this->assertSame( 'single', $lowest['plan_id'] );
		$this->assertSame( 1800000, $lowest['effective'] );

		$this->assertTrue( Pricing::has_discount( $this->plans() ) );
		$this->assertFalse( Pricing::has_discount( array( $this->plans()[0] ) ) );
		$this->assertTrue( Pricing::has_discount( array( $this->plans()[0] ), array( 'single' => array( 'sale_price' => 100 ) ) ) );
		$this->assertNull( Pricing::lowest( array() ) );
	}

	/* ---- quote: base plans ---- */

	public function test_quote_single_plan(): void {
		$quote = Pricing::quote( $this->plans()[0], null, null, $this->extras(), array() );

		$this->assertTrue( $quote['ok'] );
		$this->assertSame( 1, $quote['pax'] );
		$this->assertSame( 1800000, $quote['total'] );
	}

	public function test_quote_uses_sale_price(): void {
		$quote = Pricing::quote( $this->plans()[1], null, null, array(), array() );

		$this->assertSame( 2900000, $quote['total'] );
		$this->assertSame( 2, $quote['pax'] );
	}

	public function test_quote_honours_departure_override(): void {
		$quote = Pricing::quote( $this->plans()[2], 6000000, null, array(), array() );

		$this->assertSame( 6000000, $quote['total'] );
	}

	public function test_fixed_plan_rejects_a_different_pax(): void {
		$quote = Pricing::quote( $this->plans()[1], null, 3, array(), array() );

		$this->assertFalse( $quote['ok'] );
		$this->assertSame( array( 'pax:pax_invalid' ), $this->codes( $quote['errors'] ) );
		$this->assertSame( 2900000, $quote['total'], 'falls back to the plan group size' );
	}

	/* ---- quote: Family+ ---- */

	public function test_family_plus_base_group_has_no_surcharge(): void {
		$quote = Pricing::quote( $this->plans()[3], null, 5, array(), array() );

		$this->assertTrue( $quote['ok'] );
		$this->assertSame( 6600000, $quote['total'] );
	}

	public function test_family_plus_adds_price_per_extra_person(): void {
		$quote = Pricing::quote( $this->plans()[3], null, 7, array(), array() );

		$this->assertTrue( $quote['ok'] );
		$this->assertSame( 6600000 + 2 * 1100000, $quote['total'] );
		$this->assertSame( 'extra_person', $quote['lines'][1]['type'] );
		$this->assertSame( 2, $quote['lines'][1]['qty'] );
	}

	public function test_family_plus_respects_max_pax_and_minimum(): void {
		$over = Pricing::quote( $this->plans()[3], null, 9, array(), array() );
		$this->assertSame( array( 'pax:pax_invalid' ), $this->codes( $over['errors'] ) );
		$this->assertSame( 8, $over['pax'] );

		$under = Pricing::quote( $this->plans()[3], null, 4, array(), array() );
		$this->assertSame( array( 'pax:pax_invalid' ), $this->codes( $under['errors'] ) );

		$max = Pricing::quote( $this->plans()[3], null, 8, array(), array() );
		$this->assertTrue( $max['ok'] );
		$this->assertSame( 6600000 + 3 * 1100000, $max['total'] );
	}

	public function test_family_plus_sale_applies_to_base_only(): void {
		$plan               = $this->plans()[3];
		$plan['sale_price'] = 6000000;

		$quote = Pricing::quote( $plan, null, 6, array(), array() );

		$this->assertSame( 6000000 + 1100000, $quote['total'] );
	}

	/* ---- quote: extras ---- */

	public function test_per_booking_extra(): void {
		$quote = Pricing::quote( $this->plans()[0], null, null, $this->extras(), array( array( 'id' => 'transfer', 'qty' => 2 ) ) );

		$this->assertTrue( $quote['ok'] );
		$this->assertSame( 1800000 + 2 * 150000, $quote['total'] );
	}

	public function test_per_person_extra_is_capped_by_pax(): void {
		$ok = Pricing::quote( $this->plans()[1], null, null, $this->extras(), array( array( 'id' => 'diving', 'qty' => 2 ) ) );
		$this->assertTrue( $ok['ok'] );
		$this->assertSame( 2900000 + 2 * 250000, $ok['total'] );

		$too_many = Pricing::quote( $this->plans()[1], null, null, $this->extras(), array( array( 'id' => 'diving', 'qty' => 3 ) ) );
		$this->assertSame( array( 'extras.diving:extra_qty_exceeded' ), $this->codes( $too_many['errors'] ) );
		$this->assertSame( 2900000, $too_many['total'], 'invalid extra is not charged' );
	}

	public function test_extra_max_qty_is_enforced(): void {
		$quote = Pricing::quote( $this->plans()[0], null, null, $this->extras(), array( array( 'id' => 'transfer', 'qty' => 3 ) ) );

		$this->assertSame( array( 'extras.transfer:extra_qty_exceeded' ), $this->codes( $quote['errors'] ) );
	}

	public function test_unknown_duplicate_and_negative_extras(): void {
		$unknown = Pricing::quote( $this->plans()[0], null, null, $this->extras(), array( array( 'id' => 'nope', 'qty' => 1 ) ) );
		$this->assertSame( array( 'extras.nope:extra_unknown' ), $this->codes( $unknown['errors'] ) );

		$duplicate = Pricing::quote(
			$this->plans()[0],
			null,
			null,
			$this->extras(),
			array( array( 'id' => 'transfer', 'qty' => 1 ), array( 'id' => 'transfer', 'qty' => 1 ) )
		);
		$this->assertSame( array( 'extras.transfer:extra_unknown' ), $this->codes( $duplicate['errors'] ) );

		$negative = Pricing::quote( $this->plans()[0], null, null, $this->extras(), array( array( 'id' => 'transfer', 'qty' => -1 ) ) );
		$this->assertSame( array( 'extras.transfer:extra_qty_invalid' ), $this->codes( $negative['errors'] ) );
	}

	public function test_zero_quantity_extras_are_skipped(): void {
		$quote = Pricing::quote( $this->plans()[0], null, null, $this->extras(), array( array( 'id' => 'transfer', 'qty' => 0 ) ) );

		$this->assertTrue( $quote['ok'] );
		$this->assertCount( 1, $quote['lines'] );
		$this->assertSame( 1800000, $quote['total'] );
	}

	public function test_total_is_sum_of_lines(): void {
		$quote = Pricing::quote(
			$this->plans()[3],
			array( 'price' => 7000000 ),
			7,
			$this->extras(),
			array( array( 'id' => 'transfer', 'qty' => 1 ), array( 'id' => 'diving', 'qty' => 3 ) )
		);

		$this->assertTrue( $quote['ok'] );
		$this->assertSame( array_sum( array_column( $quote['lines'], 'amount' ) ), $quote['total'] );
		$this->assertSame( 7000000 + 2 * 1100000 + 150000 + 3 * 250000, $quote['total'] );
	}
}
