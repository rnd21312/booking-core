<?php
/**
 * Plugin settings stored in one option (`stz_settings`). Edited from Suntourz → Settings (Milestone 6).
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Settings;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'stz_settings';

	/** Money is stored in integer minor units; 100 minor = 1 major (e.g. satang → baht). */
	public const MINOR_UNIT = 100;

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'company_name'        => 'Suntourz',
			// Placeholder contact details; edit them in Suntourz → Settings.
			'phone'               => '+66 00 000 0000',
			'whatsapp'            => '66000000000',
			'line_id'             => '',
			'telegram'            => '',
			'support_email'       => 'support@example.com',
			'reviews_auto_approve' => false,
			'instagram_url'       => '',
			'facebook_url'        => '',
			'currency_code'       => 'THB',
			'currency_symbol'     => '฿',
			'currency_position'   => 'before', // before|after.
			'currency_decimals'   => 0,
			'thousand_separator'  => ',',
			'decimal_separator'   => '.',
			'last_minute_days'    => 14,
			'few_seats_threshold' => 4,
			'pending_holds_seats' => true,
			'response_time_text'  => 'within 24 hours',
			'booking_terms'       => '',
			'turnstile_site_key'  => '',
			'turnstile_secret'    => '',
			'home_content'        => array(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$saved       = get_option( self::OPTION, array() );
			$this->cache = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}

		return $this->cache;
	}

	public function get( string $key, mixed $fallback = null ): mixed {
		return $this->all()[ $key ] ?? $fallback;
	}

	/**
	 * Saves a (validated) subset of settings. Unknown keys are dropped.
	 *
	 * @param array<string, mixed> $values New values.
	 */
	public function update( array $values ): void {
		// Site name and description are WordPress' own options (browser title, search results, feeds).
		if ( array_key_exists( 'site_name', $values ) && '' !== trim( (string) $values['site_name'] ) ) {
			update_option( 'blogname', (string) $values['site_name'] );
		}
		if ( array_key_exists( 'site_description', $values ) ) {
			update_option( 'blogdescription', (string) $values['site_description'] );
		}

		$clean = array_intersect_key( $values, self::defaults() );

		update_option( self::OPTION, array_merge( $this->all(), $clean ), false );
		$this->cache = null;
	}

	/**
	 * Validates and normalizes a settings payload from the admin screen. Unknown keys are dropped;
	 * secrets are only replaced when a new non-empty value is sent.
	 *
	 * @param array<string, mixed>  $input  Raw payload.
	 * @param array<string, string> $errors Field errors (by reference).
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input, array &$errors ): array {
		$out  = array();
		foreach ( array( 'site_name' => 120, 'site_description' => 300 ) as $key => $max ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = mb_substr( sanitize_text_field( (string) $input[ $key ] ), 0, $max );
			}
		}
		$text = array( 'company_name', 'phone', 'line_id', 'telegram', 'response_time_text', 'currency_symbol', 'thousand_separator', 'decimal_separator', 'turnstile_site_key' );

		foreach ( $text as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = mb_substr( sanitize_text_field( (string) $input[ $key ] ), 0, 120 );
			}
		}

		if ( array_key_exists( 'whatsapp', $input ) ) {
			$out['whatsapp'] = preg_replace( '/\D+/', '', (string) $input['whatsapp'] ) ?? '';
		}
		if ( array_key_exists( 'support_email', $input ) ) {
			$email = sanitize_email( (string) $input['support_email'] );
			if ( '' !== (string) $input['support_email'] && ! is_email( $email ) ) {
				$errors['support_email'] = __( 'Enter a valid email address.', 'suntourz' );
			}
			$out['support_email'] = $email;
		}
		foreach ( array( 'instagram_url', 'facebook_url' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = esc_url_raw( (string) $input[ $key ] );
			}
		}

		if ( array_key_exists( 'currency_code', $input ) ) {
			$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $input['currency_code'] ) ?? '' );
			if ( strlen( $code ) < 2 || strlen( $code ) > 8 ) {
				$errors['currency_code'] = __( 'Enter a currency code such as THB.', 'suntourz' );
			}
			$out['currency_code'] = $code;
		}
		if ( array_key_exists( 'currency_position', $input ) ) {
			$out['currency_position'] = 'after' === $input['currency_position'] ? 'after' : 'before';
		}
		if ( array_key_exists( 'currency_decimals', $input ) ) {
			$out['currency_decimals'] = max( 0, min( 2, (int) $input['currency_decimals'] ) );
		}
		if ( array_key_exists( 'last_minute_days', $input ) ) {
			$out['last_minute_days'] = max( 1, min( 365, (int) $input['last_minute_days'] ) );
		}
		if ( array_key_exists( 'few_seats_threshold', $input ) ) {
			$out['few_seats_threshold'] = max( 1, min( 50, (int) $input['few_seats_threshold'] ) );
		}
		foreach ( array( 'pending_holds_seats', 'reviews_auto_approve' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = (bool) $input[ $key ];
			}
		}
		if ( array_key_exists( 'booking_terms', $input ) ) {
			$out['booking_terms'] = mb_substr( sanitize_textarea_field( (string) $input['booking_terms'] ), 0, 8000 );
		}
		if ( ! empty( $input['turnstile_secret'] ) ) {
			$out['turnstile_secret'] = sanitize_text_field( (string) $input['turnstile_secret'] );
		}
		if ( array_key_exists( 'home_content', $input ) ) {
			$out['home_content'] = is_array( $input['home_content'] ) ? self::clean_tree( $input['home_content'], 0 ) : array();
		}

		return $out;
	}

	/**
	 * Recursively sanitizes the editable content tree (strings only, depth/size bounded).
	 *
	 * @param array<mixed> $tree Tree.
	 * @return array<mixed>
	 */
	private static function clean_tree( array $tree, int $depth ): array {
		if ( $depth > 6 ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $tree, 0, 60, true ) as $key => $value ) {
			$key = is_int( $key ) ? $key : sanitize_key( (string) $key );
			if ( is_array( $value ) ) {
				$out[ $key ] = self::clean_tree( $value, $depth + 1 );
			} elseif ( is_scalar( $value ) ) {
				$out[ $key ] = is_string( $value ) ? mb_substr( sanitize_textarea_field( $value ), 0, 4000 ) : $value;
			}
		}

		return $out;
	}

	/**
	 * Settings for the admin form (secrets are never sent back).
	 *
	 * @return array<string, mixed>
	 */
	public function for_admin(): array {
		$all                        = $this->all();
		$all['site_name']            = (string) get_option( 'blogname', '' );
		$all['site_description']     = (string) get_option( 'blogdescription', '' );
		$all['turnstile_secret_set'] = '' !== (string) ( $all['turnstile_secret'] ?? '' );
		unset( $all['turnstile_secret'] );

		return $all;
	}

	/**
	 * Currency display config for the front end (money itself is always minor units).
	 *
	 * @return array{code: string, symbol: string, position: string, decimals: int, thousand_separator: string, decimal_separator: string, minor_unit: int}
	 */
	public function currency(): array {
		return array(
			'code'               => (string) $this->get( 'currency_code', 'THB' ),
			'symbol'             => (string) $this->get( 'currency_symbol', '฿' ),
			'position'           => 'after' === $this->get( 'currency_position' ) ? 'after' : 'before',
			'decimals'           => max( 0, min( 2, (int) $this->get( 'currency_decimals', 0 ) ) ),
			'thousand_separator' => (string) $this->get( 'thousand_separator', ',' ),
			'decimal_separator'  => (string) $this->get( 'decimal_separator', '.' ),
			'minor_unit'         => self::MINOR_UNIT,
		);
	}

	public function pending_holds_seats(): bool {
		return (bool) $this->get( 'pending_holds_seats', true );
	}
}
