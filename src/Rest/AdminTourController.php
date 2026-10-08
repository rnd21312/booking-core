<?php
/**
 * GET/PUT /stz/v1/admin/tours/{id}/details — the tour editor panel's load/save.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Rest;

use Suntourz\Core\PostTypes\TourPostType;
use Suntourz\Core\Services\TourEditorService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class AdminTourController {

	public function __construct( private TourEditorService $editor ) {}

	public function register_routes(): void {
		register_rest_route(
			HealthController::NAMESPACE,
			'/admin/tours/(?P<id>\d+)/details',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_details' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_details' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);
	}

	/**
	 * @return bool|WP_Error
	 */
	public function can_edit( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( TourPostType::POST_TYPE !== get_post_type( $id ) ) {
			return new WP_Error( 'stz_tour_not_found', __( 'Tour not found.', 'suntourz' ), array( 'status' => 404 ) );
		}

		return current_user_can( 'edit_post', $id );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_details( WP_REST_Request $request ) {
		$data = $this->editor->load( (int) $request['id'] );

		return null === $data
			? new WP_Error( 'stz_tour_not_found', __( 'Tour not found.', 'suntourz' ), array( 'status' => 404 ) )
			: new WP_REST_Response( $data );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_details( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'stz_bad_request', __( 'Invalid request body.', 'suntourz' ), array( 'status' => 400 ) );
		}

		$result = $this->editor->save( (int) $request['id'], $body );

		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}
}
