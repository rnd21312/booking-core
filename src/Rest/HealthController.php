<?php
/**
 * GET /wp-json/stz/v1/health — lets the theme, tests and humans confirm the plugin is alive.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Rest;

use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class HealthController {

	public const NAMESPACE = 'stz/v1';

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function get_health(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'plugin'  => 'suntourz-core',
				'version' => STZ_CORE_VERSION,
			)
		);
	}
}
