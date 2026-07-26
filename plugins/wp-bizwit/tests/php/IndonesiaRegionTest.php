<?php
/**
 * Tests for the Indonesian regional profile.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Localization\Indonesia;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Localization\Terbilang;
use WP_BizWit\Support\Settings;

/**
 * Locks in the Indonesian business rules the rest of the plugin depends on.
 *
 * These are the behaviours that make the difference between a document an
 * Indonesian client will accept and one they will send back: the NPWP written
 * in its conventional form, the amount spelled out on a kwitansi, a document
 * number that carries its month and year, and - most consequentially - a
 * non-PKP business being unable to put PPN on an invoice.
 */
class IndonesiaRegionTest extends WP_UnitTestCase {

	/**
	 * Start each test from stored defaults with a fresh region cache.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( Settings::OPTION );
		Regions::reset();
	}

	/**
	 * A fresh install already speaks Indonesian business vocabulary.
	 */
	public function test_indonesia_is_the_default_region(): void {
		$this->assertInstanceOf( Indonesia::class, Regions::current() );
		$this->assertSame( 'IDR', Settings::currency() );
	}

	/**
	 * Billing in rupiah is treated as evidence of Indonesian paperwork.
	 */
	public function test_rupiah_billing_selects_indonesia(): void {
		Settings::save(
			array(
				'region'           => Regions::AUTO,
				'business_country' => 'SG',
				'currency'         => 'IDR',
			)
		);

		$this->assertInstanceOf( Indonesia::class, Regions::current() );
	}

	/**
	 * A business elsewhere billing in another currency falls back to generic.
	 */
	public function test_non_indonesian_business_falls_back_to_generic(): void {
		Settings::save(
			array(
				'region'           => Regions::AUTO,
				'business_country' => 'SG',
				'currency'         => 'SGD',
			)
		);

		$this->assertNotInstanceOf( Indonesia::class, Regions::current() );
	}

	/**
	 * Client types are named the way Indonesian businesses name them.
	 */
	public function test_client_types_use_indonesian_wording(): void {
		$types = ( new Indonesia() )->client_types();

		$this->assertSame( 'Perorangan', $types['individual'] );
		$this->assertSame( 'Instansi Pemerintah', $types['government'] );
		$this->assertArrayHasKey( 'organization', $types );
	}

	/**
	 * Core fields are relabelled rather than duplicated.
	 */
	public function test_core_fields_are_relabelled(): void {
		$region = new Indonesia();

		$this->assertSame( 'NPWP', $region->field_label( 'tax_id', 'Tax ID' ) );
		$this->assertSame( 'NIB', $region->field_label( 'registration_no', 'Registration number' ) );
		$this->assertSame( 'Provinsi', $region->field_label( 'state', 'State' ) );
		$this->assertSame( 'Kabupaten / Kota', $region->field_label( 'city', 'City' ) );
	}

	/**
	 * A legacy 15-digit NPWP is presented in its conventional masked form.
	 */
	public function test_legacy_npwp_is_formatted(): void {
		$region = new Indonesia();

		$this->assertSame( '01.234.567.8-901.234', $region->format_tax_id( '012345678901234' ) );
		$this->assertSame( '01.234.567.8-901.234', $region->format_tax_id( '01.234.567.8-901.234' ) );
	}

	/**
	 * The 16-digit NIK-based NPWP is stored as plain digits.
	 */
	public function test_current_npwp_is_stored_as_digits(): void {
		$this->assertSame( '1234567890123456', ( new Indonesia() )->format_tax_id( '1234567890123456' ) );
	}

	/**
	 * Both NPWP lengths validate, and a wrong length does not.
	 */
	public function test_npwp_length_is_validated(): void {
		$region = new Indonesia();

		$this->assertTrue( $region->is_valid_tax_id( '' ) );
		$this->assertTrue( $region->is_valid_tax_id( '012345678901234' ) );
		$this->assertTrue( $region->is_valid_tax_id( '1234567890123456' ) );
		$this->assertFalse( $region->is_valid_tax_id( '12345' ) );
	}

	/**
	 * All 38 provinces are offered, including the newest Papua provinces.
	 */
	public function test_province_list_is_current(): void {
		$provinces = ( new Indonesia() )->provinces();

		$this->assertCount( 38, $provinces );
		$this->assertContains( 'DKI Jakarta', $provinces );
		$this->assertContains( 'Papua Barat Daya', $provinces );
		$this->assertContains( 'Papua Pegunungan', $provinces );
	}

	/**
	 * Document numbers carry the month in roman numerals and the year.
	 */
	public function test_document_number_uses_indonesian_house_style(): void {
		Settings::save(
			array(
				'region'         => 'id',
				'business_code'  => 'BW',
				'number_padding' => 3,
			)
		);

		$this->assertSame( '007/INV/BW/VII/2026', Settings::document_number( 'invoice', 7, '2026-07-26' ) );
		$this->assertSame( '001/KW/BW/I/2026', Settings::document_number( 'receipt', 1, '2026-01-05' ) );
	}

	/**
	 * The simple format is still available inside the Indonesian region.
	 */
	public function test_simple_numbering_can_be_forced(): void {
		Settings::save(
			array(
				'region'         => 'id',
				'number_format'  => 'simple',
				'invoice_prefix' => 'INV-',
				'number_padding' => 4,
			)
		);

		$this->assertSame( 'INV-0007', Settings::document_number( 'invoice', 7, '2026-07-26' ) );
	}

	/**
	 * Dates read as an Indonesian reader expects, regardless of site locale.
	 */
	public function test_dates_use_indonesian_month_names(): void {
		$this->assertSame( '26 Juli 2026', ( new Indonesia() )->format_date( '2026-07-26' ) );
		$this->assertSame( '1 Agustus 2026', ( new Indonesia() )->format_date( '2026-08-01' ) );
	}

	/**
	 * Bea meterai applies only above the statutory threshold.
	 */
	public function test_stamp_duty_applies_above_threshold(): void {
		$region = new Indonesia();

		$this->assertSame( 0, $region->stamp_duty( 5000000, 'IDR' ) );
		$this->assertSame( 10000, $region->stamp_duty( 5000001, 'IDR' ) );
		$this->assertSame( 0, $region->stamp_duty( 900000000, 'USD' ) );
	}

	/**
	 * A non-PKP business cannot charge PPN, whatever rate is stored.
	 *
	 * This is the rule most worth guarding: a small business that bills PPN it
	 * is not registered to collect has a real compliance problem, not a cosmetic
	 * one.
	 */
	public function test_non_pkp_business_charges_no_ppn(): void {
		Settings::save(
			array(
				'tax_regime'       => Indonesia::REGIME_UMKM_FINAL,
				'default_tax_rate' => '11',
			)
		);

		$this->assertFalse( Settings::charges_sales_tax() );
		$this->assertSame( '0', Settings::effective_tax_rate() );
	}

	/**
	 * A PKP business charges the configured rate.
	 */
	public function test_pkp_business_charges_configured_rate(): void {
		Settings::save(
			array(
				'tax_regime'       => Indonesia::REGIME_PKP,
				'default_tax_rate' => '11',
			)
		);

		$this->assertTrue( Settings::charges_sales_tax() );
		$this->assertSame( '11', Settings::effective_tax_rate() );
	}

	/**
	 * Payment methods cover how Indonesian clients actually pay.
	 */
	public function test_payment_methods_are_local(): void {
		$methods = ( new Indonesia() )->payment_methods();

		$this->assertArrayHasKey( 'qris', $methods );
		$this->assertArrayHasKey( 'virtual_acc', $methods );
		$this->assertArrayHasKey( 'sp2d', $methods );
	}

	/**
	 * Amounts written out in words follow Indonesian grammar.
	 *
	 * @dataProvider terbilang_provider
	 *
	 * @param int    $amount   Whole rupiah.
	 * @param string $expected Expected wording.
	 */
	public function test_terbilang_spells_amounts( int $amount, string $expected ): void {
		$this->assertSame( $expected, Terbilang::rupiah( $amount ) );
	}

	/**
	 * Amounts and the wording they must produce.
	 *
	 * @return array<string, array{0: int, 1: string}> Case name mapped to amount and wording.
	 */
	public function terbilang_provider(): array {
		return array(
			'zero'                  => array( 0, 'Nol rupiah' ),
			'single digit'          => array( 7, 'Tujuh rupiah' ),
			'ten contracts'         => array( 10, 'Sepuluh rupiah' ),
			'eleven is irregular'   => array( 11, 'Sebelas rupiah' ),
			'teens'                 => array( 15, 'Lima belas rupiah' ),
			'tens'                  => array( 21, 'Dua puluh satu rupiah' ),
			'hundred contracts'     => array( 100, 'Seratus rupiah' ),
			'hundreds'              => array( 250, 'Dua ratus lima puluh rupiah' ),
			'thousand contracts'    => array( 1000, 'Seribu rupiah' ),
			'thousands'             => array( 1500, 'Seribu lima ratus rupiah' ),
			'typical invoice'       => array( 1500000, 'Satu juta lima ratus ribu rupiah' ),
			'awkward middle zeroes' => array( 2050000, 'Dua juta lima puluh ribu rupiah' ),
			'billions'              => array( 4800000000, 'Empat miliar delapan ratus juta rupiah' ),
		);
	}

	/**
	 * Only rupiah amounts are spelled out.
	 */
	public function test_amount_in_words_is_rupiah_only(): void {
		$region = new Indonesia();

		$this->assertSame( 'Satu juta rupiah', $region->amount_in_words( 1000000, 'IDR' ) );
		$this->assertSame( '', $region->amount_in_words( 1000000, 'USD' ) );
	}
}
