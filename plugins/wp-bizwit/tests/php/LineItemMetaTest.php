<?php
/**
 * Tests for line item kind/period metadata helpers.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Support\Line_Item_Meta;

/**
 * Pure helper behaviour: whitelists, invariants, canonical presets, labels.
 */
class LineItemMetaTest extends WP_UnitTestCase {

	/**
	 * Kind slugs are whitelisted; '' stays valid.
	 */
	public function test_sanitize_kind_whitelist(): void {
		$this->assertSame( 'goods', Line_Item_Meta::sanitize_kind( 'goods' ) );
		$this->assertSame( 'service', Line_Item_Meta::sanitize_kind( 'service' ) );
		$this->assertSame( 'digital', Line_Item_Meta::sanitize_kind( 'digital' ) );
		$this->assertSame( '', Line_Item_Meta::sanitize_kind( 'product' ) );
		$this->assertSame( '', Line_Item_Meta::sanitize_kind( '' ) );
		$this->assertSame( '', Line_Item_Meta::sanitize_kind( '<script>' ) );
	}

	/**
	 * Period slugs are whitelisted; unknown becomes one_time.
	 */
	public function test_sanitize_period_whitelist(): void {
		$this->assertSame( 'monthly', Line_Item_Meta::sanitize_period( 'monthly' ) );
		$this->assertSame( 'custom', Line_Item_Meta::sanitize_period( 'custom' ) );
		$this->assertSame( 'one_time', Line_Item_Meta::sanitize_period( 'weekly' ) );
		$this->assertSame( 'one_time', Line_Item_Meta::sanitize_period( '' ) );
	}

	/**
	 * Custom length validates count and unit together.
	 */
	public function test_sanitize_custom_length(): void {
		$this->assertSame(
			array(
				'count' => 6,
				'unit'  => 'month',
			),
			Line_Item_Meta::sanitize_custom_length( '6', 'month' )
		);
		$this->assertSame(
			array(
				'count' => 0,
				'unit'  => 'month',
			),
			Line_Item_Meta::sanitize_custom_length( 0, 'month' )
		);
		$this->assertSame(
			array(
				'count' => 3,
				'unit'  => '',
			),
			Line_Item_Meta::sanitize_custom_length( 3, 'decade' )
		);
		$this->assertSame(
			array(
				'count' => 999,
				'unit'  => 'year',
			),
			Line_Item_Meta::sanitize_custom_length( 5000, 'year' )
		);
	}

	/**
	 * Periods other than custom normalise count/unit away.
	 */
	public function test_normalize_non_custom_zeroes_length(): void {
		$meta = Line_Item_Meta::normalize(
			array(
				'item_kind'      => 'goods',
				'billing_period' => 'one_time',
				'period_count'   => 5,
				'period_unit'    => 'month',
			)
		);
		$this->assertSame( 'one_time', $meta['billing_period'] );
		$this->assertSame( 0, $meta['period_count'] );
		$this->assertSame( '', $meta['period_unit'] );

		$meta = Line_Item_Meta::normalize(
			array(
				'billing_period' => 'monthly',
				'period_count'   => 5,
				'period_unit'    => 'month',
			)
		);
		$this->assertSame( 'monthly', $meta['billing_period'] );
		$this->assertSame( 0, $meta['period_count'] );
		$this->assertSame( '', $meta['period_unit'] );
	}

	/**
	 * Complete custom length survives; incomplete falls back to one_time.
	 */
	public function test_normalize_custom(): void {
		$meta = Line_Item_Meta::normalize(
			array(
				'billing_period' => 'custom',
				'period_count'   => 6,
				'period_unit'    => 'month',
			)
		);
		$this->assertSame( 'custom', $meta['billing_period'] );
		$this->assertSame( 6, $meta['period_count'] );
		$this->assertSame( 'month', $meta['period_unit'] );

		$meta = Line_Item_Meta::normalize(
			array(
				'billing_period' => 'custom',
				'period_count'   => 0,
				'period_unit'    => 'month',
			)
		);
		$this->assertSame( 'one_time', $meta['billing_period'] );

		$meta = Line_Item_Meta::normalize(
			array(
				'billing_period' => 'custom',
				'period_count'   => 6,
				'period_unit'    => '',
			)
		);
		$this->assertSame( 'one_time', $meta['billing_period'] );
	}

	/**
	 * Preset detection is canonical and locale-independent.
	 */
	public function test_is_preset_unit(): void {
		$this->assertTrue( Line_Item_Meta::is_preset_unit( '' ) );
		$this->assertTrue( Line_Item_Meta::is_preset_unit( 'pcs' ) );
		$this->assertTrue( Line_Item_Meta::is_preset_unit( 'Bulan' ) );
		$this->assertTrue( Line_Item_Meta::is_preset_unit( '6 bulan' ) );
		$this->assertTrue( Line_Item_Meta::is_preset_unit( '12 minggu' ) );
		// A custom satuan the user typed is never a preset.
		$this->assertFalse( Line_Item_Meta::is_preset_unit( 'license seat' ) );
		// Translated strings must not match (stored data is canonical only).
		$this->assertFalse( Line_Item_Meta::is_preset_unit( 'month' ) );
		$this->assertFalse( Line_Item_Meta::is_preset_unit( '6 months' ) );
	}

	/**
	 * Suggestions follow the period; one_time and bad custom suggest nothing.
	 */
	public function test_suggest_unit(): void {
		$this->assertSame( 'bulan', Line_Item_Meta::suggest_unit( 'monthly' ) );
		$this->assertSame( 'kuartal', Line_Item_Meta::suggest_unit( 'quarterly' ) );
		$this->assertSame( 'tahun', Line_Item_Meta::suggest_unit( 'yearly' ) );
		$this->assertSame( '6 bulan', Line_Item_Meta::suggest_unit( 'custom', 6, 'month' ) );
		$this->assertSame( '10 hari', Line_Item_Meta::suggest_unit( 'custom', 10, 'day' ) );
		$this->assertSame( '', Line_Item_Meta::suggest_unit( 'one_time' ) );
		$this->assertSame( '', Line_Item_Meta::suggest_unit( 'custom', 0, 'month' ) );
		$this->assertSame( '', Line_Item_Meta::suggest_unit( 'custom', 6, '' ) );
	}

	/**
	 * Stored unit always wins; empty derives from the period.
	 */
	public function test_display_unit(): void {
		$this->assertSame(
			'jam',
			Line_Item_Meta::display_unit(
				array(
					'unit'           => 'jam',
					'billing_period' => 'monthly',
				)
			)
		);
		$this->assertSame(
			'bulan',
			Line_Item_Meta::display_unit(
				array(
					'unit'           => '',
					'billing_period' => 'monthly',
				)
			)
		);
		$this->assertSame(
			'6 bulan',
			Line_Item_Meta::display_unit(
				array(
					'unit'           => '',
					'billing_period' => 'custom',
					'period_count'   => 6,
					'period_unit'    => 'month',
				)
			)
		);
		$this->assertSame(
			'',
			Line_Item_Meta::display_unit(
				array(
					'unit'           => '',
					'billing_period' => 'one_time',
				)
			)
		);
	}

	/**
	 * Subline combines kind and period, or stays empty for bare one_time.
	 */
	public function test_subline(): void {
		$this->assertSame(
			'Service · billed monthly',
			Line_Item_Meta::subline(
				array(
					'item_kind'      => 'service',
					'billing_period' => 'monthly',
				)
			)
		);
		$this->assertSame(
			'Goods',
			Line_Item_Meta::subline(
				array(
					'item_kind'      => 'goods',
					'billing_period' => 'one_time',
				)
			)
		);
		$this->assertSame(
			'billed yearly',
			Line_Item_Meta::subline(
				array(
					'item_kind'      => '',
					'billing_period' => 'yearly',
				)
			)
		);
		$this->assertSame(
			'6 months',
			Line_Item_Meta::subline(
				array(
					'item_kind'      => '',
					'billing_period' => 'custom',
					'period_count'   => 6,
					'period_unit'    => 'month',
				)
			)
		);
		$this->assertSame(
			'',
			Line_Item_Meta::subline(
				array(
					'item_kind'      => '',
					'billing_period' => 'one_time',
				)
			)
		);
	}

	/**
	 * Enriched description is escaped and carries the subline only when set.
	 */
	public function test_enrich_description_html(): void {
		$html = Line_Item_Meta::enrich_description_html(
			array(
				'description'    => 'Retainer <b>web</b>',
				'item_kind'      => 'service',
				'billing_period' => 'monthly',
			)
		);
		$this->assertStringContainsString( 'Retainer &lt;b&gt;web&lt;/b&gt;', $html );
		$this->assertStringContainsString( '<span class="wp-bizwit-line-sub">Service · billed monthly</span>', $html );

		$html = Line_Item_Meta::enrich_description_html(
			array(
				'description'    => 'Printer toner',
				'item_kind'      => '',
				'billing_period' => 'one_time',
			)
		);
		$this->assertSame( 'Printer toner', $html );
	}
}
