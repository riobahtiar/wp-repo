<?php
/**
 * Core plugin class — wires hooks, admin screens, assets and optional blocks.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit;

use WP_BizWit\Admin\Assets;
use WP_BizWit\Admin\Menu;
use WP_BizWit\Admin\Screens\Clients_Screen;
use WP_BizWit\Admin\Screens\Dashboard_Screen;
use WP_BizWit\Admin\Screens\Invoices_Screen;
use WP_BizWit\Admin\Screens\Payments_Screen;
use WP_BizWit\Admin\Screens\Projects_Screen;
use WP_BizWit\Admin\Screens\Settings_Screen;
use WP_BizWit\Database\Installer;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Project_Repository;
use WP_BizWit\Repositories\Stats_Repository;
use WP_BizWit\Rest\Controllers\Health_Controller;

/**
 * Boots the plugin: locale, schema check, admin menu, Vite assets, blocks.
 */
class WP_BizWit {


	const PLUGIN_NAME    = 'wp-bizwit';
	const PLUGIN_VERSION = '0.4.0';

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin
	 *
	 * @var Loader
	 */
	protected Loader $loader;

	/**
	 * Wire locale, schema, admin and REST hooks.
	 */
	public function __construct() {

		$this->load_dependencies();
		$this->set_locale();
		$this->define_schema_hooks();
		$this->define_admin_hooks();
		$this->define_rest_hooks();

		$this->loader->add_action( 'init', $this, 'register_blocks' );
	}

	/**
	 * Keep the custom tables in step with the code.
	 *
	 * Activation hooks do not fire when a plugin is updated in place, so the
	 * schema version is checked on every load instead. The check itself is a
	 * single autoloaded option read.
	 *
	 * @return void
	 */
	private function define_schema_hooks(): void {
		$this->loader->add_action( 'plugins_loaded', new Installer(), 'maybe_install', 5 );
	}

	/**
	 * Create the hook loader.
	 */
	private function load_dependencies(): void {
		$this->loader = new Loader();
	}

	/**
	 * Load the plugin text domain.
	 */
	/**
	 * Load translations on `init` so they apply for the current site locale.
	 *
	 * @see https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/
	 *
	 * @return void
	 */
	private function set_locale(): void {
		// Must not load in the constructor — too early for the current locale
		// and just-in-time translation loading (WP 6.5+).
		$this->loader->add_action( 'init', $this, 'load_textdomain', 0 );
	}

	/**
	 * Load the plugin text domain from /languages.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-bizwit',
			false,
			dirname( plugin_basename( __FILE__ ), 2 ) . '/languages'
		);
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 */
	private function define_admin_hooks(): void {

		// Vite product UI: shared admin entry on every BizWit screen; screen-
		// specific entries (dashboard, …) are selected inside Assets::enqueue.
		$this->loader->add_action( 'admin_enqueue_scripts', new Assets(), 'enqueue', 110 );

		$clients  = new Client_Repository();
		$projects = new Project_Repository();
		$stats    = new Stats_Repository();

		// Screens are handed their dependencies here rather than reaching for
		// globals internally. That is what keeps them testable in isolation and
		// keeps the wiring for the whole admin area visible in one place.
		$menu = new Menu(
			new Dashboard_Screen( $stats ),
			new Clients_Screen( $clients, $projects ),
			new Projects_Screen( $projects, $clients ),
			new Invoices_Screen(),
			new Payments_Screen(),
			new Settings_Screen()
		);

		$this->loader->add_action( 'admin_menu', $menu, 'register' );
	}

	/**
	 * Register custom REST routes under wp-bizwit/v1.
	 *
	 * @return void
	 */
	private function define_rest_hooks(): void {
		// Add further controllers here as the Vue shell gains screens; keep
		// permission_callback on every route (see Capabilities).
		$this->loader->add_action( 'rest_api_init', new Health_Controller(), 'register_routes' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return Loader Orchestrates the hooks of the plugin.
	 */
	public function get_loader(): Loader {
		return $this->loader;
	}

	/**
	 * Register any Gutenberg blocks present under build/Blocks (optional).
	 *
	 * No-op when the project has no blocks yet. Uses the metadata collection
	 * API (WP 6.8+) when a manifest exists.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		$manifest_file = WP_BIZWIT_PATH . 'build/blocks-manifest.php';
		$blocks_folder = WP_BIZWIT_PATH . 'build/Blocks';

		if ( ! is_readable( $manifest_file ) || ! is_dir( $blocks_folder ) ) {
			return;
		}

		wp_register_block_types_from_metadata_collection( $blocks_folder, $manifest_file );
	}
}
