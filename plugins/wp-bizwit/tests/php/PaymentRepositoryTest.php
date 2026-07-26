<?php
/**
 * Tests for payment settlement against invoices.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Database\Installer;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Invoice_Repository;
use WP_BizWit\Repositories\Payment_Repository;
use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Settings;

/**
 * Payment create/delete keeps invoice paid_minor and status correct.
 */
class PaymentRepositoryTest extends WP_UnitTestCase {

	/**
	 * @var Payment_Repository
	 */
	private Payment_Repository $payments;

	/**
	 * @var Invoice_Repository
	 */
	private Invoice_Repository $invoices;

	/**
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( Settings::OPTION );
		( new Installer() )->install();

		$this->payments = new Payment_Repository();
		$this->invoices = new Invoice_Repository();
		$this->clients  = new Client_Repository();
	}

	/**
	 * @return int
	 */
	private function make_client(): int {
		$id = $this->clients->create(
			array(
				'display_name' => 'Pay Client',
				'type'         => Client_Repository::TYPE_COMPANY,
				'status'       => Client_Repository::STATUS_ACTIVE,
			)
		);
		$this->assertIsInt( $id );

		return $id;
	}

	/**
	 * Create and send an invoice for 1_500_000 IDR.
	 *
	 * @param int $client_id Client id.
	 *
	 * @return int Invoice id.
	 */
	private function make_sent_invoice( int $client_id ): int {
		Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );

		$id = $this->invoices->create(
			array(
				'client_id'  => $client_id,
				'status'     => Invoice_Status::DRAFT,
				'issue_date' => '2026-07-01',
				'due_date'   => '2026-07-31',
				'currency'   => 'IDR',
				'items'      => array(
					array(
						'description' => 'Work',
						'quantity'    => '1',
						'unit_price'  => '1500000',
					),
				),
			)
		);
		$this->assertIsInt( $id );
		$this->assertTrue( $this->invoices->transition( (int) $id, Invoice_Status::SENT ) );

		return (int) $id;
	}

	/**
	 * Partial payment moves invoice to partial.
	 */
	public function test_partial_payment(): void {
		$client_id  = $this->make_client();
		$invoice_id = $this->make_sent_invoice( $client_id );

		$pid = $this->payments->create(
			array(
				'invoice_id' => $invoice_id,
				'paid_on'    => '2026-07-10',
				'amount'     => '500000',
				'method'     => 'bank_transfer',
			)
		);
		$this->assertIsInt( $pid );

		$inv = $this->invoices->find( $invoice_id );
		$this->assertSame( 500_000, (int) $inv['paid_minor'] );
		$this->assertSame( Invoice_Status::PARTIAL, $inv['status'] );
	}

	/**
	 * Full bank payment marks paid.
	 */
	public function test_full_payment(): void {
		$client_id  = $this->make_client();
		$invoice_id = $this->make_sent_invoice( $client_id );

		$this->payments->create(
			array(
				'invoice_id' => $invoice_id,
				'amount'     => '1500000',
				'method'     => 'bank_transfer',
			)
		);

		$inv = $this->invoices->find( $invoice_id );
		$this->assertSame( 1_500_000, (int) $inv['paid_minor'] );
		$this->assertSame( Invoice_Status::PAID, $inv['status'] );
	}

	/**
	 * Withheld amount counts toward settlement.
	 */
	public function test_withholding_counts_toward_paid(): void {
		$client_id  = $this->make_client();
		$invoice_id = $this->make_sent_invoice( $client_id );

		// Net 1_470_000 bank + 30_000 withheld = full 1_500_000.
		$this->payments->create(
			array(
				'invoice_id' => $invoice_id,
				'amount'     => '1470000',
				'withheld'   => '30000',
				'method'     => 'bank_transfer',
			)
		);

		$inv = $this->invoices->find( $invoice_id );
		$this->assertSame( 1_500_000, (int) $inv['paid_minor'] );
		$this->assertSame( Invoice_Status::PAID, $inv['status'] );
	}

	/**
	 * Delete restores invoice status.
	 */
	public function test_delete_restores_status(): void {
		$client_id  = $this->make_client();
		$invoice_id = $this->make_sent_invoice( $client_id );

		$pid = (int) $this->payments->create(
			array(
				'invoice_id' => $invoice_id,
				'amount'     => '1500000',
				'method'     => 'qris',
			)
		);

		$this->assertTrue( $this->payments->delete( $pid ) );

		$inv = $this->invoices->find( $invoice_id );
		$this->assertSame( 0, (int) $inv['paid_minor'] );
		$this->assertSame( Invoice_Status::SENT, $inv['status'] );
	}

	/**
	 * Overpayment allowed; paid_minor exceeds total.
	 */
	public function test_overpayment_allowed(): void {
		$client_id  = $this->make_client();
		$invoice_id = $this->make_sent_invoice( $client_id );

		$this->payments->create(
			array(
				'invoice_id' => $invoice_id,
				'amount'     => '1600000',
				'method'     => 'bank_transfer',
			)
		);

		$inv = $this->invoices->find( $invoice_id );
		$this->assertSame( 1_600_000, (int) $inv['paid_minor'] );
		$this->assertSame( Invoice_Status::PAID, $inv['status'] );
	}

	/**
	 * Draft invoices reject payments.
	 */
	public function test_reject_payment_on_draft(): void {
		$client_id = $this->make_client();
		Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );
		$id = $this->invoices->create(
			array(
				'client_id' => $client_id,
				'items'     => array(
					array(
						'description' => 'Work',
						'quantity'    => '1',
						'unit_price'  => '1000',
					),
				),
			)
		);

		$result = $this->payments->create(
			array(
				'invoice_id' => $id,
				'amount'     => '1000',
			)
		);
		$this->assertWPError( $result );
	}

	/**
	 * Receipt number is allocated.
	 */
	public function test_receipt_number_allocated(): void {
		$client_id  = $this->make_client();
		$invoice_id = $this->make_sent_invoice( $client_id );

		$pid = (int) $this->payments->create(
			array(
				'invoice_id' => $invoice_id,
				'amount'     => '100000',
				'paid_on'    => '2026-07-15',
			)
		);

		$row = $this->payments->find( $pid );
		$this->assertNotEmpty( $row['receipt_number'] );
		$this->assertStringNotContainsString( 'DRAFT', (string) $row['receipt_number'] );
	}
}
