<?php
/**
 * Departure status values.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Domain;

defined( 'ABSPATH' ) || exit;

final class DepartureStatus {

	public const DRAFT     = 'draft';
	public const OPEN      = 'open';
	public const CLOSED    = 'closed';
	public const CANCELLED = 'cancelled';

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::DRAFT, self::OPEN, self::CLOSED, self::CANCELLED );
	}

	/**
	 * Statuses visitors may see (draft and cancelled stay private).
	 *
	 * @return string[]
	 */
	public static function public(): array {
		return array( self::OPEN, self::CLOSED );
	}

	public static function is_valid( string $status ): bool {
		return in_array( $status, self::all(), true );
	}
}
