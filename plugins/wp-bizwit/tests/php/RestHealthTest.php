<?php
/**
 * Tests for the wp-bizwit/v1 health REST endpoint.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Support\Capabilities;
use WP_BizWit\WP_BizWit;

/**
 * Locks in auth and payload for GET /wp-bizwit/v1/health.
 */
class RestHealthTest extends WP_UnitTestCase {

	/**
	 * Ensure capabilities exist and the REST server is fresh for each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Activation may not have run under the PHPUnit mu-plugin load path.
		Capabilities::install();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * Logged-out callers must not receive health data.
	 */
	public function test_health_rejects_anonymous(): void {
		wp_set_current_user( 0 );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp-bizwit/v1/health' ) );

		$this->assertContains(
			$response->get_status(),
			array( 401, 403 ),
			'Unauthenticated health requests must return 401 or 403.'
		);
	}

	/**
	 * A user with no BizWit capability must be denied.
	 */
	public function test_health_rejects_user_without_bizwit_cap(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp-bizwit/v1/health' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * An administrator (all BizWit caps) gets 200 and the expected shape.
	 */
	public function test_health_as_admin(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp-bizwit/v1/health' ) );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( $data['ok'] );
		$this->assertSame( WP_BizWit::PLUGIN_VERSION, $data['version'] );
		$this->assertArrayHasKey( 'region', $data );
		$this->assertIsString( $data['region'] );
		$this->assertNotSame( '', $data['region'] );
	}

	/**
	 * Staff with only manage_clients still reaches the shared health route.
	 */
	public function test_health_as_staff_with_any_cap(): void {
		$user_id = self::factory()->user->create( array( 'role' => Capabilities::ROLE_STAFF ) );
		wp_set_current_user( $user_id );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp-bizwit/v1/health' ) );

		$this->assertSame( 200, $response->get_status() );
	}
}
