<?php
/**
 * Reviews API: GET /reviews, GET|POST /tours/{id}/reviews.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Rest;

use Suntourz\Core\Services\ReviewService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class ReviewsController {

	public function __construct( private ReviewService $reviews ) {}

	public function register_routes(): void {
		register_rest_route(
			HealthController::NAMESPACE,
			'/reviews',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'latest' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'per_page'   => array(
						'type'    => 'integer',
						'default' => 4,
						'minimum' => 1,
						'maximum' => 24,
					),
					'min_rating' => array(
						'type'    => 'integer',
						'default' => 4,
						'minimum' => 0,
						'maximum' => 5,
					),
				),
			)
		);

		register_rest_route(
			HealthController::NAMESPACE,
			'/tours/(?P<id>\d+)/reviews',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'for_tour' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 10,
							'minimum' => 1,
							'maximum' => 50,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	public function latest( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			$this->reviews->list( 0, 1, (int) $request['per_page'], (int) $request['min_rating'] )
		);
	}

	public function for_tour( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			$this->reviews->list( (int) $request['id'], (int) $request['page'], (int) $request['per_page'] )
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$body   = $request->get_json_params();
		$result = $this->reviews->submit( (int) $request['id'], is_array( $body ) ? $body : array() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$approved = 'approved' === $result['status'];

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'status'   => $result['status'],
				'message'  => $approved
					? __( 'Thank you! Your review is now live.', 'suntourz' )
					: __( 'Thank you! Your review will appear once our team has approved it.', 'suntourz' ),
			),
			201
		);
	}
}
