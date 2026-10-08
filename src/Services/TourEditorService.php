<?php
/**
 * Load/save for the wp-admin tour editor panel: tour meta + departures in one validated operation.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Services;

use DateTimeImmutable;
use Suntourz\Core\Domain\DepartureStatus;
use Suntourz\Core\Domain\Pricing;
use Suntourz\Core\PostTypes\TourMeta;
use Suntourz\Core\PostTypes\TourPostType;
use Suntourz\Core\Repository\DepartureRepository;
use Suntourz\Core\Settings\Settings;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class TourEditorService {

	public function __construct(
		private DepartureRepository $departures,
		private Settings $settings
	) {}

	/**
	 * Everything the editor needs for one tour.
	 *
	 * @return array<string, mixed>|null Null when the post is not a tour.
	 */
	public function load( int $tour_id ): ?array {
		$post = get_post( $tour_id );
		if ( ! $post instanceof WP_Post || TourPostType::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$meta = TourMeta::get( $tour_id, $this->settings );

		$gallery = array();
		foreach ( $meta['gallery'] as $attachment_id ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'medium' );
			if ( $url ) {
				$gallery[] = array(
					'id'  => $attachment_id,
					'url' => $url,
				);
			}
		}

		$departures = array_map(
			static fn ( array $d ): array => array(
				'id'              => $d['id'],
				'start_date'      => $d['start_date'],
				'end_date'        => $d['end_date'],
				'capacity'        => $d['capacity'],
				'status'          => $d['status'],
				'price_overrides' => (object) $d['price_overrides'],
				'note'            => $d['note'],
				'booked_pax'      => $d['booked_pax'],
				'seats_left'      => $d['seats_left'],
				'bookings_count'  => $d['bookings_count'],
			),
			$this->departures->all_for_tour( $tour_id )
		);

		return array(
			'tour'       => array(
				'id'        => $tour_id,
				'title'     => get_the_title( $post ),
				'status'    => $post->post_status,
				'permalink' => (string) get_permalink( $tour_id ),
			),
			'details'    => array(
				'duration_days' => $meta['duration_days'],
				'highlights'    => $meta['highlights'],
				'includes'      => $meta['includes'],
				'excludes'      => $meta['excludes'],
				'meeting_point' => $meta['meeting_point'],
				'itinerary'     => $meta['itinerary'],
				'gallery'       => $gallery,
			),
			'pricing'    => $meta['pricing'],
			'extras'     => $meta['extras'],
			'flags'      => array(
				'featured'      => $meta['featured'],
				'special_offer' => $meta['special_offer'],
				'sort_order'    => $meta['sort_order'],
			),
			'departures' => $departures,
		);
	}

	/**
	 * Validates and stores the editor payload.
	 *
	 * @param array<string, mixed> $input Payload (details, pricing, extras, flags, departures).
	 * @return array<string, mixed>|WP_Error The reloaded editor data (+ `warnings`), or a 422 error with per-field messages.
	 */
	public function save( int $tour_id, array $input ): array|WP_Error {
		if ( null === $this->load( $tour_id ) ) {
			return new WP_Error( 'stz_tour_not_found', __( 'Tour not found.', 'suntourz' ), array( 'status' => 404 ) );
		}

		$existing = array();
		foreach ( $this->departures->all_for_tour( $tour_id ) as $departure ) {
			$existing[ $departure['id'] ] = $departure;
		}

		$errors = array();
		$clean  = $this->validate( $input, $existing, $errors );

		if ( array() !== $errors ) {
			return new WP_Error(
				'stz_invalid_tour',
				__( 'Some fields need your attention.', 'suntourz' ),
				array(
					'status' => 422,
					'errors' => $errors,
				)
			);
		}

		$this->persist_meta( $tour_id, $clean );
		$warnings = $this->persist_departures( $tour_id, $clean['departures'], $existing );

		$data             = $this->load( $tour_id ) ?? array();
		$data['warnings'] = $warnings;

		return $data;
	}

	/**
	 * @param array<string, mixed>              $input    Raw payload.
	 * @param array<int, array<string, mixed>>  $existing Current departures by id.
	 * @param array<string, string>             $errors   Collected errors keyed by field path (by reference).
	 * @return array<string, mixed> Canonical values.
	 */
	private function validate( array $input, array $existing, array &$errors ): array {
		$details = (array) ( $input['details'] ?? array() );
		$pricing = (array) ( $input['pricing'] ?? array() );
		$flags   = (array) ( $input['flags'] ?? array() );

		$duration = (int) ( $details['duration_days'] ?? 0 );
		if ( $duration < 1 || $duration > 365 ) {
			$errors['details.duration_days'] = __( 'Duration must be between 1 and 365 days.', 'suntourz' );
		}

		$gallery_ids = array();
		foreach ( (array) ( $details['gallery'] ?? array() ) as $item ) {
			$gallery_ids[] = is_array( $item ) ? (int) ( $item['id'] ?? 0 ) : (int) $item;
		}

		$plans = $this->validate_plans( (array) ( $pricing['plans'] ?? array() ), $errors );
		$extras = $this->validate_extras( (array) ( $input['extras'] ?? array() ), $errors );

		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $pricing['currency'] ?? '' ) ) ?? '' );
		$plan_ids = array_column( $plans, 'id' );

		$departures = $this->validate_departures( (array) ( $input['departures'] ?? array() ), $existing, $plan_ids, $errors );

		return array(
			'details'    => array(
				'duration_days' => max( 1, $duration ),
				'highlights'    => TourMeta::sanitize_string_list( $details['highlights'] ?? array() ),
				'includes'      => TourMeta::sanitize_string_list( $details['includes'] ?? array() ),
				'excludes'      => TourMeta::sanitize_string_list( $details['excludes'] ?? array() ),
				'meeting_point' => TourMeta::sanitize_meeting_point( $details['meeting_point'] ?? array() ),
				'itinerary'     => TourMeta::sanitize_itinerary( $details['itinerary'] ?? array() ),
				'gallery'       => TourMeta::sanitize_gallery( $gallery_ids ),
			),
			'pricing'    => array(
				'currency' => '' !== $currency ? substr( $currency, 0, 8 ) : (string) $this->settings->get( 'currency_code', 'THB' ),
				'plans'    => $plans,
			),
			'extras'     => $extras,
			'flags'      => array(
				'featured'      => ! empty( $flags['featured'] ),
				'special_offer' => ! empty( $flags['special_offer'] ),
				'sort_order'    => (int) ( $flags['sort_order'] ?? 0 ),
			),
			'departures' => $departures,
		);
	}

	/**
	 * @param array<int, mixed>     $raw    Raw plans.
	 * @param array<string, string> $errors Errors (by reference).
	 * @return array<int, array<string, mixed>>
	 */
	private function validate_plans( array $raw, array &$errors ): array {
		$plans = array();
		$used  = array();

		foreach ( array_values( $raw ) as $i => $plan ) {
			$plan = is_array( $plan ) ? $plan : array();
			$key  = "pricing.plans.{$i}";

			$label = sanitize_text_field( (string) ( $plan['label'] ?? '' ) );
			if ( '' === $label ) {
				$errors[ "{$key}.label" ] = __( 'Give this plan a name.', 'suntourz' );
			}

			$pax = (int) ( $plan['pax'] ?? 0 );
			if ( $pax < 1 ) {
				$errors[ "{$key}.pax" ] = __( 'At least 1 traveller.', 'suntourz' );
			}

			$price = $plan['price'] ?? null;
			if ( ! is_numeric( $price ) || (int) $price < 0 ) {
				$errors[ "{$key}.price" ] = __( 'Enter a price.', 'suntourz' );
				$price                    = 0;
			}

			$sale = $plan['sale_price'] ?? null;
			$sale = ( null === $sale || '' === $sale ) ? null : $sale;
			if ( null !== $sale && ( ! is_numeric( $sale ) || (int) $sale < 0 || (int) $sale >= (int) $price ) ) {
				$errors[ "{$key}.sale_price" ] = __( 'The sale price must be lower than the regular price.', 'suntourz' );
			}

			$extra_person = $plan['extra_person_price'] ?? null;
			$has_extra    = is_numeric( $extra_person ) && (int) $extra_person > 0;
			if ( $has_extra && (int) ( $plan['max_pax'] ?? 0 ) < $pax ) {
				$errors[ "{$key}.max_pax" ] = __( 'The maximum group size cannot be below the plan size.', 'suntourz' );
			}

			$id = sanitize_key( (string) ( $plan['id'] ?? '' ) );
			if ( '' === $id ) {
				$id = sanitize_key( $label );
			}
			if ( '' === $id ) {
				$id = 'plan_' . ( $i + 1 );
			}
			$base = $id;
			for ( $n = 2; isset( $used[ $id ] ); $n++ ) {
				$id = $base . '_' . $n;
			}
			$used[ $id ] = true;

			$plans[] = array(
				'id'                 => $id,
				'label'              => $label,
				'pax'                => max( 1, $pax ),
				'price'              => (int) $price,
				'sale_price'         => $sale,
				'extra_person_price' => $has_extra ? (int) $extra_person : null,
				'max_pax'            => $has_extra ? (int) ( $plan['max_pax'] ?? $pax ) : null,
			);
		}

		return TourMeta::sanitize_pricing( array( 'plans' => $plans ) )['plans'];
	}

	/**
	 * @param array<int, mixed>     $raw    Raw extras.
	 * @param array<string, string> $errors Errors (by reference).
	 * @return array<int, array<string, mixed>>
	 */
	private function validate_extras( array $raw, array &$errors ): array {
		foreach ( array_values( $raw ) as $i => $extra ) {
			$extra = is_array( $extra ) ? $extra : array();
			if ( '' === sanitize_text_field( (string) ( $extra['label'] ?? '' ) ) ) {
				$errors[ "extras.{$i}.label" ] = __( 'Give this extra a name.', 'suntourz' );
			}
			if ( ! is_numeric( $extra['price'] ?? null ) || (int) $extra['price'] < 0 ) {
				$errors[ "extras.{$i}.price" ] = __( 'Enter a price.', 'suntourz' );
			}
		}

		return TourMeta::sanitize_extras( $raw );
	}

	/**
	 * @param array<int, mixed>                $raw      Raw departures.
	 * @param array<int, array<string, mixed>> $existing Current departures by id.
	 * @param string[]                         $plan_ids Valid plan ids (for price overrides).
	 * @param array<string, string>            $errors   Errors (by reference).
	 * @return array<int, array<string, mixed>>
	 */
	private function validate_departures( array $raw, array $existing, array $plan_ids, array &$errors ): array {
		$out = array();

		foreach ( array_values( $raw ) as $i => $row ) {
			$row = is_array( $row ) ? $row : array();
			$key = "departures.{$i}";
			$id  = (int) ( $row['id'] ?? 0 );

			if ( $id > 0 && ! isset( $existing[ $id ] ) ) {
				$errors[ "{$key}.start_date" ] = __( 'This departure no longer exists. Reload the page.', 'suntourz' );
				continue;
			}

			$start = $this->valid_date( (string) ( $row['start_date'] ?? '' ) );
			$end   = $this->valid_date( (string) ( $row['end_date'] ?? '' ) );
			if ( null === $start ) {
				$errors[ "{$key}.start_date" ] = __( 'Choose a start date.', 'suntourz' );
			}
			if ( null === $end ) {
				$errors[ "{$key}.end_date" ] = __( 'Choose an end date.', 'suntourz' );
			} elseif ( null !== $start && $end < $start ) {
				$errors[ "{$key}.end_date" ] = __( 'The end date cannot be before the start date.', 'suntourz' );
			}

			$capacity = $row['capacity'] ?? null;
			$booked   = $id > 0 ? (int) $existing[ $id ]['booked_pax'] : 0;
			if ( ! is_numeric( $capacity ) || (int) $capacity < 0 || (int) $capacity > 1000 ) {
				$errors[ "{$key}.capacity" ] = __( 'Enter a capacity (0–1000).', 'suntourz' );
			} elseif ( (int) $capacity < $booked ) {
				$errors[ "{$key}.capacity" ] = sprintf(
					/* translators: %d: number of seats already booked. */
					__( '%d seats are already booked — capacity cannot be lower.', 'suntourz' ),
					$booked
				);
			}

			$status = (string) ( $row['status'] ?? DepartureStatus::DRAFT );
			if ( ! DepartureStatus::is_valid( $status ) ) {
				$errors[ "{$key}.status" ] = __( 'Choose a valid status.', 'suntourz' );
			}

			$overrides = array();
			foreach ( (array) ( $row['price_overrides'] ?? array() ) as $plan_id => $override ) {
				$normalized = Pricing::normalize_override( $override );
				if ( array() !== $normalized && in_array( (string) $plan_id, $plan_ids, true ) ) {
					$overrides[ (string) $plan_id ] = $normalized;
				}
			}

			$out[] = array(
				'id'              => $id,
				'start_date'      => $start ?? '',
				'end_date'        => $end ?? '',
				'capacity'        => max( 0, (int) $capacity ),
				'status'          => DepartureStatus::is_valid( $status ) ? $status : DepartureStatus::DRAFT,
				'price_overrides' => $overrides,
				'note'            => sanitize_textarea_field( (string) ( $row['note'] ?? '' ) ),
			);
		}

		return $out;
	}

	private function valid_date( string $value ): ?string {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		return ( $date && $date->format( 'Y-m-d' ) === $value ) ? $value : null;
	}

	/**
	 * @param array<string, mixed> $clean Validated payload.
	 */
	private function persist_meta( int $tour_id, array $clean ): void {
		$details = $clean['details'];

		update_post_meta( $tour_id, TourMeta::DURATION, $details['duration_days'] );
		update_post_meta( $tour_id, TourMeta::GALLERY, $details['gallery'] );
		update_post_meta( $tour_id, TourMeta::HIGHLIGHTS, $details['highlights'] );
		update_post_meta( $tour_id, TourMeta::INCLUDES, $details['includes'] );
		update_post_meta( $tour_id, TourMeta::EXCLUDES, $details['excludes'] );
		update_post_meta( $tour_id, TourMeta::ITINERARY, $details['itinerary'] );
		update_post_meta( $tour_id, TourMeta::MEETING_POINT, $details['meeting_point'] );
		update_post_meta( $tour_id, TourMeta::PRICING, $clean['pricing'] );
		update_post_meta( $tour_id, TourMeta::EXTRAS, $clean['extras'] );
		update_post_meta( $tour_id, TourMeta::FEATURED, $clean['flags']['featured'] );
		update_post_meta( $tour_id, TourMeta::SPECIAL_OFFER, $clean['flags']['special_offer'] );
		update_post_meta( $tour_id, TourMeta::SORT_ORDER, $clean['flags']['sort_order'] );
	}

	/**
	 * Upserts departures; removed ones are deleted, or cancelled when they already have bookings.
	 *
	 * @param array<int, array<string, mixed>> $rows     Validated departures.
	 * @param array<int, array<string, mixed>> $existing Current departures by id.
	 * @return string[] Warnings for the UI.
	 */
	private function persist_departures( int $tour_id, array $rows, array $existing ): array {
		$kept = array();

		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			if ( $id > 0 ) {
				$this->departures->update( $id, $row );
				$kept[ $id ] = true;
			} else {
				$this->departures->insert( $tour_id, $row );
			}
		}

		$warnings = array();
		foreach ( $existing as $id => $departure ) {
			if ( isset( $kept[ $id ] ) ) {
				continue;
			}

			if ( $departure['bookings_count'] > 0 ) {
				$this->departures->set_status( $id, DepartureStatus::CANCELLED );
				$warnings[] = sprintf(
					/* translators: %s: departure start date. */
					__( 'The departure on %s has bookings, so it was cancelled instead of deleted.', 'suntourz' ),
					$departure['start_date']
				);
				continue;
			}

			$this->departures->delete( $id );
		}

		return $warnings;
	}
}
