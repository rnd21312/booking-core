<?php
/**
 * Admin REST API: bookings (list, detail, update, CSV) and settings.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Rest;

use Suntourz\Core\Install\Roles;
use Suntourz\Core\Services\BookingAdminService;
use Suntourz\Core\Services\DemoImporter;
use Suntourz\Core\Services\SiteContent;
use Suntourz\Core\Settings\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class AdminController {

	public function __construct(
		private BookingAdminService $bookings,
		private Settings $settings,
		private SiteContent $content,
		private DemoImporter $demo
	) {}

	public function register_routes(): void {
		$ns       = HealthController::NAMESPACE;
		$bookings = fn (): bool => current_user_can( Roles::CAP_MANAGE_BOOKINGS );
		$options  = fn (): bool => current_user_can( 'manage_options' );

		// Registered before /admin/bookings/{id} so "export" is not read as an id.
		register_rest_route( $ns, '/admin/bookings/export', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'export' ), 'permission_callback' => $bookings ) );
		register_rest_route( $ns, '/admin/bookings', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'list_bookings' ), 'permission_callback' => $bookings ) );
		register_rest_route(
			$ns,
			'/admin/bookings/(?P<id>\d+)',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_booking' ), 'permission_callback' => $bookings ),
				array( 'methods' => 'PATCH', 'callback' => array( $this, 'update_booking' ), 'permission_callback' => $bookings ),
			)
		);
		register_rest_route( $ns, '/admin/demo', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'demo' ), 'permission_callback' => $options ) );
		register_rest_route(
			$ns,
			'/admin/settings',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_settings' ), 'permission_callback' => $options ),
				array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'save_settings' ), 'permission_callback' => $options ),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function filters( WP_REST_Request $request ): array {
		$out = array();
		foreach ( array( 'status', 'tour_id', 'departure_id', 'date_from', 'date_to', 'search', 'page', 'per_page' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value && '' !== $value ) {
				$out[ $key ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
			}
		}

		return $out;
	}

	public function list_bookings( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->bookings->list( $this->filters( $request ) ) );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_booking( WP_REST_Request $request ) {
		$booking = $this->bookings->get( (int) $request['id'] );

		return null === $booking ? new WP_Error( 'stz_booking_not_found', __( 'Booking not found.', 'suntourz' ), array( 'status' => 404 ) ) : new WP_REST_Response( $booking );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_booking( WP_REST_Request $request ) {
		$body   = $request->get_json_params();
		$result = $this->bookings->update( (int) $request['id'], is_array( $body ) ? $body : array() );

		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	/**
	 * Streams the CSV (cookie + _wpnonce auth, so a plain link works).
	 */
	public function export( WP_REST_Request $request ): WP_REST_Response {
		$csv      = $this->bookings->csv( $this->filters( $request ) );
		$response = new WP_REST_Response( null );
		$response->set_status( 200 );
		$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="suntourz-bookings-' . gmdate( 'Y-m-d' ) . '.csv"' );

		add_filter(
			'rest_pre_serve_request',
			static function ( bool $served ) use ( $csv ): bool {
				echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download.
				return true;
			}
		);

		return $response;
	}

	/**
	 * POST /admin/demo { action: "import" | "remove" }
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function demo( WP_REST_Request $request ) {
		$action = (string) $request->get_param( 'action' );

		if ( 'import' === $action ) {
			$result = $this->demo->import();

			return is_wp_error( $result ) ? $result : new WP_REST_Response( array( 'hasDemo' => true ) + $result );
		}
		if ( 'remove' === $action ) {
			return new WP_REST_Response( array( 'hasDemo' => ! $this->demo->remove() ) );
		}

		return new WP_Error( 'stz_bad_request', __( 'Unknown action.', 'suntourz' ), array( 'status' => 400 ) );
	}

	public function get_settings(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'settings'         => $this->settings->for_admin(),
				'content_defaults' => $this->content->defaults(),
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_settings( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'stz_bad_request', __( 'Invalid request body.', 'suntourz' ), array( 'status' => 400 ) );
		}

		$errors = array();
		$clean  = $this->settings->sanitize( $body, $errors );
		if ( array() !== $errors ) {
			return new WP_Error( 'stz_invalid_settings', __( 'Some settings need your attention.', 'suntourz' ), array( 'status' => 422, 'errors' => $errors ) );
		}

		$this->settings->update( $clean );

		return $this->get_settings();
	}
}
