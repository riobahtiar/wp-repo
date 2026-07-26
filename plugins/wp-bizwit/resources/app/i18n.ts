/**
 * WordPress i18n bridge for Vue/TS.
 *
 * Uses the `wp.i18n` runtime provided by WordPress core (script dep `wp-i18n`).
 * Source strings are English; translations load via wp_set_script_translations().
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */

type I18nApi = {
	__: ( text: string, domain?: string ) => string;
	_x: ( text: string, context: string, domain?: string ) => string;
	_n: (
		single: string,
		plural: string,
		number: number,
		domain?: string
	) => string;
	sprintf: ( format: string, ...args: unknown[] ) => string;
};

function api(): I18nApi | null {
	const w = window as Window & {
		wp?: { i18n?: I18nApi };
	};
	return w.wp?.i18n ?? null;
}

const DOMAIN = 'wp-bizwit';

/** Translate a UI string (English source). */
export function __( text: string, domain: string = DOMAIN ): string {
	const i18n = api();
	return i18n ? i18n.__( text, domain ) : text;
}

/** Translate with context. */
export function _x(
	text: string,
	context: string,
	domain: string = DOMAIN
): string {
	const i18n = api();
	return i18n ? i18n._x( text, context, domain ) : text;
}

/** Plural forms. */
export function _n(
	single: string,
	plural: string,
	number: number,
	domain: string = DOMAIN
): string {
	const i18n = api();
	return i18n ? i18n._n( single, plural, number, domain ) : number === 1 ? single : plural;
}
