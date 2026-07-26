<?php
/**
 * Invoice line and header arithmetic.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Support;

/**
 * Computes invoice totals from line items.
 *
 * Ordering (documented, tested, do not change lightly):
 * 1. Each line base = quantity × unit_price_minor (half-up once).
 * 2. Each line tax  = percent_of( line base, tax_rate ) when sales tax is on.
 * 3. Subtotal       = sum of line bases.
 * 4. Header discount applied to subtotal (capped at subtotal).
 * 5. Tax is scaled by the remaining pretax share when a discount applies, so
 *    a 10% discount cuts both pretax and tax proportionally.
 * 6. Total          = subtotal − discount + tax.
 * 7. Withholding    = percent_of( total, withholding_rate ) when requested.
 * 8. Net expected   = total − withholding (what should arrive in the bank).
 *
 * Posted totals from the client are never trusted — always recompute here.
 */
class Invoice_Totals {

	/**
	 * Compute every money field for an invoice from its lines and options.
	 *
	 * @param array<int, array<string, mixed>> $lines {
	 *     Line rows (description is ignored for maths).
	 *
	 *     @type string|float|int $quantity
	 *     @type int              $unit_price_minor
	 *     @type string|float|int $tax_rate Percentage.
	 * }
	 * @param array<string, mixed>             $options {
	 *     Optional.
	 *
	 *     @type int              $discount_minor     Header discount in minor units.
	 *     @type bool             $charges_sales_tax  When false, tax rates are forced to 0.
	 *     @type string|float|int $withholding_rate    Percentage of total withheld (PPh 23).
	 *     @type bool             $apply_withholding   When false, withholding is 0.
	 * }
	 *
	 * @return array{
	 *     lines: array<int, array{line_total_minor: int, tax_minor: int, tax_rate: string}>,
	 *     subtotal_minor: int,
	 *     discount_minor: int,
	 *     tax_minor: int,
	 *     total_minor: int,
	 *     withholding_minor: int,
	 *     withholding_rate: string,
	 *     net_expected_minor: int
	 * }
	 */
	public static function calculate( array $lines, array $options = array() ): array {
		$charges_tax       = ! empty( $options['charges_sales_tax'] );
		$apply_withholding = ! empty( $options['apply_withholding'] );
		$discount_minor    = max( 0, (int) ( $options['discount_minor'] ?? 0 ) );
		$withholding_rate  = $apply_withholding
			? self::normalize_rate( $options['withholding_rate'] ?? '0' )
			: '0.0000';

		$computed_lines = array();
		$subtotal       = 0;
		$tax_raw        = 0;

		foreach ( $lines as $line ) {
			$qty        = $line['quantity'] ?? '1';
			$unit_price = (int) ( $line['unit_price_minor'] ?? 0 );
			$rate       = $charges_tax
				? self::normalize_rate( $line['tax_rate'] ?? '0' )
				: '0.0000';

			$base = Money::line_base_minor( $qty, $unit_price );
			$tax  = Money::percent_of( $base, $rate );

			$computed_lines[] = array(
				'line_total_minor' => $base,
				'tax_minor'        => $tax,
				'tax_rate'         => $rate,
			);

			$subtotal += $base;
			$tax_raw  += $tax;
		}

		if ( $discount_minor > $subtotal ) {
			$discount_minor = $subtotal;
		}

		// Scale tax with pretax remaining after discount so line rates still apply.
		$tax_minor = $tax_raw;
		if ( $subtotal > 0 && $discount_minor > 0 && $tax_raw > 0 ) {
			$remaining = $subtotal - $discount_minor;
			$tax_minor = (int) intdiv( ( $tax_raw * $remaining ) + intdiv( $subtotal, 2 ), $subtotal );
		}

		$total_minor = $subtotal - $discount_minor + $tax_minor;

		$withholding_minor = $apply_withholding
			? Money::percent_of( $total_minor, $withholding_rate )
			: 0;

		return array(
			'lines'              => $computed_lines,
			'subtotal_minor'     => $subtotal,
			'discount_minor'     => $discount_minor,
			'tax_minor'          => $tax_minor,
			'total_minor'        => $total_minor,
			'withholding_minor'  => $withholding_minor,
			'withholding_rate'   => $withholding_rate,
			'net_expected_minor' => $total_minor - $withholding_minor,
		);
	}

	/**
	 * Outstanding balance: total − paid (never negative).
	 *
	 * @param int $total_minor Invoice total in minor units.
	 * @param int $paid_minor  Amount already paid in minor units.
	 *
	 * @return int Balance in minor units.
	 */
	public static function balance_minor( int $total_minor, int $paid_minor ): int {
		return max( 0, $total_minor - max( 0, $paid_minor ) );
	}

	/**
	 * Normalise a percentage string to four decimal places for storage.
	 *
	 * @param string|float|int $rate Raw rate.
	 *
	 * @return string e.g. "11.0000".
	 */
	public static function normalize_rate( $rate ): string {
		$scaled = Money::rate_to_scaled( $rate );

		return Money::quantity_from_scaled( max( 0, $scaled ) );
	}
}
