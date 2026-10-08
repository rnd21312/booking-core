<?php
/**
 * "Suntourz" admin menu: Bookings (with a bubble for new requests), Settings, and the tour /
 * trip-request screens grouped underneath it.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Admin;

use Suntourz\Core\Install\Roles;
use Suntourz\Core\Services\BookingAdminService;
use Suntourz\Core\Services\DemoImporter;
use Suntourz\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const PARENT = 'stz-bookings';

	public function __construct(
		private BookingAdminService $bookings,
		private Settings $settings,
		private DemoImporter $demo
	) {}

	public function hooks(): void {
		// Priority 5: the parent must exist before the post types attach their screens to it.
		add_action( 'admin_menu', array( $this, 'register' ), 5 );
		add_action( 'admin_menu', array( $this, 'badge_trip_requests' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Trip requests that nobody has followed up yet (no e-mails are sent, so the menu is the alert).
	 */
	private function new_trip_requests(): int {
		$query = new \WP_Query(
			array(
				'post_type'      => \Suntourz\Core\PostTypes\TripRequestPostType::POST_TYPE,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_stz_tr_status',
						'value' => 'new',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Adds the bubble to the "Trip requests" sub-menu entry.
	 */
	public function badge_trip_requests(): void {
		global $submenu;

		$count = $this->new_trip_requests();
		if ( $count < 1 || ! isset( $submenu[ self::PARENT ] ) ) {
			return;
		}

		foreach ( $submenu[ self::PARENT ] as $index => $item ) {
			if ( str_contains( (string) $item[2], 'post_type=' . \Suntourz\Core\PostTypes\TripRequestPostType::POST_TYPE ) ) {
				$submenu[ self::PARENT ][ $index ][0] .= sprintf( ' <span class="awaiting-mod">%d</span>', $count ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	public function register(): void {
		$new    = $this->bookings->new_count();
		$bubble = $new > 0 ? sprintf( ' <span class="awaiting-mod">%d</span>', $new ) : '';
		$total  = $new + $this->new_trip_requests();
		$top    = $total > 0 ? sprintf( ' <span class="awaiting-mod">%d</span>', $total ) : '';

		add_menu_page( __( 'Suntourz bookings', 'suntourz' ), 'Suntourz' . $top, Roles::CAP_MANAGE_BOOKINGS, self::PARENT, array( $this, 'render_bookings' ), 'dashicons-palmtree', 25 );
		add_submenu_page( self::PARENT, __( 'Bookings', 'suntourz' ), __( 'Bookings', 'suntourz' ) . $bubble, Roles::CAP_MANAGE_BOOKINGS, self::PARENT, array( $this, 'render_bookings' ) );
		add_submenu_page( self::PARENT, __( 'Suntourz settings', 'suntourz' ), __( 'Settings', 'suntourz' ), 'manage_options', 'stz-settings', array( $this, 'render_settings' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function config(): array {
		return array(
			'restUrl'   => esc_url_raw( rest_url( 'stz/v1/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'currency'  => $this->settings->currency(),
			'locale'    => get_user_locale(),
			'exportUrl' => esc_url_raw( rest_url( 'stz/v1/admin/bookings/export' ) ),
			'toursUrl'  => admin_url( 'edit.php?post_type=stz_tour' ),
			'canSettings' => current_user_can( 'manage_options' ),
			'openId'    => isset( $_GET['booking'] ) ? absint( $_GET['booking'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only deep link.
			'demo'      => array(
				'hasDemo' => $this->demo->has_demo(),
			),
		);
	}

	private function mount( string $id ): void {
		printf(
			'<script type="application/json" id="%1$s-config">%2$s</script><div class="wrap"><div id="%1$s-root" class="stz-admin"><p>%3$s</p></div></div>',
			esc_attr( $id ),
			wp_json_encode( $this->config(), JSON_HEX_TAG | JSON_HEX_AMP ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HEX-escaped JSON.
			esc_html__( 'Loading…', 'suntourz' )
		);
	}

	public function render_bookings(): void {
		$this->mount( 'stz-bookings' );
	}

	public function render_settings(): void {
		$this->mount( 'stz-settings' );
	}

	public function enqueue( string $hook_suffix ): void {
		if ( 'toplevel_page_' . self::PARENT === $hook_suffix ) {
			ViteAssets::enqueue( 'bookings' );
		} elseif ( str_ends_with( $hook_suffix, '_page_stz-settings' ) ) {
			wp_enqueue_media();
			ViteAssets::enqueue( 'settings' );
		}
	}
}
