<?php
/**
 * Registration of the BizWit admin menu.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin;

use WP_BizWit\Admin\Screens\Clients_Screen;
use WP_BizWit\Admin\Screens\Dashboard_Screen;
use WP_BizWit\Admin\Screens\Invoices_Screen;
use WP_BizWit\Admin\Screens\Payments_Screen;
use WP_BizWit\Admin\Screens\Projects_Screen;
use WP_BizWit\Admin\Screens\Screen;
use WP_BizWit\Admin\Screens\Settings_Screen;
use WP_BizWit\Support\Capabilities;

/**
 * Builds the top-level BizWit menu and wires each screen's lifecycle.
 */
class Menu {

	/**
	 * Screens keyed by menu slug, in menu order.
	 *
	 * @var array<string, Screen>
	 */
	private array $screens;

	/**
	 * Menu labels keyed by menu slug.
	 *
	 * @var array<string, string>
	 */
	private array $labels;

	/**
	 * Set up the menu.
	 *
	 * @param Dashboard_Screen $dashboard Dashboard screen.
	 * @param Clients_Screen   $clients   Clients screen.
	 * @param Projects_Screen  $projects  Projects screen.
	 * @param Invoices_Screen  $invoices  Invoices screen.
	 * @param Payments_Screen  $payments  Payments screen.
	 * @param Settings_Screen  $settings  Settings screen.
	 */
	public function __construct(
		Dashboard_Screen $dashboard,
		Clients_Screen $clients,
		Projects_Screen $projects,
		Invoices_Screen $invoices,
		Payments_Screen $payments,
		Settings_Screen $settings
	) {
		$this->screens = array(
			Dashboard_Screen::SLUG => $dashboard,
			Clients_Screen::SLUG   => $clients,
			Projects_Screen::SLUG  => $projects,
			Invoices_Screen::SLUG  => $invoices,
			Payments_Screen::SLUG  => $payments,
			Settings_Screen::SLUG  => $settings,
		);

		$this->labels = array(
			Dashboard_Screen::SLUG => __( 'Dashboard', 'wp-bizwit' ),
			Clients_Screen::SLUG   => __( 'Clients', 'wp-bizwit' ),
			Projects_Screen::SLUG  => __( 'Projects', 'wp-bizwit' ),
			Invoices_Screen::SLUG  => __( 'Invoices', 'wp-bizwit' ),
			Payments_Screen::SLUG  => __( 'Payments', 'wp-bizwit' ),
			Settings_Screen::SLUG  => __( 'Settings', 'wp-bizwit' ),
		);
	}

	/**
	 * Register the menu and every submenu page.
	 *
	 * Hooked to `admin_menu`.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! Capabilities::current_user_has_any() ) {
			return;
		}

		$dashboard = $this->screens[ Dashboard_Screen::SLUG ];

		add_menu_page(
			__( 'BizWit', 'wp-bizwit' ),
			__( 'BizWit', 'wp-bizwit' ),
			$dashboard->capability(),
			Dashboard_Screen::SLUG,
			array( $dashboard, 'render_page' ),
			'dashicons-portfolio',
			56
		);

		foreach ( $this->screens as $slug => $screen ) {
			$hook = add_submenu_page(
				Dashboard_Screen::SLUG,
				$this->labels[ $slug ],
				$this->labels[ $slug ],
				$screen->capability(),
				$slug,
				array( $screen, 'render_page' )
			);

			// A page the current user cannot access returns false rather than a
			// hook suffix, so there is nothing to attach the load handler to.
			if ( false === $hook ) {
				continue;
			}

			add_action( 'load-' . $hook, array( $screen, 'on_load' ) );
		}
	}
}
