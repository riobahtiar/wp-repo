<?php
/**
 * Tests for dashboard aggregates.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Database\Installer;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Invoice_Repository;
use WP_BizWit\Repositories\Payment_Repository;
use WP_BizWit\Repositories\Stats_Repository;
use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Settings;

/**
 * Stats used by the dashboard.
 */
class StatsRepositoryTest extends WP_UnitTestCase {

	/**
	 * Stats repository under test.
	 *
	 * @var Stats_Repository
	 */
	private Stats_Repository $stats;

	/**
	 * Client fixtures.
	 *
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * Invoice fixtures.
	 *
	 * @var Invoice_Repository
	 */
	private Invoice_Repository $invoices;

	/**
	 * Install schema.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
		Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );
		( new Installer() )->install();
		$this->stats    = new Stats_Repository();
		$this->clients  = new Client_Repository();
		$this->invoices = new Invoice_Repository();
	}

	/**
	 * Create a sent invoice with due date and amount.
	 *
	 * @param int    $client_id Client.
	 * @param string $due       Due date Y-m-d.
	 * @param string $amount    Unit price string.
	 *
	 * @return int Invoice id.
	 */
	private function make_sent_invoice( int $client_id, string $due, string $amount ): int {
		$id = $this->invoices->create(
			array(
				'client_id'  => $client_id,
				'status'     => Invoice_Status::DRAFT,
				'issue_date' => '2026-01-01',
				'due_date'   => $due,
				'currency'   => 'IDR',
				'items'      => array(
					array(
						'description' => 'Work',
						'quantity'    => '1',
						'unit_price'  => $amount,
					),
				),
			)
		);
		$this->assertIsInt( $id );
		$this->assertTrue( $this->invoices->transition( (int) $id, Invoice_Status::SENT ) );

		return (int) $id;
	}

	/**
	 * Outstanding ageing puts past-due balances in the right bucket.
	 */
	public function test_ageing_buckets(): void {
		$client_id = (int) $this->clients->create( array( 'display_name' => 'Age Client' ) );

		// Far past due → 61+.
		$this->make_sent_invoice( $client_id, '2020-01-01', '1000000' );
		// Recent past due roughly 10 days ago if today is mid-2026+.
		$ten_days_ago = gmdate( 'Y-m-d', strtotime( '-10 days' ) );
		$this->make_sent_invoice( $client_id, $ten_days_ago, '500000' );
		// Future due → current.
		$next_month = gmdate( 'Y-m-d', strtotime( '+30 days' ) );
		$this->make_sent_invoice( $client_id, $next_month, '200000' );

		$ageing = $this->stats->outstanding_ageing_minor();
		$this->assertSame( 1000000, $ageing['d61_plus'] );
		$this->assertSame( 500000, $ageing['d1_30'] );
		$this->assertSame( 200000, $ageing['current'] );
		$this->assertSame( 1700000, $ageing['total'] );
	}

	/**
	 * Paid invoices do not contribute to outstanding.
	 */
	public function test_paid_excluded_from_outstanding(): void {
		$client_id = (int) $this->clients->create( array( 'display_name' => 'Paid Client' ) );
		$inv_id    = $this->make_sent_invoice( $client_id, '2020-01-01', '750000' );

		$payments = new Payment_Repository();
		$payments->create(
			array(
				'invoice_id' => $inv_id,
				'amount'     => '750000',
				'method'     => 'bank_transfer',
				'paid_on'    => '2026-07-01',
			)
		);

		$this->assertSame( 0, $this->stats->outstanding_minor() );
		$ageing = $this->stats->outstanding_ageing_minor();
		$this->assertSame( 0, $ageing['total'] );
	}

	/**
	 * Recent invoices returns newest first.
	 */
	public function test_recent_invoices(): void {
		$client_id = (int) $this->clients->create( array( 'display_name' => 'Recent' ) );
		$this->make_sent_invoice( $client_id, '2026-12-01', '1000' );
		$this->make_sent_invoice( $client_id, '2026-12-02', '2000' );

		$recent = $this->stats->recent_invoices( 5 );
		$this->assertGreaterThanOrEqual( 2, count( $recent ) );
		$this->assertArrayHasKey( 'invoice_number', $recent[0] );
	}
}
