<?php
/**
 * Optional Cloudflare Turnstile verification (active only when a secret key is saved in Settings).
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Support;

use Suntourz\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Turnstile {

	public function __construct( private Settings $settings ) {}

	public function enabled(): bool {
		return '' !== (string) $this->settings->get( 'turnstile_secret', '' ) && '' !== (string) $this->settings->get( 'turnstile_site_key', '' );
	}

	public function site_key(): string {
		return $this->enabled() ? (string) $this->settings->get( 'turnstile_site_key', '' ) : '';
	}

	/**
	 * True when Turnstile is off or the token is valid.
	 */
	public function verify( string $token ): bool {
		if ( ! $this->enabled() ) {
			return true;
		}
		if ( '' === $token ) {
			return false;
		}

		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => (string) $this->settings->get( 'turnstile_secret', '' ),
					'response' => $token,
					'remoteip' => RateLimiter::ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) && ! empty( $body['success'] );
	}
}
