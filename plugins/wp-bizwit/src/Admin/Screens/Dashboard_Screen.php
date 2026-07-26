<?php
/**
 * BizWit dashboard screen.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

use WP_BizWit\Admin\Notices;
use WP_BizWit\Documents\Template_Post_Type;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Stats_Repository;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Onboarding;
use WP_BizWit\Support\Settings;

/**
 * At-a-glance figures, setup checklist, ageing and quick actions.
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
	 * Handle checklist dismissal before output.
	 *
	 * @return void
	 */
	public function on_load(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below.
		$action = isset( $_GET['bizwit_onboarding'] ) ? sanitize_key( wp_unslash( $_GET['bizwit_onboarding'] ) ) : '';
		if ( 'dismiss' !== $action ) {
			return;
		}

		check_admin_referer( 'wp_bizwit_dismiss_onboarding' );
		Onboarding::dismiss();
		Notices::add( __( 'Setup checklist dismissed. You can keep using BizWit as usual.', 'wp-bizwit' ), 'success' );
		wp_safe_redirect( $this->page_url( self::SLUG ) );
		exit;
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
				'url'   => $this->page_url( Invoices_Screen::SLUG, array( 'overdue' => '1' ) ),
			);
			$tiles[] = array(
				'label' => __( 'Received this month', 'wp-bizwit' ),
				'value' => Money::format( $this->stats->payments_between( $month_start, $month_end ), $currency ),
				'url'   => $this->page_url( Payments_Screen::SLUG ),
			);
		}

		$urls = array(
			'settings'    => $this->page_url( Settings_Screen::SLUG ),
			'new_client'  => $this->page_url( Clients_Screen::SLUG, array( 'action' => 'new' ) ),
			'new_invoice' => $this->page_url( Invoices_Screen::SLUG, array( 'action' => 'new' ) ),
			'templates'   => admin_url( 'edit.php?post_type=' . Template_Post_Type::POST_TYPE ),
		);

		$checklist_steps = Onboarding::steps( $urls );
		$show_checklist  = ! Onboarding::is_dismissed() && Onboarding::has_incomplete( $checklist_steps );

		$dismiss_url = wp_nonce_url(
			$this->page_url( self::SLUG, array( 'bizwit_onboarding' => 'dismiss' ) ),
			'wp_bizwit_dismiss_onboarding'
		);

		$ageing         = $can_report ? $this->stats->outstanding_ageing_minor() : null;
		$recent         = current_user_can( Capabilities::MANAGE_INVOICES )
			? $this->stats->recent_invoices( 5 )
			: array();
		$status_labels  = Invoice_Status::labels();
		$recent_display = array();

		foreach ( $recent as $row ) {
			$id               = (int) $row['id'];
			$recent_display[] = array(
				'number' => (string) $row['invoice_number'],
				'client' => (string) ( $row['client_name'] ?? '' ),
				'status' => $status_labels[ (string) $row['status'] ] ?? (string) $row['status'],
				'total'  => Money::format( (int) $row['total_minor'], (string) $row['currency'] ),
				'url'    => $this->page_url(
					Invoices_Screen::SLUG,
					array(
						'action'  => 'edit',
						'invoice' => $id,
					)
				),
			);
		}

		$quick = array();
		if ( current_user_can( Capabilities::MANAGE_CLIENTS ) ) {
			$quick[] = array(
				'label' => __( 'Add client', 'wp-bizwit' ),
				'url'   => $urls['new_client'],
			);
		}
		if ( current_user_can( Capabilities::MANAGE_INVOICES ) ) {
			$quick[] = array(
				'label' => __( 'Add invoice', 'wp-bizwit' ),
				'url'   => $urls['new_invoice'],
			);
			$quick[] = array(
				'label' => __( 'Record payment', 'wp-bizwit' ),
				'url'   => $this->page_url( Payments_Screen::SLUG, array( 'action' => 'new' ) ),
			);
		}
		if ( current_user_can( Capabilities::MANAGE_PROJECTS ) ) {
			$quick[] = array(
				'label' => __( 'Add project', 'wp-bizwit' ),
				'url'   => $this->page_url( Projects_Screen::SLUG, array( 'action' => 'new' ) ),
			);
		}

		$this->view(
			'dashboard',
			array(
				'tiles'           => $tiles,
				'clients_url'     => $this->page_url( Clients_Screen::SLUG ),
				'new_client_url'  => $urls['new_client'],
				'settings_url'    => $urls['settings'],
				'invoices_url'    => $this->page_url( Invoices_Screen::SLUG ),
				'new_invoice_url' => $urls['new_invoice'],
				'can_clients'     => current_user_can( Capabilities::MANAGE_CLIENTS ),
				'can_settings'    => current_user_can( Capabilities::MANAGE_SETTINGS ),
				'can_invoices'    => current_user_can( Capabilities::MANAGE_INVOICES ),
				'show_checklist'  => $show_checklist,
				'checklist_steps' => $checklist_steps,
				'checklist_left'  => Onboarding::incomplete_count( $checklist_steps ),
				'dismiss_url'     => $dismiss_url,
				'ageing'          => $ageing,
				'ageing_currency' => $currency,
				'recent_invoices' => $recent_display,
				'quick_actions'   => $quick,
			)
		);
	}
}
