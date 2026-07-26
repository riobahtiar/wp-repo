#!/usr/bin/env node
/**
 * Soft bundle-size gate for WP BizWit production assets.
 *
 * Reads build/assets (and build/manifest.json when present), reports raw and
 * gzip sizes against the budgets in docs/frontend-architecture.md, and always
 * exits 0 for now (warnings only). Flip FAIL_HARD=1 later to fail CI.
 *
 * Usage: node scripts/check-bundle-size.mjs
 *        (after npm run build)
 */

import { createGzip } from 'node:zlib';
import { createReadStream, existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { pipeline } from 'node:stream/promises';
import { Writable } from 'node:stream';

const rootDir = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const assetsDir = path.join( rootDir, 'build', 'assets' );
const manifestPath = path.join( rootDir, 'build', 'manifest.json' );

/** Budgets from docs/frontend-architecture.md (gzipped KiB). */
const BUDGETS_KIB = {
	adminJs: 60,
	dashboardJs: 50,
	css: 25,
};

const FAIL_HARD = process.env.FAIL_HARD === '1' || process.env.FAIL_HARD === 'true';

/**
 * @param {string} filePath
 * @returns {Promise<number>} gzip size in bytes
 */
async function gzipSize( filePath ) {
	let size = 0;
	const counter = new Writable( {
		write( chunk, _enc, cb ) {
			size += chunk.length;
			cb();
		},
	} );
	await pipeline( createReadStream( filePath ), createGzip( { level: 9 } ), counter );
	return size;
}

/**
 * @param {number} bytes
 * @returns {string}
 */
function formatKiB( bytes ) {
	return ( bytes / 1024 ).toFixed( 2 ) + ' KiB';
}

/**
 * Classify a built asset file name into a budget bucket.
 *
 * @param {string} name
 * @returns {'adminJs'|'dashboardJs'|'css'|'other'}
 */
function classify( name ) {
	if ( /\.css$/i.test( name ) ) {
		return 'css';
	}
	if ( ! /\.js$/i.test( name ) ) {
		return 'other';
	}
	// Vite names: admin-HASH.js, dashboard-HASH.js
	if ( /^admin[-.]/i.test( name ) || name.startsWith( 'admin' ) ) {
		return 'adminJs';
	}
	if ( /^dashboard[-.]/i.test( name ) || name.startsWith( 'dashboard' ) ) {
		return 'dashboardJs';
	}
	return 'other';
}

/**
 * Prefer manifest entry files when available (stable logical names).
 *
 * @returns {Map<string, string>} bucket → absolute file path (first match)
 */
function resolveFromManifest() {
	/** @type {Map<string, string>} */
	const map = new Map();
	if ( ! existsSync( manifestPath ) ) {
		return map;
	}
	/** @type {Record<string, { file?: string, isEntry?: boolean, name?: string, css?: string[] }>} */
	const manifest = JSON.parse( readFileSync( manifestPath, 'utf8' ) );
	for ( const chunk of Object.values( manifest ) ) {
		if ( ! chunk || typeof chunk.file !== 'string' ) {
			continue;
		}
		const filePath = path.join( rootDir, 'build', chunk.file );
		const base = path.basename( chunk.file );
		if ( chunk.isEntry || chunk.name === 'admin' || chunk.name === 'dashboard' ) {
			const bucket = classify( base );
			if ( bucket !== 'other' && ! map.has( bucket ) ) {
				map.set( bucket, filePath );
			}
		}
		if ( Array.isArray( chunk.css ) ) {
			for ( const cssRel of chunk.css ) {
				const cssPath = path.join( rootDir, 'build', cssRel );
				if ( ! map.has( 'css' ) && existsSync( cssPath ) ) {
					map.set( 'css', cssPath );
				}
			}
		}
	}
	return map;
}

async function main() {
	if ( ! existsSync( assetsDir ) ) {
		console.error( 'check-bundle-size: build/assets missing — run `npm run build` first.' );
		process.exit( FAIL_HARD ? 1 : 0 );
	}

	const fromManifest = resolveFromManifest();
	const files = readdirSync( assetsDir ).filter( ( n ) => /\.(js|css)$/i.test( n ) );

	/** @type {{ name: string, path: string, bucket: string, raw: number, gzip: number }[]} */
	const rows = [];

	for ( const name of files ) {
		const filePath = path.join( assetsDir, name );
		const raw = statSync( filePath ).size;
		const gzip = await gzipSize( filePath );
		const bucket = classify( name );
		rows.push( { name, path: filePath, bucket, raw, gzip } );
	}

	// Totals per budget bucket (sum chunks in that bucket).
	/** @type {Record<string, number>} */
	const gzipByBucket = { adminJs: 0, dashboardJs: 0, css: 0, other: 0 };
	for ( const row of rows ) {
		gzipByBucket[ row.bucket ] = ( gzipByBucket[ row.bucket ] ?? 0 ) + row.gzip;
	}

	console.log( 'WP BizWit bundle sizes (gzip level 9)\n' );
	console.log( 'File'.padEnd( 36 ) + 'Raw'.padStart( 12 ) + 'Gzip'.padStart( 12 ) + '  Bucket' );
	console.log( '-'.repeat( 72 ) );
	for ( const row of rows.sort( ( a, b ) => a.name.localeCompare( b.name ) ) ) {
		console.log(
			row.name.padEnd( 36 ) +
				formatKiB( row.raw ).padStart( 12 ) +
				formatKiB( row.gzip ).padStart( 12 ) +
				'  ' +
				row.bucket
		);
	}
	console.log( '-'.repeat( 72 ) );

	const checks = [
		{
			label: 'Shared / admin core JS',
			bucket: 'adminJs',
			budgetKiB: BUDGETS_KIB.adminJs,
			bytes: gzipByBucket.adminJs,
		},
		{
			label: 'Dashboard (pilot) chunk JS',
			bucket: 'dashboardJs',
			budgetKiB: BUDGETS_KIB.dashboardJs,
			bytes: gzipByBucket.dashboardJs,
		},
		{
			label: 'Admin CSS (all Tailwind for BizWit)',
			bucket: 'css',
			budgetKiB: BUDGETS_KIB.css,
			bytes: gzipByBucket.css,
		},
	];

	let warnings = 0;
	console.log( '\nBudget check (gzipped)' );
	for ( const check of checks ) {
		const usedKiB = check.bytes / 1024;
		const ok = usedKiB <= check.budgetKiB;
		const status = ok ? 'OK' : 'OVER';
		if ( ! ok ) {
			warnings += 1;
		}
		const primary =
			fromManifest.get( check.bucket ) ??
			rows.find( ( r ) => r.bucket === check.bucket )?.path;
		const hint = primary ? path.basename( primary ) : '(none)';
		console.log(
			`  [${ status }] ${ check.label }: ${ usedKiB.toFixed( 2 ) } / ${ check.budgetKiB } KiB  (${ hint })`
		);
	}

	if ( gzipByBucket.other > 0 ) {
		console.log(
			`  [info] other JS/CSS not in a named budget: ${ formatKiB( gzipByBucket.other ) } gzip`
		);
	}

	console.log(
		'\nNote: dashboard currently inlines Vue. When a second Vue screen lands, extract a shared vendor chunk so admin/core stays within the 60 KiB shared budget.'
	);

	if ( warnings > 0 ) {
		console.warn( `\n${ warnings } budget warning(s). Soft gate — exit 0. Set FAIL_HARD=1 to fail.` );
		if ( FAIL_HARD ) {
			process.exit( 1 );
		}
	} else {
		console.log( '\nAll measured budgets within limits.' );
	}
}

main().catch( ( err ) => {
	console.error( err );
	process.exit( FAIL_HARD ? 1 : 0 );
} );
