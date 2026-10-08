<?php
/**
 * Human-friendly booking codes such as STZ-7K3Q9P (no look-alike characters).
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Domain;

defined( 'ABSPATH' ) || exit;

final class BookingCode {

	public const PREFIX   = 'STZ-';
	public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	public const LENGTH   = 6;

	public static function generate(): string {
		$max  = strlen( self::ALPHABET ) - 1;
		$code = '';
		for ( $i = 0; $i < self::LENGTH; $i++ ) {
			$code .= self::ALPHABET[ random_int( 0, $max ) ];
		}

		return self::PREFIX . $code;
	}

	public static function is_valid( string $code ): bool {
		return 1 === preg_match( '/^' . self::PREFIX . '[' . self::ALPHABET . ']{' . self::LENGTH . '}\z/', $code );
	}
}
