<?php
/**
 * Schema installer and upgrade path.
 *
 * @package WP_BizWit
 */

use WP_BizWit\Database\Installer;
use WP_BizWit\Database\Schema;

/**
 * Versioned migrations keep tables current after in-place updates.
 */
class InstallerUpgradeTest extends WP_UnitTestCase {

	/**
	 * Fresh install records the current schema version and creates all tables.
	 *
	 * @return void
	 */
	public function test_fresh_install_sets_version_and_tables(): void {
		delete_option( Installer::VERSION_OPTION );
		( new Installer() )->install();

		$this->assertSame( Installer::DB_VERSION, get_option( Installer::VERSION_OPTION ) );

		global $wpdb;
		foreach ( Schema::tables() as $unprefixed ) {
			$table = Schema::table( $unprefixed );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			$this->assertSame( $table, $found, "Missing table {$unprefixed}" );
		}
	}

	/**
	 * Maybe_install upgrades from an older recorded version (e.g. 1.4.0 → current).
	 *
	 * @return void
	 */
	public function test_maybe_install_upgrades_from_1_4(): void {
		global $wpdb;

		( new Installer() )->install();

		// Simulate a site still on 1.4.0 without the activity table.
		update_option( Installer::VERSION_OPTION, '1.4.0' );
		$activity = Schema::table( Schema::ACTIVITY );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$activity}`" );

		( new Installer() )->maybe_install();

		$this->assertSame( Installer::DB_VERSION, get_option( Installer::VERSION_OPTION ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $activity ) );
		$this->assertSame( $activity, $found );
	}

	/**
	 * Maybe_install is a no-op when already current (does not rewrite the option).
	 *
	 * @return void
	 */
	public function test_maybe_install_noop_when_current(): void {
		( new Installer() )->install();
		$before = get_option( Installer::VERSION_OPTION );
		( new Installer() )->maybe_install();
		$this->assertSame( $before, get_option( Installer::VERSION_OPTION ) );
	}
}
