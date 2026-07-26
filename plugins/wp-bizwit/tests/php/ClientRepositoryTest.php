<?php
/**
 * Tests for client persistence and delete guards.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Database\Installer;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Invoice_Repository;
use WP_BizWit\Repositories\Project_Repository;
use WP_BizWit\Support\Invoice_Status;
use WP_BizWit\Support\Settings;

/**
 * Client repository rules.
 */
class ClientRepositoryTest extends WP_UnitTestCase {

	/**
	 * Client repository under test.
	 *
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * Reset schema and settings.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
		( new Installer() )->install();
		$this->clients = new Client_Repository();
	}

	/**
	 * A client can be created with only a display name.
	 */
	public function test_create_minimal_client(): void {
		$id = $this->clients->create(
			array(
				'display_name' => 'Ada Lovelace',
			)
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		$row = $this->clients->find( $id );
		$this->assertSame( 'Ada Lovelace', $row['display_name'] );
		$this->assertSame( Client_Repository::STATUS_ACTIVE, $row['status'] );
	}

	/**
	 * Name is required.
	 */
	public function test_name_required(): void {
		$result = $this->clients->create( array( 'display_name' => '' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'wp_bizwit_missing_name', $result->get_error_code() );
	}

	/**
	 * Delete is refused when projects or invoices exist.
	 */
	public function test_delete_guard_with_project(): void {
		$client_id = $this->clients->create( array( 'display_name' => 'With Project' ) );
		$this->assertIsInt( $client_id );

		$projects = new Project_Repository();
		$pid      = $projects->create(
			array(
				'client_id' => $client_id,
				'name'      => 'Job',
			)
		);
		$this->assertIsInt( $pid );

		$delete = $this->clients->delete( (int) $client_id );
		$this->assertWPError( $delete );
		$this->assertSame( 'wp_bizwit_client_in_use', $delete->get_error_code() );
	}

	/**
	 * Delete succeeds for a client with no dependents.
	 */
	public function test_delete_orphan_client(): void {
		$id = $this->clients->create( array( 'display_name' => 'Temporary' ) );
		$this->assertTrue( $this->clients->delete( (int) $id ) );
		$this->assertNull( $this->clients->find( (int) $id ) );
	}

	/**
	 * Delete guard counts invoices too.
	 */
	public function test_delete_guard_with_invoice(): void {
		Settings::update( array( 'tax_regime' => Settings::REGIME_NONE ) );
		$client_id = (int) $this->clients->create( array( 'display_name' => 'Billed' ) );

		$invoices = new Invoice_Repository();
		$inv_id   = $invoices->create(
			array(
				'client_id'  => $client_id,
				'status'     => Invoice_Status::DRAFT,
				'issue_date' => '2026-07-01',
				'currency'   => 'IDR',
				'items'      => array(
					array(
						'description' => 'Work',
						'quantity'    => '1',
						'unit_price'  => '100000',
					),
				),
			)
		);
		$this->assertIsInt( $inv_id );

		$delete = $this->clients->delete( $client_id );
		$this->assertWPError( $delete );
	}
}
