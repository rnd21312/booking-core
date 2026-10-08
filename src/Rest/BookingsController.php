<?php
/**
 * POST /stz/v1/bookings (public booking request) and GET /stz/v1/bookings/{code}?email= (success summary).
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Rest;

use Suntourz\Core\Services\BookingService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class BookingsController {

	public function __construct( private BookingService $bookings ) {}

	public function register_routes(): void {
		register_rest_route(
			HealthController::NAMESPACE,
			'/bookings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			HealthController::NAMESPACE,
			'/bookings/(?P<code>[A-Za-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'summary' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'stz_bad_request', __( 'Invalid request body.', 'suntourz' ), array( 'status' => 400 ) );
		}

		$result = $this->bookings->create( $body );

		return is_wp_error( $result ) ? $result : new WP_REST_Response( array( 'ok' => true ) + $result, 201 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function summary( WP_REST_Request $request ) {
		$summary = $this->bookings->summary( strtoupper( (string) $request['code'] ), sanitize_email( (string) $request['email'] ) );

		// Same answer for "unknown code" and "wrong e-mail" so codes cannot be probed.
		return null === $summary
			? new WP_Error( 'stz_booking_not_found', __( 'We could not find that booking.', 'suntourz' ), array( 'status' => 404 ) )
			: new WP_REST_Response( $summary );
	}
}
