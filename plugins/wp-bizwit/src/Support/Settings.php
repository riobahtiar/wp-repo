<?php
/**
 * Plugin settings access.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Support;

/**
 * Reads and writes the single options row holding all plugin settings.
 *
 * One serialized option beats a dozen separate ones here: settings are always
 * read together, and a single autoloaded row means one query instead of many.
 *
 * @phpstan-type SettingsArray array{
 *     business_name: string,
 *     business_email: string,
 *     business_phone: string,
 *     business_address: string,
 *     tax_id: string,
 *     currency: string,
 *     tax_label: string,
 *     default_tax_rate: string,
 *     payment_terms_days: int,
 *     invoice_prefix: string,
 *     receipt_prefix: string,
 *     number_padding: int,
 *     delete_data_on_uninstall: bool
 * }
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
			'business_name'            => (string) get_bloginfo( 'name' ),
			'business_email'           => (string) get_option( 'admin_email', '' ),
			'business_phone'           => '',
			'business_address'         => '',
			'tax_id'                   => '',
			'currency'                 => 'USD',
			'tax_label'                => __( 'Tax', 'wp-bizwit' ),
			'default_tax_rate'         => '0',
			'payment_terms_days'       => 30,
			'invoice_prefix'           => 'INV-',
			'receipt_prefix'           => 'RCP-',
			'number_padding'           => 4,
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
		$currency = self::get( 'currency', 'USD' );

		return is_string( $currency ) && 3 === strlen( $currency ) ? strtoupper( $currency ) : 'USD';
	}

	/**
	 * Build a formatted document number from a prefix and a sequence value.
	 *
	 * @param string $prefix Document prefix, e.g. 'INV-'.
	 * @param int    $number Allocated sequence value.
	 *
	 * @return string Formatted document number, e.g. 'INV-0007'.
	 */
	public static function format_number( string $prefix, int $number ): string {
		$padding = (int) self::get( 'number_padding', 4 );
		$padding = max( 1, min( 12, $padding ) );

		return $prefix . str_pad( (string) $number, $padding, '0', STR_PAD_LEFT );
	}
}
