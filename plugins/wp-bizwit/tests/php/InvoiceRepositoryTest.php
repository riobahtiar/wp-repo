<?php
/**
 * Tests for invoice persistence, tax gating and status rules.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Database\Installer;
use WP_BizWit\Localization\Indonesia;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Invoice_Repository;
use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Settings;

/**
 * Invoice repository behaviour.
 */
class InvoiceRepositoryTest extends WP_UnitTestCase {

	/**
	 * Invoice repository under test.
	 *
	 * @var Invoice_Repository
	 */
	private Invoice_Repository $invoices;

	/**
	 * Client repository for fixtures.
	 *
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * Ensure schema and clean settings.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( Settings::OPTION );
		( new Installer() )->install();

		$this->invoices = new Invoice_Repository();
		$this->clients  = new Client_Repository();
	}

	/**
	 * Create a client fixture.
	 *
	 * @return int Client id.
	 */
	private function make_client(): int {
		$id = $this->clients->create(
			array(
				'display_name' => 'Invoice Client',
				'type'         => Client_Repository::TYPE_COMPANY,
				'status'       => Client_Repository::STATUS_ACTIVE,
			)
		);

		$this->assertIsInt( $id );

		return $id;
	}

	/**
	 * Minimal invoice payload.
	 *
	 * @param int                  $client_id Client id.
	 * @param array<string, mixed> $extra     Overrides.
	 *
	 * @return array<string, mixed>
	 */
	private function payload( int $client_id, array $extra = array() ): array {
		return array_merge(
			array(
				'client_id'  => $client_id,
				'status'     => Invoice_Status::DRAFT,
				'issue_date' => '2026-07-01',
				'currency'   => 'IDR',
				'items'      => array(
					array(
						'description' => 'Consulting',
						'quantity'    => '1',
						'unit_price'  => '1500000',
						'tax_rate'    => '11',
					),
				),
			),
			$extra
		);
	}

	/**
	 * Freelancer with tax off: no tax on invoice.
	 */
	public function test_create_without_tax_when_not_pkp(): void {
		Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );
		$client_id = $this->make_client();

		$id = $this->invoices->create( $this->payload( $client_id ) );
		$this->assertIsInt( $id );

		$row = $this->invoices->find( $id );
		$this->assertNotNull( $row );
		$this->assertSame( 1_500_000, (int) $row['total_minor'] );
		$this->assertSame( 0, (int) $row['tax_minor'] );
		$this->assertStringStartsWith( 'DRAFT-', (string) $row['invoice_number'] );
	}

	/**
	 * PKP charges PPN on lines.
	 */
	public function test_create_with_ppn_when_pkp(): void {
		Settings::update(
			array(
				'tax_regime'       => Indonesia::REGIME_PKP,
				'default_tax_rate' => '11',
			)
		);
		$client_id = $this->make_client();

		$id = $this->invoices->create( $this->payload( $client_id ) );
		$this->assertIsInt( $id );

		$row = $this->invoices->find( $id );
		$this->assertNotNull( $row );
		$this->assertSame( 1_500_000, (int) $row['subtotal_minor'] );
		$this->assertSame( 165_000, (int) $row['tax_minor'] );
		$this->assertSame( 1_665_000, (int) $row['total_minor'] );
	}

	/**
	 * Posted total is ignored — server recomputes.
	 */
	public function test_posted_total_is_ignored(): void {
		Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );
		$client_id = $this->make_client();

		$id = $this->invoices->create(
			$this->payload(
				$client_id,
				array(
					'total_minor' => 99,
					'subtotal'    => '1',
				)
			)
		); // phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- intentional bogus totals.

		$row = $this->invoices->find( (int) $id );
		$this->assertSame( 1_500_000, (int) $row['total_minor'] );
	}

	/**
	 * Kind/period columns persist on lines and normalise on save.
	 */
	public function test_items_persist_kind_and_period(): void {
		Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );
		$client_id = $this->make_client();

		$id = $this->invoices->create(
			$this->payload(
				$client_id,
				array(
					'items' => array(
						array(
							'description'    => 'Maintenance retainer',
							'quantity'       => '1',
							'unit'           => 'bulan',
							'unit_price'     => '2000000',
							'item_kind'      => 'service',
							'billing_period' => 'monthly',
							'period_count'   => 12,
							'period_unit'    => 'month',
						),
						array(
							'description'    => 'Licence, 6 months',
							'quantity'       => '1',
							'unit_price'     => '3000000',
							'item_kind'      => 'digital',
							'billing_period' => 'custom',
							'period_count'   => 6,
							'period_unit'    => 'month',
						),
						array(
							'description' => 'Printer toner',
							'quantity'    => '2',
							'unit'        => 'pcs',
							'unit_price'  => '250000',
						),
					),
				)
			)
		);
		$this->assertIsInt( $id );

		$items = $this->invoices->get_items( $id );
		$this->assertCount( 3, $items );

		// Recurring period normalises count/unit away.
		$this->assertSame( 'service', $items[0]['item_kind'] );
		$this->assertSame( 'monthly', $items[0]['billing_period'] );
		$this->assertSame( 0, (int) $items[0]['period_count'] );
		$this->assertSame( '', $items[0]['period_unit'] );

		// Custom length persists.
		$this->assertSame( 'digital', $items[1]['item_kind'] );
		$this->assertSame( 'custom', $items[1]['billing_period'] );
		$this->assertSame( 6, (int) $items[1]['period_count'] );
		$this->assertSame( 'month', $items[1]['period_unit'] );

		// Bare line picks up the column defaults.
		$this->assertSame( '', $items[2]['item_kind'] );
		$this->assertSame( 'one_time', $items[2]['billing_period'] );
		$this->assertSame( 0, (int) $items[2]['period_count'] );
		$this->assertSame( '', $items[2]['period_unit'] );
	}

	/**
	 * Kind/period never changes money: totals match the meta-less invoice.
	 */
	public function test_totals_identical_with_and_without_meta(): void {
		Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );
		$client_id = $this->make_client();

		$plain = $this->invoices->create( $this->payload( $client_id ) );
		$this->assertIsInt( $plain );

		$meta = $this->invoices->create(
			$this->payload(
				$client_id,
				array(
					'items' => array(
						array(
							'description'    => 'Consulting',
							'quantity'       => '1',
							'unit_price'     => '1500000',
							'tax_rate'       => '11',
							'item_kind'      => 'service',
							'billing_period' => 'monthly',
						),
					),
				)
			)
		);
		$this->assertIsInt( $meta );

		$a = $this->invoices->find( $plain );
		$b = $this->invoices->find( $meta );
		$this->assertSame( (int) $a['subtotal_minor'], (int) $b['subtotal_minor'] );
		$this->assertSame( (int) $a['tax_minor'], (int) $b['tax_minor'] );
		$this->assertSame( (int) $a['total_minor'], (int) $b['total_minor'] );
	}

	/**
	 * Marking sent allocates a permanent number.
	 */
	public function test_send_allocates_number(): void {
		Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );
		$client_id = $this->make_client();
		$id        = (int) $this->invoices->create( $this->payload( $client_id ) );

		$result = $this->invoices->transition( $id, Invoice_Status::SENT );
		$this->assertTrue( $result );

		$row = $this->invoices->find( $id );
		$this->assertSame( Invoice_Status::SENT, $row['status'] );
		$this->assertStringNotContainsString( 'DRAFT-', (string) $row['invoice_number'] );
		$this->assertNotEmpty( $row['invoice_number'] );
	}

	/**
	 * Non-draft cannot be deleted; void preserves number.
	 */
	public function test_void_preserves_number_and_blocks_delete(): void {
		Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );
		$client_id = $this->make_client();
		$id        = (int) $this->invoices->create( $this->payload( $client_id ) );
		$this->invoices->transition( $id, Invoice_Status::SENT );

		$row    = $this->invoices->find( $id );
		$number = (string) $row['invoice_number'];

		$delete = $this->invoices->delete( $id );
		$this->assertWPError( $delete );

		$void = $this->invoices->void( $id );
		$this->assertTrue( $void );

		$row = $this->invoices->find( $id );
		$this->assertSame( Invoice_Status::VOID, $row['status'] );
		$this->assertSame( $number, (string) $row['invoice_number'] );
	}

	/**
	 * Lines are required.
	 */
	public function test_requires_line_items(): void {
		$client_id = $this->make_client();
		$result    = $this->invoices->create(
			array(
				'client_id' => $client_id,
				'items'     => array(
					array(
						'description' => '',
						'unit_price'  => '100',
					),
				),
			)
		);

		$this->assertWPError( $result );
	}

	/**
	 * Withholding recorded on the invoice.
	 */
	public function test_withholding_recorded(): void {
		Settings::update(
			array(
				'tax_regime'       => Indonesia::REGIME_PKP,
				'withholding_rate' => '2',
			)
		);
		$client_id = $this->make_client();

		// Subtotal only (tax off for simpler assert): use none regime.
		Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );

		$id = $this->invoices->create(
			$this->payload(
				$client_id,
				array(
					'apply_withholding' => '1',
					'withholding_rate'  => '2',
				)
			)
		);

		$row = $this->invoices->find( (int) $id );
		$this->assertSame( 1_500_000, (int) $row['total_minor'] );
		$this->assertSame( 30_000, (int) $row['withholding_minor'] );
	}

	/**
	 * Overdue marker flips past-due sent invoices.
	 */
	public function test_mark_overdue(): void {
		Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );
		$client_id = $this->make_client();
		$id        = (int) $this->invoices->create(
			$this->payload(
				$client_id,
				array(
					'issue_date' => '2026-01-01',
					'due_date'   => '2026-01-15',
				)
			)
		);
		$this->invoices->transition( $id, Invoice_Status::SENT );

		$updated = $this->invoices->mark_overdue();
		$this->assertGreaterThanOrEqual( 1, $updated );

		$row = $this->invoices->find( $id );
		$this->assertSame( Invoice_Status::OVERDUE, $row['status'] );
	}
}
