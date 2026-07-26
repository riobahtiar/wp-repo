<?php
/**
 * BizWit dashboard screen.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Stats_Repository;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

/**
 * At-a-glance figures and entry points into the rest of the plugin.
 */
class Dashboard_Screen extends Screen {

	/**
	 * Menu slug for this screen.
	 *
	 * @var string
	 */
	public const SLUG = 'wp-bizwit';

	/**
	 * Aggregate query source.
	 *
	 * @var Stats_Repository
	 */
	private Stats_Repository $stats;

	/**
	 * Set up the screen.
	 *
	 * @param Stats_Repository $stats Aggregate query source.
	 */
	public function __construct( Stats_Repository $stats ) {
		$this->stats = $stats;
	}

	/**
	 * Capability required to use this screen.
	 *
	 * Every BizWit user can reach the dashboard; the financial tiles are hidden
	 * individually for users without reporting rights.
	 *
	 * @return string Capability name.
	 */
	public function capability(): string {
		return 'read';
	}

	/**
	 * Render the dashboard.
	 *
	 * @return void
	 */
	protected function render(): void {
		$currency   = Settings::currency();
		$can_report = current_user_can( Capabilities::VIEW_REPORTS );

		$month_start = (string) gmdate( 'Y-m-01', (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$month_end   = (string) gmdate( 'Y-m-t', (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		$tiles = array(
			array(
				'label' => __( 'Active clients', 'wp-bizwit' ),
				'value' => number_format_i18n( $this->stats->clients( Client_Repository::STATUS_ACTIVE ) ),
				'url'   => $this->page_url( Clients_Screen::SLUG, array( 'status' => Client_Repository::STATUS_ACTIVE ) ),
			),
			array(
				'label' => __( 'Active projects', 'wp-bizwit' ),
				'value' => number_format_i18n( $this->stats->projects( 'active' ) ),
				'url'   => $this->page_url( Projects_Screen::SLUG ),
			),
		);

		if ( $can_report ) {
			$tiles[] = array(
				'label' => __( 'Outstanding', 'wp-bizwit' ),
				'value' => Money::format( $this->stats->outstanding_minor(), $currency ),
				'url'   => $this->page_url( Invoices_Screen::SLUG ),
			);
			$tiles[] = array(
				'label' => __( 'Received this month', 'wp-bizwit' ),
				'value' => Money::format( $this->stats->payments_between( $month_start, $month_end ), $currency ),
				'url'   => $this->page_url( Payments_Screen::SLUG ),
			);
		}

		$this->view(
			'dashboard',
			array(
				'tiles'          => $tiles,
				'clients_url'    => $this->page_url( Clients_Screen::SLUG ),
				'new_client_url' => $this->page_url( Clients_Screen::SLUG, array( 'action' => 'new' ) ),
				'settings_url'   => $this->page_url( Settings_Screen::SLUG ),
				'can_clients'    => current_user_can( Capabilities::MANAGE_CLIENTS ),
				'can_settings'   => current_user_can( Capabilities::MANAGE_SETTINGS ),
			)
		);
	}
}
