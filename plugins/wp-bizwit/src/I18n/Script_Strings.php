<?php
/**
 * Catalog of JavaScript UI strings for gettext extraction.
 *
 * These strings are rendered by Vue via resources/app/i18n.ts and WordPress
 * `wp.i18n` (script handle dependency + wp_set_script_translations). They are
 * listed here so `wp i18n make-pot` includes them in the catalogue. This file is
 * never executed at runtime.
 *
 * @package WP_BizWit
 * @see https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/
 */

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Squiz.PHP.CommentedOutCode

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// Dashboard pilot (resources/screens/DashboardApp.vue).
__( 'System status', 'wp-bizwit' );
__( 'Vue pilot panel — verifies REST and money formatting.', 'wp-bizwit' );
__( 'Loading…', 'wp-bizwit' );
__( 'Could not load status', 'wp-bizwit' );
__( 'Could not load system status.', 'wp-bizwit' );
__( 'Please try again. Make sure you are still signed in and have a BizWit capability.', 'wp-bizwit' );
__( 'Try again', 'wp-bizwit' );
__( 'Version', 'wp-bizwit' );
__( 'Region', 'wp-bizwit' );
__( 'Money format example', 'wp-bizwit' );
