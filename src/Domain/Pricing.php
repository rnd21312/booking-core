<?php
/**
 * Server-side pricing. All money is integer minor units (satang); formatting is display-only.
 *
 * Pure domain class (no WordPress calls). The same code prices the public quote
 * and the booking that is stored, so a client can never dictate a total.
 *
 * Plan:   { id, label, pax, price, sale_price?, extra_person_price?, max_pax? }
 * Extra:  { id, label, price, unit: per_booking|per_person, max_qty? }
 * Override (per departure, keyed by plan id): int price, or { price?, sale_price? }.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Domain;

defined( 'ABSPATH' ) || exit;

final class Pricing {

	public const UNIT_PER_BOOKING = 'per_booking';
	public const UNIT_PER_PERSON  = 'per_person';

	/**
	 * Normalizes a departure price override for one plan.
	 *
	 * @param mixed $raw int|numeric string (price), array {price?, sale_price?}, or anything else (ignored).
	 * @return array{price?: int, sale_price?: ?int}
	 */
	public static function normalize_override( mixed $raw ): array {
		if ( is_numeric( $raw ) ) {
			return array( 'price' => max( 0, (int) $raw ) );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();

		if ( isset( $raw['price'] ) && is_numeric( $raw['price'] ) ) {
			$out['price'] = max( 0, (int) $raw['price'] );
		}

		if ( array_key_exists( 'sale_price', $raw ) ) {
			$sale              = $raw['sale_price'];
			$out['sale_price'] = ( null === $sale || '' === $sale || ! is_numeric( $sale ) ) ? null : max( 0, (int) $sale );
		}

		return $out;
	}

	/**
	 * Price of one plan after applying an optional departure override.
	 * A sale price only counts when it is lower than the regular price.
	 *
	 * @param array<string, mixed> $plan     Plan.
	 * @param mixed                $override Override for this plan (see normalize_override()).
	 * @return array{price: int, sale_price: ?int, effective: int}
	 */
	public static function plan_prices( array $plan, mixed $override = null ): array {
		$override = self::normalize_override( $override );

		$price = $override['price'] ?? (int) ( $plan['price'] ?? 0 );
		$sale  = array_key_exists( 'sale_price', $override )
			? $override['sale_price']
			: ( isset( $plan['sale_price'] ) ? (int) $plan['sale_price'] : null );

		if ( null !== $sale && $sale >= $price ) {
			$sale = null;
		}

		return array(
			'price'      => $price,
			'sale_price' => $sale,
			'effective'  => $sale ?? $price,
		);
	}

	/**
	 * Effective prices of every plan, keyed by plan id.
	 *
	 * @param array<int, array<string, mixed>> $plans     Plans.
	 * @param array<string, mixed>             $overrides Overrides keyed by plan id.
	 * @return array<string, array{price: int, sale_price: ?int, effective: int}>
	 */
	public static function prices_by_plan( array $plans, array $overrides = array() ): array {
		$out = array();

		foreach ( $plans as $plan ) {
			$id         = (string) ( $plan['id'] ?? '' );
			$out[ $id ] = self::plan_prices( $plan, $overrides[ $id ] ?? null );
		}

		return $out;
	}

	/**
	 * The cheapest plan (by effective price).
	 *
	 * @param array<int, array<string, mixed>> $plans     Plans.
	 * @param array<string, mixed>             $overrides Overrides keyed by plan id.
	 * @return array{plan_id: string, price: int, sale_price: ?int, effective: int}|null
	 */
	public static function lowest( array $plans, array $overrides = array() ): ?array {
		$best = null;

		foreach ( self::prices_by_plan( $plans, $overrides ) as $plan_id => $prices ) {
			if ( null === $best || $prices['effective'] < $best['effective'] ) {
				$best = array( 'plan_id' => (string) $plan_id ) + $prices;
			}
		}

		return $best;
	}

	/**
	 * Whether any plan is on sale.
	 *
	 * @param array<int, array<string, mixed>> $plans     Plans.
	 * @param array<string, mixed>             $overrides Overrides keyed by plan id.
	 */
	public static function has_discount( array $plans, array $overrides = array() ): bool {
		foreach ( self::prices_by_plan( $plans, $overrides ) as $prices ) {
			if ( null !== $prices['sale_price'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Finds a plan by id.
	 *
	 * @param array<int, array<string, mixed>> $plans   Plans.
	 * @param string                           $plan_id Plan id.
	 * @return array<string, mixed>|null
	 */
	public static function find_plan( array $plans, string $plan_id ): ?array {
		foreach ( $plans as $plan ) {
			if ( (string) ( $plan['id'] ?? '' ) === $plan_id ) {
				return $plan;
			}
		}

		return null;
	}

	/**
	 * Largest group a plan can take (plans without extra_person_price take exactly plan.pax).
	 *
	 * @param array<string, mixed> $plan Plan.
	 */
	public static function max_pax( array $plan ): int {
		$base = max( 1, (int) ( $plan['pax'] ?? 1 ) );

		if ( empty( $plan['extra_person_price'] ) ) {
			return $base;
		}

		return max( $base, (int) ( $plan['max_pax'] ?? $base ) );
	}

	/**
	 * Prices a booking.
	 *
	 * @param array<string, mixed>                       $plan      Chosen plan.
	 * @param mixed                                      $override  Departure override for that plan.
	 * @param int|null                                   $pax       Travellers; null → the plan's own pax.
	 * @param array<int, array<string, mixed>>           $extra_defs Extras defined on the tour.
	 * @param array<int, array{id: string, qty: int}>    $extras    Extras requested.
	 * @return array{
	 *   ok: bool,
	 *   pax: int,
	 *   total: int,
	 *   lines: array<int, array<string, mixed>>,
	 *   errors: array<int, array{field: string, code: string}>
	 * }
	 */
	public static function quote( array $plan, mixed $override, ?int $pax, array $extra_defs, array $extras ): array {
		$errors = array();
		$lines  = array();
		$total  = 0;

		$base_pax = max( 1, (int) ( $plan['pax'] ?? 1 ) );
		$max_pax  = self::max_pax( $plan );
		$pax      = $pax ?? $base_pax;

		if ( $pax < $base_pax || $pax > $max_pax ) {
			$errors[] = array(
				'field' => 'pax',
				'code'  => 'pax_invalid',
			);
			$pax      = min( max( $pax, $base_pax ), $max_pax );
		}

		$prices  = self::plan_prices( $plan, $override );
		$lines[] = array(
			'type'        => 'plan',
			'id'          => (string) ( $plan['id'] ?? '' ),
			'label'       => (string) ( $plan['label'] ?? '' ),
			'qty'         => 1,
			'unit_amount' => $prices['effective'],
			'amount'      => $prices['effective'],
		);
		$total  += $prices['effective'];

		$extra_people = $pax - $base_pax;
		if ( $extra_people > 0 ) {
			$unit     = (int) ( $plan['extra_person_price'] ?? 0 );
			$amount   = $unit * $extra_people;
			$lines[]  = array(
				'type'        => 'extra_person',
				'id'          => (string) ( $plan['id'] ?? '' ),
				'label'       => null,
				'qty'         => $extra_people,
				'unit_amount' => $unit,
				'amount'      => $amount,
			);
			$total   += $amount;
		}

		$defs_by_id = array();
		foreach ( $extra_defs as $def ) {
			$defs_by_id[ (string) ( $def['id'] ?? '' ) ] = $def;
		}

		$seen = array();
		foreach ( $extras as $request ) {
			$id  = (string) ( $request['id'] ?? '' );
			$qty = (int) ( $request['qty'] ?? 1 );

			if ( 0 === $qty ) {
				continue;
			}

			$field = 'extras.' . $id;

			if ( ! isset( $defs_by_id[ $id ] ) || isset( $seen[ $id ] ) ) {
				$errors[] = array(
					'field' => $field,
					'code'  => 'extra_unknown',
				);
				continue;
			}
			$seen[ $id ] = true;
			$def         = $defs_by_id[ $id ];

			$cap = isset( $def['max_qty'] ) && (int) $def['max_qty'] > 0 ? (int) $def['max_qty'] : PHP_INT_MAX;
			if ( self::UNIT_PER_PERSON === ( $def['unit'] ?? self::UNIT_PER_BOOKING ) ) {
				$cap = min( $cap, $pax );
			}

			if ( $qty < 1 ) {
				$errors[] = array(
					'field' => $field,
					'code'  => 'extra_qty_invalid',
				);
				continue;
			}

			if ( $qty > $cap ) {
				$errors[] = array(
					'field' => $field,
					'code'  => 'extra_qty_exceeded',
				);
				continue;
			}

			$unit     = (int) ( $def['price'] ?? 0 );
			$amount   = $unit * $qty;
			$lines[]  = array(
				'type'        => 'extra',
				'id'          => $id,
				'label'       => (string) ( $def['label'] ?? '' ),
				'qty'         => $qty,
				'unit_amount' => $unit,
				'amount'      => $amount,
			);
			$total   += $amount;
		}

		return array(
			'ok'     => array() === $errors,
			'pax'    => $pax,
			'total'  => $total,
			'lines'  => $lines,
			'errors' => $errors,
		);
	}
}
