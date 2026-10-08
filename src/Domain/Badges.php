<?php
/**
 * Computed (never stored) tour badges.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Domain;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

final class Badges {

	public const DISCOUNT      = 'discount';
	public const LAST_MINUTE   = 'last_minute';
	public const FEW_SEATS     = 'few_seats';
	public const SPECIAL_OFFER = 'special_offer';
	public const SOLD_OUT      = 'sold_out';

	/**
	 * @param array{
	 *   has_discount: bool,
	 *   special_offer: bool,
	 *   has_bookable: bool,
	 *   next_start: ?string,
	 *   next_seats_left: ?int,
	 *   today: string,
	 *   last_minute_days: int,
	 *   few_seats_threshold: int
	 * } $in Inputs. "next_*" describe the nearest bookable departure.
	 * @return array<int, array{type: string, seats?: int}>
	 */
	public static function compute( array $in ): array {
		$badges = array();

		if ( $in['has_discount'] ) {
			$badges[] = array( 'type' => self::DISCOUNT );
		}

		if ( $in['has_bookable'] && null !== $in['next_start'] ) {
			if ( self::days_between( $in['today'], $in['next_start'] ) <= $in['last_minute_days'] ) {
				$badges[] = array( 'type' => self::LAST_MINUTE );
			}

			$seats = $in['next_seats_left'];
			if ( null !== $seats && $seats > 0 && $seats <= $in['few_seats_threshold'] ) {
				$badges[] = array(
					'type'  => self::FEW_SEATS,
					'seats' => $seats,
				);
			}
		}

		if ( $in['special_offer'] ) {
			$badges[] = array( 'type' => self::SPECIAL_OFFER );
		}

		if ( ! $in['has_bookable'] ) {
			$badges[] = array( 'type' => self::SOLD_OUT );
		}

		return $badges;
	}

	/**
	 * Whole days from $from to $to (Y-m-d); negative when $to is earlier.
	 */
	public static function days_between( string $from, string $to ): int {
		$a = new DateTimeImmutable( $from );
		$b = new DateTimeImmutable( $to );

		return (int) $a->diff( $b )->format( '%r%a' );
	}
}
