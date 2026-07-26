/**
 * Build Jed 1.x JSON script translations (Gutenberg / wp-i18n format).
 *
 * WordPress loads either:
 *   {domain}-{locale}-{handle}.json
 *   {domain}-{locale}-{md5(relativeSrc)}.json
 *
 * Handles use hyphens (`wp-bizwit-dashboard`) so the filename is valid.
 * Strings are taken from the compiled po (English msgid → translated msgstr).
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/how-to-guides/internationalization.md
 */
import { readFileSync, writeFileSync, readdirSync, unlinkSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const languages = join( root, 'languages' );

/** JS/Vue source files that feed each script handle (for md5 path lookups). */
const HANDLE_SOURCES = {
	'wp-bizwit-dashboard': [
		'resources/screens/dashboard.ts',
		'resources/screens/DashboardApp.vue',
		'resources/app/i18n.ts',
		'src/I18n/Script_Strings.php',
	],
	'wp-bizwit-admin': [
		'resources/admin/main.ts',
		'src/I18n/Script_Strings.php',
	],
};

/** Msgids used by JS (must stay in sync with DashboardApp + Script_Strings). */
const JS_MSGIDS = [
	'System status',
	'Vue pilot panel — verifies REST and money formatting.',
	'Loading…',
	'Could not load status',
	'Could not load system status.',
	'Please try again. Make sure you are still signed in and have a BizWit capability.',
	'Try again',
	'Version',
	'Region',
	'Money format example',
];

function parsePo( text ) {
	const map = new Map();
	const blocks = text.split( /\n\n+/ );
	for ( const block of blocks ) {
		if ( ! block.includes( 'msgid "' ) ) {
			continue;
		}
		let msgid = '';
		let msgstr = '';
		const midMulti = block.match( /msgid ""\n((?:".*"\n?)+)/ );
		if ( midMulti ) {
			msgid = [ ...midMulti[ 1 ].matchAll( /"(.*)"/g ) ]
				.map( ( m ) => m[ 1 ] )
				.join( '' );
		} else {
			const m = block.match( /^msgid "(.*)"/m );
			if ( ! m ) {
				continue;
			}
			msgid = m[ 1 ];
		}
		const strMulti = block.match( /msgstr ""\n((?:".*"\n?)+)/ );
		if ( strMulti ) {
			msgstr = [ ...strMulti[ 1 ].matchAll( /"(.*)"/g ) ]
				.map( ( m ) => m[ 1 ] )
				.join( '' );
		} else {
			const m = block.match( /^msgstr "(.*)"/m );
			msgstr = m ? m[ 1 ] : '';
		}
		msgid = msgid.replace( /\\n/g, '\n' ).replace( /\\"/g, '"' );
		msgstr = msgstr.replace( /\\n/g, '\n' ).replace( /\\"/g, '"' );
		if ( msgid ) {
			map.set( msgid, msgstr || msgid );
		}
	}
	return map;
}

function jedPayload( locale, translations ) {
	const messages = {
		'': {
			domain: 'messages',
			lang: locale.replace( '_', '-' ).toLowerCase(),
			'plural-forms': 'nplurals=1; plural=0;',
		},
	};
	for ( const [ msgid, msgstr ] of Object.entries( translations ) ) {
		messages[ msgid ] = [ msgstr ];
	}
	return {
		'translation-revision-date': new Date().toISOString(),
		generator: 'wp-bizwit/scripts/build-script-translations.mjs',
		domain: 'messages',
		locale_data: {
			messages,
		},
	};
}

function writeJson( filePath, data ) {
	writeFileSync( filePath, JSON.stringify( data ) + '\n', 'utf8' );
	console.log( 'wrote', filePath );
}

// Prefer wp-cli make-json when available (official path).
try {
	execFileSync(
		'wp',
		[ 'i18n', 'make-json', join( languages, 'wp-bizwit-id_ID.po' ), languages, '--no-purge' ],
		{ stdio: 'inherit' }
	);
} catch {
	console.warn( 'wp i18n make-json skipped (wp-cli missing or failed); writing handle JSON only.' );
}

const poPath = join( languages, 'wp-bizwit-id_ID.po' );
const catalog = parsePo( readFileSync( poPath, 'utf8' ) );
const locale = 'id_ID';
const domain = 'wp-bizwit';

const translations = {};
for ( const id of JS_MSGIDS ) {
	translations[ id ] = catalog.get( id ) ?? id;
}

const jed = jedPayload( locale, translations );

// Handle-based filenames (Gutenberg “rename to use the handle” option).
for ( const handle of Object.keys( HANDLE_SOURCES ) ) {
	const file = join( languages, `${ domain }-${ locale }-${ handle }.json` );
	writeJson( file, { ...jed, source: handle } );
}

// Also write md5-of-source-path variants so md5 lookup works for source files.
for ( const sources of Object.values( HANDLE_SOURCES ) ) {
	for ( const rel of sources ) {
		const hash = createHash( 'md5' ).update( rel ).digest( 'hex' );
		const file = join( languages, `${ domain }-${ locale }-${ hash }.json` );
		writeJson( file, { ...jed, source: rel } );
	}
}

// Clean msgmerge backup if present.
try {
	for ( const name of readdirSync( languages ) ) {
		if ( name.endsWith( '.po~' ) ) {
			unlinkSync( join( languages, name ) );
		}
	}
} catch {
	// ignore
}

console.log( 'Script translations ready for wp_set_script_translations().' );
