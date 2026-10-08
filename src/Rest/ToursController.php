<?php
/**
 * GET /stz/v1/tours, GET /stz/v1/tours/{id|slug} — public, read-only.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Rest;

use Suntourz\Core\Services\TourCatalog;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class ToursController {

	public function __construct( private TourCatalog $catalog ) {}

	public function register_routes(): void {
		register_rest_route(
			HealthController::NAMESPACE,
			'/tours',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_tours' ),
				'permission_callback' => '__return_true',
				'args'                => $this->list_args(),
			)
		);

		register_rest_route(
			HealthController::NAMESPACE,
			'/tours/(?P<id>[\w-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_tour' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function list_tours( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->catalog->search( $request->get_params() );

		$response = new WP_REST_Response( $result );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['total_pages'] );

		return $response;
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_tour( WP_REST_Request $request ) {
		$tour = $this->catalog->get( (string) $request['id'] );

		if ( null === $tour ) {
			return new WP_Error( 'stz_tour_not_found', __( 'Tour not found.', 'suntourz' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $tour );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function list_args(): array {
		$text = array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		);
		$int  = array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
		);
		$bool = array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
		);

		return array(
			'destination'   => $text,
			'style'         => $text,
			'month'         => array(
				'type'              => 'string',
				'pattern'           => '^\d{4}-(0[1-9]|1[0-2])$',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'duration_min'  => $int,
			'duration_max'  => $int,
			'price_min'     => $int,
			'price_max'     => $int,
			'pax'           => $int,
			'discount'      => $bool,
			'last_minute'   => $bool,
			'special_offer' => $bool,
			'featured'      => $bool,
			'search'        => $text,
			'include'       => array(
				'type'              => 'array',
				'items'             => array( 'type' => 'integer' ),
				'maxItems'          => 50,
				// The raw value may be "4,5,9" or an array; wp_parse_id_list() handles both.
				'sanitize_callback' => static fn ( $value ): array => array_values( array_filter( wp_parse_id_list( $value ) ) ),
			),
			'sort'          => array(
				'type'    => 'string',
				'enum'    => TourCatalog::SORTS,
				'default' => 'recommended',
			),
			'page'          => array_merge( $int, array( 'default' => 1 ) ),
			'per_page'      => array_merge( $int, array( 'default' => 12 ) ),
		);
	}
}
