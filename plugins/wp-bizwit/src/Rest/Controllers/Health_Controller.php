<?php
/**
 * Health check REST endpoint.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Rest\Controllers;

use WP_BizWit\Localization\Regions;
use WP_BizWit\Rest\Rest_Controller;
use WP_BizWit\WP_BizWit;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Minimal route so the Vue shell can verify REST auth and config.
 *
 * GET /wp-json/wp-bizwit/v1/health
 */
class Health_Controller extends Rest_Controller {

	/**
	 * Register the health route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/health',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_health' ),
					'permission_callback' => array( $this, 'permission_any_cap' ),
				),
			)
		);
	}

	/**
	 * Return plugin version and active regional profile.
	 *
	 * @param WP_REST_Request $request Request (unused; required by REST signature).
	 * @return WP_REST_Response
	 */
	public function get_health( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- REST callback signature.
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'version' => WP_BizWit::PLUGIN_VERSION,
				'region'  => Regions::current()->code(),
			),
			200
		);
	}
}
