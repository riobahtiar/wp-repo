<?php
/**
 * Tests for project persistence and termin validation.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Database\Installer;
use WP_BizWit\Database\Schema;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Project_Repository;
use WP_BizWit\Support\Settings;

/**
 * Repository rules that guard billing integrity for projects.
 */
class ProjectRepositoryTest extends WP_UnitTestCase {

	/**
	 * Project repository under test.
	 *
	 * @var Project_Repository
	 */
	private Project_Repository $projects;

	/**
	 * Client repository for fixtures.
	 *
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * Ensure schema exists and start from empty settings.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( Settings::OPTION );
		( new Installer() )->install();

		$this->projects = new Project_Repository();
		$this->clients  = new Client_Repository();
	}

	/**
	 * Create a minimal client for project fixtures.
	 *
	 * @return int Client id.
	 */
	private function make_client(): int {
		$id = $this->clients->create(
			array(
				'display_name' => 'Fixture Client',
				'type'         => Client_Repository::TYPE_COMPANY,
				'status'       => Client_Repository::STATUS_ACTIVE,
			)
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		return $id;
	}

	/**
	 * A project can be created with only a client and a name.
	 */
	public function test_create_minimal_project(): void {
		$client_id = $this->make_client();

		$id = $this->projects->create(
			array(
				'client_id' => $client_id,
				'name'      => 'Website redesign',
			)
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		$row = $this->projects->find( $id );
		$this->assertIsArray( $row );
		$this->assertSame( 'Website redesign', $row['name'] );
		$this->assertSame( (string) $client_id, (string) $row['client_id'] );
		$this->assertSame( Project_Repository::STATUS_ACTIVE, $row['status'] );
		$this->assertSame( Project_Repository::BILLING_FIXED, $row['billing_type'] );
	}

	/**
	 * Deleting a project that still has invoices is refused.
	 */
	public function test_delete_blocked_when_invoice_exists(): void {
		global $wpdb;

		$client_id  = $this->make_client();
		$project_id = $this->projects->create(
			array(
				'client_id' => $client_id,
				'name'      => 'Has invoice',
			)
		);
		$this->assertIsInt( $project_id );

		$table = Schema::table( Schema::INVOICES );
		$now   = current_time( 'mysql' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$table,
			array(
				'invoice_number' => 'TEST-INV-' . $project_id,
				'client_id'      => $client_id,
				'project_id'     => $project_id,
				'status'         => 'draft',
				'currency'       => 'IDR',
				'subtotal_minor' => 0,
				'discount_minor' => 0,
				'tax_minor'      => 0,
				'total_minor'    => 0,
				'paid_minor'     => 0,
				'notes'          => '',
				'terms'          => '',
				'created_by'     => 0,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		// phpcs:enable

		$this->assertNotFalse( $inserted );

		$result = $this->projects->delete( $project_id );

		$this->assertWPError( $result );
		$this->assertSame( 'wp_bizwit_project_in_use', $result->get_error_code() );
		$this->assertNotNull( $this->projects->find( $project_id ) );
	}

	/**
	 * Termin amounts that do not sum to the budget fail without override.
	 */
	public function test_termin_sum_validation_fails_without_override(): void {
		$client_id = $this->make_client();

		$result = $this->projects->create(
			array(
				'client_id'    => $client_id,
				'name'         => 'Termin project',
				'billing_type' => Project_Repository::BILLING_TERMIN,
				'budget_minor' => 1000000,
				'currency'     => 'IDR',
				'terms'        => array(
					array(
						'name'         => 'Termin 1',
						'amount_minor' => 400000,
					),
					array(
						'name'         => 'Termin 2',
						'amount_minor' => 400000,
					),
				),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wp_bizwit_terms_sum_mismatch', $result->get_error_code() );
	}

	/**
	 * Matching termin amounts pass validation.
	 */
	public function test_termin_sum_ok_when_amounts_match_budget(): void {
		$client_id = $this->make_client();

		$id = $this->projects->create(
			array(
				'client_id'    => $client_id,
				'name'         => 'Termin balanced',
				'billing_type' => Project_Repository::BILLING_TERMIN,
				'budget_minor' => 1000000,
				'currency'     => 'IDR',
				'terms'        => array(
					array(
						'name'         => 'DP',
						'amount_minor' => 300000,
					),
					array(
						'name'         => 'Akhir',
						'amount_minor' => 700000,
					),
				),
			)
		);

		$this->assertIsInt( $id );
		$terms = $this->projects->get_terms( $id );
		$this->assertCount( 2, $terms );
		$this->assertSame( 300000, (int) $terms[0]['amount_minor'] );
		$this->assertSame( 700000, (int) $terms[1]['amount_minor'] );
	}

	/**
	 * Override allows termin total to differ from the budget.
	 */
	public function test_termin_sum_ok_with_override(): void {
		$client_id = $this->make_client();

		$id = $this->projects->create(
			array(
				'client_id'          => $client_id,
				'name'               => 'Termin override',
				'billing_type'       => Project_Repository::BILLING_TERMIN,
				'budget_minor'       => 1000000,
				'currency'           => 'IDR',
				'terms_sum_override' => 1,
				'terms'              => array(
					array(
						'name'         => 'Only stage',
						'amount_minor' => 250000,
					),
				),
			)
		);

		$this->assertIsInt( $id );
		$row = $this->projects->find( $id );
		$this->assertIsArray( $row );
		$this->assertSame( '1', (string) $row['terms_sum_override'] );
	}
}
