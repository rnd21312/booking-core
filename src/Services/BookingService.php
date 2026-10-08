<?php
/**
 * Creates booking requests: validates the guest, recomputes the price on the server,
 * re-checks seats under a database lock and stores the booking (the team sees it in the admin; no e-mail is sent).
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Services;

use Suntourz\Core\Domain\BookingCode;
use Suntourz\Core\Domain\Seats;
use Suntourz\Core\PostTypes\TourMeta;
use Suntourz\Core\Repository\BookingRepository;
use Suntourz\Core\Repository\DepartureRepository;
use Suntourz\Core\Settings\Settings;
use Suntourz\Core\Support\RateLimiter;
use Suntourz\Core\Support\Turnstile;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class BookingService {

	public const CHANNELS = array( 'phone', 'whatsapp', 'line', 'email' );

	private const RATE_LIMIT  = 5;
	private const RATE_WINDOW = 600;

	public function __construct(
		private Settings $settings,
		private DepartureRepository $departures,
		private BookingRepository $bookings,
		private QuoteService $quotes,
		private Turnstile $turnstile
	) {}

	/**
	 * @param array<string, mixed> $input Request body.
	 * @return array{code: string, total: int, pax: int}|WP_Error
	 */
	public function create( array $input ): array|WP_Error {
		// Honeypot: bots fill the hidden field. Pretend success, store nothing.
		if ( '' !== trim( (string) ( $input['website'] ?? '' ) ) ) {
			return array(
				'code'  => BookingCode::generate(),
				'total' => 0,
				'pax'   => 0,
			);
		}

		$errors = array();
		$guest  = $this->clean_guest( $input, $errors );

		$quote = $this->quotes->quote(
			array(
				'departure_id' => (int) ( $input['departure_id'] ?? 0 ),
				'plan_id'      => sanitize_key( (string) ( $input['plan_id'] ?? '' ) ),
				'pax'          => isset( $input['pax'] ) ? (int) $input['pax'] : null,
				'extras'       => $this->clean_extras( $input['extras'] ?? array() ),
			)
		);
		foreach ( $quote['errors'] as $error ) {
			$errors[ $error['field'] ] = $error['message'];
		}

		if ( array() !== $errors ) {
			return new WP_Error( 'stz_invalid_booking', __( 'Some fields need your attention.', 'suntourz' ), array( 'status' => 422, 'errors' => $errors ) );
		}

		if ( ! $this->turnstile->verify( (string) ( $input['turnstile_token'] ?? '' ) ) ) {
			return new WP_Error( 'stz_captcha', __( 'Please confirm you are not a robot and try again.', 'suntourz' ), array( 'status' => 403 ) );
		}

		if ( ! RateLimiter::allow( 'booking', self::RATE_LIMIT, self::RATE_WINDOW ) ) {
			return new WP_Error( 'stz_rate_limited', __( 'Too many requests. Please try again in a few minutes.', 'suntourz' ), array( 'status' => 429 ) );
		}

		$departure_id = (int) $input['departure_id'];
		$departure    = $this->departures->find( $departure_id );
		$tour_id      = (int) ( $departure['tour_id'] ?? 0 );
		$meta         = TourMeta::get( $tour_id, $this->settings );
		$pax          = (int) $quote['pax'];

		$row = array(
			'tour_id'         => $tour_id,
			'departure_id'    => $departure_id,
			'plan_id'         => sanitize_key( (string) $input['plan_id'] ),
			'pax'             => $pax,
			'extras'          => wp_json_encode( array_values( array_filter( $quote['lines'], static fn ( array $l ): bool => 'extra' === $l['type'] ) ) ),
			'total_amount'    => (int) $quote['total'],
			'currency'        => $meta['pricing']['currency'],
			'customer_name'   => $guest['name'],
			'customer_email'  => $guest['email'],
			'customer_phone'  => $guest['phone'],
			'contact_channel' => $guest['channel'],
			'contact_handle'  => $guest['handle'],
			'message'         => $guest['message'],
			'admin_note'      => '',
			'locale'          => get_locale(),
			'ip_hash'         => RateLimiter::ip_hash(),
		);

		// The decisive seat check happens under the departure row lock.
		$result = $this->bookings->create_locked(
			$departure_id,
			$row,
			function () use ( $departure_id, $pax ): ?string {
				$fresh = $this->departures->find( $departure_id );
				if ( null === $fresh || ! Seats::is_bookable( $fresh['status'], $fresh['start_date'], $this->departures->today(), $fresh['seats_left'], $pax ) ) {
					return 'not_enough_seats';
				}

				return null;
			}
		);

		if ( is_string( $result ) ) {
			$message = 'not_enough_seats' === $result
				? __( 'Sorry, someone just took the last seats on this departure. Please choose another date.', 'suntourz' )
				: __( 'Your request could not be saved. Please try again.', 'suntourz' );

			return new WP_Error(
				'stz_booking_failed',
				$message,
				array(
					'status' => 'not_enough_seats' === $result ? 409 : 500,
					'errors' => 'not_enough_seats' === $result ? array( 'departure_id' => $message ) : array(),
				)
			);
		}

		return array(
			'code'  => $result['code'],
			'total' => (int) $quote['total'],
			'pax'   => $pax,
		);
	}

	/**
	 * Minimal summary for the success page; only returned when the e-mail matches.
	 *
	 * @return array<string, mixed>|null
	 */
	public function summary( string $code, string $email ): ?array {
		$booking = BookingCode::is_valid( $code ) ? $this->bookings->find_by_code( $code ) : null;

		if ( null === $booking || 0 !== strcasecmp( (string) $booking['customer_email'], $email ) ) {
			return null;
		}

		$tour      = get_post( (int) $booking['tour_id'] );
		$departure = $this->departures->find( (int) $booking['departure_id'] );
		$meta      = TourMeta::get( (int) $booking['tour_id'], $this->settings );
		$plan      = null;
		foreach ( $meta['pricing']['plans'] as $candidate ) {
			if ( $candidate['id'] === $booking['plan_id'] ) {
				$plan = $candidate;
			}
		}

		return array(
			'code'          => $booking['code'],
			'status'        => $booking['status'],
			'tour'          => array(
				'title' => $tour ? html_entity_decode( get_the_title( $tour ), ENT_QUOTES, 'UTF-8' ) : '',
				'url'   => $tour ? (string) get_permalink( $tour ) : '',
			),
			'start_date'    => $departure['start_date'] ?? '',
			'end_date'      => $departure['end_date'] ?? '',
			'plan'          => $plan['label'] ?? '',
			'pax'           => (int) $booking['pax'],
			'total'         => (int) $booking['total_amount'],
			'currency'      => $this->settings->currency(),
			'name'          => $booking['customer_name'],
			'response_time' => (string) $this->settings->get( 'response_time_text', 'within 24 hours' ),
		);
	}

	/**
	 * @param array<string, mixed>  $input  Request.
	 * @param array<string, string> $errors Errors (by reference).
	 * @return array{name: string, email: string, phone: string, channel: string, handle: string, message: string}
	 */
	private function clean_guest( array $input, array &$errors ): array {
		$name    = mb_substr( sanitize_text_field( (string) ( $input['name'] ?? '' ) ), 0, 120 );
		$email   = sanitize_email( (string) ( $input['email'] ?? '' ) );
		$phone   = mb_substr( sanitize_text_field( (string) ( $input['phone'] ?? '' ) ), 0, 40 );
		$channel = (string) ( $input['contact_channel'] ?? 'phone' );
		$handle  = mb_substr( sanitize_text_field( (string) ( $input['contact_handle'] ?? '' ) ), 0, 190 );
		$message = mb_substr( sanitize_textarea_field( (string) ( $input['message'] ?? '' ) ), 0, 2000 );

		if ( mb_strlen( $name ) < 2 ) {
			$errors['name'] = __( 'Please enter your full name.', 'suntourz' );
		}
		if ( ! is_email( $email ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'suntourz' );
		}
		if ( ! preg_match( '/^\+?[\d\s().-]{6,40}$/', $phone ) ) {
			$errors['phone'] = __( 'Please enter a valid phone number with country code.', 'suntourz' );
		}
		if ( ! in_array( $channel, self::CHANNELS, true ) ) {
			$errors['contact_channel'] = __( 'Please choose how we should contact you.', 'suntourz' );
			$channel                   = 'phone';
		}
		if ( in_array( $channel, array( 'whatsapp', 'line' ), true ) && '' === $handle ) {
			$errors['contact_handle'] = 'whatsapp' === $channel
				? __( 'Please enter your WhatsApp number.', 'suntourz' )
				: __( 'Please enter your LINE ID.', 'suntourz' );
		}
		if ( empty( $input['terms'] ) ) {
			$errors['terms'] = __( 'Please accept the booking terms to continue.', 'suntourz' );
		}

		return array(
			'name'    => $name,
			'email'   => $email,
			'phone'   => $phone,
			'channel' => $channel,
			'handle'  => $handle,
			'message' => $message,
		);
	}

	/**
	 * @param mixed $raw Requested extras.
	 * @return array<int, array{id: string, qty: int}>
	 */
	private function clean_extras( mixed $raw ): array {
		$out = array();
		foreach ( is_array( $raw ) ? array_slice( $raw, 0, 30 ) : array() as $extra ) {
			if ( is_array( $extra ) ) {
				$out[] = array(
					'id'  => sanitize_key( (string) ( $extra['id'] ?? '' ) ),
					'qty' => (int) ( $extra['qty'] ?? 1 ),
				);
			}
		}

		return $out;
	}
}
