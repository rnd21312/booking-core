<?php
/**
 * Tour post meta: registration (typed REST schemas), sanitizers and a normalized reader.
 *
 * Money is integer minor units (satang). The sanitizers are public so the admin REST
 * endpoint (Milestone 3) stores data through exactly the same rules.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\PostTypes;

use Suntourz\Core\Domain\Pricing;
use Suntourz\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class TourMeta {

	public const DURATION      = '_stz_duration_days';
	public const GALLERY       = '_stz_gallery';
	public const HIGHLIGHTS    = '_stz_highlights';
	public const INCLUDES      = '_stz_includes';
	public const EXCLUDES      = '_stz_excludes';
	public const ITINERARY     = '_stz_itinerary';
	public const MEETING_POINT = '_stz_meeting_point';
	public const PRICING       = '_stz_pricing';
	public const EXTRAS        = '_stz_extras';
	public const SPECIAL_OFFER = '_stz_special_offer';
	public const FEATURED      = '_stz_featured';
	public const SORT_ORDER    = '_stz_sort_order';
	public const DEMO          = '_stz_demo';

	public const ITINERARY_ITEM_TYPES = array( 'transfer', 'meal', 'activity', 'rest', 'free', 'stay' );

	private const MAX_LIST_ITEMS = 60;

	public function register(): void {
		$auth = static fn ( $allowed, $meta_key, $post_id ): bool => current_user_can( 'edit_post', (int) $post_id );

		$string_list = array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);

		$definitions = array(
			self::DURATION      => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => array( self::class, 'sanitize_duration' ),
			),
			self::GALLERY       => array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_gallery' ),
				'schema'            => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
			self::HIGHLIGHTS    => array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_string_list' ),
				'schema'            => $string_list,
			),
			self::INCLUDES      => array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_string_list' ),
				'schema'            => $string_list,
			),
			self::EXCLUDES      => array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_string_list' ),
				'schema'            => $string_list,
			),
			self::ITINERARY     => array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_itinerary' ),
				'schema'            => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'day'         => array( 'type' => 'integer' ),
							'title'       => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
							'items'       => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'time'  => array( 'type' => 'string' ),
										'type'  => array(
											'type' => 'string',
											'enum' => self::ITINERARY_ITEM_TYPES,
										),
										'title' => array( 'type' => 'string' ),
									),
								),
							),
						),
					),
				),
			),
			self::MEETING_POINT => array(
				'type'              => 'object',
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_meeting_point' ),
				'schema'            => array(
					'type'       => 'object',
					'properties' => array(
						'name'    => array( 'type' => 'string' ),
						'address' => array( 'type' => 'string' ),
						'lat'     => array( 'type' => array( 'number', 'null' ) ),
						'lng'     => array( 'type' => array( 'number', 'null' ) ),
					),
				),
			),
			self::PRICING       => array(
				'type'              => 'object',
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_pricing' ),
				'schema'            => array(
					'type'       => 'object',
					'properties' => array(
						'currency' => array( 'type' => 'string' ),
						'plans'    => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'                 => array( 'type' => 'string' ),
									'label'              => array( 'type' => 'string' ),
									'pax'                => array( 'type' => 'integer' ),
									'price'              => array( 'type' => 'integer' ),
									'sale_price'         => array( 'type' => array( 'integer', 'null' ) ),
									'extra_person_price' => array( 'type' => array( 'integer', 'null' ) ),
									'max_pax'            => array( 'type' => array( 'integer', 'null' ) ),
								),
							),
						),
					),
				),
			),
			self::EXTRAS        => array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_extras' ),
				'schema'            => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'      => array( 'type' => 'string' ),
							'label'   => array( 'type' => 'string' ),
							'price'   => array( 'type' => 'integer' ),
							'unit'    => array(
								'type' => 'string',
								'enum' => array( Pricing::UNIT_PER_BOOKING, Pricing::UNIT_PER_PERSON ),
							),
							'max_qty' => array( 'type' => array( 'integer', 'null' ) ),
						),
					),
				),
			),
			self::SPECIAL_OFFER => array(
				'type'    => 'boolean',
				'default' => false,
			),
			self::FEATURED      => array(
				'type'    => 'boolean',
				'default' => false,
			),
			self::SORT_ORDER    => array(
				'type'    => 'integer',
				'default' => 0,
			),
		);

		foreach ( $definitions as $key => $definition ) {
			$schema = $definition['schema'] ?? null;
			unset( $definition['schema'] );

			register_post_meta(
				TourPostType::POST_TYPE,
				$key,
				$definition + array(
					'single'        => true,
					'show_in_rest'  => null === $schema ? true : array( 'schema' => $schema ),
					'auth_callback' => $auth,
				)
			);
		}

		// Demo marker: not exposed, only used by the importer/remover.
		register_post_meta(
			TourPostType::POST_TYPE,
			self::DEMO,
			array(
				'type'          => 'boolean',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => $auth,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Reader
	 * ------------------------------------------------------------------ */

	/**
	 * All tour meta, normalized (always the same shape, safe defaults).
	 *
	 * @return array{
	 *   duration_days: int, gallery: int[], highlights: string[], includes: string[], excludes: string[],
	 *   itinerary: array<int, array<string, mixed>>, meeting_point: array<string, mixed>,
	 *   pricing: array{currency: string, plans: array<int, array<string, mixed>>},
	 *   extras: array<int, array<string, mixed>>, special_offer: bool, featured: bool, sort_order: int
	 * }
	 */
	public static function get( int $tour_id, ?Settings $settings = null ): array {
		$settings = $settings ?? new Settings();

		$pricing = self::sanitize_pricing( get_post_meta( $tour_id, self::PRICING, true ) );
		if ( '' === $pricing['currency'] ) {
			$pricing['currency'] = (string) $settings->get( 'currency_code', 'THB' );
		}

		return array(
			'duration_days' => self::sanitize_duration( get_post_meta( $tour_id, self::DURATION, true ) ),
			'gallery'       => self::sanitize_gallery( get_post_meta( $tour_id, self::GALLERY, true ) ),
			'highlights'    => self::sanitize_string_list( get_post_meta( $tour_id, self::HIGHLIGHTS, true ) ),
			'includes'      => self::sanitize_string_list( get_post_meta( $tour_id, self::INCLUDES, true ) ),
			'excludes'      => self::sanitize_string_list( get_post_meta( $tour_id, self::EXCLUDES, true ) ),
			'itinerary'     => self::sanitize_itinerary( get_post_meta( $tour_id, self::ITINERARY, true ) ),
			'meeting_point' => self::sanitize_meeting_point( get_post_meta( $tour_id, self::MEETING_POINT, true ) ),
			'pricing'       => $pricing,
			'extras'        => self::sanitize_extras( get_post_meta( $tour_id, self::EXTRAS, true ) ),
			'special_offer' => (bool) get_post_meta( $tour_id, self::SPECIAL_OFFER, true ),
			'featured'      => (bool) get_post_meta( $tour_id, self::FEATURED, true ),
			'sort_order'    => (int) get_post_meta( $tour_id, self::SORT_ORDER, true ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Sanitizers (accept any input, always return the canonical shape)
	 * ------------------------------------------------------------------ */

	public static function sanitize_duration( mixed $value ): int {
		return max( 1, min( 365, (int) $value ) );
	}

	/**
	 * @return int[]
	 */
	public static function sanitize_gallery( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	/**
	 * @return string[]
	 */
	public static function sanitize_string_list( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $value as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$text = sanitize_text_field( (string) $item );
			if ( '' !== $text ) {
				$out[] = $text;
			}
		}

		return array_slice( $out, 0, self::MAX_LIST_ITEMS );
	}

	/**
	 * @return array<int, array{day: int, title: string, description: string, items: array<int, array{time: string, type: string, title: string}>}>
	 */
	public static function sanitize_itinerary( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$days = array();
		foreach ( $value as $index => $day ) {
			if ( ! is_array( $day ) ) {
				continue;
			}

			$items = array();
			foreach ( (array) ( $day['items'] ?? array() ) as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
				if ( '' === $title ) {
					continue;
				}
				$type    = (string) ( $item['type'] ?? 'activity' );
				$items[] = array(
					'time'  => sanitize_text_field( (string) ( $item['time'] ?? '' ) ),
					'type'  => in_array( $type, self::ITINERARY_ITEM_TYPES, true ) ? $type : 'activity',
					'title' => $title,
				);
			}

			$days[] = array(
				'day'         => max( 1, (int) ( $day['day'] ?? ( (int) $index + 1 ) ) ),
				'title'       => sanitize_text_field( (string) ( $day['title'] ?? '' ) ),
				'description' => sanitize_textarea_field( (string) ( $day['description'] ?? '' ) ),
				'items'       => array_slice( $items, 0, 40 ),
			);
		}

		usort( $days, static fn ( array $a, array $b ): int => $a['day'] <=> $b['day'] );

		return array_slice( $days, 0, 60 );
	}

	/**
	 * @return array{name: string, address: string, lat: ?float, lng: ?float}
	 */
	public static function sanitize_meeting_point( mixed $value ): array {
		$value = is_array( $value ) ? $value : array();

		$coordinate = static function ( mixed $raw, float $limit ): ?float {
			return ( is_numeric( $raw ) && abs( (float) $raw ) <= $limit ) ? (float) $raw : null;
		};

		return array(
			'name'    => sanitize_text_field( (string) ( $value['name'] ?? '' ) ),
			'address' => sanitize_text_field( (string) ( $value['address'] ?? '' ) ),
			'lat'     => $coordinate( $value['lat'] ?? null, 90 ),
			'lng'     => $coordinate( $value['lng'] ?? null, 180 ),
		);
	}

	/**
	 * @return array{currency: string, plans: array<int, array<string, mixed>>}
	 */
	public static function sanitize_pricing( mixed $value ): array {
		$value    = is_array( $value ) ? $value : array();
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $value['currency'] ?? '' ) ) ?? '' );

		$plans = array();
		$used  = array();
		foreach ( (array) ( $value['plans'] ?? array() ) as $index => $plan ) {
			if ( ! is_array( $plan ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $plan['label'] ?? '' ) );
			$id    = sanitize_key( (string) ( $plan['id'] ?? '' ) );
			if ( '' === $id ) {
				$id = sanitize_key( $label );
			}
			if ( '' === $id ) {
				$id = 'plan_' . ( (int) $index + 1 );
			}
			if ( isset( $used[ $id ] ) ) {
				continue; // Plan ids must be unique.
			}
			$used[ $id ] = true;

			$pax  = max( 1, (int) ( $plan['pax'] ?? 1 ) );
			$sale = $plan['sale_price'] ?? null;
			$out  = array(
				'id'         => $id,
				'label'      => '' !== $label ? $label : $id,
				'pax'        => $pax,
				'price'      => max( 0, (int) ( $plan['price'] ?? 0 ) ),
				'sale_price' => ( null === $sale || '' === $sale || ! is_numeric( $sale ) ) ? null : max( 0, (int) $sale ),
			);

			$extra_person = $plan['extra_person_price'] ?? null;
			if ( is_numeric( $extra_person ) && (int) $extra_person > 0 ) {
				$out['extra_person_price'] = (int) $extra_person;
				$out['max_pax']            = max( $pax, (int) ( $plan['max_pax'] ?? $pax ) );
			}

			$plans[] = $out;
		}

		return array(
			'currency' => substr( $currency, 0, 8 ),
			'plans'    => array_slice( $plans, 0, 12 ),
		);
	}

	/**
	 * @return array<int, array{id: string, label: string, price: int, unit: string, max_qty: ?int}>
	 */
	public static function sanitize_extras( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out  = array();
		$used = array();
		foreach ( $value as $index => $extra ) {
			if ( ! is_array( $extra ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $extra['label'] ?? '' ) );
			if ( '' === $label ) {
				continue;
			}

			$id = sanitize_key( (string) ( $extra['id'] ?? '' ) );
			if ( '' === $id ) {
				$id = sanitize_key( $label );
			}
			if ( '' === $id ) {
				$id = 'extra_' . ( (int) $index + 1 );
			}
			if ( isset( $used[ $id ] ) ) {
				continue;
			}
			$used[ $id ] = true;

			$max_qty = $extra['max_qty'] ?? null;
			$out[]   = array(
				'id'      => $id,
				'label'   => $label,
				'price'   => max( 0, (int) ( $extra['price'] ?? 0 ) ),
				'unit'    => Pricing::UNIT_PER_PERSON === ( $extra['unit'] ?? '' ) ? Pricing::UNIT_PER_PERSON : Pricing::UNIT_PER_BOOKING,
				'max_qty' => ( is_numeric( $max_qty ) && (int) $max_qty > 0 ) ? (int) $max_qty : null,
			);
		}

		return array_slice( $out, 0, 30 );
	}
}
