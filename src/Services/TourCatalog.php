<?php
/**
 * Read model for tours: listing with filters/sort, detail, computed badges and "from" prices.
 *
 * The catalog loads the (small) set of published tours plus their upcoming departures in two
 * queries, then filters/sorts in PHP — simple and fast for an MVP catalogue.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Services;

use Suntourz\Core\Domain\Badges;
use Suntourz\Core\Domain\Pricing;
use Suntourz\Core\Domain\Seats;
use Suntourz\Core\PostTypes\TourMeta;
use Suntourz\Core\PostTypes\TourPostType;
use Suntourz\Core\Repository\DepartureRepository;
use Suntourz\Core\Settings\Settings;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

final class TourCatalog {

	public const SORTS = array( 'recommended', 'price_asc', 'price_desc', 'date_asc', 'duration_asc', 'newest' );

	public function __construct(
		private DepartureRepository $departures,
		private Settings $settings,
		private ReviewService $reviews
	) {}

	/**
	 * Lists tours.
	 *
	 * Filters (all optional): destination, style (slug or term id), month (YYYY-MM), duration_min/max,
	 * price_min/max (minor units, vs. the "from" price), discount, last_minute, special_offer, featured,
	 * pax, include (tour ids, keeps the given order only when no sort is requested), search, sort, page, per_page.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array{items: array<int, array<string, mixed>>, total: int, total_pages: int, page: int, per_page: int, currency: array<string, mixed>}
	 */
	public function search( array $filters ): array {
		$ids = $this->query_ids( $filters );
		$ctx = $this->context( $ids );

		$cards = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post instanceof WP_Post ) {
				$cards[] = $this->card( $post, $ctx );
			}
		}

		$cards = array_values( array_filter( $cards, fn ( array $card ): bool => $this->matches( $card, $filters ) ) );
		$cards = $this->sort( $cards, (string) ( $filters['sort'] ?? 'recommended' ) );

		$per_page    = max( 1, min( 50, (int) ( $filters['per_page'] ?? 12 ) ) );
		$page        = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$total       = count( $cards );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );

		$items = array_map(
			static function ( array $card ): array {
				unset( $card['_sort'] );

				return $card;
			},
			array_slice( $cards, ( $page - 1 ) * $per_page, $per_page )
		);

		return array(
			'items'       => $items,
			'total'       => $total,
			'total_pages' => $total_pages,
			'page'        => $page,
			'per_page'    => $per_page,
			'currency'    => $this->settings->currency(),
		);
	}

	/**
	 * Full tour for the detail page, by post id or slug. Null when missing/unpublished.
	 *
	 * @param int|string $id_or_slug Post id or slug.
	 * @return array<string, mixed>|null
	 */
	public function get( int|string $id_or_slug ): ?array {
		$post = null;

		if ( is_numeric( $id_or_slug ) ) {
			$post = get_post( (int) $id_or_slug );
		} else {
			$found = get_posts(
				array(
					'post_type'      => TourPostType::POST_TYPE,
					'name'           => sanitize_title( (string) $id_or_slug ),
					'post_status'    => 'publish',
					'posts_per_page' => 1,
				)
			);
			$post  = $found[0] ?? null;
		}

		if ( ! $post instanceof WP_Post || TourPostType::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		$ctx   = $this->context( array( $post->ID ) );
		$card  = $this->card( $post, $ctx );
		$meta  = $ctx['meta'][ $post->ID ];
		$today = $this->departures->today();

		unset( $card['_sort'] );

		$departures = array();
		foreach ( $ctx['departures'][ $post->ID ] ?? array() as $departure ) {
			$departures[] = $this->departure_payload( $departure, $meta['pricing']['plans'], $today );
		}

		$gallery = array();
		foreach ( $meta['gallery'] as $attachment_id ) {
			$image = Media::image( $attachment_id, 'large' );
			if ( null !== $image ) {
				$gallery[] = $image;
			}
		}

		return array_merge(
			$card,
			array(
				'content'       => apply_filters( 'the_content', $post->post_content ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
				'gallery'       => $gallery,
				'highlights'    => $meta['highlights'],
				'includes'      => $meta['includes'],
				'excludes'      => $meta['excludes'],
				'itinerary'     => $meta['itinerary'],
				'meeting_point' => $meta['meeting_point'],
				'pricing'       => $meta['pricing'],
				'extras'        => $meta['extras'],
				'departures'    => $departures,
			)
		);
	}

	/**
	 * Published tour ids matching the taxonomy/meta (WP_Query-able) filters.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return int[]
	 */
	private function query_ids( array $filters ): array {
		$tax_query  = array();
		$meta_query = array();

		foreach ( array(
			'destination' => TourPostType::DESTINATION,
			'style'       => TourPostType::STYLE,
		) as $param => $taxonomy ) {
			$value = $filters[ $param ] ?? '';
			if ( '' === $value || null === $value ) {
				continue;
			}
			$tax_query[] = array(
				'taxonomy'         => $taxonomy,
				'field'            => is_numeric( $value ) ? 'term_id' : 'slug',
				'terms'            => is_numeric( $value ) ? (int) $value : sanitize_title( (string) $value ),
				'include_children' => true,
			);
		}

		$duration_min = (int) ( $filters['duration_min'] ?? 0 );
		$duration_max = (int) ( $filters['duration_max'] ?? 0 );
		if ( $duration_min > 0 ) {
			$meta_query[] = array(
				'key'     => TourMeta::DURATION,
				'value'   => $duration_min,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}
		if ( $duration_max > 0 ) {
			$meta_query[] = array(
				'key'     => TourMeta::DURATION,
				'value'   => $duration_max,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}
		if ( ! empty( $filters['special_offer'] ) ) {
			$meta_query[] = array(
				'key'   => TourMeta::SPECIAL_OFFER,
				'value' => '1',
			);
		}
		if ( ! empty( $filters['featured'] ) ) {
			$meta_query[] = array(
				'key'   => TourMeta::FEATURED,
				'value' => '1',
			);
		}

		$args = array(
			'post_type'              => TourPostType::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 500,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'tax_query'              => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);

		$include = array_values( array_filter( array_map( 'absint', (array) ( $filters['include'] ?? array() ) ) ) );
		if ( array() !== $include ) {
			$args['post__in'] = $include;
		}

		$search = trim( (string) ( $filters['search'] ?? '' ) );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Bulk-loads meta and departures for a set of tours.
	 *
	 * @param int[] $ids Tour ids.
	 * @return array{meta: array<int, array<string, mixed>>, departures: array<int, array<int, array<string, mixed>>>, today: string}
	 */
	private function context( array $ids ): array {
		$meta = array();
		foreach ( $ids as $id ) {
			$meta[ $id ] = TourMeta::get( $id, $this->settings );
		}

		return array(
			'meta'       => $meta,
			'departures' => $this->departures->upcoming_for_tours( $ids ),
			'today'      => $this->departures->today(),
		);
	}

	/**
	 * Listing card (also the base of the detail payload). `_sort` is internal and stripped before output.
	 *
	 * @param array<string, mixed> $ctx Output of context().
	 * @return array<string, mixed>
	 */
	private function card( WP_Post $post, array $ctx ): array {
		$id          = $post->ID;
		$meta        = $ctx['meta'][ $id ];
		$plans       = $meta['pricing']['plans'];
		$today       = $ctx['today'];
		$departures  = $ctx['departures'][ $id ] ?? array();
		$bookable    = array_values(
			array_filter(
				$departures,
				static fn ( array $d ): bool => Seats::is_bookable( $d['status'], $d['start_date'], $today, $d['seats_left'] )
			)
		);
		$next        = $bookable[0] ?? null;
		$has_sale    = Pricing::has_discount( $plans );
		$lowest      = null;
		$lowest_plan = null;

		// "From" price: cheapest plan across bookable departures (their overrides included);
		// falls back to the base plans so sold-out tours still show a price.
		foreach ( array() === $bookable ? array( $this->no_departure() ) : $bookable as $departure ) {
			$overrides = $departure['price_overrides'];
			$has_sale  = $has_sale || Pricing::has_discount( $plans, $overrides );
			$candidate = Pricing::lowest( $plans, $overrides );
			if ( null !== $candidate && ( null === $lowest || $candidate['effective'] < $lowest['effective'] ) ) {
				$lowest      = $candidate;
				$lowest_plan = Pricing::find_plan( $plans, $candidate['plan_id'] );
			}
		}

		$badges = Badges::compute(
			array(
				'has_discount'        => $has_sale,
				'special_offer'       => $meta['special_offer'],
				'has_bookable'        => null !== $next,
				'next_start'          => $next['start_date'] ?? null,
				'next_seats_left'     => $next['seats_left'] ?? null,
				'today'               => $today,
				'last_minute_days'    => (int) $this->settings->get( 'last_minute_days', 14 ),
				'few_seats_threshold' => (int) $this->settings->get( 'few_seats_threshold', 4 ),
			)
		);

		$badge_types = array_column( $badges, 'type' );
		$image_id    = (int) get_post_thumbnail_id( $id );

		$sort_date = $next['start_date'] ?? ( $departures[0]['start_date'] ?? '9999-12-31' );

		return array(
			'id'               => $id,
			'slug'             => $post->post_name,
			'url'              => (string) get_permalink( $id ),
			'title'            => $this->plain( get_the_title( $post ) ),
			'excerpt'          => $this->plain( wp_strip_all_tags( get_the_excerpt( $post ) ) ),
			'image'            => Media::image( $image_id, 'large' ),
			'duration_days'    => $meta['duration_days'],
			'destinations'     => $this->terms( $id, TourPostType::DESTINATION ),
			'styles'           => $this->terms( $id, TourPostType::STYLE ),
			'featured'         => $meta['featured'],
			'rating'           => $this->reviews->stats( $id ),
			'meeting_point'    => $meta['meeting_point']['name'],
			'includes_preview' => array_slice( $meta['includes'], 0, 2 ),
			'price_from'       => $lowest['effective'] ?? null,
			'price_from_regular' => ( null !== $lowest && null !== $lowest['sale_price'] ) ? $lowest['price'] : null,
			'price_from_plan'  => $lowest_plan['label'] ?? null,
			'currency_code'    => $meta['pricing']['currency'],
			'next_departure'   => null === $next ? null : array(
				'id'         => $next['id'],
				'start_date' => $next['start_date'],
				'end_date'   => $next['end_date'],
				'seats_left' => $next['seats_left'],
			),
			'bookable'         => null !== $next,
			'badges'           => array_map( array( $this, 'badge_payload' ), $badges ),
			'_sort'            => array(
				'featured'   => $meta['featured'] ? 0 : 1,
				'order'      => $meta['sort_order'],
				'date'       => $sort_date,
				'price'      => $lowest['effective'] ?? PHP_INT_MAX,
				'duration'   => $meta['duration_days'],
				'published'  => $post->post_date_gmt,
				'title'      => $post->post_title,
				'badge_types' => $badge_types,
				'free_seats' => array_map( static fn ( array $d ): int => $d['seats_left'], $bookable ),
				'months'     => array_values( array_unique( array_map( static fn ( array $d ): string => substr( $d['start_date'], 0, 7 ), $bookable ) ) ),
			),
		);
	}

	/**
	 * Plain text for JSON: core filters (wptexturize) emit HTML entities such as &amp; that React would show literally.
	 */
	private function plain( string $text ): string {
		return html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Placeholder "departure" so base plan prices are still evaluated when nothing is bookable.
	 *
	 * @return array<string, mixed>
	 */
	private function no_departure(): array {
		return array( 'price_overrides' => array() );
	}

	/**
	 * @param array<string, mixed> $card    Card with `_sort` data.
	 * @param array<string, mixed> $filters Filters.
	 */
	private function matches( array $card, array $filters ): bool {
		$sort = $card['_sort'];

		if ( ! empty( $filters['month'] ) && ! in_array( (string) $filters['month'], $sort['months'], true ) ) {
			return false;
		}

		$price_min = (int) ( $filters['price_min'] ?? 0 );
		$price_max = (int) ( $filters['price_max'] ?? 0 );
		if ( ( $price_min > 0 || $price_max > 0 ) && null === $card['price_from'] ) {
			return false;
		}
		if ( $price_min > 0 && $card['price_from'] < $price_min ) {
			return false;
		}
		if ( $price_max > 0 && $card['price_from'] > $price_max ) {
			return false;
		}

		if ( ! empty( $filters['discount'] ) && ! in_array( Badges::DISCOUNT, $sort['badge_types'], true ) ) {
			return false;
		}

		if ( ! empty( $filters['last_minute'] ) && ! in_array( Badges::LAST_MINUTE, $sort['badge_types'], true ) ) {
			return false;
		}

		$pax = (int) ( $filters['pax'] ?? 0 );
		if ( $pax > 0 && array() === array_filter( $sort['free_seats'], static fn ( int $seats ): bool => $seats >= $pax ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param array<int, array<string, mixed>> $cards Cards with `_sort` data.
	 * @return array<int, array<string, mixed>>
	 */
	private function sort( array $cards, string $sort ): array {
		$sort = in_array( $sort, self::SORTS, true ) ? $sort : 'recommended';

		usort(
			$cards,
			static function ( array $a, array $b ) use ( $sort ): int {
				$x = $a['_sort'];
				$y = $b['_sort'];

				$by = match ( $sort ) {
					'price_asc'    => array( $x['price'], $y['price'] ),
					'price_desc'   => array( $y['price'], $x['price'] ),
					'date_asc'     => array( $x['date'], $y['date'] ),
					'duration_asc' => array( $x['duration'], $y['duration'] ),
					'newest'       => array( $y['published'], $x['published'] ),
					default        => array( array( $x['featured'], $x['order'], $x['date'] ), array( $y['featured'], $y['order'], $y['date'] ) ),
				};

				$primary = $by[0] <=> $by[1];

				return 0 !== $primary ? $primary : strcasecmp( (string) $x['title'], (string) $y['title'] );
			}
		);

		return $cards;
	}

	/**
	 * @return array<int, array{id: int, slug: string, name: string}>
	 */
	private function terms( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values(
			array_map(
				static fn ( $term ): array => array(
					'id'   => (int) $term->term_id,
					'slug' => (string) $term->slug,
					'name' => (string) $term->name,
				),
				$terms
			)
		);
	}

	/**
	 * @param array{type: string, seats?: int} $badge Badge.
	 * @return array{type: string, label: string, seats?: int}
	 */
	private function badge_payload( array $badge ): array {
		$label = match ( $badge['type'] ) {
			Badges::DISCOUNT      => __( 'Discount', 'suntourz' ),
			Badges::LAST_MINUTE   => __( 'Last minute', 'suntourz' ),
			Badges::FEW_SEATS     => sprintf(
				/* translators: %d: number of free seats. */
				__( 'Only %d left', 'suntourz' ),
				$badge['seats'] ?? 0
			),
			Badges::SPECIAL_OFFER => __( 'Special offer', 'suntourz' ),
			Badges::SOLD_OUT      => __( 'Sold out', 'suntourz' ),
			default               => $badge['type'],
		};

		return array( 'label' => $label ) + $badge;
	}

	/**
	 * Public shape of a departure, with every plan's effective price.
	 *
	 * @param array<string, mixed>             $departure Departure.
	 * @param array<int, array<string, mixed>> $plans     Tour plans.
	 * @return array<string, mixed>
	 */
	public function departure_payload( array $departure, array $plans, string $today ): array {
		return array(
			'id'         => $departure['id'],
			'start_date' => $departure['start_date'],
			'end_date'   => $departure['end_date'],
			'capacity'   => $departure['capacity'],
			'seats_left' => $departure['seats_left'],
			'status'     => $departure['status'],
			'bookable'   => Seats::is_bookable( $departure['status'], $departure['start_date'], $today, $departure['seats_left'] ),
			'note'       => $departure['note'],
			'prices'     => Pricing::prices_by_plan( $plans, $departure['price_overrides'] ),
		);
	}
}
