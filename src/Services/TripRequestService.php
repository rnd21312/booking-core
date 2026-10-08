<?php
/**
 * Validates and stores a "Plan My Trip" request, for the team to follow up in the admin (no e-mail is sent).
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Services;

use Suntourz\Core\PostTypes\TourPostType;
use Suntourz\Core\PostTypes\TripRequestPostType;
use Suntourz\Core\Settings\Settings;
use Suntourz\Core\Support\RateLimiter;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class TripRequestService {

	private const RATE_LIMIT  = 5;
	private const RATE_WINDOW = 600;

	public function __construct(
		private Settings $settings
	) {}

	/**
	 * @param array<string, mixed> $input Raw request body.
	 * @return array{reference: string}|WP_Error
	 */
	public function submit( array $input ): array|WP_Error {
		// Honeypot: a filled hidden field means a bot. Answer with a fake success.
		if ( '' !== trim( (string) ( $input['website'] ?? '' ) ) ) {
			return array( 'reference' => $this->new_reference() );
		}

		$errors = array();
		$data   = $this->clean( $input, $errors );

		if ( array() !== $errors ) {
			return new WP_Error( 'stz_invalid_trip_request', __( 'Some fields need your attention.', 'suntourz' ), array( 'status' => 422, 'errors' => $errors ) );
		}

		if ( ! RateLimiter::allow( 'trip_request', self::RATE_LIMIT, self::RATE_WINDOW ) ) {
			return new WP_Error( 'stz_rate_limited', __( 'Too many requests. Please try again in a few minutes.', 'suntourz' ), array( 'status' => 429 ) );
		}

		$reference = $this->new_reference();
		$post_id   = wp_insert_post(
			array(
				'post_type'   => TripRequestPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $reference . ' — ' . $data['name'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'stz_trip_request_failed', __( 'Your request could not be saved. Please try again.', 'suntourz' ), array( 'status' => 500 ) );
		}

		foreach ( array( 'name', 'email', 'phone', 'destination', 'style', 'season', 'duration', 'travelers', 'hotel', 'budget', 'notes' ) as $field ) {
			update_post_meta( $post_id, '_stz_tr_' . $field, $data[ $field ] );
		}
		update_post_meta( $post_id, '_stz_tr_interests', $data['interests'] );
		update_post_meta( $post_id, '_stz_tr_tour_ids', $data['tour_ids'] );
		update_post_meta( $post_id, '_stz_tr_status', 'new' );
		update_post_meta( $post_id, '_stz_tr_reference', $reference );
		update_post_meta( $post_id, '_stz_tr_locale', get_locale() );
		update_post_meta( $post_id, '_stz_tr_ip_hash', RateLimiter::ip_hash() );

		return array( 'reference' => $reference );
	}

	/**
	 * @param array<string, mixed>  $input  Raw input.
	 * @param array<string, string> $errors Errors (by reference).
	 * @return array<string, mixed>
	 */
	private function clean( array $input, array &$errors ): array {
		$text = static fn ( string $key, int $max ): string => mb_substr( sanitize_text_field( (string) ( $input[ $key ] ?? '' ) ), 0, $max );

		$name  = $text( 'name', 120 );
		$email = sanitize_email( (string) ( $input['email'] ?? '' ) );
		$phone = $text( 'phone', 40 );
		$notes = mb_substr( sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ), 0, 2000 );

		if ( mb_strlen( $name ) < 2 ) {
			$errors['name'] = __( 'Please enter your full name.', 'suntourz' );
		}
		// Requests are followed up from the admin, so a phone / WhatsApp number or an e-mail is needed.
		if ( '' !== $email && ! is_email( $email ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'suntourz' );
		}
		if ( '' !== $phone && ! preg_match( '/^[\d\s+().-]{6,40}$/', $phone ) ) {
			$errors['phone'] = __( 'Please enter a valid phone number.', 'suntourz' );
		}
		if ( '' === $phone && '' === $email ) {
			$errors['phone'] = __( 'Please leave a phone / WhatsApp number or an email so we can reach you.', 'suntourz' );
		}

		$interests = array();
		foreach ( array_slice( (array) ( $input['interests'] ?? array() ), 0, 12 ) as $interest ) {
			$value = mb_substr( sanitize_text_field( (string) $interest ), 0, 80 );
			if ( '' !== $value ) {
				$interests[] = $value;
			}
		}

		$tour_ids = array();
		foreach ( array_slice( (array) ( $input['tour_ids'] ?? array() ), 0, 20 ) as $tour_id ) {
			$tour_id = (int) $tour_id;
			if ( $tour_id > 0 && TourPostType::POST_TYPE === get_post_type( $tour_id ) ) {
				$tour_ids[] = $tour_id;
			}
		}

		return array(
			'name'        => $name,
			'email'       => $email,
			'phone'       => $phone,
			'destination' => $text( 'destination', 120 ),
			'style'       => $text( 'style', 120 ),
			'season'      => $text( 'season', 120 ),
			'duration'    => $text( 'duration', 80 ),
			'travelers'   => $text( 'travelers', 80 ),
			'hotel'       => $text( 'hotel', 120 ),
			'budget'      => $text( 'budget', 120 ),
			'notes'       => $notes,
			'interests'   => $interests,
			'tour_ids'    => array_values( array_unique( $tour_ids ) ),
		);
	}

	private function new_reference(): string {
		return 'PMT-' . strtoupper( wp_generate_password( 6, false, false ) );
	}
}
