<?php
/**
 * Translate document chrome strings stored as English msgids.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

/**
 * Layout JSON stores free-text headings in English (source language).
 * At render time they are passed through gettext so print/preview follow
 * the active locale (user admin locale in wp-admin; site locale on front).
 */
class Document_I18n {

	/**
	 * Known document chrome strings (must stay in sync with default layout).
	 *
	 * Listed explicitly so make-pot always extracts them.
	 *
	 * @return string[]
	 */
	public static function chrome_msgids(): array {
		return array(
			'Bill to',
			'Payment details',
			'Invoice',
			'Description',
			'Qty',
			'Unit',
			'Unit price',
			'Amount',
			'Subtotal',
			'Total',
			'Discount',
			'Paid',
			'Balance',
			'Net expected',
			'Received by',
			'Name & signature',
			'Signature and company stamp',
			'package',
		);
	}

	/**
	 * Translate a stored English chrome string for the active locale.
	 *
	 * @param string $text Stored source string (English msgid).
	 *
	 * @return string Translated text, or original when empty.
	 */
	public static function chrome( string $text ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Document chrome; English source in layout JSON.
		return __( $text, 'wp-bizwit' );
	}

	/**
	 * Register msgids for pot extraction (never called at runtime).
	 *
	 * @return void
	 */
	public static function register_for_pot(): void {
		// Bill to / Payment details / table headers used in print + builder preview.
		__( 'Bill to', 'wp-bizwit' );
		__( 'Payment details', 'wp-bizwit' );
		__( 'Description', 'wp-bizwit' );
		__( 'Qty', 'wp-bizwit' );
		__( 'Unit', 'wp-bizwit' );
		__( 'Unit price', 'wp-bizwit' );
		__( 'Amount', 'wp-bizwit' );
		__( 'Subtotal', 'wp-bizwit' );
		__( 'Total', 'wp-bizwit' );
		__( 'Discount', 'wp-bizwit' );
		__( 'Paid', 'wp-bizwit' );
		__( 'Balance', 'wp-bizwit' );
		__( 'Net expected', 'wp-bizwit' );
		__( 'Received by', 'wp-bizwit' );
		__( 'Name & signature', 'wp-bizwit' );
		__( 'Signature and company stamp', 'wp-bizwit' );
		__( 'package', 'wp-bizwit' );
	}
}
