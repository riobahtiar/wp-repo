<?php
/**
 * Fired during plugin activation
 *
 * @package    WP_BizWit
 */

namespace WP_BizWit;

use WP_BizWit\Database\Installer;
use WP_BizWit\Support\Capabilities;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @link https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 * @package    WP_BizWit
 */
class Activator {

	/**
	 * This is the general callback run during the 'register_activation_hook' hook.
	 *
	 * @return void
	 */
	public static function activate(): void {
		( new Installer() )->install();

		// Roles are persisted in the options table, so they are written once on
		// activation rather than rebuilt on every request.
		Capabilities::install();
	}

	/**
	 * Add logic to the activation on a network site.
	 *
	 * @param string $plugin Plugin file loaded.
	 * @param bool   $network_wide Indicates if loaded network wide.
	 * @return void
	 */
	public static function network_activation( string $plugin, bool $network_wide ): void {

		if ( ! str_contains( $plugin, WP_BizWit::PLUGIN_NAME ) || ! $network_wide ) {
			return;
		}

		// phpcs:disable Squiz.PHP.CommentedOutCode.Found

		// Network deactivate
		// deactivate_plugins( $plugin, false, true );

		// Activate on single site
		// activate_plugins( $plugin );

		// phpcs:enable
	}
}
