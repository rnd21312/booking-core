<?php
/**
 * Traveller reviews, stored as WordPress comments of type `stz_review` on a tour.
 * They are moderated in the normal Comments screen; approved reviews feed the tour rating.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Services;

use Suntourz\Core\Install\Schema;
use Suntourz\Core\PostTypes\TourPostType;
use Suntourz\Core\Settings\Settings;
use Suntourz\Core\Support\RateLimiter;
use WP_Comment;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class ReviewService {

	public const TYPE        = 'stz_review';
	public const AVG_META    = '_stz_rating_avg';
	public const COUNT_META  = '_stz_rating_count';
	private const RATE_LIMIT = 3;
	private const RATE_WINDOW = 3600;

	public function __construct(
		private Settings $settings
	) {}

	public function hooks(): void {
		// Keep the cached rating in sync with every moderation action.
		add_action( 'transition_comment_status', array( $this, 'on_status_change' ), 10, 3 );
		add_action( 'wp_insert_comment', array( $this, 'on_insert' ), 10, 2 );
		add_action( 'deleted_comment', array( $this, 'on_delete' ), 10, 2 );
		add_action( 'edit_comment', array( $this, 'on_edit' ) );
	}

	public function on_status_change( string $new, string $old, WP_Comment $comment ): void {
		if ( self::TYPE === $comment->comment_type ) {
			$this->recalculate( (int) $comment->comment_post_ID );
		}
	}

	public function on_insert( int $id, WP_Comment $comment ): void {
		if ( self::TYPE === $comment->comment_type ) {
			$this->recalculate( (int) $comment->comment_post_ID );
		}
	}

	public function on_delete( mixed $id, WP_Comment $comment ): void {
		if ( self::TYPE === $comment->comment_type ) {
			$this->recalculate( (int) $comment->comment_post_ID );
		}
	}

	public function on_edit( int $id ): void {
		$comment = get_comment( $id );
		if ( $comment && self::TYPE === $comment->comment_type ) {
			$this->recalculate( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * Rebuilds the cached average/count of a tour from its approved reviews.
	 */
	public function recalculate( int $tour_id ): void {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(c.comment_ID) AS total, AVG(CAST(m.meta_value AS DECIMAL(4,2))) AS average
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} m ON m.comment_id = c.comment_ID AND m.meta_key = 'stz_rating'
				WHERE c.comment_post_ID = %d AND c.comment_type = %s AND c.comment_approved = '1'",
				$tour_id,
				self::TYPE
			),
			ARRAY_A
		);

		update_post_meta( $tour_id, self::COUNT_META, (int) ( $row['total'] ?? 0 ) );
		update_post_meta( $tour_id, self::AVG_META, round( (float) ( $row['average'] ?? 0 ), 2 ) );
	}

	/**
	 * Cached rating of a tour.
	 *
	 * @return array{average: float, count: int}
	 */
	public function stats( int $tour_id ): array {
		return array(
			'average' => round( (float) get_post_meta( $tour_id, self::AVG_META, true ), 1 ),
			'count'   => (int) get_post_meta( $tour_id, self::COUNT_META, true ),
		);
	}

	/**
	 * Site-wide rating across all approved reviews.
	 *
	 * @return array{average: float, count: int}
	 */
	public function overall(): array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(c.comment_ID) AS total, AVG(CAST(m.meta_value AS DECIMAL(4,2))) AS average
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} m ON m.comment_id = c.comment_ID AND m.meta_key = 'stz_rating'
				WHERE c.comment_type = %s AND c.comment_approved = '1'",
				self::TYPE
			),
			ARRAY_A
		);

		return array(
			'average' => round( (float) ( $row['average'] ?? 0 ), 2 ),
			'count'   => (int) ( $row['total'] ?? 0 ),
		);
	}

	/**
	 * Approved reviews of one tour (or of all tours when $tour_id is 0), newest first.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int, total_pages: int, stats: array{average: float, count: int}}
	 */
	public function list( int $tour_id, int $page = 1, int $per_page = 10, int $min_rating = 0 ): array {
		$per_page = max( 1, min( 50, $per_page ) );
		$page     = max( 1, $page );

		$args = array(
			'type'    => self::TYPE,
			'status'  => 'approve',
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		);
		if ( $tour_id > 0 ) {
			$args['post_id'] = $tour_id;
		}
		if ( $min_rating > 0 ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'stz_rating',
					'value'   => $min_rating,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			);
		}

		$total    = (int) get_comments( $args + array( 'count' => true ) );
		$comments = get_comments( $args + array( 'number' => $per_page, 'offset' => ( $page - 1 ) * $per_page ) );

		return array(
			'items'       => array_map( array( $this, 'payload' ), is_array( $comments ) ? $comments : array() ),
			'total'       => $total,
			'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
			'stats'       => $tour_id > 0 ? $this->stats( $tour_id ) : $this->overall(),
		);
	}

	/**
	 * Stores a new review (pending moderation unless auto-approve is on).
	 *
	 * @param array<string, mixed> $input name, email, rating, review, country.
	 * @return array{id: int, status: string}|WP_Error
	 */
	public function submit( int $tour_id, array $input ): array|WP_Error {
		$tour = get_post( $tour_id );
		if ( ! $tour || TourPostType::POST_TYPE !== $tour->post_type || 'publish' !== $tour->post_status ) {
			return new WP_Error( 'stz_tour_not_found', __( 'Tour not found.', 'suntourz' ), array( 'status' => 404 ) );
		}

		// Honeypot: bots fill the hidden field. Pretend success without storing anything.
		if ( '' !== trim( (string) ( $input['website'] ?? '' ) ) ) {
			return array(
				'id'     => 0,
				'status' => 'pending',
			);
		}

		$errors  = array();
		$name    = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		$email   = sanitize_email( (string) ( $input['email'] ?? '' ) );
		$country = sanitize_text_field( (string) ( $input['country'] ?? '' ) );
		$review  = sanitize_textarea_field( (string) ( $input['review'] ?? '' ) );
		$rating  = (int) ( $input['rating'] ?? 0 );

		if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 80 ) {
			$errors['name'] = __( 'Please enter your name (2–80 characters).', 'suntourz' );
		}
		if ( ! is_email( $email ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'suntourz' );
		}
		if ( $rating < 1 || $rating > 5 ) {
			$errors['rating'] = __( 'Please choose a rating from 1 to 5 stars.', 'suntourz' );
		}
		if ( mb_strlen( $review ) < 20 || mb_strlen( $review ) > 1500 ) {
			$errors['review'] = __( 'Please tell us a little more (20–1500 characters).', 'suntourz' );
		}
		if ( mb_strlen( $country ) > 80 ) {
			$errors['country'] = __( 'Country is too long.', 'suntourz' );
		}
		if ( array() !== $errors ) {
			return new WP_Error( 'stz_invalid_review', __( 'Some fields need your attention.', 'suntourz' ), array( 'status' => 422, 'errors' => $errors ) );
		}

		if ( $this->already_reviewed( $tour_id, $email ) ) {
			return new WP_Error(
				'stz_invalid_review',
				__( 'You have already reviewed this tour.', 'suntourz' ),
				array(
					'status' => 422,
					'errors' => array( 'email' => __( 'A review from this email already exists for this tour.', 'suntourz' ) ),
				)
			);
		}

		if ( ! RateLimiter::allow( 'review', self::RATE_LIMIT, self::RATE_WINDOW ) ) {
			return new WP_Error( 'stz_rate_limited', __( 'Too many submissions. Please try again later.', 'suntourz' ), array( 'status' => 429 ) );
		}

		$auto     = (bool) $this->settings->get( 'reviews_auto_approve', false );
		$comment  = wp_insert_comment(
			array(
				'comment_post_ID'      => $tour_id,
				'comment_author'       => $name,
				'comment_author_email' => $email,
				'comment_content'      => $review,
				'comment_type'         => self::TYPE,
				'comment_approved'     => $auto ? 1 : 0,
				'comment_meta'         => array(
					'stz_rating'  => $rating,
					'stz_country' => $country,
				),
			)
		);

		if ( ! $comment ) {
			return new WP_Error( 'stz_review_failed', __( 'Your review could not be saved. Please try again.', 'suntourz' ), array( 'status' => 500 ) );
		}

		return array(
			'id'     => (int) $comment,
			'status' => $auto ? 'approved' : 'pending',
		);
	}

	private function already_reviewed( int $tour_id, string $email ): bool {
		$found = get_comments(
			array(
				'type'         => self::TYPE,
				'post_id'      => $tour_id,
				'author_email' => $email,
				'status'       => 'all',
				'number'       => 1,
				'count'        => true,
			)
		);

		return (int) $found > 0;
	}

	/**
	 * Whether the reviewer has a paid/completed booking for the tour (shows the "verified" tick).
	 */
	private function is_verified( int $tour_id, string $email ): bool {
		global $wpdb;

		if ( '' === $email ) {
			return false;
		}

		$table = Schema::bookings();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE tour_id = %d AND customer_email = %s AND status IN ('paid','completed')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$tour_id,
				$email
			)
		) > 0;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function payload( WP_Comment $comment ): array {
		$tour_id = (int) $comment->comment_post_ID;

		return array(
			'id'       => (int) $comment->comment_ID,
			'name'     => $comment->comment_author,
			'country'  => (string) get_comment_meta( (int) $comment->comment_ID, 'stz_country', true ),
			'rating'   => (int) get_comment_meta( (int) $comment->comment_ID, 'stz_rating', true ),
			'review'   => $comment->comment_content,
			'date'     => wp_date( 'F Y', strtotime( $comment->comment_date_gmt . ' UTC' ) ?: null ),
			'verified' => $this->is_verified( $tour_id, $comment->comment_author_email ),
			'tour'     => array(
				'id'    => $tour_id,
				'title' => html_entity_decode( get_the_title( $tour_id ), ENT_QUOTES, 'UTF-8' ),
				'url'   => (string) get_permalink( $tour_id ),
			),
		);
	}
}
