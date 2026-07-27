<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @package           WP_BizWit
 *
 * @wordpress-plugin
 * Plugin Name:       WP BizWit
 * Description:       Business administration for Indonesian companies and UMKM: clients, projects, invoices and receipts (kwitansi) with NPWP, NIB, PKP/PPN, PPh 23, terbilang and stamp duty. Record-keeping only — never processes payments.
 * Version:           1.1.1
 * Requires PHP:      8.0
 * Requires at least: 6.9
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-bizwit
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
use WP_BizWit\Activator;
use WP_BizWit\Deactivator;
use WP_BizWit\WP_BizWit;
use WP_BizWit\Uninstallor;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin absolute path
 */
define( 'WP_BIZWIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_BIZWIT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Use Composer PSR-4 Autoloading
 */
require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

/**
 * Public version constant for companion plugins to detect this plugin and
 * version-gate against it. Mirrors WP_BizWit::PLUGIN_VERSION, the single
 * source of truth.
 */
if ( ! defined( 'WP_BIZWIT_VERSION' ) ) {
	define( 'WP_BIZWIT_VERSION', WP_BizWit::PLUGIN_VERSION );
}

/**
 * Fires after this plugin has loaded and registered its hooks, so companion
 * plugins can attach to its extension points. Hooked to `plugins_loaded` to
 * stay independent of plugin load order.
 *
 * @param string $version The current plugin version.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		do_action( 'wp_bizwit_loaded', WP_BizWit::PLUGIN_VERSION );
	},
	0
);

/**
 * The code that runs during plugin activation.
 */
function wp_bizwit_activate(): void {
	Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function wp_bizwit_deactivate(): void {
	Deactivator::deactivate();
}

/**
 * The code that runs during plugin uninstallation.
 */
function wp_bizwit_uninstall(): void {
	Uninstallor::uninstall();
}

register_activation_hook( __FILE__, 'wp_bizwit_activate' );
register_deactivation_hook( __FILE__, 'wp_bizwit_deactivate' );
register_uninstall_hook( __FILE__, 'wp_bizwit_uninstall' );
add_action( 'activated_plugin', array( Activator::class, 'network_activation' ), 10, 2 );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function wp_bizwit_run(): void {
	$plugin = new WP_BizWit();
	$plugin->run();
}
wp_bizwit_run();
