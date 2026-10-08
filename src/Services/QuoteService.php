<?php
/**
 * Server-side quote: validates departure/plan/pax/extras and prices them.
 * The booking endpoint (Milestone 5) calls the same method before storing anything.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Services;

use Suntourz\Core\Domain\Pricing;
use Suntourz\Core\Domain\Seats;
use Suntourz\Core\PostTypes\TourMeta;
use Suntourz\Core\PostTypes\TourPostType;
use Suntourz\Core\Repository\DepartureRepository;
use Suntourz\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class QuoteService {

	public function __construct(
		private DepartureRepository $departures,
		private Settings $settings
	) {}

	/**
	 * @param array{departure_id: int, plan_id: string, pax?: ?int, extras?: array<int, array{id: string, qty: int}>} $input Request.
	 * @return array<string, mixed> { ok, errors[{field,code,message}], total, pax, lines[], currency, seats_left }
	 */
	public function quote( array $input ): array {
		$departure = $this->departures->find( (int) ( $input['departure_id'] ?? 0 ) );
		$tour      = $departure ? get_post( $departure['tour_id'] ) : null;

		if ( null === $departure || ! $tour || TourPostType::POST_TYPE !== $tour->post_type || 'publish' !== $tour->post_status ) {
			return $this->failure( array( array( 'field' => 'departure_id', 'code' => 'departure_not_found' ) ) );
		}

		$meta   = TourMeta::get( $departure['tour_id'], $this->settings );
		$plan   = Pricing::find_plan( $meta['pricing']['plans'], (string) ( $input['plan_id'] ?? '' ) );
		$errors = array();

		if ( null === $plan ) {
			return $this->failure( array( array( 'field' => 'plan_id', 'code' => 'plan_not_found' ) ), $departure['seats_left'] );
		}

		$priced = Pricing::quote(
			$plan,
			$departure['price_overrides'][ $plan['id'] ] ?? null,
			isset( $input['pax'] ) ? (int) $input['pax'] : null,
			$meta['extras'],
			(array) ( $input['extras'] ?? array() )
		);
		$errors = $priced['errors'];

		if ( ! Seats::is_bookable( $departure['status'], $departure['start_date'], $this->departures->today(), $departure['seats_left'], $priced['pax'] ) ) {
			$code     = ( $departure['seats_left'] < $priced['pax'] && 'open' === $departure['status'] && $departure['start_date'] > $this->departures->today() )
				? 'not_enough_seats'
				: 'departure_unavailable';
			$errors[] = array(
				'field' => 'departure_id',
				'code'  => $code,
			);
		}

		return array(
			'ok'         => array() === $errors,
			'errors'     => $this->with_messages( $errors ),
			'total'      => $priced['total'],
			'pax'        => $priced['pax'],
			'lines'      => $this->with_labels( $priced['lines'] ),
			'currency'   => $this->settings->currency(),
			'seats_left' => $departure['seats_left'],
		);
	}

	/**
	 * @param array<int, array{field: string, code: string}> $errors Errors.
	 * @return array<string, mixed>
	 */
	private function failure( array $errors, ?int $seats_left = null ): array {
		return array(
			'ok'         => false,
			'errors'     => $this->with_messages( $errors ),
			'total'      => 0,
			'pax'        => 0,
			'lines'      => array(),
			'currency'   => $this->settings->currency(),
			'seats_left' => $seats_left,
		);
	}

	/**
	 * @param array<int, array{field: string, code: string}> $errors Errors.
	 * @return array<int, array{field: string, code: string, message: string}>
	 */
	private function with_messages( array $errors ): array {
		$messages = array(
			'departure_not_found'   => __( 'This departure no longer exists.', 'suntourz' ),
			'departure_unavailable' => __( 'This departure is not open for booking.', 'suntourz' ),
			'not_enough_seats'      => __( 'There are not enough seats left on this departure.', 'suntourz' ),
			'plan_not_found'        => __( 'Please choose a valid pricing plan.', 'suntourz' ),
			'pax_invalid'           => __( 'The number of travellers is not valid for this plan.', 'suntourz' ),
			'extra_unknown'         => __( 'One of the selected extras is not available.', 'suntourz' ),
			'extra_qty_invalid'     => __( 'Please enter a valid quantity for the extra.', 'suntourz' ),
			'extra_qty_exceeded'    => __( 'The quantity is above the maximum allowed for this extra.', 'suntourz' ),
		);

		return array_map(
			static fn ( array $error ): array => $error + array( 'message' => $messages[ $error['code'] ] ?? $error['code'] ),
			$errors
		);
	}

	/**
	 * Fills the labels the pure pricing class leaves empty.
	 *
	 * @param array<int, array<string, mixed>> $lines Lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function with_labels( array $lines ): array {
		return array_map(
			static function ( array $line ): array {
				if ( 'extra_person' === $line['type'] && null === $line['label'] ) {
					$line['label'] = __( 'Additional travellers', 'suntourz' );
				}

				return $line;
			},
			$lines
		);
	}
}
