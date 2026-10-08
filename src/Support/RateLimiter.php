<?php
/**
 * Per-IP rate limiting with transients (good enough for public form endpoints).
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Support;

defined( 'ABSPATH' ) || exit;

final class RateLimiter {

	/**
	 * Registers one hit and tells whether it is still within the limit.
	 *
	 * @param string $bucket Logical name, e.g. "trip_request".
	 * @param int    $limit  Max hits per window.
	 * @param int    $window Window in seconds.
	 */
	public static function allow( string $bucket, int $limit, int $window ): bool {
		$key   = 'stz_rl_' . md5( $bucket . '|' . self::ip() );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		// The window starts with the first hit; later hits must not extend it.
		if ( 0 === $count ) {
			set_transient( $key, 1, $window );
		} else {
			$timeout = (int) get_option( '_transient_timeout_' . $key );
			$left    = $timeout > 0 ? max( 1, $timeout - time() ) : $window;
			set_transient( $key, $count + 1, $left );
		}

		return true;
	}

	/**
	 * Client IP. Only REMOTE_ADDR is trusted (forwarding headers are spoofable).
	 */
	public static function ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Salted hash of the IP — stored instead of the address itself.
	 */
	public static function ip_hash(): string {
		return hash_hmac( 'sha256', self::ip(), wp_salt( 'auth' ) );
	}
}
