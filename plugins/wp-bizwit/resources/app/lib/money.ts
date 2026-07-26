/**
 * Client-side money display helpers.
 *
 * Mirrors the edge rules in PHP `Support\Money` for interactive UI only.
 * Authoritative formatting for printable documents stays on the server.
 *
 * Money is always an integer in the currency's minor unit (`amountMinor`).
 * IDR is zero-decimal here (whole rupiah), matching PHP — not ISO 4217's two
 * "sen" decimals.
 */

/** Currencies with no fractional minor unit (1 major unit = 1 minor unit). */
const ZERO_DECIMAL_CURRENCIES = new Set( [
	'BIF',
	'CLP',
	'DJF',
	'GNF',
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
] );

/**
 * Group an integer digit string with a thousands separator.
 */
function groupDigits( digits: string, separator: string ): string {
	const negative = digits.startsWith( '-' );
	const body = negative ? digits.slice( 1 ) : digits;
	const grouped = body.replace( /\B(?=(\d{3})+(?!\d))/g, separator );
	return negative ? `-${ grouped }` : grouped;
}

/**
 * Format an amount in minor units for display.
 *
 * @param amountMinor - Integer minor units (cents, whole rupiah, etc.).
 * @param currency    - ISO 4217 code (case-insensitive).
 * @returns Formatted string, e.g. `Rp 1.500.000` for IDR.
 */
export function formatMoney( amountMinor: number, currency: string ): string {
	const code = currency.toUpperCase();
	const negative = amountMinor < 0;
	const abs = Math.abs( Math.trunc( amountMinor ) );
	const sign = negative ? '-' : '';

	// Indonesian rupiah: zero-decimal, Rp prefix, dot thousands grouping.
	// Never use comma thousands or fractional ".00" — those misread for IDR.
	if ( code === 'IDR' ) {
		return `${ sign }Rp ${ groupDigits( String( abs ), '.' ) }`;
	}

	const zeroDecimal = ZERO_DECIMAL_CURRENCIES.has( code );

	if ( zeroDecimal ) {
		// Other zero-decimal: ISO code + comma grouping (generic fallback).
		return `${ sign }${ code } ${ groupDigits( String( abs ), ',' ) }`;
	}

	// Two-decimal: minor units ÷ 100, ISO code, comma thousands, dot decimals.
	const whole = Math.floor( abs / 100 );
	const fraction = abs % 100;
	const major =
		groupDigits( String( whole ), ',' ) +
		'.' +
		String( fraction ).padStart( 2, '0' );

	return `${ sign }${ code } ${ major }`;
}
