import path from 'node:path';
import { fileURLToPath } from 'node:url';

import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';

const rootDir = path.dirname( fileURLToPath( import.meta.url ) );

/**
 * Vite multi-entry build for wp-admin product UI.
 *
 * Production assets land in `build/` and are enqueued by PHP (Phase 2) via
 * plugins_url( 'build/…', WP_BIZWIT_FILE ). base './' keeps asset URLs relative
 * to the built HTML/manifest so the plugin path stays portable.
 *
 * Dev: `npm run dev` — HMR server; PHP enqueue for dev is also Phase 2.
 */
export default defineConfig( {
	plugins: [ vue(), tailwindcss() ],
	root: rootDir,
	base: './',
	build: {
		outDir: 'build',
		// false: `npm run build` cleans once, runs webpack, then Vite — wiping
		// here would delete legacy `wp-bizwit-admin.*` that PHP still enqueues.
		emptyOutDir: false,
		// Explicit path so the file is `build/manifest.json` (not `.vite/`).
		manifest: 'manifest.json',
		rollupOptions: {
			input: {
				admin: path.resolve( rootDir, 'resources/admin/main.ts' ),
				dashboard: path.resolve( rootDir, 'resources/screens/dashboard.ts' ),
			},
		},
	},
	resolve: {
		alias: {
			'@': path.resolve( rootDir, 'resources' ),
			'@ui': path.resolve( rootDir, 'resources/ui' ),
		},
	},
} );
