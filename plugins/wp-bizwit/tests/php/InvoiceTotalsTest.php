<?php
/**
 * Tests for invoice arithmetic.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Support\Invoice_Totals;
use WP_BizWit\Support\Money;

/**
 * Totals ordering and money edge cases.
 */
class InvoiceTotalsTest extends WP_UnitTestCase {

	/**
	 * Simple single line without tax.
	 */
	public function test_single_line_no_tax(): void {
		$result = Invoice_Totals::calculate(
			array(
				array(
					'quantity'         => '2',
					'unit_price_minor' => 1_000_000,
					'tax_rate'         => '11',
				),
			),
			array( 'charges_sales_tax' => false )
		);

		$this->assertSame( 2_000_000, $result['subtotal_minor'] );
		$this->assertSame( 0, $result['tax_minor'] );
		$this->assertSame( 2_000_000, $result['total_minor'] );
		$this->assertSame( 2_000_000, $result['lines'][0]['line_total_minor'] );
	}

	/**
	 * Per-line tax when the business may charge sales tax.
	 */
	public function test_line_tax_when_pkp(): void {
		$result = Invoice_Totals::calculate(
			array(
				array(
					'quantity'         => '1',
					'unit_price_minor' => 1_000_000,
					'tax_rate'         => '11',
				),
			),
			array( 'charges_sales_tax' => true )
		);

		$this->assertSame( 1_000_000, $result['subtotal_minor'] );
		$this->assertSame( 110_000, $result['tax_minor'] );
		$this->assertSame( 1_110_000, $result['total_minor'] );
	}

	/**
	 * Header discount scales tax proportionally.
	 */
	public function test_discount_scales_tax(): void {
		$result = Invoice_Totals::calculate(
			array(
				array(
					'quantity'         => '1',
					'unit_price_minor' => 1_000_000,
					'tax_rate'         => '10',
				),
			),
			array(
				'charges_sales_tax' => true,
				'discount_minor'    => 100_000,
			)
		);

		// Pretax after discount 900_000; tax was 100_000, scaled to 90_000.
		$this->assertSame( 1_000_000, $result['subtotal_minor'] );
		$this->assertSame( 100_000, $result['discount_minor'] );
		$this->assertSame( 90_000, $result['tax_minor'] );
		$this->assertSame( 990_000, $result['total_minor'] );
	}

	/**
	 * Posted totals must not be used — calculator ignores them by design.
	 */
	public function test_fractional_quantity_half_up(): void {
		// 1.5 × 1000 = 1500.
		$this->assertSame( 1500, Money::line_base_minor( '1.5', 1000 ) );
		// 1.3333 × 3 = 4 (3.9999 → 4 half-up via integer path).
		$this->assertSame( 4, Money::line_base_minor( '1.3333', 3 ) );
	}

	/**
	 * Withholding reduces net expected without changing invoice total.
	 */
	public function test_withholding(): void {
		$result = Invoice_Totals::calculate(
			array(
				array(
					'quantity'         => '1',
					'unit_price_minor' => 1_000_000,
					'tax_rate'         => '0',
				),
			),
			array(
				'charges_sales_tax' => false,
				'apply_withholding' => true,
				'withholding_rate'  => '2',
			)
		);

		$this->assertSame( 1_000_000, $result['total_minor'] );
		$this->assertSame( 20_000, $result['withholding_minor'] );
		$this->assertSame( 980_000, $result['net_expected_minor'] );
	}

	/**
	 * Balance never goes negative.
	 */
	public function test_balance(): void {
		$this->assertSame( 500, Invoice_Totals::balance_minor( 1000, 500 ) );
		$this->assertSame( 0, Invoice_Totals::balance_minor( 1000, 1500 ) );
	}
}
