<?php
/**
 * Enqueue Vite-built admin assets from the production manifest.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Admin;

use WP_BizWit\Admin\Screens\Dashboard_Screen;
use WP_BizWit\Localization\Regions;
use WP_BizWit\Support\Settings;
use WP_BizWit\WP_BizWit;

/**
 * Loads hashed JS/CSS from build/manifest.json on the BizWit screens that need them.
 *
 * Production always reads local files under build/. Optional HMR is gated on the
 * WP_BIZWIT_VITE_DEV constant (default off) so wordpress.org and production never
 * request localhost.
 */
class Assets {

	/**
	 * Path to the Vite manifest relative to the plugin root.
	 *
	 * @var string
	 */
	private const MANIFEST_FILE = 'build/manifest.json';

	/**
	 * Default Vite dev server origin (no trailing slash).
	 *
	 * @var string
	 */
	private const VITE_DEV_ORIGIN = 'http://localhost:5173';

	/**
	 * Object name for wp_localize_script config.
	 *
	 * @var string
	 */
	private const CONFIG_OBJECT = 'wpBizwitConfig';

	/**
	 * Logical entry name → source path key in the Vite manifest.
	 *
	 * Keys match rollupOptions.input names in vite.config.ts; values are the
	 * absolute-from-root source paths Vite uses as manifest keys.
	 *
	 * @var array<string, string>
	 */
	private const ENTRY_SOURCES = array(
		'admin'     => 'resources/admin/main.ts',
		'dashboard' => 'resources/screens/dashboard.ts',
	);

	/**
	 * Parsed manifest, or null when missing/unreadable.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $manifest = null;

	/**
	 * Whether the manifest has been read at least once this request.
	 *
	 * @var bool
	 */
	private bool $manifest_attempted = false;

	/**
	 * Whether wpBizwitConfig has already been localized this request.
	 *
	 * @var bool
	 */
	private bool $config_localized = false;

	/**
	 * Whether the missing-manifest admin notice was already queued.
	 *
	 * @var bool
	 */
	private bool $missing_notice_queued = false;

	/**
	 * Whether the type="module" filter was registered this request.
	 *
	 * @var bool
	 */
	private bool $module_filter_added = false;

	/**
	 * Enqueue Vite entries for the current admin screen.
	 *
	 * Hooked to `admin_enqueue_scripts`. Loads shared `admin` styles/JS on every
	 * BizWit page; dashboard also gets the `dashboard` island entry. Non-BizWit
	 * admin screens receive nothing.
	 *
	 * @param string $hook Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue( string $hook = '' ): void {
		if ( ! $this->is_bizwit_admin_page( $hook ) ) {
			return;
		}

		// Shared product styles + tiny bootstrap on every BizWit screen.
		$this->enqueue_entry( 'admin' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page slug for asset selection.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';

		if ( Dashboard_Screen::SLUG === $page ) {
			$this->enqueue_entry( 'dashboard' );
		}
	}

	/**
	 * Enqueue one Vite logical entry (JS + CSS) by name.
	 *
	 * @param string   $entry             Logical name: admin, dashboard, etc.
	 * @param string[] $extra_script_deps Additional script handles to depend on.
	 *
	 * @return void
	 */
	public function enqueue_entry( string $entry, array $extra_script_deps = array() ): void {
		if ( $this->is_vite_dev() ) {
			$this->enqueue_dev_entry( $entry, $extra_script_deps );
			return;
		}

		$chunk = $this->resolve_entry_chunk( $entry );
		if ( null === $chunk ) {
			$this->maybe_notice_missing_manifest( $entry );
			return;
		}

		$this->enqueue_chunk( $entry, $chunk, $extra_script_deps );
	}

	/**
	 * Whether the current request is a BizWit admin screen.
	 *
	 * @param string $hook Admin page hook suffix from admin_enqueue_scripts.
	 *
	 * @return bool
	 */
	private function is_bizwit_admin_page( string $hook ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page slug for asset selection.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';

		if ( '' !== $page && ( Dashboard_Screen::SLUG === $page || str_starts_with( $page, 'wp-bizwit-' ) ) ) {
			return true;
		}

		return '' !== $hook && ( str_contains( $hook, 'wp-bizwit' ) );
	}

	/**
	 * Whether Vite HMR mode is enabled.
	 *
	 * @return bool
	 */
	private function is_vite_dev(): bool {
		return defined( 'WP_BIZWIT_VITE_DEV' ) && WP_BIZWIT_VITE_DEV;
	}

	/**
	 * Load entry + Vite client from the local dev server.
	 *
	 * @param string   $entry             Logical entry name.
	 * @param string[] $extra_script_deps Extra script dependencies.
	 *
	 * @return void
	 */
	private function enqueue_dev_entry( string $entry, array $extra_script_deps ): void {
		$source = self::ENTRY_SOURCES[ $entry ] ?? null;
		if ( null === $source ) {
			return;
		}

		$origin = self::VITE_DEV_ORIGIN;
		if ( defined( 'WP_BIZWIT_VITE_ORIGIN' ) && is_string( WP_BIZWIT_VITE_ORIGIN ) && '' !== WP_BIZWIT_VITE_ORIGIN ) {
			$origin = rtrim( WP_BIZWIT_VITE_ORIGIN, '/' );
		}

		$this->ensure_module_filter();

		// Handles use hyphens (not slashes) so script translation JSON can be
		// named `{domain}-{locale}-{handle}.json` per Gutenberg / wp-i18n docs.
		$client_handle = 'wp-bizwit-vite-client';
		// HMR assets: null version is intentional so the browser always revalidates.
		// phpcs:disable WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script(
			$client_handle,
			$origin . '/@vite/client',
			array(),
			null,
			true
		);
		wp_script_add_data( $client_handle, 'type', 'module' );

		$handle = 'wp-bizwit-' . $entry;
		// Gutenberg pattern: declare wp-i18n, then set script translations.
		$deps = array_merge( array( 'wp-i18n', $client_handle ), $extra_script_deps );

		wp_enqueue_script(
			$handle,
			$origin . '/' . $source,
			$deps,
			null,
			true
		);
		// phpcs:enable WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_script_add_data( $handle, 'type', 'module' );
		wp_set_script_translations( $handle, 'wp-bizwit', WP_BIZWIT_PATH . 'languages' );

		$this->localize_config( $handle );
	}

	/**
	 * Resolve a logical entry name to its manifest chunk array.
	 *
	 * @param string $entry Logical entry name.
	 *
	 * @return array<string, mixed>|null Chunk data or null when not found.
	 */
	private function resolve_entry_chunk( string $entry ): ?array {
		$manifest = $this->get_manifest();
		if ( null === $manifest ) {
			return null;
		}

		$source = self::ENTRY_SOURCES[ $entry ] ?? null;
		if ( null !== $source && isset( $manifest[ $source ] ) && is_array( $manifest[ $source ] ) ) {
			return $manifest[ $source ];
		}

		if ( isset( $manifest[ $entry ] ) && is_array( $manifest[ $entry ] ) ) {
			return $manifest[ $entry ];
		}

		foreach ( $manifest as $chunk ) {
			if ( ! is_array( $chunk ) ) {
				continue;
			}
			$name     = isset( $chunk['name'] ) && is_string( $chunk['name'] ) ? $chunk['name'] : '';
			$is_entry = ! empty( $chunk['isEntry'] );
			if ( $is_entry && $name === $entry ) {
				return $chunk;
			}
		}

		return null;
	}

	/**
	 * Register and enqueue JS/CSS for one production chunk (and its imports).
	 *
	 * @param string               $entry             Logical entry name (handle base).
	 * @param array<string, mixed> $chunk             Manifest chunk.
	 * @param string[]             $extra_script_deps Extra script dependencies.
	 *
	 * @return void
	 */
	private function enqueue_chunk( string $entry, array $chunk, array $extra_script_deps ): void {
		$this->ensure_module_filter();

		$deps = $extra_script_deps;

		// Shared chunks listed under imports must load first (as modules).
		if ( isset( $chunk['imports'] ) && is_array( $chunk['imports'] ) ) {
			foreach ( $chunk['imports'] as $import_key ) {
				if ( ! is_string( $import_key ) ) {
					continue;
				}
				$import_handle = $this->enqueue_import( $import_key );
				if ( null !== $import_handle ) {
					$deps[] = $import_handle;
				}
			}
		}

		$file = isset( $chunk['file'] ) && is_string( $chunk['file'] ) ? $chunk['file'] : '';
		if ( '' === $file ) {
			return;
		}

		// Hyphenated handle: Gutenberg docs use handle-based JSON filenames
		// `{domain}-{locale}-{handle}.json` (slashes in handles break paths).
		$handle  = 'wp-bizwit-' . $entry;
		$version = $this->asset_version( $file );
		$url     = WP_BIZWIT_URL . 'build/' . ltrim( $file, '/' );

		// Same as Gutenberg: wp-i18n dependency + wp_set_script_translations().
		$deps = array_values( array_unique( array_merge( array( 'wp-i18n' ), $deps ) ) );

		wp_enqueue_script(
			$handle,
			$url,
			$deps,
			$version,
			true
		);
		wp_script_add_data( $handle, 'type', 'module' );

		// Optional third arg = languages dir (shipped translations, not only wordpress.org).
		wp_set_script_translations( $handle, 'wp-bizwit', WP_BIZWIT_PATH . 'languages' );

		$this->localize_config( $handle );
		$this->enqueue_chunk_styles( $entry, $chunk, $version );
	}

	/**
	 * Enqueue a shared import chunk; returns its script handle.
	 *
	 * @param string $import_key Manifest key for the imported chunk.
	 *
	 * @return string|null Script handle or null on failure.
	 */
	private function enqueue_import( string $import_key ): ?string {
		$manifest = $this->get_manifest();
		if ( null === $manifest || ! isset( $manifest[ $import_key ] ) || ! is_array( $manifest[ $import_key ] ) ) {
			return null;
		}

		$chunk = $manifest[ $import_key ];
		$file  = isset( $chunk['file'] ) && is_string( $chunk['file'] ) ? $chunk['file'] : '';
		if ( '' === $file ) {
			return null;
		}

		$handle  = 'wp-bizwit-import-' . sanitize_title( $import_key );
		$version = $this->asset_version( $file );

		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script(
				$handle,
				WP_BIZWIT_URL . 'build/' . ltrim( $file, '/' ),
				array( 'wp-i18n' ),
				$version,
				true
			);
			wp_script_add_data( $handle, 'type', 'module' );
		}

		wp_enqueue_script( $handle );

		// CSS attached to imported chunks.
		$this->enqueue_chunk_styles( 'import-' . sanitize_title( $import_key ), $chunk, $version );

		return $handle;
	}

	/**
	 * Enqueue CSS files listed on a manifest chunk.
	 *
	 * @param string               $entry   Handle suffix base.
	 * @param array<string, mixed> $chunk   Manifest chunk.
	 * @param string               $version Cache-busting version.
	 *
	 * @return void
	 */
	private function enqueue_chunk_styles( string $entry, array $chunk, string $version ): void {
		if ( ! isset( $chunk['css'] ) || ! is_array( $chunk['css'] ) ) {
			return;
		}

		$index = 0;
		foreach ( $chunk['css'] as $css_file ) {
			if ( ! is_string( $css_file ) || '' === $css_file ) {
				continue;
			}

			$style_handle = 0 === $index
				? 'wp-bizwit-' . $entry
				: 'wp-bizwit-' . $entry . '-' . (string) $index;

			wp_enqueue_style(
				$style_handle,
				WP_BIZWIT_URL . 'build/' . ltrim( $css_file, '/' ),
				array(),
				$version
			);
			++$index;
		}
	}

	/**
	 * Attach public REST/runtime config once per request.
	 *
	 * No secrets — only URL, nonce, and non-sensitive settings for the SPA.
	 *
	 * @param string $script_handle Handle that receives the localized object.
	 *
	 * @return void
	 */
	private function localize_config( string $script_handle ): void {
		if ( $this->config_localized ) {
			return;
		}

		wp_localize_script(
			$script_handle,
			self::CONFIG_OBJECT,
			array(
				'restUrl'   => esc_url_raw( rest_url( 'wp-bizwit/v1' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'pluginUrl' => WP_BIZWIT_URL,
				'version'   => WP_BizWit::PLUGIN_VERSION,
				'locale'    => get_user_locale(),
				'region'    => Regions::current()->code(),
				'currency'  => Settings::currency(),
			)
		);

		$this->config_localized = true;
	}

	/**
	 * Read and cache build/manifest.json.
	 *
	 * @return array<string, mixed>|null Decoded manifest or null.
	 */
	private function get_manifest(): ?array {
		if ( $this->manifest_attempted ) {
			return $this->manifest;
		}

		$this->manifest_attempted = true;

		$path = WP_BIZWIT_PATH . self::MANIFEST_FILE;
		if ( ! is_readable( $path ) ) {
			$this->manifest = null;
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin build artifact.
		$raw = file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			$this->manifest = null;
			return null;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			$this->manifest = null;
			return null;
		}

		$this->manifest = $decoded;

		return $this->manifest;
	}

	/**
	 * Cache-busting version: filemtime when available, else plugin version.
	 *
	 * @param string $relative_file Path under build/ from the manifest.
	 *
	 * @return string
	 */
	private function asset_version( string $relative_file ): string {
		$path = WP_BIZWIT_PATH . 'build/' . ltrim( $relative_file, '/' );
		if ( is_readable( $path ) ) {
			$mtime = filemtime( $path );
			if ( false !== $mtime ) {
				return (string) $mtime;
			}
		}

		return WP_BizWit::PLUGIN_VERSION;
	}

	/**
	 * Ensure enqueued Vite scripts get type="module" (classic WP script tags).
	 *
	 * @return void
	 */
	private function ensure_module_filter(): void {
		if ( $this->module_filter_added ) {
			return;
		}

		$this->module_filter_added = true;

		add_filter(
			'script_loader_tag',
			static function ( string $tag, string $handle ): string {
				if ( ! str_starts_with( $handle, 'wp-bizwit-' ) ) {
					return $tag;
				}
				if ( str_contains( $tag, 'type=' ) ) {
					return $tag;
				}

				return (string) preg_replace( '/<script\b/', '<script type="module"', $tag, 1 );
			},
			10,
			2
		);
	}

	/**
	 * Soft-fail when the production manifest is missing.
	 *
	 * @param string $entry Entry that could not be resolved.
	 *
	 * @return void
	 */
	private function maybe_notice_missing_manifest( string $entry ): void {
		if ( $this->missing_notice_queued ) {
			return;
		}

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->missing_notice_queued = true;

		add_action(
			'admin_notices',
			static function () use ( $entry ): void {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: Vite entry name (e.g. dashboard). */
							__( 'WP BizWit: Vite asset manifest is missing or incomplete (entry: %s). Run npm run build.', 'wp-bizwit' ),
							$entry
						)
					)
				);
			}
		);
	}
}
