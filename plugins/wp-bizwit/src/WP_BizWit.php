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
use WP_BizWit\Cron\Overdue_Invoices;
use WP_BizWit\Database\Installer;
use WP_BizWit\Documents\Default_Templates;
use WP_BizWit\Documents\Document_Blocks;
use WP_BizWit\Documents\Template_Post_Type;
use WP_BizWit\Repositories\Activity_Repository;
use WP_BizWit\Repositories\Client_Repository;
use WP_BizWit\Repositories\Invoice_Repository;
use WP_BizWit\Repositories\Payment_Repository;
use WP_BizWit\Repositories\Project_Repository;
use WP_BizWit\Repositories\Stats_Repository;
use WP_BizWit\Rest\Controllers\Health_Controller;
use WP_BizWit\Support\Capabilities;

/**
 * Boots the plugin: locale, schema check, admin menu, Vite assets, blocks.
 */
class WP_BizWit {


	const PLUGIN_NAME    = 'wp-bizwit';
	const PLUGIN_VERSION = '1.1.1';

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
		$this->define_cron_hooks();
		$this->define_document_hooks();

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
		// Roles/caps live in the DB; re-sync after plugin updates without re-activation.
		$this->loader->add_action( 'plugins_loaded', $this, 'maybe_install_capabilities', 6 );
	}

	/**
	 * Ensure BizWit capabilities exist on administrators and plugin roles.
	 *
	 * @return void
	 */
	public function maybe_install_capabilities(): void {
		Capabilities::maybe_install();
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
		$invoices = new Invoice_Repository();
		$payments = new Payment_Repository();
		$stats    = new Stats_Repository();
		$activity = new Activity_Repository();

		// Audit trail + stats-cache invalidation — listen to repository actions.
		$activity->register_hooks();

		// Screens are handed their dependencies here rather than reaching for
		// globals internally. That is what keeps them testable in isolation and
		// keeps the wiring for the whole admin area visible in one place.
		$menu = new Menu(
			new Dashboard_Screen( $stats, $activity ),
			new Clients_Screen( $clients, $projects ),
			new Projects_Screen( $projects, $clients ),
			new Invoices_Screen( $invoices, $clients, $projects ),
			new Payments_Screen( $payments, $invoices, $clients ),
			new Settings_Screen()
		);

		$this->loader->add_action( 'admin_menu', $menu, 'register' );
	}

	/**
	 * Schedule and run background jobs (overdue invoices, …).
	 *
	 * @return void
	 */
	private function define_cron_hooks(): void {
		$overdue = new Overdue_Invoices();
		$this->loader->add_action( 'init', $overdue, 'schedule' );
		$this->loader->add_action( Overdue_Invoices::HOOK, $overdue, 'run' );
	}

	/**
	 * Document templates (Gutenberg Header / Body / Footer) and merge blocks.
	 *
	 * @return void
	 */
	private function define_document_hooks(): void {
		( new Template_Post_Type() )->register();
		// Legacy Gutenberg document blocks remain registered for older content.
		( new Document_Blocks() )->register();

		// Seed sample default invoice template after CPT exists (admin only).
		$this->loader->add_action( 'admin_init', new Default_Templates(), 'maybe_seed', 20 );
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
