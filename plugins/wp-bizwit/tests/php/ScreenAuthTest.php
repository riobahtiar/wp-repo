<?php
/**
 * Admin screen authorisation boundaries.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Admin\Screens\Clients_Screen;
use WP_BizWit\Admin\Screens\Settings_Screen;
use WP_BizWit\Database\Installer;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Project_Repository;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Settings;

/**
 * Screens re-check capabilities even if the menu is guessed from the URL.
 */
class ScreenAuthTest extends WP_UnitTestCase {

	/**
	 * Install schema.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
		( new Installer() )->install();
		Capabilities::install();
	}

	/**
	 * Convert wp_die into an exception so we can assert on it.
	 *
	 * @return void
	 */
	private function trap_wp_die(): void {
		add_filter(
			'wp_die_handler',
			static function () {
				return static function ( $message, $title = '', $args = array() ): void {
					$code = is_array( $args ) && isset( $args['response'] ) ? (int) $args['response'] : 500;
					throw new WPDieException( (string) $message, $code );
				};
			}
		);
	}

	/**
	 * Subscriber without BizWit caps is rejected from Clients.
	 *
	 * @return void
	 */
	public function test_clients_screen_denies_subscriber(): void {
		$this->trap_wp_die();
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );

		$screen = new Clients_Screen( new Client_Repository(), new Project_Repository() );

		try {
			$screen->render_page();
			$this->fail( 'Expected wp_die for unauthorized user' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 403, $e->getCode() );
		}
	}

	/**
	 * Settings screen requires manage_settings, not merely manage_clients.
	 *
	 * @return void
	 */
	public function test_settings_denies_staff_role(): void {
		$this->trap_wp_die();

		$user_id = self::factory()->user->create( array( 'role' => Capabilities::ROLE_STAFF ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( user_can( $user_id, Capabilities::MANAGE_CLIENTS ) );
		$this->assertFalse( user_can( $user_id, Capabilities::MANAGE_SETTINGS ) );

		$screen = new Settings_Screen();

		try {
			$screen->render_page();
			$this->fail( 'Expected wp_die for staff on settings' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 403, $e->getCode() );
		}
	}

	/**
	 * Manager can open settings (capability granted).
	 *
	 * @return void
	 */
	public function test_settings_allows_manager(): void {
		$user_id = self::factory()->user->create( array( 'role' => Capabilities::ROLE_MANAGER ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( user_can( $user_id, Capabilities::MANAGE_SETTINGS ) );

		$screen = new Settings_Screen();
		ob_start();
		$screen->render_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'wp-bizwit', $html );
		$this->assertStringContainsString( 'wp-bizwit-main', $html );
	}
}
