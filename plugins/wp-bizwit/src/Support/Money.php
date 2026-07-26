<?php
/**
 * Money conversion and formatting helpers.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Support;

use WP_BizWit\Localization\Regions;

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
		// ISO 4217 formally gives IDR two decimals (sen), but sen has not
		// circulated for decades and no Indonesian invoice, kwitansi or bank
		// statement shows them. Storing whole rupiah keeps stored values the
		// same magnitude as the numbers people actually type and read.
		'IDR',
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
		'IDR' => 'Rp ',
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
	 * display. Use format() for display, or to_input() for locale-friendly fields.
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
	 * Format minor units for a form input using the active region's separators.
	 *
	 * Indonesian users type and read `1.000.000`, not `1000000`. to_minor()
	 * already accepts multi-separator grouping, so this is the round-trip pair.
	 * Returns empty string for zero so placeholders stay visible.
	 *
	 * @param int    $minor    Amount in minor units.
	 * @param string $currency ISO 4217 currency code.
	 *
	 * @return string Grouped amount without currency symbol, or ''.
	 */
	public static function to_input( int $minor, string $currency = 'USD' ): string {
		if ( 0 === $minor ) {
			return '';
		}

		$currency = strtoupper( $currency );
		$decimals = self::decimals( $currency );
		$value    = 0 === $decimals ? (float) $minor : $minor / ( 10 ** $decimals );

		return Regions::current()->format_number( $value, $decimals );
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

		// Grouping and decimal characters come from the active region, not the
		// site locale. A rupiah figure rendered "1,500,000" reads as one and a
		// half to an Indonesian reader, whatever language wp-admin is in.
		$value = Regions::current()->format_number(
			0 === $decimals ? (float) $minor : $minor / ( 10 ** $decimals ),
			$decimals
		);

		if ( $minor < 0 ) {
			return '-' . $symbol . ltrim( $value, '-' );
		}

		return $symbol . $value;
	}

	/**
	 * Express an amount in words, where the region has such a convention.
	 *
	 * @param int    $minor    Amount in minor units.
	 * @param string $currency ISO 4217 currency code.
	 *
	 * @return string Amount written out, or '' when not applicable.
	 */
	public static function in_words( int $minor, string $currency = 'USD' ): string {
		return Regions::current()->amount_in_words( $minor, strtoupper( $currency ) );
	}

	/**
	 * Multiply a DECIMAL quantity by an integer unit price in minor units.
	 *
	 * Quantity is treated as fixed-point with four decimal places (matching
	 * `DECIMAL(14,4)` on line items). Rounding to the nearest minor unit
	 * happens once, half-up. Never compute this with bare float multiplication.
	 *
	 * @param string|float|int $quantity         Line quantity.
	 * @param int              $unit_price_minor Unit price in minor units.
	 *
	 * @return int Line base amount in minor units (ex tax).
	 */
	public static function line_base_minor( $quantity, int $unit_price_minor ): int {
		$scaled = self::quantity_to_scaled( $quantity );

		if ( 0 === $scaled || 0 === $unit_price_minor ) {
			return 0;
		}

		// half-up: add half the divisor before integer division.
		$product = $scaled * $unit_price_minor;
		$sign    = $product < 0 ? -1 : 1;
		$abs     = abs( $product );

		return $sign * (int) intdiv( $abs + 5000, 10000 );
	}

	/**
	 * Apply a percentage rate to a minor-unit amount (half-up).
	 *
	 * @param int              $amount_minor Base amount in minor units.
	 * @param string|float|int $rate_percent Percentage, e.g. 11 or "2.5".
	 *
	 * @return int Resulting amount in minor units.
	 */
	public static function percent_of( int $amount_minor, $rate_percent ): int {
		$rate_scaled = self::rate_to_scaled( $rate_percent );

		if ( 0 === $amount_minor || 0 === $rate_scaled ) {
			return 0;
		}

		// rate_scaled is percent × 10000 (four decimals), so / 1_000_000 for %.
		$product = $amount_minor * $rate_scaled;
		$sign    = $product < 0 ? -1 : 1;
		$abs     = abs( $product );

		return $sign * (int) intdiv( $abs + 500000, 1000000 );
	}

	/**
	 * Parse a quantity into a 4-decimal fixed-point integer (value × 10000).
	 *
	 * @param string|float|int $quantity Raw quantity.
	 *
	 * @return int Scaled quantity.
	 */
	public static function quantity_to_scaled( $quantity ): int {
		if ( is_int( $quantity ) ) {
			return $quantity * 10000;
		}

		if ( is_float( $quantity ) ) {
			return (int) round( $quantity * 10000 );
		}

		$normalized = self::normalize_decimal_string( (string) $quantity );

		if ( '' === $normalized ) {
			return 0;
		}

		$negative   = str_starts_with( $normalized, '-' );
		$normalized = ltrim( $normalized, '-' );
		$parts      = explode( '.', $normalized, 2 );
		$whole      = (int) $parts[0];
		$frac       = isset( $parts[1] ) ? substr( $parts[1] . '0000', 0, 4 ) : '0000';
		// Round fifth decimal digit into the fourth when present.
		if ( isset( $parts[1] ) && strlen( $parts[1] ) > 4 ) {
			$fifth  = (int) $parts[1][4];
			$frac_i = (int) $frac + ( $fifth >= 5 ? 1 : 0 );
			if ( $frac_i >= 10000 ) {
				++$whole;
				$frac_i = 0;
			}
			$frac = str_pad( (string) $frac_i, 4, '0', STR_PAD_LEFT );
		}

		$scaled = $whole * 10000 + (int) $frac;

		return $negative ? -$scaled : $scaled;
	}

	/**
	 * Parse a percentage into a 4-decimal fixed-point integer (rate × 10000).
	 *
	 * "11" → 110000, "2.5" → 25000.
	 *
	 * @param string|float|int $rate Percentage.
	 *
	 * @return int Scaled rate.
	 */
	public static function rate_to_scaled( $rate ): int {
		return self::quantity_to_scaled( $rate );
	}

	/**
	 * Format a scaled quantity (×10000) back to a plain decimal string.
	 *
	 * @param int $scaled Quantity × 10000.
	 *
	 * @return string e.g. "1.5000".
	 */
	public static function quantity_from_scaled( int $scaled ): string {
		$negative = $scaled < 0;
		$scaled   = abs( $scaled );
		$whole    = intdiv( $scaled, 10000 );
		$frac     = str_pad( (string) ( $scaled % 10000 ), 4, '0', STR_PAD_LEFT );
		$value    = $whole . '.' . $frac;

		return $negative ? '-' . $value : $value;
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
			// IDR leads the list because this plugin's primary users bill in it.
			array( 'IDR', 'USD', 'SGD', 'MYR', 'AUD', 'EUR', 'GBP', 'JPY', 'CNY', 'INR', 'PHP', 'THB', 'VND', 'CAD', 'CHF', 'NZD' )
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
