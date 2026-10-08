<?php
/**
 * "Plan My Trip" requests: private post type with an admin list, details box and status.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\PostTypes;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class TripRequestPostType {

	public const POST_TYPE = 'stz_trip_request';
	public const STATUSES  = array( 'new', 'contacted', 'closed' );

	/** Meta key => label shown in the admin details box. */
	public const FIELDS = array(
		'_stz_tr_name'        => 'Name',
		'_stz_tr_email'       => 'Email',
		'_stz_tr_phone'       => 'Phone / WhatsApp',
		'_stz_tr_destination' => 'Destination',
		'_stz_tr_style'       => 'Travel style',
		'_stz_tr_season'      => 'When',
		'_stz_tr_duration'    => 'Duration',
		'_stz_tr_travelers'   => 'Travellers',
		'_stz_tr_hotel'       => 'Accommodation',
		'_stz_tr_budget'      => 'Budget',
		'_stz_tr_notes'       => 'Notes',
	);

	public function hooks(): void {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_status' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
	}

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Trip requests', 'suntourz' ),
					'singular_name' => __( 'Trip request', 'suntourz' ),
					'all_items'     => __( 'Trip requests', 'suntourz' ),
					'edit_item'     => __( 'Trip request', 'suntourz' ),
					'search_items'  => __( 'Search trip requests', 'suntourz' ),
					'not_found'     => __( 'No trip requests yet', 'suntourz' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'stz-bookings',
				'supports'        => array( 'title' ),
				// Own capability set (edit_stz_trip_requests …), granted by Roles::add() to the same roles that
				// hold stz_manage_bookings. Pointing meta caps at stz_manage_bookings itself would make WordPress
				// treat that capability as a post meta cap and deny it everywhere.
				'map_meta_cap'    => true,
				'capability_type' => array( 'stz_trip_request', 'stz_trip_requests' ),
				'capabilities'    => array(
					'create_posts' => 'do_not_allow', // Requests only come from the public form.
				),
			)
		);
	}

	public function add_meta_boxes(): void {
		add_meta_box( 'stz-tr-details', __( 'Request details', 'suntourz' ), array( $this, 'render_details' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'stz-tr-status', __( 'Follow-up', 'suntourz' ), array( $this, 'render_status' ), self::POST_TYPE, 'side', 'high' );
	}

	public function render_details( WP_Post $post ): void {
		echo '<table class="widefat striped"><tbody>';
		foreach ( self::FIELDS as $key => $label ) {
			$value = (string) get_post_meta( $post->ID, $key, true );
			if ( '' === $value ) {
				continue;
			}
			printf(
				'<tr><th style="width:170px">%s</th><td>%s</td></tr>',
				esc_html( $label ),
				nl2br( esc_html( $value ) )
			);
		}

		$interests = (array) get_post_meta( $post->ID, '_stz_tr_interests', true );
		if ( array() !== array_filter( $interests ) ) {
			printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Interests', 'suntourz' ), esc_html( implode( ', ', $interests ) ) );
		}

		$tour_ids = array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_stz_tr_tour_ids', true ) ) );
		if ( array() !== $tour_ids ) {
			$links = array();
			foreach ( $tour_ids as $tour_id ) {
				$links[] = sprintf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $tour_id ) ), esc_html( get_the_title( $tour_id ) ) );
			}
			printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Tours of interest', 'suntourz' ), wp_kses( implode( ', ', $links ), array( 'a' => array( 'href' => array() ) ) ) );
		}

		echo '</tbody></table>';
	}

	public function render_status( WP_Post $post ): void {
		$current = (string) get_post_meta( $post->ID, '_stz_tr_status', true ) ?: 'new';
		$email   = (string) get_post_meta( $post->ID, '_stz_tr_email', true );
		$phone   = preg_replace( '/[^\d]/', '', (string) get_post_meta( $post->ID, '_stz_tr_phone', true ) );

		wp_nonce_field( 'stz_tr_status', 'stz_tr_status_nonce' );
		echo '<p><label for="stz_tr_status"><strong>' . esc_html__( 'Status', 'suntourz' ) . '</strong></label></p><select name="stz_tr_status" id="stz_tr_status" style="width:100%">';
		foreach ( self::STATUSES as $status ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $status ), selected( $current, $status, false ), esc_html( ucfirst( $status ) ) );
		}
		echo '</select>';

		echo '<p style="margin-top:14px">';
		if ( is_email( $email ) ) {
			printf( '<a class="button" href="%s">%s</a> ', esc_url( 'mailto:' . $email ), esc_html__( 'Email', 'suntourz' ) );
		}
		if ( '' !== $phone ) {
			printf( '<a class="button" href="%s" target="_blank" rel="noopener">%s</a>', esc_url( 'https://wa.me/' . $phone ), esc_html__( 'WhatsApp', 'suntourz' ) );
		}
		echo '</p>';
	}

	public function save_status( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['stz_tr_status_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['stz_tr_status_nonce'] ) ), 'stz_tr_status' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$status = isset( $_POST['stz_tr_status'] ) ? sanitize_key( wp_unslash( $_POST['stz_tr_status'] ) ) : 'new';
		update_post_meta( $post_id, '_stz_tr_status', in_array( $status, self::STATUSES, true ) ? $status : 'new' );
	}

	/**
	 * @param array<string, string> $columns Default columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'          => $columns['cb'] ?? '',
			'title'       => __( 'Reference', 'suntourz' ),
			'stz_guest'   => __( 'Guest', 'suntourz' ),
			'stz_trip'    => __( 'Trip', 'suntourz' ),
			'stz_status'  => __( 'Status', 'suntourz' ),
			'date'        => $columns['date'] ?? __( 'Date', 'suntourz' ),
		);
	}

	public function column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'stz_guest':
				echo esc_html( (string) get_post_meta( $post_id, '_stz_tr_name', true ) ) . '<br><span class="description">' . esc_html( (string) get_post_meta( $post_id, '_stz_tr_email', true ) ) . '</span>';
				break;
			case 'stz_trip':
				echo esc_html( (string) get_post_meta( $post_id, '_stz_tr_destination', true ) ) . '<br><span class="description">' . esc_html( (string) get_post_meta( $post_id, '_stz_tr_travelers', true ) ) . ' · ' . esc_html( (string) get_post_meta( $post_id, '_stz_tr_duration', true ) ) . '</span>';
				break;
			case 'stz_status':
				echo esc_html( ucfirst( (string) get_post_meta( $post_id, '_stz_tr_status', true ) ?: 'new' ) );
				break;
		}
	}

	/**
	 * @param array<string, string> $actions Row actions.
	 * @return array<string, string>
	 */
	public function row_actions( array $actions, WP_Post $post ): array {
		if ( self::POST_TYPE === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
		}

		return $actions;
	}
}
