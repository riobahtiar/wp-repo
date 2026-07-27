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

// Searchable select (resources/admin/searchable-select.ts).
__( 'Code', 'wp-bizwit' );
__( 'No matches found', 'wp-bizwit' );

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

// Document chrome (layout headings + print preview tables).
__( 'Bill to', 'wp-bizwit' );
__( 'Payment details', 'wp-bizwit' );
__( 'Description', 'wp-bizwit' );
__( 'Qty', 'wp-bizwit' );
__( 'Unit', 'wp-bizwit' );
__( 'Unit price', 'wp-bizwit' );
__( 'Amount', 'wp-bizwit' );
__( 'Subtotal', 'wp-bizwit' );
__( 'Total', 'wp-bizwit' );
__( 'Received by', 'wp-bizwit' );
__( 'Name & signature', 'wp-bizwit' );
__( 'Signature and company stamp', 'wp-bizwit' );
__( 'package', 'wp-bizwit' );
