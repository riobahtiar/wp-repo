<?php
/**
 * Base class for BizWit REST API controllers.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Rest;

use WP_BizWit\Support\Capabilities;

/**
 * Shared namespace, permission helpers and registration shape for REST routes.
 *
 * Controllers never query `$wpdb` directly — they call repositories, same as
 * admin screens. Routes register under {@see self::API_NAMESPACE}.
 */
abstract class Rest_Controller {

	/**
	 * REST API namespace (without leading slash).
	 *
	 * @var string
	 */
	public const API_NAMESPACE = 'wp-bizwit/v1';

	/**
	 * Register this controller's routes with WordPress.
	 *
	 * Hooked from {@see \WP_BizWit\WP_BizWit} on `rest_api_init`.
	 *
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * Whether the current user holds any BizWit capability.
	 *
	 * Health and other shared read endpoints use this so staff with only
	 * `bizwit_manage_clients` can still reach the API shell. Resource routes
	 * should check the specific capability for that resource instead.
	 *
	 * @return bool True when the user may use the BizWit admin API.
	 */
	public function permission_any_cap(): bool {
		return Capabilities::current_user_has_any();
	}
}
