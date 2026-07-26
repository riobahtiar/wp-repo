<?php
/**
 * Money conversion and formatting helpers.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Support;

/**
 * Converts between human-entered amounts and integer minor units.
 *
 * Every amount stored by this plugin is an integer in the currency's minor unit
 * (cents for USD, yen for JPY, and so on). This class is the only place allowed
 * to cross that boundary, so rounding happens exactly once - at the edge - and
 * never in the middle of a calculation.
 */
class Money {

	/**
	 * Currencies with no minor unit, where 1 major unit is the smallest amount.
	 *
	 * @var string[]
	 */
	private const ZERO_DECIMAL_CURRENCIES = array(
		'BIF',
		'CLP',
		'DJF',
		'GNF',
		'JPY',
		'KMF',
		'KRW',
		'MGA',
		'PYG',
		'RWF',
		'UGX',
		'VND',
		'VUV',
		'XAF',
		'XOF',
		'XPF',
	);

	/**
	 * Display symbols for the currencies most likely to be selected.
	 *
	 * Falls back to the ISO code, which is always a correct thing to show.
	 *
	 * @var array<string, string>
	 */
	private const SYMBOLS = array(
		'AUD' => 'A$',
		'CAD' => 'C$',
		'CHF' => 'CHF',
		'CNY' => '¥',
		'EUR' => '€',
		'GBP' => '£',
		'IDR' => 'Rp',
		'INR' => '₹',
		'JPY' => '¥',
		'MYR' => 'RM',
		'NZD' => 'NZ$',
		'PHP' => '₱',
		'SGD' => 'S$',
		'THB' => '฿',
		'USD' => '$',
		'VND' => '₫',
	);

	/**
	 * Number of decimal places used by a currency.
	 *
	 * @param string $currency ISO 4217 currency code.
	 *
	 * @return int Decimal places, 0 or 2.
	 */
	public static function decimals( string $currency ): int {
		return in_array( strtoupper( $currency ), self::ZERO_DECIMAL_CURRENCIES, true ) ? 0 : 2;
	}

	/**
	 * Convert a user-entered amount into integer minor units.
	 *
	 * Accepts both `1,234.56` and `1.234,56` by treating whichever separator
	 * appears last as the decimal point, which is how a human reading the string
	 * would disambiguate it too.
	 *
	 * @param string|float|int $amount   Raw amount, typically straight from a form field.
	 * @param string           $currency ISO 4217 currency code.
	 *
	 * @return int Amount in minor units.
	 */
	public static function to_minor( $amount, string $currency = 'USD' ): int {
		if ( is_float( $amount ) || is_int( $amount ) ) {
			$normalized = (string) $amount;
		} else {
			$normalized = self::normalize_decimal_string( $amount );
		}

		if ( '' === $normalized ) {
			return 0;
		}

		$factor = 10 ** self::decimals( $currency );

		return (int) round( (float) $normalized * $factor );
	}

	/**
	 * Convert integer minor units into a plain decimal string.
	 *
	 * Returns an unformatted string suitable for a form input value, not for
	 * display. Use format() for display.
	 *
	 * @param int    $minor    Amount in minor units.
	 * @param string $currency ISO 4217 currency code.
	 *
	 * @return string Decimal representation, e.g. '1234.56'.
	 */
	public static function to_decimal( int $minor, string $currency = 'USD' ): string {
		$decimals = self::decimals( $currency );

		if ( 0 === $decimals ) {
			return (string) $minor;
		}

		return number_format( $minor / ( 10 ** $decimals ), $decimals, '.', '' );
	}

	/**
	 * Format an amount for display, including its currency symbol.
	 *
	 * @param int    $minor    Amount in minor units.
	 * @param string $currency ISO 4217 currency code.
	 *
	 * @return string Formatted amount, e.g. '$1,234.56'.
	 */
	public static function format( int $minor, string $currency = 'USD' ): string {
		$currency = strtoupper( $currency );
		$decimals = self::decimals( $currency );
		$symbol   = self::SYMBOLS[ $currency ] ?? $currency . ' ';

		$value = 0 === $decimals
			? number_format_i18n( $minor )
			: number_format_i18n( $minor / ( 10 ** $decimals ), $decimals );

		if ( $minor < 0 ) {
			return '-' . $symbol . ltrim( $value, '-' );
		}

		return $symbol . $value;
	}

	/**
	 * Currency codes offered in settings dropdowns.
	 *
	 * @return string[] Sorted list of ISO 4217 codes.
	 */
	public static function currencies(): array {
		/**
		 * Filters the list of selectable currency codes.
		 *
		 * @param string[] $currencies List of ISO 4217 codes.
		 */
		$currencies = apply_filters(
			'wp_bizwit_currencies',
			array( 'AUD', 'CAD', 'CHF', 'CNY', 'EUR', 'GBP', 'IDR', 'INR', 'JPY', 'MYR', 'NZD', 'PHP', 'SGD', 'THB', 'USD', 'VND' )
		);

		return array_values( array_unique( array_map( 'strval', (array) $currencies ) ) );
	}

	/**
	 * Reduce a messy user-entered string to a parseable decimal number.
	 *
	 * @param string $amount Raw input.
	 *
	 * @return string Normalized decimal string, or '' when nothing usable remains.
	 */
	private static function normalize_decimal_string( string $amount ): string {
		$amount = trim( $amount );

		// Keep only characters that can meaningfully appear in a number.
		$amount = (string) preg_replace( '/[^0-9,.\-]/', '', $amount );

		if ( '' === $amount ) {
			return '';
		}

		$negative = str_starts_with( $amount, '-' );
		$amount   = str_replace( '-', '', $amount );

		$dots   = substr_count( $amount, '.' );
		$commas = substr_count( $amount, ',' );

		if ( $dots > 0 && $commas > 0 ) {
			// Both characters present, so the rightmost one is the decimal point
			// and the other is grouping. `1.234,56` and `1,234.56` both work.
			$amount = self::split_at( $amount, (int) max( strrpos( $amount, '.' ), strrpos( $amount, ',' ) ) );
		} elseif ( $dots > 1 ) {
			// `1.500.000` - repeated dots can only be thousands grouping.
			$amount = str_replace( '.', '', $amount );
		} elseif ( $commas > 1 ) {
			// `1,500,000` - repeated commas can only be thousands grouping.
			$amount = str_replace( ',', '', $amount );
		} elseif ( 1 === $commas ) {
			// A lone comma is ambiguous: `1,234` reads as 1234 to most of the
			// world and as 1.234 to the rest. A trailing group of exactly three
			// digits is far more often grouping, so treat it that way and treat
			// anything else (`1,5` / `1,50`) as a decimal point.
			$amount = 3 === strlen( substr( $amount, (int) strrpos( $amount, ',' ) + 1 ) )
				? str_replace( ',', '', $amount )
				: self::split_at( $amount, (int) strrpos( $amount, ',' ) );
		}

		// A lone dot, or no separator at all, is already a valid decimal string.
		return $negative ? '-' . $amount : $amount;
	}

	/**
	 * Rebuild a number treating one position as its decimal point.
	 *
	 * @param string $amount   Digits with separators.
	 * @param int    $position Offset of the decimal separator.
	 *
	 * @return string Plain decimal string.
	 */
	private static function split_at( string $amount, int $position ): string {
		$integer  = (string) preg_replace( '/[^0-9]/', '', substr( $amount, 0, $position ) );
		$fraction = (string) preg_replace( '/[^0-9]/', '', substr( $amount, $position + 1 ) );

		if ( '' === $integer ) {
			$integer = '0';
		}

		return '' === $fraction ? $integer : $integer . '.' . $fraction;
	}
}
