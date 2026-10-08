<?php
/**
 * POST /stz/v1/quote — public, no side effects: returns the server-computed total and validation errors.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Rest;

use Suntourz\Core\Services\QuoteService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class QuoteController {

	public function __construct( private QuoteService $quotes ) {}

	public function register_routes(): void {
		register_rest_route(
			HealthController::NAMESPACE,
			'/quote',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'quote' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'departure_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'plan_id'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'pax'          => array(
						'type'    => array( 'integer', 'null' ),
						'minimum' => 1,
						'maximum' => 50,
					),
					'extras'       => array(
						'type'    => 'array',
						'default' => array(),
						'maxItems' => 30,
						'items'   => array(
							'type'       => 'object',
							'properties' => array(
								'id'  => array( 'type' => 'string' ),
								'qty' => array(
									'type'    => 'integer',
									'minimum' => 0,
									'maximum' => 50,
								),
							),
						),
					),
				),
			)
		);
	}

	public function quote( WP_REST_Request $request ): WP_REST_Response {
		$extras = array();
		foreach ( (array) $request->get_param( 'extras' ) as $extra ) {
			$extras[] = array(
				'id'  => sanitize_key( (string) ( $extra['id'] ?? '' ) ),
				'qty' => (int) ( $extra['qty'] ?? 1 ),
			);
		}

		return new WP_REST_Response(
			$this->quotes->quote(
				array(
					'departure_id' => (int) $request['departure_id'],
					'plan_id'      => (string) $request['plan_id'],
					'pax'          => null === $request['pax'] ? null : (int) $request['pax'],
					'extras'       => $extras,
				)
			)
		);
	}
}
