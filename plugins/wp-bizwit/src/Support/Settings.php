<?php
/**
 * Plugin settings access.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Support;

use WP_BizWit\Localization\Generic_Region;
use WP_BizWit\Localization\Indonesia;
use WP_BizWit\Localization\Regions;

/**
 * Reads and writes the single options row holding all plugin settings.
 *
 * One serialized option beats a dozen separate ones here: settings are always
 * read together, and a single autoloaded row means one query instead of many.
 */
class Settings {

	/**
	 * Option name holding the settings array.
	 *
	 * @var string
	 */
	public const OPTION = 'wp_bizwit_settings';

	/**
	 * Default values for every known setting.
	 *
	 * @return array<string, mixed> Default settings.
	 */
	public static function defaults(): array {
		return array(
			// Business identity.
			'business_name'            => (string) get_bloginfo( 'name' ),
			'business_email'           => (string) get_option( 'admin_email', '' ),
			'business_phone'           => '',
			'business_address'         => '',
			'business_country'         => 'ID',
			'business_code'            => '',
			'tax_id'                   => '',
			'business_reg_no'          => '',

			// Regional profile. 'auto' resolves to Indonesia unless the business
			// details say otherwise - see Localization\Regions.
			'region'                   => 'auto',
			'business_scale'           => 'kecil',
			'tax_regime'               => 'umkm_final',

			// Money. Defaults target an Indonesian UMKM.
			'currency'                 => 'IDR',
			'tax_label'                => 'PPN',
			'default_tax_rate'         => '11',
			'withholding_label'        => 'PPh 23',
			'withholding_rate'         => '2',
			'payment_terms_days'       => 30,

			// Bank details. Indonesian invoices are expected to carry them.
			'bank_name'                => '',
			'bank_account_no'          => '',
			'bank_account_name'        => '',
			'bank_branch'              => '',

			// Document numbering.
			'number_format'            => 'regional',
			'invoice_prefix'           => 'INV-',
			'receipt_prefix'           => 'KW-',
			'number_padding'           => 3,
			'apply_stamp_duty'         => true,

			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * All settings, with defaults filled in for anything unset.
	 *
	 * @return array<string, mixed> Settings.
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Read a single setting.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default_value Value returned when the setting is unknown.
	 *
	 * @return mixed Setting value.
	 */
	public static function get( string $key, $default_value = null ) {
		$settings = self::all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default_value;
	}

	/**
	 * Persist a partial settings update.
	 *
	 * @param array<string, mixed> $values Settings to merge over the stored ones.
	 *
	 * @return void
	 */
	public static function update( array $values ): void {
		update_option( self::OPTION, array_merge( self::all(), $values ) );
	}

	/**
	 * The default currency for new records.
	 *
	 * @return string ISO 4217 currency code.
	 */
	public static function currency(): string {
		$currency = self::get( 'currency', 'IDR' );

		return is_string( $currency ) && 3 === strlen( $currency ) ? strtoupper( $currency ) : 'IDR';
	}

	/**
	 * Persist settings and drop any cached derived state.
	 *
	 * @param array<string, mixed> $values Settings to merge over the stored ones.
	 *
	 * @return void
	 */
	public static function save( array $values ): void {
		self::update( $values );

		// The active region is derived from these values, so a stale cached
		// instance would keep serving the previous profile for this request.
		Regions::reset();
	}

	/**
	 * Whether the business charges sales tax on its invoices.
	 *
	 * Only a PKP may charge PPN. A business on the UMKM final regime pays 0.5%
	 * of its own turnover instead; that is a tax on the seller, not a line the
	 * client is billed, so it must never be added to an invoice total.
	 *
	 * @return bool True when invoices should carry a tax line.
	 */
	public static function charges_sales_tax(): bool {
		return Indonesia::REGIME_PKP === (string) self::get( 'tax_regime', Indonesia::REGIME_UMKM_FINAL );
	}

	/**
	 * The tax rate to apply to new invoice lines, as a percentage string.
	 *
	 * Returns '0' when the business is not entitled to charge tax.
	 *
	 * @return string Percentage rate.
	 */
	public static function effective_tax_rate(): string {
		return self::charges_sales_tax() ? (string) self::get( 'default_tax_rate', '11' ) : '0';
	}

	/**
	 * Build a document number for an allocated sequence value.
	 *
	 * @param string $type     Document type key, 'invoice' or 'receipt'.
	 * @param int    $number   Allocated sequence value.
	 * @param string $date     Document date in Y-m-d form.
	 *
	 * @return string Formatted document number.
	 */
	public static function document_number( string $type, int $number, string $date = '' ): string {
		if ( '' === $date ) {
			$date = (string) current_time( 'Y-m-d' );
		}

		// 'simple' forces the plain prefixed format even inside a region that
		// would otherwise use its own house style.
		if ( 'simple' === (string) self::get( 'number_format', 'regional' ) ) {
			return ( new Generic_Region() )->document_number( $type, $number, $date );
		}

		return Regions::current()->document_number( $type, $number, $date );
	}
}
