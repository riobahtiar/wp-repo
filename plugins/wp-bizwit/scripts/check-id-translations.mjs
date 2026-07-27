/**
 * Fail if languages/wp-bizwit-id_ID.po has incomplete Indonesian translations.
 *
 * Gate against shipping English-only UI when the site locale is id_ID
 * (mixed-language screens). Run after i18n:update-po and before calling a
 * feature "done".
 *
 * Usage: node scripts/check-id-translations.mjs
 * Exit:  0 = complete catalogue · 1 = missing translations
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const poPath = join( root, 'languages', 'wp-bizwit-id_ID.po' );

/**
 * @param {string} source
 * @returns {{ msgid: string, msgstrs: string[], fuzzy: boolean }[]}
 */
function parsePo( source ) {
	const entries = [];
	const blocks = source.replace( /\r\n/g, '\n' ).split( /\n\n+/ );

	for ( const raw of blocks ) {
		// Drop comment-only noise for field detection, but keep #, flags.
		const lines = raw.split( '\n' );
		const codeLines = lines.filter(
			( l ) => ! l.startsWith( '#' ) || l.startsWith( '#,' )
		);
		if ( ! codeLines.some( ( l ) => l.startsWith( 'msgid ' ) ) ) {
			continue;
		}
		if ( lines.some( ( l ) => l.startsWith( '#~' ) ) ) {
			continue;
		}

		const fuzzy = lines.some(
			( l ) => l.startsWith( '#,' ) && /\bfuzzy\b/.test( l )
		);
		const msgid = extractQuoted( codeLines, 'msgid' );
		if ( msgid === '' ) {
			continue; // header
		}

		const msgstrs = extractMsgstrs( codeLines );
		entries.push( { msgid, msgstrs, fuzzy } );
	}

	return entries;
}

/**
 * @param {string[]} lines
 * @param {string} field  e.g. msgid, msgid_plural, msgstr
 * @returns {string}
 */
function extractQuoted( lines, field ) {
	const prefix = field + ' ';
	let i = lines.findIndex( ( l ) => l.startsWith( prefix ) );
	if ( i < 0 ) {
		return '';
	}

	let out = unquote( lines[ i ].slice( prefix.length ) );
	i += 1;
	while ( i < lines.length && lines[ i ].startsWith( '"' ) ) {
		out += unquote( lines[ i ] );
		i += 1;
	}
	return out;
}

/**
 * Collect msgstr or msgstr[N] values (id_ID is nplurals=1 → msgstr[0] only).
 *
 * @param {string[]} lines
 * @returns {string[]}
 */
function extractMsgstrs( lines ) {
	const values = [];
	for ( let i = 0; i < lines.length; i++ ) {
		const line = lines[ i ];
		const m = line.match( /^msgstr(?:\[\d+\])? (.*)$/ );
		if ( ! m ) {
			continue;
		}
		let out = unquote( m[ 1 ] );
		i += 1;
		while ( i < lines.length && lines[ i ].startsWith( '"' ) ) {
			out += unquote( lines[ i ] );
			i += 1;
		}
		i -= 1; // outer loop will +1
		values.push( out );
	}
	return values;
}

/**
 * @param {string} token
 * @returns {string}
 */
function unquote( token ) {
	const t = token.trim();
	if ( t.length < 2 || t[ 0 ] !== '"' || t[ t.length - 1 ] !== '"' ) {
		return t;
	}
	return t
		.slice( 1, -1 )
		.replace( /\\n/g, '\n' )
		.replace( /\\t/g, '\t' )
		.replace( /\\"/g, '"' )
		.replace( /\\\\/g, '\\' );
}

const source = readFileSync( poPath, 'utf8' );
const entries = parsePo( source );

/**
 * Detect accidental concatenation of the same sentence twice (no separator).
 *
 * @param {string} s
 * @returns {boolean}
 */
function isDoubledMsgstr( s ) {
	const t = s.trim();
	if ( t.length < 24 || t.length % 2 !== 0 ) {
		return false;
	}
	const half = t.length / 2;
	return t.slice( 0, half ) === t.slice( half );
}

const missing = entries.filter( ( e ) => {
	if ( e.fuzzy ) {
		return true;
	}
	if ( e.msgstrs.length === 0 ) {
		return true;
	}
	return e.msgstrs.some( ( s ) => s === '' || isDoubledMsgstr( s ) );
} );

if ( missing.length === 0 ) {
	console.log(
		`i18n:check-id OK — ${ entries.length } msgids in wp-bizwit-id_ID.po all have Indonesian msgstr.`
	);
	process.exit( 0 );
}

console.error(
	`i18n:check-id FAILED — ${ missing.length } string(s) incomplete in languages/wp-bizwit-id_ID.po:\n`
);
for ( const e of missing.slice( 0, 40 ) ) {
	const reasons = [];
	if ( e.fuzzy ) {
		reasons.push( 'fuzzy' );
	}
	if ( e.msgstrs.length === 0 || e.msgstrs.some( ( s ) => s === '' ) ) {
		reasons.push( 'empty' );
	}
	if ( e.msgstrs.some( ( s ) => isDoubledMsgstr( s ) ) ) {
		reasons.push( 'doubled-msgstr' );
	}
	const flag = reasons.length ? ` [${ reasons.join( ', ' ) }]` : '';
	const preview = e.msgid.replace( /\n/g, '\\n' ).slice( 0, 120 );
	console.error( `  -${ flag } ${ preview }` );
}
if ( missing.length > 40 ) {
	console.error( `  … and ${ missing.length - 40 } more` );
}
console.error( `
Fill msgstr / msgstr[0] in languages/wp-bizwit-id_ID.po (clear fuzzy flags; fix doubled copy), then:
  npm run i18n:compile
  npm run i18n:check-id

Feature work is not done until this check passes (site language id_ID must not show English leftovers).
` );
process.exit( 1 );
