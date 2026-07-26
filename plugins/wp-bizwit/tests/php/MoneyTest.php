<?php
/**
 * Tests for the money conversion helpers.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Localization\Regions;
use WP_BizWit\Support\Money;
use WP_BizWit\Support\Settings;

/**
 * Locks in how user-entered amounts are parsed into integer minor units.
 *
 * Amount parsing is the single most dangerous piece of arithmetic in a billing
 * plugin: a separator misread by one position is an invoice wrong by a factor of
 * a hundred, and nothing downstream will catch it. These cases exist so that
 * behaviour cannot regress silently.
 */
class MoneyTest extends WP_UnitTestCase {

	/**
	 * Start each test from a known regional profile.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( Settings::OPTION );
		Regions::reset();
	}

	/**
	 * Amounts that must parse to a known number of minor units.
	 *
	 * @return array<string, array{0: string, 1: string, 2: int}> Case name mapped to input, currency and expected minor units.
	 */
	public function amount_provider(): array {
		return array(
			'plain integer'              => array( '99', 'USD', 9900 ),
			'plain decimal'              => array( '1234.5', 'USD', 123450 ),
			'us grouped with decimal'    => array( '1,234.56', 'USD', 123456 ),
			'eu grouped with decimal'    => array( '1.234,56', 'USD', 123456 ),
			'lone comma as decimal'      => array( '1,50', 'USD', 150 ),
			'lone comma as grouping'     => array( '1,234', 'USD', 123400 ),
			'repeated dots as grouping'  => array( '1.500.000', 'IDR', 1500000 ),
			'repeated commas as group'   => array( '1,500,000', 'USD', 150000000 ),
			'currency symbol is ignored' => array( 'Rp 2.750.000', 'IDR', 2750000 ),
			'dollar sign is ignored'     => array( '$1,234.56', 'USD', 123456 ),
			'negative amount'            => array( '-45.10', 'USD', -4510 ),
			'empty string'               => array( '', 'USD', 0 ),
			'rounds to nearest unit'     => array( '0.005', 'USD', 1 ),
			'zero decimal currency'      => array( '1500', 'JPY', 1500 ),
			'zero decimal with grouping' => array( '1,500', 'JPY', 1500 ),
		);
	}

	/**
	 * User-entered amounts convert to the expected minor units.
	 *
	 * @dataProvider amount_provider
	 *
	 * @param string $input    Raw amount as typed.
	 * @param string $currency ISO 4217 currency code.
	 * @param int    $expected Expected amount in minor units.
	 */
	public function test_to_minor_parses_amounts( string $input, string $currency, int $expected ): void {
		$this->assertSame( $expected, Money::to_minor( $input, $currency ) );
	}

	/**
	 * Converting to minor units and back is lossless.
	 */
	public function test_minor_units_round_trip(): void {
		$minor = Money::to_minor( '1.234,56', 'USD' );

		$this->assertSame( '1234.56', Money::to_decimal( $minor, 'USD' ) );
		$this->assertSame( $minor, Money::to_minor( Money::to_decimal( $minor, 'USD' ), 'USD' ) );
	}

	/**
	 * Form inputs show region-grouped amounts for IDR (not raw 1000000).
	 */
	public function test_to_input_groups_idr_for_forms(): void {
		update_option(
			Settings::OPTION,
			array(
				'region'   => 'id',
				'currency' => 'IDR',
			)
		);
		Regions::reset();

		$this->assertSame( '', Money::to_input( 0, 'IDR' ) );
		$this->assertSame( '1.500.000', Money::to_input( 1500000, 'IDR' ) );
		$this->assertSame( 1500000, Money::to_minor( Money::to_input( 1500000, 'IDR' ), 'IDR' ) );
	}

	/**
	 * Currencies without a minor unit are not divided by one hundred.
	 *
	 * IDR is treated as zero-decimal deliberately: sen has not circulated for
	 * decades, and storing whole rupiah keeps stored values the same magnitude
	 * as the figures on an Indonesian invoice.
	 */
	public function test_zero_decimal_currencies_have_no_fraction(): void {
		$this->assertSame( 0, Money::decimals( 'JPY' ) );
		$this->assertSame( 0, Money::decimals( 'IDR' ) );
		$this->assertSame( 2, Money::decimals( 'USD' ) );
		$this->assertSame( '1500', Money::to_decimal( 1500, 'JPY' ) );
		$this->assertSame( '1500000', Money::to_decimal( 1500000, 'IDR' ) );
	}

	/**
	 * Rupiah is displayed with Indonesian separators and no decimals.
	 */
	public function test_rupiah_uses_indonesian_separators(): void {
		Settings::save( array( 'region' => 'id' ) );

		$this->assertSame( 'Rp 1.500.000', Money::format( 1500000, 'IDR' ) );
		$this->assertSame( '-Rp 250.000', Money::format( -250000, 'IDR' ) );
	}

	/**
	 * Negative amounts keep the sign outside the currency symbol.
	 */
	public function test_format_places_sign_before_symbol(): void {
		$this->assertStringStartsWith( '-$', Money::format( -4510, 'USD' ) );
		$this->assertStringStartsWith( '$', Money::format( 4510, 'USD' ) );
	}

	/**
	 * Unknown currencies fall back to showing their ISO code.
	 */
	public function test_unknown_currency_falls_back_to_code(): void {
		$this->assertStringContainsString( 'ZWL', Money::format( 100, 'ZWL' ) );
	}
}
