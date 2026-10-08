<?php
/**
 * Booking status values and the one place where allowed transitions are defined.
 *
 * Pure domain class (no WordPress calls) so it can be unit-tested directly.
 * Every status change in the plugin must be validated here and logged.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Domain;

defined( 'ABSPATH' ) || exit;

final class BookingStatus {

	public const NEW       = 'new';
	public const CONTACTED = 'contacted';
	public const CONFIRMED = 'confirmed';
	public const PAID      = 'paid';
	public const COMPLETED = 'completed';
	public const CANCELLED = 'cancelled';
	public const NO_SHOW   = 'no_show';

	/**
	 * Allowed transitions: from => list of to.
	 * Main flow: new → contacted → confirmed → paid → completed. Side exits: cancelled, no_show.
	 */
	private const TRANSITIONS = array(
		self::NEW       => array( self::CONTACTED, self::CONFIRMED, self::CANCELLED ),
		self::CONTACTED => array( self::CONFIRMED, self::CANCELLED ),
		self::CONFIRMED => array( self::PAID, self::CANCELLED, self::NO_SHOW ),
		self::PAID      => array( self::COMPLETED, self::CANCELLED, self::NO_SHOW ),
		self::COMPLETED => array(),
		self::CANCELLED => array(),
		self::NO_SHOW   => array(),
	);

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array_keys( self::TRANSITIONS );
	}

	public static function is_valid( string $status ): bool {
		return isset( self::TRANSITIONS[ $status ] );
	}

	/**
	 * @return string[] Statuses reachable from $from (empty for unknown / terminal statuses).
	 */
	public static function allowed_from( string $from ): array {
		return self::TRANSITIONS[ $from ] ?? array();
	}

	public static function can_transition( string $from, string $to ): bool {
		return in_array( $to, self::allowed_from( $from ), true );
	}

	/**
	 * Statuses whose bookings occupy seats on a departure.
	 *
	 * @param bool $pending_holds_seats Whether new/contacted (not yet confirmed) bookings hold seats.
	 * @return string[]
	 */
	public static function seat_holding( bool $pending_holds_seats = true ): array {
		$holding = array( self::CONFIRMED, self::PAID, self::COMPLETED );

		if ( $pending_holds_seats ) {
			array_unshift( $holding, self::NEW, self::CONTACTED );
		}

		return $holding;
	}

	public static function holds_seat( string $status, bool $pending_holds_seats = true ): bool {
		return in_array( $status, self::seat_holding( $pending_holds_seats ), true );
	}
}
