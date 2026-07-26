<?php
/**
 * Fired during plugin uninstallation
 *
 * @package    WP_BizWit
 */

namespace WP_BizWit;

use WP_BizWit\Database\Installer;
use WP_BizWit\Support\Capabilities;
use WP_BizWit\Support\Settings;

/**
 * Fired during plugin uninstallation.
 *
 * This class defines all code that should be run during the uninstallation of the plugin
 *
 * @package    WP_BizWit
 */
class Uninstallor {

	/**
	 * This is the general callback run during the 'uninstall_wp_bizwit' register_uninstall_hook.
	 *
	 * Roles and capabilities are always cleaned up, because leaving orphaned
	 * roles behind clutters every user profile on the site. Business data is only
	 * dropped when the site owner explicitly asked for it in settings: an
	 * accidental uninstall should never be able to destroy years of invoices.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		Capabilities::uninstall();

		if ( ! Settings::get( 'delete_data_on_uninstall', false ) ) {
			return;
		}

		( new Installer() )->drop_tables();

		delete_option( Settings::OPTION );
	}
}
