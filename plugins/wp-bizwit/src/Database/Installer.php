<?php
/**
 * Versioned installer and migration runner for the plugin's custom tables.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Database;

/**
 * Creates and upgrades the plugin's schema.
 *
 * Activation hooks only fire on activation, which means a plugin updated in
 * place (via WP-CLI, a deploy, or the updater) never runs them. The version
 * check on `plugins_loaded` is what actually keeps the schema current.
 */
class Installer {

	/**
	 * Schema version. Bump this whenever Schema::statements() changes.
	 *
	 * @var string
	 */
	public const DB_VERSION = '1.4.0';

	/**
	 * Option name holding the installed schema version.
	 *
	 * @var string
	 */
	public const VERSION_OPTION = 'wp_bizwit_db_version';

	/**
	 * Run the installer only when the stored version is behind the code.
	 *
	 * Safe and cheap to call on every request: the common path is a single
	 * autoloaded option read and a string comparison.
	 *
	 * @return void
	 */
	public function maybe_install(): void {
		$installed = get_option( self::VERSION_OPTION, '' );

		if ( is_string( $installed ) && version_compare( $installed, self::DB_VERSION, '>=' ) ) {
			return;
		}

		$this->install();
	}

	/**
	 * Create or upgrade every table, then record the schema version.
	 *
	 * @return void
	 */
	public function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php'; // @phpstan-ignore requireOnce.fileNotFound

		foreach ( Schema::statements() as $statement ) {
			dbDelta( $statement );
		}

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Drop every table this plugin owns.
	 *
	 * Only ever called from the uninstall routine, and only when the site owner
	 * has explicitly opted into data removal.
	 *
	 * @return void
	 */
	public function drop_tables(): void {
		global $wpdb;

		foreach ( Schema::tables() as $table ) {
			$name = Schema::table( $table );

			// Table names cannot be bound as prepared-statement placeholders, and
			// $name is built from a hardcoded constant plus $wpdb->prefix.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS `{$name}`" );
		}

		delete_option( self::VERSION_OPTION );
	}
}
