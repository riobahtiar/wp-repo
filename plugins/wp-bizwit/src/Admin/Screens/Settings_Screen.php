<?php
/**
 * Settings admin screen.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin\Screens;

use WP_BizWit\Admin\Notices;
use WP_BizWit\Localization\Indonesia;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Payment_Destinations;
use WP_BizWit\Support\Settings;

/**
 * Business identity, tax regime, bank details and document numbering.
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

		Settings::save( $this->collect_input() );

		Notices::add( __( 'Settings saved.', 'wp-bizwit' ), 'success' );

		wp_safe_redirect( $this->page_url( self::SLUG ) );
		exit;
	}

	/**
	 * Sanitise the submitted settings form.
	 *
	 * @return array<string, mixed> Settings ready to persist.
	 */
	private function collect_input(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by the caller.
		$currency = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ?? '' ) ) );
		if ( ! in_array( $currency, Money::currencies(), true ) ) {
			$currency = Settings::currency();
		}

		$region = sanitize_key( wp_unslash( $_POST['region'] ?? Regions::AUTO ) );
		if ( Regions::AUTO !== $region && ! array_key_exists( $region, Regions::all() ) ) {
			$region = Regions::AUTO;
		}

		$regime = sanitize_key( wp_unslash( $_POST['tax_regime'] ?? '' ) );
		if ( ! array_key_exists( $regime, Indonesia::tax_regimes() ) ) {
			$regime = Settings::REGIME_NONE;
		}

		$scale = sanitize_key( wp_unslash( $_POST['business_scale'] ?? '' ) );
		if ( ! array_key_exists( $scale, Indonesia::business_scales() ) ) {
			$scale = 'kecil';
		}

		$number_format = sanitize_key( wp_unslash( $_POST['number_format'] ?? 'regional' ) );

		$business_type = sanitize_key( wp_unslash( $_POST['business_type'] ?? '' ) );
		if ( ! array_key_exists( $business_type, Settings::business_types() ) ) {
			$business_type = Settings::TYPE_PERSONAL;
		}

		$settings = array(
			'business_name'            => sanitize_text_field( wp_unslash( $_POST['business_name'] ?? '' ) ),
			'business_email'           => sanitize_email( wp_unslash( $_POST['business_email'] ?? '' ) ),
			'business_phone'           => sanitize_text_field( wp_unslash( $_POST['business_phone'] ?? '' ) ),
			'business_address'         => sanitize_textarea_field( wp_unslash( $_POST['business_address'] ?? '' ) ),
			'business_country'         => strtoupper( substr( sanitize_text_field( wp_unslash( $_POST['business_country'] ?? '' ) ), 0, 2 ) ),
			'business_code'            => strtoupper( substr( sanitize_text_field( wp_unslash( $_POST['business_code'] ?? '' ) ), 0, 12 ) ),
			'tax_id'                   => sanitize_text_field( wp_unslash( $_POST['tax_id'] ?? '' ) ),
			'business_reg_no'          => sanitize_text_field( wp_unslash( $_POST['business_reg_no'] ?? '' ) ),
			'region'                   => $region,
			'business_type'            => $business_type,
			'business_scale'           => $scale,
			'tax_regime'               => $regime,
			'currency'                 => $currency,
			'tax_label'                => sanitize_text_field( wp_unslash( $_POST['tax_label'] ?? '' ) ),
			'default_tax_rate'         => sanitize_text_field( wp_unslash( $_POST['default_tax_rate'] ?? '0' ) ),
			'withholding_label'        => sanitize_text_field( wp_unslash( $_POST['withholding_label'] ?? '' ) ),
			'withholding_rate'         => sanitize_text_field( wp_unslash( $_POST['withholding_rate'] ?? '0' ) ),
			'payment_terms_days'       => max( 0, min( 3650, absint( $_POST['payment_terms_days'] ?? 30 ) ) ),
			'number_format'            => 'simple' === $number_format ? 'simple' : 'regional',
			'invoice_prefix'           => sanitize_text_field( wp_unslash( $_POST['invoice_prefix'] ?? '' ) ),
			'receipt_prefix'           => sanitize_text_field( wp_unslash( $_POST['receipt_prefix'] ?? '' ) ),
			'number_padding'           => max( 1, min( 12, absint( $_POST['number_padding'] ?? 3 ) ) ),
			'apply_stamp_duty'         => isset( $_POST['apply_stamp_duty'] ),
			'delete_data_on_uninstall' => isset( $_POST['delete_data_on_uninstall'] ),
		);

		// Multi payment destinations (bank, VA, e-wallets, links, offline, …).
		$raw_dest = isset( $_POST['payment_destinations'] ) && is_array( $_POST['payment_destinations'] )
			? wp_unslash( $_POST['payment_destinations'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised by Payment_Destinations.
			: array();
		$destinations                     = Payment_Destinations::sanitize_list( $raw_dest );
		$settings['payment_destinations'] = $destinations;
		$settings                         = array_merge( $settings, Payment_Destinations::legacy_bank_fields_from( $destinations ) );
		// phpcs:enable

		// A business that is not a PKP has no right to charge PPN, so the stored
		// rate is forced to zero rather than being left as a trap for whoever
		// builds the first invoice.
		if ( Indonesia::REGIME_PKP !== $regime ) {
			$settings['default_tax_rate'] = '0';
		}

		return $settings;
	}

	/**
	 * Render the settings form.
	 *
	 * @return void
	 */
	protected function render(): void {
		$region = Regions::current();

		$this->view(
			'settings',
			array(
				'settings'       => Settings::all(),
				'currencies'     => Money::currencies(),
				'region'         => $region,
				'is_indonesia'   => $region instanceof Indonesia,
				'region_choices' => Regions::choices(),
				'business_types' => Settings::business_types(),
				'tax_regimes'    => Indonesia::tax_regimes(),
				'handles_tax'    => Settings::handles_tax(),
				'tax_summary'    => Settings::tax_summary(),
				'scales'         => Indonesia::business_scales(),
				'sample_number'  => Settings::document_number( 'invoice', 1 ),
				'nonce_field'    => self::FORM_NONCE,
			)
		);
	}
}
