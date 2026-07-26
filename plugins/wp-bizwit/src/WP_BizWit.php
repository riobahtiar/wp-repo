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
use WP_BizWit\Repositories\Stats_Repository;

/**
 * Boots the plugin: locale, schema check, admin menu, public assets, blocks.
 */
class WP_BizWit {


	const PLUGIN_NAME    = 'wp-bizwit';
	const PLUGIN_VERSION = '0.3.0';

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin
	 *
	 * @var Loader
	 */
	protected Loader $loader;

	/**
	 * Wire locale, schema, admin and public hooks.
	 */
	public function __construct() {

		$this->load_dependencies();
		$this->set_locale();
		$this->define_schema_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();

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
	private function set_locale(): void {
		load_plugin_textdomain(
			'wp-bizwit',
			false,
			dirname( plugin_basename( __FILE__ ), 2 ) . '/languages/'
		);
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 */
	private function define_admin_hooks(): void {

		// Legacy webpack admin CSS/JS (wp-bizwit-admin.*) until Phase 6 retires it.
		add_action(
			'admin_enqueue_scripts',
			function () {
				$this->enqueue_entrypoint( 'wp-bizwit-admin' );
			},
			100
		);

		// Vite product UI: only the entry for the current BizWit screen (see Assets).
		$this->loader->add_action( 'admin_enqueue_scripts', new Assets(), 'enqueue', 110 );

		$clients = new Client_Repository();
		$stats   = new Stats_Repository();

		// Screens are handed their dependencies here rather than reaching for
		// globals internally. That is what keeps them testable in isolation and
		// keeps the wiring for the whole admin area visible in one place.
		$menu = new Menu(
			new Dashboard_Screen( $stats ),
			new Clients_Screen( $clients ),
			new Projects_Screen(),
			new Invoices_Screen(),
			new Payments_Screen(),
			new Settings_Screen()
		);

		$this->loader->add_action( 'admin_menu', $menu, 'register' );
	}

	/**
	 * Register public-facing asset hooks.
	 */
	private function define_public_hooks(): void {

		add_action(
			'wp_enqueue_scripts',
			function () {
				$this->enqueue_entrypoint( 'wp-bizwit-frontend' );
			},
			100
		);
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
	 * Enqueue a webpack entrypoint
	 *
	 * @param string              $entry Name of the entrypoint defined in webpack.config.js.
	 * @param array<string,mixed> $localize_data Array of associated data. See https://developer.wordpress.org/reference/functions/wp_localize_script/ .
	 */
	private function enqueue_entrypoint( string $entry, array $localize_data = array() ): void {

		// Try to get WordPress filesystem. If not possible load it.
		global $wp_filesystem;
		if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound
			WP_Filesystem();
		}

		$filesystem = new \WP_Filesystem_Direct( false );

		$asset_file = WP_BIZWIT_PATH . "/build/{$entry}.asset.php";
		if ( ! $filesystem->exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;
		if ( ! isset( $asset['dependencies'], $asset['version'] ) ) {
			return;
		}

		if ( $filesystem->exists( WP_BIZWIT_PATH . "build/{$entry}.js" ) ) {
			wp_enqueue_script(
				self::PLUGIN_NAME . "/{$entry}",
				WP_BIZWIT_URL . "build/{$entry}.js",
				$asset['dependencies'],
				$asset['version'],
				true
			);

			// Potentially add localize data
			if ( ! empty( $localize_data ) ) {
				wp_localize_script(
					self::PLUGIN_NAME . "/{$entry}",
					str_replace( '-', '_', self::PLUGIN_NAME ),
					$localize_data
				);
			}

			// Load JSON translations
			wp_set_script_translations( self::PLUGIN_NAME . "/{$entry}", 'wp-bizwit', WP_BIZWIT_PATH . 'languages/' );
		}

		if ( $filesystem->exists( WP_BIZWIT_PATH . "build/{$entry}.css" ) ) {
			wp_enqueue_style(
				self::PLUGIN_NAME . "/{$entry}",
				WP_BIZWIT_URL . "build/{$entry}.css",
				array(),
				$asset['version']
			);
		}
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
