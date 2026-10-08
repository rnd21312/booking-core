<?php
/**
 * POST /stz/v1/trip-requests — "Plan My Trip" submissions.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Rest;

use Suntourz\Core\Services\TripRequestService;
use Suntourz\Core\Settings\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class TripRequestsController {

	public function __construct(
		private TripRequestService $requests,
		private Settings $settings
	) {}

	public function register_routes(): void {
		register_rest_route(
			HealthController::NAMESPACE,
			'/trip-requests',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				'permission_callback' => '__return_true',
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

		$result = $this->requests->submit( $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'ok'            => true,
				'reference'     => $result['reference'],
				'response_time' => (string) $this->settings->get( 'response_time_text', 'within 24 hours' ),
			),
			201
		);
	}
}
