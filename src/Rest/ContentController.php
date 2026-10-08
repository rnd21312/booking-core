<?php
/**
 * Public read endpoints for editorial content: destinations, travel styles and articles.
 *
 * @package Suntourz\Core
 */

declare(strict_types=1);

namespace Suntourz\Core\Rest;

use Suntourz\Core\Services\ArticleService;
use Suntourz\Core\Services\TermCatalog;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class ContentController {

	public function __construct(
		private TermCatalog $terms,
		private ArticleService $articles
	) {}

	public function register_routes(): void {
		$read = array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
		);

		register_rest_route( HealthController::NAMESPACE, '/destinations', $read + array( 'callback' => fn (): WP_REST_Response => new WP_REST_Response( $this->terms->destinations() ) ) );
		register_rest_route( HealthController::NAMESPACE, '/styles', $read + array( 'callback' => fn (): WP_REST_Response => new WP_REST_Response( $this->terms->styles() ) ) );

		register_rest_route(
			HealthController::NAMESPACE,
			'/articles',
			$read + array(
				'callback' => array( $this, 'list_articles' ),
				'args'     => array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 6,
						'minimum' => 1,
						'maximum' => 50,
					),
					'category' => array(
						'type'              => 'string',
						'default'           => '',
						// Closure: REST passes ($value, $request, $key), which sanitize_title() would misread.
						'sanitize_callback' => static fn ( $value ): string => sanitize_title( (string) $value ),
					),
					'tag'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static fn ( $value ): string => sanitize_title( (string) $value ),
					),
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static fn ( $value ): string => mb_substr( sanitize_text_field( (string) $value ), 0, 80 ),
					),
					'sort'     => array(
						'type'    => 'string',
						'default' => 'newest',
						'enum'    => ArticleService::SORTS,
					),
				),
			)
		);

		register_rest_route(
			HealthController::NAMESPACE,
			'/articles/taxonomy',
			$read + array(
				'callback' => fn (): WP_REST_Response => new WP_REST_Response(
					array(
						'categories' => $this->articles->categories(),
						'tags'       => $this->articles->tags(),
					)
				),
			)
		);

		register_rest_route(
			HealthController::NAMESPACE,
			'/articles/(?P<id>[\w-]+)',
			$read + array( 'callback' => array( $this, 'get_article' ) )
		);
	}

	public function list_articles( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			$this->articles->list(
				(int) $request['page'],
				(int) $request['per_page'],
				(string) $request['category'],
				(string) $request['search'],
				(string) $request['sort'],
				(string) $request['tag']
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_article( WP_REST_Request $request ) {
		$article = $this->articles->get( (string) $request['id'] );

		return null === $article
			? new WP_Error( 'stz_article_not_found', __( 'Article not found.', 'suntourz' ), array( 'status' => 404 ) )
			: new WP_REST_Response( $article );
	}
}
