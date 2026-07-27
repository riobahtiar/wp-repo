<?php
/**
 * Tests for the Indonesian bank catalogue.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Localization\Indonesia_Banks;
use WP_BizWit\Support\Payment_Destinations;

/**
 * Vendored bank list from data-bank-indonesia.
 */
class IndonesiaBanksTest extends WP_UnitTestCase {

	/**
	 * Clear cache.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		Indonesia_Banks::reset_cache();
	}

	/**
	 * Catalogue loads and includes major banks.
	 *
	 * @return void
	 */
	public function test_loads_catalogue(): void {
		$this->assertGreaterThan( 50, Indonesia_Banks::count() );
		$bca = Indonesia_Banks::find_by_code( '014' );
		$this->assertNotNull( $bca );
		$this->assertStringContainsString( 'BCA', $bca['name'] );
	}

	/**
	 * Find by name matches abbreviations.
	 *
	 * @return void
	 */
	public function test_find_by_name_bca(): void {
		$hit = Indonesia_Banks::find_by_name( 'BCA' );
		$this->assertNotNull( $hit );
		$this->assertSame( '014', $hit['code'] );
	}

	/**
	 * Sanitize destination resolves bank code to official name.
	 *
	 * @return void
	 */
	public function test_destination_resolves_bank_code(): void {
		$row = Payment_Destinations::sanitize_one(
			array(
				'type'         => 'bank_transfer',
				'enabled'      => '1',
				'bank_code'    => '014',
				'account_no'   => '123',
				'account_name' => 'PT Demo',
			)
		);
		$this->assertIsArray( $row );
		$this->assertSame( '014', $row['bank_code'] );
		$this->assertStringContainsString( 'BCA', $row['bank_name'] );

		$lines = Payment_Destinations::detail_lines( $row );
		$this->assertNotEmpty( $lines );
		$this->assertStringContainsString( '014', $lines[0] );
	}

	/**
	 * Groups are non-empty for primary types.
	 *
	 * @return void
	 */
	public function test_grouped_has_state_and_private(): void {
		$groups = Indonesia_Banks::grouped();
		$this->assertArrayHasKey( 'state', $groups );
		$this->assertArrayHasKey( 'private', $groups );
		$this->assertNotEmpty( $groups['state'] );
	}
}
