<?php
/**
 * Tests for multi payment destinations.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Support\Payment_Destinations;
use WP_BizWit\Support\Settings;

/**
 * Payment destinations sanitise, migrate and render.
 */
class PaymentDestinationsTest extends WP_UnitTestCase {

	/**
	 * Clean settings.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
	}

	/**
	 * Legacy single bank fields become one bank_transfer destination.
	 *
	 * @return void
	 */
	public function test_legacy_migration(): void {
		Settings::update(
			array(
				'bank_name'         => 'BCA',
				'bank_account_no'   => '1234567890',
				'bank_account_name' => 'PT Contoh',
				'bank_branch'       => 'Sudirman',
			)
		);

		$list = Payment_Destinations::all();
		$this->assertCount( 1, $list );
		$this->assertSame( Payment_Destinations::TYPE_BANK_TRANSFER, $list[0]['type'] );
		$this->assertSame( 'BCA', $list[0]['bank_name'] );
		$this->assertSame( '1234567890', $list[0]['account_no'] );
		$this->assertTrue( Payment_Destinations::has_any() );
	}

	/**
	 * Sanitize drops blank rows and keeps mixed types.
	 *
	 * @return void
	 */
	public function test_sanitize_list(): void {
		$list = Payment_Destinations::sanitize_list(
			array(
				array(
					'type'         => 'bank_transfer',
					'enabled'      => '1',
					'bank_name'    => 'Mandiri',
					'account_no'   => '987',
					'account_name' => 'Ada',
				),
				array(
					'type'       => 'ewallet_gopay',
					'enabled'    => '1',
					'ewallet_id' => '08123456789',
				),
				array(
					'type' => 'payment_link',
					// empty — should drop
				),
				array(
					'type'    => 'virtual_account',
					'enabled' => '1',
					'va_bank' => 'BNI',
					'va_number' => '8800123456',
				),
			)
		);

		$this->assertCount( 3, $list );
		$this->assertSame( 'ewallet_gopay', $list[1]['type'] );
		$this->assertSame( 'virtual_account', $list[2]['type'] );
	}

	/**
	 * Block HTML includes all enabled methods with labels.
	 *
	 * @return void
	 */
	public function test_block_html(): void {
		Settings::update(
			array(
				'payment_destinations' => array(
					array(
						'id'           => 'pd_1',
						'type'         => 'bank_transfer',
						'enabled'      => true,
						'bank_name'    => 'BCA',
						'account_no'   => '111',
						'account_name' => 'Biz',
						'branch'       => '',
						'label'        => '',
						'va_bank'      => '',
						'va_number'    => '',
						'ewallet_id'   => '',
						'url'          => '',
						'notes'        => '',
					),
					array(
						'id'           => 'pd_2',
						'type'         => 'ewallet_dana',
						'enabled'      => true,
						'bank_name'    => '',
						'account_no'   => '',
						'account_name' => '',
						'branch'       => '',
						'label'        => '',
						'va_bank'      => '',
						'va_number'    => '',
						'ewallet_id'   => '0812',
						'url'          => '',
						'notes'        => '',
					),
					array(
						'id'           => 'pd_3',
						'type'         => 'offline',
						'enabled'      => false,
						'bank_name'    => '',
						'account_no'   => '',
						'account_name' => '',
						'branch'       => '',
						'label'        => 'Kasir',
						'va_bank'      => '',
						'va_number'    => '',
						'ewallet_id'   => '',
						'url'          => '',
						'notes'        => 'Bayar di toko',
					),
				),
			)
		);

		$html = Payment_Destinations::block_html();
		$this->assertStringContainsString( 'BCA', $html );
		$this->assertStringContainsString( 'DANA', $html );
		$this->assertStringContainsString( '0812', $html );
		$this->assertStringNotContainsString( 'Kasir', $html );
	}

	/**
	 * Legacy bank fields mirror first bank transfer.
	 *
	 * @return void
	 */
	public function test_legacy_sync(): void {
		$list = Payment_Destinations::sanitize_list(
			array(
				array(
					'type'         => 'bank_transfer',
					'enabled'      => '1',
					'bank_name'    => 'BRI',
					'account_no'   => '555',
					'account_name' => 'Holder',
					'branch'       => 'Malang',
				),
			)
		);
		$legacy = Payment_Destinations::legacy_bank_fields_from( $list );
		$this->assertSame( 'BRI', $legacy['bank_name'] );
		$this->assertSame( '555', $legacy['bank_account_no'] );
		$this->assertSame( 'Holder', $legacy['bank_account_name'] );
		$this->assertSame( 'Malang', $legacy['bank_branch'] );
	}
}
