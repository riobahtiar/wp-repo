<?php
/**
 * Repositories must not accept unexpected column names from input.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Database\Installer;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Support\Settings;

/**
 * Mass-assignment guard.
 */
class MassAssignmentTest extends WP_UnitTestCase {

	/**
	 * @var Client_Repository
	 */
	private Client_Repository $clients;

	/**
	 * Install schema.
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
	 * Unknown keys in the input array never become database columns.
	 *
	 * @return void
	 */
	public function test_client_rejects_unknown_fields(): void {
		$id = $this->clients->create(
			array(
				'display_name'      => 'Safe Client',
				'not_a_real_column' => 'injection',
				'created_by'        => 99999, // stripped/overridden by repository.
			)
		);

		$this->assertIsInt( $id );
		$row = $this->clients->find( $id );
		$this->assertIsArray( $row );
		$this->assertArrayNotHasKey( 'not_a_real_column', $row );
		$this->assertSame( 'Safe Client', $row['display_name'] );
	}

	/**
	 * Orderby whitelist blocks SQL injection via sort parameter.
	 *
	 * @return void
	 */
	public function test_orderby_whitelist(): void {
		$this->clients->create( array( 'display_name' => 'Alpha' ) );
		$this->clients->create( array( 'display_name' => 'Beta' ) );

		$result = $this->clients->query(
			array(
				'orderby'  => 'display_name; DROP TABLE students;--',
				'order'    => 'ASC',
				'per_page' => 10,
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
		// Still returns rows — malicious orderby fell back to default.
		$this->assertGreaterThanOrEqual( 2, count( $result['items'] ) );
	}
}
