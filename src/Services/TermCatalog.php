<?php
/**
 * Read model for destinations and travel styles ("experiences") with their editorial fields.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Services;

use Suntourz\Core\PostTypes\TourPostType;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class TermCatalog {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function destinations(): array {
		return $this->terms( TourPostType::DESTINATION, true );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function styles(): array {
		return $this->terms( TourPostType::STYLE, false );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function terms( string $taxonomy, bool $destination ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$items = array_map(
			fn ( WP_Term $term ): array => $destination ? $this->destination( $term ) : $this->style( $term ),
			$terms
		);

		usort(
			$items,
			static fn ( array $a, array $b ): int => array( $a['order'], strtolower( $a['name'] ) ) <=> array( $b['order'], strtolower( $b['name'] ) )
		);

		return $items;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function base( WP_Term $term ): array {
		$meta = static fn ( string $key ): string => (string) get_term_meta( $term->term_id, $key, true );

		$image_id = (int) get_term_meta( $term->term_id, 'stz_image_id', true );
		$image    = Media::image( $image_id, 'large' );
		$url      = $meta( 'stz_image_url' );

		if ( null === $image && '' !== $url ) {
			$image = array(
				'id'     => 0,
				'url'    => $url,
				'srcset' => '',
				'width'  => 0,
				'height' => 0,
				'alt'    => $term->name,
			);
		}

		$order = get_term_meta( $term->term_id, 'stz_order', true );

		return array(
			'id'          => $term->term_id,
			'slug'        => $term->slug,
			'name'        => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
			'description' => $term->description,
			'url'         => (string) get_term_link( $term ),
			'count'       => (int) $term->count,
			'parent'      => (int) $term->parent,
			'image'       => $image,
			'order'       => '' === $order ? 999 : (int) $order,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function destination( WP_Term $term ): array {
		$meta       = static fn ( string $key ): string => (string) get_term_meta( $term->term_id, $key, true );
		$highlights = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $meta( 'stz_highlights' ) ) ?: array() ) ) );

		return $this->base( $term ) + array(
			'tagline'     => $meta( 'stz_tagline' ),
			'eyebrow'     => $meta( 'stz_eyebrow' ),
			'vibe'        => $meta( 'stz_vibe' ),
			'best_season' => $meta( 'stz_best_season' ),
			'ideal_stay'  => $meta( 'stz_ideal_stay' ),
			'highlights'  => $highlights,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function style( WP_Term $term ): array {
		$meta = static fn ( string $key ): string => (string) get_term_meta( $term->term_id, $key, true );

		return $this->base( $term ) + array(
			'category' => $meta( 'stz_category' ),
			'location' => $meta( 'stz_location' ),
			'duration' => $meta( 'stz_duration' ),
		);
	}
}
