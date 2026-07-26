<?php
/**
 * Settings admin screen.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

use WP_BizWit\Admin\Notices;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

/**
 * Business identity, currency and document numbering settings.
 */
class Settings_Screen extends Screen {

	/**
	 * Menu slug for this screen.
	 *
	 * @var string
	 */
	public const SLUG = 'wp-bizwit-settings';

	/**
	 * Nonce action used by the settings form.
	 *
	 * @var string
	 */
	private const FORM_NONCE = 'wp_bizwit_save_settings';

	/**
	 * Capability required to use this screen.
	 *
	 * @return string Capability name.
	 */
	public function capability(): string {
		return Capabilities::MANAGE_SETTINGS;
	}

	/**
	 * Persist the settings form before output starts.
	 *
	 * @return void
	 */
	public function on_load(): void {
		if ( ! isset( $_POST['wp_bizwit_settings_form'] ) || ! current_user_can( $this->capability() ) ) {
			return;
		}

		check_admin_referer( self::FORM_NONCE );

		$currency = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ?? '' ) ) );
		if ( ! in_array( $currency, Money::currencies(), true ) ) {
			$currency = Settings::currency();
		}

		Settings::update(
			array(
				'business_name'            => sanitize_text_field( wp_unslash( $_POST['business_name'] ?? '' ) ),
				'business_email'           => sanitize_email( wp_unslash( $_POST['business_email'] ?? '' ) ),
				'business_phone'           => sanitize_text_field( wp_unslash( $_POST['business_phone'] ?? '' ) ),
				'business_address'         => sanitize_textarea_field( wp_unslash( $_POST['business_address'] ?? '' ) ),
				'tax_id'                   => sanitize_text_field( wp_unslash( $_POST['tax_id'] ?? '' ) ),
				'currency'                 => $currency,
				'tax_label'                => sanitize_text_field( wp_unslash( $_POST['tax_label'] ?? '' ) ),
				'default_tax_rate'         => sanitize_text_field( wp_unslash( $_POST['default_tax_rate'] ?? '0' ) ),
				'payment_terms_days'       => max( 0, min( 3650, absint( $_POST['payment_terms_days'] ?? 30 ) ) ),
				'invoice_prefix'           => sanitize_text_field( wp_unslash( $_POST['invoice_prefix'] ?? '' ) ),
				'receipt_prefix'           => sanitize_text_field( wp_unslash( $_POST['receipt_prefix'] ?? '' ) ),
				'number_padding'           => max( 1, min( 12, absint( $_POST['number_padding'] ?? 4 ) ) ),
				'delete_data_on_uninstall' => isset( $_POST['delete_data_on_uninstall'] ),
			)
		);

		Notices::add( __( 'Settings saved.', 'wp-bizwit' ), 'success' );

		wp_safe_redirect( $this->page_url( self::SLUG ) );
		exit;
	}

	/**
	 * Render the settings form.
	 *
	 * @return void
	 */
	protected function render(): void {
		$this->view(
			'settings',
			array(
				'settings'    => Settings::all(),
				'currencies'  => Money::currencies(),
				'nonce_field' => self::FORM_NONCE,
			)
		);
	}
}
