<?php
/**
 * Travel Guide articles = regular WordPress posts (so the client uses the normal editor).
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Services;

use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

final class ArticleService {

	public const SORTS = array( 'newest', 'oldest', 'title_asc', 'title_desc' );

	/**
	 * @param string $category Category slug.
	 * @param string $search   Free-text search.
	 * @param string $sort     One of self::SORTS.
	 * @param string $tag      Tag slug.
	 * @return array{items: array<int, array<string, mixed>>, total: int, total_pages: int, page: int, per_page: int}
	 */
	public function list( int $page = 1, int $per_page = 6, string $category = '', string $search = '', string $sort = 'newest', string $tag = '' ): array {
		$per_page = max( 1, min( 50, $per_page ) );
		$page     = max( 1, $page );

		$order = match ( $sort ) {
			'oldest'     => array( 'orderby' => 'date', 'order' => 'ASC' ),
			'title_asc'  => array( 'orderby' => 'title', 'order' => 'ASC' ),
			'title_desc' => array( 'orderby' => 'title', 'order' => 'DESC' ),
			default      => array( 'orderby' => 'date', 'order' => 'DESC' ),
		};

		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'category_name'       => $category,
			'tag'                 => $tag,
			's'                   => trim( $search ),
			'ignore_sticky_posts' => true,
		) + $order;

		$query = new WP_Query( $args );

		return array(
			'items'       => array_map( fn ( WP_Post $post ): array => $this->card( $post ), $query->posts ),
			'total'       => (int) $query->found_posts,
			'total_pages' => max( 1, (int) $query->max_num_pages ),
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	/**
	 * Categories that have at least one published article (sidebar filter).
	 *
	 * @return array<int, array{id: int, slug: string, name: string, count: int, url: string}>
	 */
	public function categories(): array {
		$terms = get_categories(
			array(
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		$out = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$out[] = array(
				'id'    => (int) $term->term_id,
				'slug'  => $term->slug,
				'name'  => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'count' => (int) $term->count,
				'url'   => (string) get_category_link( $term ),
			);
		}

		return $out;
	}

	/**
	 * Most used tags (sidebar topic cloud).
	 *
	 * @return array<int, array{id: int, slug: string, name: string, count: int, url: string}>
	 */
	public function tags( int $limit = 12 ): array {
		$terms = get_tags(
			array(
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => $limit,
			)
		);

		$out = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$out[] = array(
				'id'    => (int) $term->term_id,
				'slug'  => $term->slug,
				'name'  => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'count' => (int) $term->count,
				'url'   => (string) get_tag_link( $term ),
			);
		}

		return $out;
	}

	/**
	 * Full article by id or slug.
	 *
	 * @param int|string $id_or_slug Post id or slug.
	 * @return array<string, mixed>|null
	 */
	public function get( int|string $id_or_slug ): ?array {
		$post = is_numeric( $id_or_slug )
			? get_post( (int) $id_or_slug )
			: ( get_posts(
				array(
					'post_type'      => 'post',
					'name'           => sanitize_title( (string) $id_or_slug ),
					'post_status'    => 'publish',
					'posts_per_page' => 1,
				)
			)[0] ?? null );

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		return $this->full( $post );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function full( WP_Post $post ): array {
		return $this->card( $post ) + array(
			'content' => apply_filters( 'the_content', $post->post_content ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function card( WP_Post $post ): array {
		$author_id   = (int) $post->post_author;
		$author_name = (string) get_the_author_meta( 'display_name', $author_id );
		$author_role = trim( (string) get_the_author_meta( 'description', $author_id ) );
		$categories  = get_the_category( $post->ID );
		$words       = str_word_count( wp_strip_all_tags( $post->post_content ) );

		return array(
			'id'           => $post->ID,
			'slug'         => $post->post_name,
			'url'          => (string) get_permalink( $post ),
			'title'        => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'excerpt'      => html_entity_decode( wp_strip_all_tags( get_the_excerpt( $post ) ), ENT_QUOTES, 'UTF-8' ),
			'image'        => Media::image( (int) get_post_thumbnail_id( $post ), 'large' ),
			'category'     => $categories ? html_entity_decode( $categories[0]->name, ENT_QUOTES, 'UTF-8' ) : __( 'Travel Guide', 'suntourz' ),
			'category_slug' => $categories ? $categories[0]->slug : '',
			'read_minutes' => max( 1, (int) ceil( $words / 200 ) ),
			'author'       => '' !== $author_role ? $author_name . ', ' . $author_role : $author_name,
			'date'         => wp_date( 'F j, Y', (int) get_post_timestamp( $post ) ),
		);
	}
}
