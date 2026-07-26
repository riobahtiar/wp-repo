/// <reference types="vitest/config" />
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import externalGlobals from 'rollup-plugin-external-globals';
import { defineConfig, type PluginOption } from 'vite';

const rootDir = path.dirname( fileURLToPath( import.meta.url ) );
const analyze = process.env.ANALYZE === '1' || process.env.ANALYZE === 'true';

/**
 * Vite multi-entry build for wp-admin product UI.
 *
 * WordPress script packages (Gutenberg i18n pattern):
 * - Source: `import { __ } from '@wordpress/i18n'`
 * - Runtime: WordPress enqueues `wp-i18n` → `window.wp.i18n`
 * - Build: externalize `@wordpress/i18n` so it is not bundled (same idea as
 *   @wordpress/dependency-extraction-webpack-plugin in Gutenberg).
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/how-to-guides/internationalization.md
 */
export default defineConfig( async () => {
	const plugins: PluginOption[] = [
		vue(),
		tailwindcss(),
		// Map ESM imports of @wordpress/i18n onto the global provided by WP core.
		externalGlobals( {
			'@wordpress/i18n': 'wp.i18n',
		} ) as PluginOption,
	];

	if ( analyze ) {
		const { visualizer } = await import( 'rollup-plugin-visualizer' );
		plugins.push(
			visualizer( {
				filename: path.resolve( rootDir, 'build/stats.html' ),
				gzipSize: true,
				brotliSize: true,
				open: false,
				template: 'treemap',
			} )
		);
	}

	return {
		plugins,
		root: rootDir,
		base: './',
		build: {
			outDir: 'build',
			emptyOutDir: true,
			manifest: 'manifest.json',
			rollupOptions: {
				input: {
					admin: path.resolve( rootDir, 'resources/admin/main.ts' ),
					dashboard: path.resolve(
						rootDir,
						'resources/screens/dashboard.ts'
					),
					'template-builder': path.resolve(
						rootDir,
						'resources/screens/template-builder.ts'
					),
				},
				// Do not ship a second copy of @wordpress/i18n — use core's.
				external: [ '@wordpress/i18n' ],
			},
		},
		resolve: {
			alias: {
				'@': path.resolve( rootDir, 'resources' ),
				'@ui': path.resolve( rootDir, 'resources/ui' ),
			},
		},
		// Vitest: use the real package (Node), not the WP global.
		optimizeDeps: {
			include: [ '@wordpress/i18n' ],
		},
		test: {
			include: [ 'resources/**/*.test.ts' ],
			environment: 'node',
		},
	};
} );
