/**
 * Re-export WordPress i18n helpers (Gutenberg / wp-i18n package).
 *
 * Usage (same as Gutenberg docs):
 *
 *   import { __ } from '@wordpress/i18n';
 *   // or from this module:
 *   import { __ } from '@/app/i18n';
 *
 * Runtime: Vite externalizes `@wordpress/i18n` to `window.wp.i18n`.
 * PHP must enqueue with dependency `wp-i18n` and call
 * `wp_set_script_translations( $handle, 'wp-bizwit', $languages_path )`.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/how-to-guides/internationalization.md
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */

export { __, _n, _nx, _x, sprintf, isRTL } from '@wordpress/i18n';

/** Default text domain for this plugin (must match plugin header). */
export const TEXT_DOMAIN = 'wp-bizwit';
